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
    $due    = get_post_meta( $post_id, '_trello_due', true );
    $labels = get_post_meta( $post_id, '_trello_labels', true );
    if ( is_array( $labels ) ) {
        $label_names = array();
        foreach ( $labels as $label ) {
            if ( is_array( $label ) && ! empty( $label['name'] ) ) {
                $label_names[] = $label['name'];
            }
        }
        $labels = implode( ', ', $label_names );
    }

    $client_id   = get_post_meta( $post_id, '_tts_client_id', true );
    $client_name = $client_id ? get_the_title( $client_id ) : '';

    $replacements = array(
        '{title}'       => get_the_title( $post_id ),
        '{content}'     => $post->post_content,
        '{excerpt}'     => get_the_excerpt( $post_id ),
        '{url}'         => $url,
        '{due}'         => $due,
        '{labels}'      => $labels,
        '{client_name}' => $client_name,
        '{publish_at}'  => get_post_meta( $post_id, '_tts_publish_at', true ),
        '{trello_id}'   => get_post_meta( $post_id, '_trello_card_id', true ),
        '{channel}'     => $channel,
    );

    return strtr( $template, $replacements );
}
