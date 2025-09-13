/**
 * Optimized Core JavaScript for FP Publisher
 * Consolidated and performance-optimized utilities
 * Version: 1.4.0
 */

(function($) {
    'use strict';

    // Namespace for TTS functionality
    window.TTS = window.TTS || {};

    /**
     * Performance optimized utilities
     */
    TTS.Utils = {
        // Debounce function for performance
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        // Throttle function for scroll events
        throttle: function(func, limit) {
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
        },

        // Optimized DOM ready check
        ready: function(callback) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback);
            } else {
                callback();
            }
        },

        // Safe AJAX requests with retry logic
        ajax: function(options) {
            const defaults = {
                url: tts_ajax.ajax_url,
                type: 'POST',
                dataType: 'json',
                timeout: 30000,
                retries: 3,
                retryDelay: 1000,
                data: {
                    nonce: tts_ajax.nonce
                }
            };

            const settings = $.extend(true, {}, defaults, options);
            let retryCount = 0;

            function makeRequest() {
                return $.ajax(settings)
                    .fail(function(xhr, status, error) {
                        if (retryCount < settings.retries && 
                            (status === 'timeout' || xhr.status >= 500)) {
                            retryCount++;
                            setTimeout(makeRequest, settings.retryDelay * retryCount);
                        } else {
                            TTS.Notifications.error('Request failed: ' + error);
                        }
                    });
            }

            return makeRequest();
        },

        // Sanitize HTML to prevent XSS
        sanitizeHTML: function(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },

        // Format dates consistently
        formatDate: function(date, format = 'Y-m-d H:i') {
            const d = new Date(date);
            const formats = {
                'Y': d.getFullYear(),
                'm': String(d.getMonth() + 1).padStart(2, '0'),
                'd': String(d.getDate()).padStart(2, '0'),
                'H': String(d.getHours()).padStart(2, '0'),
                'i': String(d.getMinutes()).padStart(2, '0'),
                's': String(d.getSeconds()).padStart(2, '0')
            };

            return format.replace(/[Ymdis]/g, match => formats[match]);
        },

        // Local storage with fallback
        storage: {
            set: function(key, value) {
                try {
                    localStorage.setItem('tts_' + key, JSON.stringify(value));
                } catch (e) {
                    // Fallback to cookie if localStorage fails
                    document.cookie = `tts_${key}=${JSON.stringify(value)}; path=/`;
                }
            },

            get: function(key, defaultValue = null) {
                try {
                    const item = localStorage.getItem('tts_' + key);
                    return item ? JSON.parse(item) : defaultValue;
                } catch (e) {
                    // Fallback to cookie
                    const match = document.cookie.match(new RegExp(`tts_${key}=([^;]+)`));
                    return match ? JSON.parse(match[1]) : defaultValue;
                }
            },

            remove: function(key) {
                try {
                    localStorage.removeItem('tts_' + key);
                } catch (e) {
                    document.cookie = `tts_${key}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;`;
                }
            }
        }
    };

    /**
     * Optimized notification system
     */
    TTS.Notifications = {
        container: null,
        
        init: function() {
            if (!this.container) {
                this.container = $('<div class="tts-notifications"></div>')
                    .appendTo('body')
                    .css({
                        position: 'fixed',
                        top: '20px',
                        right: '20px',
                        zIndex: 9999,
                        maxWidth: '400px'
                    });
            }
        },

        show: function(message, type = 'info', duration = 5000) {
            this.init();
            
            const notification = $(`
                <div class="tts-notice tts-notice-${type}" style="
                    margin-bottom: 10px;
                    padding: 15px;
                    border-radius: 6px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    background: white;
                    border-left: 4px solid var(--tts-${type});
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                ">
                    <button type="button" class="tts-notice-dismiss" style="
                        float: right;
                        background: none;
                        border: none;
                        font-size: 18px;
                        cursor: pointer;
                        line-height: 1;
                        color: #666;
                    ">&times;</button>
                    <div class="tts-notice-message">${TTS.Utils.sanitizeHTML(message)}</div>
                </div>
            `);

            // Animate in
            this.container.append(notification);
            setTimeout(() => {
                notification.css({
                    opacity: 1,
                    transform: 'translateX(0)'
                });
            }, 10);

            // Auto remove
            if (duration > 0) {
                setTimeout(() => this.remove(notification), duration);
            }

            // Manual remove
            notification.find('.tts-notice-dismiss').on('click', () => {
                this.remove(notification);
            });

            return notification;
        },

        remove: function(notification) {
            notification.css({
                opacity: 0,
                transform: 'translateX(100%)'
            });
            
            setTimeout(() => notification.remove(), 300);
        },

        success: function(message, duration) {
            return this.show(message, 'success', duration);
        },

        error: function(message, duration) {
            return this.show(message, 'error', duration);
        },

        warning: function(message, duration) {
            return this.show(message, 'warning', duration);
        },

        info: function(message, duration) {
            return this.show(message, 'info', duration);
        }
    };

    /**
     * Loading state management
     */
    TTS.Loading = {
        show: function(element, text = 'Loading...') {
            const $element = $(element);
            $element.addClass('tts-loading')
                .attr('data-original-text', $element.text())
                .text(text)
                .prop('disabled', true);
        },

        hide: function(element) {
            const $element = $(element);
            const originalText = $element.attr('data-original-text');
            $element.removeClass('tts-loading')
                .text(originalText || $element.text())
                .prop('disabled', false)
                .removeAttr('data-original-text');
        }
    };

    /**
     * Form validation utilities
     */
    TTS.Validation = {
        rules: {
            required: function(value) {
                return value && value.trim().length > 0;
            },
            email: function(value) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            },
            url: function(value) {
                try {
                    new URL(value);
                    return true;
                } catch {
                    return false;
                }
            },
            minLength: function(value, length) {
                return value && value.length >= length;
            }
        },

        validate: function(form) {
            const $form = $(form);
            let isValid = true;
            const errors = [];

            $form.find('[data-validate]').each(function() {
                const $field = $(this);
                const rules = $field.data('validate').split('|');
                const value = $field.val();
                const label = $field.data('label') || $field.attr('name');

                // Remove previous error states
                $field.removeClass('tts-field-error');
                $field.next('.tts-field-error-message').remove();

                rules.forEach(rule => {
                    const [ruleName, ruleParam] = rule.split(':');
                    
                    if (this.rules[ruleName] && !this.rules[ruleName](value, ruleParam)) {
                        isValid = false;
                        $field.addClass('tts-field-error');
                        
                        const errorMessage = this.getErrorMessage(ruleName, label, ruleParam);
                        $field.after(`<div class="tts-field-error-message" style="color: var(--tts-error); font-size: 12px; margin-top: 5px;">${errorMessage}</div>`);
                        errors.push(errorMessage);
                    }
                });
            });

            return { isValid, errors };
        },

        getErrorMessage: function(rule, label, param) {
            const messages = {
                required: `${label} is required`,
                email: `${label} must be a valid email address`,
                url: `${label} must be a valid URL`,
                minLength: `${label} must be at least ${param} characters long`
            };

            return messages[rule] || `${label} is invalid`;
        }
    };

    /**
     * Data table enhancements
     */
    TTS.DataTable = {
        init: function(selector, options = {}) {
            const $table = $(selector);
            if (!$table.length) return;

            const defaults = {
                pagination: true,
                search: true,
                sorting: true,
                pageSize: 10
            };

            const settings = $.extend({}, defaults, options);
            this.enhance($table, settings);
        },

        enhance: function($table, settings) {
            // Add search functionality
            if (settings.search) {
                this.addSearch($table);
            }

            // Add sorting
            if (settings.sorting) {
                this.addSorting($table);
            }

            // Add pagination
            if (settings.pagination) {
                this.addPagination($table, settings.pageSize);
            }
        },

        addSearch: function($table) {
            const searchInput = $(`
                <div class="tts-table-search" style="margin-bottom: 15px;">
                    <input type="text" placeholder="Search..." class="tts-input" style="max-width: 300px;">
                </div>
            `);

            $table.before(searchInput);

            const debouncedSearch = TTS.Utils.debounce(function() {
                const searchTerm = $(this).val().toLowerCase();
                $table.find('tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchTerm));
                });
            }, 300);

            searchInput.find('input').on('input', debouncedSearch);
        },

        addSorting: function($table) {
            $table.find('thead th').css('cursor', 'pointer').on('click', function() {
                const $th = $(this);
                const columnIndex = $th.index();
                const isAscending = !$th.hasClass('sorted-asc');

                // Remove sorting classes from other columns
                $th.siblings().removeClass('sorted-asc sorted-desc');
                
                // Add sorting class to current column
                $th.toggleClass('sorted-asc', isAscending)
                   .toggleClass('sorted-desc', !isAscending);

                // Sort rows
                const rows = $table.find('tbody tr').toArray();
                rows.sort((a, b) => {
                    const aText = $(a).find('td').eq(columnIndex).text().trim();
                    const bText = $(b).find('td').eq(columnIndex).text().trim();
                    
                    // Try to parse as numbers
                    const aNum = parseFloat(aText);
                    const bNum = parseFloat(bText);
                    
                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAscending ? aNum - bNum : bNum - aNum;
                    }
                    
                    // String comparison
                    return isAscending ? 
                        aText.localeCompare(bText) : 
                        bText.localeCompare(aText);
                });

                $table.find('tbody').html(rows);
            });
        },

        addPagination: function($table, pageSize) {
            const rows = $table.find('tbody tr');
            const totalRows = rows.length;
            const totalPages = Math.ceil(totalRows / pageSize);
            
            if (totalPages <= 1) return;

            let currentPage = 1;

            // Create pagination controls
            const pagination = $(`
                <div class="tts-table-pagination" style="
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-top: 15px;
                    padding: 15px 0;
                    border-top: 1px solid var(--tts-border);
                ">
                    <div class="tts-pagination-info"></div>
                    <div class="tts-pagination-controls"></div>
                </div>
            `);

            $table.after(pagination);

            function updatePagination() {
                // Hide all rows
                rows.hide();
                
                // Show current page rows
                const start = (currentPage - 1) * pageSize;
                const end = start + pageSize;
                rows.slice(start, end).show();

                // Update info
                const showingStart = start + 1;
                const showingEnd = Math.min(end, totalRows);
                pagination.find('.tts-pagination-info')
                    .text(`Showing ${showingStart}-${showingEnd} of ${totalRows} entries`);

                // Update controls
                const controls = pagination.find('.tts-pagination-controls');
                controls.empty();

                // Previous button
                if (currentPage > 1) {
                    controls.append(`<button type="button" class="tts-btn tts-btn-secondary" data-page="${currentPage - 1}">Previous</button>`);
                }

                // Page numbers
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, currentPage + 2);

                for (let i = startPage; i <= endPage; i++) {
                    const activeClass = i === currentPage ? 'tts-btn-primary' : 'tts-btn-secondary';
                    controls.append(`<button type="button" class="tts-btn ${activeClass}" data-page="${i}">${i}</button>`);
                }

                // Next button
                if (currentPage < totalPages) {
                    controls.append(`<button type="button" class="tts-btn tts-btn-secondary" data-page="${currentPage + 1}">Next</button>`);
                }
            }

            // Handle pagination clicks
            pagination.on('click', 'button[data-page]', function() {
                currentPage = parseInt($(this).data('page'));
                updatePagination();
            });

            // Initial pagination
            updatePagination();
        }
    };

    /**
     * Initialize core functionality
     */
    TTS.Utils.ready(function() {
        // Initialize notifications
        TTS.Notifications.init();

        // Enhance existing tables
        $('.tts-table').each(function() {
            TTS.DataTable.init(this);
        });

        // Add form validation
        $('form[data-validate="true"]').on('submit', function(e) {
            const validation = TTS.Validation.validate(this);
            if (!validation.isValid) {
                e.preventDefault();
                TTS.Notifications.error('Please fix the errors in the form');
            }
        });

        // Add loading states to AJAX forms
        $('form[data-ajax="true"]').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $submit = $form.find('[type="submit"]');
            
            TTS.Loading.show($submit);
            
            TTS.Utils.ajax({
                url: $form.attr('action') || tts_ajax.ajax_url,
                data: $form.serialize(),
                success: function(response) {
                    if (response.success) {
                        TTS.Notifications.success(response.data.message || 'Success!');
                        if (response.data.redirect) {
                            window.location.href = response.data.redirect;
                        }
                    } else {
                        TTS.Notifications.error(response.data || 'An error occurred');
                    }
                },
                complete: function() {
                    TTS.Loading.hide($submit);
                }
            });
        });

        // Auto-hide WordPress admin notices after 5 seconds
        $('.notice.is-dismissible').each(function() {
            const $notice = $(this);
            setTimeout(() => {
                if ($notice.is(':visible')) {
                    $notice.fadeOut();
                }
            }, 5000);
        });

        // Add keyboard shortcuts info
        if (typeof tts_ajax !== 'undefined' && tts_ajax.current_page) {
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.shiftKey && e.key === '?') {
                    // Show keyboard shortcuts modal
                    TTS.Notifications.info(
                        'Keyboard Shortcuts:<br>' +
                        'Ctrl+Shift+D: Dashboard<br>' +
                        'Ctrl+Shift+C: Calendar<br>' +
                        'Ctrl+Shift+A: Analytics<br>' +
                        'Ctrl+Shift+H: Health<br>' +
                        'Ctrl+Shift+?: Show this help',
                        0 // Don't auto-hide
                    );
                }
            });
        }
    });

})(jQuery);