<?php
/**
 * URL shortening utilities.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides methods for shortening URLs.
 */
class TTS_Shortener {

    /**
     * Get the WordPress shortlink for a post.
     *
     * @param int $post_id Post ID.
     * @return string|false Short URL or false on failure.
     */
    public static function shorten_wp( $post_id ) {
        return wp_get_shortlink( $post_id );
    }

    /**
     * Shorten a URL using the Bitly API.
     *
     * @param string $url   Long URL.
     * @param string $token Bitly generic access token.
     * @return string Shortened URL or original URL on failure.
     */
    public static function shorten_bitly( $url, $token ) {
        $response = wp_remote_post(
            'https://api-ssl.bitly.com/v4/shorten',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array( 'long_url' => $url ) ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $url;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 201 !== $code ) {
            return $url;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $body['link'] ) ) {
            return $body['link'];
        }

        return $url;
    }
}
