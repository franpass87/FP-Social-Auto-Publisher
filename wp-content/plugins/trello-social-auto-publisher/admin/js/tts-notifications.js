/**
 * Enhanced Notification System for Social Auto Publisher
 */

class TTSNotificationSystem {
    constructor() {
        this.container = null;
        this.notifications = new Map();
        this.init();
    }

    init() {
        // Create notification container
        this.container = document.createElement('div');
        this.container.id = 'tts-notification-container';
        this.container.className = 'tts-notifications';
        document.body.appendChild(this.container);

        // Add global styles if not already present
        if (!document.getElementById('tts-notification-styles')) {
            const style = document.createElement('style');
            style.id = 'tts-notification-styles';
            style.textContent = this.getNotificationStyles();
            document.head.appendChild(style);
        }

        // Listen for WordPress admin notices to enhance them
        this.enhanceWordPressNotices();
    }

    getNotificationStyles() {
        return `
            .tts-notifications {
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 100000;
                max-width: 400px;
                pointer-events: none;
            }
            
            .tts-notification {
                background: #fff;
                border-radius: 8px;
                padding: 16px 20px;
                margin-bottom: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                border-left: 4px solid #135e96;
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.320, 1.275);
                pointer-events: auto;
                position: relative;
                overflow: hidden;
            }
            
            .tts-notification.show {
                opacity: 1;
                transform: translateX(0);
            }
            
            .tts-notification.success {
                border-left-color: #00a32a;
            }
            
            .tts-notification.error {
                border-left-color: #d63638;
            }
            
            .tts-notification.warning {
                border-left-color: #f56e28;
            }
            
            .tts-notification.info {
                border-left-color: #135e96;
            }
            
            .tts-notification::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, currentColor, transparent);
                opacity: 0.3;
            }
            
            .tts-notification-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 8px;
            }
            
            .tts-notification-title {
                font-weight: 600;
                color: #1d2327;
                margin: 0;
                font-size: 14px;
            }
            
            .tts-notification-close {
                background: none;
                border: none;
                font-size: 18px;
                cursor: pointer;
                color: #666;
                padding: 0;
                margin-left: 12px;
                transition: color 0.2s;
                width: 20px;
                height: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .tts-notification-close:hover {
                color: #333;
            }
            
            .tts-notification-message {
                color: #50575e;
                font-size: 13px;
                line-height: 1.4;
                margin: 0;
            }
            
            .tts-notification-actions {
                margin-top: 12px;
                display: flex;
                gap: 8px;
            }
            
            .tts-notification-action {
                background: #f0f0f1;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                text-decoration: none;
                color: #1d2327;
            }
            
            .tts-notification-action:hover {
                background: #dcdcde;
                text-decoration: none;
                color: #1d2327;
            }
            
            .tts-notification-action.primary {
                background: #135e96;
                color: #fff;
                border-color: #135e96;
            }
            
            .tts-notification-action.primary:hover {
                background: #0a4b78;
                color: #fff;
            }
            
            .tts-notification-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background: currentColor;
                opacity: 0.3;
                transition: width linear;
            }
            
            .tts-notification.dismissing {
                opacity: 0;
                transform: translateX(100%) scale(0.9);
            }
            
            @media (max-width: 782px) {
                .tts-notifications {
                    top: 46px;
                    right: 10px;
                    left: 10px;
                    max-width: none;
                }
            }
        `;
    }

    show(options) {
        const {
            title = '',
            message = '',
            type = 'info',
            duration = 5000,
            actions = [],
            id = null,
            persistent = false
        } = options;

        // Input validation and sanitization
        if (!message && !title) {
            console.warn('TTSNotificationSystem: Empty notification message and title');
            return null;
        }

        const notificationId = id || 'notification_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        // Remove existing notification with same ID
        if (this.notifications.has(notificationId)) {
            this.dismiss(notificationId);
        }

        try {
            const notification = this.createNotificationElement({
                id: notificationId,
                title: this.escapeHtml(title),
                message: this.escapeHtml(message),
                type,
                actions,
                persistent,
                duration
            });

            this.container.appendChild(notification);
            this.notifications.set(notificationId, notification);

            // Announce to screen readers
            this.announceToScreenReader(message || title, type);

            // Trigger animation
            requestAnimationFrame(() => {
                notification.classList.add('show');
            });

            // Auto dismiss
            if (!persistent && duration > 0) {
                const progressBar = notification.querySelector('.tts-notification-progress');
                if (progressBar) {
                    progressBar.style.width = '100%';
                    progressBar.style.transition = `width ${duration}ms linear`;
                    requestAnimationFrame(() => {
                        progressBar.style.width = '0%';
                    });
                }

                setTimeout(() => {
                    this.dismiss(notificationId);
                }, duration);
            }

            return notificationId;
        } catch (error) {
            console.error('TTSNotificationSystem: Error creating notification:', error);
            return null;
        }
    }

