<?php
/**
 * Notification utilities for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Send a notification when a social post is processed.
 *
 * Uses a Slack webhook if configured via the `tts_slack_webhook` option,
 * otherwise falls back to the site's admin email via `wp_mail`.
 *
 * @param int    $post_id Post ID.
 * @param string $status  Publication status.
 * @param string $channel Social channel.
 */
function tts_notify_publication( $post_id, $status, $channel ) {
    $title   = get_the_title( $post_id );
    $message = sprintf( 'Post "%s" on %s: %s', $title, $channel, $status );

    $webhook = get_option( 'tts_slack_webhook', '' );
    if ( ! empty( $webhook ) ) {
        wp_remote_post(
            $webhook,
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array( 'text' => $message ) ),
            )
        );
        return;
    }

    $subject = sprintf( '[Social Publish] %s - %s', $channel, $status );
    wp_mail( get_option( 'admin_email' ), $subject, $message );
}
