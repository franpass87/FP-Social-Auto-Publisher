<?php
/**
 * Timing utilities for engagement-based suggestions.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provide posting time suggestions based on past engagement.
 */
class TTS_Timing {

    /**
     * Suggest the time range with highest engagement for a given channel.
     *
     * @param string $channel Channel slug.
     * @return string Suggested time range or empty string.
     */
    public static function suggest_time( $channel ) {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'meta_query'     => array(
                    array(
                        'key'     => '_tts_metrics',
                        'compare' => 'EXISTS',
                    ),
                ),
                'fields'         => 'ids',
            )
        );

        if ( empty( $posts ) ) {
            return '';
        }

        $bands = array(
            '00:00-06:00' => 0,
            '06:00-12:00' => 0,
            '12:00-18:00' => 0,
            '18:00-24:00' => 0,
        );

        foreach ( $posts as $post_id ) {
            $metrics = get_post_meta( $post_id, '_tts_metrics', true );
            if ( ! is_array( $metrics ) || ! isset( $metrics[ $channel ] ) ) {
                continue;
            }

            $publish_at = get_post_meta( $post_id, '_tts_publish_at', true );
            if ( ! $publish_at ) {
                $publish_at = get_post_field( 'post_date', $post_id );
            }
            $hour  = (int) date( 'H', strtotime( $publish_at ) );
            $count = (int) $metrics[ $channel ];

            if ( $hour < 6 ) {
                $bands['00:00-06:00'] += $count;
            } elseif ( $hour < 12 ) {
                $bands['06:00-12:00'] += $count;
            } elseif ( $hour < 18 ) {
                $bands['12:00-18:00'] += $count;
            } else {
                $bands['18:00-24:00'] += $count;
            }
        }

        arsort( $bands );
        $top = key( $bands );
        return $bands[ $top ] > 0 ? $top : '';
    }
}