    createNotificationElement(options) {
        const { id, title, message, type, actions, persistent } = options;

        const notification = document.createElement('div');
        notification.className = `tts-notification ${type}`;
        notification.setAttribute('data-id', id);

        const header = document.createElement('div');
        header.className = 'tts-notification-header';

        if (title) {
            const titleElement = document.createElement('h4');
            titleElement.className = 'tts-notification-title';
            titleElement.textContent = title;
            header.appendChild(titleElement);
        }

        const closeButton = document.createElement('button');
        closeButton.className = 'tts-notification-close';
        closeButton.innerHTML = '×';
        closeButton.onclick = () => this.dismiss(id);
        header.appendChild(closeButton);

        notification.appendChild(header);

        if (message) {
            const messageElement = document.createElement('p');
            messageElement.className = 'tts-notification-message';
            messageElement.textContent = message;
            notification.appendChild(messageElement);
        }

        if (actions && actions.length > 0) {
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'tts-notification-actions';

            actions.forEach(action => {
                const actionElement = document.createElement(action.href ? 'a' : 'button');
                actionElement.className = `tts-notification-action ${action.primary ? 'primary' : ''}`;
                actionElement.textContent = action.label;

                if (action.href) {
                    actionElement.href = action.href;
                } else if (action.onClick) {
                    actionElement.onclick = action.onClick;
                }

                actionsContainer.appendChild(actionElement);
            });

            notification.appendChild(actionsContainer);
        }

        if (!persistent) {
            const progressBar = document.createElement('div');
            progressBar.className = 'tts-notification-progress';
            notification.appendChild(progressBar);
        }

        return notification;
    }

    dismiss(id) {
        const notification = this.notifications.get(id);
        if (!notification) return;

        notification.classList.add('dismissing');

        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
            this.notifications.delete(id);
        }, 400);
    }

    dismissAll() {
        this.notifications.forEach((notification, id) => {
            this.dismiss(id);
        });
    }

    enhanceWordPressNotices() {
        // Replace WordPress admin notices with enhanced versions
        const notices = document.querySelectorAll('.notice:not(.tts-enhanced)');
        notices.forEach(notice => {
            const type = notice.classList.contains('notice-error') ? 'error' :
                        notice.classList.contains('notice-warning') ? 'warning' :
                        notice.classList.contains('notice-success') ? 'success' : 'info';

            const message = notice.textContent.trim();
            if (message) {
                this.show({
                    message,
                    type,
                    duration: 8000
                });

                notice.style.display = 'none';
                notice.classList.add('tts-enhanced');
            }
        });
    }

    // Helper methods for improved error handling and accessibility
    escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    announceToScreenReader(message, type) {
        if (!message) return;
        
        // Create temporary element for screen reader announcement
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.style.cssText = 'position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden;';
        announcement.textContent = `${type}: ${message}`;
        
        document.body.appendChild(announcement);
        
        // Remove after announcement
        setTimeout(() => {
            if (announcement.parentNode) {
                announcement.parentNode.removeChild(announcement);
            }
        }, 1000);
    }

    // Convenience methods
    success(message, options = {}) {
        return this.show({ ...options, message, type: 'success' });
    }

    error(message, options = {}) {
        return this.show({ ...options, message, type: 'error', duration: 0 });
    }

    warning(message, options = {}) {
        return this.show({ ...options, message, type: 'warning' });
    }

    info(message, options = {}) {
        return this.show({ ...options, message, type: 'info' });
    }
}

// Global instance
window.TTSNotifications = new TTSNotificationSystem();

// WordPress integration
document.addEventListener('DOMContentLoaded', function() {
    // Monitor for new WordPress notices
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1 && node.classList && node.classList.contains('notice')) {
                    window.TTSNotifications.enhanceWordPressNotices();
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Show welcome message if on plugin pages
    if (window.location.href.includes('page=tts-')) {
        setTimeout(() => {
            window.TTSNotifications.info('Enhanced interface loaded successfully!', {
                duration: 3000
            });
        }, 1000);
    }
});