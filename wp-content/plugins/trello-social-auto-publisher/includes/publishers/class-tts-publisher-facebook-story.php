<?php
/**
 * Facebook Story publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing Facebook Stories.
 */
class TTS_Publisher_Facebook_Story {

    /**
     * Publish a Story to Facebook.
     *
     * @param int    $post_id     Post ID.
     * @param mixed  $credentials Page ID and access token.
     * @param string $media_url   URL of the media to publish.
     * @return array|\WP_Error Log data or error.
     */
    public function publish_story( $post_id, $credentials, $media_url ) {
        if ( empty( $credentials ) || empty( $media_url ) ) {
            $error = __( 'Missing credentials or media for Facebook Story', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook_story' );
            return new \WP_Error( 'facebook_story_missing_data', $error );
        }

        $page_id = '';
        $token   = $credentials;
        if ( false !== strpos( $credentials, '|' ) ) {
            list( $page_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        } else {
            $page_id = get_post_meta( $post_id, '_tts_fb_page_id', true );
        }
        if ( empty( $page_id ) || empty( $token ) ) {
            $error = __( 'Invalid Facebook credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook_story' );
            return new \WP_Error( 'facebook_story_bad_credentials', $error );
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s/stories', $page_id );
        $body     = array(
            'access_token' => $token,
            'file_url'     => $media_url,
        );

        $result = wp_remote_post(
            $endpoint,
            array(
                'body'    => $body,
                'timeout' => 20,
            )
        );
        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'facebook_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'facebook_story' );
            return $result;
        }

        $data = json_decode( wp_remote_retrieve_body( $result ), true );
        $code = wp_remote_retrieve_response_code( $result );
        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'facebook_story', 'error', $error, $data );
            tts_notify_publication( $post_id, 'error', 'facebook_story' );
            return new \WP_Error( 'facebook_story_error', $error, $data );
        }

        $response = array(
            'message' => __( 'Published Facebook Story', 'trello-social-auto-publisher' ),
            'id'      => $data['id'],
        );
        tts_log_event( $post_id, 'facebook_story', 'success', $response['message'], '' );
        tts_notify_publication( $post_id, 'success', 'facebook_story' );
        return $response;
    }
}
