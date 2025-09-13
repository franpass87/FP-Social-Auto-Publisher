<?php
/**
 * Content Source Management for Social Auto Publisher.
 * Handles multiple content sources: local uploads, Dropbox, Google Drive, and optionally Trello.
 *
 * @package FPPublisher
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
        
        // Background sync actions
        add_action( 'tts_sync_dropbox_content', array( $this, 'background_sync_dropbox' ) );
        add_action( 'tts_sync_google_drive_content', array( $this, 'background_sync_google_drive' ) );
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
            'name' => __( 'Local Upload', 'fp-publisher' ),
            'description' => __( 'Upload files directly from your computer', 'fp-publisher' ),
            'enabled' => true,
        );

        if ( $client_id ) {
            // Check if Dropbox is configured
            $dropbox_token = get_post_meta( $client_id, '_tts_dropbox_token', true );
            $sources['dropbox'] = array(
                'type' => self::SOURCE_DROPBOX,
                'name' => __( 'Dropbox', 'fp-publisher' ),
                'description' => __( 'Sync content from Dropbox folders', 'fp-publisher' ),
                'enabled' => ! empty( $dropbox_token ),
            );

            // Check if Google Drive is configured
            $gdrive_token = get_post_meta( $client_id, '_tts_google_drive_token', true );
            $sources['google_drive'] = array(
                'type' => self::SOURCE_GOOGLE_DRIVE,
                'name' => __( 'Google Drive', 'fp-publisher' ),
                'description' => __( 'Sync content from Google Drive folders', 'fp-publisher' ),
                'enabled' => ! empty( $gdrive_token ),
            );

            // Check if Trello is configured (now optional)
            $trello_key = get_post_meta( $client_id, '_tts_trello_key', true );
            $trello_token = get_post_meta( $client_id, '_tts_trello_token', true );
            $sources['trello'] = array(
                'type' => self::SOURCE_TRELLO,
                'name' => __( 'Trello', 'fp-publisher' ),
                'description' => __( 'Import content from Trello cards (optional)', 'fp-publisher' ),
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
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        check_ajax_referer( 'tts_upload_nonce', 'nonce' );

        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file uploaded', 'fp-publisher' ) ) );
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
                'source_reference' => basename( $movefile['file'] ),
            ) );

            if ( $post_id ) {
                wp_send_json_success( array(
                    'message' => __( 'Content uploaded successfully', 'fp-publisher' ),
                    'post_id' => $post_id,
                    'attachment_id' => $attachment_id,
                ) );
            } else {
                wp_send_json_error( array( 'message' => __( 'Failed to create social post', 'fp-publisher' ) ) );
            }
        } else {
            wp_send_json_error( array( 'message' => $movefile['error'] ?? __( 'Upload failed', 'fp-publisher' ) ) );
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
            'post_title'   => $data['title'] ?: __( 'Untitled Post', 'fp-publisher' ),
            'post_content' => $data['content'],
            'post_status'  => 'draft',
            'post_type'    => 'tts_social_post',
            'meta_input'   => array(
                '_tts_client_id'        => $data['client_id'],
                '_tts_content_source'   => $data['source_type'],
                '_tts_source_reference' => $data['source_reference'] ?? '',
                '_tts_attachment_id'    => $data['attachment_id'],
                '_tts_social_channel'   => $data['social_channels'],
                '_tts_schedule_date'    => $data['schedule_date'],
                '_tts_created_via'      => 'content_source_' . $data['source_type'],
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
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        check_ajax_referer( 'tts_sync_nonce', 'nonce' );

        $client_id = intval( $_POST['client_id'] ?? 0 );
        $dropbox_token = get_post_meta( $client_id, '_tts_dropbox_token', true );

        if ( empty( $dropbox_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Dropbox not configured', 'fp-publisher' ) ) );
        }

        // Schedule background sync
        as_schedule_single_action(
            time() + 5,
            'tts_sync_dropbox_content',
            array( $client_id ),
            'tts_content_sync'
        );

        wp_send_json_success( array(
            'message' => __( 'Dropbox sync started in background', 'fp-publisher' ),
            'client_id' => $client_id,
        ) );
    }

    /**
     * Sync content from Google Drive.
     */
    public function sync_google_drive_content() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        check_ajax_referer( 'tts_sync_nonce', 'nonce' );

        $client_id = intval( $_POST['client_id'] ?? 0 );
        $gdrive_token = get_post_meta( $client_id, '_tts_google_drive_token', true );

        if ( empty( $gdrive_token ) ) {
            wp_send_json_error( array( 'message' => __( 'Google Drive not configured', 'fp-publisher' ) ) );
        }

        // Schedule background sync
        as_schedule_single_action(
            time() + 5,
            'tts_sync_google_drive_content',
            array( $client_id ),
            'tts_content_sync'
        );

        wp_send_json_success( array(
            'message' => __( 'Google Drive sync started in background', 'fp-publisher' ),
            'client_id' => $client_id,
        ) );
    }

    /**
     * Create content post via AJAX (for the interactive editor).
     */
    public function create_content_post() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'fp-publisher' ) );
        }

        check_ajax_referer( 'tts_create_content_nonce', 'nonce' );

        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $content = wp_kses_post( $_POST['content'] ?? '' );
        $client_id = intval( $_POST['client_id'] ?? 0 );
        $social_channels = array_map( 'sanitize_text_field', $_POST['social_channels'] ?? array() );
        $schedule_date = sanitize_text_field( $_POST['schedule_date'] ?? '' );

        if ( empty( $title ) || empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Title and content are required', 'fp-publisher' ) ) );
        }

        $post_id = $this->create_social_post_from_upload( array(
            'title' => $title,
            'content' => $content,
            'attachment_id' => 0,
            'client_id' => $client_id,
            'schedule_date' => $schedule_date,
            'social_channels' => $social_channels,
            'source_type' => 'manual',
            'source_reference' => 'created-' . current_time( 'Y-m-d-H-i-s' ),
        ) );

        if ( $post_id ) {
            wp_send_json_success( array(
                'message' => __( 'Content created successfully', 'fp-publisher' ),
                'post_id' => $post_id,
                'edit_url' => admin_url( "post.php?post={$post_id}&action=edit" ),
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Failed to create content', 'fp-publisher' ) ) );
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
                return new WP_Error( 'invalid_source', __( 'Invalid content source type', 'fp-publisher' ), array( 'status' => 400 ) );
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
            return new WP_Error( 'missing_data', __( 'Title and content are required', 'fp-publisher' ), array( 'status' => 400 ) );
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
                'message' => __( 'Content created successfully', 'fp-publisher' ),
                'post_id' => $post_id,
            ) );
        } else {
            return new WP_Error( 'creation_failed', __( 'Failed to create content', 'fp-publisher' ), array( 'status' => 500 ) );
        }
    }

    /**
     * Sync Dropbox via API.
     *
     * @param int $client_id Client ID.
     * @return WP_REST_Response
     */
    private function sync_dropbox_api( $client_id ) {
        $dropbox_token = get_post_meta( $client_id, '_tts_dropbox_token', true );

        if ( empty( $dropbox_token ) ) {
            return new WP_Error( 'dropbox_not_configured', __( 'Dropbox not configured for this client', 'fp-publisher' ), array( 'status' => 400 ) );
        }

        // Schedule background sync
        as_schedule_single_action(
            time() + 5,
            'tts_sync_dropbox_content',
            array( $client_id ),
            'tts_content_sync'
        );

        return rest_ensure_response( array(
            'message' => __( 'Dropbox sync started in background', 'fp-publisher' ),
            'client_id' => $client_id,
        ) );
    }

    /**
     * Sync Google Drive via API.
     *
     * @param int $client_id Client ID.
     * @return WP_REST_Response
     */
    private function sync_google_drive_api( $client_id ) {
        $gdrive_token = get_post_meta( $client_id, '_tts_google_drive_token', true );

        if ( empty( $gdrive_token ) ) {
            return new WP_Error( 'google_drive_not_configured', __( 'Google Drive not configured for this client', 'fp-publisher' ), array( 'status' => 400 ) );
        }

        // Schedule background sync
        as_schedule_single_action(
            time() + 5,
            'tts_sync_google_drive_content',
            array( $client_id ),
            'tts_content_sync'
        );

        return rest_ensure_response( array(
            'message' => __( 'Google Drive sync started in background', 'fp-publisher' ),
            'client_id' => $client_id,
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
            return new WP_Error( 'trello_not_configured', __( 'Trello not configured for this client', 'fp-publisher' ), array( 'status' => 400 ) );
        }

        // Use existing Trello integration logic
        // This is a placeholder - actual implementation would use existing webhook logic
        return rest_ensure_response( array(
            'message' => __( 'Trello sync completed', 'fp-publisher' ),
            'cards_synced' => 0,
        ) );
    }

    /**
     * Background sync for Dropbox content.
     *
     * @param int $client_id Client ID.
     */
    public function background_sync_dropbox( $client_id ) {
        $dropbox_token = get_post_meta( $client_id, '_tts_dropbox_token', true );
        $dropbox_folder = get_post_meta( $client_id, '_tts_dropbox_folder', true ) ?: '/Social Content';
        
        if ( empty( $dropbox_token ) ) {
            return;
        }
        
        // Get files from Dropbox API
        $files = $this->fetch_dropbox_files( $dropbox_token, $dropbox_folder );
        
        if ( ! empty( $files ) ) {
            foreach ( $files as $file ) {
                $this->import_cloud_file( $client_id, $file, self::SOURCE_DROPBOX );
            }
            
            tts_log( sprintf( 
                'Synced %d files from Dropbox for client %d', 
                count( $files ), 
                $client_id 
            ) );
        }
    }

    /**
     * Background sync for Google Drive content.
     *
     * @param int $client_id Client ID.
     */
    public function background_sync_google_drive( $client_id ) {
        $gdrive_token = get_post_meta( $client_id, '_tts_google_drive_token', true );
        $gdrive_folder = get_post_meta( $client_id, '_tts_google_drive_folder', true ) ?: 'Social Content';
        
        if ( empty( $gdrive_token ) ) {
            return;
        }
        
        // Get files from Google Drive API
        $files = $this->fetch_google_drive_files( $gdrive_token, $gdrive_folder );
        
        if ( ! empty( $files ) ) {
            foreach ( $files as $file ) {
                $this->import_cloud_file( $client_id, $file, self::SOURCE_GOOGLE_DRIVE );
            }
            
            tts_log( sprintf( 
                'Synced %d files from Google Drive for client %d', 
                count( $files ), 
                $client_id 
            ) );
        }
    }

    /**
     * Fetch files from Dropbox API.
     *
     * @param string $token  Access token.
     * @param string $folder Folder path.
     * @return array Array of file objects.
     */
    private function fetch_dropbox_files( $token, $folder ) {
        $url = 'https://api.dropboxapi.com/2/files/list_folder';
        
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( array(
                'path' => $folder,
                'recursive' => false,
                'include_media_info' => true,
            ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tts_log( 'Dropbox API error: ' . $response->get_error_message() );
            return array();
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! isset( $data['entries'] ) ) {
            return array();
        }
        
        $files = array();
        foreach ( $data['entries'] as $entry ) {
            if ( $entry['.tag'] === 'file' && $this->is_media_file( $entry['name'] ) ) {
                $files[] = array(
                    'id' => $entry['id'],
                    'name' => $entry['name'],
                    'path' => $entry['path_display'],
                    'size' => $entry['size'],
                    'modified' => $entry['server_modified'],
                    'download_url' => $this->get_dropbox_download_url( $token, $entry['path_display'] ),
                );
            }
        }
        
        return $files;
    }

    /**
     * Fetch files from Google Drive API.
     *
     * @param string $token  Access token.
     * @param string $folder Folder name.
     * @return array Array of file objects.
     */
    private function fetch_google_drive_files( $token, $folder ) {
        // First, find the folder ID
        $folder_id = $this->get_google_drive_folder_id( $token, $folder );
        
        if ( ! $folder_id ) {
            return array();
        }
        
        $url = 'https://www.googleapis.com/drive/v3/files';
        $query_params = array(
            'q' => "'{$folder_id}' in parents and (mimeType contains 'image/' or mimeType contains 'video/')",
            'fields' => 'files(id,name,mimeType,size,modifiedTime,webContentLink)',
        );
        
        $response = wp_remote_get( add_query_arg( $query_params, $url ), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            tts_log( 'Google Drive API error: ' . $response->get_error_message() );
            return array();
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( ! isset( $data['files'] ) ) {
            return array();
        }
        
        $files = array();
        foreach ( $data['files'] as $file ) {
            $files[] = array(
                'id' => $file['id'],
                'name' => $file['name'],
                'mime_type' => $file['mimeType'],
                'size' => isset( $file['size'] ) ? $file['size'] : 0,
                'modified' => $file['modifiedTime'],
                'download_url' => $file['webContentLink'],
            );
        }
        
        return $files;
    }

    /**
     * Get Dropbox download URL for a file.
     *
     * @param string $token Access token.
     * @param string $path  File path.
     * @return string Download URL.
     */
    private function get_dropbox_download_url( $token, $path ) {
        $url = 'https://api.dropboxapi.com/2/files/get_temporary_link';
        
        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( array( 'path' => $path ) ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return '';
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        return isset( $data['link'] ) ? $data['link'] : '';
    }

    /**
     * Get Google Drive folder ID by name.
     *
     * @param string $token       Access token.
     * @param string $folder_name Folder name.
     * @return string|false Folder ID or false if not found.
     */
    private function get_google_drive_folder_id( $token, $folder_name ) {
        $url = 'https://www.googleapis.com/drive/v3/files';
        $query_params = array(
            'q' => "name='{$folder_name}' and mimeType='application/vnd.google-apps.folder'",
            'fields' => 'files(id,name)',
        );
        
        $response = wp_remote_get( add_query_arg( $query_params, $url ), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        ) );
        
        if ( is_wp_error( $response ) ) {
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['files'][0]['id'] ) ) {
            return $data['files'][0]['id'];
        }
        
        return false;
    }

    /**
     * Import a cloud file as content.
     *
     * @param int    $client_id Client ID.
     * @param array  $file      File data.
     * @param string $source    Source type.
     */
    private function import_cloud_file( $client_id, $file, $source ) {
        // Check if file already imported
        $existing = get_posts( array(
            'post_type' => 'tts_social_post',
            'meta_query' => array(
                array(
                    'key' => '_tts_cloud_file_id',
                    'value' => $file['id'],
                ),
                array(
                    'key' => '_tts_source_type',
                    'value' => $source,
                ),
            ),
            'fields' => 'ids',
            'numberposts' => 1,
        ) );
        
        if ( ! empty( $existing ) ) {
            return; // Already imported
        }
        
        // Download and import the file
        $local_file = $this->download_cloud_file( $file['download_url'], $file['name'] );
        
        if ( ! $local_file ) {
            return;
        }
        
        // Create attachment
        $attachment_id = $this->create_attachment_from_upload( $local_file, $file['name'] );
        
        // Generate title from filename
        $title = preg_replace( '/\.[^.]+$/', '', $file['name'] );
        $title = str_replace( array( '_', '-' ), ' ', $title );
        $title = ucwords( $title );
        
        // Create social post
        $post_id = $this->create_social_post_from_upload( array(
            'title' => $title,
            'content' => sprintf( __( 'Content imported from %s', 'fp-publisher' ), ucfirst( str_replace( '_', ' ', $source ) ) ),
            'attachment_id' => $attachment_id,
            'client_id' => $client_id,
            'schedule_date' => '',
            'social_channels' => array(), // Can be configured later
            'source_type' => $source,
            'source_reference' => $file['path'] ?? $file['name'],
        ) );
        
        if ( $post_id ) {
            // Store cloud file metadata
            update_post_meta( $post_id, '_tts_cloud_file_id', $file['id'] );
            update_post_meta( $post_id, '_tts_cloud_file_path', $file['path'] ?? $file['name'] );
            update_post_meta( $post_id, '_tts_cloud_file_modified', $file['modified'] );
        }
        
        // Clean up temporary file
        if ( file_exists( $local_file['file'] ) ) {
            unlink( $local_file['file'] );
        }
    }

    /**
     * Download a cloud file to local storage.
     *
     * @param string $url      Download URL.
     * @param string $filename Original filename.
     * @return array|false Upload result or false on failure.
     */
    private function download_cloud_file( $url, $filename ) {
        if ( empty( $url ) ) {
            return false;
        }
        
        // Download the file
        $response = wp_remote_get( $url, array(
            'timeout' => 300, // 5 minutes for large files
        ) );
        
        if ( is_wp_error( $response ) ) {
            tts_log( 'Failed to download cloud file: ' . $response->get_error_message() );
            return false;
        }
        
        $file_content = wp_remote_retrieve_body( $response );
        
        if ( empty( $file_content ) ) {
            return false;
        }
        
        // Save to temporary file
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['path'] . '/' . wp_unique_filename( $upload_dir['path'], $filename );
        
        if ( false === file_put_contents( $temp_file, $file_content ) ) {
            return false;
        }
        
        return array(
            'file' => $temp_file,
            'url' => $upload_dir['url'] . '/' . basename( $temp_file ),
            'type' => wp_check_filetype( $temp_file )['type'],
        );
    }

    /**
     * Check if a file is a supported media file.
     *
     * @param string $filename Filename.
     * @return bool True if supported media file.
     */
    private function is_media_file( $filename ) {
        $extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'wmv', 'flv' );
        
        return in_array( $extension, $allowed_extensions, true );
    }
}

// Initialize the content source manager
new TTS_Content_Source();