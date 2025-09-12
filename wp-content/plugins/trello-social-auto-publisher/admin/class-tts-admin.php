<?php
/**
 * Admin functionality for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles admin pages and filters.
 */
class TTS_Admin {

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_client_filter' ) );
        add_action( 'restrict_manage_posts', array( $this, 'add_approved_filter' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_posts_by_client' ) );
        add_action( 'pre_get_posts', array( $this, 'filter_posts_by_approved' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_assets' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_scheduled_posts_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
        add_action( 'wp_ajax_tts_get_lists', array( $this, 'ajax_get_lists' ) );
        add_action( 'wp_ajax_tts_refresh_posts', array( $this, 'ajax_refresh_posts' ) );
        add_action( 'wp_ajax_tts_delete_post', array( $this, 'ajax_delete_post' ) );
        add_action( 'wp_ajax_tts_bulk_action', array( $this, 'ajax_bulk_action' ) );
        add_action( 'wp_ajax_tts_test_connection', array( $this, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_tts_check_rate_limits', array( $this, 'ajax_check_rate_limits' ) );
        add_action( 'wp_ajax_tts_export_data', array( $this, 'ajax_export_data' ) );
        add_action( 'wp_ajax_tts_import_data', array( $this, 'ajax_import_data' ) );
        add_action( 'wp_ajax_tts_system_maintenance', array( $this, 'ajax_system_maintenance' ) );
        add_action( 'wp_ajax_tts_generate_report', array( $this, 'ajax_generate_report' ) );
        add_action( 'wp_ajax_tts_quick_connection_check', array( $this, 'ajax_quick_connection_check' ) );
        add_action( 'wp_ajax_tts_refresh_health', array( $this, 'ajax_refresh_health' ) );
        add_action( 'wp_ajax_tts_show_export_modal', array( $this, 'ajax_show_export_modal' ) );
        add_action( 'wp_ajax_tts_show_import_modal', array( $this, 'ajax_show_import_modal' ) );
        add_filter( 'manage_tts_social_post_posts_columns', array( $this, 'add_approved_column' ) );
        add_action( 'manage_tts_social_post_posts_custom_column', array( $this, 'render_approved_column' ), 10, 2 );
        add_filter( 'bulk_actions-edit-tts_social_post', array( $this, 'register_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-edit-tts_social_post', array( $this, 'handle_bulk_actions' ), 10, 3 );
    }

    /**
     * Register plugin menu pages.
     */
    public function register_menu() {
        // Main menu page
        add_menu_page(
            __( 'Social Auto Publisher', 'trello-social-auto-publisher' ),
            __( 'Social Auto Publisher', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-main',
            array( $this, 'render_dashboard_page' ),
            'dashicons-share-alt',
            25
        );

        // Dashboard as first submenu (same as main page)
        add_submenu_page(
            'tts-main',
            __( 'Dashboard', 'trello-social-auto-publisher' ),
            __( 'Dashboard', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-main',
            array( $this, 'render_dashboard_page' )
        );

        // Clients submenu
        add_submenu_page(
            'tts-main',
            __( 'Clienti', 'trello-social-auto-publisher' ),
            __( 'Clienti', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-clienti',
            array( $this, 'render_clients_page' )
        );

        // Client Wizard submenu
        add_submenu_page(
            'tts-main',
            __( 'Client Wizard', 'trello-social-auto-publisher' ),
            __( 'Client Wizard', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-client-wizard',
            array( $this, 'tts_render_client_wizard' )
        );

        // Social Posts submenu
        add_submenu_page(
            'tts-main',
            __( 'Social Post', 'trello-social-auto-publisher' ),
            __( 'Social Post', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-social-posts',
            array( $this, 'render_social_posts_page' )
        );

        // Settings submenu
        add_submenu_page(
            'tts-main',
            __( 'Settings', 'trello-social-auto-publisher' ),
            __( 'Settings', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-settings',
            array( $this, 'render_settings_page' )
        );

        // Social Connections submenu
        add_submenu_page(
            'tts-main',
            __( 'Social Connections', 'trello-social-auto-publisher' ),
            __( 'Social Connections', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-social-connections',
            array( $this, 'render_social_connections_page' )
        );

        // Help submenu
        add_submenu_page(
            'tts-main',
            __( 'Help & Setup', 'trello-social-auto-publisher' ),
            __( 'Help & Setup', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-help',
            array( $this, 'render_help_page' )
        );
    }

    /**
     * Enqueue assets for the dashboard page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_dashboard_assets( $hook ) {
        // Check if we're on any TTS admin page
        if ( strpos( $hook, 'tts-' ) === false && $hook !== 'toplevel_page_tts-main' ) {
            return;
        }

        // Core assets - loaded on all TTS pages
        $this->enqueue_core_assets();

        // Page-specific assets
        switch ( $hook ) {
            case 'toplevel_page_tts-main':
                $this->enqueue_dashboard_specific_assets();
                break;
            case 'social-auto-publisher_page_tts-social-connections':
                $this->enqueue_social_connections_assets();
                break;
            case 'social-auto-publisher_page_tts-client-wizard':
                $this->enqueue_wizard_assets();
                break;
            case 'social-auto-publisher_page_tts-analytics':
                $this->enqueue_analytics_assets();
                break;
        }
    }

    /**
     * Enqueue core assets needed on all TTS pages.
     */
    private function enqueue_core_assets() {
        // Essential styles
        wp_enqueue_style(
            'tts-core',
            plugin_dir_url( __FILE__ ) . 'css/tts-core.css',
            array(),
            '1.3'
        );

        // Essential JavaScript
        wp_enqueue_script(
            'tts-core',
            plugin_dir_url( __FILE__ ) . 'js/tts-core.js',
            array( 'jquery' ),
            '1.3',
            true
        );

        // Localize core script
        wp_localize_script( 'tts-core', 'tts_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'tts_ajax_nonce' ),
        ));
    }

    /**
     * Enqueue dashboard-specific assets.
     */
    private function enqueue_dashboard_specific_assets() {
        wp_enqueue_style(
            'tts-dashboard',
            plugin_dir_url( __FILE__ ) . 'css/tts-dashboard.css',
            array( 'tts-core' ),
            '1.2'
        );

        wp_enqueue_script(
            'tts-dashboard',
            plugin_dir_url( __FILE__ ) . 'js/tts-dashboard.js',
            array( 'tts-core', 'wp-element', 'wp-components', 'wp-api-fetch' ),
            '1.2',
            true
        );
    }

    /**
     * Enqueue social connections specific assets.
     */
    private function enqueue_social_connections_assets() {
        wp_enqueue_style(
            'tts-social-connections',
            plugin_dir_url( __FILE__ ) . 'css/tts-social-connections.css',
            array( 'tts-core' ),
            '1.0'
        );

        wp_enqueue_script(
            'tts-social-connections',
            plugin_dir_url( __FILE__ ) . 'js/tts-social-connections.js',
            array( 'tts-core' ),
            '1.0',
            true
        );
    }

    /**
     * Enqueue analytics specific assets.
     */
    private function enqueue_analytics_assets() {
        wp_enqueue_style(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'css/tts-analytics.css',
            array( 'tts-core' ),
            '1.0'
        );

        wp_enqueue_script(
            'tts-analytics',
            plugin_dir_url( __FILE__ ) . 'js/tts-analytics.js',
            array( 'tts-core', 'chart-js' ),
            '1.0',
            true
        );

        // Enqueue Chart.js for analytics
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );

        // Localize script with enhanced data (global for all TTS pages)
        wp_localize_script(
            'tts-admin-utils',
            'ttsDashboard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'tts_dashboard' ),
                'restUrl' => rest_url( 'wp/v2/' ),
                'restNonce' => wp_create_nonce( 'wp_rest' ),
                'currentPage' => isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '',
                'strings' => array(
                    'confirmDelete' => __( 'Are you sure you want to delete this item?', 'trello-social-auto-publisher' ),
                    'bulkDelete' => __( 'Are you sure you want to delete the selected items?', 'trello-social-auto-publisher' ),
                    'loading' => __( 'Loading...', 'trello-social-auto-publisher' ),
                    'error' => __( 'An error occurred', 'trello-social-auto-publisher' ),
                    'success' => __( 'Operation completed successfully', 'trello-social-auto-publisher' ),
                )
            )
        );
    }

    /**
     * Enqueue optimized wizard assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_wizard_assets( $hook ) {
        if ( 'social-auto-publisher_page_tts-client-wizard' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'tts-wizard',
            plugin_dir_url( __FILE__ ) . 'js/tts-wizard.js',
            array( 'tts-core' ),
            '1.1',
            true
        );

        wp_localize_script(
            'tts-wizard',
            'ttsWizard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_wizard' ),
                'strings' => array(
                    'validating' => __( 'Validating...', 'trello-social-auto-publisher' ),
                    'connecting' => __( 'Connecting...', 'trello-social-auto-publisher' ),
                    'success' => __( 'Success!', 'trello-social-auto-publisher' ),
                    'error' => __( 'Error occurred', 'trello-social-auto-publisher' ),
                )
            )
        );
    }

    /**
     * Enqueue optimized media assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_media_assets( $hook ) {
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        $screen = get_current_screen();
        if ( ! $screen || 'tts_social_post' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_script(
            'tts-media',
            plugin_dir_url( __FILE__ ) . 'js/tts-media.js',
            array( 'tts-core', 'media-editor', 'jquery-ui-sortable' ),
            '1.1',
            true
        );
    }

    /**
     * Register dashboard widget listing scheduled social posts.
     */
    public function register_scheduled_posts_widget() {
        wp_add_dashboard_widget(
            'tts_scheduled_posts',
            __( 'Social Post programmati', 'trello-social-auto-publisher' ),
            array( $this, 'render_scheduled_posts_widget' )
        );
    }

    /**
     * Render the scheduled social posts widget.
     */
    public function render_scheduled_posts_widget() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'posts_per_page' => 5,
                'post_status'    => 'any',
                'meta_key'       => '_tts_publish_at',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => '_tts_publish_at',
                        'value'   => current_time( 'mysql' ),
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
            )
        );

        if ( empty( $posts ) ) {
            echo '<p>' . esc_html__( 'Nessun post programmato.', 'trello-social-auto-publisher' ) . '</p>';
            return;
        }

        echo '<ul>';
        foreach ( $posts as $post ) {
            $channel    = get_post_meta( $post->ID, '_tts_social_channel', true );
            $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );
            $edit_link  = get_edit_post_link( $post->ID );
            echo '<li><a href="' . esc_url( $edit_link ) . '">' . esc_html( $post->post_title ) . '</a> - ' . esc_html( is_array( $channel ) ? implode( ', ', $channel ) : $channel ) . ' - ' . esc_html( date_i18n( 'Y-m-d H:i', strtotime( $publish_at ) ) ) . '</li>';
        }
        echo '</ul>';
    }

    /**
     * Enqueue assets for the dashboard widget.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_widget_assets( $hook ) {
        if ( 'index.php' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'tts-dashboard-widget',
            plugin_dir_url( __FILE__ ) . 'js/tts-dashboard-widget.js',
            array( 'jquery' ),
            '1.0',
            true
        );

        wp_localize_script(
            'tts-dashboard-widget',
            'ttsDashboardWidget',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_dashboard_widget' ),
            )
        );
    }

    /**
     * AJAX callback: fetch lists for a Trello board.
     */
    public function ajax_get_lists() {
        check_ajax_referer( 'tts_wizard', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to perform this action.', 'trello-social-auto-publisher' ) );
        }

        $board = isset( $_POST['board'] ) ? sanitize_text_field( $_POST['board'] ) : '';
        $key   = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

        // Enhanced validation with specific error messages
        if ( empty( $board ) ) {
            wp_send_json_error( __( 'Board ID is required.', 'trello-social-auto-publisher' ) );
        }
        if ( empty( $key ) ) {
            wp_send_json_error( __( 'Trello API key is required.', 'trello-social-auto-publisher' ) );
        }
        if ( empty( $token ) ) {
            wp_send_json_error( __( 'Trello token is required.', 'trello-social-auto-publisher' ) );
        }

        // Validate board ID format (should be 24 character hex string)
        if ( ! preg_match( '/^[a-f0-9]{24}$/i', $board ) ) {
            wp_send_json_error( __( 'Invalid board ID format.', 'trello-social-auto-publisher' ) );
        }

        $response = wp_remote_get(
            'https://api.trello.com/1/boards/' . rawurlencode( $board ) . '/lists?key=' . rawurlencode( $key ) . '&token=' . rawurlencode( $token ),
            array( 'timeout' => 20 )
        );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'TTS AJAX Error: ' . $response->get_error_message() );
            wp_send_json_error( 
                sprintf( 
                    __( 'Failed to connect to Trello API: %s', 'trello-social-auto-publisher' ), 
                    $response->get_error_message() 
                ) 
            );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            error_log( "TTS AJAX Error: HTTP $http_code from Trello API" );
            wp_send_json_error( 
                sprintf( 
                    __( 'Trello API returned error code %d. Please check your credentials.', 'trello-social-auto-publisher' ), 
                    $http_code 
                ) 
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'TTS AJAX Error: Invalid JSON response from Trello API' );
            wp_send_json_error( __( 'Invalid response from Trello API.', 'trello-social-auto-publisher' ) );
        }

        wp_send_json_success( $data );
    }

    /**
     * AJAX callback: refresh posts data for dashboard.
     */
    public function ajax_refresh_posts() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        // Check user capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'You do not have permission to view posts.', 'trello-social-auto-publisher' ) );
        }

        try {
            $posts = get_posts(array(
                'post_type' => 'tts_social_post',
                'posts_per_page' => 10,
                'post_status' => 'any',
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => '_tts_publish_at',
                        'compare' => 'EXISTS'
                    ),
                    array(
                        'key' => '_tts_publish_at',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ));

            if ( empty( $posts ) ) {
                wp_send_json_success( array(
                    'posts' => array(),
                    'message' => __( 'No posts found.', 'trello-social-auto-publisher' ),
                    'timestamp' => current_time( 'timestamp' )
                ) );
            }

            $formatted_posts = array();
            foreach ( $posts as $post ) {
                $channel = get_post_meta( $post->ID, '_tts_social_channel', true );
                $status = get_post_meta( $post->ID, '_published_status', true );
                $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );
                
                $formatted_posts[] = array(
                    'ID' => intval( $post->ID ),
                    'title' => wp_trim_words( $post->post_title, 10 ),
                    'channel' => is_array( $channel ) ? $channel : array( $channel ),
                    'status' => $status ?: 'scheduled',
                    'publish_at' => $publish_at ?: $post->post_date,
                    'edit_link' => current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID ) : ''
                );
            }

            wp_send_json_success( array(
                'posts' => $formatted_posts,
                'message' => sprintf( 
                    _n( 
                        '%d post refreshed successfully', 
                        '%d posts refreshed successfully', 
                        count( $formatted_posts ), 
                        'trello-social-auto-publisher' 
                    ), 
                    count( $formatted_posts ) 
                ),
                'timestamp' => current_time( 'timestamp' )
            ) );

        } catch ( Exception $e ) {
            error_log( 'TTS Refresh Posts Error: ' . $e->getMessage() );
            wp_send_json_error( __( 'An error occurred while refreshing posts. Please try again.', 'trello-social-auto-publisher' ) );
        }
    }

    /**
     * AJAX callback: delete a social post.
     */
    public function ajax_delete_post() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        // Rate limiting check
        if (!$this->check_rate_limit('delete_post', 20, 60)) {
            wp_send_json_error(__('Too many delete requests. Please wait a moment and try again.', 'trello-social-auto-publisher'));
        }

        if (!current_user_can('delete_posts')) {
            wp_send_json_error(__('You do not have permission to delete posts.', 'trello-social-auto-publisher'));
        }

        $post_id = isset($_POST['postId']) ? intval($_POST['postId']) : 0;
        
        if (!$post_id || $post_id <= 0) {
            wp_send_json_error(__('Invalid post ID.', 'trello-social-auto-publisher'));
        }

        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'tts_social_post') {
            wp_send_json_error(__('Post not found.', 'trello-social-auto-publisher'));
        }

