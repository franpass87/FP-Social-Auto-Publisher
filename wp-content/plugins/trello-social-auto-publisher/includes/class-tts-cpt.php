<?php
/**
 * Custom post type for Trello Social Auto Publisher.
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registers the custom post type used by the plugin.
 */
class TTS_CPT {

    /**
     * Initialize hooks.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'init', array( $this, 'register_meta_fields' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_schedule_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_preview_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_channel_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_media_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_messages_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_approval_metabox' ) );
        add_action( 'add_meta_boxes_tts_social_post', array( $this, 'add_location_metabox' ) );
        add_action( 'save_post_tts_social_post', array( $this, 'save_schedule_metabox' ), 5, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_channel_metabox' ), 10, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_media_metabox' ), 15, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_messages_metabox' ), 20, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_approval_metabox' ), 1, 3 );
        add_action( 'save_post_tts_social_post', array( $this, 'save_location_metabox' ), 25, 3 );
    }

    /**
     * Register the custom post type.
     */
    public function register_post_type() {
        $args = array(
            'public'             => false,
            'publicly_queryable' => false,
            'show_ui'            => true,
            'supports'           => array( 'title', 'editor', 'custom-fields', 'thumbnail' ),
            'show_in_rest'       => true,
            'label'              => __( 'Social Posts', 'trello-social-auto-publisher' ),
        );

        register_post_type( 'tts_social_post', $args );
    }

    /**
     * Register custom meta fields.
     */
    public function register_meta_fields() {
        register_post_meta(
            'tts_social_post',
            '_tts_client_id',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_social_channel',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'array',
                'items'        => array(
                    'type' => 'string',
                ),
                'default'      => array(),
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_approved',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
                'default'      => false,
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_publish_story',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'boolean',
                'default'      => false,
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_story_media',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'integer',
                'default'      => 0,
            )
        );

        $channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
        foreach ( $channels as $ch ) {
            register_post_meta(
                'tts_social_post',
                '_tts_message_' . $ch,
                array(
                    'show_in_rest' => true,
                    'single'       => true,
                    'type'         => 'string',
                    'default'      => '',
                )
            );
        }

