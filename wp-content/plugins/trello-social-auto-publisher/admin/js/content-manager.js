/**
 * Content Manager JavaScript for Social Auto Publisher.
 * Handles interactive content creation, upload, and management without page reloads.
 */

(function($) {
    'use strict';

    const ContentManager = {
        init: function() {
            this.bindEvents();
            this.initializeDropzone();
            this.initializeTabs();
        },

        bindEvents: function() {
            // Tab navigation
            $('.nav-tab').on('click', this.handleTabClick);
            
            // Form submissions
            $('#tts-create-content-form').on('submit', this.handleCreateContent);
            $('#tts-upload-content-form').on('submit', this.handleUploadContent);
            
            // File upload
            $('#content-file').on('change', this.handleFileSelect);
            
            // Sync buttons
            $('.sync-source-btn').on('click', this.handleSyncSource);
            
            // Content management
            $('#refresh-content').on('click', this.refreshContentList);
            $('#filter-client, #filter-status').on('change', this.filterContent);
            $('#search-content').on('input', this.searchContent);
            $(document).on('click', '.delete-content', this.handleDeleteContent);
            
            // Preview and modal
            $('#preview-content').on('click', this.previewContent);
            $('.close-modal, .tts-modal').on('click', this.closeModal);
            $('.modal-content').on('click', function(e) {
                e.stopPropagation();
            });
        },

        initializeTabs: function() {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');
            
            const activeTab = window.location.hash || '#create-content';
            $(`.nav-tab[href="${activeTab}"]`).addClass('nav-tab-active');
            $(activeTab).addClass('active');
        },

        initializeDropzone: function() {
            const $dropzone = $('#upload-dropzone');
            const $fileInput = $('#content-file');

            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $dropzone.on('dragleave dragend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    $fileInput[0].files = files;
                    ContentManager.handleFileSelect.call($fileInput[0]);
                }
            });

            $dropzone.on('click', function() {
                $fileInput.click();
            });
        },

        handleTabClick: function(e) {
            e.preventDefault();
            
            const $tab = $(this);
            const target = $tab.attr('href');
            
            $('.nav-tab').removeClass('nav-tab-active');
            $('.tab-content').removeClass('active');
            
            $tab.addClass('nav-tab-active');
            $(target).addClass('active');
            
            window.location.hash = target;
        },

        handleCreateContent: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Get form data
            const title = $('#content-title').val().trim();
            const clientId = $('#content-client').val();
            const content = ContentManager.getEditorContent('content-body');
            const socialChannels = [];
            const scheduleDate = $('#content-schedule').val();
            
            $('input[name="social_channels[]"]:checked').each(function() {
                socialChannels.push($(this).val());
            });
            
            // Validation
            if (!title) {
                ContentManager.showNotice(ttsContentManager.strings.titleRequired, 'error');
                return;
            }
            
            if (!content.trim()) {
                ContentManager.showNotice(ttsContentManager.strings.contentRequired, 'error');
                return;
            }
            
            if (!clientId) {
                ContentManager.showNotice('Please select a client.', 'error');
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Creating...');
            
            // Submit via AJAX
            $.ajax({
                url: ttsContentManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tts_create_content',
                    nonce: ttsContentManager.nonces.create,
                    title: title,
                    content: content,
                    client_id: clientId,
                    social_channels: socialChannels,
                    schedule_date: scheduleDate
                },
                success: function(response) {
                    if (response.success) {
                        ContentManager.showNotice(response.data.message, 'success');
                        $form[0].reset();
                        ContentManager.clearEditor('content-body');
                        ContentManager.refreshContentList();
                    } else {
                        ContentManager.showNotice(response.data.message || ttsContentManager.strings.createError, 'error');
                    }
                },
                error: function() {
                    ContentManager.showNotice(ttsContentManager.strings.createError, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Create Content');
                }
            });
        },

        handleUploadContent: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const fileInput = $('#content-file')[0];
            
            if (!fileInput.files || fileInput.files.length === 0) {
                ContentManager.showNotice(ttsContentManager.strings.noFileSelected, 'error');
                return;
            }
            
            const clientId = $('#upload-client').val();
            if (!clientId) {
                ContentManager.showNotice('Please select a client.', 'error');
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true).text('Uploading...');
            
            // Create FormData for file upload
            const formData = new FormData();
            formData.append('action', 'tts_upload_content');
            formData.append('nonce', ttsContentManager.nonces.upload);
            formData.append('file', fileInput.files[0]);
            formData.append('title', $('#upload-title').val());
            formData.append('content', $('#upload-content').val());
            formData.append('client_id', clientId);
            formData.append('schedule_date', $('#upload-schedule').val());
            
            // Add social channels
            $('input[name="social_channels[]"]:checked').each(function() {
                formData.append('social_channels[]', $(this).val());
            });
            
            $.ajax({
                url: ttsContentManager.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        ContentManager.showNotice(response.data.message, 'success');
                        $form[0].reset();
                        $('.upload-details').hide();
                        ContentManager.refreshContentList();
                    } else {
                        ContentManager.showNotice(response.data.message || ttsContentManager.strings.uploadError, 'error');
                    }
                },
                error: function() {
                    ContentManager.showNotice(ttsContentManager.strings.uploadError, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false).text('Upload & Create Post');
                }
            });
        },

        handleFileSelect: function() {
            const files = this.files;
            const $details = $('.upload-details');
            
            if (files && files.length > 0) {
                const file = files[0];
                
                // Auto-fill title from filename
                const filename = file.name.replace(/\.[^/.]+$/, "");
                $('#upload-title').val(filename);
                
                // Show upload details
                $details.show();
                
                // Show file preview if it's an image
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let preview = $('.file-preview');
                        if (preview.length === 0) {
                            preview = $('<div class="file-preview"></div>');
                            $details.prepend(preview);
                        }
                        preview.html(`<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 200px;">`);
                    };
                    reader.readAsDataURL(file);
                }
            } else {
                $details.hide();
                $('.file-preview').remove();
            }
        },

        handleSyncSource: function() {
            const $btn = $(this);
            const source = $btn.data('source');
            const clientId = $btn.closest('.sync-options').data('client-id');
            const $status = $(`#${source}-status-${clientId}`);
            
            $btn.prop('disabled', true).text('Syncing...');
            $status.text('Syncing...');
            
            $.ajax({
                url: `${ttsContentManager.restUrl}content-source/${source}/sync`,
                type: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wp.api.nonce);
                },
                data: {
                    client_id: clientId
                },
                success: function(response) {
                    ContentManager.showNotice(response.message || ttsContentManager.strings.syncSuccess, 'success');
                    $status.text(`✓ ${response.files_synced || 0} files synced`);
                    ContentManager.refreshContentList();
                },
                error: function(xhr) {
                    const message = xhr.responseJSON?.message || ttsContentManager.strings.syncError;
                    ContentManager.showNotice(message, 'error');
                    $status.text('✗ Sync failed');
                },
                complete: function() {
                    $btn.prop('disabled', false).text($btn.text().replace('Syncing...', $btn.data('source').replace('_', ' ')));
                }
            });
        },

        handleDeleteContent: function() {
            const postId = $(this).data('post-id');
            
            if (!confirm(ttsContentManager.strings.confirmDelete)) {
                return;
            }
            
            const $item = $(this).closest('.content-item');
            $item.addClass('deleting');
            
            $.ajax({
                url: ttsContentManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tts_delete_post',
                    post_id: postId,
                    nonce: wp.api.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(function() {
                            $(this).remove();
                        });
                        ContentManager.showNotice('Content deleted successfully.', 'success');
                    } else {
                        ContentManager.showNotice('Failed to delete content.', 'error');
                        $item.removeClass('deleting');
                    }
                },
                error: function() {
                    ContentManager.showNotice('Failed to delete content.', 'error');
                    $item.removeClass('deleting');
                }
            });
        },

        refreshContentList: function() {
            const $contentList = $('#content-list');
            
            $contentList.html('<div class="loading">Loading content...</div>');
            
            $.ajax({
                url: ttsContentManager.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tts_refresh_posts',
                    nonce: wp.api.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $contentList.html(response.data.html);
                    } else {
                        $contentList.html('<p class="error">Failed to load content.</p>');
                    }
                },
                error: function() {
                    $contentList.html('<p class="error">Failed to load content.</p>');
                }
            });
        },

        filterContent: function() {
            const clientFilter = $('#filter-client').val();
            const statusFilter = $('#filter-status').val();
            
            $('.content-item').each(function() {
                const $item = $(this);
                let show = true;
                
                if (clientFilter && $item.data('client-id') != clientFilter) {
                    show = false;
                }
                
                if (statusFilter && !$item.hasClass(statusFilter)) {
                    show = false;
                }
                
                $item.toggle(show);
            });
        },

        searchContent: function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('.content-item').each(function() {
                const $item = $(this);
                const title = $item.find('h4').text().toLowerCase();
                const content = $item.find('p').text().toLowerCase();
                
                const matches = title.includes(searchTerm) || content.includes(searchTerm);
                $item.toggle(matches);
            });
        },

        previewContent: function(e) {
            e.preventDefault();
            
            const title = $('#content-title').val();
            const content = ContentManager.getEditorContent('content-body');
            
            if (!title || !content.trim()) {
                ContentManager.showNotice('Please enter title and content to preview.', 'error');
                return;
            }
            
            const previewHtml = `
                <h2>${title}</h2>
                <div class="content-preview">${content}</div>
            `;
            
            $('#preview-content-area').html(previewHtml);
            $('#content-preview-modal').show();
        },

        closeModal: function(e) {
            if (e.target === this || $(e.target).hasClass('close-modal')) {
                $('.tts-modal').hide();
            }
        },

        getEditorContent: function(editorId) {
            if (typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get(editorId);
                if (editor && !editor.isHidden()) {
                    return editor.getContent();
                }
            }
            return $(`#${editorId}`).val();
        },

        clearEditor: function(editorId) {
            if (typeof tinyMCE !== 'undefined') {
                const editor = tinyMCE.get(editorId);
                if (editor && !editor.isHidden()) {
                    editor.setContent('');
                    return;
                }
            }
            $(`#${editorId}`).val('');
        },

        showNotice: function(message, type = 'info') {
            // Remove existing notices
            $('.tts-notice').remove();
            
            const noticeClass = type === 'error' ? 'notice-error' : 
                               type === 'success' ? 'notice-success' : 'notice-info';
            
            const $notice = $(`
                <div class="notice ${noticeClass} is-dismissible tts-notice">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);
            
            $('.wrap.tts-content-manager').prepend($notice);
            
            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut();
                }, 5000);
            }
            
            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut();
            });
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.tts-content-manager').length) {
            ContentManager.init();
        }
    });

})(jQuery);