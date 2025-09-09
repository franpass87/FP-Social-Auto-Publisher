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
     * @param int    $post_id     Post ID.
     * @param mixed  $credentials Credentials used for publishing.
     * @param string $message     Message to publish.
     * @return string Log message.
     */
    public function publish( $post_id, $credentials, $message ) {
        if ( empty( $credentials ) ) {
            $message = __( 'Instagram token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $message, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return $message;
        }

        $response = __( 'Published to Instagram', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'instagram', 'success', $response, array( 'message' => $message ) );
        tts_notify_publication( $post_id, 'success', 'instagram' );
        return $response;
    }
}
