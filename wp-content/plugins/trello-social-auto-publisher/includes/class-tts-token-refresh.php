<?php
/**
 * Token refresh utilities.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles refreshing of social tokens for clients.
 */
class TTS_Token_Refresh {

    /**
     * Refresh tokens for all tts_client posts.
     */
    public static function refresh_tokens() {
        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'posts_per_page' => -1,
                'post_status'    => 'any',
            )
        );

        if ( empty( $clients ) ) {
            return;
        }

        foreach ( $clients as $client ) {
            self::refresh_client_tokens( $client->ID );
        }
    }

    /**
     * Refresh tokens for a single client.
     *
     * @param int $client_id Client post ID.
     */
    protected static function refresh_client_tokens( $client_id ) {
        $tokens = array(
            'facebook'  => array(
                'meta'  => '_tts_fb_token',
                'token' => get_post_meta( $client_id, '_tts_fb_token', true ),
            ),
            'instagram' => array(
                'meta'  => '_tts_ig_token',
                'token' => get_post_meta( $client_id, '_tts_ig_token', true ),
            ),
        );

        foreach ( $tokens as $channel => $data ) {
            $token    = $data['token'];
            $meta_key = $data['meta'];

            if ( empty( $token ) ) {
                continue;
            }

            $grant    = 'facebook' === $channel ? 'fb_exchange_token' : 'ig_refresh_token';
            $endpoint = 'https://graph.facebook.com/v18.0/refresh_access_token';
            $url      = add_query_arg(
                array(
                    'grant_type'   => $grant,
                    'access_token' => $token,
                ),
                $endpoint
            );

            $response = wp_remote_get( $url );
            if ( is_wp_error( $response ) ) {
                tts_log_event( $client_id, $channel, 'error', 'Token refresh failed', $response->get_error_message() );
                continue;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['access_token'] ) ) {
                update_post_meta( $client_id, $meta_key, sanitize_text_field( $body['access_token'] ) );
            } else {
                tts_log_event( $client_id, $channel, 'error', 'Token refresh error', $body );
            }
        }
    }
}
