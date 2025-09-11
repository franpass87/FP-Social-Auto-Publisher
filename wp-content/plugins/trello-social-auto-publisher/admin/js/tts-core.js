/**
 * Core JavaScript functionality for Trello Social Auto Publisher
 * Consolidated common functionality used across all admin pages
 */

(function($) {
    'use strict';

    // Global TTS object
    window.TTS = window.TTS || {};

    /**
     * Core functionality initialization
     */
    TTS.Core = {
        
        // Configuration
        config: {
            ajaxUrl: tts_ajax ? tts_ajax.ajax_url : ajaxurl,
            nonce: tts_ajax ? tts_ajax.nonce : '',
            loadingClass: 'loading',
            disabledClass: 'disabled'
        },

        /**
         * Initialize core functionality
         */
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.initNotifications();
            this.initAjaxForms();
            this.initConfirmations();
            this.setupGlobalKeyHandlers();
        },

        /**
         * Bind global events
         */
        bindEvents: function() {
            // Handle all AJAX buttons
            $(document).on('click', '[data-ajax-action]', this.handleAjaxAction.bind(this));
            
            // Handle form submissions
            $(document).on('submit', '.tts-ajax-form', this.handleAjaxForm.bind(this));
            
            // Handle confirmations
            $(document).on('click', '[data-confirm]', this.handleConfirmation.bind(this));
            
            // Handle bulk selections
            $(document).on('change', '.tts-bulk-select-all', this.handleBulkSelectAll.bind(this));
            $(document).on('change', '.tts-bulk-select-item', this.updateBulkSelectAll.bind(this));
            
            // Handle loading states
            $(document).on('ajaxStart', this.showGlobalLoading.bind(this));
            $(document).on('ajaxStop', this.hideGlobalLoading.bind(this));
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Simple tooltip implementation
            $(document).on('mouseenter', '[data-tooltip]', function() {
                const $el = $(this);
                const text = $el.data('tooltip');
                if (!text) return;

                const $tooltip = $('<div class="tts-tooltip-popup"></div>').text(text);
                $('body').append($tooltip);

                const offset = $el.offset();
                $tooltip.css({
                    position: 'absolute',
                    top: offset.top - $tooltip.outerHeight() - 5,
                    left: offset.left + ($el.outerWidth() / 2) - ($tooltip.outerWidth() / 2),
                    zIndex: 9999
                });
            });

            $(document).on('mouseleave', '[data-tooltip]', function() {
                $('.tts-tooltip-popup').remove();
            });
        },

        /**
         * Initialize notifications system
         */
        initNotifications: function() {
            // Auto-hide notifications after 5 seconds
            $('.tts-notification:not(.permanent)').each(function() {
                const $notification = $(this);
                setTimeout(function() {
                    $notification.fadeOut();
                }, 5000);
            });
        },

        /**
         * Initialize AJAX forms
         */
        initAjaxForms: function() {
            $('.tts-ajax-form').each(function() {
                const $form = $(this);
                if (!$form.data('ajax-initialized')) {
                    $form.data('ajax-initialized', true);
                }
            });
        },

        /**
         * Initialize confirmations
         */
        initConfirmations: function() {
            // Already handled in bindEvents
        },

        /**
         * Setup global keyboard handlers
         */
        setupGlobalKeyHandlers: function() {
            $(document).on('keydown', function(e) {
                // Escape key - close modals/tooltips
                if (e.key === 'Escape') {
                    $('.tts-tooltip-popup').remove();
                    $('.tts-modal.active').removeClass('active');
                }

                // Ctrl+S - Save forms
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    $('.tts-form-save:visible').first().click();
                }
            });
        },

        /**
         * Handle AJAX actions
         */
        handleAjaxAction: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const action = $button.data('ajax-action');
            const confirmText = $button.data('confirm');
            
            if (!action) return;

            // Confirmation check
            if (confirmText && !confirm(confirmText)) {
                return;
            }

            this.performAjaxAction($button, action);
        },

        /**
         * Perform AJAX action
         */
        performAjaxAction: function($button, action, extraData = {}) {
            const loadingText = $button.data('loading-text') || 'Processing...';
            const originalText = $button.text();
            
            // Set loading state
            $button.addClass(this.config.loadingClass)
                   .prop('disabled', true)
                   .text(loadingText);

            // Prepare data
            const data = $.extend({
                action: action,
                nonce: this.config.nonce
            }, $button.data(), extraData);

            // Remove non-data attributes
            delete data.ajaxAction;
            delete data.confirm;
            delete data.loadingText;

            // Perform AJAX request
            $.post(this.config.ajaxUrl, data)
                .done((response) => {
                    this.handleAjaxResponse(response, $button);
                })
                .fail((xhr) => {
                    this.handleAjaxError(xhr, $button);
                })
                .always(() => {
                    // Reset button state
                    $button.removeClass(this.config.loadingClass)
                           .prop('disabled', false)
                           .text(originalText);
                });
        },

        /**
         * Handle AJAX form submissions
         */
        handleAjaxForm: function(e) {
            e.preventDefault();
            
            const $form = $(e.currentTarget);
            const $submitBtn = $form.find('[type="submit"]');
            const action = $form.data('ajax-action');
            
            if (!action) {
                $form.off('submit').submit();
                return;
            }

            const formData = new FormData($form[0]);
            formData.append('action', action);
            formData.append('nonce', this.config.nonce);

            // Set loading state
            $submitBtn.addClass(this.config.loadingClass).prop('disabled', true);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done((response) => {
                this.handleAjaxResponse(response, $form);
            })
            .fail((xhr) => {
                this.handleAjaxError(xhr, $form);
            })
            .always(() => {
                $submitBtn.removeClass(this.config.loadingClass).prop('disabled', false);
            });
        },

        /**
         * Handle AJAX responses
         */
        handleAjaxResponse: function(response, $element) {
            if (response.success) {
                this.showNotification(response.data.message || 'Action completed successfully', 'success');
                
                // Handle specific response actions
                if (response.data.action) {
                    this.handleResponseAction(response.data.action, response.data, $element);
                }
            } else {
                this.showNotification(response.data.message || 'Action failed', 'error');
            }
        },

        /**
         * Handle AJAX errors
         */
        handleAjaxError: function(xhr, $element) {
            let message = 'An error occurred';
            
            if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            } else if (xhr.responseText) {
                message = 'Server error: ' + xhr.status;
            }
            
            this.showNotification(message, 'error');
        },

        /**
         * Handle specific response actions
         */
        handleResponseAction: function(action, data, $element) {
            switch (action) {
                case 'refresh':
                    location.reload();
                    break;
                case 'redirect':
                    if (data.url) {
                        window.location.href = data.url;
                    }
                    break;
                case 'update_element':
                    if (data.selector && data.html) {
                        $(data.selector).html(data.html);
                    }
                    break;
                case 'remove_element':
                    if (data.selector) {
                        $(data.selector).fadeOut();
                    }
                    break;
            }
        },

        /**
         * Handle confirmations
         */
        handleConfirmation: function(e) {
            const $element = $(e.currentTarget);
            const confirmText = $element.data('confirm');
            
            if (confirmText && !confirm(confirmText)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        },

        /**
         * Handle bulk select all
         */
        handleBulkSelectAll: function(e) {
            const $checkbox = $(e.currentTarget);
            const isChecked = $checkbox.prop('checked');
            
            $('.tts-bulk-select-item').prop('checked', isChecked);
            this.updateBulkActions();
        },

        /**
         * Update bulk select all checkbox
         */
        updateBulkSelectAll: function() {
            const total = $('.tts-bulk-select-item').length;
            const checked = $('.tts-bulk-select-item:checked').length;
            
            $('.tts-bulk-select-all').prop('checked', total > 0 && checked === total);
            this.updateBulkActions();
        },

        /**
         * Update bulk actions availability
         */
        updateBulkActions: function() {
            const hasSelected = $('.tts-bulk-select-item:checked').length > 0;
            $('.tts-bulk-actions').toggleClass('has-selection', hasSelected);
        },

        /**
         * Show loading indicator
         */
        showGlobalLoading: function() {
            if (!$('#tts-global-loading').length) {
                $('body').append('<div id="tts-global-loading" class="tts-loading-overlay"><div class="tts-spinner"></div></div>');
            }
        },

        /**
         * Hide loading indicator
         */
        hideGlobalLoading: function() {
            $('#tts-global-loading').remove();
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'info', duration = 5000) {
            const $notification = $(`
                <div class="tts-notification ${type} tts-notification-dynamic">
                    <span class="tts-notification-content">${message}</span>
                    <button class="tts-notification-close">&times;</button>
                </div>
            `);

            // Add to page
            if (!$('#tts-notifications-container').length) {
                $('body').append('<div id="tts-notifications-container"></div>');
            }
            
            $('#tts-notifications-container').append($notification);

            // Auto-hide
            if (duration > 0) {
                setTimeout(() => {
                    $notification.fadeOut(() => $notification.remove());
                }, duration);
            }

            // Manual close
            $notification.find('.tts-notification-close').on('click', () => {
                $notification.fadeOut(() => $notification.remove());
            });
        },

        /**
         * Utility: Debounce function
         */
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        /**
         * Utility: Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Utility: Format date
         */
        formatDate: function(date, format = 'Y-m-d H:i:s') {
            const d = new Date(date);
            const pad = (num) => num.toString().padStart(2, '0');
            
            const replacements = {
                'Y': d.getFullYear(),
                'm': pad(d.getMonth() + 1),
                'd': pad(d.getDate()),
                'H': pad(d.getHours()),
                'i': pad(d.getMinutes()),
                's': pad(d.getSeconds())
            };

            return format.replace(/[Ymddhis]/g, (match) => replacements[match] || match);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        TTS.Core.init();
    });

})(jQuery);