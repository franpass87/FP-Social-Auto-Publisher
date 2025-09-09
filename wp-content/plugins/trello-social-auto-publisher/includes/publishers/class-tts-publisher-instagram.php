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
     * Credentials must be provided in the form `{ig-user-id}|{access-token}` where `ig-user-id` is
     * the Instagram Business account ID. The access token requires the following permissions:
     * `instagram_basic`, `pages_show_list`, `pages_read_engagement`, and `pages_manage_posts`.
     *
     * @param int         $post_id     Post ID.
     * @param string      $credentials Instagram user ID and access token.
     * @param string      $message     Message to publish.
     * @return string|\WP_Error Log message or error.
     */
    public function publish( $post_id, $credentials, $message ) {
        if ( empty( $credentials ) ) {
            $message = __( 'Instagram token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $message, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_no_token', $message );
        }

        list( $ig_user_id, $token ) = array_pad( explode( '|', $credentials, 2 ), 2, '' );
        if ( empty( $ig_user_id ) || empty( $token ) ) {
            $error = __( 'Invalid Instagram credentials', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_bad_credentials', $error );
        }

        $image_url = '';
        $images    = get_attached_media( 'image', $post_id );
        if ( ! empty( $images ) ) {
            $image_url = wp_get_attachment_url( reset( $images )->ID );
        }

        $video_url = '';
        if ( empty( $image_url ) ) {
            $videos = get_attached_media( 'video', $post_id );
            if ( ! empty( $videos ) ) {
                $video_url = wp_get_attachment_url( reset( $videos )->ID );
            }
        }

        if ( empty( $image_url ) && empty( $video_url ) ) {
            $error = __( 'No image or video to publish', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_no_media', $error );
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s/media', $ig_user_id );
        $body     = array(
            'caption'      => $message,
            'access_token' => $token,
        );

        if ( $image_url ) {
            $body['image_url'] = $image_url;
        } else {
            $body['media_type'] = 'VIDEO';
            $body['video_url']  = $video_url;
        }

        $result = wp_remote_post( $endpoint, array( 'body' => $body ) );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return $result;
        }

        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );

        if ( 200 !== $code || empty( $data['id'] ) ) {
            $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'error', $error, $data );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return new \WP_Error( 'instagram_error', $error, $data );
        }

        $publish_endpoint = sprintf( 'https://graph.facebook.com/%s/media_publish', $ig_user_id );
        $publish_body     = array(
            'creation_id'  => $data['id'],
            'access_token' => $token,
        );

        $publish_result = wp_remote_post( $publish_endpoint, array( 'body' => $publish_body ) );

        if ( is_wp_error( $publish_result ) ) {
            $error = $publish_result->get_error_message();
            tts_log_event( $post_id, 'instagram', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'instagram' );
            return $publish_result;
        }

        $publish_code = wp_remote_retrieve_response_code( $publish_result );
        $publish_data = json_decode( wp_remote_retrieve_body( $publish_result ), true );

        if ( 200 === $publish_code && isset( $publish_data['id'] ) ) {
            $response = __( 'Published to Instagram', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'instagram', 'success', $response, $publish_data );
            tts_notify_publication( $post_id, 'success', 'instagram' );
            return $response;
        }

        $error = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'instagram', 'error', $error, $publish_data );
        tts_notify_publication( $post_id, 'error', 'instagram' );
        return new \WP_Error( 'instagram_error', $error, $publish_data );
    }
}
