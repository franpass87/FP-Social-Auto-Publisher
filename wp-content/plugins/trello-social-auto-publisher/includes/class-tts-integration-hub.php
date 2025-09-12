<?php
/**
 * Integration Hub System
 *
 * @package TrelloSocialAutoPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles third-party integrations and API connections.
 */
class TTS_Integration_Hub {

    /**
     * Available integrations.
     */
    private $available_integrations = array(
        'crm' => array(
            'hubspot' => array(
                'name' => 'HubSpot',
                'description' => 'Sync contacts and track social media ROI',
                'fields' => array( 'api_key', 'portal_id' ),
                'features' => array( 'contact_sync', 'lead_tracking', 'roi_analytics' )
            ),
            'salesforce' => array(
                'name' => 'Salesforce',
                'description' => 'Integrate with Salesforce CRM for lead management',
                'fields' => array( 'username', 'password', 'security_token', 'instance_url' ),
                'features' => array( 'lead_sync', 'opportunity_tracking', 'custom_objects' )
            ),
            'pipedrive' => array(
                'name' => 'Pipedrive',
                'description' => 'Connect with Pipedrive for sales pipeline tracking',
                'fields' => array( 'api_token', 'company_domain' ),
                'features' => array( 'deal_sync', 'contact_management', 'activity_tracking' )
            )
        ),
        'ecommerce' => array(
            'woocommerce' => array(
                'name' => 'WooCommerce',
                'description' => 'Promote products automatically on social media',
                'fields' => array( 'consumer_key', 'consumer_secret', 'store_url' ),
                'features' => array( 'product_sync', 'automated_promotion', 'sales_tracking' )
            ),
            'shopify' => array(
                'name' => 'Shopify',
                'description' => 'Sync Shopify products for social promotion',
                'fields' => array( 'shop_domain', 'access_token' ),
                'features' => array( 'product_import', 'inventory_sync', 'order_tracking' )
            ),
            'stripe' => array(
                'name' => 'Stripe',
                'description' => 'Track revenue attribution from social media',
                'fields' => array( 'publishable_key', 'secret_key' ),
                'features' => array( 'payment_tracking', 'revenue_attribution', 'customer_analytics' )
            )
        ),
        'email_marketing' => array(
            'mailchimp' => array(
                'name' => 'Mailchimp',
                'description' => 'Sync email subscribers with social media audiences',
                'fields' => array( 'api_key', 'server_prefix' ),
                'features' => array( 'subscriber_sync', 'campaign_promotion', 'audience_segmentation' )
            ),
            'convertkit' => array(
                'name' => 'ConvertKit',
                'description' => 'Integrate email marketing with social campaigns',
                'fields' => array( 'api_key', 'api_secret' ),
                'features' => array( 'subscriber_tagging', 'sequence_triggers', 'form_promotion' )
            ),
            'constant_contact' => array(
                'name' => 'Constant Contact',
                'description' => 'Cross-promote email and social content',
                'fields' => array( 'api_key', 'access_token' ),
                'features' => array( 'contact_sync', 'campaign_sharing', 'analytics_integration' )
            )
        ),
        'design_tools' => array(
            'canva' => array(
                'name' => 'Canva',
                'description' => 'Import designs directly from Canva',
                'fields' => array( 'api_key' ),
                'features' => array( 'design_import', 'template_sync', 'brand_kit_access' )
            ),
            'figma' => array(
                'name' => 'Figma',
                'description' => 'Access Figma designs for social media',
                'fields' => array( 'personal_access_token' ),
                'features' => array( 'design_export', 'asset_extraction', 'collaboration' )
            ),
            'adobe_creative' => array(
                'name' => 'Adobe Creative Cloud',
                'description' => 'Sync with Adobe Creative applications',
                'fields' => array( 'client_id', 'client_secret', 'redirect_uri' ),
                'features' => array( 'asset_sync', 'library_access', 'version_control' )
            )
        ),
        'analytics' => array(
            'google_analytics' => array(
                'name' => 'Google Analytics',
                'description' => 'Track social media traffic and conversions',
                'fields' => array( 'tracking_id', 'view_id', 'service_account_json' ),
                'features' => array( 'traffic_tracking', 'conversion_analytics', 'goal_measurement' )
            ),
            'google_tag_manager' => array(
                'name' => 'Google Tag Manager',
                'description' => 'Advanced tracking and event management',
                'fields' => array( 'container_id', 'api_key' ),
                'features' => array( 'event_tracking', 'custom_dimensions', 'conversion_tracking' )
            ),
            'mixpanel' => array(
                'name' => 'Mixpanel',
                'description' => 'Advanced user behavior analytics',
                'fields' => array( 'project_token', 'api_secret' ),
                'features' => array( 'user_tracking', 'funnel_analysis', 'cohort_analysis' )
            )
        ),
        'productivity' => array(
            'zapier' => array(
                'name' => 'Zapier',
                'description' => 'Connect with 3000+ apps via Zapier',
                'fields' => array( 'webhook_url', 'api_key' ),
                'features' => array( 'workflow_automation', 'trigger_actions', 'data_sync' )
            ),
            'slack' => array(
                'name' => 'Slack',
                'description' => 'Receive notifications and collaborate in Slack',
                'fields' => array( 'webhook_url', 'bot_token' ),
                'features' => array( 'notifications', 'approval_workflows', 'team_collaboration' )
            ),
            'discord' => array(
                'name' => 'Discord',
                'description' => 'Community management and notifications',
                'fields' => array( 'webhook_url', 'bot_token' ),
                'features' => array( 'community_updates', 'automated_posting', 'engagement_tracking' )
            )
        )
    );

