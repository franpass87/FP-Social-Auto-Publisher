/**
 * Social Connections specific JavaScript
 */

(function($) {
    'use strict';

    // Social Connections specific functionality
    TTS.SocialConnections = {
        
        // Configuration
        config: {
            testConnectionEndpoint: 'tts_test_connection',
            checkLimitsEndpoint: 'tts_check_rate_limits',
            saveSettingsEndpoint: 'tts_save_social_settings',
            platforms: ['facebook', 'instagram', 'youtube', 'tiktok']
        },

        /**
         * Initialize social connections functionality
         */
        init: function() {
            this.bindEvents();
            this.initAdvancedToggles();
            this.initAutoSave();
            this.initConnectionMonitoring();
            this.addPlatformStyling();
        },

        /**
         * Bind events specific to social connections
         */
        bindEvents: function() {
            // Test connection buttons
            $(document).on('click', '.tts-test-connection', this.handleTestConnection.bind(this));
            
            // Check rate limits buttons
            $(document).on('click', '.tts-check-limits', this.handleCheckLimits.bind(this));
            
            // Advanced settings toggle
            $(document).on('click', '.tts-advanced-trigger', this.toggleAdvancedSettings.bind(this));
            
            // Auto-save on input change
            $(document).on('change input', '.tts-platform-config input', 
                TTS.Core.debounce(this.handleAutoSave.bind(this), 2000));
            
            // Platform selection
            $(document).on('change', '.tts-platform-selector', this.handlePlatformSelection.bind(this));
            
            // Form validation
            $(document).on('submit', '#tts-social-connections-form', this.validateForm.bind(this));
        },

        /**
         * Initialize advanced settings toggles
         */
        initAdvancedToggles: function() {
            $('.tts-advanced-trigger').each(function() {
                const $trigger = $(this);
                const $content = $trigger.siblings('.tts-advanced-content');
                
                if ($content.find('input').filter(function() { return $(this).val() !== ''; }).length > 0) {
                    $content.addClass('active');
                    $trigger.find('.dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
                }
            });
        },

        /**
         * Initialize auto-save functionality
         */
        initAutoSave: function() {
            // Add save indicator
            if (!$('#tts-save-indicator').length) {
                $('body').append(`
                    <div id="tts-save-indicator" class="tts-save-indicator" style="display: none;">
                        <span class="dashicons dashicons-upload"></span>
                        <span class="text">Saving...</span>
                    </div>
                `);
            }
        },

        /**
         * Initialize connection monitoring
         */
        initConnectionMonitoring: function() {
            // Check connection status every 5 minutes
            setInterval(() => {
                this.monitorConnections();
            }, 300000); // 5 minutes

            // Initial check
            this.monitorConnections();
        },

        /**
         * Add platform-specific styling
         */
        addPlatformStyling: function() {
            this.config.platforms.forEach(platform => {
                $(`.tts-platform-config[data-platform="${platform}"]`).addClass(`tts-platform-${platform}`);
            });
        },

        /**
         * Handle test connection
         */
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const platform = $button.data('platform');
            const $platformConfig = $button.closest('.tts-platform-config');
            const $resultDiv = $(`#test-result-${platform}`);
            
            if (!platform) return;

            // Validate required fields first
            if (!this.validatePlatformCredentials(platform)) {
                TTS.Core.showNotification('Please fill in all required credentials before testing', 'error');
                return;
            }

            // Set loading state
            $button.prop('disabled', true).text('Testing...');
            $platformConfig.addClass('loading');
            $resultDiv.hide();

            // Prepare test data
            const credentials = this.getPlatformCredentials(platform);
            
            // Perform test
            $.post(TTS.Core.config.ajaxUrl, {
                action: this.config.testConnectionEndpoint,
                platform: platform,
                credentials: credentials,
                nonce: TTS.Core.config.nonce
            })
            .done((response) => {
                this.handleTestResponse(response, platform, $button, $resultDiv);
            })
            .fail((xhr) => {
                this.handleTestError(xhr, platform, $button, $resultDiv);
            })
            .always(() => {
                $button.prop('disabled', false).text('Test Connection');
                $platformConfig.removeClass('loading');
            });
        },

        /**
         * Handle rate limit checking
         */
        handleCheckLimits: function(e) {
            e.preventDefault();
            
            const $button = $(e.currentTarget);
            const platform = $button.data('platform');
            const $container = $(`#rate-limit-${platform}`);
            
            if (!platform) return;

            $button.prop('disabled', true).text('Checking...');

            $.post(TTS.Core.config.ajaxUrl, {
                action: this.config.checkLimitsEndpoint,
                platform: platform,
                nonce: TTS.Core.config.nonce
            })
            .done((response) => {
                this.handleLimitsResponse(response, platform, $container);
            })
            .fail((xhr) => {
                TTS.Core.handleAjaxError(xhr, $button);
            })
            .always(() => {
                $button.prop('disabled', false).text('Check API Limits');
            });
        },

        /**
         * Toggle advanced settings
         */
        toggleAdvancedSettings: function(e) {
            e.preventDefault();
            
            const $trigger = $(e.currentTarget);
            const $content = $trigger.siblings('.tts-advanced-content');
            const $icon = $trigger.find('.dashicons');
            
            $content.toggleClass('active');
            
            if ($content.hasClass('active')) {
                $icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
            } else {
                $icon.removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
            }
        },

        /**
         * Handle auto-save
         */
        handleAutoSave: function(e) {
            const $input = $(e.currentTarget);
            const platform = $input.closest('.tts-platform-config').data('platform');
            
            if (!platform) return;

            this.showSaveIndicator();
            
            const credentials = this.getPlatformCredentials(platform);
            
            $.post(TTS.Core.config.ajaxUrl, {
                action: this.config.saveSettingsEndpoint,
                platform: platform,
                credentials: credentials,
                nonce: TTS.Core.config.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.showSaveIndicator('success');
                } else {
                    this.showSaveIndicator('error');
                }
            })
            .fail(() => {
                this.showSaveIndicator('error');
            });
        },

        /**
         * Handle platform selection
         */
        handlePlatformSelection: function(e) {
            const $select = $(e.currentTarget);
            const selectedPlatforms = $select.val() || [];
            
            $('.tts-platform-config').each(function() {
                const $config = $(this);
                const platform = $config.data('platform');
                
                if (selectedPlatforms.includes(platform)) {
                    $config.show();
                } else {
                    $config.hide();
                }
            });
        },

        /**
         * Validate form before submission
         */
        validateForm: function(e) {
            let isValid = true;
            const errors = [];

            this.config.platforms.forEach(platform => {
                const $config = $(`.tts-platform-config[data-platform="${platform}"]`);
                if ($config.is(':visible')) {
                    const validation = this.validatePlatformCredentials(platform);
                    if (!validation) {
                        errors.push(`${platform.charAt(0).toUpperCase() + platform.slice(1)} credentials are incomplete`);
                        isValid = false;
                    }
                }
            });

            if (!isValid) {
                e.preventDefault();
                TTS.Core.showNotification(
                    'Please complete the following:\n' + errors.join('\n'), 
                    'error', 
                    8000
                );
            }

            return isValid;
        },

        /**
         * Validate platform credentials
         */
        validatePlatformCredentials: function(platform) {
            const credentials = this.getPlatformCredentials(platform);
            const requiredFields = this.getRequiredFields(platform);
            
            return requiredFields.every(field => credentials[field] && credentials[field].trim() !== '');
        },

        /**
         * Get platform credentials from form
         */
        getPlatformCredentials: function(platform) {
            const credentials = {};
            const $config = $(`.tts-platform-config[data-platform="${platform}"]`);
            
            $config.find('input').each(function() {
                const $input = $(this);
                const name = $input.attr('name');
                if (name && name.includes(platform)) {
                    const fieldName = name.split(`[${platform}]`)[1].replace(/[\[\]]/g, '');
                    credentials[fieldName] = $input.val();
                }
            });
            
            return credentials;
        },

        /**
         * Get required fields for platform
         */
        getRequiredFields: function(platform) {
            const fieldMap = {
                facebook: ['app_id', 'app_secret'],
                instagram: ['app_id', 'app_secret'],
                youtube: ['client_id', 'client_secret'],
                tiktok: ['client_key', 'client_secret']
            };
            
            return fieldMap[platform] || [];
        },

        /**
         * Handle test response
         */
        handleTestResponse: function(response, platform, $button, $resultDiv) {
            const $platformConfig = $button.closest('.tts-platform-config');
            
            if (response.success) {
                $resultDiv.removeClass('error').addClass('success')
                          .html('✅ ' + response.data.message).show();
                $platformConfig.addClass('success');
                
                // Update connection status
                this.updateConnectionStatus(platform, 'connected');
                
                setTimeout(() => $platformConfig.removeClass('success'), 2000);
            } else {
                $resultDiv.removeClass('success').addClass('error')
                          .html('❌ ' + (response.data.message || 'Connection test failed')).show();
                $platformConfig.addClass('error');
                
                setTimeout(() => $platformConfig.removeClass('error'), 2000);
            }
        },

        /**
         * Handle test error
         */
        handleTestError: function(xhr, platform, $button, $resultDiv) {
            $resultDiv.removeClass('success').addClass('error')
                      .html('❌ Failed to test connection').show();
            
            const $platformConfig = $button.closest('.tts-platform-config');
            $platformConfig.addClass('error');
            setTimeout(() => $platformConfig.removeClass('error'), 2000);
        },

        /**
         * Handle limits response
         */
        handleLimitsResponse: function(response, platform, $container) {
            if (response.success) {
                const limits = response.data;
                const html = `
                    <div class="tts-rate-limit-display">
                        <strong>API Rate Limits:</strong><br>
                        Used: ${limits.used} / ${limits.limit}<br>
                        Remaining: ${limits.remaining}<br>
                        Reset: ${limits.reset_time}
                    </div>
                `;
                
                // Remove existing display
                $container.find('.tts-rate-limit-display').remove();
                $container.append(html);
            }
        },

        /**
         * Update connection status
         */
        updateConnectionStatus: function(platform, status) {
            const $statusElement = $(`.tts-connection-status[data-platform="${platform}"] .tts-status-${status}`);
            $statusElement.siblings().hide();
            $statusElement.show();
        },

        /**
         * Show save indicator
         */
        showSaveIndicator: function(status = 'saving') {
            const $indicator = $('#tts-save-indicator');
            
            $indicator.removeClass('success error saving').addClass(status);
            
            const messages = {
                saving: 'Saving...',
                success: 'Saved!',
                error: 'Save failed'
            };
            
            $indicator.find('.text').text(messages[status]);
            $indicator.show();
            
            if (status !== 'saving') {
                setTimeout(() => {
                    $indicator.fadeOut();
                }, 2000);
            }
        },

        /**
         * Monitor connections periodically
         */
        monitorConnections: function() {
            // Only monitor if page is visible
            if (document.hidden) return;

            this.config.platforms.forEach(platform => {
                const $config = $(`.tts-platform-config[data-platform="${platform}"]`);
                if ($config.is(':visible') && this.validatePlatformCredentials(platform)) {
                    this.quickConnectionCheck(platform);
                }
            });
        },

        /**
         * Quick connection check (lightweight)
         */
        quickConnectionCheck: function(platform) {
            $.post(TTS.Core.config.ajaxUrl, {
                action: 'tts_quick_connection_check',
                platform: platform,
                nonce: TTS.Core.config.nonce
            })
            .done((response) => {
                if (response.success) {
                    this.updateConnectionStatus(platform, response.data.status);
                }
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.tts-social-apps-container').length) {
            TTS.SocialConnections.init();
        }
    });

})(jQuery);