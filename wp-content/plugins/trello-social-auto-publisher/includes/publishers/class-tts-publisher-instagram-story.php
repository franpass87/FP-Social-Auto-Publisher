<?php
/**
 * Instagram Story publisher.
 *
 * @package FPPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing Instagram Stories.
 */
class TTS_Publisher_Instagram_Story {

    /**
     * Publish a Story to Instagram.
     *
     * @param int    $post_id     Post ID.
     * @param string $credentials IG user ID and access token.
     * @param string $media_url   URL of the media to publish.
     * @return array|\WP_Error Log data or error.
     */
    public function publish_story( $post_id, $credentials, $media_url ) {
        if ( empty( $credentials ) || empty( $media_url ) ) {
            $error = __( 'Missing credentials or media for Instagram Story', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            return new \WP_Error( 'instagram_story_missing_data', $error );
        }

        list( $ig_user_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        if ( empty( $ig_user_id ) || empty( $token ) ) {
            $error = __( 'Invalid Instagram credentials', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            return new \WP_Error( 'instagram_story_bad_credentials', $error );
        }

        $mime = wp_check_filetype( $media_url );
        $endpoint = sprintf( 'https://graph.facebook.com/%s/media', $ig_user_id );
        $body     = array(
            'access_token' => $token,
            'media_type'   => 'STORIES',
        );

        if ( 0 === strpos( $mime['type'], 'image/' ) ) {
            $body['image_url'] = $media_url;
        } else {
            $body['video_url'] = $media_url;
        }

        $result = wp_remote_post(
            $endpoint,
            array(
                'body'    => $body,
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            return $result;
        }

        $data = json_decode( wp_remote_retrieve_body( $result ), true );
        $code = wp_remote_retrieve_response_code( $result );
        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, $data );
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            return new \WP_Error( 'instagram_story_error', $error, $data );
        }

        $publish_endpoint = sprintf( 'https://graph.facebook.com/%s/media_publish', $ig_user_id );
        $publish_result   = wp_remote_post(
            $publish_endpoint,
            array(
                'body'    => array(
                    'creation_id'  => $data['id'],
                    'access_token' => $token,
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $publish_result ) ) {
            $error = $publish_result->get_error_message();
            tts_log_event( $post_id, 'instagram_story', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            return $publish_result;
        }

        $publish_data = json_decode( wp_remote_retrieve_body( $publish_result ), true );
        $publish_code = wp_remote_retrieve_response_code( $publish_result );
        if ( 200 !== $publish_code || empty( $publish_data['id'] ) ) {
            $error = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : __( 'Unknown error', 'fp-publisher' );
            tts_log_event( $post_id, 'instagram_story', 'error', $error, $publish_data );
            tts_notify_publication( $post_id, 'error', 'instagram_story' );
            return new \WP_Error( 'instagram_story_error', $error, $publish_data );
        }

        $response = array(
            'message' => __( 'Published Instagram Story', 'fp-publisher' ),
            'id'      => $publish_data['id'],
        );
        tts_log_event( $post_id, 'instagram_story', 'success', $response['message'], '' );
        tts_notify_publication( $post_id, 'success', 'instagram_story' );
        return $response;
    }
}
