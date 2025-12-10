<?php
/**
 * Keiste Solar Report - Plugin Initialization
 * Handles WordPress integration, shortcodes, and asset loading
 * Version: 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSRAD_Plugin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load admin settings
        require_once KSRAD_PLUGIN_DIR . 'includes/admin-settings.php';
        
        // Register shortcode
        add_shortcode('keiste_solar_report', array($this, 'render_shortcode'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Register AJAX handlers
        add_action('wp_ajax_ksrad_generate_gamma_pdf', array($this, 'handle_gamma_pdf_generation'));
        add_action('wp_ajax_nopriv_ksrad_generate_gamma_pdf', array($this, 'handle_gamma_pdf_generation'));
        add_action('wp_ajax_ksrad_validate_phone', array($this, 'handle_phone_validation'));
        add_action('wp_ajax_nopriv_ksrad_validate_phone', array($this, 'handle_phone_validation'));
        add_action('wp_ajax_ksrad_validate_email', array($this, 'handle_email_validation'));
        add_action('wp_ajax_nopriv_ksrad_validate_email', array($this, 'handle_email_validation'));
        
        // Register scheduled email handler
        add_action('ksrad_send_gamma_report_email', array($this, 'send_scheduled_gamma_email'));
        
        // Add activation/deactivation hooks
        register_activation_hook(KSRAD_PLUGIN_BASENAME, array($this, 'activate'));
        register_deactivation_hook(KSRAD_PLUGIN_BASENAME, array($this, 'deactivate'));
    }
    
    /**
     * Enqueue plugin assets
     */
    public function enqueue_assets() {
        // Check if we should load assets
        global $post;
        
        // Load assets if:
        // 1. Current post has the shortcode, OR
        // 2. We're on a page that might use the shortcode (post object not yet available), OR
        // 3. We're in admin preview
        $should_load = false;
        
        if (is_a($post, 'WP_Post')) {
            $should_load = has_shortcode($post->post_content, 'keiste_solar_report');
        } else {
            // If $post not available yet, load assets (they'll be unused if shortcode not present)
            // This handles edge cases where $post isn't set during enqueue
            $should_load = true;
        }
        
        if (!$should_load) {
            return;
        }
        
        // Font Awesome (CDN)
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            array(),
            '6.4.0'
        );
        
        // Bootstrap CSS (CDN)
        wp_enqueue_style(
            'bootstrap',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
            array(),
            '5.1.3'
        );
        
        // Plugin stylesheet
        wp_enqueue_style(
            'keiste-solar-styles',
            KSRAD_PLUGIN_URL . 'assets/css/solar-analysis.css',
            array('bootstrap'),
            KSRAD_VERSION
        );
        
        // Chart.js (CDN)
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        // Bootstrap JS (CDN)
        wp_enqueue_script(
            'bootstrap-js',
            'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js',
            array(),
            '5.1.3',
            true
        );
        
        // Google Maps API is loaded dynamically by solar-analysis.php JavaScript
        // to ensure proper initialization timing for the autocomplete component
        
        // Plugin JavaScript files (load in proper dependency order)
        
        // 1. Base utilities (legacy support)
        wp_enqueue_script(
            'keiste-solar-utilities',
            KSRAD_PLUGIN_URL . 'assets/js/utilities.js',
            array('jquery'),
            KSRAD_VERSION,
            true
        );
        
        // 2. Charts utilities (legacy support)
        wp_enqueue_script(
            'keiste-solar-charts',
            KSRAD_PLUGIN_URL . 'assets/js/charts.js',
            array('chartjs', 'keiste-solar-utilities'),
            KSRAD_VERSION,
            true
        );
        
        // 3. ROI calculator (legacy support) - DISABLED, using solar-calculator-main.js instead
        // wp_enqueue_script(
        //     'keiste-solar-roi',
        //     KSRAD_PLUGIN_URL . 'assets/js/roi-calculator.js',
        //     array('keiste-solar-utilities', 'keiste-solar-charts'),
        //     KSRAD_VERSION,
        //     true
        // );
        
        // 4. Maps integration
        wp_enqueue_script(
            'keiste-solar-maps',
            KSRAD_PLUGIN_URL . 'assets/js/maps-integration.js',
            array('jquery'),
            KSRAD_VERSION,
            true
        );
        
        // 5. Modal handler
        wp_enqueue_script(
            'keiste-solar-modal',
            KSRAD_PLUGIN_URL . 'assets/js/modal-handler.js',
            array('jquery'),
            KSRAD_VERSION,
            true
        );
        
        // 6. Utility functions (new modular)
        wp_enqueue_script(
            'keiste-solar-utility-functions',
            KSRAD_PLUGIN_URL . 'assets/js/utility-functions.js',
            array('jquery'),
            KSRAD_VERSION,
            true
        );
        
        // 7. Event handlers
        wp_enqueue_script(
            'keiste-solar-event-handlers',
            KSRAD_PLUGIN_URL . 'assets/js/event-handlers.js',
            array('jquery', 'keiste-solar-utility-functions'),
            KSRAD_VERSION,
            true
        );
        
        // 8. Chart initialization
        wp_enqueue_script(
            'keiste-solar-chart-init',
            KSRAD_PLUGIN_URL . 'assets/js/chart-initialization.js',
            array('chartjs', 'keiste-solar-utility-functions'),
            KSRAD_VERSION,
            true
        );
        
        // 9. Solar calculator main (depends on utilities and charts)
        wp_enqueue_script(
            'keiste-solar-calculator-main',
            KSRAD_PLUGIN_URL . 'assets/js/solar-calculator-main.js',
            array('jquery', 'keiste-solar-utility-functions', 'keiste-solar-chart-init'),
            KSRAD_VERSION,
            true
        );
        
        // 10. Configuration inline (global utilities)
        wp_enqueue_script(
            'keiste-solar-config-inline',
            KSRAD_PLUGIN_URL . 'assets/js/config-inline.js',
            array('jquery'),
            KSRAD_VERSION,
            true
        );
        
        // Pass PHP data to JavaScript
        wp_localize_script('keiste-solar-modal', 'ksradData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ksrad_nonce'),
            'googleSolarApiKey' => ksrad_get_option('google_solar_api_key', ''),
            'reportKey' => ksrad_get_option('report_key', ''),
            'defaultElectricityRate' => ksrad_get_option('default_electricity_rate', '0.45'),
            'defaultExportRate' => ksrad_get_option('default_export_rate', '40'),
            'defaultFeedInTariff' => ksrad_get_option('default_feed_in_tariff', '0.21'),
            'defaultLoanApr' => ksrad_get_option('default_loan_apr', '5'),
            'loanTerm' => ksrad_get_option('loan_term', '7'),
            'annualPriceIncrease' => ksrad_get_option('annual_price_increase', '5'),
            'currency' => ksrad_get_option('currency', '€'),
            'country' => ksrad_get_option('country', 'Rep. of Ireland'),
            'systemCostRatio' => ksrad_get_option('system_cost_ratio', '1500'),
            'seaiGrantRate' => ksrad_get_option('seai_grant_rate', '30'),
            'seaiGrantCap' => ksrad_get_option('seai_grant_cap', '162000'),
            'acaRate' => ksrad_get_option('aca_rate', '12.5'),
            'enablePdfExport' => ksrad_get_option('enable_pdf_export', true),
        ));
        
        // Add inline script to set global configuration variables
        // These need to be output inline because they may depend on dynamic page data
        $inline_config = "
            window.CURRENCY_SYMBOL = '" . esc_js(ksrad_get_option('currency', '€')) . "';
            window.COUNTRY_SETTING = '" . esc_js(ksrad_get_option('country', 'Rep. of Ireland')) . "';
            window.BUILDING_TYPE = 'Residential'; // Default, will be updated by user selection
        ";
        wp_add_inline_script('keiste-solar-utility-functions', $inline_config, 'before');
        
        // Enqueue Google Analytics 4 tracking
        $ga4_id = ksrad_get_option('ga4_measurement_id', '');
        if (!empty($ga4_id)) {
            // Register Google Analytics gtag script
            wp_enqueue_script(
                'google-analytics-gtag',
                'https://www.googletagmanager.com/gtag/js?id=' . esc_attr($ga4_id),
                array(),
                null,
                false // Load in head for proper tracking
            );
            
            // Add GA4 initialization inline script
            $ga4_init = "
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '" . esc_js($ga4_id) . "');
            ";
            wp_add_inline_script('google-analytics-gtag', $ga4_init);
            
            // Enqueue GA4 event tracking helper functions
            wp_enqueue_script(
                'keiste-solar-ga4-tracking',
                KSRAD_PLUGIN_URL . 'assets/js/ga4-tracking.js',
                array('google-analytics-gtag'),
                KSRAD_VERSION,
                false // Load in head after gtag
            );
        }
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts = array()) {
        // Parse and sanitize attributes
        $atts = shortcode_atts(array(
            'location' => '',
            'business_name' => '',
        ), $atts, 'keiste_solar_report');
        
        $atts['location'] = sanitize_text_field($atts['location']);
        $atts['business_name'] = sanitize_text_field($atts['business_name']);
        
        // Start output buffering
        ob_start();
        
        // Define constant to allow solar-analysis.php to run
        define('KSRAD_RENDERING', true);
        
        // Load the main analysis file
        // Note: The original solar-analysis.php will need refactoring to work as a template
        // For now, we'll include it directly
        include KSRAD_PLUGIN_DIR . 'keiste-solar-report.php';
        
        return ob_get_clean();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $default_options = array(
            'google_solar_api_key' => '',
            'google_maps_api_key' => '',
            'report_key' => '',
            'logo_url' => '',
            'default_electricity_rate' => '0.45',
            'default_export_rate' => '40',
            'default_feed_in_tariff' => '0.21',
            'default_loan_apr' => '5',
            'loan_term' => '7',
            'annual_price_increase' => '5',
            'currency' => '€',
            'country' => 'Rep. of Ireland',
            'system_cost_ratio' => '1500',
            'seai_grant_rate' => '30',
            'seai_grant_cap' => '162000',
            'aca_rate' => '12.5',
            'enable_pdf_export' => true,
        );
        
        add_option('ksrad_options', $default_options);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear any scheduled email events
        wp_clear_scheduled_hook('ksrad_send_gamma_report_email');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Handle Gamma PDF Generation AJAX Request
     */
    public function handle_gamma_pdf_generation() {
        error_log('=== GAMMA PDF GENERATION FUNCTION CALLED ===');
        
        // Verify nonce
        $nonce_valid = check_ajax_referer('ksrad_nonce', 'nonce', false);
        if (!$nonce_valid) {
            error_log('NONCE VERIFICATION FAILED');
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Get form data
        $full_name = sanitize_text_field($_POST['fullName'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $panel_count = intval($_POST['panelCount'] ?? 0);
        $location = sanitize_text_field($_POST['location'] ?? '');
        
        // Validate required fields
        if (empty($email)) {
            error_log('KSRAD Form Submission: Email is required');
            wp_send_json_error(array('message' => 'Email is required'));
            return;
        }
        
        error_log('KSRAD Form Submission: Processing - ' . $email);
        
        // Prepare submission data for email/storage
        $submission_data = array(
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'panel_count' => $panel_count,
            'location' => $location,
            'country' => $_POST['country'] ?? 'Unknown',
            'building_type' => $_POST['buildingType'] ?? 'Unknown'
        );
        
        // Send admin notification email if configured
        $admin_email = ksrad_get_option('admin_notification_email', '');
        if (!empty($admin_email)) {
            $this->send_admin_notification($admin_email, $submission_data);
        }
        
        // Store submission in local file
        $this->store_submission_to_file($submission_data);
        
        // Get API credentials for optional Gamma PDF generation
        $gamma_api_key = ksrad_get_option('gamma_api_key', '');
        $gamma_template_id = ksrad_get_option('gamma_template_id', '');
        
        // Use hardcoded defaults if not configured
        if (empty($gamma_api_key)) {
            $gamma_api_key = '';
        }
        if (empty($gamma_template_id)) {
            $gamma_template_id = 'g_6h8kwcjnyzhxn9f';
        }
        
        // GAMMA API DISABLED - Always skip PDF generation
        error_log('KSRAD Form Submission: Gamma API disabled, returning success without PDF');
        wp_send_json_success(array(
            'message' => 'Form submitted successfully (Gamma PDF disabled)',
            'email_sent' => !empty($admin_email),
            'file_stored' => true
        ));
        return;
        
        // Skip Gamma API if not configured - just return success
        if (empty($gamma_api_key) || empty($gamma_template_id)) {
            error_log('KSRAD Form Submission: Gamma not configured, returning success without PDF');
            wp_send_json_success(array(
                'message' => 'Form submitted successfully (Gamma PDF disabled)',
                'email_sent' => !empty($admin_email),
                'file_stored' => true
            ));
            return;
        }
        
        error_log('Using Gamma API Key: ' . substr($gamma_api_key, 0, 15) . '...');
        error_log('Using Gamma Template ID: ' . $gamma_template_id);
        
        // Build prompt
        $prompt = sprintf(
            "Generate a professional solar report for %s at %s.\n\nSystem Details:\n- %d x 400W solar panels\n- Annual production: 7500 kWh\n- Contact: %s\n- Phone: %s",
            $full_name, $location, $panel_count, $email, $phone
        );
        
        // Prepare request
        $request_body = array(
            'gammaId' => $gamma_template_id,
            'prompt' => $prompt,
            'themeId' => 'default-light',
            'exportAs' => 'pdf',
            'imageOptions' => array('model' => 'imagen-4-pro', 'style' => 'Line Art'),
            'sharingOptions' => array(
                'workspaceAccess' => 'view',
                'externalAccess' => 'noAccess',
                'emailOptions' => array('recipients' => array($email), 'access' => 'comment')
            )
        );
        
        $json_body = json_encode($request_body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Call API
        $response = wp_remote_post('https://public-api.gamma.app/v1.0/generations/from-template', array(
            'headers' => array('Content-Type' => 'application/json', 'X-API-KEY' => $gamma_api_key),
            'body' => $json_body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // 200 OK and 201 Created are both success codes
        if ($response_code !== 200 && $response_code !== 201) {
            wp_send_json_error(array('message' => 'API Error: ' . $response_code, 'body' => $body));
            return;
        }
        
        $result = json_decode($body, true);
        
        // Log the full response to see what we're getting
        error_log('=== GAMMA API FULL RESPONSE ===');
        error_log(print_r($result, true));
        error_log('=== END GAMMA RESPONSE ===');
        
        // Get all possible ID fields from response
        $generation_id = $result['generationId'] ?? null;
        $document_id = $result['documentId'] ?? null;
        $web_url = $result['webUrl'] ?? null;
        
        // Determine which URL to use - prefer webUrl if available, otherwise construct from documentId or generationId
        $gamma_url = null;
        if (!empty($web_url)) {
            $gamma_url = $web_url;
            error_log('Using webUrl from response: ' . $gamma_url);
        } elseif (!empty($document_id)) {
            $gamma_url = 'https://gamma.app/docs/Solar-Report-for-' . sanitize_title($full_name) . '-' . $document_id;
            error_log('Constructed URL from documentId: ' . $gamma_url);
        } elseif (!empty($generation_id)) {
            // Fallback to generation ID if document ID not available yet
            $gamma_url = 'https://gamma.app/docs/Solar-Report-for-' . sanitize_title($full_name) . '-' . $generation_id;
            error_log('Constructed URL from generationId (fallback): ' . $gamma_url);
        }
        
        // Schedule email to be sent in 15 minutes if we have a URL
        // TEMPORARILY DISABLED - Email feature toggled off
        $email_scheduled = false;
        if (false && !empty($email) && !empty($gamma_url)) {
            // Schedule the email for 15 minutes from now
            $email_data = array(
                'email' => $email,
                'full_name' => $full_name,
                'gamma_url' => $gamma_url,
                'location' => $location,
                'panel_count' => $panel_count
            );
            
            wp_schedule_single_event(time() + (15 * 60), 'ksrad_send_gamma_report_email', array($email_data));
            
            error_log('Scheduled email for ' . $email . ' to be sent in 15 minutes with URL: ' . $gamma_url);
            $email_scheduled = true;
        } else {
            error_log('Email feature DISABLED - not scheduling email');
        }
        
        wp_send_json_success(array(
            'message' => 'PDF generation started successfully',
            'generation_id' => $generation_id,
            'document_id' => $document_id,
            'web_url' => $web_url,
            'gamma_url' => $gamma_url,
            'email_scheduled' => $email_scheduled,
            'response' => $result
        ));
    }
    
    /**
     * Send scheduled Gamma report email
     * Called by WordPress cron 15 minutes after report generation
     */
    public function send_scheduled_gamma_email($email_data) {
        if (empty($email_data['email']) || empty($email_data['gamma_url'])) {
            error_log('KSRAD: Cannot send email - missing email or URL');
            return;
        }
        
        $subject = 'Your Keiste Solar Report is Ready';
        $message = sprintf(
            "Hello %s,\n\n" .
            "Your personalized solar report has been generated and is ready to view!\n\n" .
            "You can access your report here:\n%s\n\n" .
            "Report Details:\n" .
            "- Location: %s\n" .
            "- Solar Panels: %d x 400W\n\n" .
            "This link will remain active and you can revisit it anytime to review your solar analysis.\n\n" .
            "If you have any questions about your report, please don't hesitate to contact us.\n\n" .
            "Best regards,\n" .
            "Keiste Solar Team",
            $email_data['full_name'],
            $email_data['gamma_url'],
            $email_data['location'],
            $email_data['panel_count']
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Keiste Solar <noreply@' . $_SERVER['HTTP_HOST'] . '>'
        );
        
        $email_sent = wp_mail($email_data['email'], $subject, $message, $headers);
        
        error_log('KSRAD: Scheduled email sent to ' . $email_data['email'] . ': ' . ($email_sent ? 'SUCCESS' : 'FAILED'));
        error_log('KSRAD: Report URL: ' . $email_data['gamma_url']);
    }
    
    /**
     * Send admin notification email when user submits form
     */
    private function send_admin_notification($admin_email, $data) {
        $subject = 'New Solar Report Form Submission - ' . $data['full_name'];
        
        $message = "New solar report form submission received:\n\n";
        $message .= "=== User Details ===\n";
        $message .= "Name: " . $data['full_name'] . "\n";
        $message .= "Email: " . $data['email'] . "\n";
        $message .= "Phone: " . $data['phone'] . "\n\n";
        $message .= "=== System Details ===\n";
        $message .= "Location: " . $data['location'] . "\n";
        $message .= "Country: " . $data['country'] . "\n";
        $message .= "Building Type: " . $data['building_type'] . "\n";
        $message .= "Panel Count: " . $data['panel_count'] . " x 400W\n\n";
        $message .= "=== Submission Time ===\n";
        $message .= "Date: " . date('F j, Y') . "\n";
        $message .= "Time: " . date('g:i A T') . "\n\n";
        $message .= "---\n";
        $message .= "This notification was sent from your Keiste Solar Report plugin.\n";
        $message .= "Site: " . get_site_url() . "\n";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: Keiste Solar <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'Reply-To: ' . $data['email']
        );
        
        $email_sent = wp_mail($admin_email, $subject, $message, $headers);
        
        error_log('KSRAD: Admin notification sent to ' . $admin_email . ': ' . ($email_sent ? 'SUCCESS' : 'FAILED'));
        
        return $email_sent;
    }
    
    /**
     * Store form submission to local text file
     */
    private function store_submission_to_file($data) {
        // Define the submissions directory and file path
        $submissions_dir = KSRAD_PLUGIN_DIR . 'submissions';
        $submissions_file = $submissions_dir . '/form-submissions.txt';
        
        // Create submissions directory if it doesn't exist
        if (!file_exists($submissions_dir)) {
            wp_mkdir_p($submissions_dir);
            
            // Add .htaccess to protect the directory
            $htaccess_file = $submissions_dir . '/.htaccess';
            file_put_contents($htaccess_file, "Deny from all\n");
            
            // Add index.php to prevent directory listing
            $index_file = $submissions_dir . '/index.php';
            file_put_contents($index_file, "<?php\n// Silence is golden.\n");
        }
        
        // Get current record number
        $record_number = 1;
        if (file_exists($submissions_file)) {
            $content = file_get_contents($submissions_file);
            // Count existing records by looking for "RECORD #" occurrences
            preg_match_all('/RECORD #(\d+)/', $content, $matches);
            if (!empty($matches[1])) {
                $record_number = max($matches[1]) + 1;
            }
        }
        
        // Format the submission entry
        $entry = str_repeat('=', 80) . "\n";
        $entry .= "RECORD #" . $record_number . "\n";
        $entry .= str_repeat('=', 80) . "\n";
        $entry .= "Date: " . date('F j, Y') . "\n";
        $entry .= "Time: " . date('g:i:s A T') . "\n";
        $entry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $entry .= "\n--- USER DETAILS ---\n";
        $entry .= "Name:     " . $data['full_name'] . "\n";
        $entry .= "Email:    " . $data['email'] . "\n";
        $entry .= "Phone:    " . $data['phone'] . "\n";
        $entry .= "\n--- SYSTEM DETAILS ---\n";
        $entry .= "Location:      " . $data['location'] . "\n";
        $entry .= "Country:       " . $data['country'] . "\n";
        $entry .= "Building Type: " . $data['building_type'] . "\n";
        $entry .= "Panel Count:   " . $data['panel_count'] . " x 400W\n";
        $entry .= "System Size:   " . number_format($data['panel_count'] * 0.4, 2) . " kWp\n";
        $entry .= "\n--- SUBMISSION INFO ---\n";
        $entry .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
        $entry .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
        $entry .= "\n\n";
        
        // Append to file
        $result = file_put_contents($submissions_file, $entry, FILE_APPEND | LOCK_EX);
        
        if ($result !== false) {
            error_log('KSRAD: Submission #' . $record_number . ' stored to file successfully');
        } else {
            error_log('KSRAD: Failed to store submission to file');
        }
        
        return $result !== false;
    }
    
    /**
     * Handle Email Validation via emailchecker API
     */
    public function handle_email_validation() {
        // Verify nonce
        $nonce_valid = check_ajax_referer('ksrad_nonce', 'nonce', false);
        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Get email
        $email = sanitize_email($_POST['email'] ?? '');
        
        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email is required'));
            return;
        }
        
        // Call emailchecker API
        $url = 'https://emailcheck-api.thexos.dev/check/' . urlencode($email);
        
        error_log('KSRAD Email Validation: Checking ' . $email);
        
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('KSRAD Email Validation: API Error - ' . $error_message);
            // On error, assume valid to not block user
            wp_send_json_success(array(
                'message' => 'Email validation error, assuming valid',
                'valid' => true,
                'email' => $email
            ));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            error_log('KSRAD Email Validation: Non-200 response code ' . $status_code);
            // Assume valid on error
            wp_send_json_success(array(
                'message' => 'Email validation API error, assuming valid',
                'valid' => true,
                'email' => $email
            ));
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log('KSRAD Email Validation: Response - ' . $body);
        
        if (!$data) {
            error_log('KSRAD Email Validation: Failed to parse response');
            wp_send_json_success(array(
                'message' => 'Email validation parse error, assuming valid',
                'valid' => true,
                'email' => $email
            ));
            return;
        }
        
        // Check validation result using correct nested structure
        $isValid = true;
        $reason = '';
        
        // Check disposable (nested in disposable.value)
        if (isset($data['disposable']['value']) && $data['disposable']['value'] === true) {
            $isValid = false;
            $reason = 'Disposable email addresses are not allowed';
        }
        // Check MX records (nested in dns.value.has_mx)
        elseif (isset($data['dns']['value']['has_mx']) && $data['dns']['value']['has_mx'] === false) {
            $isValid = false;
            $reason = 'Cannot verify email domain records. Reload form, try again with correct email.';
        }
        // Check prediction risk level (added check)
        elseif (isset($data['prediction']['risk_level']) && $data['prediction']['risk_level'] === 'high') {
            $isValid = false;
            $reason = 'Email has high risk: ' . implode(', ', $data['prediction']['reasons'] ?? ['Unknown reason']);
        }
        
        error_log('KSRAD Email Validation: Result - ' . ($isValid ? 'VALID' : 'INVALID') . ' - ' . $reason);
        
        if ($isValid) {
            wp_send_json_success(array(
                'message' => 'Email is valid',
                'valid' => true,
                'data' => $data
            ));
        } else {
            wp_send_json_error(array(
                'message' => $reason,
                'valid' => false,
                'reason' => $reason,
                'data' => $data
            ));
        }
    }
    
    /**
     * Handle Phone Validation via Twilio Lookup API
     */
    public function handle_phone_validation() {
        // Verify nonce
        $nonce_valid = check_ajax_referer('ksrad_nonce', 'nonce', false);
        if (!$nonce_valid) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        // Get phone number
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => 'Phone number is required'));
            return;
        }
        
        // Get Twilio credentials from settings
        $twilio_account_sid = ksrad_get_option('twilio_account_sid', '');
        $twilio_auth_token = ksrad_get_option('twilio_auth_token', '');
        
        // If Twilio not configured, assume valid (don't block users)
        if (empty($twilio_account_sid) || empty($twilio_auth_token)) {
            error_log('KSRAD Phone Validation: Twilio not configured, skipping validation for ' . $phone);
            wp_send_json_success(array(
                'message' => 'Phone validation skipped (Twilio not configured)',
                'valid' => true,
                'phone' => $phone
            ));
            return;
        }
        
        // Call Twilio Lookup API
        $url = 'https://lookups.twilio.com/v1/PhoneNumbers/' . urlencode($phone);
        
        error_log('KSRAD Phone Validation: Calling Twilio for ' . $phone);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($twilio_account_sid . ':' . $twilio_auth_token)
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('KSRAD Phone Validation: API Error - ' . $error_message);
            // On error, assume valid to not block user
            wp_send_json_success(array(
                'message' => 'Phone validation error, assuming valid',
                'valid' => true,
                'phone' => $phone,
                'error' => $error_message
            ));
            return;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('KSRAD Phone Validation: Twilio response code ' . $status_code);
        error_log('KSRAD Phone Validation: Response body - ' . $body);
        
        if ($status_code === 200) {
            $data = json_decode($body, true);
            
            wp_send_json_success(array(
                'message' => 'Phone number is valid',
                'valid' => true,
                'formatted' => $data['phone_number'] ?? $phone,
                'country' => $data['country_code'] ?? null
            ));
        } else {
            // Invalid phone number
            error_log('KSRAD Phone Validation: Invalid phone number - ' . $phone);
            wp_send_json_error(array(
                'message' => 'Invalid phone number format',
                'valid' => false,
                'phone' => $phone,
                'status_code' => $status_code
            ));
        }
    }
}

// Initialize the plugin
function ksrad_init() {
    return new KSRAD_Plugin();
}

// Start the plugin
add_action('plugins_loaded', 'ksrad_init');

/**
 * Template function for direct PHP calls
 */
function ksrad_render_analysis($args = array()) {
    $plugin = ksrad_init();
    // Output is already escaped within render_shortcode method
    echo $plugin->render_shortcode($args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
