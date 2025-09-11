<?php
/**
 * Fetch social metrics for published posts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Analytics utilities.
 */
class TTS_Analytics {

    /**
     * Fetch metrics for all published social posts.
     */
    public static function fetch_all() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_key'       => '_published_status',
                'meta_value'     => 'published',
            )
        );

        foreach ( $posts as $post_id ) {
            $client_id = (int) get_post_meta( $post_id, '_tts_client_id', true );
            if ( ! $client_id ) {
                continue;
            }

            $tokens = array(
                'facebook'  => get_post_meta( $client_id, '_tts_fb_token', true ),
                'instagram' => get_post_meta( $client_id, '_tts_ig_token', true ),
                'youtube'   => get_post_meta( $client_id, '_tts_yt_token', true ),
                'tiktok'    => get_post_meta( $client_id, '_tts_tt_token', true ),
            );

            $channels = get_post_meta( $post_id, '_tts_social_channel', true );
            $channels = is_array( $channels ) ? $channels : array( $channels );

            $metrics = array();
            foreach ( $channels as $ch ) {
                $method = 'fetch_' . $ch . '_metrics';
                if ( method_exists( __CLASS__, $method ) ) {
                    $creds  = isset( $tokens[ $ch ] ) ? $tokens[ $ch ] : '';
                    $result = self::$method( $post_id, $creds );
                    if ( ! is_wp_error( $result ) ) {
                        $metrics[ $ch ] = self::count_interactions( (array) $result );
                    }
                }
            }

            if ( ! empty( $metrics ) ) {
                update_post_meta( $post_id, '_tts_metrics', $metrics );
            }
        }
    }

    /**
     * Count total interactions from a metrics array.
     *
     * @param array $data Metrics data.
     * @return int
     */
    private static function count_interactions( $data ) {
        $sum = 0;
        foreach ( $data as $value ) {
            if ( is_array( $value ) ) {
                $sum += self::count_interactions( $value );
            } elseif ( is_numeric( $value ) ) {
                $sum += (int) $value;
            }
        }
        return $sum;
    }

    /**
     * Fetch Facebook metrics.
     *
     * @param int   $post_id     Post ID.
     * @param mixed $credentials Access token.
     *
     * @return array|WP_Error
     */
    public static function fetch_facebook_metrics( $post_id, $credentials ) {
        if ( empty( $credentials ) ) {
            return new WP_Error( 'fb_no_token', __( 'Facebook token missing', 'trello-social-auto-publisher' ) );
        }

        $remote_id = get_post_meta( $post_id, '_tts_facebook_id', true );
        if ( empty( $remote_id ) ) {
            return new WP_Error( 'fb_no_id', __( 'Missing Facebook post ID', 'trello-social-auto-publisher' ) );
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s?fields=engagement&access_token=%s', rawurlencode( $remote_id ), rawurlencode( $credentials ) );
        $response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $data['engagement'] ) ? $data['engagement'] : array();
    }

    /**
     * Fetch Instagram metrics.
     *
     * @param int   $post_id     Post ID.
     * @param mixed $credentials Access token.
     *
     * @return array|WP_Error
     */
    public static function fetch_instagram_metrics( $post_id, $credentials ) {
        if ( empty( $credentials ) ) {
            return new WP_Error( 'ig_no_token', __( 'Instagram token missing', 'trello-social-auto-publisher' ) );
        }

        $remote_id = get_post_meta( $post_id, '_tts_instagram_id', true );
        if ( empty( $remote_id ) ) {
            return new WP_Error( 'ig_no_id', __( 'Missing Instagram media ID', 'trello-social-auto-publisher' ) );
        }

        $endpoint = sprintf( 'https://graph.facebook.com/%s?fields=like_count,comments_count&access_token=%s', rawurlencode( $remote_id ), rawurlencode( $credentials ) );
        $response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data;
    }

    /**
     * Fetch YouTube metrics.
     *
     * @param int   $post_id     Post ID.
     * @param mixed $credentials API key or access token.
     *
     * @return array|WP_Error
     */
    public static function fetch_youtube_metrics( $post_id, $credentials ) {
        if ( empty( $credentials ) ) {
            return new WP_Error( 'yt_no_token', __( 'YouTube token missing', 'trello-social-auto-publisher' ) );
        }

        $remote_id = get_post_meta( $post_id, '_tts_youtube_id', true );
        if ( empty( $remote_id ) ) {
            return new WP_Error( 'yt_no_id', __( 'Missing YouTube video ID', 'trello-social-auto-publisher' ) );
        }

        $endpoint = add_query_arg(
            array(
                'id'   => $remote_id,
                'part' => 'statistics',
                'key'  => $credentials,
            ),
            'https://www.googleapis.com/youtube/v3/videos'
        );
        $response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['items'][0]['statistics'] ) ) {
            return $data['items'][0]['statistics'];
        }
        return array();
    }

    /**
     * Fetch TikTok metrics.
     *
     * @param int   $post_id     Post ID.
     * @param mixed $credentials Access token.
     *
     * @return array|WP_Error
     */
    public static function fetch_tiktok_metrics( $post_id, $credentials ) {
        if ( empty( $credentials ) ) {
            return new WP_Error( 'tt_no_token', __( 'TikTok token missing', 'trello-social-auto-publisher' ) );
        }

        $remote_id = get_post_meta( $post_id, '_tts_tiktok_id', true );
        if ( empty( $remote_id ) ) {
            return new WP_Error( 'tt_no_id', __( 'Missing TikTok video ID', 'trello-social-auto-publisher' ) );
        }

        $endpoint = sprintf( 'https://open.tiktokapis.com/v2/video/%s/metrics?access_token=%s', rawurlencode( $remote_id ), rawurlencode( $credentials ) );
        $response = wp_remote_get( $endpoint, array( 'timeout' => 20 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $data;
    }
}
