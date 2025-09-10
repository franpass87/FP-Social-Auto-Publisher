<?php
/**
 * Plugin Name: Trello Social Auto Publisher
 * Plugin URI:  https://example.com/
 * Description: Publish social posts triggered by Trello cards.
 * Version:     1.0.0
 * Author:      FP-Social-Auto-Publisher
 * Author URI:  https://example.com/
 * Text Domain: trello-social-auto-publisher
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin directory.
if ( ! defined( 'TSAP_PLUGIN_DIR' ) ) {
    define( 'TSAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Ensure Action Scheduler is available.
if ( ! function_exists( 'as_schedule_single_action' ) ) {
    add_action(
        'admin_notices',
        function() {
            echo '<div class="error"><p>' . esc_html__( 'Action Scheduler plugin is required for Trello Social Auto Publisher.', 'trello-social-auto-publisher' ) . '</p></div>';
        }
    );
    return;
}

// Load support files from the includes directory.
foreach ( glob( TSAP_PLUGIN_DIR . 'includes/*.php' ) as $file ) {
    if ( 'class-tts-rest.php' === basename( $file ) ) {
        continue;
    }
    require_once $file;
}

// Load REST API endpoints after other includes.
require_once TSAP_PLUGIN_DIR . 'includes/class-tts-rest.php';
// Register activation hook.
register_activation_hook( __FILE__, 'tts_create_logs_table' );

// Load admin files when in the dashboard.
if ( is_admin() ) {
    require_once TSAP_PLUGIN_DIR . 'admin/class-tts-admin.php';
    require_once TSAP_PLUGIN_DIR . 'admin/class-tts-log-page.php';
}

// Add a weekly cron schedule.
add_filter(
    'cron_schedules',
    function( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'trello-social-auto-publisher' ),
            );
        }
        return $schedules;
    }
);

// Schedule weekly token refreshes.
add_action(
    'init',
    function () {
        if ( ! wp_next_scheduled( 'tts_refresh_tokens' ) ) {
            wp_schedule_event( time(), 'weekly', 'tts_refresh_tokens' );
        }
    }
);

// Attach the refresh action to the token refresh handler.
add_action( 'tts_refresh_tokens', array( 'TTS_Token_Refresh', 'refresh_tokens' ) );

// Schedule daily metrics fetching.
add_action(
    'init',
    function () {
        if ( ! wp_next_scheduled( 'tts_fetch_metrics' ) ) {
            wp_schedule_event( time(), 'daily', 'tts_fetch_metrics' );
        }
    }
);

// Hook the analytics fetcher.
add_action( 'tts_fetch_metrics', array( 'TTS_Analytics', 'fetch_all' ) );
