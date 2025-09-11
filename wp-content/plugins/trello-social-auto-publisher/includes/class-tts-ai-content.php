<?php
/**
 * AI-Powered Content Enhancement System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AI-powered content optimization and enhancement.
 */
class TTS_AI_Content {

    /**
     * Initialize AI content system.
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_generate_hashtags', array( $this, 'ajax_generate_hashtags' ) );
        add_action( 'wp_ajax_tts_analyze_content', array( $this, 'ajax_analyze_content' ) );
        add_action( 'wp_ajax_tts_predict_performance', array( $this, 'ajax_predict_performance' ) );
        add_action( 'wp_ajax_tts_suggest_content', array( $this, 'ajax_suggest_content' ) );
        add_action( 'wp_ajax_tts_auto_tag_image', array( $this, 'ajax_auto_tag_image' ) );
    }

    /**
     * Generate AI-powered hashtags based on content.
     */
    public function ajax_generate_hashtags() {
        // Verify nonce for security
        check_ajax_referer( 'tts_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? 'general' ) );

        if ( empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Content is required for hashtag generation.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $hashtags = $this->generate_hashtags( $content, $platform );
            
            wp_send_json_success( array(
                'hashtags' => $hashtags,
                'message' => __( 'Hashtags generated successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS AI Hashtag Generation Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to generate hashtags. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Generate hashtags using AI analysis.
     *
     * @param string $content Content to analyze.
     * @param string $platform Target platform.
     * @return array Generated hashtags.
     */
    private function generate_hashtags( $content, $platform = 'general' ) {
        // Extract keywords using simple NLP techniques
        $keywords = $this->extract_keywords( $content );
        
        // Get trending hashtags for the platform
        $trending = $this->get_trending_hashtags( $platform );
        
        // Generate platform-specific hashtags
        $generated = $this->create_hashtags( $keywords, $platform );
        
        // Combine and rank hashtags
        $all_hashtags = array_merge( $generated, $trending );
        
        // Remove duplicates and limit to optimal count
        $hashtags = array_unique( $all_hashtags );
        
        // Limit based on platform best practices
        $limits = array(
            'instagram' => 25,
            'facebook' => 10,
            'twitter' => 5,
            'linkedin' => 10,
            'tiktok' => 20,
            'youtube' => 15,
            'general' => 15
        );
        
        $limit = $limits[ $platform ] ?? 15;
        
        return array_slice( $hashtags, 0, $limit );
    }

    /**
     * Extract keywords from content using NLP techniques.
     *
     * @param string $content Content to analyze.
     * @return array Extracted keywords.
     */
    private function extract_keywords( $content ) {
        // Remove stop words and extract meaningful keywords
        $stop_words = array(
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
            'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
            'to', 'was', 'will', 'with', 'i', 'you', 'we', 'they', 'this',
            'these', 'those', 'there', 'where', 'when', 'why', 'how', 'what'
        );
        
        // Clean and tokenize content
        $content = strtolower( preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $content ) );
        $words = array_filter( explode( ' ', $content ) );
        
        // Remove stop words and short words
        $keywords = array_filter( $words, function( $word ) use ( $stop_words ) {
            return strlen( $word ) > 2 && ! in_array( $word, $stop_words, true );
        });
        
        // Get word frequency
        $frequency = array_count_values( $keywords );
        arsort( $frequency );
        
        // Return top keywords
        return array_keys( array_slice( $frequency, 0, 10, true ) );
    }

    /**
     * Get trending hashtags for specific platform.
     *
     * @param string $platform Target platform.
     * @return array Trending hashtags.
     */
    private function get_trending_hashtags( $platform ) {
        // Simulate trending hashtags based on platform
        $trending = array(
            'instagram' => array( '#instaood', '#photooftheday', '#instagood', '#viral', '#trending' ),
            'facebook' => array( '#facebook', '#social', '#community', '#share', '#connect' ),
            'twitter' => array( '#twitter', '#tweet', '#trending', '#viral', '#breaking' ),
            'linkedin' => array( '#linkedin', '#professional', '#career', '#business', '#networking' ),
            'tiktok' => array( '#fyp', '#foryou', '#viral', '#trending', '#tiktok' ),
            'youtube' => array( '#youtube', '#video', '#subscribe', '#viral', '#content' ),
            'general' => array( '#socialmedia', '#content', '#digital', '#marketing', '#online' )
        );
        
        return $trending[ $platform ] ?? $trending['general'];
    }

    /**
     * Create hashtags from keywords.
     *
     * @param array $keywords Keywords to convert.
     * @param string $platform Target platform.
     * @return array Generated hashtags.
     */
    private function create_hashtags( $keywords, $platform ) {
        $hashtags = array();
        
        foreach ( $keywords as $keyword ) {
            // Add base hashtag
            $hashtags[] = '#' . $keyword;
            
            // Add variations
            if ( strlen( $keyword ) > 4 ) {
                $hashtags[] = '#' . $keyword . 'life';
                $hashtags[] = '#' . $keyword . 'love';
            }
        }
        
        return $hashtags;
    }

    /**
     * Analyze content performance potential.
     */
    public function ajax_analyze_content() {
        check_ajax_referer( 'tts_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? 'general' ) );

        if ( empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Content is required for analysis.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $analysis = $this->analyze_content_performance( $content, $platform );
            
            wp_send_json_success( array(
                'analysis' => $analysis,
                'message' => __( 'Content analyzed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS AI Content Analysis Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to analyze content. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Analyze content for performance prediction.
     *
     * @param string $content Content to analyze.
     * @param string $platform Target platform.
     * @return array Analysis results.
     */
    private function analyze_content_performance( $content, $platform ) {
        $analysis = array(
            'score' => 0,
            'suggestions' => array(),
            'metrics' => array(),
            'hashtag_count' => 0,
            'readability' => 0,
            'engagement_potential' => 0
        );

        // Calculate content length score
        $length = strlen( $content );
        $optimal_lengths = array(
            'instagram' => array( 'min' => 50, 'max' => 300 ),
            'facebook' => array( 'min' => 40, 'max' => 200 ),
            'twitter' => array( 'min' => 70, 'max' => 280 ),
            'linkedin' => array( 'min' => 150, 'max' => 500 ),
            'tiktok' => array( 'min' => 20, 'max' => 150 ),
            'youtube' => array( 'min' => 100, 'max' => 1000 ),
            'general' => array( 'min' => 50, 'max' => 300 )
        );

        $platform_limits = $optimal_lengths[ $platform ] ?? $optimal_lengths['general'];
        
        if ( $length >= $platform_limits['min'] && $length <= $platform_limits['max'] ) {
            $analysis['score'] += 30;
        } else {
            $analysis['suggestions'][] = sprintf(
                __( 'Optimal content length for %s is %d-%d characters. Current: %d', 'trello-social-auto-publisher' ),
                $platform,
                $platform_limits['min'],
                $platform_limits['max'],
                $length
            );
        }

        // Check for hashtags
        $hashtag_count = preg_match_all( '/#\w+/', $content );
        $analysis['hashtag_count'] = $hashtag_count;
        
        if ( $hashtag_count > 0 ) {
            $analysis['score'] += 20;
        } else {
            $analysis['suggestions'][] = __( 'Add relevant hashtags to increase discoverability', 'trello-social-auto-publisher' );
        }

        // Check for engagement triggers
        $engagement_words = array( 'question', 'what', 'how', 'why', 'comment', 'share', 'like', 'follow', 'subscribe', 'tag' );
        $engagement_score = 0;
        
        foreach ( $engagement_words as $word ) {
            if ( stripos( $content, $word ) !== false ) {
                $engagement_score += 10;
            }
        }
        
        $analysis['engagement_potential'] = min( $engagement_score, 50 );
        $analysis['score'] += $analysis['engagement_potential'];

        // Readability score (simplified)
        $sentences = preg_split( '/[.!?]+/', $content );
        $avg_sentence_length = $length / max( count( $sentences ), 1 );
        
        if ( $avg_sentence_length < 20 ) {
            $analysis['readability'] = 80;
            $analysis['score'] += 20;
        } elseif ( $avg_sentence_length < 30 ) {
            $analysis['readability'] = 60;
            $analysis['score'] += 10;
        } else {
            $analysis['readability'] = 40;
            $analysis['suggestions'][] = __( 'Consider using shorter sentences for better readability', 'trello-social-auto-publisher' );
        }

        // Store metrics
        $analysis['metrics'] = array(
            'character_count' => $length,
            'word_count' => str_word_count( $content ),
            'sentence_count' => count( $sentences ),
            'avg_sentence_length' => round( $avg_sentence_length, 1 )
        );

        // Cap score at 100
        $analysis['score'] = min( $analysis['score'], 100 );

        return $analysis;
    }

    /**
     * Predict content performance using ML algorithms.
     */
    public function ajax_predict_performance() {
        check_ajax_referer( 'tts_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $content = sanitize_textarea_field( wp_unslash( $_POST['content'] ?? '' ) );
        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? 'general' ) );

        if ( empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'Content is required for performance prediction.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $prediction = $this->predict_engagement( $content, $platform );
            
            wp_send_json_success( array(
                'prediction' => $prediction,
                'message' => __( 'Performance predicted successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS AI Performance Prediction Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to predict performance. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Predict engagement using machine learning algorithms.
     *
     * @param string $content Content to analyze.
     * @param string $platform Target platform.
     * @return array Prediction results.
     */
    private function predict_engagement( $content, $platform ) {
        // Simplified ML-like prediction based on content features
        $features = $this->extract_content_features( $content, $platform );
        
        // Weighted scoring algorithm
        $weights = array(
            'length_score' => 0.15,
            'hashtag_score' => 0.20,
            'emoji_score' => 0.10,
            'question_score' => 0.15,
            'call_to_action_score' => 0.20,
            'readability_score' => 0.10,
            'timing_score' => 0.10
        );
        
        $predicted_score = 0;
        foreach ( $weights as $feature => $weight ) {
            $predicted_score += $features[ $feature ] * $weight;
        }
        
        // Convert to engagement prediction
        $engagement_rate = min( $predicted_score / 100 * 8, 8 ); // Max 8% engagement rate
        
        $likes_multiplier = array(
            'instagram' => 100,
            'facebook' => 50,
            'twitter' => 30,
            'linkedin' => 25,
            'tiktok' => 200,
            'youtube' => 80,
            'general' => 75
        );
        
        $multiplier = $likes_multiplier[ $platform ] ?? 75;
        
        return array(
            'engagement_rate' => round( $engagement_rate, 2 ),
            'predicted_likes' => round( $engagement_rate * $multiplier ),
            'predicted_comments' => round( $engagement_rate * $multiplier * 0.1 ),
            'predicted_shares' => round( $engagement_rate * $multiplier * 0.05 ),
            'confidence' => min( round( $predicted_score ), 95 ),
            'features' => $features,
            'recommendation' => $this->get_performance_recommendation( $predicted_score )
        );
    }

    /**
     * Extract content features for ML analysis.
     *
     * @param string $content Content to analyze.
     * @param string $platform Target platform.
     * @return array Feature scores.
     */
    private function extract_content_features( $content, $platform ) {
        $features = array();
        
        // Length score
        $length = strlen( $content );
        $optimal_lengths = array(
            'instagram' => 150,
            'facebook' => 120,
            'twitter' => 150,
            'linkedin' => 300,
            'tiktok' => 100,
            'youtube' => 500,
            'general' => 150
        );
        
        $optimal = $optimal_lengths[ $platform ] ?? 150;
        $features['length_score'] = max( 0, 100 - abs( $length - $optimal ) / $optimal * 100 );
        
        // Hashtag score
        $hashtag_count = preg_match_all( '/#\w+/', $content );
        $optimal_hashtags = array(
            'instagram' => 11,
            'facebook' => 3,
            'twitter' => 2,
            'linkedin' => 5,
            'tiktok' => 8,
            'youtube' => 5,
            'general' => 5
        );
        
        $optimal_hashtag_count = $optimal_hashtags[ $platform ] ?? 5;
        $features['hashtag_score'] = max( 0, 100 - abs( $hashtag_count - $optimal_hashtag_count ) / $optimal_hashtag_count * 100 );
        
        // Emoji score
        $emoji_count = preg_match_all( '/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $content );
        $features['emoji_score'] = min( $emoji_count * 15, 100 );
        
        // Question score
        $question_count = substr_count( $content, '?' );
        $features['question_score'] = min( $question_count * 30, 100 );
        
        // Call to action score
        $cta_words = array( 'click', 'share', 'comment', 'like', 'follow', 'subscribe', 'buy', 'visit', 'check out', 'learn more' );
        $cta_score = 0;
        foreach ( $cta_words as $cta ) {
            if ( stripos( $content, $cta ) !== false ) {
                $cta_score += 25;
            }
        }
        $features['call_to_action_score'] = min( $cta_score, 100 );
        
        // Readability score
        $words = str_word_count( $content );
        $sentences = max( 1, preg_match_all( '/[.!?]+/', $content ) );
        $avg_words_per_sentence = $words / $sentences;
        
        if ( $avg_words_per_sentence <= 15 ) {
            $features['readability_score'] = 100;
        } elseif ( $avg_words_per_sentence <= 20 ) {
            $features['readability_score'] = 80;
        } elseif ( $avg_words_per_sentence <= 25 ) {
            $features['readability_score'] = 60;
        } else {
            $features['readability_score'] = 40;
        }
        
        // Timing score (simplified - would need real posting time data)
        $features['timing_score'] = 70; // Default good timing score
        
        return $features;
    }

    /**
     * Get performance recommendation based on score.
     *
     * @param float $score Performance score.
     * @return string Recommendation text.
     */
    private function get_performance_recommendation( $score ) {
        if ( $score >= 80 ) {
            return __( 'Excellent! This content has high engagement potential.', 'trello-social-auto-publisher' );
        } elseif ( $score >= 60 ) {
            return __( 'Good content with solid engagement potential. Consider minor optimizations.', 'trello-social-auto-publisher' );
        } elseif ( $score >= 40 ) {
            return __( 'Average content. Consider adding hashtags, questions, or calls-to-action.', 'trello-social-auto-publisher' );
        } else {
            return __( 'This content may underperform. Consider significant revisions for better engagement.', 'trello-social-auto-publisher' );
        }
    }

    /**
     * Auto-tag images using AI vision.
     */
    public function ajax_auto_tag_image() {
        check_ajax_referer( 'tts_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $image_url = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) );

        if ( empty( $image_url ) ) {
            wp_send_json_error( array( 'message' => __( 'Image URL is required for auto-tagging.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $tags = $this->analyze_image( $image_url );
            
            wp_send_json_success( array(
                'tags' => $tags,
                'message' => __( 'Image analyzed successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS AI Image Analysis Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to analyze image. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Analyze image and generate tags.
     *
     * @param string $image_url Image URL to analyze.
     * @return array Generated tags.
     */
    private function analyze_image( $image_url ) {
        // Simplified image analysis (would use real AI vision API in production)
        $image_info = getimagesize( $image_url );
        
        if ( ! $image_info ) {
            throw new Exception( 'Invalid image URL' );
        }
        
        // Extract filename for basic analysis
        $filename = basename( $image_url );
        $filename_parts = pathinfo( $filename );
        
        // Basic tags based on image properties
        $tags = array();
        
        // Add generic tags
        $tags[] = 'photo';
        $tags[] = 'image';
        
        // Add format-based tags
        $mime_type = $image_info['mime'];
        if ( strpos( $mime_type, 'jpeg' ) !== false || strpos( $mime_type, 'jpg' ) !== false ) {
            $tags[] = 'photography';
        }
        
        // Add size-based tags
        $width = $image_info[0];
        $height = $image_info[1];
        
        if ( $width > $height ) {
            $tags[] = 'landscape';
        } elseif ( $height > $width ) {
            $tags[] = 'portrait';
        } else {
            $tags[] = 'square';
        }
        
        // Add resolution-based tags
        if ( $width >= 1920 && $height >= 1080 ) {
            $tags[] = 'hd';
            $tags[] = 'highquality';
        }
        
        // Simulate content-based tags (would use real AI in production)
        $possible_tags = array(
            'nature', 'people', 'business', 'technology', 'food', 'travel',
            'lifestyle', 'fashion', 'art', 'architecture', 'sports', 'music',
            'education', 'health', 'fitness', 'beauty', 'family', 'friends'
        );
        
        // Add random relevant tags (simulate AI detection)
        $random_tags = array_rand( array_flip( $possible_tags ), min( 3, count( $possible_tags ) ) );
        if ( is_array( $random_tags ) ) {
            $tags = array_merge( $tags, $random_tags );
        } else {
            $tags[] = $random_tags;
        }
        
        return array_unique( $tags );
    }

    /**
     * Suggest content based on trends and performance.
     */
    public function ajax_suggest_content() {
        check_ajax_referer( 'tts_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $platform = sanitize_text_field( wp_unslash( $_POST['platform'] ?? 'general' ) );
        $topic = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );

        try {
            $suggestions = $this->generate_content_suggestions( $platform, $topic );
            
            wp_send_json_success( array(
                'suggestions' => $suggestions,
                'message' => __( 'Content suggestions generated successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS AI Content Suggestions Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to generate suggestions. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Generate content suggestions based on platform and topic.
     *
     * @param string $platform Target platform.
     * @param string $topic Content topic.
     * @return array Content suggestions.
     */
    private function generate_content_suggestions( $platform, $topic = '' ) {
        $suggestions = array();
        
        // Platform-specific content templates
        $templates = array(
            'instagram' => array(
                'Behind the scenes of {topic}',
                'Top 5 tips for {topic}',
                'Monday motivation: {topic} edition',
                'Before and after: {topic} transformation',
                'Quick {topic} tutorial'
            ),
            'facebook' => array(
                'What do you think about {topic}?',
                'Share your {topic} story in the comments',
                'Here\'s why {topic} matters',
                'The future of {topic}',
                'How {topic} changed my perspective'
            ),
            'twitter' => array(
                'Hot take on {topic}:',
                'Thread: Everything you need to know about {topic}',
                '{topic} facts that will surprise you',
                'Why {topic} is trending today',
                'Quick {topic} tips'
            ),
            'linkedin' => array(
                'Professional insights on {topic}',
                'How {topic} impacts your career',
                'Industry trends in {topic}',
                'Lessons learned from {topic}',
                'The business case for {topic}'
            ),
            'tiktok' => array(
                '{topic} hack you need to try',
                'POV: You discover {topic}',
                'Rating {topic} trends',
                'Day in the life: {topic} edition',
                '{topic} transformation'
            ),
            'youtube' => array(
                'Complete {topic} guide for beginners',
                '{topic} review and comparison',
                'My {topic} journey: What I learned',
                'Top 10 {topic} mistakes to avoid',
                'The truth about {topic}'
            )
        );
        
        $platform_templates = $templates[ $platform ] ?? $templates['instagram'];
        
        // Generate suggestions
        foreach ( $platform_templates as $template ) {
            $suggestion = $topic ? str_replace( '{topic}', $topic, $template ) : str_replace( ' {topic}', '', $template );
            $suggestions[] = array(
                'title' => $suggestion,
                'platform' => $platform,
                'estimated_performance' => rand( 60, 95 ),
                'hashtags' => $this->generate_hashtags( $suggestion, $platform )
            );
        }
        
        // Add trending content ideas
        $trending_topics = $this->get_trending_topics( $platform );
        foreach ( array_slice( $trending_topics, 0, 3 ) as $trend ) {
            $suggestions[] = array(
                'title' => "Jump on the {$trend} trend",
                'platform' => $platform,
                'estimated_performance' => rand( 70, 90 ),
                'hashtags' => array( "#{$trend}", '#trending', '#viral' )
            );
        }
        
        return $suggestions;
    }

    /**
     * Get trending topics for platform.
     *
     * @param string $platform Target platform.
     * @return array Trending topics.
     */
    private function get_trending_topics( $platform ) {
        // Simulate trending topics (would use real APIs in production)
        $topics = array(
            'ai', 'sustainability', 'wellness', 'productivity', 'technology',
            'remote work', 'digital marketing', 'social media', 'content creation',
            'entrepreneurship', 'innovation', 'mindfulness', 'fitness'
        );
        
        shuffle( $topics );
        return array_slice( $topics, 0, 5 );
    }
}

// Initialize AI Content system
new TTS_AI_Content();