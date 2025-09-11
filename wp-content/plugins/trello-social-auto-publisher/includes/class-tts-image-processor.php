<?php
/**
 * Image processing utilities.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles image resizing for social channels.
 */
class TTS_Image_Processor {
    /**
     * Resize an attachment for a specific channel.
     *
     * @param int    $attachment_id Attachment ID.
     * @param string $channel       Social channel key.
     * @return string URL to resized image or empty string on failure.
     */
    public function resize_for_channel( $attachment_id, $channel ) {
        $path = get_attached_file( $attachment_id );
        if ( ! $path || ! file_exists( $path ) ) {
            return '';
        }

        $options = get_option( 'tts_settings', array() );
        $defaults = array(
            'facebook'  => '1200x630',
            'instagram' => '1080x1080',
            'youtube'   => '1280x720',
            'tiktok'    => '1080x1920',
        );
        $size = isset( $options[ $channel . '_size' ] ) ? strtolower( $options[ $channel . '_size' ] ) : ( isset( $defaults[ $channel ] ) ? $defaults[ $channel ] : '' );
        if ( ! $size || ! preg_match( '/^(\d+)x(\d+)$/', $size, $m ) ) {
            return '';
        }
        $width  = (int) $m[1];
        $height = (int) $m[2];

        $editor = wp_get_image_editor( $path );
        if ( is_wp_error( $editor ) ) {
            return '';
        }

        $editor->resize( $width, $height, true );
        $dest_file = $editor->generate_filename( $channel );
        $result    = $editor->save( $dest_file );
        if ( is_wp_error( $result ) || empty( $result['path'] ) ) {
            return '';
        }

        $upload = wp_upload_dir();
        if ( strpos( $result['path'], $upload['basedir'] ) !== 0 ) {
            return '';
        }

        $url = str_replace( $upload['basedir'], $upload['baseurl'], $result['path'] );
        return $url;
    }
}
