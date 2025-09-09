<?php
/**
 * Template utilities for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build a URL with UTM parameters for a specific channel.
 *
 * @param string $url     Base URL.
 * @param string $channel Channel name.
 * @return string URL with UTM parameters.
 */
function tts_build_utm_url( $url, $channel ) {
    $options = get_option( 'tts_settings', array() );
    $params  = array();

    foreach ( array( 'source', 'medium', 'campaign' ) as $param ) {
        $key = $channel . '_utm_' . $param;
        if ( ! empty( $options[ $key ] ) ) {
            $params[ 'utm_' . $param ] = $options[ $key ];
        }
    }

    if ( empty( $params ) ) {
        return $url;
    }

    return add_query_arg( $params, $url );
}

/**
 * Apply placeholders in template.
 *
 * @param string $template Template string.
 * @param int    $post_id  Post ID.
 * @param string $channel  Channel name.
 * @return string Processed template.
 */
function tts_apply_template( $template, $post_id, $channel ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return $template;
    }

    $url = get_permalink( $post_id );
    $url = tts_build_utm_url( $url, $channel );

    $replacements = array(
        '{title}' => get_the_title( $post_id ),
        '{url}'   => $url,
    );

    return strtr( $template, $replacements );
}
