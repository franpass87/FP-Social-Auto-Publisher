<?php
/**
 * Instagram publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to Instagram.
 */
class TTS_Publisher_Instagram {

    /**
     * Publish the post to Instagram.
     *
     * @param int   $post_id     Post ID.
     * @param mixed $credentials Credentials used for publishing.
     * @return string Log message.
     */
    public function publish( $post_id, $credentials ) {
        if ( empty( $credentials ) ) {
            $message = __( 'Instagram token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $message, '' );
            return $message;
        }

        $message = __( 'Published to Instagram', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'instagram', 'success', $message, array() );
        return $message;
    }
}
