<?php
/**
 * Logging utilities for Trello Social Auto Publisher.
 *
 * @package FPPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Create the tts logs table.
 */
function tts_create_logs_table() {
    global $wpdb;

    $table_name      = $wpdb->prefix . 'tts_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned NOT NULL,
        channel varchar(50) NOT NULL,
        status varchar(20) NOT NULL,
        message text NOT NULL,
        response longtext NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY channel_status (channel, status),
        KEY created_at (created_at),
        KEY status_created (status, created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Log an event to the custom table.
 *
 * @param int    $post_id  Post ID.
 * @param string $channel  Channel identifier.
 * @param string $status   Status of the event.
 * @param string $message  Message to log.
 * @param mixed  $response Response data.
 */
function tts_log_event( $post_id, $channel, $status, $message, $response ) {
    global $wpdb;

    $table = $wpdb->prefix . 'tts_logs';
    $wpdb->insert(
        $table,
        array(
            'post_id'   => $post_id,
            'channel'   => $channel,
            'status'    => $status,
            'message'   => $message,
            'response'  => is_scalar( $response ) ? $response : wp_json_encode( $response ),
            'created_at'=> current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s' )
    );
}

/**
 * Purge log records older than the retention period.
 */
function tts_purge_old_logs() {
    global $wpdb;

    $options = get_option( 'tts_settings', array() );
    $days    = isset( $options['log_retention_days'] ) ? (int) $options['log_retention_days'] : 30;

    $table  = $wpdb->prefix . 'tts_logs';
    $cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );

    $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
}

add_action( 'tts_purge_old_logs', 'tts_purge_old_logs' );

add_action(
    'init',
    function () {
        if ( ! wp_next_scheduled( 'tts_purge_old_logs' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_purge_old_logs' );
        }
    }
);