        register_post_meta(
            'tts_social_post',
            '_tts_lat',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'default'      => '',
            )
        );

        register_post_meta(
            'tts_social_post',
            '_tts_lng',
            array(
                'show_in_rest' => true,
                'single'       => true,
                'type'         => 'string',
                'default'      => '',
            )
        );
    }

    /**
     * Register the scheduling meta box.
     */
    public function add_schedule_metabox() {
        add_meta_box(
            'tts_programmazione',
            __( 'Programmazione', 'trello-social-auto-publisher' ),
            array( $this, 'render_schedule_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the preview meta box.
     */
    public function add_preview_metabox() {
        add_meta_box(
            'tts_anteprima',
            __( 'Preview', 'trello-social-auto-publisher' ),
            array( $this, 'render_preview_metabox' ),
            'tts_social_post',
            'normal'
        );
    }

    /**
     * Register the social channels meta box.
     */
    public function add_channel_metabox() {
        add_meta_box(
            'tts_social_channel',
            __( 'Channels', 'trello-social-auto-publisher' ),
            array( $this, 'render_channel_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the manual media meta box.
     */
    public function add_media_metabox() {
        add_meta_box(
            'tts_manual_media',
            __( 'Media', 'trello-social-auto-publisher' ),
            array( $this, 'render_media_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the per-channel messages meta box.
     */
    public function add_messages_metabox() {
        add_meta_box(
            'tts_messages',
            __( 'Messaggi per canale', 'trello-social-auto-publisher' ),
            array( $this, 'render_messages_metabox' ),
            'tts_social_post',
            'normal'
        );
    }

    /**
     * Register the location meta box.
     */
    public function add_location_metabox() {
        add_meta_box(
            'tts_location',
            __( 'Localizzazione', 'trello-social-auto-publisher' ),
            array( $this, 'render_location_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Register the approval status meta box.
     */
    public function add_approval_metabox() {
        add_meta_box(
            'tts_approval_status',
            __( 'Stato di approvazione', 'trello-social-auto-publisher' ),
            array( $this, 'render_approval_metabox' ),
            'tts_social_post',
            'side'
        );
    }

    /**
     * Render the scheduling meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_schedule_metabox( $post ) {
        wp_nonce_field( 'tts_schedule_metabox', 'tts_schedule_nonce' );
        $value     = get_post_meta( $post->ID, '_tts_publish_at', true );
        $formatted = $value ? date( 'Y-m-d\\TH:i', strtotime( $value ) ) : '';

        echo '<label for="_tts_publish_at">' . esc_html__( 'Data di pubblicazione', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="datetime-local" id="_tts_publish_at" name="_tts_publish_at" value="' . esc_attr( $formatted ) . '" class="widefat" />';

        $channels = get_post_meta( $post->ID, '_tts_social_channel', true );
        $channel  = is_array( $channels ) ? reset( $channels ) : $channels;
        if ( $channel && class_exists( 'TTS_Timing' ) ) {
            $suggested = TTS_Timing::suggest_time( $channel );
            if ( $suggested ) {
                echo '<p class="description">' . sprintf( esc_html__( 'Orario suggerito: %s', 'trello-social-auto-publisher' ), esc_html( $suggested ) ) . '</p>';
            }
        }
    }

    /**
     * Render the preview meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_preview_metabox( $post ) {
        $options   = get_option( 'tts_settings', array() );
        $templates = array(
            'facebook'  => isset( $options['facebook_template'] ) ? $options['facebook_template'] : '',
            'instagram' => isset( $options['instagram_template'] ) ? $options['instagram_template'] : '',
            'youtube'   => isset( $options['youtube_template'] ) ? $options['youtube_template'] : '',
        );

        echo '<div class="tts-preview">';
        foreach ( $templates as $network => $template ) {
            if ( empty( $template ) ) {
                continue;
            }
            $preview = tts_apply_template( $template, $post->ID, $network );
            echo '<p><strong>' . esc_html( ucfirst( $network ) ) . ':</strong> ' . esc_html( $preview ) . '</p>';
        }
        echo '</div>';
    }

    /**
     * Render the channels meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_channel_metabox( $post ) {
        wp_nonce_field( 'tts_channel_metabox', 'tts_channel_nonce' );
        $value    = get_post_meta( $post->ID, '_tts_social_channel', true );
        $value    = is_array( $value ) ? $value : array();
        $channels = array(
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        );

        $options = get_option( 'tts_settings', array() );

        foreach ( $channels as $key => $label ) {
            $offset  = isset( $options[ $key . '_offset' ] ) ? intval( $options[ $key . '_offset' ] ) : 0;
            $display = sprintf( __( '%1$s (%2$d min)', 'trello-social-auto-publisher' ), $label, $offset );
            printf(
                '<p><label><input type="checkbox" name="_tts_social_channel[]" value="%1$s" %2$s /> %3$s</label></p>',
                esc_attr( $key ),
                checked( in_array( $key, $value, true ), true, false ),
                esc_html( $display )
            );
        }
    }

    /**
     * Render the manual media meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_media_metabox( $post ) {
        wp_nonce_field( 'tts_media_metabox', 'tts_media_nonce' );
        wp_enqueue_media();
        wp_enqueue_script( 'jquery-ui-sortable' );
        $attachments = get_post_meta( $post->ID, '_tts_attachment_ids', true );
        $attachments = is_array( $attachments ) ? $attachments : array();
        echo '<ul id="tts_attachments_list">';
        foreach ( $attachments as $id ) {
            $thumb = wp_get_attachment_image( $id, array( 80, 80 ) );
            echo '<li class="tts-attachment-item"><label><input type="checkbox" class="tts-attachment-select" value="' . esc_attr( $id ) . '" checked />' . $thumb . '</label></li>';
        }
        echo '</ul>';
        echo '<input type="hidden" id="tts_attachment_ids" name="_tts_attachment_ids" value="' . esc_attr( implode( ',', $attachments ) ) . '" />';
        $value = get_post_meta( $post->ID, '_tts_manual_media', true );
        echo '<input type="hidden" id="tts_manual_media" name="_tts_manual_media" value="' . esc_attr( $value ) . '" />';
        echo '<button type="button" class="button tts-select-media">' . esc_html__( 'Seleziona/Carica file', 'trello-social-auto-publisher' ) . '</button>';

        $story_enabled = (bool) get_post_meta( $post->ID, '_tts_publish_story', true );
        $story_media   = (int) get_post_meta( $post->ID, '_tts_story_media', true );
        $story_thumb   = $story_media ? wp_get_attachment_image( $story_media, array( 80, 80 ) ) : '';
        echo '<p><label><input type="checkbox" id="tts_publish_story" name="_tts_publish_story" value="1" ' . checked( $story_enabled, true, false ) . ' /> ' . esc_html__( 'Pubblica come Story', 'trello-social-auto-publisher' ) . '</label></p>';
        echo '<div id="tts_story_media_wrapper"' . ( $story_enabled ? '' : ' style="display:none;"' ) . '>';
        echo '<div id="tts_story_media_preview">' . $story_thumb . '</div>';
        echo '<input type="hidden" id="tts_story_media" name="_tts_story_media" value="' . esc_attr( $story_media ) . '" />';
        echo '<button type="button" class="button tts-select-story-media">' . esc_html__( 'Seleziona media Story', 'trello-social-auto-publisher' ) . '</button>';
        echo '</div>';
    }

    /**
     * Render per-channel messages meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_messages_metabox( $post ) {
        wp_nonce_field( 'tts_messages_metabox', 'tts_messages_nonce' );
        $channels = array(
            'facebook'  => 'Facebook',
            'instagram' => 'Instagram',
            'youtube'   => 'YouTube',
            'tiktok'    => 'TikTok',
        );

        foreach ( $channels as $key => $label ) {
            $value = get_post_meta( $post->ID, '_tts_message_' . $key, true );
            echo '<p><label for="tts_message_' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label>';
            echo '<textarea id="tts_message_' . esc_attr( $key ) . '" name="_tts_message_' . esc_attr( $key ) . '" rows="3" class="widefat">' . esc_textarea( $value ) . '</textarea></p>';

            if ( 'instagram' === $key ) {
                $comment = get_post_meta( $post->ID, '_tts_instagram_first_comment', true );
                echo '<p><label for="tts_instagram_first_comment">' . esc_html__( 'Commento iniziale Instagram', 'trello-social-auto-publisher' ) . '</label>';
                echo '<textarea id="tts_instagram_first_comment" name="_tts_instagram_first_comment" rows="3" class="widefat">' . esc_textarea( $comment ) . '</textarea></p>';
            }
        }
    }

    /**
     * Render location meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_location_metabox( $post ) {
        wp_nonce_field( 'tts_location_metabox', 'tts_location_nonce' );
        $lat     = get_post_meta( $post->ID, '_tts_lat', true );
        $lng     = get_post_meta( $post->ID, '_tts_lng', true );
        $options = get_option( 'tts_settings', array() );

        if ( '' === $lat && isset( $options['default_lat'] ) ) {
            $lat = $options['default_lat'];
        }
        if ( '' === $lng && isset( $options['default_lng'] ) ) {
            $lng = $options['default_lng'];
        }

        echo '<p><label for="_tts_lat">' . esc_html__( 'Latitude', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="_tts_lat" name="_tts_lat" value="' . esc_attr( $lat ) . '" class="widefat" /></p>';
        echo '<p><label for="_tts_lng">' . esc_html__( 'Longitude', 'trello-social-auto-publisher' ) . '</label>';
        echo '<input type="text" id="_tts_lng" name="_tts_lng" value="' . esc_attr( $lng ) . '" class="widefat" /></p>';
    }

    /**
     * Save scheduling meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_schedule_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_schedule_nonce'] ) && wp_verify_nonce( $_POST['tts_schedule_nonce'], 'tts_schedule_metabox' ) ) {
            if ( isset( $_POST['_tts_publish_at'] ) && '' !== $_POST['_tts_publish_at'] ) {
                update_post_meta( $post_id, '_tts_publish_at', sanitize_text_field( wp_unslash( $_POST['_tts_publish_at'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_publish_at' );
            }
        }
    }

    /**
     * Save manual media meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_media_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_media_nonce'] ) && wp_verify_nonce( $_POST['tts_media_nonce'], 'tts_media_metabox' ) ) {
            if ( isset( $_POST['_tts_attachment_ids'] ) ) {
                $ids = array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['_tts_attachment_ids'] ) ) ) ) );
                update_post_meta( $post_id, '_tts_attachment_ids', $ids );
            }
            if ( isset( $_POST['_tts_manual_media'] ) && '' !== $_POST['_tts_manual_media'] ) {
                update_post_meta( $post_id, '_tts_manual_media', (int) $_POST['_tts_manual_media'] );
            } else {
                delete_post_meta( $post_id, '_tts_manual_media' );
            }

            $is_story = isset( $_POST['_tts_publish_story'] ) && '1' === $_POST['_tts_publish_story'];
            if ( $is_story ) {
                update_post_meta( $post_id, '_tts_publish_story', true );
                if ( isset( $_POST['_tts_story_media'] ) && '' !== $_POST['_tts_story_media'] ) {
                    update_post_meta( $post_id, '_tts_story_media', (int) $_POST['_tts_story_media'] );
                }
            } else {
                delete_post_meta( $post_id, '_tts_publish_story' );
                delete_post_meta( $post_id, '_tts_story_media' );
            }
        }
    }

    /**
     * Save channel selection meta box.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_channel_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_channel_nonce'] ) && wp_verify_nonce( $_POST['tts_channel_nonce'], 'tts_channel_metabox' ) ) {
            if ( isset( $_POST['_tts_social_channel'] ) && is_array( $_POST['_tts_social_channel'] ) ) {
                $channels = array_map( 'sanitize_text_field', wp_unslash( $_POST['_tts_social_channel'] ) );
                update_post_meta( $post_id, '_tts_social_channel', $channels );
            } else {
                delete_post_meta( $post_id, '_tts_social_channel' );
            }
        }
    }

    /**
     * Save per-channel messages meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_messages_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_messages_nonce'] ) && wp_verify_nonce( $_POST['tts_messages_nonce'], 'tts_messages_metabox' ) ) {
            $channels = array( 'facebook', 'instagram', 'youtube', 'tiktok' );
            foreach ( $channels as $ch ) {
                $field = '_tts_message_' . $ch;
                if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
                    update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
                } else {
                    delete_post_meta( $post_id, $field );
                }
            }

            $comment_field = '_tts_instagram_first_comment';
            if ( isset( $_POST[ $comment_field ] ) && '' !== $_POST[ $comment_field ] ) {
                update_post_meta( $post_id, $comment_field, sanitize_textarea_field( wp_unslash( $_POST[ $comment_field ] ) ) );
            } else {
                delete_post_meta( $post_id, $comment_field );
            }
        }
    }

    /**
     * Save location meta box data.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_location_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_location_nonce'] ) && wp_verify_nonce( $_POST['tts_location_nonce'], 'tts_location_metabox' ) ) {
            if ( isset( $_POST['_tts_lat'] ) && '' !== $_POST['_tts_lat'] ) {
                update_post_meta( $post_id, '_tts_lat', sanitize_text_field( wp_unslash( $_POST['_tts_lat'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_lat' );
            }
            if ( isset( $_POST['_tts_lng'] ) && '' !== $_POST['_tts_lng'] ) {
                update_post_meta( $post_id, '_tts_lng', sanitize_text_field( wp_unslash( $_POST['_tts_lng'] ) ) );
            } else {
                delete_post_meta( $post_id, '_tts_lng' );
            }
        }
    }

    /**
     * Render approval status meta box.
     *
     * @param WP_Post $post Current post object.
     */
    public function render_approval_metabox( $post ) {
        wp_nonce_field( 'tts_approval_metabox', 'tts_approval_nonce' );
        $approved = (bool) get_post_meta( $post->ID, '_tts_approved', true );
        echo '<label><input type="checkbox" name="_tts_approved" value="1" ' . checked( $approved, true, false ) . ' /> ';
        echo esc_html__( 'Approvato', 'trello-social-auto-publisher' ) . '</label>';
    }

    /**
     * Save approval status meta box.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     */
    public function save_approval_metabox( $post_id, $post, $update ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['tts_approval_nonce'] ) && wp_verify_nonce( $_POST['tts_approval_nonce'], 'tts_approval_metabox' ) ) {
            $old = (bool) get_post_meta( $post_id, '_tts_approved', true );
            $new = isset( $_POST['_tts_approved'] ) ? (bool) $_POST['_tts_approved'] : false;

            if ( $new ) {
                update_post_meta( $post_id, '_tts_approved', true );
                if ( ! $old ) {
                    do_action( 'tts_post_approved', $post_id );
                }
            } else {
                delete_post_meta( $post_id, '_tts_approved' );
            }
        }
    }
}

new TTS_CPT();
