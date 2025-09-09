<?php
/**
 * Plugin Name: Trello Social Auto Publisher
 * Plugin URI:  https://example.com/
 * Description: Automatically publishes posts to Trello.
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

// Load support files from the includes directory.
foreach ( glob( TSAP_PLUGIN_DIR . 'includes/*.php' ) as $file ) {
    require_once $file;
}
