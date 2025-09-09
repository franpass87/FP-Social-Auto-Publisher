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
 * Apply placeholders in template.
 *
 * @param string $template Template string.
 * @param int    $post_id  Post ID.
 * @return string Processed template.
 */
function tts_apply_template( $template, $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post ) {
        return $template;
    }

    $options = get_option( 'tts_settings', array() );
    $url     = get_permalink( $post_id );
    $utm     = isset( $options['utm_options'] ) ? ltrim( $options['utm_options'], '?' ) : '';
    if ( $utm ) {
        $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . $utm;
    }

    $replacements = array(
        '{title}' => get_the_title( $post_id ),
        '{url}'   => $url,
    );

    return strtr( $template, $replacements );
}
