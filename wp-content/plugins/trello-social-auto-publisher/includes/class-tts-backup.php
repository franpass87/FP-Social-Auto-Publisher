<?php
/**
 * Advanced Backup and Recovery System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * TTS_Backup class for enterprise-level data backup and recovery
 */
class TTS_Backup {

    /**
     * Initialize the backup system
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_create_backup', array( $this, 'ajax_create_backup' ) );
        add_action( 'wp_ajax_tts_restore_backup', array( $this, 'ajax_restore_backup' ) );
        add_action( 'wp_ajax_tts_download_backup', array( $this, 'ajax_download_backup' ) );
        add_action( 'wp_ajax_tts_delete_backup', array( $this, 'ajax_delete_backup' ) );
        add_action( 'wp_ajax_tts_list_backups', array( $this, 'ajax_list_backups' ) );
        
        // Schedule automatic backups
        add_action( 'tts_daily_backup', array( $this, 'create_automatic_backup' ) );
        if ( ! wp_next_scheduled( 'tts_daily_backup' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_daily_backup' );
        }
    }

    /**
     * Create backup via AJAX
     */
    public function ajax_create_backup() {
        check_ajax_referer( 'tts_backup_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $backup_type = sanitize_text_field( $_POST['backup_type'] ?? 'full' );
        $result = $this->create_backup( $backup_type );
        
        wp_send_json( $result );
    }

    /**
     * Create a comprehensive backup
     *
     * @param string $type Backup type: 'full', 'settings', 'clients', 'logs'
     * @return array Backup result
     */
    public function create_backup( $type = 'full' ) {
        global $wpdb;
        
        try {
            $backup_data = array(
                'timestamp' => current_time( 'mysql' ),
                'type' => $type,
                'version' => '1.0.0',
                'site_url' => get_site_url(),
                'data' => array()
            );

            switch ( $type ) {
                case 'full':
                    $backup_data['data'] = $this->get_full_backup_data();
                    break;
                case 'settings':
                    $backup_data['data'] = $this->get_settings_backup_data();
                    break;
                case 'clients':
                    $backup_data['data'] = $this->get_clients_backup_data();
                    break;
                case 'logs':
                    $backup_data['data'] = $this->get_logs_backup_data();
                    break;
            }

            $backup_filename = $this->generate_backup_filename( $type );
            $backup_path = $this->get_backup_directory() . $backup_filename;
            
            // Create backup directory if it doesn't exist
            $this->ensure_backup_directory();
            
            // Save backup to file
            $backup_json = wp_json_encode( $backup_data, JSON_PRETTY_PRINT );
            file_put_contents( $backup_path, $backup_json );
            
            // Compress backup
            $compressed_path = $this->compress_backup( $backup_path );
            unlink( $backup_path ); // Remove uncompressed version
            
            // Log backup creation
            TTS_Logger::log( 'Backup created successfully: ' . $backup_filename );
            
            return array(
                'success' => true,
                'message' => __( 'Backup created successfully', 'trello-social-auto-publisher' ),
                'filename' => basename( $compressed_path ),
                'size' => $this->format_file_size( filesize( $compressed_path ) ),
                'timestamp' => current_time( 'mysql' )
            );
            
        } catch ( Exception $e ) {
            TTS_Logger::log( 'Backup creation failed: ' . $e->getMessage(), 'error' );
            
            return array(
                'success' => false,
                'message' => __( 'Backup creation failed: ', 'trello-social-auto-publisher' ) . $e->getMessage()
            );
        }
    }

    /**
     * Get full backup data
     */
    private function get_full_backup_data() {
        return array(
            'settings' => $this->get_settings_backup_data(),
            'clients' => $this->get_clients_backup_data(),
            'logs' => $this->get_logs_backup_data(),
            'posts' => $this->get_posts_backup_data(),
            'media' => $this->get_media_backup_data()
        );
    }

    /**
     * Get settings backup data
     */
    private function get_settings_backup_data() {
        $settings = array();
        $settings_keys = array(
            'tts_facebook_app_id',
            'tts_facebook_app_secret',
            'tts_instagram_app_id',
            'tts_instagram_app_secret',
            'tts_youtube_client_id',
            'tts_youtube_client_secret',
            'tts_tiktok_client_key',
            'tts_tiktok_client_secret',
            'tts_default_schedule_time',
            'tts_post_frequency',
            'tts_retry_attempts',
            'tts_enable_analytics',
            'tts_performance_mode'
        );
        
        foreach ( $settings_keys as $key ) {
            $settings[ $key ] = get_option( $key );
        }
        
        return $settings;
    }

    /**
     * Get clients backup data
     */
    private function get_clients_backup_data() {
        global $wpdb;
        
        $clients = $wpdb->get_results(
            "SELECT * FROM {$wpdb->posts} WHERE post_type = 'tts_client'",
            ARRAY_A
        );
        
        // Get client meta data
        foreach ( $clients as &$client ) {
            $client['meta'] = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d",
                    $client['ID']
                ),
                ARRAY_A
            );
        }
        
