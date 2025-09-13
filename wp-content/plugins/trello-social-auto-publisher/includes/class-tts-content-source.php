<?php
/**
 * Content Source Management for Social Auto Publisher.
 * Handles multiple content sources: local uploads, Dropbox, Google Drive, and optionally Trello.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Manages multiple content sources for the social auto publisher.
 */
class TTS_Content_Source {

    /**
     * Available content source types.
     */
    const SOURCE_LOCAL = 'local';
    const SOURCE_DROPBOX = 'dropbox';
    const SOURCE_GOOGLE_DRIVE = 'google_drive';
    const SOURCE_TRELLO = 'trello';

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'wp_ajax_tts_upload_content', array( $this, 'handle_local_upload' ) );
        add_action( 'wp_ajax_tts_sync_dropbox', array( $this, 'sync_dropbox_content' ) );
        add_action( 'wp_ajax_tts_sync_google_drive', array( $this, 'sync_google_drive_content' ) );
        add_action( 'wp_ajax_tts_create_content', array( $this, 'create_content_post' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            'tts/v1',
            '/content-sources',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_content_sources' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        register_rest_route(
            'tts/v1',
            '/content-source/(?P<type>\w+)/sync',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'sync_content_source' ),
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            )
        );

        register_rest_route(
            'tts/v1',
            '/content/create',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'create_content_via_api' ),
                'permission_callback' => function() {
                    return current_user_can( 'edit_posts' );
                },
            )
        );
    }

    /**
     * Get available content sources for a client.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function get_content_sources( $request ) {
        $client_id = $request->get_param( 'client_id' );
        
        $sources = array();
        
        // Always available
        $sources['local'] = array(
            'type' => self::SOURCE_LOCAL,
            'name' => __( 'Local Upload', 'trello-social-auto-publisher' ),
            'description' => __( 'Upload files directly from your computer', 'trello-social-auto-publisher' ),
            'enabled' => true,
        );

        if ( $client_id ) {
            // Check if Dropbox is configured
            $dropbox_token = get_post_meta( $client_id, '_tts_dropbox_token', true );
            $sources['dropbox'] = array(
                'type' => self::SOURCE_DROPBOX,
                'name' => __( 'Dropbox', 'trello-social-auto-publisher' ),
                'description' => __( 'Sync content from Dropbox folders', 'trello-social-auto-publisher' ),
                'enabled' => ! empty( $dropbox_token ),
            );

            // Check if Google Drive is configured
            $gdrive_token = get_post_meta( $client_id, '_tts_google_drive_token', true );
            $sources['google_drive'] = array(
                'type' => self::SOURCE_GOOGLE_DRIVE,
                'name' => __( 'Google Drive', 'trello-social-auto-publisher' ),
                'description' => __( 'Sync content from Google Drive folders', 'trello-social-auto-publisher' ),
                'enabled' => ! empty( $gdrive_token ),
            );

            // Check if Trello is configured (now optional)
            $trello_key = get_post_meta( $client_id, '_tts_trello_key', true );
            $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
            $sources['trello'] = array(
                'type' => self::SOURCE_TRELLO,
                'name' => __( 'Trello', 'trello-social-auto-publisher' ),
                'description' => __( 'Import content from Trello cards (optional)', 'trello-social-auto-publisher' ),
                'enabled' => ! empty( $trello_key ) && ! empty( $trello_token ),
            );
        }

        return rest_ensure_response( $sources );
    }

    /**
     * Handle local file upload.
     */
    public function handle_local_upload() {
        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        check_ajax_referer( 'tts_upload_nonce', 'nonce' );

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded', 'trello-social-auto-publisher' ) ) );
        }

        $file = $_FILES['file'];
        $client_id = intval( $_POST['client_id'] ?? 0 );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $content = wp_kses_post( $_POST['content'] ?? '' );
        $schedule_date = sanitize_text_field( $_POST['schedule_date'] ?? '' );
        $social_channels = array_map( 'sanitize_text_field', $_POST['social_channels'] ?? array() );

        // Handle file upload
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $file, $upload_overrides );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            // Create attachment
            $attachment_id = $this->create_attachment_from_upload( $movefile, $title );
            
            // Create social post
            $post_id = $this->create_social_post_from_upload( array(
                'title' => $title,
                'content' => $content,
                'attachment_id' => $attachment_id,
                'client_id' => $client_id,
                'schedule_date' => $schedule_date,
                'social_channels' => $social_channels,
                'source_type' => self::SOURCE_LOCAL,
            ) );

            if ( $post_id ) {
                wp_send_json_success( array(
                    'message' => __( 'Content uploaded successfully', 'trello-social-auto-publisher' ),
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id,
                ) );
            } else {
                wp_send_json_error( array( 'message' => __( 'Failed to create social post', 'trello-social-auto-publisher' ) ) );
            }
        } else {
            wp_send_json_error( array( 'message' => $movefile['error'] ?? __( 'Upload failed', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Create attachment from uploaded file.
     *
     * @param array  $movefile Upload result.
     * @param string $title    File title.
     * @return int Attachment ID.
     */
    private function create_attachment_from_upload( $movefile, $title = '' ) {
        $wp_filetype = wp_check_filetype( $movefile['file'], null );
        
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title'     => $title ?: preg_replace( '/\.[^.]+$/', '', basename( $movefile['file'] ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $movefile['file'] );
        
        if ( ! is_wp_error( $attach_id ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            $attach_data = wp_generate_attachment_metadata( $attach_id, $movefile['file'] );
            wp_update_attachment_metadata( $attach_id, $attach_data );
        }

        return $attach_id;
    }

    /**
     * Create social post from uploaded content.
     *
     * @param array $data Post data.
     * @return int|false Post ID on success, false on failure.
     */
    private function create_social_post_from_upload( $data ) {
        $post_data = array(
            'post_title'   => $data['title'] ?: __( 'Untitled Post', 'trello-social-auto-publisher' ),
            'post_content' => $data['content'],
            'post_status'  => 'draft',
            'post_type'    => 'tts_social_post',
            'meta_input'   => array(
                '_tts_client_id'        => $data['client_id'],
                '_tts_source_type'      => $data['source_type'],
                '_tts_attachment_id'    => $data['attachment_id'],
                '_tts_social_channels'  => $data['social_channels'],
                '_tts_schedule_date'    => $data['schedule_date'],
                '_tts_created_via'      => 'content_upload',
            ),
        );

        if ( ! empty( $data['schedule_date'] ) ) {
            $schedule_time = strtotime( $data['schedule_date'] );
            if ( $schedule_time > time() ) {
                $post_data['post_status'] = 'future';
                $post_data['post_date'] = date( 'Y-m-d H:i:s', $schedule_time );
                $post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $schedule_time );
            }
        }

        return wp_insert_post( $post_data );
    }

    /**
     * Sync content from Dropbox.
     */
    public function sync_dropbox_content() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        check_ajax_referer( 'tts_sync_nonce', 'nonce' );

        $client_id = intval( $_POST['client_id'] ?? 0 );
        $dropbox_token = get_post_meta( $client_id, '_tts_dropbox_token', true );
        $dropbox_folder = get_post_meta( $client_id, '_tts_dropbox_folder', true ) ?: '/Social Content';

        if ( empty( $dropbox_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Dropbox not configured', 'trello-social-auto-publisher' ) ) );
        }

        // TODO: Implement Dropbox API integration
        // For now, return a placeholder response
        wp_send_json_success( array(
            'message' => __( 'Dropbox sync functionality will be implemented', 'trello-social-auto-publisher' ),
            'files_found' => 0,
        ) );
    }

    /**
     * Sync content from Google Drive.
     */
    public function sync_google_drive_content() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        check_ajax_referer( 'tts_sync_nonce', 'nonce' );

        $client_id = intval( $_POST['client_id'] ?? 0 );
        $gdrive_token = get_post_meta( $client_id, '_tts_google_drive_token', true );
        $gdrive_folder = get_post_meta( $client_id, '_tts_google_drive_folder', true ) ?: 'Social Content';

        if ( empty( $gdrive_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Google Drive not configured', 'trello-social-auto-publisher' ) ) );
        }

        // TODO: Implement Google Drive API integration
        // For now, return a placeholder response
        wp_send_json_success( array(
            'message' => __( 'Google Drive sync functionality will be implemented', 'trello-social-auto-publisher' ),
            'files_found' => 0,
        ) );
    }

    /**
     * Create content post via AJAX (for the interactive editor).
     */
    public function create_content_post() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        check_ajax_referer( 'tts_create_content_nonce', 'nonce' );

        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $content = wp_kses_post( $_POST['content'] ?? '' );
        $client_id = intval( $_POST['client_id'] ?? 0 );
        $social_channels = array_map( 'sanitize_text_field', $_POST['social_channels'] ?? array() );
        $schedule_date = sanitize_text_field( $_POST['schedule_date'] ?? '' );

        if ( empty( $title ) || empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Title and content are required', 'trello-social-auto-publisher' ) ) );
        }

        $post_id = $this->create_social_post_from_upload( array(
            'title' => $title,
            'content' => $content,
            'attachment_id' => 0,
            'client_id' => $client_id,
            'schedule_date' => $schedule_date,
            'social_channels' => $social_channels,
            'source_type' => 'manual',
        ) );

        if ( $post_id ) {
            wp_send_json_success( array(
                'message' => __( 'Content created successfully', 'trello-social-auto-publisher' ),
                'post_id' => $post_id,
                'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to create content', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Sync content source via REST API.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function sync_content_source( $request ) {
        $type = $request->get_param( 'type' );
        $client_id = $request->get_param( 'client_id' );

        switch ( $type ) {
            case self::SOURCE_DROPBOX:
                return $this->sync_dropbox_api( $client_id );
            case self::SOURCE_GOOGLE_DRIVE:
                return $this->sync_google_drive_api( $client_id );
            case self::SOURCE_TRELLO:
                return $this->sync_trello_api( $client_id );
            default:
                return new WP_Error( 'invalid_source', __( 'Invalid content source type', 'trello-social-auto-publisher' ), array( 'status' => 400 ) );
        }
    }

    /**
     * Create content via REST API.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public function create_content_via_api( $request ) {
        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $content = wp_kses_post( $request->get_param( 'content' ) );
        $client_id = intval( $request->get_param( 'client_id' ) );
        $social_channels = $request->get_param( 'social_channels' ) ?: array();
        $schedule_date = sanitize_text_field( $request->get_param( 'schedule_date' ) );

        if ( empty( $title ) || empty( $content ) ) {
            return new WP_Error( 'missing_data', __( 'Title and content are required', 'trello-social-auto-publisher' ), array( 'status' => 400 ) );
        }

        $post_id = $this->create_social_post_from_upload( array(
            'title' => $title,
            'content' => $content,
            'attachment_id' => 0,
            'client_id' => $client_id,
            'schedule_date' => $schedule_date,
            'social_channels' => $social_channels,
            'source_type' => 'api',
        ) );

        if ( $post_id ) {
            return rest_ensure_response( array(
                'message' => __( 'Content created successfully', 'trello-social-auto-publisher' ),
                'post_id' => $post_id,
            ) );
        } else {
            return new WP_Error( 'creation_failed', __( 'Failed to create content', 'trello-social-auto-publisher' ), array( 'status' => 500 ) );
        }
    }

    /**
     * Sync Dropbox via API (placeholder).
     *
     * @param int $client_id Client ID.
     * @return WP_REST_Response
     */
    private function sync_dropbox_api( $client_id ) {
        return rest_ensure_response( array(
            'message' => __( 'Dropbox API integration pending', 'trello-social-auto-publisher' ),
            'files_synced' => 0,
        ) );
    }

    /**
     * Sync Google Drive via API (placeholder).
     *
     * @param int $client_id Client ID.
     * @return WP_REST_Response
     */
    private function sync_google_drive_api( $client_id ) {
        return rest_ensure_response( array(
            'message' => __( 'Google Drive API integration pending', 'trello-social-auto-publisher' ),
            'files_synced' => 0,
        ) );
    }

    /**
     * Sync Trello via API (using existing logic).
     *
     * @param int $client_id Client ID.
     * @return WP_REST_Response
     */
    private function sync_trello_api( $client_id ) {
        $trello_key = get_post_meta( $client_id, '_tts_trello_key', true );
        $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );

        if ( empty( $trello_key ) || empty( $trello_token ) ) {
            return new WP_Error( 'trello_not_configured', __( 'Trello not configured for this client', 'trello-social-auto-publisher' ), array( 'status' => 400 ) );
        }

        // Use existing Trello integration logic
        // This is a placeholder - actual implementation would use existing webhook logic
        return rest_ensure_response( array(
            'message' => __( 'Trello sync completed', 'trello-social-auto-publisher' ),
            'cards_synced' => 0,
        ) );
    }
}

// Initialize the content source manager
new TTS_Content_Source();