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

        $attachment_ids = get_post_meta( $post_id, '_tts_attachment_ids', true );
        $attachment_ids = is_array( $attachment_ids ) ? array_map( 'intval', $attachment_ids ) : array();
        $images         = array();
        $videos         = array();
        foreach ( $attachment_ids as $att_id ) {
            $mime = get_post_mime_type( $att_id );
            if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                $images[] = $att_id;
            } elseif ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                $videos[] = $att_id;
            }
        }

        if ( empty( $images ) && empty( $videos ) ) {
            $manual_id = (int) get_post_meta( $post_id, '_tts_manual_media', true );
            if ( $manual_id ) {
                $mime = get_post_mime_type( $manual_id );
                if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                    $images[] = $manual_id;
                } elseif ( $mime && 0 === strpos( $mime, 'video/' ) ) {
                    $videos[] = $manual_id;
                }
            }
        }

        if ( empty( $images ) && empty( $videos ) ) {
            $endpoint = sprintf( 'https://graph.facebook.com/%s/feed', $page_id );
            $link     = get_permalink( $post_id );
            $body     = array(
                'message'      => $message,
                'access_token' => $token,
            );
            if ( $link ) {
                $body['link'] = $link;
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

        foreach ( $videos as $index => $video_id ) {
            $endpoint   = sprintf( 'https://graph.facebook.com/%s/videos', $page_id );
            $video_body = array(
                'access_token' => $token,
                'file_url'     => wp_get_attachment_url( $video_id ),
            );
            if ( 0 === $index ) {
                $video_body['description'] = $message;
            }
            $result = wp_remote_post(
                $endpoint,
                array(
                    'body'    => $video_body,
                    'timeout' => 20,
                )
            );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
                tts_log_event( $post_id, 'facebook', 'error', $error, '' );
                tts_notify_publication( $post_id, 'error', 'facebook' );
                return $result;
            }
            $code = wp_remote_retrieve_response_code( $result );
            $data = json_decode( wp_remote_retrieve_body( $result ), true );
            if ( 200 !== $code || empty( $data['id'] ) ) {
                $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
                tts_log_event( $post_id, 'facebook', 'error', $error, $data );
                tts_notify_publication( $post_id, 'error', 'facebook' );
                return new \WP_Error( 'facebook_error', $error, $data );
            }
        }

        foreach ( $images as $index => $image_id ) {
            $endpoint  = sprintf( 'https://graph.facebook.com/%s/photos', $page_id );
            $image_url = wp_get_attachment_url( $image_id );
            $img_body  = array(
                'access_token' => $token,
                'source'       => $image_url,
            );
            if ( 0 === $index && empty( $videos ) ) {
                $img_body['message'] = $message;
            }
            $result = wp_remote_post(
                $endpoint,
                array(
                    'body'    => $img_body,
                    'timeout' => 20,
                )
            );
            if ( is_wp_error( $result ) ) {
                $error = $result->get_error_message();
                tts_log_event( $post_id, 'facebook', 'error', $error, '' );
                tts_notify_publication( $post_id, 'error', 'facebook' );
                return $result;
            }
            $code = wp_remote_retrieve_response_code( $result );
            $data = json_decode( wp_remote_retrieve_body( $result ), true );
            if ( 200 !== $code || empty( $data['id'] ) ) {
                $error = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unknown error', 'trello-social-auto-publisher' );
                tts_log_event( $post_id, 'facebook', 'error', $error, $data );
                tts_notify_publication( $post_id, 'error', 'facebook' );
                return new \WP_Error( 'facebook_error', $error, $data );
            }
        }

        $response = __( 'Published to Facebook', 'trello-social-auto-publisher' );
        tts_log_event( $post_id, 'facebook', 'success', $response, $data );
        tts_notify_publication( $post_id, 'success', 'facebook' );
        return $response;
    }
}
