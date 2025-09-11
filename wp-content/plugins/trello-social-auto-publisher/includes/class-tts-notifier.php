<?php
/**
 * Notification handler for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provides methods to send notifications via Slack and email.
 */
class TTS_Notifier {

    /**
     * Send a message to Slack using the configured webhook.
     *
     * @param string $message Message to send.
     */
    public function notify_slack( $message ) {
        $options = get_option( 'tts_settings', array() );
        $webhook = isset( $options['slack_webhook'] ) ? esc_url_raw( $options['slack_webhook'] ) : '';
        if ( empty( $webhook ) ) {
            return;
        }

        wp_remote_post(
            $webhook,
            array(
                'headers' => array( 'Content-Type' => 'application/json' ),
                'body'    => wp_json_encode( array( 'text' => $message ) ),
                'timeout' => 20,
            )
        );
    }

    /**
     * Send an email notification to configured recipients.
     *
     * @param string $subject Email subject.
     * @param string $body    Email body.
     */
    public function notify_email( $subject, $body ) {
        $options = get_option( 'tts_settings', array() );
        $emails  = isset( $options['notification_emails'] ) ? $options['notification_emails'] : '';
        if ( empty( $emails ) ) {
            return;
        }

        $recipients = array_map( 'sanitize_email', array_map( 'trim', explode( ',', $emails ) ) );
        $recipients = array_filter( $recipients );
        if ( empty( $recipients ) ) {
            return;
        }

        wp_mail( $recipients, $subject, $body );
    }
}
