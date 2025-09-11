<?php
/**
 * TikTok publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to TikTok.
 */
class TTS_Publisher_TikTok {

    /**
     * Publish the post to TikTok.
     *
     * Requires an OAuth 2.0 access token granted with the `video.upload`
     * scope in order to send media and create the video post.
     *
     * @param int    $post_id Post ID.
     * @param string $token   OAuth 2.0 access token.
     * @param string $message Video description to publish.
     * @return string|\WP_Error Log message.
     */
    public function publish( $post_id, $token, $message ) {
        if ( empty( $token ) ) {
            $error = __( 'TikTok token missing or lacks video.upload scope', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'tiktok' );
            return new \WP_Error( 'tiktok_no_token', $error );
        }

        $lat = get_post_meta( $post_id, '_tts_lat', true );
        $lng = get_post_meta( $post_id, '_tts_lng', true );

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $videos         = array();
        foreach ( $attachment_ids as $att_id ) {
            $mime = get_post_mime_type( $att_id );
            if ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                $videos[] = $att_id;
            }
        }
        if ( empty( $videos ) ) {
            $manual_id = (int) get_post_meta( $post_id, '_tts_manual_media', true );
            if ( $manual_id && 0 === strpos( (string) get_post_mime_type( $manual_id ), 'video/' ) ) {
                $videos[] = $manual_id;
            }
        }
        if ( empty( $videos ) ) {
            $error = __( 'No video to publish', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'tiktok' );
            return new \WP_Error( 'tiktok_no_video', $error );
        }
        foreach ( $videos as $video_id ) {
            $video_path = get_attached_file( $video_id );
            if ( empty( $video_path ) || ! file_exists( $video_path ) ) {
                $error = __( 'Video file not found', 'trello-social-auto-publisher' );
                tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
                tts_notify_publication( $post_id, 'error', 'tiktok' );
                return new \WP_Error( 'tiktok_video_missing', $error );
            }
            $upload_endpoint = 'https://open.tiktokapis.com/v2/video/upload/';
            $upload_result   = wp_remote_post(
                $upload_endpoint,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'video/mp4',
                    ),
                    'body'    => file_get_contents( $video_path ),
                    'timeout' => 60,
                )
            );
            if ( is_wp_error( $upload_result ) ) {
                $error = $upload_result->get_error_message();
                tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
                tts_notify_publication( $post_id, 'error', 'tiktok' );
                return $upload_result;
            }
            $upload_code = wp_remote_retrieve_response_code( $upload_result );
            $upload_data = json_decode( wp_remote_retrieve_body( $upload_result ), true );
            if ( 200 !== $upload_code || empty( $upload_data['data']['video_id'] ) ) {
                $error = isset( $upload_data['error']['message'] ) ? $upload_data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
                tts_log_event( $post_id, 'tiktok', 'error', $error, $upload_data );
                tts_notify_publication( $post_id, 'error', 'tiktok' );
                return new \WP_Error( 'tiktok_upload_error', $error, $upload_data );
            }
            $publish_endpoint = 'https://open.tiktokapis.com/v2/video/publish/';
            $publish_body     = array(
                'video_id' => $upload_data['data']['video_id'],
                'caption'  => $message,
            );
            if ( $lat && $lng ) {
                $publish_body['location'] = array(
                    'latitude'  => $lat,
                    'longitude' => $lng,
                );
            }
            $publish_result = wp_remote_post(
                $publish_endpoint,
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $token,
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode( $publish_body ),
                    'timeout' => 60,
                )
            );
            if ( is_wp_error( $publish_result ) ) {
                $error = $publish_result->get_error_message();
                tts_log_event( $post_id, 'tiktok', 'error', $error, '' );
                tts_notify_publication( $post_id, 'error', 'tiktok' );
                return $publish_result;
            }
            $publish_code = wp_remote_retrieve_response_code( $publish_result );
            $publish_data = json_decode( wp_remote_retrieve_body( $publish_result ), true );
            if ( 200 !== $publish_code || empty( $publish_data['data']['video_id'] ) ) {
                $error = isset( $publish_data['error']['message'] ) ? $publish_data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
                tts_log_event( $post_id, 'tiktok', 'error', $error, $publish_data );
                tts_notify_publication( $post_id, 'error', 'tiktok' );
                return new \WP_Error( 'tiktok_publish_error', $error, $publish_data );
            }
        }
        $response = __( 'Published to TikTok', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'tiktok', 'success', $response, '' );
        tts_notify_publication( $post_id, 'success', 'tiktok' );
        return $response;
    }
}
