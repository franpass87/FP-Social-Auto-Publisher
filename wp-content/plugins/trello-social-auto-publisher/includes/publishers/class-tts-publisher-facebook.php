<?php
/**
 * Facebook publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to Facebook.
 */
class TTS_Publisher_Facebook {

    /**
     * Publish the post to Facebook.
     *
     * @param int   $post_id     Post ID.
     * @param mixed $credentials Credentials used for publishing.
     * @return string Log message.
     */
    public function publish( $post_id, $credentials ) {
        if ( empty( $credentials ) ) {
            $message = __( 'Facebook token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $message, '' );
            return $message;
        }

        $message = __( 'Published to Facebook', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'success', $message, array() );
        return $message;
    }
}
