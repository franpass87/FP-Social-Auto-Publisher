<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Callback to publish scheduled social posts.
 *
 * In the future will invoke publish_facebook, publish_instagram, etc. methods.
 *
 * @param int $post_id ID of the post to publish.
 */
function tts_publish_social_post( $post_id ) {
    if ( function_exists( 'publish_facebook' ) ) {
        publish_facebook( $post_id );
    }

    if ( function_exists( 'publish_instagram' ) ) {
        publish_instagram( $post_id );
    }
}
