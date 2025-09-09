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
            $error = __( 'Facebook token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return new \WP_Error( 'facebook_no_token', $error );
        }

        list( $page_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        if ( empty( $page_id ) || empty( $token ) ) {
            $error = __( 'Invalid Facebook credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return new \WP_Error( 'facebook_bad_credentials', $error );
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s/feed', $page_id );

        $body = array(
            'message'      => $message,
            'access_token' => $token,
        );

        $link = get_permalink( $post_id );
        if ( $link ) {
            $body['link'] = $link;
        }

        $attachments = get_attached_media( 'image', $post_id );
        $index       = 0;
        foreach ( $attachments as $attachment ) {
            $url = wp_get_attachment_url( $attachment->ID );
            if ( $url ) {
                $body[ 'attached_media[' . $index . ']' ] = wp_json_encode( array( 'link' => $url ) );
                $index++;
            }
        }

        $result = wp_remote_post( $endpoint, array( 'body' => $body ) );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return $result;
        }

        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );

        if ( 200 === $code && isset( $data['id'] ) ) {
            $response = __( 'Published to Facebook', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'success', $response, $data );
            return $response;
        }

        $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'error', $error, $data );
        tts_notify_publication( $post_id, 'error', 'facebook' );
        return new \WP_Error( 'facebook_error', $error, $data );
    }
}
