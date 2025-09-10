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
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_assets' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_scheduled_posts_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_widget_assets' ) );
        add_action( 'wp_ajax_tts_get_lists', array( $this, 'ajax_get_lists' ) );
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

        add_submenu_page(
            'tts-clienti',
            __( 'Client Wizard', 'trello-social-auto-publisher' ),
            __( 'Client Wizard', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-client-wizard',
            array( $this, 'tts_render_client_wizard' )
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
     * Enqueue assets for the client wizard.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_wizard_assets( $hook ) {
        if ( 'tts-clienti_page_tts-client-wizard' !== $hook ) {
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
     * Render the client creation wizard.
     */
    public function tts_render_client_wizard() {
        if ( ! session_id() ) {
            session_start();
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
            echo '<input type="hidden" name="step" value="2" />';
            echo '<p><label>' . esc_html__( 'Trello API Key', 'trello-social-auto-publisher' ) . '<br />';
            echo '<input type="text" name="trello_key" value="' . esc_attr( $trello_key ) . '" /></label></p>';
            echo '<p><label>' . esc_html__( 'Trello Token', 'trello-social-auto-publisher' ) . '<br />';
            echo '<input type="text" name="trello_token" value="' . esc_attr( $trello_token ) . '" /></label></p>';

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
     * Render social posts list page.
     */
    public function render_social_posts_page() {
        if ( ! class_exists( 'WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

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
}

new TTS_Admin();
