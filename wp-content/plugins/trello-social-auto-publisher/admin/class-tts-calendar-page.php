<?php
/**
 * Admin page to display monthly calendar of scheduled posts.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Calendar page controller.
 */
class TTS_Calendar_Page {

    /**
     * Hook into admin_menu.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Register the calendar menu page.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Calendario', 'trello-social-auto-publisher' ),
            __( 'Calendario', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-calendar',
            array( $this, 'render_page' ),
            'dashicons-calendar'
        );
    }

    /**
     * Render the calendar page.
     */
    public function render_page() {
        $month_param = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );
        $timestamp   = strtotime( $month_param . '-01' );
        if ( false === $timestamp ) {
            $timestamp = current_time( 'timestamp' );
        }

        $month_start = date( 'Y-m-01 00:00:00', $timestamp );
        $month_end   = date( 'Y-m-t 23:59:59', $timestamp );

        $posts = get_posts(
            array(
                'post_type'      => 'tts_social_post',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'meta_key'       => '_tts_publish_at',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_tts_publish_at',
                        'value'   => $month_start,
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => '_tts_publish_at',
                        'value'   => $month_end,
                        'compare' => '<=',
                        'type'    => 'DATETIME',
                    ),
                    array(
                        'key'     => '_tts_publish_at',
                        'value'   => current_time( 'mysql' ),
                        'compare' => '>=',
                        'type'    => 'DATETIME',
                    ),
                ),
            )
        );

        $posts_by_day = array();
        foreach ( $posts as $post ) {
            $publish_at = get_post_meta( $post->ID, '_tts_publish_at', true );
            $day_key    = date( 'Y-m-d', strtotime( $publish_at ) );
            if ( ! isset( $posts_by_day[ $day_key ] ) ) {
                $posts_by_day[ $day_key ] = array();
            }
            $posts_by_day[ $day_key ][] = $post;
        }

        $days_in_month  = (int) date( 't', $timestamp );
        $first_weekday  = (int) date( 'N', $timestamp ); // 1 (Mon) - 7 (Sun).
        $prev_month     = date( 'Y-m', strtotime( '-1 month', $timestamp ) );
        $next_month     = date( 'Y-m', strtotime( '+1 month', $timestamp ) );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Calendario', 'trello-social-auto-publisher' ) . '</h1>';
        echo '<div class="tts-calendar-nav">';
        echo '<a href="#" data-month="' . esc_attr( $prev_month ) . '">&laquo;</a> ';
        echo esc_html( date_i18n( 'F Y', $timestamp ) ) . ' ';
        echo '<a href="#" data-month="' . esc_attr( $next_month ) . '">&raquo;</a>';
        echo '</div>';

        $weekdays = array(
            __( 'Lun', 'trello-social-auto-publisher' ),
            __( 'Mar', 'trello-social-auto-publisher' ),
            __( 'Mer', 'trello-social-auto-publisher' ),
            __( 'Gio', 'trello-social-auto-publisher' ),
            __( 'Ven', 'trello-social-auto-publisher' ),
            __( 'Sab', 'trello-social-auto-publisher' ),
            __( 'Dom', 'trello-social-auto-publisher' ),
        );

        echo '<table class="widefat fixed tts-calendar">';
        echo '<thead><tr>';
        foreach ( $weekdays as $wd ) {
            echo '<th>' . esc_html( $wd ) . '</th>';
        }
        echo '</tr></thead><tbody><tr>';

        $current_cell = 1;
        for ( $i = 1; $i < $first_weekday; $i++ ) {
            echo '<td class="empty">&nbsp;</td>';
            $current_cell++;
        }

        for ( $day = 1; $day <= $days_in_month; $day++, $current_cell++ ) {
            $date_key = date( 'Y-m-d', strtotime( $month_param . '-' . $day ) );
            echo '<td class="tts-day">';
            echo '<div class="day-number">' . esc_html( $day ) . '</div>';
            if ( isset( $posts_by_day[ $date_key ] ) ) {
                foreach ( $posts_by_day[ $date_key ] as $post ) {
                    $channels  = get_post_meta( $post->ID, '_tts_social_channel', true );
                    $edit_link = get_edit_post_link( $post->ID );
                    echo '<div class="tts-calendar-entry">';
                    echo '<strong>' . esc_html( $post->post_title ) . '</strong><br />';
                    echo esc_html( is_array( $channels ) ? implode( ', ', $channels ) : $channels );
                    echo ' - <a href="' . esc_url( $edit_link ) . '">' . esc_html__( 'Modifica', 'trello-social-auto-publisher' ) . '</a>';
                    echo '</div>';
                }
            }
            echo '</td>';

            if ( $current_cell % 7 === 1 && $day !== $days_in_month ) {
                echo '</tr><tr>';
            }
        }

        $remaining = ( $current_cell - 1 ) % 7;
        if ( 0 !== $remaining ) {
            for ( $i = $remaining; $i < 7; $i++ ) {
                echo '<td class="empty">&nbsp;</td>';
            }
        }

        echo '</tr></tbody></table>';
        echo '</div>';
    }
}
