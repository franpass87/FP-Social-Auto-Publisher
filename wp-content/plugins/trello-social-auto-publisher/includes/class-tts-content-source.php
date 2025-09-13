<?php
/**
 * Content Source Management System
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles content source tracking and management.
 */
class TTS_Content_Source {

    /**
     * Supported content sources.
     */
    const SOURCES = array(
        'trello'       => 'Trello',
        'google_drive' => 'Google Drive',
        'dropbox'      => 'Dropbox',
        'local_upload' => 'Local Upload',
        'manual'       => 'Manual Creation',
    );

    /**
     * Initialize content source system.
     */
    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_content_source_metabox' ) );
        add_action( 'save_post', array( $this, 'save_content_source_meta' ) );
        add_action( 'wp_ajax_tts_add_content_source', array( $this, 'ajax_add_content_source' ) );
        add_action( 'wp_ajax_tts_sync_content_sources', array( $this, 'ajax_sync_content_sources' ) );
    }

    /**
     * Add content source metabox to post editor.
     */
    public function add_content_source_metabox() {
        add_meta_box(
            'tts_content_source',
            __( 'Content Source', 'fp-publisher' ),
            array( $this, 'render_content_source_metabox' ),
            'tts_social_post',
            'side',
            'high'
        );
    }

    /**
     * Render content source metabox.
     *
     * @param WP_Post $post The post object.
     */
    public function render_content_source_metabox( $post ) {
        wp_nonce_field( 'tts_content_source_meta', 'tts_content_source_nonce' );
        
        $source = get_post_meta( $post->ID, '_tts_content_source', true );
        $source_reference = get_post_meta( $post->ID, '_tts_source_reference', true );
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="tts_content_source">' . esc_html__( 'Source', 'fp-publisher' ) . '</label></th>';
        echo '<td>';
        echo '<select name="tts_content_source" id="tts_content_source" class="widefat">';
        echo '<option value="">' . esc_html__( 'Select Source', 'fp-publisher' ) . '</option>';
        
        foreach ( self::SOURCES as $key => $label ) {
            $selected = selected( $source, $key, false );
            echo '<option value="' . esc_attr( $key ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
        }
        
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label for="tts_source_reference">' . esc_html__( 'Source Reference', 'fp-publisher' ) . '</label></th>';
        echo '<td>';
        echo '<input type="text" name="tts_source_reference" id="tts_source_reference" value="' . esc_attr( $source_reference ) . '" class="widefat" placeholder="' . esc_attr__( 'e.g., Trello Card ID, Drive File ID', 'fp-publisher' ) . '">';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
    }

    /**
     * Save content source metadata.
     *
     * @param int $post_id The post ID.
     */
    public function save_content_source_meta( $post_id ) {
        // Verify nonce.
        if ( ! isset( $_POST['tts_content_source_nonce'] ) || 
             ! wp_verify_nonce( $_POST['tts_content_source_nonce'], 'tts_content_source_meta' ) ) {
            return;
        }

        // Check user permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save content source.
        if ( isset( $_POST['tts_content_source'] ) ) {
            $source = sanitize_text_field( $_POST['tts_content_source'] );
            if ( array_key_exists( $source, self::SOURCES ) || empty( $source ) ) {
                update_post_meta( $post_id, '_tts_content_source', $source );
            }
        }

        // Save source reference.
        if ( isset( $_POST['tts_source_reference'] ) ) {
            $reference = sanitize_text_field( $_POST['tts_source_reference'] );
            update_post_meta( $post_id, '_tts_source_reference', $reference );
        }
    }

    /**
     * Get posts by content source.
     *
     * @param string $source The content source.
     * @param array  $args Additional query arguments.
     * @return WP_Query The query object.
     */
    public static function get_posts_by_source( $source, $args = array() ) {
        $default_args = array(
            'post_type'      => 'tts_social_post',
            'meta_query'     => array(
                array(
                    'key'     => '_tts_content_source',
                    'value'   => $source,
                    'compare' => '=',
                ),
            ),
            'posts_per_page' => -1,
        );

        $args = wp_parse_args( $args, $default_args );
        return new WP_Query( $args );
    }

    /**
     * Get content source statistics.
     *
     * @return array Source statistics.
     */
    public static function get_source_stats() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT meta_value as source, COUNT(*) as count 
             FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_tts_content_source' 
             AND p.post_type = 'tts_social_post' 
             AND p.post_status != 'trash'
             GROUP BY meta_value"
        );

        $stats = array();
        foreach ( $results as $result ) {
            $source_name = isset( self::SOURCES[ $result->source ] ) 
                         ? self::SOURCES[ $result->source ] 
                         : $result->source;
            $stats[ $result->source ] = array(
                'name'  => $source_name,
                'count' => intval( $result->count ),
            );
        }

        return $stats;
    }

    /**
     * AJAX handler for adding content source.
     */
    public function ajax_add_content_source() {
        check_ajax_referer( 'tts_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $source = sanitize_text_field( $_POST['source'] ?? '' );
        $reference = sanitize_text_field( $_POST['reference'] ?? '' );
        $title = sanitize_text_field( $_POST['title'] ?? '' );
        $content = wp_kses_post( $_POST['content'] ?? '' );

        if ( empty( $source ) || ! array_key_exists( $source, self::SOURCES ) ) {
            wp_send_json_error( __( 'Invalid content source', 'fp-publisher' ) );
        }

        // Create new social post.
        $post_data = array(
            'post_title'   => $title ?: __( 'New Content', 'fp-publisher' ),
            'post_content' => $content,
            'post_type'    => 'tts_social_post',
            'post_status'  => 'draft',
            'meta_input'   => array(
                '_tts_content_source'   => $source,
                '_tts_source_reference' => $reference,
            ),
        );

        $post_id = wp_insert_post( $post_data );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( $post_id->get_error_message() );
        }

        wp_send_json_success( array(
            'post_id' => $post_id,
            'message' => __( 'Content source added successfully', 'fp-publisher' ),
        ) );
    }

    /**
     * AJAX handler for syncing content sources.
     */
    public function ajax_sync_content_sources() {
        check_ajax_referer( 'tts_admin_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'fp-publisher' ) );
        }

        $source = sanitize_text_field( $_POST['source'] ?? '' );
        
        if ( empty( $source ) || ! array_key_exists( $source, self::SOURCES ) ) {
            wp_send_json_error( __( 'Invalid content source', 'fp-publisher' ) );
        }

        // Trigger sync based on source type.
        $synced_count = 0;
        switch ( $source ) {
            case 'trello':
                $synced_count = $this->sync_trello_content();
                break;
            case 'google_drive':
                $synced_count = $this->sync_google_drive_content();
                break;
            case 'dropbox':
                $synced_count = $this->sync_dropbox_content();
                break;
            default:
                wp_send_json_error( __( 'Sync not supported for this source', 'fp-publisher' ) );
        }

        wp_send_json_success( array(
            'synced_count' => $synced_count,
            'message'      => sprintf( 
                /* translators: %d: number of synced items */
                __( 'Synced %d items from %s', 'fp-publisher' ), 
                $synced_count, 
                self::SOURCES[ $source ] 
            ),
        ) );
    }

    /**
     * Sync Trello content.
     *
     * @return int Number of synced items.
     */
    private function sync_trello_content() {
        // Implement Trello sync logic here.
        // This would integrate with existing Trello webhook system.
        return 0;
    }

    /**
     * Sync Google Drive content.
     *
     * @return int Number of synced items.
     */
    private function sync_google_drive_content() {
        // Implement Google Drive sync logic here.
        return 0;
    }

    /**
     * Sync Dropbox content.
     *
     * @return int Number of synced items.
     */
    private function sync_dropbox_content() {
        // Implement Dropbox sync logic here.
        return 0;
    }
}

// Initialize the content source system.
new TTS_Content_Source();