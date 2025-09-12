<?php
/**
 * Blog publisher for WordPress.
 *
 * @package TrelloSocialAutoPublisher\Publishers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles publishing to WordPress blog with WPML support.
 */
class TTS_Publisher_Blog {

    /**
     * Publish the post as a WordPress blog post.
     *
     * Creates a WordPress post with featured image, SEO tags, and WPML support.
     *
     * @param int    $post_id     Post ID.
     * @param mixed  $credentials Blog publishing credentials/settings.
     * @param string $message     Content to publish.
     * @return string|WP_Error Log message or error.
     */
    public function publish( $post_id, $credentials, $message ) {
        if ( empty( $message ) ) {
            $error = __( 'Blog content is empty', 'trello-social-auto-publisher' );
            tts_log_event( $post_id, 'blog', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'blog' );
            return new \WP_Error( 'blog_no_content', $error );
        }

        // Parse blog settings from credentials
        $blog_settings = $this->parse_blog_settings( $credentials );
        
        // Get content details from Trello post
        $title = get_the_title( $post_id );
        $content = $this->prepare_content( $message, $post_id, $blog_settings );
        
        // Detect language from content or settings
        $language = $this->detect_language( $content, $blog_settings );
        
        // Create WordPress post
        $blog_post_data = array(
            'post_title'   => $title,
            'post_content' => $content,
            'post_status'  => $blog_settings['post_status'] ?? 'draft',
            'post_type'    => $blog_settings['post_type'] ?? 'post',
            'post_author'  => $blog_settings['author_id'] ?? get_current_user_id(),
        );

        // Add category if specified
        if ( ! empty( $blog_settings['category_id'] ) ) {
            $blog_post_data['post_category'] = array( $blog_settings['category_id'] );
        }

        // Insert the WordPress post
        $blog_post_id = wp_insert_post( $blog_post_data );
        
        if ( is_wp_error( $blog_post_id ) ) {
            $error = sprintf( 
                __( 'Failed to create blog post: %s', 'trello-social-auto-publisher' ), 
                $blog_post_id->get_error_message() 
            );
            tts_log_event( $post_id, 'blog', 'error', $error, '' );
            tts_notify_publication( $post_id, 'error', 'blog' );
            return $blog_post_id;
        }

        // Set featured image
        $this->set_featured_image( $blog_post_id, $post_id );
        
        // Handle WPML if available
        if ( $this->is_wpml_active() ) {
            $this->handle_wpml( $blog_post_id, $language, $blog_settings );
        }
        
        // Add SEO meta tags
        $this->add_seo_meta( $blog_post_id, $blog_settings, $content );
        
        // Process keyword links
        $this->process_keyword_links( $blog_post_id, $content, $blog_settings );
        
        // Log success
        $response_data = array(
            'blog_post_id' => $blog_post_id,
            'post_url'     => get_permalink( $blog_post_id ),
            'language'     => $language,
        );
        
        $response = sprintf( 
            __( 'Published to blog (Post ID: %d)', 'trello-social-auto-publisher' ), 
            $blog_post_id 
        );
        
        tts_log_event( $post_id, 'blog', 'success', $response, $response_data );
        tts_notify_publication( $post_id, 'success', 'blog' );
        
        return $response;
    }

    /**
     * Parse blog settings from credentials string.
     *
     * @param string $credentials Blog settings in format key1:value1|key2:value2
     * @return array Parsed settings.
     */
    private function parse_blog_settings( $credentials ) {
        $settings = array();
        
        if ( empty( $credentials ) ) {
            return $settings;
        }
        
        // Parse settings in format: post_type:post|post_status:publish|author_id:1
        $pairs = explode( '|', $credentials );
        foreach ( $pairs as $pair ) {
            if ( strpos( $pair, ':' ) !== false ) {
                list( $key, $value ) = explode( ':', $pair, 2 );
                $settings[ trim( $key ) ] = trim( $value );
            }
        }
        
        return $settings;
    }

    /**
     * Prepare content for blog post with keyword processing.
     *
     * @param string $message Original message.
     * @param int    $post_id Source post ID.
     * @param array  $settings Blog settings.
     * @return string Processed content.
     */
    private function prepare_content( $message, $post_id, $settings ) {
        // Clean up the message
        $content = wpautop( $message );
        
        // Add source attribution if enabled
        if ( ! empty( $settings['add_source'] ) && $settings['add_source'] === 'true' ) {
            $source_url = get_permalink( $post_id );
            if ( $source_url ) {
                $content .= sprintf( 
                    '<p><em>%s: <a href="%s">%s</a></em></p>', 
                    __( 'Source', 'trello-social-auto-publisher' ),
                    esc_url( $source_url ),
                    esc_html( get_the_title( $post_id ) )
                );
            }
        }
        
        return $content;
    }

    /**
     * Detect language from content or settings.
     *
     * @param string $content Blog content.
     * @param array  $settings Blog settings.
     * @return string Language code (it, en, etc).
     */
    private function detect_language( $content, $settings ) {
        // Use explicitly set language first
        if ( ! empty( $settings['language'] ) ) {
            return $settings['language'];
        }
        
        // Simple language detection based on common words
        $italian_words = array( 'il', 'la', 'di', 'che', 'e', 'un', 'a', 'per', 'con', 'su', 'del', 'della' );
        $english_words = array( 'the', 'and', 'of', 'to', 'a', 'in', 'for', 'is', 'on', 'that', 'with', 'as' );
        
        $content_lower = strtolower( $content );
        $italian_count = 0;
        $english_count = 0;
        
        foreach ( $italian_words as $word ) {
            $italian_count += substr_count( $content_lower, ' ' . $word . ' ' );
        }
        
        foreach ( $english_words as $word ) {
            $english_count += substr_count( $content_lower, ' ' . $word . ' ' );
        }
        
        return $italian_count > $english_count ? 'it' : 'en';
    }

