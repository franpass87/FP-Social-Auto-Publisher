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
        add_action( 'pre_get_posts', array( $this, 'filter_posts_by_client' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_dashboard_assets' ) );
    }

    /**
     * Register plugin menu pages.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Clienti', 'trello-social-auto-publisher' ),
            __( 'Clienti', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-clienti',
            array( $this, 'render_clients_page' ),
            'dashicons-groups'
        );

        add_menu_page(
            __( 'Social Post', 'trello-social-auto-publisher' ),
            __( 'Social Post', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-social-posts',
            array( $this, 'render_social_posts_page' ),
            'dashicons-share'
        );

        add_menu_page(
            __( 'Social Dashboard', 'trello-social-auto-publisher' ),
            __( 'Social Dashboard', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-chart-bar'
        );
    }

    /**
     * Enqueue assets for the dashboard page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_dashboard_assets( $hook ) {
        if ( 'toplevel_page_tts-dashboard' !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'tts-dashboard',
            plugin_dir_url( __FILE__ ) . 'js/tts-dashboard.js',
            array( 'wp-element', 'wp-components', 'wp-api-fetch' ),
            '1.0',
            true
        );
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Social Dashboard', 'trello-social-auto-publisher' ) . '</h1>';
        echo '<div id="tts-dashboard-root"></div>';
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
     * Render social posts list page.
     */
    public function render_social_posts_page() {
        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        // Handle publish now action.
        if ( isset( $_GET['action'], $_GET['post'] ) && 'publish' === $_GET['action'] ) {
            if ( ! current_user_can( 'manage_options' ) ) {
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
}

new TTS_Admin();
