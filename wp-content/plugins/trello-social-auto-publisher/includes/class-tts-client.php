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
        add_action( 'admin_post_tts_oauth_facebook', array( $this, 'handle_oauth_facebook' ) );
        add_action( 'admin_post_tts_oauth_instagram', array( $this, 'handle_oauth_instagram' ) );
        add_action( 'admin_post_tts_oauth_youtube', array( $this, 'handle_oauth_youtube' ) );
        add_action( 'admin_post_tts_oauth_tiktok', array( $this, 'handle_oauth_tiktok' ) );
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
        $trello_secret   = get_post_meta( $post->ID, '_tts_trello_secret', true );
        $board_ids       = get_post_meta( $post->ID, '_tts_trello_boards', true );
        $published_list  = get_post_meta( $post->ID, '_tts_trello_published_list', true );
        $fb_token        = get_post_meta( $post->ID, '_tts_fb_token', true );
        $ig_token        = get_post_meta( $post->ID, '_tts_ig_token', true );
        $yt_token        = get_post_meta( $post->ID, '_tts_yt_token', true );
        $tt_token        = get_post_meta( $post->ID, '_tts_tt_token', true );
        $fb_hashtags     = get_post_meta( $post->ID, '_tts_default_hashtags_facebook', true );
        $ig_hashtags     = get_post_meta( $post->ID, '_tts_default_hashtags_instagram', true );
        $yt_hashtags     = get_post_meta( $post->ID, '_tts_default_hashtags_youtube', true );
        $tt_hashtags     = get_post_meta( $post->ID, '_tts_default_hashtags_tiktok', true );
        $trello_map      = get_post_meta( $post->ID, '_tts_trello_map', true );

        if ( ! is_array( $trello_map ) ) {
            $trello_map = array();
        }
        if ( ! is_array( $board_ids ) ) {
            $board_ids = array();
        }

        echo '<p><label for="tts_trello_key">' . esc_html__( 'Trello API Key', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_key" name="tts_trello_key" value="' . esc_attr( $trello_key ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_trello_token">' . esc_html__( 'Trello API Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_token" name="tts_trello_token" value="' . esc_attr( $trello_token ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_trello_secret">' . esc_html__( 'Trello API Secret', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_secret" name="tts_trello_secret" value="' . esc_attr( $trello_secret ) . '" class="widefat" /></p>';

        ?>
        <div id="tts_trello_boards">
            <?php
            $index = 0;
            foreach ( $board_ids as $board ) :
                ?>
                <p class="tts-trello-board-row">
                    <input type="text" name="tts_trello_boards[<?php echo $index; ?>]" value="<?php echo esc_attr( $board ); ?>" placeholder="<?php esc_attr_e( 'Trello Board ID', 'trello-social-auto-publisher' ); ?>" />
                </p>
                <?php
                $index++;
            endforeach;
            ?>
            <p class="tts-trello-board-row">
                <input type="text" name="tts_trello_boards[<?php echo $index; ?>]" placeholder="<?php esc_attr_e( 'Trello Board ID', 'trello-social-auto-publisher' ); ?>" />
            </p>
        </div>
        <p><button type="button" class="button" id="add-tts-trello-board"><?php esc_html_e( 'Add Board', 'trello-social-auto-publisher' ); ?></button></p>
        <script type="text/javascript">
        jQuery(document).ready(function($){
            $('#add-tts-trello-board').on('click', function(e){
                e.preventDefault();
                var index = $('#tts_trello_boards .tts-trello-board-row').length;
                var safeIndex = String(parseInt(index, 10));
                var row = '<p class="tts-trello-board-row"><input type="text" name="tts_trello_boards[' + safeIndex + ']" placeholder="<?php echo esc_js( __( 'Trello Board ID', 'trello-social-auto-publisher' ) ); ?>" /></p>';
                $('#tts_trello_boards').append(row);
            });
        });
        </script>
        <?php

        echo '<p><label for="tts_trello_published_list">' . esc_html__( 'Trello Published List ID', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_trello_published_list" name="tts_trello_published_list" value="' . esc_attr( $published_list ) . '" class="widefat" /></p>';

        echo '<p><label for="tts_fb_token">' . esc_html__( 'Facebook Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_fb_token" name="tts_fb_token" value="' . esc_attr( $fb_token ) . '" class="widefat" /></p>';
        echo '<p><label for="tts_default_hashtags_facebook">' . esc_html__( 'Facebook Default Hashtags', 'trello-social-auto-publisher' ) . '</label>';
        echo '<textarea id="tts_default_hashtags_facebook" name="tts_default_hashtags_facebook" class="widefat" rows="3">' . esc_textarea( $fb_hashtags ) . '</textarea></p>';

        echo '<p><label for="tts_ig_token">' . esc_html__( 'Instagram Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_ig_token" name="tts_ig_token" value="' . esc_attr( $ig_token ) . '" class="widefat" /></p>';
        echo '<p><label for="tts_default_hashtags_instagram">' . esc_html__( 'Instagram Default Hashtags', 'trello-social-auto-publisher' ) . '</label>';
        echo '<textarea id="tts_default_hashtags_instagram" name="tts_default_hashtags_instagram" class="widefat" rows="3">' . esc_textarea( $ig_hashtags ) . '</textarea></p>';

        echo '<p><label for="tts_yt_token">' . esc_html__( 'YouTube Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_yt_token" name="tts_yt_token" value="' . esc_attr( $yt_token ) . '" class="widefat" /></p>';
        echo '<p><label for="tts_default_hashtags_youtube">' . esc_html__( 'YouTube Default Hashtags', 'trello-social-auto-publisher' ) . '</label>';
        echo '<textarea id="tts_default_hashtags_youtube" name="tts_default_hashtags_youtube" class="widefat" rows="3">' . esc_textarea( $yt_hashtags ) . '</textarea></p>';

        echo '<p><label for="tts_tt_token">' . esc_html__( 'TikTok Access Token', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="tts_tt_token" name="tts_tt_token" value="' . esc_attr( $tt_token ) . '" class="widefat" /></p>';
        echo '<p><label for="tts_default_hashtags_tiktok">' . esc_html__( 'TikTok Default Hashtags', 'trello-social-auto-publisher' ) . '</label>';
        echo '<textarea id="tts_default_hashtags_tiktok" name="tts_default_hashtags_tiktok" class="widefat" rows="3">' . esc_textarea( $tt_hashtags ) . '</textarea></p>';

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
            'tts_trello_published_list' => '_tts_trello_published_list',
            'tts_fb_token'      => '_tts_fb_token',
            'tts_ig_token'      => '_tts_ig_token',
            'tts_yt_token'      => '_tts_yt_token',
            'tts_tt_token'      => '_tts_tt_token',
        );

        foreach ( $fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }

        $hashtag_fields = array(
            'tts_default_hashtags_facebook'  => '_tts_default_hashtags_facebook',
            'tts_default_hashtags_instagram' => '_tts_default_hashtags_instagram',
            'tts_default_hashtags_youtube'   => '_tts_default_hashtags_youtube',
            'tts_default_hashtags_tiktok'    => '_tts_default_hashtags_tiktok',
        );

        foreach ( $hashtag_fields as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
            } else {
                delete_post_meta( $post_id, $meta_key );
            }
        }

        if ( isset( $_POST['tts_trello_boards'] ) && is_array( $_POST['tts_trello_boards'] ) ) {
            $boards = array();
            foreach ( wp_unslash( $_POST['tts_trello_boards'] ) as $board ) {
                $board = sanitize_text_field( $board );
                if ( '' !== $board ) {
                    $boards[] = $board;
                }
            }
            if ( ! empty( $boards ) ) {
                update_post_meta( $post_id, '_tts_trello_boards', $boards );
            } else {
                delete_post_meta( $post_id, '_tts_trello_boards' );
            }
        } else {
            delete_post_meta( $post_id, '_tts_trello_boards' );
        }

        // Cleanup old single board meta if present.
        delete_post_meta( $post_id, '_tts_trello_board' );

        if ( isset( $_POST['tts_trello_map'] ) && is_array( $_POST['tts_trello_map'] ) ) {
            $map = array();
            foreach ( wp_unslash( $_POST['tts_trello_map'] ) as $row ) {
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

    /**
     * Generic handler for OAuth callbacks.
     *
     * @param string $channel Social channel slug.
     */
    protected function handle_oauth( $channel ) {
        if ( ! session_id() ) {
            session_start();
        }

        $state   = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
        $expect  = isset( $_SESSION['tts_oauth_state'] ) ? $_SESSION['tts_oauth_state'] : '';
        $code    = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        $step    = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 2;
        $client  = isset( $_GET['client_id'] ) ? absint( $_GET['client_id'] ) : 0;

        if ( empty( $code ) || $state !== $expect ) {
            wp_die( esc_html__( 'OAuth verification failed.', 'trello-social-auto-publisher' ) );
        }

        // Exchange code for token
        $token = $this->exchange_code_for_token( $channel, $code );
        
        if ( ! $token ) {
            wp_die( esc_html__( 'Failed to obtain access token.', 'trello-social-auto-publisher' ) );
        }

        if ( $client ) {
            $meta_key = '';
            switch ( $channel ) {
                case 'facebook':
                    $meta_key = '_tts_fb_token';
                    break;
                case 'instagram':
                    $meta_key = '_tts_ig_token';
                    break;
                case 'youtube':
                    $meta_key = '_tts_yt_token';
                    break;
                case 'tiktok':
                    $meta_key = '_tts_tt_token';
                    break;
            }
            if ( $meta_key ) {
                update_post_meta( $client, $meta_key, $token );
            }
        } else {
            set_transient( 'tts_oauth_' . $channel . '_token', $token, 15 * MINUTE_IN_SECONDS );
        }

        wp_safe_redirect( add_query_arg( array( 'page' => 'tts-client-wizard', 'step' => $step ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Exchange authorization code for access token.
     *
     * @param string $channel Social platform.
     * @param string $code Authorization code.
     * @return string|false Access token or false on failure.
     */
    private function exchange_code_for_token( $channel, $code ) {
        $settings = get_option( 'tts_social_apps', array() );
        $platform_settings = isset( $settings[$channel] ) ? $settings[$channel] : array();
        $redirect_uri = admin_url( 'admin-post.php?action=tts_oauth_' . $channel );

        switch ( $channel ) {
            case 'facebook':
                if ( empty( $platform_settings['app_id'] ) || empty( $platform_settings['app_secret'] ) ) {
                    return false;
                }
                
                $response = wp_remote_post( 'https://graph.facebook.com/v18.0/oauth/access_token', array(
                    'body' => array(
                        'client_id' => $platform_settings['app_id'],
                        'client_secret' => $platform_settings['app_secret'],
                        'redirect_uri' => $redirect_uri,
                        'code' => $code
                    )
                ) );
                
                if ( is_wp_error( $response ) ) {
                    return false;
                }
                
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                return isset( $body['access_token'] ) ? $body['access_token'] : false;

            case 'instagram':
                if ( empty( $platform_settings['app_id'] ) || empty( $platform_settings['app_secret'] ) ) {
                    return false;
                }
                
                $response = wp_remote_post( 'https://api.instagram.com/oauth/access_token', array(
                    'body' => array(
                        'client_id' => $platform_settings['app_id'],
                        'client_secret' => $platform_settings['app_secret'],
                        'redirect_uri' => $redirect_uri,
                        'code' => $code,
                        'grant_type' => 'authorization_code'
                    )
                ) );
                
                if ( is_wp_error( $response ) ) {
                    return false;
                }
                
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                return isset( $body['access_token'] ) ? $body['access_token'] : false;

            case 'youtube':
                if ( empty( $platform_settings['client_id'] ) || empty( $platform_settings['client_secret'] ) ) {
                    return false;
                }
                
                $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
                    'body' => array(
                        'client_id' => $platform_settings['client_id'],
                        'client_secret' => $platform_settings['client_secret'],
                        'redirect_uri' => $redirect_uri,
                        'code' => $code,
                        'grant_type' => 'authorization_code'
                    )
                ) );
                
                if ( is_wp_error( $response ) ) {
                    return false;
                }
                
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                return isset( $body['access_token'] ) ? $body['access_token'] : false;

            case 'tiktok':
                if ( empty( $platform_settings['client_key'] ) || empty( $platform_settings['client_secret'] ) ) {
                    return false;
                }
                
                $response = wp_remote_post( 'https://open-api.tiktok.com/oauth/access_token/', array(
                    'body' => array(
                        'client_key' => $platform_settings['client_key'],
                        'client_secret' => $platform_settings['client_secret'],
                        'redirect_uri' => $redirect_uri,
                        'code' => $code,
                        'grant_type' => 'authorization_code'
                    )
                ) );
                
                if ( is_wp_error( $response ) ) {
                    return false;
                }
                
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                return isset( $body['data']['access_token'] ) ? $body['data']['access_token'] : false;
        }

        return false;
    }

    /**
     * Specific OAuth handlers.
     */
    public function handle_oauth_facebook() {
        $this->handle_oauth( 'facebook' );
    }

    public function handle_oauth_instagram() {
        $this->handle_oauth( 'instagram' );
    }

    public function handle_oauth_youtube() {
        $this->handle_oauth( 'youtube' );
    }

    public function handle_oauth_tiktok() {
        $this->handle_oauth( 'tiktok' );
    }
}

new TTS_Client();
