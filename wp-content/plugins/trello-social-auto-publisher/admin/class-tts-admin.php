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
    }

    /**
     * Register the Clienti menu page.
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
}

new TTS_Admin();
