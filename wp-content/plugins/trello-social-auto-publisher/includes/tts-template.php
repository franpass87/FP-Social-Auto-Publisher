<?php
/**
 * Template utilities for Trello Social Auto Publisher.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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

    $options            = get_option( 'tts_settings', array() );
    $labels_as_hashtags = ! empty( $options['labels_as_hashtags'] );

    $url = get_permalink( $post_id );

    if ( false !== strpos( $template, '{url}' ) ) {
        $utm_params = array();
        if ( ! empty( $options['utm'][ $channel ] ) && is_array( $options['utm'][ $channel ] ) ) {
            foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $utm_key ) {
                if ( ! empty( $options['utm'][ $channel ][ $utm_key ] ) ) {
                    $utm_params[ $utm_key ] = $options['utm'][ $channel ][ $utm_key ];
                }
            }
        }
        if ( ! empty( $utm_params ) ) {
            $url = add_query_arg( $utm_params, $url );
        }
    }

    if ( ! empty( $options['url_shortener'] ) && 'none' !== $options['url_shortener'] ) {
        if ( 'wp' === $options['url_shortener'] ) {
            $short = TTS_Shortener::shorten_wp( $post_id );
            if ( $short ) {
                $url = $short;
            }
        } elseif ( 'bitly' === $options['url_shortener'] && ! empty( $options['bitly_token'] ) ) {
            $url = TTS_Shortener::shorten_bitly( $url, $options['bitly_token'] );
        }
    }
    $due         = get_post_meta( $post_id, '_trello_due', true );
    $labels_meta = get_post_meta( $post_id, '_trello_labels', true );
    $label_names = array();
    if ( is_array( $labels_meta ) ) {
        foreach ( $labels_meta as $label ) {
            if ( is_array( $label ) && ! empty( $label['name'] ) ) {
                $label_names[] = $label['name'];
            }
        }
    }
    $labels = implode( ', ', $label_names );

    $client_id   = get_post_meta( $post_id, '_tts_client_id', true );
    $client_name = $client_id ? get_the_title( $client_id ) : '';

    $replacements = array(
        '{title}'       => get_the_title( $post_id ),
        '{content}'     => $post->post_content,
        '{excerpt}'     => get_the_excerpt( $post_id ),
        '{url}'         => $url,
        '{due}'         => $due,
        '{labels}'      => $labels_as_hashtags ? '' : $labels,
        '{client_name}' => $client_name,
        '{publish_at}'  => get_post_meta( $post_id, '_tts_publish_at', true ),
        '{trello_id}'   => get_post_meta( $post_id, '_trello_card_id', true ),
        '{channel}'     => $channel,
    );

    $message = strtr( $template, $replacements );

    if ( $labels_as_hashtags && ! empty( $label_names ) ) {
        $hashtags = array();
        foreach ( $label_names as $label_name ) {
            $sanitized = sanitize_title( $label_name );
            $sanitized = str_replace( '-', '', $sanitized );
            if ( '' !== $sanitized ) {
                $hashtags[] = '#' . $sanitized;
            }
        }
        if ( ! empty( $hashtags ) ) {
            $message = trim( $message ) . ' ' . implode( ' ', $hashtags );
        }
    }

    if ( $client_id ) {
        $default_hashtags = get_post_meta( $client_id, '_tts_default_hashtags_' . $channel, true );
        if ( ! empty( $default_hashtags ) ) {
            $message = trim( $message ) . ' ' . trim( $default_hashtags );
        }
    }

    return $message;
}