        // Check specific delete permission for this post
        if (!current_user_can('delete_post', $post_id)) {
            wp_send_json_error(__('You do not have permission to delete this specific post.', 'trello-social-auto-publisher'));
        }

        $result = wp_delete_post($post_id, true);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Post deleted successfully.', 'trello-social-auto-publisher'),
                'refresh' => true
            ));
        } else {
            wp_send_json_error(__('Failed to delete post.', 'trello-social-auto-publisher'));
        }
    }

    /**
     * AJAX callback: handle bulk actions on social posts.
     */
    public function ajax_bulk_action() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        // Rate limiting check
        if (!$this->check_rate_limit('bulk_action', 10, 60)) {
            wp_send_json_error(__('Too many requests. Please wait a moment and try again.', 'trello-social-auto-publisher'));
        }

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'trello-social-auto-publisher'));
        }

        $action = isset($_POST['bulkAction']) ? sanitize_text_field($_POST['bulkAction']) : '';
        $post_ids = isset($_POST['postIds']) ? array_map('intval', $_POST['postIds']) : array();

        // Input validation
        if (!$action || empty($post_ids)) {
            wp_send_json_error(__('Invalid action or no posts selected.', 'trello-social-auto-publisher'));
        }

        // Validate action is allowed
        $allowed_actions = array('delete', 'approve', 'revoke');
        if (!in_array($action, $allowed_actions, true)) {
            wp_send_json_error(__('Invalid action specified.', 'trello-social-auto-publisher'));
        }

        // Limit number of posts that can be processed at once
        if (count($post_ids) > 100) {
            wp_send_json_error(__('Too many posts selected. Please select 100 or fewer posts.', 'trello-social-auto-publisher'));
        }

        $processed = 0;
        $errors = array();

        foreach ($post_ids as $post_id) {
            // Additional validation for each post ID
            if ($post_id <= 0) {
                $errors[] = __('Invalid post ID provided.', 'trello-social-auto-publisher');
                continue;
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== 'tts_social_post') {
                $errors[] = sprintf(__('Post ID %d not found.', 'trello-social-auto-publisher'), $post_id);
                continue;
            }

            switch ($action) {
                case 'delete':
                    if (current_user_can('delete_post', $post_id)) {
                        if (wp_delete_post($post_id, true)) {
                            $processed++;
                        } else {
                            $errors[] = sprintf(__('Failed to delete post ID %d.', 'trello-social-auto-publisher'), $post_id);
                        }
                    } else {
                        $errors[] = sprintf(__('You do not have permission to delete post ID %d.', 'trello-social-auto-publisher'), $post_id);
                    }
                    break;

                case 'approve':
                    if (current_user_can('edit_post', $post_id)) {
                        update_post_meta($post_id, '_tts_approved', true);
                        do_action('save_post_tts_social_post', $post_id, $post, true);
                        do_action('tts_post_approved', $post_id);
                        $processed++;
                    } else {
                        $errors[] = sprintf(__('You do not have permission to approve post ID %d.', 'trello-social-auto-publisher'), $post_id);
                    }
                    break;

                case 'revoke':
                    if (current_user_can('edit_post', $post_id)) {
                        delete_post_meta($post_id, '_tts_approved');
                        do_action('save_post_tts_social_post', $post_id, $post, true);
                        $processed++;
                    } else {
                        $errors[] = sprintf(__('You do not have permission to revoke approval for post ID %d.', 'trello-social-auto-publisher'), $post_id);
                    }
                    break;
            }
        }

        if ($processed > 0) {
            $message = sprintf(
                _n(
                    '%d post processed successfully.',
                    '%d posts processed successfully.',
                    $processed,
                    'trello-social-auto-publisher'
                ),
                $processed
            );

            if (!empty($errors)) {
                $message .= ' ' . sprintf(__('However, %d errors occurred.', 'trello-social-auto-publisher'), count($errors));
            }

            wp_send_json_success(array(
                'message' => $message,
                'processed' => $processed,
                'errors' => $errors,
                'refresh' => true
            ));
        } else {
            wp_send_json_error(__('No posts were processed.', 'trello-social-auto-publisher') . ' ' . implode(' ', $errors));
        }
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Social Auto Publisher Dashboard', 'trello-social-auto-publisher' ) . '</h1>';
        
        // Add notification area
        echo '<div id="tts-notification-area" style="margin: 15px 0;"></div>';
        
        // Health status banner (if there are issues)
        $this->render_health_status_banner();
        
        // Quick stats cards
        $this->render_dashboard_stats();
        
        // Enhanced monitoring section
        $this->render_monitoring_dashboard();
        
        // Recent activity and actions
        echo '<div class="tts-dashboard-sections">';
        echo '<div class="tts-dashboard-left">';
        $this->render_recent_posts_section();
        echo '</div>';
        
        echo '<div class="tts-dashboard-right">';
        $this->render_quick_actions_section();
        $this->render_system_status_widget();
        echo '</div>';
        echo '</div>';
        
        // Advanced tools section
        $this->render_advanced_tools_section();
        
        // React component container for advanced features
        echo '<div id="tts-dashboard-root"></div>';
        echo '</div>';
    }

    /**
     * Render health status banner.
     */
    private function render_health_status_banner() {
        $health_status = TTS_Monitoring::get_current_health_status();
        
        if ( $health_status['status'] === 'critical' || $health_status['status'] === 'warning' ) {
            $banner_class = $health_status['status'] === 'critical' ? 'error' : 'warning';
            echo '<div class="notice notice-' . $banner_class . ' is-dismissible tts-health-banner">';
            echo '<div style="display: flex; align-items: center; gap: 15px;">';
            echo '<span style="font-size: 24px;">' . ( $health_status['status'] === 'critical' ? '🚨' : '⚠️' ) . '</span>';
            echo '<div>';
            echo '<h3 style="margin: 0;">System Health Alert</h3>';
            echo '<p style="margin: 5px 0 0 0;">' . esc_html( $health_status['message'] ) . '</p>';
            if ( ! empty( $health_status['alerts'] ) ) {
                echo '<p style="margin: 5px 0 0 0; font-size: 12px;">Issues: ';
                $issue_types = array_unique( array_column( $health_status['alerts'], 'type' ) );
                echo esc_html( implode( ', ', $issue_types ) );
                echo '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Render monitoring dashboard.
     */
    private function render_monitoring_dashboard() {
        echo '<div class="tts-monitoring-section">';
        echo '<h2>' . esc_html__( 'System Monitoring', 'trello-social-auto-publisher' ) . '</h2>';
        
        echo '<div class="tts-monitoring-grid">';
        
        // Real-time health score
        $this->render_health_score_widget();
        
        // Performance metrics
        $this->render_performance_metrics_widget();
        
        // API status
        $this->render_api_status_widget();
        
        // Recent activity
        $this->render_activity_timeline_widget();
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render health score widget.
     */
    private function render_health_score_widget() {
        $health_status = TTS_Monitoring::get_current_health_status();
        
        echo '<div class="tts-monitoring-card tts-health-score-card">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'System Health', 'trello-social-auto-publisher' ) . '</h3>';
        echo '<button class="tts-btn small" data-ajax-action="tts_refresh_health" data-loading-text="' . esc_attr__( 'Checking...', 'trello-social-auto-publisher' ) . '">';
        echo esc_html__( 'Refresh', 'trello-social-auto-publisher' );
        echo '</button>';
        echo '</div>';
        
        echo '<div class="tts-health-score-display">';
        $score = $health_status['score'];
        $score_class = $score >= 90 ? 'excellent' : ( $score >= 70 ? 'good' : 'needs-attention' );
        
        echo '<div class="tts-score-circle ' . $score_class . '" style="--score-percent: ' . $score . '%;">';
        echo '<div class="tts-score-text">' . $score . '</div>';
        echo '</div>';
        
        echo '<div class="tts-health-status">';
        echo '<h4>' . esc_html( ucfirst( $health_status['status'] ) ) . '</h4>';
        echo '<p>' . esc_html( $health_status['message'] ) . '</p>';
        if ( $health_status['last_check'] ) {
            echo '<p class="tts-last-check">Last check: ' . esc_html( human_time_diff( strtotime( $health_status['last_check'] ) ) ) . ' ago</p>';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render performance metrics widget.
     */
    private function render_performance_metrics_widget() {
        $performance = TTS_Performance::get_performance_metrics();
        
        echo '<div class="tts-monitoring-card">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'Performance Metrics', 'trello-social-auto-publisher' ) . '</h3>';
        echo '</div>';
        
        echo '<div class="tts-metrics-display">';
        
        // Database performance
        if ( isset( $performance['database'] ) ) {
            $db_status = $performance['database']['status'];
            $status_icon = $db_status === 'excellent' ? '🟢' : ( $db_status === 'good' ? '🟡' : '🔴' );
            
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">' . $status_icon . ' Database</span>';
            echo '<span class="tts-metric-value">' . $performance['database']['response_ms'] . 'ms</span>';
            echo '</div>';
        }
        
        // Memory usage
        if ( isset( $performance['memory'] ) ) {
            $memory_status = $performance['memory']['status'];
            $status_icon = $memory_status === 'good' ? '🟢' : '🟡';
            
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">' . $status_icon . ' Memory</span>';
            echo '<span class="tts-metric-value">' . $performance['memory']['usage_percent'] . '%</span>';
            echo '</div>';
        }
        
        // Cache performance
        if ( isset( $performance['cache'] ) ) {
            $cache_status = $performance['cache']['status'];
            $status_icon = $cache_status === 'excellent' ? '🟢' : ( $cache_status === 'good' ? '🟡' : '🔴' );
            
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">' . $status_icon . ' Cache</span>';
            echo '<span class="tts-metric-value">' . $performance['cache']['hit_ratio'] . '%</span>';
            echo '</div>';
        }
        
        // Performance score
        if ( isset( $performance['score'] ) ) {
            echo '<div class="tts-metric-item">';
            echo '<span class="tts-metric-label">Overall Score</span>';
            echo '<span class="tts-metric-value">' . $performance['score'] . '/100</span>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render API status widget.
     */
    private function render_api_status_widget() {
        $health_data = get_option( 'tts_last_health_check', array() );
        $api_status = isset( $health_data['checks']['api_connections'] ) ? $health_data['checks']['api_connections'] : array();
        
        echo '<div class="tts-monitoring-card">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'API Connections', 'trello-social-auto-publisher' ) . '</h3>';
        echo '</div>';
        
        echo '<div class="tts-api-status-display">';
        
        if ( ! empty( $api_status['platform_status'] ) ) {
            foreach ( $api_status['platform_status'] as $platform => $status ) {
                $platform_icon = array(
                    'facebook' => '📘',
                    'instagram' => '📷',
                    'youtube' => '🎥',
                    'tiktok' => '🎵'
                );
                
                $status_icon = $status['success'] ? '🟢' : '🔴';
                $status_text = $status['success'] ? 'Connected' : 'Failed';
                
                echo '<div class="tts-api-platform-item">';
                echo '<span class="tts-platform-icon">' . ( $platform_icon[$platform] ?? '📱' ) . '</span>';
                echo '<span class="tts-platform-name">' . esc_html( ucfirst( $platform ) ) . '</span>';
                echo '<span class="tts-platform-status">' . $status_icon . ' ' . esc_html( $status_text ) . '</span>';
                echo '</div>';
            }
        } else {
            echo '<p class="tts-no-data">' . esc_html__( 'No API connection data available', 'trello-social-auto-publisher' ) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render activity timeline widget.
     */
    private function render_activity_timeline_widget() {
        global $wpdb;
        
        // Get recent activity logs
        $recent_logs = $wpdb->get_results( $wpdb->prepare( "
            SELECT event_type, status, message, created_at
            FROM {$wpdb->prefix}tts_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
            ORDER BY created_at DESC
            LIMIT 10
        ", 24 ), ARRAY_A );
        
        echo '<div class="tts-monitoring-card tts-activity-timeline">';
        echo '<div class="tts-card-header">';
        echo '<h3>' . esc_html__( 'Recent Activity', 'trello-social-auto-publisher' ) . '</h3>';
        echo '</div>';
        
        echo '<div class="tts-timeline-container">';
        
        if ( ! empty( $recent_logs ) ) {
            foreach ( $recent_logs as $log ) {
                $status_icon = $log['status'] === 'success' ? '✅' : 
                              ( $log['status'] === 'error' ? '❌' : '⚠️' );
                
                echo '<div class="tts-timeline-item">';
                echo '<div class="tts-timeline-icon">' . $status_icon . '</div>';
                echo '<div class="tts-timeline-content">';
                echo '<div class="tts-timeline-event">' . esc_html( $log['event_type'] ) . '</div>';
                echo '<div class="tts-timeline-message">' . esc_html( wp_trim_words( $log['message'], 10 ) ) . '</div>';
                echo '<div class="tts-timeline-time">' . esc_html( human_time_diff( strtotime( $log['created_at'] ) ) ) . ' ago</div>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p class="tts-no-data">' . esc_html__( 'No recent activity', 'trello-social-auto-publisher' ) . '</p>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render advanced tools section.
     */
    private function render_advanced_tools_section() {
        echo '<div class="tts-advanced-tools-section">';
        echo '<h2>' . esc_html__( 'Advanced Tools', 'trello-social-auto-publisher' ) . '</h2>';
        
        echo '<div class="tts-tools-grid">';
        
        // Export/Import Tools
        echo '<div class="tts-tool-card">';
        echo '<h3>📦 ' . esc_html__( 'Export & Import', 'trello-social-auto-publisher' ) . '</h3>';
        echo '<p>' . esc_html__( 'Backup your settings and data or migrate from another installation.', 'trello-social-auto-publisher' ) . '</p>';
        echo '<div class="tts-tool-actions">';
        echo '<button class="tts-btn primary" data-ajax-action="tts_show_export_modal">';
        echo esc_html__( 'Export Data', 'trello-social-auto-publisher' );
        echo '</button>';
        echo '<button class="tts-btn secondary" data-ajax-action="tts_show_import_modal">';
        echo esc_html__( 'Import Data', 'trello-social-auto-publisher' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        // System Maintenance
        echo '<div class="tts-tool-card">';
        echo '<h3>🔧 ' . esc_html__( 'System Maintenance', 'trello-social-auto-publisher' ) . '</h3>';
        echo '<p>' . esc_html__( 'Optimize database, clear cache, and perform system cleanup.', 'trello-social-auto-publisher' ) . '</p>';
        echo '<div class="tts-tool-actions">';
        echo '<button class="tts-btn warning" data-ajax-action="tts_system_maintenance" data-confirm="' . esc_attr__( 'This will perform system maintenance. Continue?', 'trello-social-auto-publisher' ) . '">';
        echo esc_html__( 'Run Maintenance', 'trello-social-auto-publisher' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        // System Report
        echo '<div class="tts-tool-card">';
        echo '<h3>📊 ' . esc_html__( 'System Report', 'trello-social-auto-publisher' ) . '</h3>';
        echo '<p>' . esc_html__( 'Generate comprehensive system report for troubleshooting.', 'trello-social-auto-publisher' ) . '</p>';
        echo '<div class="tts-tool-actions">';
        echo '<button class="tts-btn info" data-ajax-action="tts_generate_report">';
        echo esc_html__( 'Generate Report', 'trello-social-auto-publisher' );
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render dashboard statistics cards with optimized queries and caching.
     */
    private function render_dashboard_stats() {
        // Use transient caching for expensive queries (cache for 5 minutes)
        $cache_key = 'tts_dashboard_stats_' . get_current_user_id();
        $stats = get_transient($cache_key);
        
        if (false === $stats) {
            $stats = $this->get_optimized_dashboard_statistics();
            set_transient($cache_key, $stats, 5 * MINUTE_IN_SECONDS);
        }
        
        // Access variables directly from the stats array for security
        $total_posts = $stats['total_posts'];
        $total_clients = $stats['total_clients'];
        $scheduled_posts = $stats['scheduled_posts'];
        $published_today = $stats['published_today'];
        $published_yesterday = $stats['published_yesterday'];
        $failed_today = $stats['failed_today'];
        $success_rate = $stats['success_rate'];
        $trend_percentage = $stats['trend_percentage'];
        $next_scheduled = $stats['next_scheduled'];
        $weekly_average = $stats['weekly_average'];

        echo '<div class="tts-stats-row">';
        
        // Total Posts Card
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Total Posts', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . intval($total_posts->publish + $total_posts->draft + $total_posts->private) . '</span>';
        echo '<div class="tts-stat-trend">All time posts created</div>';
        echo '<span class="tts-tooltiptext">Total number of social media posts created in the system</span>';
        echo '</div>';
        
        // Active Clients Card
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Active Clients', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . intval($total_clients->publish) . '</span>';
        echo '<div class="tts-stat-trend">Currently configured</div>';
        echo '<span class="tts-tooltiptext">Number of clients with active social media configurations</span>';
        echo '</div>';
        
        // Scheduled Posts Card
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Scheduled Posts', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . intval($scheduled_posts) . '</span>';
        echo '<div class="tts-stat-trend">Awaiting publication</div>';
        echo '<span class="tts-tooltiptext">Posts scheduled for future publication</span>';
        echo '</div>';
        
        // Published Today Card with Trend
        $today_count = intval($published_today);
        $trend_class = $trend_percentage > 0 ? 'positive' : ($trend_percentage < 0 ? 'negative' : '');
        $trend_icon = $trend_percentage > 0 ? '↗' : ($trend_percentage < 0 ? '↘' : '→');
        
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Published Today', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . $today_count . '</span>';
        if ($published_yesterday > 0) {
            echo '<div class="tts-stat-trend ' . esc_attr($trend_class) . '">';
            echo esc_html($trend_icon . ' ' . abs($trend_percentage) . '% vs yesterday');
            echo '</div>';
        } else {
            echo '<div class="tts-stat-trend">Published today</div>';
        }
        echo '<span class="tts-tooltiptext">Posts successfully published today with trend comparison</span>';
        echo '</div>';

        echo '</div>';

        // Additional stats row for more detailed metrics
        echo '<div class="tts-stats-row">';
        
        // Failed Posts Today
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Failed Today', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number" style="color: #d63638;">' . $failed_today . '</span>';
        echo '<div class="tts-stat-trend">Requires attention</div>';
        echo '<span class="tts-tooltiptext">Posts that failed to publish today and need attention</span>';
        echo '</div>';

        // Success Rate (already calculated in optimized method)
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Success Rate', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number" style="color: ' . ($success_rate >= 95 ? '#00a32a' : ($success_rate >= 80 ? '#f56e28' : '#d63638')) . ';">' . $success_rate . '%</span>';
        echo '<div class="tts-stat-trend">Today\'s performance</div>';
        echo '<span class="tts-tooltiptext">Percentage of successful publications today</span>';
        echo '</div>';

        // Next Scheduled (already fetched in optimized method)
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Next Post', 'trello-social-auto-publisher') . '</h3>';
        if ($next_scheduled) {
            $time_diff = human_time_diff(current_time('timestamp'), strtotime($next_scheduled->publish_at));
            echo '<span class="tts-stat-number" style="font-size: 20px;">in ' . $time_diff . '</span>';
            echo '<div class="tts-stat-trend">' . esc_html($next_scheduled->post_title) . '</div>';
        } else {
            echo '<span class="tts-stat-number" style="font-size: 20px;">None</span>';
            echo '<div class="tts-stat-trend">No posts scheduled</div>';
        }
        echo '<span class="tts-tooltiptext">Time until the next scheduled post publication</span>';
        echo '</div>';

        // Weekly Average (already calculated in optimized method)
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Daily Average', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . $weekly_average . '</span>';
        echo '<div class="tts-stat-trend">Posts per day (7-day avg)</div>';
        echo '<span class="tts-tooltiptext">Average number of posts published per day over the last week</span>';
        echo '</div>';
        
        // Performance Metrics Card
        if ( isset( $stats['performance_metrics'] ) ) {
            $perf = $stats['performance_metrics'];
            echo '<div class="tts-stat-card tts-performance-card tts-tooltip">';
            echo '<h3>' . esc_html__('Performance', 'trello-social-auto-publisher') . '</h3>';
            echo '<div class="tts-perf-metrics">';
            echo '<div class="tts-perf-item">DB: ' . $perf['database_response_ms'] . 'ms</div>';
            echo '<div class="tts-perf-item">Memory: ' . $perf['memory_usage_mb'] . 'MB</div>';
            echo '<div class="tts-perf-item">Cache: ' . $perf['cache_hit_ratio'] . '%</div>';
            echo '</div>';
            echo '<span class="tts-tooltiptext">System performance metrics: database response time, memory usage, and cache hit ratio</span>';
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render recent posts section.
     */
    private function render_recent_posts_section() {
        echo '<div class="tts-dashboard-section">';
        echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">';
        echo '<h2 style="margin: 0;">' . esc_html__('Recent Social Posts', 'trello-social-auto-publisher') . '</h2>';
        echo '<div>';
        echo '<button class="tts-btn small" data-ajax-action="tts_refresh_posts" data-loading-text="' . esc_attr__('Refreshing...', 'trello-social-auto-publisher') . '">';
        echo esc_html__('Refresh', 'trello-social-auto-publisher');
        echo '</button>';
        echo '</div>';
        echo '</div>';
        
        $recent_posts = get_posts(array(
            'post_type' => 'tts_social_post',
            'posts_per_page' => 10,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($recent_posts)) {
            echo '<div class="tts-enhanced-table-container">';
            echo '<table class="widefat tts-enhanced-table">';
            echo '<thead><tr>';
            echo '<th style="width: 20px;"><input type="checkbox" class="tts-bulk-select-all"></th>';
            echo '<th>' . esc_html__('Title', 'trello-social-auto-publisher') . '</th>';
            echo '<th>' . esc_html__('Channel', 'trello-social-auto-publisher') . '</th>';
            echo '<th>' . esc_html__('Status', 'trello-social-auto-publisher') . '</th>';
            echo '<th>' . esc_html__('Date', 'trello-social-auto-publisher') . '</th>';
            echo '<th>' . esc_html__('Actions', 'trello-social-auto-publisher') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($recent_posts as $post) {
                $channel = get_post_meta($post->ID, '_tts_social_channel', true);
                $status = get_post_meta($post->ID, '_published_status', true);
                $publish_at = get_post_meta($post->ID, '_tts_publish_at', true);
                
                // Determine status class and text
                $status_class = $status === 'published' ? 'success' : ($status === 'failed' ? 'error' : 'warning');
                $status_text = $status ?: __('Scheduled', 'trello-social-auto-publisher');
                
                echo '<tr class="tts-list-item">';
                echo '<td><input type="checkbox" class="tts-bulk-select-item" value="' . esc_attr($post->ID) . '"></td>';
                echo '<td>';
                echo '<a href="' . esc_url(get_edit_post_link($post->ID)) . '" class="tts-tooltip">';
                echo '<strong>' . esc_html($post->post_title) . '</strong>';
                echo '<span class="tts-tooltiptext">' . esc_html__('Click to edit this post', 'trello-social-auto-publisher') . '</span>';
                echo '</a>';
                echo '<div class="row-actions">';
                echo '<span class="edit"><a href="' . esc_url(get_edit_post_link($post->ID)) . '">' . esc_html__('Edit', 'trello-social-auto-publisher') . '</a> | </span>';
                echo '<span class="delete"><a href="#" data-confirm="' . esc_attr__('Are you sure you want to delete this post?', 'trello-social-auto-publisher') . '" data-dangerous data-ajax-action="tts_delete_post" data-post-id="' . esc_attr($post->ID) . '">' . esc_html__('Delete', 'trello-social-auto-publisher') . '</a></span>';
                echo '</div>';
                echo '</td>';
                echo '<td>';
                if (is_array($channel)) {
                    foreach ($channel as $ch) {
                        echo '<span class="tts-status-badge info" style="margin-right: 5px;">' . esc_html($ch) . '</span>';
                    }
                } else {
                    echo '<span class="tts-status-badge info">' . esc_html($channel ?: __('No channel', 'trello-social-auto-publisher')) . '</span>';
                }
                echo '</td>';
                echo '<td><span class="tts-status-badge ' . $status_class . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>';
                $date_text = $publish_at ? date_i18n('Y-m-d H:i', strtotime($publish_at)) : get_the_date('Y-m-d H:i', $post);
                echo '<span class="tts-tooltip">';
                echo esc_html($date_text);
                echo '<span class="tts-tooltiptext">' . esc_html(human_time_diff(strtotime($date_text), current_time('timestamp'))) . ' ago</span>';
                echo '</span>';
                echo '</td>';
                echo '<td>';
                echo '<a href="' . esc_url(admin_url('admin.php?page=tts-social-posts&action=log&post=' . $post->ID)) . '" class="tts-btn small secondary">';
                echo esc_html__('View Log', 'trello-social-auto-publisher');
                echo '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            
            // Bulk actions
            echo '<div class="tts-bulk-actions">';
            echo '<h4>' . esc_html__('Bulk Actions', 'trello-social-auto-publisher') . '</h4>';
            echo '<div style="display: flex; gap: 10px; align-items: center;">';
            echo '<select class="tts-bulk-action-select">';
            echo '<option value="">' . esc_html__('Choose an action...', 'trello-social-auto-publisher') . '</option>';
            echo '<option value="delete">' . esc_html__('Delete', 'trello-social-auto-publisher') . '</option>';
            echo '<option value="approve">' . esc_html__('Approve', 'trello-social-auto-publisher') . '</option>';
            echo '<option value="revoke">' . esc_html__('Revoke', 'trello-social-auto-publisher') . '</option>';
            echo '</select>';
            echo '<button class="tts-btn" data-ajax-action="tts_bulk_action" data-confirm="' . esc_attr__('Are you sure you want to perform this action on the selected posts?', 'trello-social-auto-publisher') . '">';
            echo esc_html__('Apply', 'trello-social-auto-publisher');
            echo '</button>';
            echo '</div>';
            echo '</div>';
            
        } else {
            echo '<div style="text-align: center; padding: 40px; color: #666;">';
            echo '<span style="font-size: 48px; margin-bottom: 10px; display: block;">📝</span>';
            echo '<p style="margin: 0; font-size: 16px;">' . esc_html__('No social posts found.', 'trello-social-auto-publisher') . '</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 14px;">' . esc_html__('Create your first social media post to get started!', 'trello-social-auto-publisher') . '</p>';
            echo '<a href="' . esc_url(admin_url('admin.php?page=tts-client-wizard')) . '" class="tts-btn" style="margin-top: 15px;">';
            echo esc_html__('Add New Client', 'trello-social-auto-publisher');
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    }

    /**
     * Render quick actions section.
     */
    private function render_quick_actions_section() {
        echo '<div class="tts-dashboard-section">';
        echo '<h2>' . esc_html__('Quick Actions', 'trello-social-auto-publisher') . '</h2>';
        echo '<div class="tts-quick-actions">';
        
        $actions = array(
            array(
                'title' => __('Add New Client', 'trello-social-auto-publisher'),
                'description' => __('Set up a new social media client', 'trello-social-auto-publisher'),
                'url' => admin_url('admin.php?page=tts-client-wizard'),
                'icon' => 'dashicons-plus',
                'color' => '#135e96'
            ),
            array(
                'title' => __('View Calendar', 'trello-social-auto-publisher'),
                'description' => __('See scheduled posts in calendar view', 'trello-social-auto-publisher'),
                'url' => admin_url('admin.php?page=tts-calendar'),
                'icon' => 'dashicons-calendar',
                'color' => '#f56e28'
            ),
            array(
                'title' => __('Check Health Status', 'trello-social-auto-publisher'),
                'description' => __('Monitor system health and tokens', 'trello-social-auto-publisher'),
                'url' => admin_url('admin.php?page=tts-health'),
                'icon' => 'dashicons-heart',
                'color' => '#00a32a'
            ),
            array(
                'title' => __('View Analytics', 'trello-social-auto-publisher'),
                'description' => __('Analyze performance and engagement', 'trello-social-auto-publisher'),
                'url' => admin_url('admin.php?page=tts-analytics'),
                'icon' => 'dashicons-chart-area',
                'color' => '#7c3aed'
            ),
            array(
                'title' => __('Manage Posts', 'trello-social-auto-publisher'),
                'description' => __('View and manage all social posts', 'trello-social-auto-publisher'),
                'url' => admin_url('admin.php?page=tts-social-posts'),
                'icon' => 'dashicons-admin-post',
                'color' => '#2563eb'
            ),
            array(
                'title' => __('View Logs', 'trello-social-auto-publisher'),
                'description' => __('Check system logs and debugging info', 'trello-social-auto-publisher'),
                'url' => admin_url('admin.php?page=tts-log'),
                'icon' => 'dashicons-list-view',
                'color' => '#64748b'
            )
        );
        
        foreach ($actions as $action) {
            echo '<a href="' . esc_url($action['url']) . '" class="tts-quick-action tts-tooltip" style="border-left: 4px solid ' . $action['color'] . ';">';
            echo '<div style="display: flex; align-items: center;">';
            echo '<span class="dashicons ' . esc_attr($action['icon']) . '" style="color: ' . $action['color'] . '; margin-right: 12px; font-size: 20px;"></span>';
            echo '<div>';
            echo '<div style="font-weight: 600; margin-bottom: 2px;">' . esc_html($action['title']) . '</div>';
            echo '<div style="font-size: 12px; color: #666;">' . esc_html($action['description']) . '</div>';
            echo '</div>';
            echo '</div>';
            echo '<span class="tts-tooltiptext">' . esc_html($action['description']) . '</span>';
            echo '</a>';
        }
        
        echo '</div>';
        echo '</div>';
    }

    /**
     * Render system status widget for dashboard.
     */
    private function render_system_status_widget() {
        echo '<div class="tts-dashboard-section">';
        echo '<h2>' . esc_html__('System Status', 'trello-social-auto-publisher') . '</h2>';
        
        // Check various system components
        $status_checks = array();
        
        // Check WordPress requirements
        $wp_version = get_bloginfo('version');
        $status_checks['wordpress'] = array(
            'name' => 'WordPress Version',
            'status' => version_compare($wp_version, '5.0', '>=') ? 'success' : 'error',
            'message' => 'WordPress ' . $wp_version
        );
        
        // Check if Action Scheduler is available
        $status_checks['scheduler'] = array(
            'name' => 'Action Scheduler',
            'status' => class_exists('ActionScheduler') ? 'success' : 'warning',
            'message' => class_exists('ActionScheduler') ? 'Available' : 'Not available'
        );
        
        // Check recent error logs
        $recent_errors = get_posts(array(
            'post_type' => 'tts_log',
            'posts_per_page' => 1,
            'meta_query' => array(
                array(
                    'key' => '_log_level',
                    'value' => 'error',
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '24 hours ago'
                )
            )
        ));
        
        $status_checks['errors'] = array(
            'name' => 'Recent Errors',
            'status' => empty($recent_errors) ? 'success' : 'warning',
            'message' => empty($recent_errors) ? 'No errors in 24h' : count($recent_errors) . ' error(s) in 24h'
        );
        
        // Overall health calculation
        $success_count = 0;
        foreach ($status_checks as $check) {
            if ($check['status'] === 'success') $success_count++;
        }
        $health_percentage = round(($success_count / count($status_checks)) * 100);
        
        // Health indicator
        echo '<div style="text-align: center; margin-bottom: 15px;">';
        $health_color = $health_percentage >= 80 ? '#00a32a' : ($health_percentage >= 60 ? '#f56e28' : '#d63638');
        echo '<div style="font-size: 24px; color: ' . $health_color . '; font-weight: bold;">';
        echo $health_percentage . '% ' . esc_html__('Healthy', 'trello-social-auto-publisher');
        echo '</div>';
        echo '</div>';
        
        // Status items
        foreach ($status_checks as $key => $check) {
            $icon_color = $check['status'] === 'success' ? '#00a32a' : ($check['status'] === 'warning' ? '#f56e28' : '#d63638');
            echo '<div style="display: flex; align-items: center; margin-bottom: 8px;">';
            echo '<span class="tts-status-indicator ' . $check['status'] . '" style="background: ' . $icon_color . ';"></span>';
            echo '<span style="flex: 1;">' . esc_html($check['name']) . '</span>';
            echo '<span style="color: #666; font-size: 12px;">' . esc_html($check['message']) . '</span>';
            echo '</div>';
        }
        
        echo '<div style="margin-top: 15px;">';
        echo '<a href="' . admin_url('admin.php?page=tts-health') . '" class="tts-btn small">View Detailed Status</a>';
        echo '</div>';
        
        echo '</div>';
    }

    /**
     * Render the clients list page.
     */
    public function render_clients_page() {
        $clients = get_posts(
            array(
                'post_type'      => 'tts_client',
                'posts_per_page' => -1,
            )
        );
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Clienti', 'trello-social-auto-publisher' ) . '</h1>';
        if ( ! empty( $clients ) ) {
            echo '<ul>';
            foreach ( $clients as $client ) {
                $url = admin_url( 'edit.php?post_type=tts_social_post&tts_client=' . $client->ID );
                echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $client->post_title ) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . esc_html__( 'Nessun cliente trovato.', 'trello-social-auto-publisher' ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render the client creation wizard.
     */
    public function tts_render_client_wizard() {
        if ( ! session_id() ) {
            session_start();
        }

        // Security: Verify nonce for form submissions
        if ( isset( $_POST['step'] ) && $_POST['step'] > 1 ) {
            if ( ! wp_verify_nonce( $_POST['tts_wizard_nonce'], 'tts_client_wizard' ) ) {
                wp_die( esc_html__( 'Security verification failed. Please try again.', 'trello-social-auto-publisher' ) );
            }
        }

        $step = isset( $_REQUEST['step'] ) ? absint( $_REQUEST['step'] ) : 1;

        echo '<div class="wrap tts-client-wizard">';
        echo '<h1>' . esc_html__( 'Client Wizard', 'trello-social-auto-publisher' ) . '</h1>';

        // Add helpful notice about social media setup
        if ( 2 === $step ) {
            echo '<div class="notice notice-info">';
            echo '<h3>' . esc_html__( 'Social Media Setup Required', 'trello-social-auto-publisher' ) . '</h3>';
            echo '<p>' . esc_html__( 'To connect social media accounts, you must first configure OAuth apps for each platform. Click "Configure App" for platforms that are not set up.', 'trello-social-auto-publisher' ) . '</p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tts-social-connections' ) ) . '" class="button">' . esc_html__( 'Manage Social Connections', 'trello-social-auto-publisher' ) . '</a> ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=tts-main' ) ) . '" target="_blank">' . esc_html__( 'View Setup Guide', 'trello-social-auto-publisher' ) . '</a></p>';
            echo '</div>';
        }

        $fb_token = get_transient( 'tts_oauth_facebook_token' );
        $ig_token = get_transient( 'tts_oauth_instagram_token' );
        $yt_token = get_transient( 'tts_oauth_youtube_token' );
        $tt_token = get_transient( 'tts_oauth_tiktok_token' );

        $trello_key   = isset( $_REQUEST['trello_key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['trello_key'] ) ) : '';
        $trello_token = isset( $_REQUEST['trello_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['trello_token'] ) ) : '';
        $board        = isset( $_REQUEST['trello_board'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['trello_board'] ) ) : '';
        $channels     = isset( $_REQUEST['channels'] ) ? array_map( 'sanitize_text_field', (array) $_REQUEST['channels'] ) : array();

        if ( 1 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-1">';
            wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce' );
            echo '<input type="hidden" name="step" value="2" />';
            echo '<p><label>' . esc_html__( 'Trello API Key', 'trello-social-auto-publisher' ) . '<br />';
            echo '<input type="text" name="trello_key" value="' . esc_attr( $trello_key ) . '" required /></label></p>';
            echo '<p><label>' . esc_html__( 'Trello Token', 'trello-social-auto-publisher' ) . '<br />';
            echo '<input type="text" name="trello_token" value="' . esc_attr( $trello_token ) . '" required /></label></p>';

            $boards = array();
            if ( $trello_key && $trello_token ) {
                $response = wp_remote_get(
                    'https://api.trello.com/1/members/me/boards?key=' . rawurlencode( $trello_key ) . '&token=' . rawurlencode( $trello_token ),
                    array( 'timeout' => 20 )
                );
                if ( ! is_wp_error( $response ) ) {
                    $boards = json_decode( wp_remote_retrieve_body( $response ), true );
                }
            }

            if ( ! empty( $boards ) ) {
                echo '<p><label>' . esc_html__( 'Trello Board', 'trello-social-auto-publisher' ) . '<br />';
                echo '<select name="trello_board">';
                foreach ( $boards as $b ) {
                    printf( '<option value="%s" %s>%s</option>', esc_attr( $b['id'] ), selected( $board, $b['id'], false ), esc_html( $b['name'] ) );
                }
                echo '</select></label></p>';
            }

            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'trello-social-auto-publisher' ) . '</button></p>';
            echo '</form>';
        } elseif ( 2 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-2">';
            wp_nonce_field( 'tts_client_wizard', 'tts_wizard_nonce' );
            echo '<input type="hidden" name="step" value="3" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';

            $opts = array(
                'facebook'  => __( 'Facebook', 'trello-social-auto-publisher' ),
                'instagram' => __( 'Instagram', 'trello-social-auto-publisher' ),
                'youtube'   => __( 'YouTube', 'trello-social-auto-publisher' ),
                'tiktok'    => __( 'TikTok', 'trello-social-auto-publisher' ),
            );

            foreach ( $opts as $slug => $label ) {
                $token     = '';
                $connected = false;
                $app_configured = false;
                
                // Check if app is configured
                $settings = get_option( 'tts_social_apps', array() );
                $platform_settings = isset( $settings[$slug] ) ? $settings[$slug] : array();
                
                switch ( $slug ) {
                    case 'facebook':
                        $token     = $fb_token;
                        $connected = ! empty( $fb_token );
                        $app_configured = ! empty( $platform_settings['app_id'] ) && ! empty( $platform_settings['app_secret'] );
                        break;
                    case 'instagram':
                        $token     = $ig_token;
                        $connected = ! empty( $ig_token );
                        $app_configured = ! empty( $platform_settings['app_id'] ) && ! empty( $platform_settings['app_secret'] );
                        break;
                    case 'youtube':
                        $token     = $yt_token;
                        $connected = ! empty( $yt_token );
                        $app_configured = ! empty( $platform_settings['client_id'] ) && ! empty( $platform_settings['client_secret'] );
                        break;
                    case 'tiktok':
                        $token     = $tt_token;
                        $connected = ! empty( $tt_token );
                        $app_configured = ! empty( $platform_settings['client_key'] ) && ! empty( $platform_settings['client_secret'] );
                        break;
                }

                echo '<div style="border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px;">';
                echo '<p><label><input type="checkbox" name="channels[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $channels, true ) || $connected, true, false ) . ' /> <strong>' . esc_html( $label ) . '</strong></label>';
                
                if ( ! $app_configured ) {
                    echo '<br><span style="color: #d63638;">⚠️ ' . esc_html__( 'App not configured', 'trello-social-auto-publisher' ) . '</span>';
                    echo '<br><a href="' . esc_url( admin_url( 'admin.php?page=tts-social-connections' ) ) . '" class="button">' . esc_html__( 'Configure App', 'trello-social-auto-publisher' ) . '</a>';
                } elseif ( $connected ) {
                    echo '<br><span style="color: #00a32a;">✅ ' . esc_html__( 'Connected', 'trello-social-auto-publisher' ) . '</span>';
                } else {
                    $url = add_query_arg( array( 'action' => 'tts_oauth_' . $slug, 'step' => 2 ), admin_url( 'admin-post.php' ) );
                    echo '<br><span style="color: #f56e28;">🟡 ' . esc_html__( 'Ready to connect', 'trello-social-auto-publisher' ) . '</span>';
                    echo '<br><a href="' . esc_url( $url ) . '" class="button button-primary">' . esc_html__( 'Connect Account', 'trello-social-auto-publisher' ) . '</a>';
                }
                echo '</div>';
            }

            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'trello-social-auto-publisher' ) . '</button></p>';
            echo '</form>';
        } elseif ( 3 === $step ) {
            echo '<form method="post" class="tts-wizard-step tts-step-3">';
            echo '<input type="hidden" name="step" value="4" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            foreach ( $channels as $ch ) {
                echo '<input type="hidden" name="channels[]" value="' . esc_attr( $ch ) . '" />';
            }
            echo '<div id="tts-lists" data-board="' . esc_attr( $board ) . '" data-key="' . esc_attr( $trello_key ) . '" data-token="' . esc_attr( $trello_token ) . '"></div>';
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Next', 'trello-social-auto-publisher' ) . '</button></p>';
            echo '</form>';
        } else {
            if ( isset( $_POST['finalize'] ) ) {
                $post_id = wp_insert_post(
                    array(
                        'post_type'   => 'tts_client',
                        'post_status' => 'publish',
                        'post_title'  => 'Client ' . $board,
                    )
                );
                if ( $post_id ) {
                    update_post_meta( $post_id, '_tts_trello_key', $trello_key );
                    update_post_meta( $post_id, '_tts_trello_token', $trello_token );
                    if ( $fb_token ) {
                        update_post_meta( $post_id, '_tts_fb_token', $fb_token );
                    }
                    if ( $ig_token ) {
                        update_post_meta( $post_id, '_tts_ig_token', $ig_token );
                    }
                    if ( $yt_token ) {
                        update_post_meta( $post_id, '_tts_yt_token', $yt_token );
                    }
                    if ( $tt_token ) {
                        update_post_meta( $post_id, '_tts_tt_token', $tt_token );
                    }
                    if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
                        $map = array();
                        foreach ( $_POST['tts_trello_map'] as $id_list => $row ) {
                            if ( empty( $row['canale_social'] ) ) {
                                continue;
                            }
                            $map[] = array(
                                'idList'        => sanitize_text_field( $id_list ),
                                'canale_social' => sanitize_text_field( $row['canale_social'] ),
                            );
                        }
                        if ( ! empty( $map ) ) {
                            update_post_meta( $post_id, '_tts_trello_map', $map );
                        }
                    }

                    delete_transient( 'tts_oauth_facebook_token' );
                    delete_transient( 'tts_oauth_instagram_token' );
                    delete_transient( 'tts_oauth_youtube_token' );
                    delete_transient( 'tts_oauth_tiktok_token' );

                    echo '<p>' . esc_html__( 'Client created.', 'trello-social-auto-publisher' ) . '</p>';
                }
                echo '</div>';
                return;
            }

            echo '<form method="post" class="tts-wizard-step tts-step-4">';
            echo '<input type="hidden" name="step" value="4" />';
            echo '<input type="hidden" name="finalize" value="1" />';
            echo '<input type="hidden" name="trello_key" value="' . esc_attr( $trello_key ) . '" />';
            echo '<input type="hidden" name="trello_token" value="' . esc_attr( $trello_token ) . '" />';
            echo '<input type="hidden" name="trello_board" value="' . esc_attr( $board ) . '" />';
            foreach ( $channels as $ch ) {
                echo '<input type="hidden" name="channels[]" value="' . esc_attr( $ch ) . '" />';
            }
            if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
                foreach ( $_POST['tts_trello_map'] as $id_list => $row ) {
                    echo '<input type="hidden" name="tts_trello_map[' . esc_attr( $id_list ) . '][canale_social]" value="' . esc_attr( $row['canale_social'] ) . '" />';
                }
            }

            echo '<h2>' . esc_html__( 'Summary', 'trello-social-auto-publisher' ) . '</h2>';
            echo '<p>' . esc_html__( 'Trello Board:', 'trello-social-auto-publisher' ) . ' ' . esc_html( $board ) . '</p>';
            echo '<p>' . esc_html__( 'Channels:', 'trello-social-auto-publisher' ) . ' ' . esc_html( implode( ', ', $channels ) ) . '</p>';
            echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Create Client', 'trello-social-auto-publisher' ) . '</button></p>';
            echo '</form>';
        }

        echo '</div>';
    }

    /**
     * Add dropdown filter on social posts list table.
     *
     * @param string $post_type Current post type.
     */
    public function add_client_filter( $post_type ) {
        if ( 'tts_social_post' !== $post_type ) {
            return;
        }

        $selected = isset( $_GET['tts_client'] ) ? absint( $_GET['tts_client'] ) : 0;
        $clients  = get_posts(
            array(
                'post_type'      => 'tts_client',
                'posts_per_page' => -1,
            )
        );
        echo '<select name="tts_client">';
        echo '<option value="">' . esc_html__( 'All Clients', 'trello-social-auto-publisher' ) . '</option>';
        foreach ( $clients as $client ) {
            printf(
                '<option value="%1$d" %3$s>%2$s</option>',
                $client->ID,
                esc_html( $client->post_title ),
                selected( $selected, $client->ID, false )
            );
        }
        echo '</select>';
    }

    /**
     * Filter social posts list by selected client.
     *
     * @param WP_Query $query Current query instance.
     */
    public function filter_posts_by_client( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'tts_social_post' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( ! empty( $_GET['tts_client'] ) ) {
            $query->set(
                'meta_query',
                array(
                    array(
                        'key'   => '_tts_client_id',
                        'value' => absint( $_GET['tts_client'] ),
                    ),
                )
            );
        }
    }

    /**
     * Add approval status filter on social posts list table.
     *
     * @param string $post_type Current post type.
     */
    public function add_approved_filter( $post_type ) {
        if ( 'tts_social_post' !== $post_type ) {
            return;
        }

        $selected = isset( $_GET['tts_approved'] ) ? sanitize_text_field( $_GET['tts_approved'] ) : '';
        echo '<select name="tts_approved">';
        echo '<option value="">' . esc_html__( 'Stato approvazione', 'trello-social-auto-publisher' ) . '</option>';
        echo '<option value="1" ' . selected( $selected, '1', false ) . '>' . esc_html__( 'Approvato', 'trello-social-auto-publisher' ) . '</option>';
        echo '<option value="0" ' . selected( $selected, '0', false ) . '>' . esc_html__( 'Non approvato', 'trello-social-auto-publisher' ) . '</option>';
        echo '</select>';
    }

    /**
     * Filter social posts list by approval status.
     *
     * @param WP_Query $query Current query instance.
     */
    public function filter_posts_by_approved( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'tts_social_post' !== $query->get( 'post_type' ) ) {
            return;
        }

        if ( isset( $_GET['tts_approved'] ) && '' !== $_GET['tts_approved'] ) {
            $meta_query   = (array) $query->get( 'meta_query', array() );
            $meta_query[] = array(
                'key'   => '_tts_approved',
                'value' => '1' === $_GET['tts_approved'] ? '1' : '0',
            );
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Add approved column to social posts list.
     *
     * @param array $columns Existing columns.
     *
     * @return array
     */
    public function add_approved_column( $columns ) {
        $columns['tts_approved'] = __( 'Approvato', 'trello-social-auto-publisher' );
        return $columns;
    }

    /**
     * Render approved column content.
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public function render_approved_column( $column, $post_id ) {
        if ( 'tts_approved' === $column ) {
            $approved = (bool) get_post_meta( $post_id, '_tts_approved', true );
            echo $approved ? esc_html__( 'Si', 'trello-social-auto-publisher' ) : esc_html__( 'No', 'trello-social-auto-publisher' );
        }
    }

    /**
     * Register bulk actions for approving/revoking posts.
     *
     * @param array $actions Existing actions.
     *
     * @return array
     */
    public function register_bulk_actions( $actions ) {
        $actions['tts_approve'] = __( 'Approva', 'trello-social-auto-publisher' );
        $actions['tts_revoke']  = __( 'Revoca', 'trello-social-auto-publisher' );
        return $actions;
    }

    /**
     * Handle bulk actions for approval status.
     *
     * @param string $redirect_to Redirect URL.
     * @param string $doaction    Action name.
     * @param array  $post_ids    Selected post IDs.
     *
     * @return string
     */
    public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
        if ( 'tts_approve' === $doaction ) {
            foreach ( $post_ids as $post_id ) {
                update_post_meta( $post_id, '_tts_approved', true );
                do_action( 'save_post_tts_social_post', $post_id, get_post( $post_id ), true );
                do_action( 'tts_post_approved', $post_id );
            }
        } elseif ( 'tts_revoke' === $doaction ) {
            foreach ( $post_ids as $post_id ) {
                delete_post_meta( $post_id, '_tts_approved' );
                do_action( 'save_post_tts_social_post', $post_id, get_post( $post_id ), true );
            }
        }

        return $redirect_to;
    }

    /**
     * Render social posts list page.
     */
    public function render_social_posts_page() {
        // Handle publish now action.
        if ( isset( $_GET['action'], $_GET['post'] ) && 'publish' === $_GET['action'] ) {
            if ( ! current_user_can( 'publish_posts' ) ) {
                wp_die( esc_html__( 'Sorry, you are not allowed to publish this post.', 'trello-social-auto-publisher' ) );
            }

            check_admin_referer( 'tts_publish_social_post_' . absint( $_GET['post'] ) );
            do_action( 'tts_publish_social_post', array( 'post_id' => absint( $_GET['post'] ) ) );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Post published.', 'trello-social-auto-publisher' ) . '</p></div>';
        }

        // Handle log view.
        if ( isset( $_GET['action'], $_GET['post'] ) && 'log' === $_GET['action'] ) {
            $log = get_post_meta( absint( $_GET['post'] ), '_tts_publish_log', true );
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Log', 'trello-social-auto-publisher' ) . '</h1>';
            if ( ! empty( $log ) ) {
                echo '<div class="tts-log-display">';
                if ( is_array( $log ) || is_object( $log ) ) {
                    echo '<pre class="tts-log-content">' . esc_html( wp_json_encode( $log, JSON_PRETTY_PRINT ) ) . '</pre>';
                } else {
                    echo '<pre class="tts-log-content">' . esc_html( $log ) . '</pre>';
                }
                echo '</div>';
            } else {
                echo '<p>' . esc_html__( 'No log entries found.', 'trello-social-auto-publisher' ) . '</p>';
            }
            echo '</div>';
            return;
        }

        $table = new TTS_Social_Posts_Table();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Social Post', 'trello-social-auto-publisher' ) . '</h1>';
        $table->display();
        echo '</div>';
    }
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table implementation for social posts.
 */
class TTS_Social_Posts_Table extends WP_List_Table {

    /**
     * Retrieve table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'title'        => __( 'Titolo', 'trello-social-auto-publisher' ),
            'channel'      => __( 'Canale', 'trello-social-auto-publisher' ),
            'publish_date' => __( 'Data Pubblicazione', 'trello-social-auto-publisher' ),
            'status'       => __( 'Stato', 'trello-social-auto-publisher' ),
        );
    }

    /**
     * Prepare the table items.
     */
    public function prepare_items() {
        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'post_status'    => 'any',
                'posts_per_page' => -1,
            )
        );

        $data = array();
        foreach ( $posts as $post ) {
            $channel = get_post_meta( $post->ID, '_tts_social_channel', true );
            $publish = get_post_meta( $post->ID, '_tts_publish_at', true );
            $status  = get_post_meta( $post->ID, '_published_status', true );

            $data[] = array(
                'ID'          => $post->ID,
                'title'       => $post->post_title,
                'channel'     => is_array( $channel ) ? implode( ', ', $channel ) : $channel,
                'publish_date'=> $publish ? date_i18n( 'Y-m-d H:i', strtotime( $publish ) ) : '',
                'status'      => $status ? $status : __( 'scheduled', 'trello-social-auto-publisher' ),
            );
        }

        $this->items = $data;
    }

    /**
     * Render title column with row actions.
     *
     * @param array $item Current row.
     *
     * @return string
     */
    public function column_title( $item ) {
        $publish_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page'   => 'tts-social-posts',
                    'action' => 'publish',
                    'post'   => $item['ID'],
                ),
                admin_url( 'admin.php' )
            ),
            'tts_publish_social_post_' . $item['ID']
        );

        $actions = array(
            'publish'  => sprintf( '<a href="%s">%s</a>', esc_url( $publish_url ), __( 'Publish Now', 'trello-social-auto-publisher' ) ),
            'edit'     => sprintf( '<a href="%s">%s</a>', get_edit_post_link( $item['ID'] ), __( 'Edit', 'trello-social-auto-publisher' ) ),
            'view_log' => sprintf( '<a href="%s">%s</a>', esc_url( add_query_arg( array( 'page' => 'tts-social-posts', 'action' => 'log', 'post' => $item['ID'] ), admin_url( 'admin.php' ) ) ), __( 'View Log', 'trello-social-auto-publisher' ) ),
        );

        return sprintf( '<strong>%1$s</strong>%2$s', esc_html( $item['title'] ), $this->row_actions( $actions ) );
    }

    /**
     * Default column rendering.
     *
     * @param array  $item        Row item.
     * @param string $column_name Column name.
     *
     * @return string
     */
    public function column_default( $item, $column_name ) {
        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    /**
     * Get optimized dashboard statistics with single database query.
     *
     * @return array Optimized statistics data.
     */
    private function get_optimized_dashboard_statistics() {
        global $wpdb;

        // Get basic post counts
        $total_posts = wp_count_posts('tts_social_post');
        $total_clients = wp_count_posts('tts_client');

        // Single optimized query to get all post statistics by date and status
        $today = current_time('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day', current_time('timestamp')));
        $current_time = current_time('mysql');

        $query = $wpdb->prepare("
            SELECT 
                pm.meta_value as status,
                DATE(p.post_date) as post_date,
                pm2.meta_value as publish_at,
                COUNT(*) as count
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_published_status'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_tts_publish_at'
            WHERE p.post_type = 'tts_social_post'
            AND (
                DATE(p.post_date) = %s 
                OR DATE(p.post_date) = %s
                OR (pm2.meta_value IS NOT NULL AND pm2.meta_value >= %s)
            )
            GROUP BY pm.meta_value, DATE(p.post_date)
        ", $today, $yesterday, $current_time);

        $results = $wpdb->get_results($query);

        // Process results
        $published_today = 0;
        $published_yesterday = 0;
        $failed_today = 0;
        $scheduled_posts = 0;

        foreach ($results as $row) {
            if ($row->post_date === $today) {
                if ($row->status === 'published') {
                    $published_today = $row->count;
                } elseif ($row->status === 'failed') {
                    $failed_today = $row->count;
                }
            } elseif ($row->post_date === $yesterday && $row->status === 'published') {
                $published_yesterday = $row->count;
            }
            
            // Count scheduled posts (those with future publish_at)
            if ($row->publish_at && $row->publish_at >= $current_time) {
                $scheduled_posts += $row->count;
            }
        }

        // Calculate additional metrics
        $total_today = $published_today + $failed_today;
        $success_rate = $total_today > 0 ? round(($published_today / $total_today) * 100) : 100;
        $trend_percentage = $published_yesterday > 0 ? round((($published_today - $published_yesterday) / $published_yesterday) * 100) : 0;

        // Get next scheduled post
        $next_scheduled = $wpdb->get_row($wpdb->prepare("
            SELECT p.ID, p.post_title, pm.meta_value as publish_at
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type = 'tts_social_post'
            AND pm.meta_key = '_tts_publish_at'
            AND pm.meta_value >= %s
            ORDER BY pm.meta_value ASC
            LIMIT 1
        ", $current_time));

        // Weekly average
        $week_published = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'tts_social_post'
            AND pm.meta_key = '_published_status'
            AND pm.meta_value = 'published'
            AND p.post_date >= %s
        ", date('Y-m-d H:i:s', strtotime('-1 week', current_time('timestamp')))));

        $weekly_average = round($week_published / 7, 1);

        return array(
            'total_posts' => $total_posts,
            'total_clients' => $total_clients,
            'scheduled_posts' => $scheduled_posts,
            'published_today' => $published_today,
            'published_yesterday' => $published_yesterday,
            'failed_today' => $failed_today,
            'success_rate' => $success_rate,
            'trend_percentage' => $trend_percentage,
            'next_scheduled' => $next_scheduled,
            'weekly_average' => $weekly_average,
            'performance_metrics' => TTS_Performance::get_performance_metrics(),
        );
    }

    /**
     * Simple rate limiting for AJAX endpoints.
     *
     * @param string $action Action being performed.
     * @param int $limit Maximum number of requests.
     * @param int $window Time window in seconds.
     * @return bool Whether the request is within limits.
     */
    private function check_rate_limit($action, $limit = 10, $window = 60) {
        $user_id = get_current_user_id();
        $transient_key = "tts_rate_limit_{$action}_{$user_id}";
        
        $current_count = get_transient($transient_key);
        
        if (false === $current_count) {
            // First request in this window
            set_transient($transient_key, 1, $window);
            return true;
        }
        
        if ($current_count >= $limit) {
            return false; // Rate limit exceeded
        }
        
        // Increment counter
        set_transient($transient_key, $current_count + 1, $window);
        return true;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Social Auto Publisher Settings', 'trello-social-auto-publisher' ); ?></h1>
            
            <div class="notice notice-info">
                <p><?php esc_html_e( 'Configure your global plugin settings here. For social media connections, please visit the Social Connections page.', 'trello-social-auto-publisher' ); ?></p>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields( 'tts_settings_group' );
                do_settings_sections( 'tts_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the social connections page.
     */
    public function render_social_connections_page() {
        // Handle form submissions
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'save_social_apps' ) {
            if ( wp_verify_nonce( $_POST['tts_social_nonce'], 'tts_save_social_apps' ) ) {
                $this->save_social_app_settings();
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Social media app settings saved successfully!', 'trello-social-auto-publisher' ) . '</p></div>';
            }
        }

        $settings = get_option( 'tts_social_apps', array() );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Social Media Connections', 'trello-social-auto-publisher' ); ?></h1>
            
            <div class="notice notice-info">
                <h3><?php esc_html_e( 'Setup Instructions', 'trello-social-auto-publisher' ); ?></h3>
                <p><?php esc_html_e( 'To connect your social media accounts, you need to create apps on each platform and configure OAuth credentials:', 'trello-social-auto-publisher' ); ?></p>
                <ol>
                    <li><strong>Facebook:</strong> <?php esc_html_e( 'Create an app at', 'trello-social-auto-publisher' ); ?> <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a></li>
                    <li><strong>Instagram:</strong> <?php esc_html_e( 'Use Facebook app with Instagram Basic Display product', 'trello-social-auto-publisher' ); ?></li>
                    <li><strong>YouTube:</strong> <?php esc_html_e( 'Create a project at', 'trello-social-auto-publisher' ); ?> <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a></li>
                    <li><strong>TikTok:</strong> <?php esc_html_e( 'Apply for TikTok for Developers at', 'trello-social-auto-publisher' ); ?> <a href="https://developers.tiktok.com/" target="_blank">TikTok Developers</a></li>
                </ol>
                <p><strong><?php esc_html_e( 'Redirect URI:', 'trello-social-auto-publisher' ); ?></strong> <code><?php echo esc_url( admin_url( 'admin-post.php' ) ); ?></code></p>
            </div>

            <div class="tts-social-apps-container">
                <form method="post" action="">
                    <?php wp_nonce_field( 'tts_save_social_apps', 'tts_social_nonce' ); ?>
                    <input type="hidden" name="action" value="save_social_apps" />

                    <div class="tts-social-platforms">
                        <?php
                        $platforms = array(
                            'facebook' => array(
                                'name' => 'Facebook',
                                'icon' => '📘',
                                'fields' => array( 'app_id', 'app_secret' )
                            ),
                            'instagram' => array(
                                'name' => 'Instagram',
                                'icon' => '📷',
                                'fields' => array( 'app_id', 'app_secret' )
                            ),
                            'youtube' => array(
                                'name' => 'YouTube',
                                'icon' => '🎥',
                                'fields' => array( 'client_id', 'client_secret' )
                            ),
                            'tiktok' => array(
                                'name' => 'TikTok',
                                'icon' => '🎵',
                                'fields' => array( 'client_key', 'client_secret' )
                            )
                        );

                        foreach ( $platforms as $platform => $config ) :
                            $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
                        ?>
                        <div class="tts-platform-config">
                            <h2><?php echo esc_html( $config['icon'] . ' ' . $config['name'] ); ?></h2>
                            
                            <?php foreach ( $config['fields'] as $field ) : 
                                $field_value = isset( $platform_settings[$field] ) ? $platform_settings[$field] : '';
                                $field_label = ucwords( str_replace( '_', ' ', $field ) );
                            ?>
                            <p>
                                <label for="<?php echo esc_attr( $platform . '_' . $field ); ?>">
                                    <?php echo esc_html( $field_label ); ?>:
                                </label>
                                <input type="text" 
                                       id="<?php echo esc_attr( $platform . '_' . $field ); ?>"
                                       name="social_apps[<?php echo esc_attr( $platform ); ?>][<?php echo esc_attr( $field ); ?>]"
                                       value="<?php echo esc_attr( $field_value ); ?>"
                                       class="regular-text" />
                            </p>
                            <?php endforeach; ?>

                            <?php 
                            // Check connection status
                            $connection_status = $this->check_platform_connection_status( $platform );
                            ?>
                            <div class="tts-connection-status">
                                <strong><?php esc_html_e( 'Status:', 'trello-social-auto-publisher' ); ?></strong>
                                <span class="tts-status-<?php echo esc_attr( $connection_status['status'] ); ?>">
                                    <?php echo esc_html( $connection_status['message'] ); ?>
                                </span>
                                
                                <?php if ( $connection_status['status'] === 'configured' ) : ?>
                                    <div class="tts-platform-actions">
                                        <a href="<?php echo esc_url( $this->get_oauth_url( $platform ) ); ?>" 
                                           class="button button-primary">
                                            <?php esc_html_e( 'Connect Account', 'trello-social-auto-publisher' ); ?>
                                        </a>
                                        <button type="button" class="button tts-test-connection" 
                                                data-platform="<?php echo esc_attr( $platform ); ?>">
                                            <?php esc_html_e( 'Test Connection', 'trello-social-auto-publisher' ); ?>
                                        </button>
                                    </div>
                                    <div class="tts-test-result" id="test-result-<?php echo esc_attr( $platform ); ?>" style="display: none;"></div>
                                <?php endif; ?>
                                
                                <?php if ( $connection_status['status'] === 'connected' ) : ?>
                                    <div class="tts-rate-limit-info" id="rate-limit-<?php echo esc_attr( $platform ); ?>">
                                        <button type="button" class="button tts-check-limits" 
                                                data-platform="<?php echo esc_attr( $platform ); ?>">
                                            <?php esc_html_e( 'Check API Limits', 'trello-social-auto-publisher' ); ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save App Settings', 'trello-social-auto-publisher' ); ?>" />
                    </p>
                </form>
            </div>

            <script>
            jQuery(document).ready(function($) {
                // Connection testing
                $('.tts-test-connection').on('click', function() {
                    var platform = $(this).data('platform');
                    var resultDiv = $('#test-result-' + platform);
                    var button = $(this);
                    
                    button.prop('disabled', true).text('<?php esc_html_e( 'Testing...', 'trello-social-auto-publisher' ); ?>');
                    resultDiv.hide();
                    
                    $.post(ajaxurl, {
                        action: 'tts_test_connection',
                        platform: platform,
                        nonce: '<?php echo wp_create_nonce( 'tts_test_connection' ); ?>'
                    }, function(response) {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'trello-social-auto-publisher' ); ?>');
                        
                        if (response.success) {
                            resultDiv.removeClass('error').addClass('success')
                                     .html('✅ ' + response.data.message).show();
                        } else {
                            resultDiv.removeClass('success').addClass('error')
                                     .html('❌ ' + (response.data.message || 'Connection test failed')).show();
                        }
                    }).fail(function() {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Test Connection', 'trello-social-auto-publisher' ); ?>');
                        resultDiv.removeClass('success').addClass('error')
                                 .html('❌ Failed to test connection').show();
                    });
                });
                
                // Rate limit checking
                $('.tts-check-limits').on('click', function() {
                    var platform = $(this).data('platform');
                    var container = $('#rate-limit-' + platform);
                    var button = $(this);
                    
                    button.prop('disabled', true).text('<?php esc_html_e( 'Checking...', 'trello-social-auto-publisher' ); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'tts_check_rate_limits',
                        platform: platform,
                        nonce: '<?php echo wp_create_nonce( 'tts_check_rate_limits' ); ?>'
                    }, function(response) {
                        button.prop('disabled', false).text('<?php esc_html_e( 'Check API Limits', 'trello-social-auto-publisher' ); ?>');
                        
                        if (response.success) {
                            var limits = response.data;
                            var html = '<div class="tts-rate-limit-display">';
                            html += '<strong><?php esc_html_e( 'API Rate Limits:', 'trello-social-auto-publisher' ); ?></strong><br>';
                            html += '<?php esc_html_e( 'Used:', 'trello-social-auto-publisher' ); ?> ' + limits.used + ' / ' + limits.limit + '<br>';
                            html += '<?php esc_html_e( 'Remaining:', 'trello-social-auto-publisher' ); ?> ' + limits.remaining + '<br>';
                            html += '<?php esc_html_e( 'Reset:', 'trello-social-auto-publisher' ); ?> ' + limits.reset_time;
                            html += '</div>';
                            container.append(html);
                        }
                    });
                });
            });
            </script>

            <style>
            .tts-social-apps-container {
                margin-top: 20px;
            }
            .tts-social-platforms {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }
            .tts-platform-config {
                border: 1px solid #ddd;
                padding: 20px;
                border-radius: 8px;
                background: #fff;
            }
            .tts-platform-config h2 {
                margin-top: 0;
                font-size: 1.3em;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            .tts-connection-status {
                margin-top: 15px;
                padding: 10px;
                background: #f9f9f9;
                border-radius: 4px;
            }
            .tts-status-not-configured {
                color: #d63638;
            }
            .tts-status-configured {
                color: #f56e28;
            }
            .tts-status-connected {
                color: #00a32a;
            }
            .tts-platform-actions {
                display: flex;
                gap: 10px;
                margin-top: 10px;
            }
            .tts-test-result {
                margin-top: 10px;
                padding: 8px;
                border-radius: 4px;
                font-size: 14px;
            }
            .tts-test-result.success {
                background: #d1eddd;
                color: #00a32a;
                border: 1px solid #00a32a;
            }
            .tts-test-result.error {
                background: #f7dde0;
                color: #d63638;
                border: 1px solid #d63638;
            }
            .tts-rate-limit-info {
                margin-top: 10px;
            }
            .tts-rate-limit-display {
                font-size: 12px;
                color: #666;
                margin-top: 5px;
            }
            </style>
        </div>
        <?php
    }

    /**
     * Save social media app settings.
     */
    private function save_social_app_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $social_apps = isset( $_POST['social_apps'] ) ? $_POST['social_apps'] : array();
        $sanitized_apps = array();

        foreach ( $social_apps as $platform => $settings ) {
            $platform = sanitize_key( $platform );
            $sanitized_apps[$platform] = array();
            
            foreach ( $settings as $key => $value ) {
                $key = sanitize_key( $key );
                $sanitized_apps[$platform][$key] = sanitize_text_field( $value );
            }
        }

        update_option( 'tts_social_apps', $sanitized_apps );
    }

    /**
     * Check the connection status for a platform.
     *
     * @param string $platform Platform name.
     * @return array Status information.
     */
    private function check_platform_connection_status( $platform ) {
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();

        // Check if app credentials are configured
        $required_fields = array();
        switch ( $platform ) {
            case 'facebook':
            case 'instagram':
                $required_fields = array( 'app_id', 'app_secret' );
                break;
            case 'youtube':
                $required_fields = array( 'client_id', 'client_secret' );
                break;
            case 'tiktok':
                $required_fields = array( 'client_key', 'client_secret' );
                break;
        }

        $configured = true;
        foreach ( $required_fields as $field ) {
            if ( empty( $platform_settings[$field] ) ) {
                $configured = false;
                break;
            }
        }

        if ( ! $configured ) {
            return array(
                'status' => 'not-configured',
                'message' => __( 'App credentials not configured', 'trello-social-auto-publisher' )
            );
        }

        // Check if there are any connected accounts
        $connected_clients = get_posts( array(
            'post_type' => 'tts_client',
            'meta_query' => array(
                array(
                    'key' => '_tts_' . substr( $platform, 0, 2 ) . '_token',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids'
        ) );

        if ( ! empty( $connected_clients ) ) {
            return array(
                'status' => 'connected',
                'message' => sprintf( __( '%d account(s) connected', 'trello-social-auto-publisher' ), count( $connected_clients ) )
            );
        }

        return array(
            'status' => 'configured',
            'message' => __( 'Ready to connect accounts', 'trello-social-auto-publisher' )
        );
    }

    /**
     * Generate OAuth URL for a platform.
     *
     * @param string $platform Platform name.
     * @return string OAuth URL.
     */
    private function get_oauth_url( $platform ) {
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
        $redirect_uri = admin_url( 'admin-post.php?action=tts_oauth_' . $platform );
        $state = wp_generate_password( 20, false );
        
        // Store state for verification
        if ( ! session_id() ) {
            session_start();
        }
        $_SESSION['tts_oauth_state'] = $state;

        switch ( $platform ) {
            case 'facebook':
                if ( ! empty( $platform_settings['app_id'] ) ) {
                    return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query( array(
                        'client_id' => $platform_settings['app_id'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list',
                        'state' => $state,
                        'response_type' => 'code'
                    ) );
                }
                break;
            case 'instagram':
                if ( ! empty( $platform_settings['app_id'] ) ) {
                    return 'https://api.instagram.com/oauth/authorize?' . http_build_query( array(
                        'client_id' => $platform_settings['app_id'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'user_profile,user_media',
                        'state' => $state,
                        'response_type' => 'code'
                    ) );
                }
                break;
            case 'youtube':
                if ( ! empty( $platform_settings['client_id'] ) ) {
                    return 'https://accounts.google.com/o/oauth2/auth?' . http_build_query( array(
                        'client_id' => $platform_settings['client_id'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'https://www.googleapis.com/auth/youtube.upload',
                        'state' => $state,
                        'response_type' => 'code',
                        'access_type' => 'offline'
                    ) );
                }
                break;
            case 'tiktok':
                if ( ! empty( $platform_settings['client_key'] ) ) {
                    return 'https://www.tiktok.com/auth/authorize/?' . http_build_query( array(
                        'client_key' => $platform_settings['client_key'],
                        'redirect_uri' => $redirect_uri,
                        'scope' => 'user.info.basic,video.upload',
                        'state' => $state,
                        'response_type' => 'code'
                    ) );
                }
                break;
        }

        return '#';
    }

    /**
     * Render the help and setup page.
     */
    public function render_help_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Help & Setup Guide', 'trello-social-auto-publisher' ); ?></h1>
            
            <div class="tts-help-container">
                <div class="tts-help-sidebar">
                    <h3><?php esc_html_e( 'Quick Links', 'trello-social-auto-publisher' ); ?></h3>
                    <ul>
                        <li><a href="#overview"><?php esc_html_e( 'Overview', 'trello-social-auto-publisher' ); ?></a></li>
                        <li><a href="#facebook"><?php esc_html_e( 'Facebook Setup', 'trello-social-auto-publisher' ); ?></a></li>
                        <li><a href="#instagram"><?php esc_html_e( 'Instagram Setup', 'trello-social-auto-publisher' ); ?></a></li>
                        <li><a href="#youtube"><?php esc_html_e( 'YouTube Setup', 'trello-social-auto-publisher' ); ?></a></li>
                        <li><a href="#tiktok"><?php esc_html_e( 'TikTok Setup', 'trello-social-auto-publisher' ); ?></a></li>
                        <li><a href="#troubleshooting"><?php esc_html_e( 'Troubleshooting', 'trello-social-auto-publisher' ); ?></a></li>
                    </ul>
                    
                    <div class="tts-help-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tts-social-connections' ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Configure Social Apps', 'trello-social-auto-publisher' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tts-client-wizard' ) ); ?>" class="button">
                            <?php esc_html_e( 'Create Client', 'trello-social-auto-publisher' ); ?>
                        </a>
                    </div>
                </div>
                
                <div class="tts-help-content">
                    <section id="overview">
                        <h2><?php esc_html_e( '🚀 Getting Started', 'trello-social-auto-publisher' ); ?></h2>
                        <p><?php esc_html_e( 'To use the Social Auto Publisher, you need to:', 'trello-social-auto-publisher' ); ?></p>
                        <ol>
                            <li><strong><?php esc_html_e( 'Create developer apps', 'trello-social-auto-publisher' ); ?></strong> <?php esc_html_e( 'on each social media platform', 'trello-social-auto-publisher' ); ?></li>
                            <li><strong><?php esc_html_e( 'Configure OAuth credentials', 'trello-social-auto-publisher' ); ?></strong> <?php esc_html_e( 'in Social Connections', 'trello-social-auto-publisher' ); ?></li>
                            <li><strong><?php esc_html_e( 'Connect your accounts', 'trello-social-auto-publisher' ); ?></strong> <?php esc_html_e( 'using the OAuth flow', 'trello-social-auto-publisher' ); ?></li>
                            <li><strong><?php esc_html_e( 'Create clients', 'trello-social-auto-publisher' ); ?></strong> <?php esc_html_e( 'and assign social accounts', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                        
                        <div class="tts-notice-warning">
                            <p><strong><?php esc_html_e( 'Important:', 'trello-social-auto-publisher' ); ?></strong> <?php esc_html_e( 'Each social media platform requires you to create a developer application. This is a one-time setup per platform.', 'trello-social-auto-publisher' ); ?></p>
                        </div>
                    </section>

                    <section id="facebook">
                        <h2><?php esc_html_e( '📘 Facebook Setup', 'trello-social-auto-publisher' ); ?></h2>
                        <h3><?php esc_html_e( 'Step 1: Create Facebook App', 'trello-social-auto-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Visit', 'trello-social-auto-publisher' ); ?> <a href="https://developers.facebook.com/apps/" target="_blank">Facebook Developers</a></li>
                            <li><?php esc_html_e( 'Click "Create App" → "Business" → "Consumer"', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Enter app name and contact email', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Add "Facebook Login" product', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                        
                        <h3><?php esc_html_e( 'Step 2: Configure OAuth Settings', 'trello-social-auto-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Go to Facebook Login → Settings', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Add redirect URI:', 'trello-social-auto-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_facebook' ) ); ?></code></li>
                            <li><?php esc_html_e( 'Enable "Use Strict Mode for Redirect URIs"', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                        
                        <h3><?php esc_html_e( 'Step 3: Get Credentials', 'trello-social-auto-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Go to Settings → Basic', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Copy App ID and App Secret', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Enter these in Social Connections page', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                    </section>

                    <section id="instagram">
                        <h2><?php esc_html_e( '📷 Instagram Setup', 'trello-social-auto-publisher' ); ?></h2>
                        <p><?php esc_html_e( 'Instagram uses the same Facebook app with additional configuration:', 'trello-social-auto-publisher' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'In your Facebook app, add "Instagram Basic Display" product', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Configure redirect URI:', 'trello-social-auto-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_instagram' ) ); ?></code></li>
                            <li><?php esc_html_e( 'Add your Instagram account as a test user', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Use the same App ID and App Secret from Facebook', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                    </section>

                    <section id="youtube">
                        <h2><?php esc_html_e( '🎥 YouTube Setup', 'trello-social-auto-publisher' ); ?></h2>
                        <h3><?php esc_html_e( 'Step 1: Create Google Project', 'trello-social-auto-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Visit', 'trello-social-auto-publisher' ); ?> <a href="https://console.developers.google.com/" target="_blank">Google Developers Console</a></li>
                            <li><?php esc_html_e( 'Create a new project or select existing one', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Enable "YouTube Data API v3"', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                        
                        <h3><?php esc_html_e( 'Step 2: Create OAuth Credentials', 'trello-social-auto-publisher' ); ?></h3>
                        <ol>
                            <li><?php esc_html_e( 'Go to Credentials → Create Credentials → OAuth 2.0 Client IDs', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Choose "Web application"', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Add redirect URI:', 'trello-social-auto-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_youtube' ) ); ?></code></li>
                        </ol>
                    </section>

                    <section id="tiktok">
                        <h2><?php esc_html_e( '🎵 TikTok Setup', 'trello-social-auto-publisher' ); ?></h2>
                        <div class="tts-notice-warning">
                            <p><strong><?php esc_html_e( 'Note:', 'trello-social-auto-publisher' ); ?></strong> <?php esc_html_e( 'TikTok requires developer account approval, which can take several days.', 'trello-social-auto-publisher' ); ?></p>
                        </div>
                        <ol>
                            <li><?php esc_html_e( 'Visit', 'trello-social-auto-publisher' ); ?> <a href="https://developers.tiktok.com/" target="_blank">TikTok Developers</a></li>
                            <li><?php esc_html_e( 'Apply for developer access', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Create a new app in the developer portal', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Configure redirect URI:', 'trello-social-auto-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php?action=tts_oauth_tiktok' ) ); ?></code></li>
                        </ol>
                    </section>

                    <section id="troubleshooting">
                        <h2><?php esc_html_e( '🔧 Troubleshooting Guide', 'trello-social-auto-publisher' ); ?></h2>
                        
                        <h3><?php esc_html_e( 'Common Issues and Solutions', 'trello-social-auto-publisher' ); ?></h3>
                        
                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '❌ "OAuth verification failed" Error', 'trello-social-auto-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Causes:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e( 'Incorrect redirect URI in app settings', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'App ID/Secret mismatch', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Session issues', 'trello-social-auto-publisher' ); ?></li>
                            </ul>
                            <p><strong><?php esc_html_e( 'Solutions:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Verify redirect URI matches exactly:', 'trello-social-auto-publisher' ); ?> <code><?php echo esc_url( admin_url( 'admin-post.php' ) ); ?></code></li>
                                <li><?php esc_html_e( 'Double-check App ID and App Secret', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Clear browser cache and cookies', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Try the connection in an incognito/private window', 'trello-social-auto-publisher' ); ?></li>
                            </ol>
                        </div>
                        
                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '🔑 "Failed to obtain access token" Error', 'trello-social-auto-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Causes:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e( 'Invalid app credentials', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'App not approved/active', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Insufficient permissions granted', 'trello-social-auto-publisher' ); ?></li>
                            </ul>
                            <p><strong><?php esc_html_e( 'Solutions:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Verify app is in "Live" mode (not development)', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Check that required permissions are granted during OAuth', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Use the "Test Connection" button to validate credentials', 'trello-social-auto-publisher' ); ?></li>
                            </ol>
                        </div>
                        
                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '⚠️ Rate Limiting Issues', 'trello-social-auto-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Symptoms:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ul>
                                <li><?php esc_html_e( 'Posts failing to publish', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( '"Rate limit exceeded" errors in logs', 'trello-social-auto-publisher' ); ?></li>
                            </ul>
                            <p><strong><?php esc_html_e( 'Solutions:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Use "Check API Limits" button to monitor usage', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Reduce posting frequency in high-volume periods', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Consider upgrading to business/developer API tiers', 'trello-social-auto-publisher' ); ?></li>
                            </ol>
                        </div>

                        <div class="tts-troubleshoot-item">
                            <h4><?php esc_html_e( '🔧 Performance Issues', 'trello-social-auto-publisher' ); ?></h4>
                            <p><strong><?php esc_html_e( 'Solutions:', 'trello-social-auto-publisher' ); ?></strong></p>
                            <ol>
                                <li><?php esc_html_e( 'Enable WordPress object caching', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Limit number of concurrent social posts', 'trello-social-auto-publisher' ); ?></li>
                                <li><?php esc_html_e( 'Monitor system performance in Dashboard', 'trello-social-auto-publisher' ); ?></li>
                            </ol>
                        </div>
                        
                        <h3><?php esc_html_e( 'Getting Support', 'trello-social-auto-publisher' ); ?></h3>
                        <p><?php esc_html_e( 'If you continue experiencing issues:', 'trello-social-auto-publisher' ); ?></p>
                        <ol>
                            <li><?php esc_html_e( 'Check the plugin logs for detailed error messages', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Use the "Test Connection" feature to isolate the problem', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Document the exact error message and steps to reproduce', 'trello-social-auto-publisher' ); ?></li>
                            <li><?php esc_html_e( 'Contact support with your findings', 'trello-social-auto-publisher' ); ?></li>
                        </ol>
                    </section>
                </div>
            </div>
        </div>
        
        <style>
        .tts-help-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .tts-help-sidebar {
            flex: 0 0 250px;
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            height: fit-content;
            position: sticky;
            top: 32px;
        }
        .tts-help-content {
            flex: 1;
            background: #fff;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .tts-help-sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .tts-help-sidebar li {
            margin-bottom: 8px;
        }
        .tts-help-sidebar a {
            text-decoration: none;
            padding: 8px 12px;
            display: block;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .tts-help-sidebar a:hover {
            background-color: #f0f0f1;
        }
        .tts-help-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .tts-help-actions .button {
            display: block;
            margin-bottom: 10px;
            text-align: center;
        }
        .tts-help-content section {
            margin-bottom: 40px;
        }
        .tts-help-content h2 {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .tts-help-content h3 {
            color: #0073aa;
            margin-top: 25px;
        }
        .tts-help-content code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
            word-break: break-all;
        }
        .tts-notice-warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
        }
        .tts-help-content dt {
            margin-top: 15px;
        }
        .tts-help-content dd {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        .tts-troubleshoot-item {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .tts-troubleshoot-item h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .tts-troubleshoot-item ul, .tts-troubleshoot-item ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .tts-troubleshoot-item li {
            margin: 5px 0;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler for testing social media connections.
     */
    public function ajax_test_connection() {
        check_ajax_referer( 'tts_test_connection', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'trello-social-auto-publisher' ) );
        }
        
        $platform = sanitize_key( $_POST['platform'] );
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$platform] ) ? $settings[$platform] : array();
        
        $result = $this->test_platform_connection( $platform, $platform_settings );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 'message' => $result['message'] ) );
        } else {
            wp_send_json_error( array( 'message' => $result['message'] ) );
        }
    }
    
    /**
     * AJAX handler for checking API rate limits.
     */
    public function ajax_check_rate_limits() {
        check_ajax_referer( 'tts_check_rate_limits', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'trello-social-auto-publisher' ) );
        }
        
        $platform = sanitize_key( $_POST['platform'] );
        $limits = $this->get_platform_rate_limits( $platform );
        
        wp_send_json_success( $limits );
    }
    
    /**
     * Test platform connection.
     *
     * @param string $platform Platform name.
     * @param array  $settings Platform settings.
     * @return array Test result.
     */
    private function test_platform_connection( $platform, $settings ) {
        switch ( $platform ) {
            case 'facebook':
                if ( empty( $settings['app_id'] ) || empty( $settings['app_secret'] ) ) {
                    return array( 'success' => false, 'message' => __( 'App credentials not configured', 'trello-social-auto-publisher' ) );
                }
                
                $response = wp_remote_get( 'https://graph.facebook.com/v18.0/oauth/access_token?' . http_build_query( array(
                    'client_id' => $settings['app_id'],
                    'client_secret' => $settings['app_secret'],
                    'grant_type' => 'client_credentials'
                ) ) );
                
                if ( is_wp_error( $response ) ) {
                    return array( 'success' => false, 'message' => __( 'Connection failed: ', 'trello-social-auto-publisher' ) . $response->get_error_message() );
                }
                
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['access_token'] ) ) {
                    return array( 'success' => true, 'message' => __( 'Facebook app credentials valid', 'trello-social-auto-publisher' ) );
                } else {
                    return array( 'success' => false, 'message' => __( 'Invalid Facebook app credentials', 'trello-social-auto-publisher' ) );
                }
                
            case 'youtube':
                if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
                    return array( 'success' => false, 'message' => __( 'Client credentials not configured', 'trello-social-auto-publisher' ) );
                }
                
                return array( 'success' => true, 'message' => __( 'YouTube client credentials format valid', 'trello-social-auto-publisher' ) );
                
            default:
                return array( 'success' => true, 'message' => __( 'Platform configuration appears valid', 'trello-social-auto-publisher' ) );
        }
    }
    
    /**
     * Get platform rate limits.
     *
     * @param string $platform Platform name.
     * @return array Rate limit information.
     */
    private function get_platform_rate_limits( $platform ) {
        $settings = get_option( 'tts_settings', array() );
        
        // Check if we have cached rate limit data (updated every 15 minutes)
        $cache_key = "tts_rate_limits_{$platform}";
        $cached_limits = get_transient( $cache_key );
        
        if ( $cached_limits !== false ) {
            return $cached_limits;
        }
        
        $limits = array( 'used' => 0, 'limit' => 100, 'remaining' => 100, 'reset_time' => 'Unknown' );
        
        switch ( $platform ) {
            case 'facebook':
                $limits = $this->get_facebook_rate_limits( $settings );
                break;
                
            case 'instagram':
                $limits = $this->get_instagram_rate_limits( $settings );
                break;
                
            case 'youtube':
                $limits = $this->get_youtube_rate_limits( $settings );
                break;
                
            case 'tiktok':
                $limits = $this->get_tiktok_rate_limits( $settings );
                break;
        }
        
        // Cache the rate limits for 15 minutes
        set_transient( $cache_key, $limits, 15 * MINUTE_IN_SECONDS );
        
        return $limits;
    }
    
    /**
     * Get Facebook API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_facebook_rate_limits( $settings ) {
        $access_token = $settings['facebook_access_token'] ?? '';
        
        if ( empty( $access_token ) ) {
            return array( 'used' => 0, 'limit' => 200, 'remaining' => 200, 'reset_time' => 'No token configured' );
        }
        
        $response = wp_remote_get( 'https://graph.facebook.com/me?access_token=' . $access_token, array(
            'timeout' => 10
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array( 'used' => 0, 'limit' => 200, 'remaining' => 200, 'reset_time' => 'API Error' );
        }
        
        $headers = wp_remote_retrieve_headers( $response );
        
        return array(
            'used' => intval( $headers['X-App-Usage'] ?? 0 ),
            'limit' => 200, // Facebook default
            'remaining' => 200 - intval( $headers['X-App-Usage'] ?? 0 ),
            'reset_time' => $headers['X-App-Usage-Reset-Time'] ?? '1 hour'
        );
    }
    
    /**
     * Get Instagram API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_instagram_rate_limits( $settings ) {
        $access_token = $settings['instagram_access_token'] ?? '';
        
        if ( empty( $access_token ) ) {
            return array( 'used' => 0, 'limit' => 100, 'remaining' => 100, 'reset_time' => 'No token configured' );
        }
        
        // Instagram Basic Display API limits
        $response = wp_remote_get( 'https://graph.instagram.com/me?access_token=' . $access_token, array(
            'timeout' => 10
        ) );
        
        if ( is_wp_error( $response ) ) {
            return array( 'used' => 0, 'limit' => 100, 'remaining' => 100, 'reset_time' => 'API Error' );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $headers = wp_remote_retrieve_headers( $response );
        
        if ( $response_code === 429 ) {
            return array( 'used' => 100, 'limit' => 100, 'remaining' => 0, 'reset_time' => 'Rate limited' );
        }
        
        return array(
            'used' => intval( $headers['X-RateLimit-Used'] ?? 0 ),
            'limit' => intval( $headers['X-RateLimit-Limit'] ?? 100 ),
            'remaining' => intval( $headers['X-RateLimit-Remaining'] ?? 100 ),
            'reset_time' => $headers['X-RateLimit-Reset'] ?? '1 hour'
        );
    }
    
    /**
     * Get YouTube API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_youtube_rate_limits( $settings ) {
        $api_key = $settings['youtube_api_key'] ?? '';
        
        if ( empty( $api_key ) ) {
            return array( 'used' => 0, 'limit' => 10000, 'remaining' => 10000, 'reset_time' => 'No API key configured' );
        }
        
        // YouTube Data API quota information isn't directly available via API
        // We track usage internally
        $daily_usage = get_option( 'tts_youtube_daily_usage', 0 );
        $daily_limit = 10000; // YouTube default quota
        
        return array(
            'used' => $daily_usage,
            'limit' => $daily_limit,
            'remaining' => max( 0, $daily_limit - $daily_usage ),
            'reset_time' => 'Daily at midnight PST'
        );
    }
    
    /**
     * Get TikTok API rate limits.
     *
     * @param array $settings Plugin settings.
     * @return array Rate limit data.
     */
    private function get_tiktok_rate_limits( $settings ) {
        $access_token = $settings['tiktok_access_token'] ?? '';
        
        if ( empty( $access_token ) ) {
            return array( 'used' => 0, 'limit' => 50, 'remaining' => 50, 'reset_time' => 'No token configured' );
        }
        
        // TikTok for Business API has limited public rate limit endpoints
        // We track usage internally based on API calls
        $hourly_usage = get_transient( 'tts_tiktok_hourly_usage' ) ?: 0;
        $hourly_limit = 50; // Typical TikTok limit
        
        return array(
            'used' => $hourly_usage,
            'limit' => $hourly_limit,
            'remaining' => max( 0, $hourly_limit - $hourly_usage ),
            'reset_time' => 'Hourly'
        );
    }
    
    /**
     * AJAX handler for data export.
     */
    public function ajax_export_data() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        $export_options = array(
            'settings' => isset( $_POST['export_settings'] ) && $_POST['export_settings'] === 'true',
            'social_apps' => isset( $_POST['export_social_apps'] ) && $_POST['export_social_apps'] === 'true',
            'clients' => isset( $_POST['export_clients'] ) && $_POST['export_clients'] === 'true',
            'posts' => isset( $_POST['export_posts'] ) && $_POST['export_posts'] === 'true',
            'logs' => isset( $_POST['export_logs'] ) && $_POST['export_logs'] === 'true',
            'analytics' => isset( $_POST['export_analytics'] ) && $_POST['export_analytics'] === 'true'
        );
        
        $result = TTS_Advanced_Utils::export_data( $export_options );
        
        if ( $result['success'] ) {
            // Create download file
            $filename = 'tts-export-' . date( 'Y-m-d-H-i-s' ) . '.json';
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;
            
            file_put_contents( $file_path, json_encode( $result['data'], JSON_PRETTY_PRINT ) );
            
            wp_send_json_success( array( 
                'message' => __( 'Export completed successfully', 'trello-social-auto-publisher' ),
                'download_url' => $upload_dir['url'] . '/' . $filename,
                'file_size' => $result['file_size']
            ) );
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
    }
    
    /**
     * AJAX handler for data import.
     */
    public function ajax_import_data() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        if ( ! isset( $_FILES['import_file'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No file provided', 'trello-social-auto-publisher' ) ) );
        }
        
        $file = $_FILES['import_file'];
        $import_data = json_decode( file_get_contents( $file['tmp_name'] ), true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( array( 'message' => __( 'Invalid JSON file', 'trello-social-auto-publisher' ) ) );
        }
        
        $import_options = array(
            'overwrite_settings' => isset( $_POST['overwrite_settings'] ) && $_POST['overwrite_settings'] === 'true',
            'overwrite_social_apps' => isset( $_POST['overwrite_social_apps'] ) && $_POST['overwrite_social_apps'] === 'true',
            'import_clients' => isset( $_POST['import_clients'] ) && $_POST['import_clients'] === 'true',
            'import_posts' => isset( $_POST['import_posts'] ) && $_POST['import_posts'] === 'true'
        );
        
        $result = TTS_Advanced_Utils::import_data( $import_data, $import_options );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 
                'message' => __( 'Import completed successfully', 'trello-social-auto-publisher' ),
                'log' => $result['log']
            ) );
        } else {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }
    }
    
    /**
     * AJAX handler for system maintenance.
     */
    public function ajax_system_maintenance() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        $tasks = array(
            'optimize_database' => isset( $_POST['optimize_database'] ) && $_POST['optimize_database'] === 'true',
            'clear_cache' => isset( $_POST['clear_cache'] ) && $_POST['clear_cache'] === 'true',
            'cleanup_logs' => isset( $_POST['cleanup_logs'] ) && $_POST['cleanup_logs'] === 'true',
            'update_statistics' => isset( $_POST['update_statistics'] ) && $_POST['update_statistics'] === 'true',
            'check_health' => isset( $_POST['check_health'] ) && $_POST['check_health'] === 'true'
        );
        
        $result = TTS_Advanced_Utils::system_maintenance( $tasks );
        
        if ( $result['success'] ) {
            wp_send_json_success( array( 
                'message' => __( 'System maintenance completed', 'trello-social-auto-publisher' ),
                'log' => $result['log']
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Maintenance failed', 'trello-social-auto-publisher' ) ) );
        }
    }
    
    /**
     * AJAX handler for system report generation.
     */
    public function ajax_generate_report() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        $report = TTS_Advanced_Utils::generate_system_report();
        
        wp_send_json_success( array( 
            'message' => __( 'System report generated', 'trello-social-auto-publisher' ),
            'report' => $report
        ) );
    }
    
    /**
     * AJAX handler for quick connection check.
     */
    public function ajax_quick_connection_check() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        $platform = sanitize_text_field( $_POST['platform'] );
        $social_apps = get_option( 'tts_social_apps', array() );
        
        if ( ! isset( $social_apps[$platform] ) ) {
            wp_send_json_success( array( 'status' => 'not_configured' ) );
            return;
        }
        
        $settings = $social_apps[$platform];
        $required_fields = $this->get_required_platform_fields( $platform );
        
        foreach ( $required_fields as $field ) {
            if ( empty( $settings[$field] ) ) {
                wp_send_json_success( array( 'status' => 'not_configured' ) );
                return;
            }
        }
        
        // Quick validation - just check if credentials are present
        wp_send_json_success( array( 'status' => 'configured' ) );
    }
    
    /**
     * Get required fields for platform.
     *
     * @param string $platform Platform name.
     * @return array Required fields.
     */
    private function get_required_platform_fields( $platform ) {
        $fields = array(
            'facebook' => array( 'app_id', 'app_secret' ),
            'instagram' => array( 'app_id', 'app_secret' ),
            'youtube' => array( 'client_id', 'client_secret' ),
            'tiktok' => array( 'client_key', 'client_secret' )
        );
        
        return isset( $fields[$platform] ) ? $fields[$platform] : array();
    }
    
    /**
     * AJAX handler for health check refresh.
     */
    public function ajax_refresh_health() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        // Perform fresh health check
        $health_data = TTS_Monitoring::perform_health_check();
        
        wp_send_json_success( array( 
            'message' => __( 'Health check completed', 'trello-social-auto-publisher' ),
            'health_data' => $health_data
        ) );
    }
    
    /**
     * AJAX handler for showing export modal.
     */
    public function ajax_show_export_modal() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        ob_start();
        ?>
        <div class="tts-modal-content">
            <h2><?php esc_html_e( 'Export Data', 'trello-social-auto-publisher' ); ?></h2>
            <form id="tts-export-form">
                <div class="tts-export-options">
                    <label>
                        <input type="checkbox" name="export_settings" checked>
                        <?php esc_html_e( 'Plugin Settings', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_social_apps" checked>
                        <?php esc_html_e( 'Social Media Configurations', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_clients" checked>
                        <?php esc_html_e( 'Clients', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_posts">
                        <?php esc_html_e( 'Social Posts (last 100)', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_logs">
                        <?php esc_html_e( 'Recent Logs (last 30 days)', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="export_analytics">
                        <?php esc_html_e( 'Analytics Data', 'trello-social-auto-publisher' ); ?>
                    </label>
                </div>
                <div class="tts-modal-actions">
                    <button type="submit" class="tts-btn primary">
                        <?php esc_html_e( 'Export', 'trello-social-auto-publisher' ); ?>
                    </button>
                    <button type="button" class="tts-btn secondary tts-modal-close">
                        <?php esc_html_e( 'Cancel', 'trello-social-auto-publisher' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        $modal_html = ob_get_clean();
        
        wp_send_json_success( array( 
            'modal_html' => $modal_html
        ) );
    }
    
    /**
     * AJAX handler for showing import modal.
     */
    public function ajax_show_import_modal() {
        if ( ! wp_verify_nonce( $_POST['nonce'], 'tts_ajax_nonce' ) ) {
            wp_die( 'Security check failed' );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions', 'trello-social-auto-publisher' ) ) );
        }
        
        ob_start();
        ?>
        <div class="tts-modal-content">
            <h2><?php esc_html_e( 'Import Data', 'trello-social-auto-publisher' ); ?></h2>
            <form id="tts-import-form" enctype="multipart/form-data">
                <div class="tts-import-file">
                    <label for="import_file">
                        <?php esc_html_e( 'Select Export File:', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <input type="file" id="import_file" name="import_file" accept=".json" required>
                </div>
                
                <div class="tts-import-options">
                    <h4><?php esc_html_e( 'Import Options:', 'trello-social-auto-publisher' ); ?></h4>
                    <label>
                        <input type="checkbox" name="overwrite_settings">
                        <?php esc_html_e( 'Overwrite existing settings', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="overwrite_social_apps">
                        <?php esc_html_e( 'Overwrite social media configurations', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="import_clients" checked>
                        <?php esc_html_e( 'Import clients', 'trello-social-auto-publisher' ); ?>
                    </label>
                    <label>
                        <input type="checkbox" name="import_posts">
                        <?php esc_html_e( 'Import social posts (as drafts)', 'trello-social-auto-publisher' ); ?>
                    </label>
                </div>
                
                <div class="tts-modal-actions">
                    <button type="submit" class="tts-btn primary">
                        <?php esc_html_e( 'Import', 'trello-social-auto-publisher' ); ?>
                    </button>
                    <button type="button" class="tts-btn secondary tts-modal-close">
                        <?php esc_html_e( 'Cancel', 'trello-social-auto-publisher' ); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php
        $modal_html = ob_get_clean();
        
        wp_send_json_success( array( 
            'modal_html' => $modal_html
        ) );
    }
}

new TTS_Admin();