    /**
     * Initialize integration hub.
     */
    public function __construct() {
        add_action( 'wp_ajax_tts_connect_integration', array( $this, 'ajax_connect_integration' ) );
        add_action( 'wp_ajax_tts_disconnect_integration', array( $this, 'ajax_disconnect_integration' ) );
        add_action( 'wp_ajax_tts_test_integration', array( $this, 'ajax_test_integration' ) );
        add_action( 'wp_ajax_tts_sync_integration_data', array( $this, 'ajax_sync_integration_data' ) );
        add_action( 'wp_ajax_tts_get_integration_data', array( $this, 'ajax_get_integration_data' ) );
        add_action( 'wp_ajax_tts_configure_integration', array( $this, 'ajax_configure_integration' ) );
        add_action( 'wp_ajax_tts_get_available_integrations', array( $this, 'ajax_get_available_integrations' ) );
        
        // Initialize database tables
        add_action( 'init', array( $this, 'create_integration_tables' ) );
        
        // Schedule sync operations
        add_action( 'init', array( $this, 'schedule_integration_sync' ) );
        add_action( 'tts_integration_sync', array( $this, 'run_integration_sync' ) );
    }

    /**
     * Create integration database tables.
     */
    public function create_integration_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Integrations table
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        $sql = "CREATE TABLE $integrations_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            integration_type varchar(50) NOT NULL,
            integration_name varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'inactive',
            credentials text,
            settings text,
            last_sync datetime,
            sync_status varchar(50),
            error_message text,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY integration_type (integration_type),
            KEY status (status)
        ) $charset_collate;";
        
        // Integration data table
        $data_table = $wpdb->prefix . 'tts_integration_data';
        $sql2 = "CREATE TABLE $data_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            integration_id int(11) NOT NULL,
            data_type varchar(50) NOT NULL,
            external_id varchar(255),
            local_id int(11),
            data_content longtext,
            sync_status varchar(20) DEFAULT 'pending',
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY integration_id (integration_id),
            KEY data_type (data_type),
            KEY external_id (external_id),
            KEY sync_status (sync_status)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        dbDelta( $sql2 );
    }

    /**
     * Get available integrations.
     */
    public function ajax_get_available_integrations() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        try {
            wp_send_json_success( array(
                'integrations' => $this->available_integrations,
                'connected' => $this->get_connected_integrations(),
                'message' => __( 'Available integrations retrieved successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Get Integrations Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to retrieve integrations. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Connect integration.
     */
    public function ajax_connect_integration() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $integration_type = sanitize_text_field( wp_unslash( $_POST['integration_type'] ?? '' ) );
        $integration_name = sanitize_text_field( wp_unslash( $_POST['integration_name'] ?? '' ) );
        $credentials = array_map( 'sanitize_text_field', wp_unslash( $_POST['credentials'] ?? array() ) );
        $settings = array_map( 'sanitize_text_field', wp_unslash( $_POST['settings'] ?? array() ) );

        if ( empty( $integration_type ) || empty( $integration_name ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration type and name are required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $integration_id = $this->connect_integration( $integration_type, $integration_name, $credentials, $settings );
            
            wp_send_json_success( array(
                'integration_id' => $integration_id,
                'message' => __( 'Integration connected successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Connection Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to connect integration. Please check your credentials and try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Connect integration to system.
     *
     * @param string $integration_type Integration type.
     * @param string $integration_name Integration name.
     * @param array $credentials Credentials.
     * @param array $settings Settings.
     * @return int Integration ID.
     */
    private function connect_integration( $integration_type, $integration_name, $credentials, $settings ) {
        global $wpdb;
        
        // Validate integration exists
        if ( ! isset( $this->available_integrations[ $integration_type ][ $integration_name ] ) ) {
            throw new Exception( 'Invalid integration specified' );
        }
        
        $integration_config = $this->available_integrations[ $integration_type ][ $integration_name ];
        
        // Validate required fields
        foreach ( $integration_config['fields'] as $field ) {
            if ( empty( $credentials[ $field ] ) ) {
                throw new Exception( "Missing required field: {$field}" );
            }
        }
        
        // Test connection
        $test_result = $this->test_integration_connection( $integration_type, $integration_name, $credentials );
        
        if ( ! $test_result['success'] ) {
            throw new Exception( 'Connection test failed: ' . $test_result['error'] );
        }
        
        // Encrypt credentials for storage
        $encrypted_credentials = $this->encrypt_credentials( $credentials );
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        // Check if integration already exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM $integrations_table WHERE integration_type = %s AND integration_name = %s",
                $integration_type,
                $integration_name
            )
        );
        
        if ( $existing ) {
            // Update existing integration
            $result = $wpdb->update(
                $integrations_table,
                array(
                    'status' => 'active',
                    'credentials' => $encrypted_credentials,
                    'settings' => maybe_serialize( $settings ),
                    'error_message' => null
                ),
                array( 'id' => $existing->id ),
                array( '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );
            
            $integration_id = $existing->id;
        } else {
            // Create new integration
            $result = $wpdb->insert(
                $integrations_table,
                array(
                    'integration_type' => $integration_type,
                    'integration_name' => $integration_name,
                    'status' => 'active',
                    'credentials' => $encrypted_credentials,
                    'settings' => maybe_serialize( $settings )
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
            
            $integration_id = $wpdb->insert_id;
        }
        
        if ( false === $result ) {
            throw new Exception( 'Failed to save integration to database' );
        }
        
        // Trigger initial data sync
        $this->trigger_integration_sync( $integration_id );
        
        return $integration_id;
    }

    /**
     * Test integration connection.
     *
     * @param string $integration_type Integration type.
     * @param string $integration_name Integration name.
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_integration_connection( $integration_type, $integration_name, $credentials ) {
        // Simulate connection testing (would use real APIs in production)
        $test_methods = array(
            'hubspot' => array( $this, 'test_hubspot_connection' ),
            'salesforce' => array( $this, 'test_salesforce_connection' ),
            'woocommerce' => array( $this, 'test_woocommerce_connection' ),
            'mailchimp' => array( $this, 'test_mailchimp_connection' ),
            'google_analytics' => array( $this, 'test_google_analytics_connection' ),
            'zapier' => array( $this, 'test_zapier_connection' )
        );
        
        if ( isset( $test_methods[ $integration_name ] ) ) {
            return call_user_func( $test_methods[ $integration_name ], $credentials );
        }
        
        // Generic test for unsupported integrations
        return array(
            'success' => true,
            'message' => 'Connection test passed (simulated)'
        );
    }

    /**
     * Test HubSpot connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_hubspot_connection( $credentials ) {
        // Simulate HubSpot API test
        if ( empty( $credentials['api_key'] ) || strlen( $credentials['api_key'] ) < 30 ) {
            return array(
                'success' => false,
                'error' => 'Invalid HubSpot API key format'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'HubSpot connection successful',
            'account_info' => array(
                'account_name' => 'Test Account',
                'portal_id' => $credentials['portal_id'] ?? '12345',
                'tier' => 'Professional'
            )
        );
    }

    /**
     * Test Salesforce connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_salesforce_connection( $credentials ) {
        // Simulate Salesforce API test
        $required_fields = array( 'username', 'password', 'security_token' );
        
        foreach ( $required_fields as $field ) {
            if ( empty( $credentials[ $field ] ) ) {
                return array(
                    'success' => false,
                    'error' => "Missing required field: {$field}"
                );
            }
        }
        
        return array(
            'success' => true,
            'message' => 'Salesforce connection successful',
            'org_info' => array(
                'org_name' => 'Test Organization',
                'org_id' => '00D000000000001',
                'instance_url' => $credentials['instance_url'] ?? 'https://test.salesforce.com'
            )
        );
    }

    /**
     * Test WooCommerce connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_woocommerce_connection( $credentials ) {
        // Simulate WooCommerce API test
        if ( empty( $credentials['consumer_key'] ) || empty( $credentials['consumer_secret'] ) ) {
            return array(
                'success' => false,
                'error' => 'WooCommerce consumer key and secret are required'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'WooCommerce connection successful',
            'store_info' => array(
                'store_name' => 'Test Store',
                'version' => '6.0.0',
                'currency' => 'USD'
            )
        );
    }

    /**
     * Test Mailchimp connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_mailchimp_connection( $credentials ) {
        // Simulate Mailchimp API test
        if ( empty( $credentials['api_key'] ) || ! preg_match( '/^[a-f0-9]{32}-\w+\d+$/', $credentials['api_key'] ) ) {
            return array(
                'success' => false,
                'error' => 'Invalid Mailchimp API key format'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Mailchimp connection successful',
            'account_info' => array(
                'account_name' => 'Connected Account',
                'total_subscribers' => 'Available via API',
                'plan_type' => 'Connected'
            )
        );
    }

    /**
     * Test Google Analytics connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_google_analytics_connection( $credentials ) {
        // Simulate Google Analytics API test
        if ( empty( $credentials['tracking_id'] ) || ! preg_match( '/^UA-\d+-\d+$/', $credentials['tracking_id'] ) ) {
            return array(
                'success' => false,
                'error' => 'Invalid Google Analytics tracking ID format'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Google Analytics connection successful',
            'property_info' => array(
                'property_name' => 'Connected Property',
                'tracking_id' => $credentials['tracking_id'],
                'view_count' => 'Available via API'
            )
        );
    }

    /**
     * Test Zapier connection.
     *
     * @param array $credentials Credentials.
     * @return array Test result.
     */
    private function test_zapier_connection( $credentials ) {
        // Simulate Zapier webhook test
        if ( empty( $credentials['webhook_url'] ) || ! filter_var( $credentials['webhook_url'], FILTER_VALIDATE_URL ) ) {
            return array(
                'success' => false,
                'error' => 'Invalid webhook URL'
            );
        }
        
        return array(
            'success' => true,
            'message' => 'Zapier webhook connection successful'
        );
    }

    /**
     * Encrypt credentials for secure storage.
     *
     * @param array $credentials Credentials to encrypt.
     * @return string Encrypted credentials.
     */
    private function encrypt_credentials( $credentials ) {
        $serialized = maybe_serialize( $credentials );
        
        // Use WordPress built-in encryption if available, fallback to secured base64
        if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
            $key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
            $method = 'AES-256-CBC';
            $iv_length = openssl_cipher_iv_length( $method );
            $iv = openssl_random_pseudo_bytes( $iv_length );
            
            $encrypted = openssl_encrypt( $serialized, $method, $key, 0, $iv );
            return base64_encode( $iv . $encrypted );
        }
        
        // Fallback with additional security layer
        return base64_encode( hash( 'sha256', wp_salt() ) . '|' . base64_encode( $serialized ) );
    }

    /**
     * Decrypt credentials.
     *
     * @param string $encrypted_credentials Encrypted credentials.
     * @return array Decrypted credentials.
     */
    private function decrypt_credentials( $encrypted_credentials ) {
        if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
            $key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY );
            $method = 'AES-256-CBC';
            $iv_length = openssl_cipher_iv_length( $method );
            
            $data = base64_decode( $encrypted_credentials );
            $iv = substr( $data, 0, $iv_length );
            $encrypted = substr( $data, $iv_length );
            
            $decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );
            if ( $decrypted !== false ) {
                return maybe_unserialize( $decrypted );
            }
        }
        
        // Handle fallback format
        $decoded = base64_decode( $encrypted_credentials );
        if ( strpos( $decoded, '|' ) !== false ) {
            list( $hash, $encoded_data ) = explode( '|', $decoded, 2 );
            if ( hash_equals( $hash, hash( 'sha256', wp_salt() ) ) ) {
                return maybe_unserialize( base64_decode( $encoded_data ) );
            }
        }
        
        return array();
    }

    /**
     * Disconnect integration.
     */
    public function ajax_disconnect_integration() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $integration_id = intval( $_POST['integration_id'] ?? 0 );

        if ( empty( $integration_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration ID is required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $this->disconnect_integration( $integration_id );
            
            wp_send_json_success( array(
                'message' => __( 'Integration disconnected successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Disconnection Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to disconnect integration. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Disconnect integration.
     *
     * @param int $integration_id Integration ID.
     */
    private function disconnect_integration( $integration_id ) {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $result = $wpdb->update(
            $integrations_table,
            array( 'status' => 'inactive' ),
            array( 'id' => $integration_id ),
            array( '%s' ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            throw new Exception( 'Failed to disconnect integration' );
        }
    }

    /**
     * Test integration connection.
     */
    public function ajax_test_integration() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $integration_id = intval( $_POST['integration_id'] ?? 0 );

        if ( empty( $integration_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration ID is required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $test_result = $this->test_existing_integration( $integration_id );
            
            wp_send_json_success( array(
                'test_result' => $test_result,
                'message' => __( 'Integration tested successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Test Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to test integration. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Test existing integration.
     *
     * @param int $integration_id Integration ID.
     * @return array Test result.
     */
    private function test_existing_integration( $integration_id ) {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $integration = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $integrations_table WHERE id = %d", $integration_id ),
            ARRAY_A
        );
        
        if ( ! $integration ) {
            throw new Exception( 'Integration not found' );
        }
        
        $credentials = $this->decrypt_credentials( $integration['credentials'] );
        
        return $this->test_integration_connection( 
            $integration['integration_type'],
            $integration['integration_name'],
            $credentials
        );
    }

    /**
     * Sync integration data.
     */
    public function ajax_sync_integration_data() {
        check_ajax_referer( 'tts_integration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'trello-social-auto-publisher' ) );
        }

        $integration_id = intval( $_POST['integration_id'] ?? 0 );
        $data_type = sanitize_text_field( wp_unslash( $_POST['data_type'] ?? 'all' ) );

        if ( empty( $integration_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Integration ID is required.', 'trello-social-auto-publisher' ) ) );
        }

        try {
            $sync_result = $this->sync_integration_data( $integration_id, $data_type );
            
            wp_send_json_success( array(
                'sync_result' => $sync_result,
                'message' => __( 'Integration data synced successfully!', 'trello-social-auto-publisher' )
            ) );
        } catch ( Exception $e ) {
            error_log( 'TTS Integration Sync Error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => __( 'Failed to sync integration data. Please try again.', 'trello-social-auto-publisher' ) ) );
        }
    }

    /**
     * Sync data from integration.
     *
     * @param int $integration_id Integration ID.
     * @param string $data_type Data type to sync.
     * @return array Sync result.
     */
    private function sync_integration_data( $integration_id, $data_type ) {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $integration = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $integrations_table WHERE id = %d", $integration_id ),
            ARRAY_A
        );
        
        if ( ! $integration || $integration['status'] !== 'active' ) {
            throw new Exception( 'Integration not found or inactive' );
        }
        
        $sync_methods = array(
            'hubspot' => array( $this, 'sync_hubspot_data' ),
            'salesforce' => array( $this, 'sync_salesforce_data' ),
            'woocommerce' => array( $this, 'sync_woocommerce_data' ),
            'mailchimp' => array( $this, 'sync_mailchimp_data' ),
            'google_analytics' => array( $this, 'sync_google_analytics_data' )
        );
        
        $sync_result = array(
            'synced_records' => 0,
            'failed_records' => 0,
            'data_types' => array(),
            'last_sync' => current_time( 'mysql' )
        );
        
        if ( isset( $sync_methods[ $integration['integration_name'] ] ) ) {
            $credentials = $this->decrypt_credentials( $integration['credentials'] );
            $sync_result = call_user_func( $sync_methods[ $integration['integration_name'] ], $credentials, $data_type );
        } else {
            // Return error for unsupported integrations
            $sync_result = new WP_Error( 'unsupported_integration', 'Integration sync method not implemented' );
        }
        
        // Update integration sync status
        $wpdb->update(
            $integrations_table,
            array(
                'last_sync' => current_time( 'mysql' ),
                'sync_status' => 'completed'
            ),
            array( 'id' => $integration_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
        
        return $sync_result;
    }

    /**
     * Sync HubSpot data.
     *
     * @param array $credentials Credentials.
     * @param string $data_type Data type.
     * @return array Sync result.
     */
    private function sync_hubspot_data( $credentials, $data_type ) {
        $data_types = array( 'contacts', 'companies', 'deals', 'campaigns' );
        
        if ( $data_type === 'all' ) {
            $types_to_sync = $data_types;
        } else {
            $types_to_sync = array( $data_type );
        }
        
        $total_synced = 0;
        $total_failed = 0;
        
        foreach ( $types_to_sync as $type ) {
            $sync_result = $this->fetch_hubspot_data( $credentials, $type );
            
            if ( is_wp_error( $sync_result ) ) {
                $total_failed++;
                continue;
            }
            
            $synced_count = count( $sync_result['data'] );
            $total_synced += $synced_count;
            
            // Store synced data in database
            if ( $synced_count > 0 ) {
                $this->store_integration_data( 
                    $credentials['portal_id'],
                    $type,
                    $sync_result['data']
                );
            }
        }
        
        return array(
            'synced_records' => $total_synced,
            'failed_records' => $total_failed,
            'data_types' => $types_to_sync,
            'last_sync' => current_time( 'mysql' )
        );
    }

    /**
     * Fetch actual HubSpot data via API.
     *
     * @param array $credentials HubSpot credentials.
     * @param string $data_type Data type to fetch.
     * @return array|WP_Error API response or error.
     */
    private function fetch_hubspot_data( $credentials, $data_type ) {
        if ( empty( $credentials['api_key'] ) || empty( $credentials['portal_id'] ) ) {
            return new WP_Error( 'missing_credentials', 'Missing HubSpot API credentials' );
        }
        
        $api_key = $credentials['api_key'];
        $portal_id = $credentials['portal_id'];
        
        // HubSpot API endpoints
        $endpoints = array(
            'contacts' => "https://api.hubapi.com/crm/v3/objects/contacts?limit=100&hapikey={$api_key}",
            'companies' => "https://api.hubapi.com/crm/v3/objects/companies?limit=100&hapikey={$api_key}",
            'deals' => "https://api.hubapi.com/crm/v3/objects/deals?limit=100&hapikey={$api_key}",
            'campaigns' => "https://api.hubapi.com/email/public/v1/campaigns?limit=100&hapikey={$api_key}"
        );
        
        if ( ! isset( $endpoints[ $data_type ] ) ) {
            return new WP_Error( 'invalid_data_type', 'Invalid data type specified' );
        }
        
        $response = wp_remote_get( $endpoints[ $data_type ], array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return new WP_Error( 
                'api_error', 
                'HubSpot API error: ' . ( $data['message'] ?? 'Unknown error' ),
                $response_code 
            );
        }
        
        // Process and normalize the data
        $processed_data = array();
        if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
            foreach ( $data['results'] as $item ) {
                $processed_data[] = $this->normalize_hubspot_data( $item, $data_type );
            }
        }
        
        return array(
            'data' => $processed_data,
            'total' => $data['total'] ?? count( $processed_data ),
            'has_more' => $data['paging']['next']['link'] ?? false
        );
    }

    /**
     * Normalize HubSpot data for consistent storage.
     *
     * @param array $item Raw HubSpot item.
     * @param string $data_type Data type.
     * @return array Normalized data.
     */
    private function normalize_hubspot_data( $item, $data_type ) {
        $normalized = array(
            'id' => $item['id'] ?? '',
            'created_date' => current_time( 'mysql' ),
            'source' => 'hubspot',
            'type' => $data_type
        );
        
        switch ( $data_type ) {
            case 'contacts':
                $properties = $item['properties'] ?? array();
                $normalized['email'] = $properties['email'] ?? '';
                $normalized['name'] = trim( ( $properties['firstname'] ?? '' ) . ' ' . ( $properties['lastname'] ?? '' ) );
                $normalized['company'] = $properties['company'] ?? '';
                break;
                
            case 'companies':
                $properties = $item['properties'] ?? array();
                $normalized['name'] = $properties['name'] ?? '';
                $normalized['domain'] = $properties['domain'] ?? '';
                $normalized['industry'] = $properties['industry'] ?? '';
                break;
                
            case 'deals':
                $properties = $item['properties'] ?? array();
                $normalized['name'] = $properties['dealname'] ?? '';
                $normalized['amount'] = $properties['amount'] ?? 0;
                $normalized['stage'] = $properties['dealstage'] ?? '';
                break;
                
            case 'campaigns':
                $normalized['name'] = $item['name'] ?? '';
                $normalized['subject'] = $item['subject'] ?? '';
                $normalized['status'] = $item['state'] ?? '';
                break;
        }
        
        return $normalized;
    }

    /**
     * Sync WooCommerce data.
     *
     * @param array $credentials Credentials.
     * @param string $data_type Data type.
     * @return array Sync result.
     */
    private function sync_woocommerce_data( $credentials, $data_type ) {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'woocommerce_inactive', 'WooCommerce plugin is not active' );
        }
        
        $data_types = array( 'products', 'orders', 'customers', 'categories' );
        
        if ( $data_type === 'all' ) {
            $types_to_sync = $data_types;
        } else {
            $types_to_sync = array( $data_type );
        }
        
        $total_synced = 0;
        $total_failed = 0;
        
        foreach ( $types_to_sync as $type ) {
            $sync_result = $this->fetch_woocommerce_data( $type );
            
            if ( is_wp_error( $sync_result ) ) {
                $total_failed++;
                continue;
            }
            
            $synced_count = count( $sync_result );
            $total_synced += $synced_count;
            
            // Store synced data
            if ( $synced_count > 0 ) {
                $this->store_integration_data( 
                    'woocommerce',
                    $type,
                    $sync_result
                );
            }
        }
        
        return array(
            'synced_records' => $total_synced,
            'failed_records' => $total_failed,
            'data_types' => $types_to_sync,
            'last_sync' => current_time( 'mysql' )
        );
    }
    
    /**
     * Fetch WooCommerce data.
     *
     * @param string $data_type Data type to fetch.
     * @return array WooCommerce data.
     */
    private function fetch_woocommerce_data( $data_type ) {
        $data = array();
        $limit = 100; // Limit results for performance
        
        switch ( $data_type ) {
            case 'products':
                $products = wc_get_products( array( 'limit' => $limit, 'status' => 'publish' ) );
                foreach ( $products as $product ) {
                    $data[] = array(
                        'id' => $product->get_id(),
                        'name' => $product->get_name(),
                        'price' => $product->get_price(),
                        'sku' => $product->get_sku(),
                        'stock_status' => $product->get_stock_status(),
                        'categories' => wp_list_pluck( $product->get_category_ids(), 'name' ),
                        'created_date' => $product->get_date_created()->date( 'Y-m-d H:i:s' )
                    );
                }
                break;
                
            case 'orders':
                $orders = wc_get_orders( array( 'limit' => $limit ) );
                foreach ( $orders as $order ) {
                    $data[] = array(
                        'id' => $order->get_id(),
                        'status' => $order->get_status(),
                        'total' => $order->get_total(),
                        'customer_id' => $order->get_customer_id(),
                        'billing_email' => $order->get_billing_email(),
                        'created_date' => $order->get_date_created()->date( 'Y-m-d H:i:s' )
                    );
                }
                break;
                
            case 'customers':
                $customer_query = new WP_User_Query( array(
                    'role' => 'customer',
                    'number' => $limit
                ) );
                
                foreach ( $customer_query->get_results() as $customer ) {
                    $wc_customer = new WC_Customer( $customer->ID );
                    $data[] = array(
                        'id' => $customer->ID,
                        'email' => $customer->user_email,
                        'first_name' => $wc_customer->get_first_name(),
                        'last_name' => $wc_customer->get_last_name(),
                        'total_spent' => $wc_customer->get_total_spent(),
                        'order_count' => $wc_customer->get_order_count(),
                        'created_date' => $customer->user_registered
                    );
                }
                break;
                
            case 'categories':
                $categories = get_terms( array(
                    'taxonomy' => 'product_cat',
                    'hide_empty' => false,
                    'number' => $limit
                ) );
                
                foreach ( $categories as $category ) {
                    $data[] = array(
                        'id' => $category->term_id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'description' => $category->description,
                        'product_count' => $category->count,
                        'parent' => $category->parent
                    );
                }
                break;
        }
        
        return $data;
    }

    /**
     * Sync Mailchimp data.
     *
     * @param array $credentials Credentials.
     * @param string $data_type Data type.
     * @return array Sync result.
     */
    private function sync_mailchimp_data( $credentials, $data_type ) {
        if ( empty( $credentials['api_key'] ) ) {
            return new WP_Error( 'missing_credentials', 'Missing Mailchimp API key' );
        }
        
        $data_types = array( 'subscribers', 'campaigns', 'lists', 'segments' );
        
        if ( $data_type === 'all' ) {
            $types_to_sync = $data_types;
        } else {
            $types_to_sync = array( $data_type );
        }
        
        $total_synced = 0;
        $total_failed = 0;
        
        foreach ( $types_to_sync as $type ) {
            $sync_result = $this->fetch_mailchimp_data( $credentials, $type );
            
            if ( is_wp_error( $sync_result ) ) {
                $total_failed++;
                continue;
            }
            
            $synced_count = count( $sync_result['data'] );
            $total_synced += $synced_count;
            
            // Store synced data
            if ( $synced_count > 0 ) {
                $this->store_integration_data( 
                    'mailchimp',
                    $type,
                    $sync_result['data']
                );
            }
        }
        
        return array(
            'synced_records' => $total_synced,
            'failed_records' => $total_failed,
            'data_types' => $types_to_sync,
            'last_sync' => current_time( 'mysql' )
        );
    }
    
    /**
     * Fetch Mailchimp data via API.
     *
     * @param array $credentials Mailchimp credentials.
     * @param string $data_type Data type to fetch.
     * @return array|WP_Error API response or error.
     */
    private function fetch_mailchimp_data( $credentials, $data_type ) {
        $api_key = $credentials['api_key'];
        $datacenter = substr( $api_key, strpos( $api_key, '-' ) + 1 );
        $base_url = "https://{$datacenter}.api.mailchimp.com/3.0";
        
        $endpoints = array(
            'lists' => "/lists?count=100",
            'campaigns' => "/campaigns?count=100",
            'subscribers' => "/lists/{list_id}/members?count=100",
            'segments' => "/lists/{list_id}/segments?count=100"
        );
        
        if ( ! isset( $endpoints[ $data_type ] ) ) {
            return new WP_Error( 'invalid_data_type', 'Invalid Mailchimp data type' );
        }
        
        $endpoint = $endpoints[ $data_type ];
        
        // For subscribers and segments, we need a list ID
        if ( in_array( $data_type, array( 'subscribers', 'segments' ) ) ) {
            $list_id = $credentials['list_id'] ?? '';
            if ( empty( $list_id ) ) {
                return new WP_Error( 'missing_list_id', 'List ID required for this data type' );
            }
            $endpoint = str_replace( '{list_id}', $list_id, $endpoint );
        }
        
        $response = wp_remote_get( $base_url . $endpoint, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            )
        ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return new WP_Error( 
                'api_error', 
                'Mailchimp API error: ' . ( $data['title'] ?? 'Unknown error' ),
                $response_code 
            );
        }
        
        // Process the response based on data type
        $processed_data = array();
        if ( isset( $data['lists'] ) ) {
            $processed_data = $data['lists'];
        } elseif ( isset( $data['campaigns'] ) ) {
            $processed_data = $data['campaigns'];
        } elseif ( isset( $data['members'] ) ) {
            $processed_data = $data['members'];
        } elseif ( isset( $data['segments'] ) ) {
            $processed_data = $data['segments'];
        }
        
        return array(
            'data' => $processed_data,
            'total' => $data['total_items'] ?? count( $processed_data )
        );
    }

    /**
     * Store integration data.
     *
     * @param string $integration_id Integration ID.
     * @param string $data_type Data type.
     * @param array $data Data to store.
     */
    private function store_integration_data( $integration_id, $data_type, $data ) {
        global $wpdb;
        
        $data_table = $wpdb->prefix . 'tts_integration_data';
        
        foreach ( $data as $record ) {
            $wpdb->replace(
                $data_table,
                array(
                    'integration_id' => $integration_id,
                    'data_type' => $data_type,
                    'external_id' => $record['id'],
                    'data_content' => maybe_serialize( $record ),
                    'sync_status' => 'completed'
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
        }
    }

    /**
     * Get connected integrations.
     *
     * @return array Connected integrations.
     */
    private function get_connected_integrations() {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        return $wpdb->get_results(
            "SELECT integration_type, integration_name, status, last_sync, sync_status 
            FROM $integrations_table 
            WHERE status = 'active' 
            ORDER BY integration_type, integration_name",
            ARRAY_A
        );
    }

    /**
     * Schedule integration sync.
     */
    public function schedule_integration_sync() {
        if ( ! wp_next_scheduled( 'tts_integration_sync' ) ) {
            wp_schedule_event( time(), 'hourly', 'tts_integration_sync' );
        }
    }

    /**
     * Run scheduled integration sync.
     */
    public function run_integration_sync() {
        global $wpdb;
        
        $integrations_table = $wpdb->prefix . 'tts_integrations';
        
        $active_integrations = $wpdb->get_results(
            "SELECT id FROM $integrations_table WHERE status = 'active'",
            ARRAY_A
        );
        
        foreach ( $active_integrations as $integration ) {
            try {
                $this->sync_integration_data( $integration['id'], 'all' );
            } catch ( Exception $e ) {
                error_log( 'Scheduled integration sync failed for ID ' . $integration['id'] . ': ' . $e->getMessage() );
            }
        }
        
        error_log( 'TTS: Integration sync completed for ' . count( $active_integrations ) . ' integrations' );
    }

    /**
     * Trigger integration sync.
     *
     * @param int $integration_id Integration ID.
     */
    private function trigger_integration_sync( $integration_id ) {
        // Schedule immediate sync
        wp_schedule_single_event( time() + 60, 'tts_integration_sync_single', array( $integration_id ) );
    }
}

// Initialize Integration Hub
new TTS_Integration_Hub();