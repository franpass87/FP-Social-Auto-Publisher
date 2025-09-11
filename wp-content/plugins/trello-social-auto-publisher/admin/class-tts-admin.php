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

        // Enqueue accessibility styles (global for all TTS pages)
        wp_enqueue_style(
            'tts-accessibility',
            plugin_dir_url( __FILE__ ) . 'css/tts-accessibility.css',
            array(),
            '1.2'
        );

        // Enqueue enhanced notification system (global for all TTS pages)
        wp_enqueue_script(
            'tts-notifications',
            plugin_dir_url( __FILE__ ) . 'js/tts-notifications.js',
            array(),
            '1.2',
            true
        );

        // Enqueue admin utilities (global for all TTS pages)
        wp_enqueue_script(
            'tts-admin-utils',
            plugin_dir_url( __FILE__ ) . 'js/tts-admin-utils.js',
            array( 'tts-notifications' ),
            '1.2',
            true
        );

        // Enqueue advanced features (global for all TTS pages)
        wp_enqueue_script(
            'tts-advanced-features',
            plugin_dir_url( __FILE__ ) . 'js/tts-advanced-features.js',
            array( 'tts-notifications', 'tts-admin-utils' ),
            '1.2',
            true
        );

        // Enqueue help system (global for all TTS pages)
        wp_enqueue_script(
            'tts-help-system',
            plugin_dir_url( __FILE__ ) . 'js/tts-help-system.js',
            array( 'tts-notifications', 'tts-admin-utils' ),
            '1.2',
            true
        );

        // Dashboard-specific assets
        if ( 'toplevel_page_tts-main' === $hook ) {
            wp_enqueue_style(
                'tts-dashboard',
                plugin_dir_url( __FILE__ ) . 'css/tts-dashboard.css',
                array(),
                '1.1'
            );

            wp_enqueue_script(
                'tts-dashboard',
                plugin_dir_url( __FILE__ ) . 'js/tts-dashboard.js',
                array( 'wp-element', 'wp-components', 'wp-api-fetch', 'tts-notifications', 'tts-admin-utils', 'tts-advanced-features', 'tts-help-system' ),
                '1.1',
                true
            );
        }

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
     * Enqueue assets for the client wizard.
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
            array( 'jquery' ),
            '1.0',
            true
        );

        wp_localize_script(
            'tts-wizard',
            'ttsWizard',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tts_wizard' ),
            )
        );
    }

    /**
     * Enqueue assets for the manual media metabox.
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
            array( 'jquery', 'media-editor', 'jquery-ui-sortable' ),
            '1.0',
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

        $board = isset( $_POST['board'] ) ? sanitize_text_field( $_POST['board'] ) : '';
        $key   = isset( $_POST['key'] ) ? sanitize_text_field( $_POST['key'] ) : '';
        $token = isset( $_POST['token'] ) ? sanitize_text_field( $_POST['token'] ) : '';

        if ( empty( $board ) || empty( $key ) || empty( $token ) ) {
            wp_send_json_error();
        }

        $response = wp_remote_get(
            'https://api.trello.com/1/boards/' . rawurlencode( $board ) . '/lists?key=' . rawurlencode( $key ) . '&token=' . rawurlencode( $token ),
            array( 'timeout' => 20 )
        );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error();
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( $data );
    }

    /**
     * AJAX callback: refresh posts data for dashboard.
     */
    public function ajax_refresh_posts() {
        check_ajax_referer( 'tts_dashboard', 'nonce' );

        $posts = get_posts(array(
            'post_type' => 'tts_social_post',
            'posts_per_page' => 10,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        ));

        $formatted_posts = array();
        foreach ($posts as $post) {
            $channel = get_post_meta($post->ID, '_tts_social_channel', true);
            $status = get_post_meta($post->ID, '_published_status', true);
            $publish_at = get_post_meta($post->ID, '_tts_publish_at', true);
            
            $formatted_posts[] = array(
                'ID' => $post->ID,
                'title' => $post->post_title,
                'channel' => is_array($channel) ? $channel : array($channel),
                'status' => $status ?: 'scheduled',
                'publish_at' => $publish_at ?: $post->post_date,
                'edit_link' => get_edit_post_link($post->ID)
            );
        }

        wp_send_json_success(array(
            'posts' => $formatted_posts,
            'message' => __('Posts refreshed successfully', 'trello-social-auto-publisher'),
            'timestamp' => current_time('timestamp')
        ));
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
        
        // Quick stats cards
        $this->render_dashboard_stats();
        
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
        
        // React component container for advanced features
        echo '<div id="tts-dashboard-root"></div>';
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
        
        extract($stats); // Extract variables for use below

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
        echo '<span class="tts-stat-number">' . $scheduled_posts . '</span>';
        echo '<div class="tts-stat-trend">Awaiting publication</div>';
        echo '<span class="tts-tooltiptext">Posts scheduled for future publication</span>';
        echo '</div>';
        
        // Published Today Card with Trend
        $today_count = $published_today;
        $trend_class = $trend_percentage > 0 ? 'positive' : ($trend_percentage < 0 ? 'negative' : '');
        $trend_icon = $trend_percentage > 0 ? '↗' : ($trend_percentage < 0 ? '↘' : '→');
        
        echo '<div class="tts-stat-card tts-tooltip">';
        echo '<h3>' . esc_html__('Published Today', 'trello-social-auto-publisher') . '</h3>';
        echo '<span class="tts-stat-number">' . $today_count . '</span>';
        if ($published_yesterday > 0) {
            echo '<div class="tts-stat-trend ' . $trend_class . '">';
            echo $trend_icon . ' ' . abs($trend_percentage) . '% vs yesterday';
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
                switch ( $slug ) {
                    case 'facebook':
                        $token     = $fb_token;
                        $connected = ! empty( $fb_token );
                        break;
                    case 'instagram':
                        $token     = $ig_token;
                        $connected = ! empty( $ig_token );
                        break;
                    case 'youtube':
                        $token     = $yt_token;
                        $connected = ! empty( $yt_token );
                        break;
                    case 'tiktok':
                        $token     = $tt_token;
                        $connected = ! empty( $tt_token );
                        break;
                }

                echo '<p><label><input type="checkbox" name="channels[]" value="' . esc_attr( $slug ) . '" ' . checked( in_array( $slug, $channels, true ) || $connected, true, false ) . ' /> ' . esc_html( $label ) . '</label>';
                $url = add_query_arg( array( 'action' => 'tts_oauth_' . $slug, 'step' => 2 ), admin_url( 'admin-post.php' ) );
                echo ' <a href="' . esc_url( $url ) . '" class="button">' . esc_html__( 'Connect', 'trello-social-auto-publisher' ) . '</a>';
                if ( $connected ) {
                    echo ' ' . esc_html__( 'Connected', 'trello-social-auto-publisher' );
                }
                echo '</p>';
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
                echo '<pre>' . esc_html( print_r( $log, true ) ) . '</pre>';
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
}

new TTS_Admin();
