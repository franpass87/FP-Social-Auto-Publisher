/**
 * Enhanced Admin Utilities for Social Auto Publisher
 * Provides advanced functionality like bulk operations, confirmations, and AJAX helpers
 */

class TTSAdminUtils {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.enhanceExistingElements();
        this.addCustomStyles();
    }

    addCustomStyles() {
        if (document.getElementById('tts-admin-utils-styles')) return;

        const style = document.createElement('style');
        style.id = 'tts-admin-utils-styles';
        style.textContent = `
            /* Enhanced Modal Styles */
            .tts-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
                opacity: 0;
                transition: opacity 0.3s ease;
                backdrop-filter: blur(2px);
            }
            
            .tts-modal-overlay.show {
                opacity: 1;
            }
            
            .tts-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.9);
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                max-width: 500px;
                width: 90%;
                z-index: 100001;
                opacity: 0;
                transition: all 0.3s cubic-bezier(0.175, 0.885, 0.320, 1.275);
            }
            
            .tts-modal.show {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            
            .tts-modal-header {
                padding: 20px 20px 0 20px;
                border-bottom: 1px solid #f0f0f1;
                margin-bottom: 20px;
            }
            
            .tts-modal-title {
                margin: 0 0 10px 0;
                font-size: 18px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .tts-modal-body {
                padding: 0 20px 20px 20px;
                color: #50575e;
                line-height: 1.5;
            }
            
            .tts-modal-footer {
                padding: 20px;
                border-top: 1px solid #f0f0f1;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            
            .tts-modal-close {
                position: absolute;
                top: 15px;
                right: 15px;
                background: none;
                border: none;
                font-size: 20px;
                cursor: pointer;
                color: #666;
                width: 30px;
                height: 30px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }
            
            .tts-modal-close:hover {
                background: #f0f0f1;
                color: #333;
            }
            
            /* Enhanced Buttons */
            .tts-btn-enhanced {
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            
            .tts-btn-enhanced::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                transition: width 0.3s, height 0.3s;
            }
            
            .tts-btn-enhanced:hover::before {
                width: 300px;
                height: 300px;
            }
            
            /* Loading States */
            .tts-loading-btn {
                position: relative;
                pointer-events: none;
                opacity: 0.7;
            }
            
            .tts-loading-btn::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 16px;
                height: 16px;
                margin: -8px 0 0 -8px;
                border: 2px solid transparent;
                border-top: 2px solid currentColor;
                border-radius: 50%;
                animation: tts-spin 1s linear infinite;
            }
            
            @keyframes tts-spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            /* Progress Bars */
            .tts-progress {
                width: 100%;
                height: 8px;
                background: #f0f0f1;
                border-radius: 4px;
                overflow: hidden;
                margin: 10px 0;
            }
            
            .tts-progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #135e96, #2271b1);
                width: 0%;
                transition: width 0.3s ease;
                position: relative;
            }
            
            .tts-progress-bar::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
                animation: tts-progress-shine 1.5s infinite;
            }
            
            @keyframes tts-progress-shine {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
            
            /* Enhanced Tables */
            .tts-enhanced-table {
                background: #fff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            }
            
            .tts-enhanced-table th {
                background: #f8f9fa;
                font-weight: 600;
                position: sticky;
                top: 0;
                z-index: 10;
            }
            
            .tts-enhanced-table tr:hover {
                background: #f8f9fa;
            }
            
            .tts-enhanced-table .row-actions {
                opacity: 0;
                transition: opacity 0.2s;
            }
            
            .tts-enhanced-table tr:hover .row-actions {
                opacity: 1;
            }
            
            /* Bulk Actions */
            .tts-bulk-actions {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 15px;
                margin: 15px 0;
                display: none;
            }
            
            .tts-bulk-actions.show {
                display: block;
                animation: tts-fadeInDown 0.3s ease;
            }
            
            @keyframes tts-fadeInDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Status Badges */
            .tts-status-badge {
                display: inline-flex;
                align-items: center;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .tts-status-badge.success {
                background: rgba(0, 163, 42, 0.1);
                color: #00a32a;
            }
            
            .tts-status-badge.error {
                background: rgba(214, 54, 56, 0.1);
                color: #d63638;
            }
            
            .tts-status-badge.warning {
                background: rgba(245, 110, 40, 0.1);
                color: #f56e28;
            }
            
            .tts-status-badge.info {
                background: rgba(19, 94, 150, 0.1);
                color: #135e96;
            }
            
            /* Search and Filter Enhancements */
            .tts-search-container {
                position: relative;
                display: inline-block;
            }
            
            .tts-search-input {
                padding-left: 35px;
                border-radius: 20px;
                border: 1px solid #ddd;
                transition: all 0.3s ease;
            }
            
            .tts-search-input:focus {
                border-color: #135e96;
                box-shadow: 0 0 0 3px rgba(19, 94, 150, 0.1);
            }
            
            .tts-search-icon {
                position: absolute;
                left: 12px;
                top: 50%;
                transform: translateY(-50%);
                color: #666;
                pointer-events: none;
            }
            
            /* Responsive Enhancements */
            @media (max-width: 768px) {
                .tts-modal {
                    width: 95%;
                    margin: 20px;
                }
                
                .tts-modal-footer {
                    flex-direction: column;
                }
                
                .tts-modal-footer .tts-btn {
                    width: 100%;
                    margin-bottom: 5px;
                }
            }
        `;
        document.head.appendChild(style);
    }

    bindEvents() {
        // Enhanced bulk selection
        document.addEventListener('change', (e) => {
            if (e.target.matches('.tts-bulk-select-all')) {
                this.handleBulkSelectAll(e.target);
            } else if (e.target.matches('.tts-bulk-select-item')) {
                this.handleBulkSelectItem();
            }
        });

        // Enhanced form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('.tts-enhanced-form')) {
                this.handleEnhancedForm(e);
            }
        });

        // Confirmation dialogs
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-confirm]')) {
                e.preventDefault();
                this.showConfirmationDialog(e.target);
            }
        });

        // AJAX actions
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-ajax-action]')) {
                e.preventDefault();
                this.handleAjaxAction(e.target);
            }
        });
    }

    enhanceExistingElements() {
        // Enhance existing tables
        document.querySelectorAll('.widefat').forEach(table => {
            if (!table.classList.contains('tts-enhanced-table')) {
                table.classList.add('tts-enhanced-table');
            }
        });

        // Enhance existing buttons
        document.querySelectorAll('.button').forEach(button => {
            if (!button.classList.contains('tts-btn-enhanced')) {
                button.classList.add('tts-btn-enhanced');
            }
        });

        // Add search functionality to existing lists
        this.addSearchToLists();
    }

    handleBulkSelectAll(selectAllCheckbox) {
        const form = selectAllCheckbox.closest('form') || document;
        const checkboxes = form.querySelectorAll('.tts-bulk-select-item');
        const bulkActions = form.querySelector('.tts-bulk-actions');

        checkboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });

        if (bulkActions) {
            bulkActions.classList.toggle('show', selectAllCheckbox.checked);
        }
    }

    handleBulkSelectItem() {
        const form = event.target.closest('form') || document;
        const checkboxes = form.querySelectorAll('.tts-bulk-select-item');
        const selectAll = form.querySelector('.tts-bulk-select-all');
        const bulkActions = form.querySelector('.tts-bulk-actions');

        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
        const totalCount = checkboxes.length;

        if (selectAll) {
            selectAll.checked = checkedCount === totalCount;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }

        if (bulkActions) {
            bulkActions.classList.toggle('show', checkedCount > 0);
        }
    }

    showConfirmationDialog(element) {
        const message = element.getAttribute('data-confirm');
        const title = element.getAttribute('data-confirm-title') || 'Confirm Action';
        const confirmText = element.getAttribute('data-confirm-button') || 'Confirm';
        const cancelText = element.getAttribute('data-cancel-button') || 'Cancel';
        const dangerous = element.hasAttribute('data-dangerous');

        const modal = this.createModal({
            title,
            body: message,
            buttons: [
                {
                    text: cancelText,
                    class: 'button',
                    onclick: () => this.closeModal(modal)
                },
                {
                    text: confirmText,
                    class: dangerous ? 'button-primary button-danger' : 'button-primary',
                    onclick: () => {
                        this.closeModal(modal);
                        if (element.href) {
                            window.location.href = element.href;
                        } else if (element.onclick) {
                            element.onclick();
                        } else if (element.type === 'submit') {
                            element.closest('form').submit();
                        }
                    }
                }
            ]
        });

        this.showModal(modal);
    }

    async handleAjaxAction(element) {
        const action = element.getAttribute('data-ajax-action');
        const data = this.getElementData(element);
        const loadingText = element.getAttribute('data-loading-text') || 'Loading...';
        const originalText = element.textContent;

        // Show loading state
        element.classList.add('tts-loading-btn');
        element.textContent = loadingText;

        try {
            const response = await this.ajaxRequest(action, data);
            
            if (response.success) {
                window.TTSNotifications.success(response.data.message || 'Action completed successfully');
                
                // Handle redirect
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                    return;
                }
                
                // Handle refresh
                if (response.data.refresh) {
                    window.location.reload();
                    return;
                }
                
                // Handle custom callback
                const callback = element.getAttribute('data-success-callback');
                if (callback && window[callback]) {
                    window[callback](response.data);
                }
            } else {
                throw new Error(response.data || 'Action failed');
            }
        } catch (error) {
            window.TTSNotifications.error(error.message || 'An error occurred');
        } finally {
            // Restore button state
            element.classList.remove('tts-loading-btn');
            element.textContent = originalText;
        }
    }

    getElementData(element) {
        const data = {};
        
        // Get data attributes
        Array.from(element.attributes).forEach(attr => {
            if (attr.name.startsWith('data-') && attr.name !== 'data-ajax-action') {
                const key = attr.name.replace('data-', '').replace(/-([a-z])/g, (g) => g[1].toUpperCase());
                data[key] = attr.value;
            }
        });
        
        // Add WordPress nonce
        data.nonce = window.ajaxurl ? wp.ajax.settings.nonce : '';
        
        return data;
    }

    async ajaxRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        
        Object.keys(data).forEach(key => {
            formData.append(key, data[key]);
        });

        const response = await fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const result = await response.json();
        return result;
    }

    createModal({ title, body, buttons = [] }) {
        const overlay = document.createElement('div');
        overlay.className = 'tts-modal-overlay';

        const modal = document.createElement('div');
        modal.className = 'tts-modal';

        modal.innerHTML = `
            <div class="tts-modal-header">
                <h3 class="tts-modal-title">${title}</h3>
                <button class="tts-modal-close" type="button">×</button>
            </div>
            <div class="tts-modal-body">
                ${body}
            </div>
            ${buttons.length > 0 ? `
                <div class="tts-modal-footer">
                    ${buttons.map(btn => `
                        <button class="button ${btn.class || ''}" type="button">${btn.text}</button>
                    `).join('')}
                </div>
            ` : ''}
        `;

        // Bind button events
        buttons.forEach((btn, index) => {
            const buttonElement = modal.querySelectorAll('.tts-modal-footer button')[index];
            if (btn.onclick) {
                buttonElement.onclick = btn.onclick;
            }
        });

        // Bind close events
        modal.querySelector('.tts-modal-close').onclick = () => this.closeModal(overlay);
        overlay.onclick = (e) => {
            if (e.target === overlay) {
                this.closeModal(overlay);
            }
        };

        overlay.appendChild(modal);
        return overlay;
    }

    showModal(modalOverlay) {
        document.body.appendChild(modalOverlay);
        requestAnimationFrame(() => {
            modalOverlay.classList.add('show');
            modalOverlay.querySelector('.tts-modal').classList.add('show');
        });
    }

    closeModal(modalOverlay) {
        modalOverlay.classList.remove('show');
        modalOverlay.querySelector('.tts-modal').classList.remove('show');
        
        setTimeout(() => {
            if (modalOverlay.parentNode) {
                modalOverlay.parentNode.removeChild(modalOverlay);
            }
        }, 300);
    }

    addSearchToLists() {
        document.querySelectorAll('.tts-searchable-list').forEach(list => {
            if (list.querySelector('.tts-search-container')) return;

            const searchContainer = document.createElement('div');
            searchContainer.className = 'tts-search-container';
            searchContainer.innerHTML = `
                <span class="tts-search-icon">🔍</span>
                <input type="text" class="tts-search-input" placeholder="Search...">
            `;

            list.insertBefore(searchContainer, list.firstChild);

            const searchInput = searchContainer.querySelector('.tts-search-input');
            searchInput.addEventListener('input', (e) => {
                this.filterList(list, e.target.value);
            });
        });
    }

    filterList(list, searchTerm) {
        const items = list.querySelectorAll('.tts-list-item, tr');
        const term = searchTerm.toLowerCase();

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(term) ? '' : 'none';
        });
    }

    showProgress(element, percentage) {
        let progressBar = element.querySelector('.tts-progress');
        
        if (!progressBar) {
            progressBar = document.createElement('div');
            progressBar.className = 'tts-progress';
            progressBar.innerHTML = '<div class="tts-progress-bar"></div>';
            element.appendChild(progressBar);
        }

        const bar = progressBar.querySelector('.tts-progress-bar');
        bar.style.width = percentage + '%';
    }

    // Utility methods
    formatDate(date) {
        return new Intl.DateTimeFormat('default', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(date));
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // Form Validation Utilities
    validateForm(form) {
        const fields = form.querySelectorAll('input[required], select[required], textarea[required]');
        let isValid = true;
        let firstInvalidField = null;

        fields.forEach(field => {
            const isFieldValid = this.validateField(field);
            if (!isFieldValid && isValid) {
                isValid = false;
                firstInvalidField = field;
            }
        });

        // Focus first invalid field for accessibility
        if (firstInvalidField) {
            firstInvalidField.focus();
            this.announceError('Please check the form for errors and try again.');
        }

        return isValid;
    }

    validateField(field) {
        const value = field.value.trim();
        const type = field.type;
        const required = field.hasAttribute('required');
        let isValid = true;
        let errorMessage = '';

        // Remove existing error states
        this.clearFieldError(field);

        // Required field validation
        if (required && !value) {
            isValid = false;
            errorMessage = 'This field is required.';
        }

        // Email validation
        if (type === 'email' && value && !this.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address.';
        }

        // URL validation for URL fields
        if (type === 'url' && value && !this.isValidUrl(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid URL.';
        }

        // Custom validation patterns
        const pattern = field.getAttribute('pattern');
        if (pattern && value && !new RegExp(pattern).test(value)) {
            isValid = false;
            errorMessage = field.getAttribute('data-pattern-error') || 'Invalid format.';
        }

        // Show error state if invalid
        if (!isValid) {
            this.showFieldError(field, errorMessage);
        } else {
            this.showFieldSuccess(field);
        }

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('tts-field-error');
        field.setAttribute('aria-invalid', 'true');
        
        // Add error message
        let errorElement = field.nextElementSibling;
        if (!errorElement || !errorElement.classList.contains('tts-error-message')) {
            errorElement = document.createElement('div');
            errorElement.className = 'tts-error-message';
            errorElement.setAttribute('role', 'alert');
            field.parentNode.insertBefore(errorElement, field.nextSibling);
        }
        errorElement.textContent = message;
    }

    showFieldSuccess(field) {
        field.classList.add('tts-field-success');
        field.setAttribute('aria-invalid', 'false');
    }

    clearFieldError(field) {
        field.classList.remove('tts-field-error', 'tts-field-success');
        field.removeAttribute('aria-invalid');
        
        const errorElement = field.nextElementSibling;
        if (errorElement && errorElement.classList.contains('tts-error-message')) {
            errorElement.remove();
        }
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    // Screen reader announcements
    announceError(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'assertive');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.style.position = 'absolute';
        announcement.style.left = '-10000px';
        announcement.style.width = '1px';
        announcement.style.height = '1px';
        announcement.style.overflow = 'hidden';
        announcement.textContent = message;
        
        document.body.appendChild(announcement);
        setTimeout(() => document.body.removeChild(announcement), 1000);
    }
}

// Initialize admin utils
window.TTSAdminUtils = new TTSAdminUtils();

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TTSAdminUtils;
}