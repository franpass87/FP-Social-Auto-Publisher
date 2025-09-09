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
     * @param int    $post_id     Post ID.
     * @param mixed  $credentials Credentials used for publishing.
     * @param string $message     Message to publish.
     * @return string Log message.
     */
    public function publish( $post_id, $credentials, $message ) {
        if ( empty( $credentials ) ) {
            $message = __( 'Facebook token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $message, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return $message;
        }

        $response = __( 'Published to Facebook', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'success', $response, array( 'message' => $message ) );
        tts_notify_publication( $post_id, 'success', 'facebook' );
        return $response;
    }
}