        return $clients;
    }

    /**
     * Get logs backup data
     */
    private function get_logs_backup_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'tts_logs';
        return $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY timestamp DESC LIMIT 1000", ARRAY_A );
    }

    /**
     * Get posts backup data
     */
    private function get_posts_backup_data() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT p.*, pm.meta_key, pm.meta_value 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type IN ('post', 'page') 
             AND p.post_status = 'publish'
             ORDER BY p.post_date DESC 
             LIMIT 500",
            ARRAY_A
        );
    }

    /**
     * Get media backup data
     */
    private function get_media_backup_data() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT p.*, pm.meta_key, pm.meta_value 
             FROM {$wpdb->posts} p 
             LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'attachment'
             ORDER BY p.post_date DESC 
             LIMIT 200",
            ARRAY_A
        );
    }

    /**
     * Restore backup via AJAX
     */
    public function ajax_restore_backup() {
        check_ajax_referer( 'tts_backup_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $backup_filename = sanitize_file_name( $_POST['backup_filename'] ?? '' );
        $restore_type = sanitize_text_field( $_POST['restore_type'] ?? 'full' );
        
        $result = $this->restore_backup( $backup_filename, $restore_type );
        wp_send_json( $result );
    }

    /**
     * Restore backup from file
     *
     * @param string $filename Backup filename
     * @param string $restore_type Type of restore
     * @return array Restore result
     */
    public function restore_backup( $filename, $restore_type = 'full' ) {
        try {
            $backup_path = $this->get_backup_directory() . $filename;
            
            if ( ! file_exists( $backup_path ) ) {
                throw new Exception( __( 'Backup file not found', 'trello-social-auto-publisher' ) );
            }
            
            // Decompress backup
            $decompressed_path = $this->decompress_backup( $backup_path );
            
            // Load backup data
            $backup_content = file_get_contents( $decompressed_path );
            $backup_data = json_decode( $backup_content, true );
            
            if ( ! $backup_data ) {
                throw new Exception( __( 'Invalid backup file format', 'trello-social-auto-publisher' ) );
            }
            
            // Perform restore based on type
            switch ( $restore_type ) {
                case 'full':
                    $this->restore_full_backup( $backup_data['data'] );
                    break;
                case 'settings':
                    $this->restore_settings_backup( $backup_data['data']['settings'] ?? $backup_data['data'] );
                    break;
                case 'clients':
                    $this->restore_clients_backup( $backup_data['data']['clients'] ?? $backup_data['data'] );
                    break;
            }
            
            // Clean up decompressed file
            unlink( $decompressed_path );
            
            TTS_Logger::log( 'Backup restored successfully: ' . $filename );
            
            return array(
                'success' => true,
                'message' => __( 'Backup restored successfully', 'trello-social-auto-publisher' )
            );
            
        } catch ( Exception $e ) {
            TTS_Logger::log( 'Backup restore failed: ' . $e->getMessage(), 'error' );
            
            return array(
                'success' => false,
                'message' => __( 'Backup restore failed: ', 'trello-social-auto-publisher' ) . $e->getMessage()
            );
        }
    }

    /**
     * Restore full backup
     */
    private function restore_full_backup( $data ) {
        if ( isset( $data['settings'] ) ) {
            $this->restore_settings_backup( $data['settings'] );
        }
        if ( isset( $data['clients'] ) ) {
            $this->restore_clients_backup( $data['clients'] );
        }
    }

    /**
     * Restore settings backup
     */
    private function restore_settings_backup( $settings ) {
        foreach ( $settings as $key => $value ) {
            update_option( $key, $value );
        }
    }

    /**
     * Restore clients backup
     */
    private function restore_clients_backup( $clients ) {
        global $wpdb;
        
        foreach ( $clients as $client ) {
            // Insert or update client post
            $post_data = array(
                'post_title' => $client['post_title'],
                'post_content' => $client['post_content'],
                'post_status' => $client['post_status'],
                'post_type' => 'tts_client'
            );
            
            $post_id = wp_insert_post( $post_data );
            
            if ( $post_id && isset( $client['meta'] ) ) {
                foreach ( $client['meta'] as $meta ) {
                    update_post_meta( $post_id, $meta['meta_key'], $meta['meta_value'] );
                }
            }
        }
    }

    /**
     * List available backups via AJAX
     */
    public function ajax_list_backups() {
        check_ajax_referer( 'tts_backup_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $backups = $this->list_backups();
        wp_send_json_success( $backups );
    }

    /**
     * List available backups
     */
    public function list_backups() {
        $backup_dir = $this->get_backup_directory();
        $backups = array();
        
        if ( is_dir( $backup_dir ) ) {
            $files = scandir( $backup_dir );
            
            foreach ( $files as $file ) {
                if ( strpos( $file, 'tts-backup-' ) === 0 && strpos( $file, '.gz' ) !== false ) {
                    $file_path = $backup_dir . $file;
                    $backups[] = array(
                        'filename' => $file,
                        'size' => $this->format_file_size( filesize( $file_path ) ),
                        'date' => date( 'Y-m-d H:i:s', filemtime( $file_path ) ),
                        'type' => $this->extract_backup_type( $file )
                    );
                }
            }
        }
        
        return $backups;
    }

    /**
     * Create automatic backup
     */
    public function create_automatic_backup() {
        $this->create_backup( 'full' );
        $this->cleanup_old_backups();
    }

    /**
     * Delete backup via AJAX
     */
    public function ajax_delete_backup() {
        check_ajax_referer( 'tts_backup_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $filename = sanitize_file_name( $_POST['filename'] ?? '' );
        $result = $this->delete_backup( $filename );
        
        wp_send_json( $result );
    }

    /**
     * Delete backup file
     */
    public function delete_backup( $filename ) {
        $backup_path = $this->get_backup_directory() . $filename;
        
        if ( file_exists( $backup_path ) && unlink( $backup_path ) ) {
            return array(
                'success' => true,
                'message' => __( 'Backup deleted successfully', 'trello-social-auto-publisher' )
            );
        }
        
        return array(
            'success' => false,
            'message' => __( 'Failed to delete backup', 'trello-social-auto-publisher' )
        );
    }

    /**
     * Download backup via AJAX
     */
    public function ajax_download_backup() {
        check_ajax_referer( 'tts_backup_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'trello-social-auto-publisher' ) );
        }

        $filename = sanitize_file_name( $_GET['filename'] ?? '' );
        $this->download_backup( $filename );
    }

    /**
     * Download backup file
     */
    public function download_backup( $filename ) {
        $backup_path = $this->get_backup_directory() . $filename;
        
        if ( ! file_exists( $backup_path ) ) {
            wp_die( esc_html__( 'Backup file not found', 'trello-social-auto-publisher' ) );
        }
        
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $backup_path ) );
        
        readfile( $backup_path );
        exit;
    }

    /**
     * Generate backup filename
     */
    private function generate_backup_filename( $type ) {
        return 'tts-backup-' . $type . '-' . date( 'Y-m-d-H-i-s' ) . '.json';
    }

    /**
     * Get backup directory path
     */
    private function get_backup_directory() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/tts-backups/';
    }

    /**
     * Ensure backup directory exists
     */
    private function ensure_backup_directory() {
        $backup_dir = $this->get_backup_directory();
        
        if ( ! file_exists( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
            
            // Add security files
            file_put_contents( $backup_dir . '.htaccess', 'deny from all' );
            file_put_contents( $backup_dir . 'index.php', '<?php // Silence is golden' );
        }
    }

    /**
     * Compress backup file
     */
    private function compress_backup( $file_path ) {
        $compressed_path = $file_path . '.gz';
        
        $fp_out = gzopen( $compressed_path, 'wb9' );
        $fp_in = fopen( $file_path, 'rb' );
        
        while ( ! feof( $fp_in ) ) {
            gzwrite( $fp_out, fread( $fp_in, 1024 * 512 ) );
        }
        
        fclose( $fp_in );
        gzclose( $fp_out );
        
        return $compressed_path;
    }

    /**
     * Decompress backup file
     */
    private function decompress_backup( $compressed_path ) {
        $decompressed_path = str_replace( '.gz', '', $compressed_path );
        
        $fp_out = fopen( $decompressed_path, 'wb' );
        $fp_in = gzopen( $compressed_path, 'rb' );
        
        while ( ! gzeof( $fp_in ) ) {
            fwrite( $fp_out, gzread( $fp_in, 1024 * 512 ) );
        }
        
        fclose( $fp_out );
        gzclose( $fp_in );
        
        return $decompressed_path;
    }

    /**
     * Cleanup old backups
     */
    private function cleanup_old_backups() {
        $backup_dir = $this->get_backup_directory();
        $files = glob( $backup_dir . 'tts-backup-*.gz' );
        
        // Keep only the latest 10 backups
        if ( count( $files ) > 10 ) {
            usort( $files, function( $a, $b ) {
                return filemtime( $b ) - filemtime( $a );
            });
            
            $files_to_delete = array_slice( $files, 10 );
            foreach ( $files_to_delete as $file ) {
                unlink( $file );
            }
        }
    }

    /**
     * Format file size
     */
    private function format_file_size( $bytes ) {
        if ( $bytes >= 1073741824 ) {
            return number_format( $bytes / 1073741824, 2 ) . ' GB';
        } elseif ( $bytes >= 1048576 ) {
            return number_format( $bytes / 1048576, 2 ) . ' MB';
        } elseif ( $bytes >= 1024 ) {
            return number_format( $bytes / 1024, 2 ) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Extract backup type from filename
     */
    private function extract_backup_type( $filename ) {
        if ( strpos( $filename, '-full-' ) !== false ) return 'full';
        if ( strpos( $filename, '-settings-' ) !== false ) return 'settings';
        if ( strpos( $filename, '-clients-' ) !== false ) return 'clients';
        if ( strpos( $filename, '-logs-' ) !== false ) return 'logs';
        return 'unknown';
    }
}

// Initialize backup system
new TTS_Backup();