    /**
     * Set featured image from source post attachments.
     *
     * @param int $blog_post_id Blog post ID.
     * @param int $source_post_id Source post ID.
     */
    private function set_featured_image( $blog_post_id, $source_post_id ) {
        // Get attachments from source post
        $attachment_ids = get_post_meta( $source_post_id, '_tts_attachments', true );
        
        if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
            // Try manual media
            $manual_id = get_post_meta( $source_post_id, '_tts_manual_media', true );
            if ( $manual_id ) {
                $attachment_ids = array( $manual_id );
            }
        }
        
        if ( ! empty( $attachment_ids ) && is_array( $attachment_ids ) ) {
            // Use first image as featured image
            foreach ( $attachment_ids as $att_id ) {
                $mime = get_post_mime_type( $att_id );
                if ( $mime && 0 === strpos( $mime, 'image/' ) ) {
                    set_post_thumbnail( $blog_post_id, $att_id );
                    break;
                }
            }
        }
    }

    /**
     * Check if WPML is active.
     *
     * @return bool True if WPML is active.
     */
    private function is_wpml_active() {
        return function_exists( 'icl_object_id' ) && defined( 'ICL_SITEPRESS_VERSION' );
    }

    /**
     * Handle WPML language assignment.
     *
     * @param int    $blog_post_id Blog post ID.
     * @param string $language Language code.
     * @param array  $settings Blog settings.
     */
    private function handle_wpml( $blog_post_id, $language, $settings ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }
        
        global $sitepress;
        
        // Set post language
        $sitepress->set_element_language_details( 
            $blog_post_id, 
            'post_' . get_post_type( $blog_post_id ), 
            false, 
            $language 
        );
        
        // If translation group is specified, connect to existing post
        if ( ! empty( $settings['translation_group'] ) ) {
            $translation_group_id = (int) $settings['translation_group'];
            if ( $translation_group_id > 0 ) {
                $sitepress->set_element_language_details( 
                    $blog_post_id, 
                    'post_' . get_post_type( $blog_post_id ), 
                    $translation_group_id, 
                    $language 
                );
            }
        }
    }

    /**
     * Add SEO meta tags to the blog post.
     *
     * @param int    $blog_post_id Blog post ID.
     * @param array  $settings Blog settings.
     * @param string $content Post content.
     */
    private function add_seo_meta( $blog_post_id, $settings, $content ) {
        // Add meta description
        if ( ! empty( $settings['meta_description'] ) ) {
            update_post_meta( $blog_post_id, '_yoast_wpseo_metadesc', $settings['meta_description'] );
        } else {
            // Generate meta description from content
            $description = wp_trim_words( strip_tags( $content ), 25 );
            update_post_meta( $blog_post_id, '_yoast_wpseo_metadesc', $description );
        }
        
        // Add focus keyword
        if ( ! empty( $settings['focus_keyword'] ) ) {
            update_post_meta( $blog_post_id, '_yoast_wpseo_focuskw', $settings['focus_keyword'] );
        }
        
        // Add canonical URL if specified
        if ( ! empty( $settings['canonical_url'] ) ) {
            update_post_meta( $blog_post_id, '_yoast_wpseo_canonical', $settings['canonical_url'] );
        }
        
        // Set SEO title
        if ( ! empty( $settings['seo_title'] ) ) {
            update_post_meta( $blog_post_id, '_yoast_wpseo_title', $settings['seo_title'] );
        }
    }

    /**
     * Process keyword links using link juicer functionality.
     *
     * @param int    $blog_post_id Blog post ID.
     * @param string $content Post content.
     * @param array  $settings Blog settings.
     */
    private function process_keyword_links( $blog_post_id, $content, $settings ) {
        if ( empty( $settings['keywords'] ) ) {
            return;
        }
        
        // Parse keywords in format: keyword1:url1|keyword2:url2
        $keywords = array();
        $keyword_pairs = explode( '|', $settings['keywords'] );
        
        foreach ( $keyword_pairs as $pair ) {
            if ( strpos( $pair, ':' ) !== false ) {
                list( $keyword, $url ) = explode( ':', $pair, 2 );
                $keywords[ trim( $keyword ) ] = trim( $url );
            }
        }
        
        if ( empty( $keywords ) ) {
            return;
        }
        
        // Get current post content
        $post = get_post( $blog_post_id );
        $updated_content = $post->post_content;
        
        // Replace keywords with links (only first occurrence)
        foreach ( $keywords as $keyword => $url ) {
            // Only link if keyword is not already linked
            if ( strpos( $updated_content, '>' . $keyword . '<' ) === false ) {
                $updated_content = preg_replace(
                    '/\b' . preg_quote( $keyword, '/' ) . '\b/',
                    '<a href="' . esc_url( $url ) . '" target="_blank">' . $keyword . '</a>',
                    $updated_content,
                    1 // Only replace first occurrence
                );
            }
        }
        
        // Update post content if changes were made
        if ( $updated_content !== $post->post_content ) {
            wp_update_post( array(
                'ID'           => $blog_post_id,
                'post_content' => $updated_content,
            ) );
        }
    }
}