<?php
/**
 * Remote media importer.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Imports media files from a URL into the Media Library.
 */
class TTS_Media_Importer {

    /**
     * Import a file from a URL and add it to the Media Library.
     *
     * @param string $url Remote file URL.
     * @return int|WP_Error Attachment ID on success, WP_Error on failure.
     */
    public function import_from_url( $url ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $url = esc_url_raw( $url );

        // For images try the media_sideload_image helper first.
        if ( preg_match( '/\.(jpe?g|png|gif|webp|bmp)$/i', $url ) ) {
            $attachment_id = media_sideload_image( $url, 0, null, 'id' );
            if ( ! is_wp_error( $attachment_id ) ) {
                return (int) $attachment_id;
            }
        }

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) {
            return $tmp;
        }

        $file_array = array(
            'name'     => sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) ),
            'tmp_name' => $tmp,
        );

        $overrides = array(
            'test_form' => false,
        );
        $results = wp_handle_sideload( $file_array, $overrides );

        if ( isset( $results['error'] ) ) {
            @unlink( $tmp );
            return new WP_Error( 'sideload_error', $results['error'] );
        }

        $attachment = array(
            'post_mime_type' => $results['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $file_array['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment( $attachment, $results['file'] );

        if ( ! is_wp_error( $attach_id ) ) {
            $attach_data = wp_generate_attachment_metadata( $attach_id, $results['file'] );
            wp_update_attachment_metadata( $attach_id, $attach_data );
        }

        return $attach_id;
    }
}
