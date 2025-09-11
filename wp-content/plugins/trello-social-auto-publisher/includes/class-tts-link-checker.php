<?php
/**
 * Verify URLs in scheduled messages.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Link checker for scheduled posts.
 */
class TTS_Link_Checker {

    /**
     * Verify all URLs present in messages for a given post.
     *
     * @param int $post_id Post ID.
     */
    public static function verify_urls( $post_id ) {
        $channels = get_post_meta( $post_id, '_tts_social_channel', true );
        $channels = is_array( $channels ) ? $channels : array( $channels );
        if ( empty( $channels ) ) {
            return;
        }

        $options  = get_option( 'tts_settings', array() );
        $notifier = new TTS_Notifier();

        foreach ( $channels as $channel ) {
            $custom_message = get_post_meta( $post_id, '_tts_message_' . $channel, true );
            if ( $custom_message ) {
                $message = $custom_message;
            } else {
                $template = isset( $options[ $channel . '_template' ] ) ? $options[ $channel . '_template' ] : '';
                $message  = $template ? tts_apply_template( $template, $post_id, $channel ) : '';
            }

            if ( empty( $message ) ) {
                continue;
            }

            preg_match_all( '/https?:\/\/[^\s]+/', $message, $matches );
            if ( empty( $matches[0] ) ) {
                continue;
            }

            $urls = array_unique( $matches[0] );
            foreach ( $urls as $url ) {
                $response = wp_remote_head( $url, array( 'timeout' => 20 ) );
                $code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );

                if ( is_wp_error( $response ) || $code >= 400 ) {
                    $error_message = is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_message( $response );
                    tts_log_event(
                        $post_id,
                        'link_checker',
                        'error',
                        sprintf( __( 'URL check failed for %s', 'trello-social-auto-publisher' ), $url ),
                        $error_message
                    );

                    $notify_msg = sprintf( __( 'Invalid URL %1$s in post "%2$s"', 'trello-social-auto-publisher' ), $url, get_the_title( $post_id ) );
                    $notifier->notify_slack( $notify_msg );
                    $notifier->notify_email( __( 'Invalid URL detected', 'trello-social-auto-publisher' ), $notify_msg );
                }
            }
        }
    }
}
