<?php
/**
 * Admin page to display logs.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Log page controller.
 */
class TTS_Log_Page {

    /**
     * Hook into admin_menu.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
    }

    /**
     * Register the menu page.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Log', 'trello-social-auto-publisher' ),
            __( 'Log', 'trello-social-auto-publisher' ),
            'manage_options',
            'tts-log',
            array( $this, 'render_page' ),
            'dashicons-list-view'
        );
    }

    /**
     * Render the log page.
     */
    public function render_page() {
        $table = new TTS_Log_Table();
        $table->process_actions();
        $table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Log', 'trello-social-auto-publisher' ) . '</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="tts-log" />';
        $table->display();
        echo '</form>';
        echo '</div>';
    }
}

/**
 * WP_List_Table implementation for logs.
 */
class TTS_Log_Table extends WP_List_Table {

    /**
     * Retrieve table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'id'         => __( 'ID', 'trello-social-auto-publisher' ),
            'post_id'    => __( 'Post ID', 'trello-social-auto-publisher' ),
            'channel'    => __( 'Channel', 'trello-social-auto-publisher' ),
            'status'     => __( 'Status', 'trello-social-auto-publisher' ),
            'message'    => __( 'Message', 'trello-social-auto-publisher' ),
            'metrics'    => __( 'Metrics', 'trello-social-auto-publisher' ),
            'created_at' => __( 'Date', 'trello-social-auto-publisher' ),
        );
    }

    /**
     * Prepare the table items.
     */
    public function prepare_items() {
        global $wpdb;

        $table_name   = $wpdb->prefix . 'tts_logs';
        $per_page     = 20;
        $current_page = $this->get_pagenum();
        $offset       = ( $current_page - 1 ) * $per_page;

        $channel = isset( $_REQUEST['channel'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['channel'] ) ) : '';
        $status  = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

        $where   = ' WHERE 1=1';
        $params  = array();
        if ( $channel ) {
            $where   .= ' AND channel = %s';
            $params[] = $channel;
        }
        if ( $status ) {
            $where   .= ' AND status = %s';
            $params[] = $status;
        }

        $sql_count   = "SELECT COUNT(*) FROM {$table_name}{$where}";
        $total_items = $params ? $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) ) : $wpdb->get_var( $sql_count );

        $sql = "SELECT * FROM {$table_name}{$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params_for_query = $params;
        $params_for_query[] = $per_page;
        $params_for_query[] = $offset;
        $items = $wpdb->get_results( $wpdb->prepare( $sql, $params_for_query ), ARRAY_A );

        $this->items = $items;

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = array();
        $this->_column_headers = array( $columns, $hidden, $sortable );
    }

    /**
     * Render message column with actions.
     *
     * @param array $item Current item.
     *
     * @return string
     */
    public function column_message( $item ) {
        $delete_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'delete',
                    'log'    => $item['id'],
                )
            ),
            'tts_delete_log_' . $item['id']
        );

        $actions = array(
            'delete' => sprintf( '<a href="%s">%s</a>', esc_url( $delete_url ), __( 'Delete', 'trello-social-auto-publisher' ) ),
        );

        return sprintf( '%1$s %2$s', esc_html( $item['message'] ), $this->row_actions( $actions ) );
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
        if ( 'metrics' === $column_name ) {
            $metrics = get_post_meta( $item['post_id'], '_tts_metrics', true );
            $channel = isset( $item['channel'] ) ? $item['channel'] : '';
            if ( is_array( $metrics ) && isset( $metrics[ $channel ] ) ) {
                return esc_html( wp_json_encode( $metrics[ $channel ] ) );
            }
            return '';
        }

        return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
    }

    /**
     * Output filters above the table.
     *
     * @param string $which Top or bottom.
     */
    protected function extra_tablenav( $which ) {
        if ( 'top' !== $which ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'tts_logs';
        $channels   = $wpdb->get_col( "SELECT DISTINCT channel FROM {$table_name}" );
        $statuses   = $wpdb->get_col( "SELECT DISTINCT status FROM {$table_name}" );

        $current_channel = isset( $_REQUEST['channel'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['channel'] ) ) : '';
        $current_status  = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

        echo '<div class="alignleft actions">';
        echo '<select name="channel">';
        echo '<option value="">' . esc_html__( 'All Channels', 'trello-social-auto-publisher' ) . '</option>';
        foreach ( $channels as $ch ) {
            printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $ch ), selected( $ch, $current_channel, false ) );
        }
        echo '</select>';

        echo '<select name="status">';
        echo '<option value="">' . esc_html__( 'All Statuses', 'trello-social-auto-publisher' ) . '</option>';
        foreach ( $statuses as $st ) {
            printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $st ), selected( $st, $current_status, false ) );
        }
        echo '</select>';

        submit_button( __( 'Filter' ), '', 'filter_action', false );
        echo '</div>';
    }

    /**
     * Handle row actions.
     */
    public function process_actions() {
        if ( isset( $_GET['action'], $_GET['log'] ) && 'delete' === $_GET['action'] ) {
            $log_id = absint( $_GET['log'] );
            check_admin_referer( 'tts_delete_log_' . $log_id );

            global $wpdb;
            $table = $wpdb->prefix . 'tts_logs';
            $wpdb->delete( $table, array( 'id' => $log_id ), array( '%d' ) );

            wp_safe_redirect( remove_query_arg( array( 'action', 'log', '_wpnonce' ) ) );
            exit;
        }
    }
}

new TTS_Log_Page();
