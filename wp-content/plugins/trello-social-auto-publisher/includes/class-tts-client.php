<?php
/**
 * Client management for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the client custom post type and related meta boxes.
 */
class TTS_Client {

    /**
     * Hook into WordPress actions.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes_tts_client', array( $this, 'add_credentials_metabox' ) );
        add_action( 'save_post_tts_client', array( $this, 'save_credentials_metabox' ), 10, 2 );
    }

    /**
     * Register the tts_client post type.
     */
    public function register_post_type() {
        $labels = array(
            'name'          => __( 'Clients', 'trello-social-auto-publisher' ),
            'singular_name' => __( 'Client', 'trello-social-auto-publisher' ),
            'add_new_item'  => __( 'Add New Client', 'trello-social-auto-publisher' ),
            'edit_item'     => __( 'Edit Client', 'trello-social-auto-publisher' ),
        );

        $args = array(
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'supports'        => array( 'title' ),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        );

        register_post_type( 'tts_client', $args );
    }

    /**
     * Add the credentials meta box.
     */
    public function add_credentials_metabox() {
        add_meta_box(
            'tts_client_credentials',
            __( 'Client Credentials', 'trello-social-auto-publisher' ),
            array( $this, 'render_credentials_metabox' ),
            'tts_client'
        );
    }

    /**
     * Render the credentials meta box fields.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_credentials_metabox( $post ) {
        wp_nonce_field( 'tts_client_credentials', 'tts_client_nonce' );

        $trello_key    = get_post_meta( $post->ID, '_tts_trello_key', true );
        $trello_token  = get_post_meta( $post->ID, '_tts_trello_token', true );
        $trello_secret = get_post_meta( $post->ID, '_tts_trello_secret', true );
        $board_id      = get_post_meta( $post->ID, '_tts_trello_board', true );
        $fb_token     = get_post_meta( $post->ID, '_tts_fb_token', true );
        $ig_token     = get_post_meta( $post->ID, '_tts_ig_token', true );
        $yt_token     = get_post_meta( $post->ID, '_tts_yt_token', true );
        $trello_map   = get_post_meta( $post->ID, '_tts_trello_map', true );

        if ( ! is_array( $trello_map ) ) {
            $trello_map = array();
        }

        echo '<p><label for="tts_trello_key">' . esc_html__( 'Trello API Key', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_key" name="tts_trello_key" value="' . esc_attr( $trello_key ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_trello_token">' . esc_html__( 'Trello API Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_token" name="tts_trello_token" value="' . esc_attr( $trello_token ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_trello_secret">' . esc_html__( 'Trello API Secret', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_secret" name="tts_trello_secret" value="' . esc_attr( $trello_secret ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_trello_board">' . esc_html__( 'Trello Board/List ID', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_board" name="tts_trello_board" value="' . esc_attr( $board_id ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_fb_token">' . esc_html__( 'Facebook Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_fb_token" name="tts_fb_token" value="' . esc_attr( $fb_token ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_ig_token">' . esc_html__( 'Instagram Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_ig_token" name="tts_ig_token" value="' . esc_attr( $ig_token ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_yt_token">' . esc_html__( 'YouTube Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_yt_token" name="tts_yt_token" value="' . esc_attr( $yt_token ) . '" class="widefat" /></p>';

        ?>
        <div id="tts_trello_map">
            <?php
            $index = 0;
            foreach ( $trello_map as $row ) :
                $id_list = isset( $row['idList'] ) ? esc_attr( $row['idList'] ) : '';
                $social  = isset( $row['canale_social'] ) ? esc_attr( $row['canale_social'] ) : '';
                ?>
                <p class="tts-trello-map-row">
                    <input type="text" name="tts_trello_map[<?php echo $index; ?>][idList]" value="<?php echo $id_list; ?>" placeholder="<?php esc_attr_e( 'Trello List ID', 'trello-social-auto-publisher' ); ?>" />
                    <input type="text" name="tts_trello_map[<?php echo $index; ?>][canale_social]" value="<?php echo $social; ?>" placeholder="<?php esc_attr_e( 'Canale Social', 'trello-social-auto-publisher' ); ?>" />
                </p>
                <?php
                $index++;
            endforeach;
            ?>
            <p class="tts-trello-map-row">
                <input type="text" name="tts_trello_map[<?php echo $index; ?>][idList]" placeholder="<?php esc_attr_e( 'Trello List ID', 'trello-social-auto-publisher' ); ?>" />
                <input type="text" name="tts_trello_map[<?php echo $index; ?>][canale_social]" placeholder="<?php esc_attr_e( 'Canale Social', 'trello-social-auto-publisher' ); ?>" />
            </p>
        </div>
        <p><button type="button" class="button" id="add-tts-trello-map"><?php esc_html_e( 'Add Mapping', 'trello-social-auto-publisher' ); ?></button></p>
        <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#add-tts-trello-map').on('click', function(e){
                e.preventDefault();
                var index = $('#tts_trello_map .tts-trello-map-row').length;
                var safeIndex = String(parseInt(index, 10));
                var row = '<p class="tts-trello-map-row">' +
                    '<input type="text" name="tts_trello_map[' + safeIndex + '][idList]" placeholder="<?php echo esc_js( __( 'Trello List ID', 'trello-social-auto-publisher' ) ); ?>" />' +
                    '<input type="text" name="tts_trello_map[' + safeIndex + '][canale_social]" placeholder="<?php echo esc_js( __( 'Canale Social', 'trello-social-auto-publisher' ) ); ?>" />' +
                '</p>';
                $('#tts_trello_map').append(row);
            });
        });
        </script>
        <?php
    }

    /**
     * Save credentials meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_credentials_metabox( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['tts_client_nonce'] ) || ! wp_verify_nonce( $_POST['tts_client_nonce'], 'tts_client_credentials' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = array(
            'tts_trello_key'    => '_tts_trello_key',
            'tts_trello_token'  => '_tts_trello_token',
            'tts_trello_secret' => '_tts_trello_secret',
            'tts_trello_board'  => '_tts_trello_board',
            'tts_fb_token'      => '_tts_fb_token',
            'tts_ig_token'      => '_tts_ig_token',
            'tts_yt_token'      => '_tts_yt_token',
        );

        foreach ( $fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $field ] ) );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }

        if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
            $map = array();
            foreach ( $_POST['tts_trello_map'] as $row ) {
                if ( empty( $row['idList'] ) || empty( $row['canale_social'] ) ) {
                    continue;
                }
                $map[] = array(
                    'idList'        => sanitize_text_field( $row['idList'] ),
                    'canale_social' => sanitize_text_field( $row['canale_social'] ),
                );
            }
            if ( ! empty( $map ) ) {
                update_post_meta( $post_id, '_tts_trello_map', $map );
            } else {
                delete_post_meta( $post_id, '_tts_trello_map' );
            }
        } else {
            delete_post_meta( $post_id, '_tts_trello_map' );
        }
    }
}

new TTS_Client();
