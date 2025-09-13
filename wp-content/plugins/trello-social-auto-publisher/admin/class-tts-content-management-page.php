<?php
/**
 * Content Management Page for Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the content management admin page.
 */
class TTS_Content_Management_Page {

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add the content management menu page.
     */
    public function add_menu_page() {
        add_submenu_page(
            'social-auto-publisher',
            __( 'Content Manager', 'trello-social-auto-publisher' ),
            __( 'Content Manager', 'trello-social-auto-publisher' ),
            'edit_posts',
            'tts-content-manager',
            array( $this, 'render_page' )
        );
    }

    /**
     * Enqueue scripts and styles.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_scripts( $hook ) {
        if ( 'social-auto-publisher_page_tts-content-manager' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'wp-editor' );
        wp_enqueue_editor();
        wp_enqueue_media();
        
        wp_enqueue_script(
            'tts-content-manager',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/content-manager.js',
            array( 'jquery', 'wp-editor' ),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'tts-content-manager',
            plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/content-manager.css',
            array(),
            '1.0.0'
        );

        wp_localize_script( 'tts-content-manager', 'ttsContentManager', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'restUrl' => rest_url( 'tts/v1/' ),
            'nonces' => array(
                'upload' => wp_create_nonce( 'tts_upload_nonce' ),
                'sync' => wp_create_nonce( 'tts_sync_nonce' ),
                'create' => wp_create_nonce( 'tts_create_content_nonce' ),
            ),
            'strings' => array(
                'uploadSuccess' => __( 'Content uploaded successfully!', 'trello-social-auto-publisher' ),
                'uploadError' => __( 'Upload failed. Please try again.', 'trello-social-auto-publisher' ),
                'syncSuccess' => __( 'Content synced successfully!', 'trello-social-auto-publisher' ),
                'syncError' => __( 'Sync failed. Please check your configuration.', 'trello-social-auto-publisher' ),
                'createSuccess' => __( 'Content created successfully!', 'trello-social-auto-publisher' ),
                'createError' => __( 'Content creation failed. Please try again.', 'trello-social-auto-publisher' ),
                'confirmDelete' => __( 'Are you sure you want to delete this content?', 'trello-social-auto-publisher' ),
                'noFileSelected' => __( 'Please select a file to upload.', 'trello-social-auto-publisher' ),
                'titleRequired' => __( 'Title is required.', 'trello-social-auto-publisher' ),
                'contentRequired' => __( 'Content is required.', 'trello-social-auto-publisher' ),
            ),
        ) );
    }

    /**
     * Render the content management page.
     */
    public function render_page() {
        $clients = $this->get_clients();
        $content_posts = $this->get_content_posts();
        ?>
        <div class="wrap tts-content-manager">
            <h1><?php esc_html_e( 'Content Manager', 'trello-social-auto-publisher' ); ?></h1>
            <p><?php esc_html_e( 'Create, upload, and manage your social media content from multiple sources.', 'trello-social-auto-publisher' ); ?></p>

            <div class="tts-content-tabs">
                <nav class="nav-tab-wrapper">
                    <a href="#create-content" class="nav-tab nav-tab-active"><?php esc_html_e( 'Create Content', 'trello-social-auto-publisher' ); ?></a>
                    <a href="#upload-content" class="nav-tab"><?php esc_html_e( 'Upload Files', 'trello-social-auto-publisher' ); ?></a>
                    <a href="#sync-content" class="nav-tab"><?php esc_html_e( 'Sync Sources', 'trello-social-auto-publisher' ); ?></a>
                    <a href="#manage-content" class="nav-tab"><?php esc_html_e( 'Manage Content', 'trello-social-auto-publisher' ); ?></a>
                </nav>

                <!-- Create Content Tab -->
                <div id="create-content" class="tab-content active">
                    <div class="tts-create-content-form">
                        <h3><?php esc_html_e( 'Create New Content', 'trello-social-auto-publisher' ); ?></h3>
                        <form id="tts-create-content-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="content-title"><?php esc_html_e( 'Title', 'trello-social-auto-publisher' ); ?></label>
                                    <input type="text" id="content-title" name="title" required>
                                </div>
                                <div class="form-group">
                                    <label for="content-client"><?php esc_html_e( 'Client', 'trello-social-auto-publisher' ); ?></label>
                                    <select id="content-client" name="client_id" required>
                                        <option value=""><?php esc_html_e( 'Select Client', 'trello-social-auto-publisher' ); ?></option>
                                        <?php foreach ( $clients as $client ) : ?>
                                            <option value="<?php echo esc_attr( $client->ID ); ?>">
                                                <?php echo esc_html( $client->post_title ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="content-body"><?php esc_html_e( 'Content', 'trello-social-auto-publisher' ); ?></label>
                                <?php
                                wp_editor( '', 'content-body', array(
                                    'textarea_name' => 'content',
                                    'media_buttons' => true,
                                    'textarea_rows' => 10,
                                    'teeny' => false,
                                    'tinymce' => array(
                                        'resize' => false,
                                        'wordpress_adv_hidden' => false,
                                    ),
                                ) );
                                ?>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><?php esc_html_e( 'Social Channels', 'trello-social-auto-publisher' ); ?></label>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="social_channels[]" value="facebook"> Facebook</label>
                                        <label><input type="checkbox" name="social_channels[]" value="instagram"> Instagram</label>
                                        <label><input type="checkbox" name="social_channels[]" value="youtube"> YouTube</label>
                                        <label><input type="checkbox" name="social_channels[]" value="tiktok"> TikTok</label>
                                        <label><input type="checkbox" name="social_channels[]" value="blog"> Blog</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="content-schedule"><?php esc_html_e( 'Schedule Date (Optional)', 'trello-social-auto-publisher' ); ?></label>
                                    <input type="datetime-local" id="content-schedule" name="schedule_date">
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="button button-primary">
                                    <?php esc_html_e( 'Create Content', 'trello-social-auto-publisher' ); ?>
                                </button>
                                <button type="button" class="button" id="preview-content">
                                    <?php esc_html_e( 'Preview', 'trello-social-auto-publisher' ); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Upload Content Tab -->
                <div id="upload-content" class="tab-content">
                    <div class="tts-upload-content-form">
                        <h3><?php esc_html_e( 'Upload Content Files', 'trello-social-auto-publisher' ); ?></h3>
                        <form id="tts-upload-content-form" enctype="multipart/form-data">
                            <div class="upload-area" id="upload-dropzone">
                                <div class="upload-icon">📁</div>
                                <p><?php esc_html_e( 'Drag & drop files here or click to browse', 'trello-social-auto-publisher' ); ?></p>
                                <input type="file" id="content-file" name="file" accept="image/*,video/*" multiple>
                            </div>

                            <div class="upload-details" style="display: none;">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="upload-title"><?php esc_html_e( 'Title', 'trello-social-auto-publisher' ); ?></label>
                                        <input type="text" id="upload-title" name="title">
                                    </div>
                                    <div class="form-group">
                                        <label for="upload-client"><?php esc_html_e( 'Client', 'trello-social-auto-publisher' ); ?></label>
                                        <select id="upload-client" name="client_id" required>
                                            <option value=""><?php esc_html_e( 'Select Client', 'trello-social-auto-publisher' ); ?></option>
                                            <?php foreach ( $clients as $client ) : ?>
                                                <option value="<?php echo esc_attr( $client->ID ); ?>">
                                                    <?php echo esc_html( $client->post_title ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="upload-content"><?php esc_html_e( 'Description', 'trello-social-auto-publisher' ); ?></label>
                                    <textarea id="upload-content" name="content" rows="5"></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label><?php esc_html_e( 'Social Channels', 'trello-social-auto-publisher' ); ?></label>
                                        <div class="checkbox-group">
                                            <label><input type="checkbox" name="social_channels[]" value="facebook"> Facebook</label>
                                            <label><input type="checkbox" name="social_channels[]" value="instagram"> Instagram</label>
                                            <label><input type="checkbox" name="social_channels[]" value="youtube"> YouTube</label>
                                            <label><input type="checkbox" name="social_channels[]" value="tiktok"> TikTok</label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="upload-schedule"><?php esc_html_e( 'Schedule Date (Optional)', 'trello-social-auto-publisher' ); ?></label>
                                        <input type="datetime-local" id="upload-schedule" name="schedule_date">
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="button button-primary">
                                        <?php esc_html_e( 'Upload & Create Post', 'trello-social-auto-publisher' ); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sync Content Tab -->
                <div id="sync-content" class="tab-content">
                    <div class="tts-sync-content">
                        <h3><?php esc_html_e( 'Sync Content Sources', 'trello-social-auto-publisher' ); ?></h3>
                        <p><?php esc_html_e( 'Sync content from your configured cloud storage and other sources.', 'trello-social-auto-publisher' ); ?></p>

                        <div class="sync-sources">
                            <?php foreach ( $clients as $client ) : ?>
                                <div class="sync-client-section">
                                    <h4><?php echo esc_html( $client->post_title ); ?></h4>
                                    <div class="sync-options" data-client-id="<?php echo esc_attr( $client->ID ); ?>">
                                        <div class="sync-option">
                                            <button type="button" class="button sync-source-btn" data-source="dropbox">
                                                📦 <?php esc_html_e( 'Sync Dropbox', 'trello-social-auto-publisher' ); ?>
                                            </button>
                                            <span class="sync-status" id="dropbox-status-<?php echo esc_attr( $client->ID ); ?>"></span>
                                        </div>
                                        <div class="sync-option">
                                            <button type="button" class="button sync-source-btn" data-source="google_drive">
                                                🗄️ <?php esc_html_e( 'Sync Google Drive', 'trello-social-auto-publisher' ); ?>
                                            </button>
                                            <span class="sync-status" id="google_drive-status-<?php echo esc_attr( $client->ID ); ?>"></span>
                                        </div>
                                        <div class="sync-option">
                                            <button type="button" class="button sync-source-btn" data-source="trello">
                                                📋 <?php esc_html_e( 'Sync Trello', 'trello-social-auto-publisher' ); ?>
                                            </button>
                                            <span class="sync-status" id="trello-status-<?php echo esc_attr( $client->ID ); ?>"></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Manage Content Tab -->
                <div id="manage-content" class="tab-content">
                    <div class="tts-manage-content">
                        <h3><?php esc_html_e( 'Manage Content', 'trello-social-auto-publisher' ); ?></h3>
                        
                        <div class="content-filters">
                            <select id="filter-client">
                                <option value=""><?php esc_html_e( 'All Clients', 'trello-social-auto-publisher' ); ?></option>
                                <?php foreach ( $clients as $client ) : ?>
                                    <option value="<?php echo esc_attr( $client->ID ); ?>">
                                        <?php echo esc_html( $client->post_title ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <select id="filter-status">
                                <option value=""><?php esc_html_e( 'All Statuses', 'trello-social-auto-publisher' ); ?></option>
                                <option value="draft"><?php esc_html_e( 'Draft', 'trello-social-auto-publisher' ); ?></option>
                                <option value="scheduled"><?php esc_html_e( 'Scheduled', 'trello-social-auto-publisher' ); ?></option>
                                <option value="published"><?php esc_html_e( 'Published', 'trello-social-auto-publisher' ); ?></option>
                            </select>
                            
                            <input type="search" id="search-content" placeholder="<?php esc_attr_e( 'Search content...', 'trello-social-auto-publisher' ); ?>">
                            
                            <button type="button" id="refresh-content" class="button">
                                🔄 <?php esc_html_e( 'Refresh', 'trello-social-auto-publisher' ); ?>
                            </button>
                        </div>

                        <div class="content-list" id="content-list">
                            <?php $this->render_content_list( $content_posts ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Preview Modal -->
        <div id="content-preview-modal" class="tts-modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><?php esc_html_e( 'Content Preview', 'trello-social-auto-publisher' ); ?></h3>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <div id="preview-content-area"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get all clients.
     *
     * @return WP_Post[] Array of client posts.
     */
    private function get_clients() {
        return get_posts( array(
            'post_type' => 'tts_client',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ) );
    }

    /**
     * Get content posts.
     *
     * @return WP_Post[] Array of content posts.
     */
    private function get_content_posts() {
        return get_posts( array(
            'post_type' => 'tts_social_post',
            'posts_per_page' => 50,
            'post_status' => array( 'draft', 'future', 'publish' ),
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_tts_created_via',
                    'value' => array( 'content_upload', 'manual', 'api' ),
                    'compare' => 'IN',
                ),
            ),
        ) );
    }

    /**
     * Render the content list.
     *
     * @param WP_Post[] $posts Array of posts.
     */
    private function render_content_list( $posts ) {
        if ( empty( $posts ) ) {
            echo '<p class="no-content">' . esc_html__( 'No content found. Create some content to get started!', 'trello-social-auto-publisher' ) . '</p>';
            return;
        }

        echo '<div class="content-grid">';
        foreach ( $posts as $post ) {
            $client_id = get_post_meta( $post->ID, '_tts_client_id', true );
            $client = $client_id ? get_post( $client_id ) : null;
            $source_type = get_post_meta( $post->ID, '_tts_source_type', true ) ?: 'manual';
            $social_channels = get_post_meta( $post->ID, '_tts_social_channels', true ) ?: array();
            $attachment_id = get_post_meta( $post->ID, '_tts_attachment_id', true );
            $schedule_date = get_post_meta( $post->ID, '_tts_schedule_date', true );

            $status_class = $post->post_status === 'future' ? 'scheduled' : $post->post_status;
            ?>
            <div class="content-item <?php echo esc_attr( $status_class ); ?>" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                <div class="content-thumbnail">
                    <?php if ( $attachment_id ) : ?>
                        <?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
                    <?php else : ?>
                        <div class="no-image">📄</div>
                    <?php endif; ?>
                </div>
                <div class="content-details">
                    <h4><?php echo esc_html( $post->post_title ); ?></h4>
                    <p><?php echo esc_html( wp_trim_words( $post->post_content, 20 ) ); ?></p>
                    <div class="content-meta">
                        <span class="client"><?php echo $client ? esc_html( $client->post_title ) : __( 'No Client', 'trello-social-auto-publisher' ); ?></span>
                        <span class="source"><?php echo esc_html( ucfirst( str_replace( '_', ' ', $source_type ) ) ); ?></span>
                        <span class="status"><?php echo esc_html( ucfirst( $post->post_status ) ); ?></span>
                    </div>
                    <?php if ( $schedule_date ) : ?>
                        <div class="schedule-info">
                            📅 <?php echo esc_html( date( 'M j, Y g:i A', strtotime( $schedule_date ) ) ); ?>
                        </div>
                    <?php endif; ?>
                    <div class="social-channels">
                        <?php if ( ! empty( $social_channels ) ) : ?>
                            <?php foreach ( $social_channels as $channel ) : ?>
                                <span class="channel-badge <?php echo esc_attr( $channel ); ?>">
                                    <?php echo esc_html( ucfirst( $channel ) ); ?>
                                </span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="content-actions">
                    <a href="<?php echo esc_url( admin_url( "post.php?post={$post->ID}&action=edit" ) ); ?>" class="button button-small">
                        <?php esc_html_e( 'Edit', 'trello-social-auto-publisher' ); ?>
                    </a>
                    <button type="button" class="button button-small delete-content" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
                        <?php esc_html_e( 'Delete', 'trello-social-auto-publisher' ); ?>
                    </button>
                </div>
            </div>
            <?php
        }
        echo '</div>';
    }
}

// Initialize the content management page
new TTS_Content_Management_Page();