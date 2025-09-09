<?php
/**
 * YouTube publisher.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to YouTube.
 */
class TTS_Publisher_YouTube {

    /**
     * Publish the post to YouTube.
     *
     * @param int    $post_id     Post ID.
     * @param string $token       OAuth 2.0 access token.
     * @param string $message     Message to publish.
     * @return string|\WP_Error  Log message.
     */
    public function publish( $post_id, $token, $message ) {
        if ( empty( $token ) ) {
            $error = __( 'YouTube token missing', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return new \WP_Error( 'youtube_no_token', $error );
        }

        $videos = get_attached_media( 'video', $post_id );
        if ( empty( $videos ) ) {
            $error = __( 'No video to publish', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return new \WP_Error( 'youtube_no_video', $error );
        }

        $video      = reset( $videos );
        $video_path = get_attached_file( $video->ID );
        if ( empty( $video_path ) || ! file_exists( $video_path ) ) {
            $error = __( 'Video file not found', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return new \WP_Error( 'youtube_video_missing', $error );
        }

        $snippet = array(
            'title'           => get_the_title( $post_id ),
            'description'     => $message,
            'categoryId'      => '22',
            'shortsVideoType' => 'SHORTS',
        );

        $status = array(
            'privacyStatus' => 'public',
        );

        $metadata = array(
            'snippet' => $snippet,
            'status'  => $status,
        );

        $boundary  = wp_generate_password( 24, false );
        $delimiter = '-------' . $boundary;

        $body  = "--$delimiter\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= wp_json_encode( $metadata ) . "\r\n";

        $body .= "--$delimiter\r\n";
        $body .= "Content-Type: video/mp4\r\n";
        $body .= "Content-Transfer-Encoding: binary\r\n";
        $body .= 'Content-Disposition: form-data; name="video"; filename="' . basename( $video_path ) . '"' . "\r\n\r\n";
        $body .= file_get_contents( $video_path ) . "\r\n";
        $body .= "--$delimiter--";

        $endpoint = 'https://www.googleapis.com/upload/youtube/v3/videos?part=snippet,status&uploadType=multipart';
        $result   = wp_remote_post( $endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'multipart/related; boundary=' . $delimiter,
            ),
            'body'    => $body,
            'timeout' => 60,
        ) );

        if ( is_wp_error( $result ) ) {
            $error = $result->get_error_message();
            tts_log_event( $post_id, 'youtube', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'youtube' );
            return $result;
        }

        $code = wp_remote_retrieve_response_code( $result );
        $data = json_decode( wp_remote_retrieve_body( $result ), true );

        if ( 200 === $code && ! empty( $data['id'] ) ) {
            $response = __( 'Published to YouTube', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'youtube', 'success', $response, $data );
            return $response;
        }

        $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'youtube', 'error', $error, $data );
        tts_notify_publication( $post_id, 'error', 'youtube' );
        return new \WP_Error( 'youtube_error', $error, $data );
    }
}
