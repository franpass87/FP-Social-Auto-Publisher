<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Callback to publish scheduled social posts.
 *
 * In futuro invocherà i metodi publish_facebook, publish_instagram, ecc.
 *
 * @param int $post_id ID del post da pubblicare.
 */
function tts_publish_social_post( $post_id ) {
    if ( function_exists( 'publish_facebook' ) ) {
        publish_facebook( $post_id );
    }

    if ( function_exists( 'publish_instagram' ) ) {
        publish_instagram( $post_id );
    }
}
