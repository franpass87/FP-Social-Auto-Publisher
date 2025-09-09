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
     * Requires the `pages_manage_posts` permission to publish and
     * `pages_read_engagement` to read the response from the API.
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

        $token   = $credentials;
        $page_id = '';

        // Allow credentials in the form page_id|token for backward compatibility.
        if ( false !== strpos( $credentials, '|' ) ) {
            list( $page_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        } else {
            // Retrieve the page ID from post meta when not included in the token.
            $page_id = get_post_meta( $post_id, '_tts_fb_page_id', true );
        }

        if ( empty( $page_id ) || empty( $token ) ) {
            $error = __( 'Invalid Facebook credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook' );
            return new \WP_Error( 'facebook_bad_credentials', $error );
        }

        $body = array(
            'message'      => $message,
            'access_token' => $token,
        );

        $attachments = get_attached_media( 'image', $post_id );
        if ( ! empty( $attachments ) ) {
            // Posting an image requires the photos edge and the "pages_manage_posts" permission.
            $endpoint       = sprintf( 'https://graph.facebook.com/%s/photos', $page_id );
            $body['source'] = wp_get_attachment_url( reset( $attachments )->ID );
        } else {
            // Text or link posts use the feed edge.
            $endpoint = sprintf( 'https://graph.facebook.com/%s/feed', $page_id );
            $link     = get_permalink( $post_id );
            if ( $link ) {
                $body['link'] = $link;
            }
        }

        // Possible errors include expired tokens or insufficient permissions (e.g. missing "pages_manage_posts").
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
            tts_notify_publication( $post_id, 'success', 'facebook' );
            return $response;
        }

        $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'error', $error, $data );
        tts_notify_publication( $post_id, 'error', 'facebook' );
        return new \WP_Error( 'facebook_error', $error, $data );
    }
}
