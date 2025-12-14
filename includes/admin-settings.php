<?php
/**
 * Keiste Solar Report - Admin Settings
 * Handles WordPress admin interface for plugin settings
 * Version: 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class KSRAD_Admin {
    
    private $options;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(KSRAD_PLUGIN_BASENAME), array($this, 'add_settings_link'));
    }
    
    /**
     * Enqueue admin scripts for media uploader
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our settings page
        if ('toplevel_page_ksrad-solar' !== $hook && 'settings_page_ksrad-solar-settings' !== $hook) {
            return;
        }
        wp_enqueue_media();
    }
    
    /**
     * Add options page
     */
    public function add_plugin_page() {
        // Add top-level menu
        add_menu_page(
            'Keiste Solar',                     // Page title
            'Keiste Solar',                     // Menu title
            'manage_options',                   // Capability
            'ksrad-solar',                      // Menu slug
            array($this, 'create_admin_page'),  // Callback
            'dashicons-lightbulb',              // Icon
            30                                   // Position
        );
        
        // Add submenu under Settings as well
        add_options_page(
            'Keiste Solar Settings',           // Page title
            'Keiste Solar',                     // Menu title
            'manage_options',                   // Capability
            'ksrad-solar-settings',           // Menu slug
            array($this, 'create_admin_page')  // Callback
        );
    }
    
    /**
     * Add settings link on plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ksrad-solar') . '">' . __('Settings', 'keiste-solar') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Options page callback
     */
    public function create_admin_page() {
        $this->options = get_option('ksrad_options');
        ?>
        <div class="wrap">
            <h1>Keiste Solar Report Settings</h1>
            <p>Configure your Google API keys and default settings for the solar analysis tool.</p>

            <form method="post" action="options.php">
                <?php
                settings_fields('ksrad_option_group');
                do_settings_sections('keiste-solar-admin');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>Usage Instructions</h2>
            <div class="card">
                <h3>Shortcode</h3>
                <p>Use this shortcode to display the solar analysis tool on any page or post:</p>
                <code>[keiste_solar_report]</code>
                
                <h3>PHP Template Tag</h3>
                <p>Or use this PHP code in your theme templates:</p>
                <code>&lt;?php if (function_exists('ksrad_render_analysis')) ksrad_render_analysis(); ?&gt;</code>
            </div>
            
            <hr>
            
            <h2>API Key Setup</h2>
            <div class="card">
                <h3>Google Solar API</h3>
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>Create a new project or select an existing one</li>
                    <li>Enable the <strong>Solar API</strong></li>
                    <li>Create credentials (API Key)</li>
                    <li>Copy the API key and paste it above</li>
                </ol>
                
                <h3>Google Maps JavaScript API</h3>
                <ol>
                    <li>In the same Google Cloud project</li>
                    <li>Enable the <strong>Maps JavaScript API</strong></li>
                    <li>Enable the <strong>Places API</strong></li>
                    <li>Use the same API key or create a new one</li>
                </ol>
                
                <p><strong>Note:</strong> Both APIs may have usage costs. Check Google's pricing for current rates.</p>
            </div>
        </div>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }
            .card h3 {
                margin-top: 0;
            }
            .card code {
                background: #f0f0f1;
                padding: 10px;
                display: block;
                margin: 10px 0;
                border-radius: 3px;
            }
        </style>
        <?php
    }
    
    /**
     * Register and add settings
     */
    public function page_init() {
        register_setting(
            'ksrad_option_group',      // Option group
            'ksrad_options',            // Option name
            array($this, 'sanitize')           // Sanitize callback
        );
        
        // API Keys Section
        add_settings_section(
            'api_keys_section',                // ID
            'API Configuration',               // Title
            array($this, 'api_keys_info'),     // Callback
            'keiste-solar-admin'               // Page
        );
        
        add_settings_field(
            'google_solar_api_key',
            'Google API Credentials Key',
            array($this, 'google_solar_api_key_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'report_key',
            'Report Key',
            array($this, 'report_key_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'gamma_api_key',
            'Gamma API Key',
            array($this, 'gamma_api_key_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'gamma_template_id',
            'Gamma Template ID',
            array($this, 'gamma_template_id_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'twilio_account_sid',
            'Twilio Account SID',
            array($this, 'twilio_account_sid_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'twilio_auth_token',
            'Twilio Auth Token',
            array($this, 'twilio_auth_token_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'ga4_measurement_id',
            'GA4 Measurement ID',
            array($this, 'ga4_measurement_id_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        add_settings_field(
            'admin_notification_email',
            'Admin Notification Email',
            array($this, 'admin_notification_email_callback'),
            'keiste-solar-admin',
            'api_keys_section'
        );
        
        // Branding Section
        add_settings_section(
            'branding_section',
            'Branding',
            array($this, 'branding_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'logo_url',
            'Report Logo',
            array($this, 'logo_url_callback'),
            'keiste-solar-admin',
            'branding_section'
        );
        
        // Country Section
        add_settings_section(
            'country_section',
            'Country Settings',
            array($this, 'country_section_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'country',
            'Country',
            array($this, 'country_callback'),
            'keiste-solar-admin',
            'country_section'
        );
        
        // Default Values Section
        add_settings_section(
            'default_values_section',
            'Default Values',
            array($this, 'default_values_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'default_electricity_rate',
            'Default Electricity Rate (cost/kWh)',
            array($this, 'default_electricity_rate_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'default_export_rate',
            'Default Export Rate (%)',
            array($this, 'default_export_rate_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'default_feed_in_tariff',
            'Feed-in Tariff (cost/kWh)',
            array($this, 'default_feed_in_tariff_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'default_loan_apr',
            'Default Loan APR (%)',
            array($this, 'default_loan_apr_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'loan_term',
            'Loan Term (Years)',
            array($this, 'loan_term_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'annual_price_increase',
            'Annual Price Increase (%)',
            array($this, 'annual_price_increase_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'system_cost_ratio',
            'System Cost Ratio (cost/kWp)',
            array($this, 'system_cost_ratio_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'aca_rate',
            'Saving Allowance Rate (%)',
            array($this, 'aca_rate_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        add_settings_field(
            'financial_analysis_notes',
            'Financial Analysis Notes',
            array($this, 'financial_analysis_notes_callback'),
            'keiste-solar-admin',
            'default_values_section'
        );
        
        // Grants Configuration Section
        add_settings_section(
            'grants_section',
            'Grants Configuration',
            array($this, 'grants_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'grants_table',
            'Grants by Country & Building Type',
            array($this, 'grants_table_callback'),
            'keiste-solar-admin',
            'grants_section'
        );
        
        // System Size Configuration Section
        add_settings_section(
            'system_size_section',
            'System Size Configuration',
            array($this, 'system_size_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'system_size_table',
            'System Cost Ratio by Country & Building Type',
            array($this, 'system_size_table_callback'),
            'keiste-solar-admin',
            'system_size_section'
        );
        
        // Display Options
        add_settings_section(
            'display_options_section',
            'Display Options',
            array($this, 'display_options_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'enable_pdf_export',
            'Enable PDF Export',
            array($this, 'enable_pdf_export_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
        
        add_settings_field(
            'modal_popup_delay',
            'Lead Form Popup Delay (seconds)',
            array($this, 'modal_popup_delay_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
        
        add_settings_field(
            'map_boundary_coords',
            'Map Search Boundary Coordinates',
            array($this, 'map_boundary_coords_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
        
        add_settings_field(
            'search_instructions_text',
            'Search Instructions Text',
            array($this, 'search_instructions_text_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
    }
    
    /**
     * Sanitize each setting field as needed
     */
    public function sanitize($input) {
        $new_input = array();
        
        // Sanitize API keys (alphanumeric, dashes, underscores only)
        if (isset($input['google_solar_api_key'])) {
            $new_input['google_solar_api_key'] = sanitize_text_field($input['google_solar_api_key']);
        }
        
        // Validate and sanitize numeric values with range checks
        if (isset($input['default_electricity_rate'])) {
            $rate = floatval($input['default_electricity_rate']);
            $new_input['default_electricity_rate'] = ($rate >= 0 && $rate <= 10) ? $rate : 0.35;
        }
        
        if (isset($input['default_export_rate'])) {
            $rate = floatval($input['default_export_rate']);
            $new_input['default_export_rate'] = ($rate >= 0 && $rate <= 100) ? $rate : 10;
        }
        
        if (isset($input['default_feed_in_tariff'])) {
            $tariff = floatval($input['default_feed_in_tariff']);
            $new_input['default_feed_in_tariff'] = ($tariff >= 0 && $tariff <= 10) ? $tariff : 0.21;
        }
        
        if (isset($input['default_loan_apr'])) {
            $apr = floatval($input['default_loan_apr']);
            $new_input['default_loan_apr'] = ($apr >= 0 && $apr <= 100) ? $apr : 5;
        }
        
        if (isset($input['loan_term'])) {
            $term = intval($input['loan_term']);
            $new_input['loan_term'] = ($term >= 1 && $term <= 30) ? $term : 7;
        }
        
        if (isset($input['annual_price_increase'])) {
            $increase = floatval($input['annual_price_increase']);
            $new_input['annual_price_increase'] = ($increase >= 0 && $increase <= 50) ? $increase : 5;
        }
        
        if (isset($input['country'])) {
            $allowed = array('Rep. of Ireland', 'UK', 'United States', 'Canada');
            $new_input['country'] = in_array($input['country'], $allowed) ? $input['country'] : 'Rep. of Ireland';
            
            // Auto-set currency based on country
            $currency_map = array(
                'Rep. of Ireland' => '€',
                'UK' => '£',
                'United States' => '$',
                'Canada' => '$'
            );
            $new_input['currency'] = isset($currency_map[$new_input['country']]) ? $currency_map[$new_input['country']] : '€';
        }
        
        if (isset($input['system_cost_ratio'])) {
            $ratio = floatval($input['system_cost_ratio']);
            $new_input['system_cost_ratio'] = ($ratio >= 0 && $ratio <= 10000) ? $ratio : 1500;
        }
        
        if (isset($input['aca_rate'])) {
            $aca = floatval($input['aca_rate']);
            $new_input['aca_rate'] = ($aca >= 0 && $aca <= 100) ? $aca : 12.5;
        }
        
        if (isset($input['financial_analysis_notes'])) {
            $new_input['financial_analysis_notes'] = sanitize_textarea_field($input['financial_analysis_notes']);
        }
        
        if (isset($input['report_key'])) {
            $new_input['report_key'] = sanitize_text_field($input['report_key']);
        }
        
        if (isset($input['gamma_api_key'])) {
            $new_input['gamma_api_key'] = sanitize_text_field($input['gamma_api_key']);
        }
        
        if (isset($input['gamma_template_id'])) {
            $new_input['gamma_template_id'] = sanitize_text_field($input['gamma_template_id']);
        }
        
        if (isset($input['twilio_account_sid'])) {
            $new_input['twilio_account_sid'] = sanitize_text_field($input['twilio_account_sid']);
        }
        
        if (isset($input['twilio_auth_token'])) {
            $new_input['twilio_auth_token'] = sanitize_text_field($input['twilio_auth_token']);
        }
        
        if (isset($input['ga4_measurement_id'])) {
            $new_input['ga4_measurement_id'] = sanitize_text_field($input['ga4_measurement_id']);
        }
        
        if (isset($input['admin_notification_email'])) {
            $new_input['admin_notification_email'] = sanitize_email($input['admin_notification_email']);
        }
        
        if (isset($input['enable_pdf_export'])) {
            $new_input['enable_pdf_export'] = (bool)$input['enable_pdf_export'];
        }
        
        if (isset($input['modal_popup_delay'])) {
            $delay = intval($input['modal_popup_delay']);
            $new_input['modal_popup_delay'] = ($delay >= 0 && $delay <= 60) ? $delay : 3;
        }
        
        // Sanitize boundary coordinates
        if (isset($input['boundary_south'])) {
            $lat = floatval($input['boundary_south']);
            $new_input['boundary_south'] = ($lat >= -90 && $lat <= 90) ? $lat : '';
        }
        if (isset($input['boundary_west'])) {
            $lng = floatval($input['boundary_west']);
            $new_input['boundary_west'] = ($lng >= -180 && $lng <= 180) ? $lng : '';
        }
        if (isset($input['boundary_north'])) {
            $lat = floatval($input['boundary_north']);
            $new_input['boundary_north'] = ($lat >= -90 && $lat <= 90) ? $lat : '';
        }
        if (isset($input['boundary_east'])) {
            $lng = floatval($input['boundary_east']);
            $new_input['boundary_east'] = ($lng >= -180 && $lng <= 180) ? $lng : '';
        }
        
        if (isset($input['search_instructions_text'])) {
            $new_input['search_instructions_text'] = wp_kses_post($input['search_instructions_text']);
        }
        
        if (isset($input['logo_url'])) {
            $new_input['logo_url'] = esc_url_raw($input['logo_url']);
        }
        
        // Sanitize grants table
        if (isset($input['grants_table']) && is_array($input['grants_table'])) {
            $new_input['grants_table'] = array();
            foreach ($input['grants_table'] as $key => $grant) {
                $new_input['grants_table'][$key] = array(
                    'country' => sanitize_text_field($grant['country']),
                    'building_type' => sanitize_text_field($grant['building_type']),
                    'grant_percentage' => floatval($grant['grant_percentage']),
                    'grant_max' => floatval($grant['grant_max'])
                );
            }
        }
        
        // Sanitize system size table
        if (isset($input['system_size_table']) && is_array($input['system_size_table'])) {
            $new_input['system_size_table'] = array();
            foreach ($input['system_size_table'] as $key => $size) {
                $new_input['system_size_table'][$key] = array(
                    'country' => sanitize_text_field($size['country']),
                    'building_type' => sanitize_text_field($size['building_type']),
                    'cost_ratio' => floatval($size['cost_ratio'])
                );
            }
        }
        
        return $new_input;
    }
    
    /**
     * Section info callbacks
     */
    public function api_keys_info() {
        echo '<p>Enter your Google API keys. These are required for the solar analysis tool to function.</p>';
    }
    
    public function branding_info() {
        echo '<p>Customize the appearance of your solar reports with your company logo.</p>';
    }
    
    public function default_values_info() {
        echo '<p>Set default values for financial calculations. Users can override these in the calculator.</p>';
    }
    
    public function country_section_info() {
        echo '<p>Select the default country for your solar analysis tool. Currency is automatically set based on country selection.</p>';
    }
    
    public function grants_info() {
        echo '<p>Configure grant percentages and maximum amounts for different country and building type combinations.</p>';
    }
    
    public function system_size_info() {
        echo '<p>Configure system installation cost ratios (per kWp) for different country and building type combinations.</p>';
    }
    
    public function display_options_info() {
        echo '<p>Configure display and feature options for the solar analysis tool.</p>';
    }
    
    /**
     * Field callbacks
     */
    public function google_solar_api_key_callback() {
        printf(
            '<input type="password" id="google_solar_api_key" name="ksrad_options[google_solar_api_key]" value="%s" class="regular-text" />',
            isset($this->options['google_solar_api_key']) ? esc_attr($this->options['google_solar_api_key']) : ''
        );
        echo '<p class="description">**REQUIRED - Single API key for all Google services (Solar API, Maps JavaScript API, Maps API, Places API)</p>';
    }
    
    public function logo_url_callback() {
        $logo_url = isset($this->options['logo_url']) ? esc_url($this->options['logo_url']) : '';
        ?>
        <div class="logo-upload-wrapper">
            <input type="text" id="logo_url" name="ksrad_options[logo_url]" value="<?php echo esc_attr($logo_url); ?>" class="regular-text" readonly />
            <button type="button" class="button upload-logo-button">Upload Logo</button>
            <button type="button" class="button remove-logo-button" style="<?php echo empty($logo_url) ? 'display:none;' : ''; ?>">Remove</button>
            <div class="logo-preview" style="margin-top: 10px;">
                <?php if (!empty($logo_url)): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; height: auto;" />
                <?php endif; ?>
            </div>
        </div>
        <p class="description">Upload your company logo to display on solar reports. Recommended size: 400x150px or similar.</p>
        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            $('.upload-logo-button').click(function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: 'Choose Logo',
                    button: {
                        text: 'Use this logo'
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#logo_url').val(attachment.url);
                    $('.logo-preview').html('<img src="' + attachment.url + '" style="max-width: 200px; height: auto;" />');
                    $('.remove-logo-button').show();
                });
                
                mediaUploader.open();
            });
            
            $('.remove-logo-button').click(function(e) {
                e.preventDefault();
                $('#logo_url').val('');
                $('.logo-preview').html('');
                $(this).hide();
            });
        });
        </script>
        <?php
    }
    
    public function default_electricity_rate_callback() {
        printf(
            '<input type="number" step="0.01" id="default_electricity_rate" name="ksrad_options[default_electricity_rate]" value="%s" />',
            isset($this->options['default_electricity_rate']) ? esc_attr($this->options['default_electricity_rate']) : '0.35'
        );
        echo ' <span class="description">cost/kWh (e.g., 0.35 for $0.35/kWh or €0.35/kWh)</span>';
    }
    
    public function default_export_rate_callback() {
        printf(
            '<input type="number" step="1" id="default_export_rate" name="ksrad_options[default_export_rate]" value="%s" />',
            isset($this->options['default_export_rate']) ? esc_attr($this->options['default_export_rate']) : '10'
        );
        echo ' <span class="description">% (percentage of energy exported to grid)</span>';
    }
    
    public function default_feed_in_tariff_callback() {
        printf(
            '<input type="number" step="0.01" id="default_feed_in_tariff" name="ksrad_options[default_feed_in_tariff]" value="%s" />',
            isset($this->options['default_feed_in_tariff']) ? esc_attr($this->options['default_feed_in_tariff']) : '0.21'
        );
        echo ' <span class="description">cost/kWh</span>';
    }
    
    public function default_loan_apr_callback() {
        printf(
            '<input type="number" step="0.1" id="default_loan_apr" name="ksrad_options[default_loan_apr]" value="%s" />',
            isset($this->options['default_loan_apr']) ? esc_attr($this->options['default_loan_apr']) : '5'
        );
        echo ' <span class="description">% (annual percentage rate)</span>';
    }
    
    public function loan_term_callback() {
        printf(
            '<input type="number" step="1" id="loan_term" name="ksrad_options[loan_term]" value="%s" />',
            isset($this->options['loan_term']) ? esc_attr($this->options['loan_term']) : '7'
        );
        echo ' <span class="description">Years (length of payback period)</span>';
    }
    
    public function annual_price_increase_callback() {
        printf(
            '<input type="number" step="0.1" id="annual_price_increase" name="ksrad_options[annual_price_increase]" value="%s" />',
            isset($this->options['annual_price_increase']) ? esc_attr($this->options['annual_price_increase']) : '5'
        );
        echo ' <span class="description">% (expected electricity price inflation)</span>';
    }
    
    public function country_callback() {
        $current = isset($this->options['country']) ? $this->options['country'] : 'United States';
        $countries = array(
            'Rep. of Ireland' => 'Rep. of Ireland (€)',
            'UK' => 'UK (£)',
            'United States' => 'United States ($)',
            'Canada' => 'Canada ($)'
        );
        echo '<select id="country" name="ksrad_options[country]">';
        foreach ($countries as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';
        echo '<p class="description">Currency is automatically set based on country selection.</p>';
    }
    
    public function system_cost_ratio_callback() {
        printf(
            '<input type="number" step="0.01" id="system_cost_ratio" name="ksrad_options[system_cost_ratio]" value="%s" />',
            isset($this->options['system_cost_ratio']) ? esc_attr($this->options['system_cost_ratio']) : '1500'
        );
        echo ' <span class="description">cost/kWp (cost per kilowatt peak installed)</span>';
    }
    
    public function aca_rate_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="number" step="0.1" id="aca_rate" name="ksrad_options[aca_rate]" value="%s" %s />',
            isset($this->options['aca_rate']) ? esc_attr($this->options['aca_rate']) : '12.5',
            $disabled
        );
        echo ' <span class="description">% (Saving allowance rate)</span>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Upgrade to customize this value.</p>';
        }
    }
    
    public function financial_analysis_notes_callback() {
        printf(
            '<textarea id="financial_analysis_notes" name="ksrad_options[financial_analysis_notes]" rows="4" class="large-text">%s</textarea>',
            isset($this->options['financial_analysis_notes']) ? esc_textarea($this->options['financial_analysis_notes']) : ''
        );
        echo '<p class="description">Add notes or disclaimers for financial analysis calculations (optional)</p>';
    }
    
    public function report_key_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="text" id="report_key" name="ksrad_options[report_key]" value="%s" class="regular-text" %s />',
            isset($this->options['report_key']) ? esc_attr($this->options['report_key']) : '',
            $disabled
        );
        echo '<p class="description">Optional key for report authentication</p>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Activate the premium version to enable this field.</p>';
        }
    }
    
    public function gamma_api_key_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="text" id="gamma_api_key" name="ksrad_options[gamma_api_key]" value="%s" class="regular-text" %s />',
            isset($this->options['gamma_api_key']) ? esc_attr($this->options['gamma_api_key']) : '',
            $disabled
        );
        echo '<p class="description">API key for Gamma.app PDF generation service</p>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Activate the premium version to enable this field.</p>';
        }
    }
    
    public function gamma_template_id_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="text" id="gamma_template_id" name="ksrad_options[gamma_template_id]" value="%s" class="regular-text" %s />',
            isset($this->options['gamma_template_id']) ? esc_attr($this->options['gamma_template_id']) : '',
            $disabled
        );
        echo '<p class="description">Template ID from Gamma.app</p>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Activate the premium version to enable this field.</p>';
        }
    }
    
    public function twilio_account_sid_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="text" id="twilio_account_sid" name="ksrad_options[twilio_account_sid]" value="%s" class="regular-text" %s />',
            isset($this->options['twilio_account_sid']) ? esc_attr($this->options['twilio_account_sid']) : '',
            $disabled
        );
        echo '<p class="description">Twilio Account SID for phone number verification</p>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Activate the premium version to enable this field.</p>';
        }
    }
    
    public function twilio_auth_token_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="password" id="twilio_auth_token" name="ksrad_options[twilio_auth_token]" value="%s" class="regular-text" %s />',
            isset($this->options['twilio_auth_token']) ? esc_attr($this->options['twilio_auth_token']) : '',
            $disabled
        );
        echo '<p class="description">Your Twilio Auth Token for phone number verification.</p>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Activate the premium version to enable this field.</p>';
        }
    }
    
    public function ga4_measurement_id_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="text" id="ga4_measurement_id" name="ksrad_options[ga4_measurement_id]" value="%s" class="regular-text" placeholder="G-XXXXXXXXXX" %s />',
            isset($this->options['ga4_measurement_id']) ? esc_attr($this->options['ga4_measurement_id']) : '',
            $disabled
        );
        echo '<p class="description">Your Google Analytics 4 Measurement ID (format: G-XXXXXXXXXX). Form submissions will be tracked as conversion events.</p>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Activate the premium version to enable this field.</p>';
        }
    }
    
    public function admin_notification_email_callback() {
        printf(
            '<input type="email" id="admin_notification_email" name="ksrad_options[admin_notification_email]" value="%s" class="regular-text" placeholder="admin@example.com" />',
            isset($this->options['admin_notification_email']) ? esc_attr($this->options['admin_notification_email']) : ''
        );
        echo '<p class="description">Email address to receive notifications when users submit the solar report form. Leave blank to disable admin notifications.</p>';
    }
    
    public function enable_pdf_export_callback() {
        $checked = isset($this->options['enable_pdf_export']) && $this->options['enable_pdf_export'] ? 'checked' : '';
        printf(
            '<input type="checkbox" id="enable_pdf_export" name="ksrad_options[enable_pdf_export]" value="1" %s />',
            esc_attr($checked)
        );
        echo ' <span class="description">Allow users to export analysis as PDF</span>';
    }
    
    public function modal_popup_delay_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        printf(
            '<input type="number" step="1" min="0" max="60" id="modal_popup_delay" name="ksrad_options[modal_popup_delay]" value="%s" %s />',
            isset($this->options['modal_popup_delay']) ? esc_attr($this->options['modal_popup_delay']) : '3',
            $disabled
        );
        echo ' <span class="description">Time in seconds before lead capture form appears after user interaction (0-60, default: 3)</span>';
        if (!$is_premium) {
            echo '<p class="description" style="color: #d63638;"><strong>Premium feature:</strong> Upgrade to customize this value.</p>';
        }
    }
    
    public function map_boundary_coords_callback() {
        $is_premium = apply_filters('ksrad_is_premium', false);
        $disabled = $is_premium ? '' : 'disabled';
        
        $south = isset($this->options['boundary_south']) ? esc_attr($this->options['boundary_south']) : '';
        $west = isset($this->options['boundary_west']) ? esc_attr($this->options['boundary_west']) : '';
        $north = isset($this->options['boundary_north']) ? esc_attr($this->options['boundary_north']) : '';
        $east = isset($this->options['boundary_east']) ? esc_attr($this->options['boundary_east']) : '';
        ?>

        <div style="margin-bottom: 10px;">
            <label for="boundary_north" style="display: inline-block; width: 150px;">North:</label>
            <input type="number" step="0.000001" min="-90" max="90" id="boundary_north" 
                   name="ksrad_options[boundary_north]" value="<?php echo $north; ?>" 
                   class="regular-text" placeholder="e.g., 55.387" <?php echo $disabled; ?> />
        </div>
        <div style="margin-bottom: 10px;">
            <label for="boundary_south" style="display: inline-block; width: 150px;">South:</label>
            <input type="number" step="0.000001" min="-90" max="90" id="boundary_south" 
                   name="ksrad_options[boundary_south]" value="<?php echo $south; ?>" 
                   class="regular-text" placeholder="e.g., 51.421" <?php echo $disabled; ?> />
        </div>
        <div style="margin-bottom: 10px;">
            <label for="boundary_east" style="display: inline-block; width: 150px;">East:</label>
            <input type="number" step="0.000001" min="-180" max="180" id="boundary_east" 
                   name="ksrad_options[boundary_east]" value="<?php echo $east; ?>" 
                   class="regular-text" placeholder="e.g., -5.992" <?php echo $disabled; ?> />
        </div>
        <div style="margin-bottom: 10px;">
            <label for="boundary_west" style="display: inline-block; width: 150px;">West:</label>
            <input type="number" step="0.000001" min="-180" max="180" id="boundary_west" 
                   name="ksrad_options[boundary_west]" value="<?php echo $west; ?>" 
                   class="regular-text" placeholder="e.g., -10.476" <?php echo $disabled; ?> />
        </div>
        <p class="description">
            Define a geographical boundary to restrict map search results. Enter the bounding box coordinates.
            Leave blank to allow global search.
        </p>
        <?php if (!$is_premium): ?>
            <p class="description" style="color: #d63638;">
                <strong>Premium feature:</strong> Activate the premium version to enable custom boundaries.
            </p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Callback for search instructions text
     */
    public function search_instructions_text_callback() {
        $value = ksrad_get_option('search_instructions_text', 'Select your country and building type. Then search for your chosen building address.');
        ?>
        <textarea 
            id="search_instructions_text" 
            name="ksrad_options[search_instructions_text]" 
            rows="3" 
            cols="50" 
            class="large-text"
            placeholder="Select your country and building type. Then search for your chosen building address."
        ><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            Instructions shown to users above the map search box. Supports basic HTML formatting.
        </p>
        <?php
    }
    
    public function grants_table_callback() {
        $grants = isset($this->options['grants_table']) ? $this->options['grants_table'] : array();
        
        // Default grants if none set
        if (empty($grants)) {
            $grants = array(
                array('country' => 'United States', 'building_type' => 'Residential', 'grant_percentage' => 30, 'grant_max' => 7500),
                array('country' => 'United States', 'building_type' => 'Non-Residential', 'grant_percentage' => 30, 'grant_max' => 50000),
                array('country' => 'Rep. of Ireland', 'building_type' => 'Residential', 'grant_percentage' => 47, 'grant_max' => 1800),
                array('country' => 'Rep. of Ireland', 'building_type' => 'Non-Residential', 'grant_percentage' => 30, 'grant_max' => 162000),
                array('country' => 'UK', 'building_type' => 'Residential', 'grant_percentage' => 0, 'grant_max' => 0),
                array('country' => 'UK', 'building_type' => 'Non-Residential', 'grant_percentage' => 0, 'grant_max' => 0),
                array('country' => 'Canada', 'building_type' => 'Residential', 'grant_percentage' => 25, 'grant_max' => 5000),
                array('country' => 'Canada', 'building_type' => 'Non-Residential', 'grant_percentage' => 20, 'grant_max' => 40000),
            );
        }
        
        $countries = array('United States', 'Canada', 'UK', 'Rep. of Ireland');
        $building_types = array('Residential', 'Non-Residential');
        ?>
        <div class="grants-table-wrapper">
            <style>
                .grants-table-wrapper {
                    margin: 20px 0;
                }
                .grants-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #fff;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .grants-table th {
                    background: #f0f0f1;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    border-bottom: 2px solid #ddd;
                }
                .grants-table td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #eee;
                }
                .grants-table tr:hover {
                    background: #f9f9f9;
                }
                .grants-table input[type="number"] {
                    width: 100px;
                    padding: 4px 8px;
                }
                .grants-table select {
                    width: 150px;
                    padding: 4px 8px;
                }
                .add-grant-row {
                    margin-top: 10px;
                }
                .remove-grant-row {
                    color: #b32d2e;
                    cursor: pointer;
                    text-decoration: none;
                }
                .remove-grant-row:hover {
                    color: #dc3232;
                }
            </style>
            
            <table class="grants-table">
                <thead>
                    <tr>
                        <th>Country</th>
                        <th>Building Type</th>
                        <th>Grant % (0-100)</th>
                        <th>Maximum Amount (<?php echo isset($this->options['currency']) ? esc_html($this->options['currency']) : '€'; ?>)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="grants-table-body">
                    <?php foreach ($grants as $index => $grant): ?>
                    <tr class="grant-row">
                        <td>
                            <select name="ksrad_options[grants_table][<?php echo esc_attr($index); ?>][country]" class="grant-country">
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo esc_attr($country); ?>" <?php selected($grant['country'], $country); ?>>
                                        <?php echo esc_html($country); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="ksrad_options[grants_table][<?php echo esc_attr($index); ?>][building_type]" class="grant-building-type">
                                <?php foreach ($building_types as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($grant['building_type'], $type); ?>>
                                        <?php echo esc_html($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" 
                                   name="ksrad_options[grants_table][<?php echo esc_attr($index); ?>][grant_percentage]" 
                                   value="<?php echo esc_attr($grant['grant_percentage']); ?>" 
                                   step="0.1" 
                                   min="0" 
                                   max="100"
                                   class="grant-percentage" />
                            <span class="description">%</span>
                        </td>
                        <td>
                            <input type="number" 
                                   name="ksrad_options[grants_table][<?php echo esc_attr($index); ?>][grant_max]" 
                                   value="<?php echo esc_attr($grant['grant_max']); ?>" 
                                   step="1" 
                                   min="0"
                                   class="grant-max" />
                        </td>
                        <td>
                            <a href="#" class="remove-grant-row" title="Remove this row">Remove</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <button type="button" class="button add-grant-row">Add New Grant Configuration</button>
            
            <p class="description" style="margin-top: 15px;">
                Configure grant percentages and maximum amounts for different combinations of countries and building types. 
                The grant percentage will be applied to the installation cost, up to the maximum amount specified.
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var grantRowIndex = <?php echo count($grants); ?>;
            
            // Add new grant row
            $('.add-grant-row').click(function() {
                var newRow = `
                    <tr class="grant-row">
                        <td>
                            <select name="ksrad_options[grants_table][${grantRowIndex}][country]" class="grant-country">
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="ksrad_options[grants_table][${grantRowIndex}][building_type]" class="grant-building-type">
                                <?php foreach ($building_types as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" 
                                   name="ksrad_options[grants_table][${grantRowIndex}][grant_percentage]" 
                                   value="0" 
                                   step="0.1" 
                                   min="0" 
                                   max="100"
                                   class="grant-percentage" />
                            <span class="description">%</span>
                        </td>
                        <td>
                            <input type="number" 
                                   name="ksrad_options[grants_table][${grantRowIndex}][grant_max]" 
                                   value="0" 
                                   step="1" 
                                   min="0"
                                   class="grant-max" />
                        </td>
                        <td>
                            <a href="#" class="remove-grant-row" title="Remove this row">Remove</a>
                        </td>
                    </tr>
                `;
                $('#grants-table-body').append(newRow);
                grantRowIndex++;
            });
            
            // Remove grant row
            $(document).on('click', '.remove-grant-row', function(e) {
                e.preventDefault();
                if ($('.grant-row').length > 1) {
                    $(this).closest('tr').remove();
                } else {
                    alert('You must have at least one grant configuration.');
                }
            });
        });
        </script>
        <?php
    }
    
    public function system_size_table_callback() {
        $system_sizes = isset($this->options['system_size_table']) ? $this->options['system_size_table'] : array();
        
        // Default system sizes if none set
        if (empty($system_sizes)) {
            $system_sizes = array(
                array('country' => 'United States', 'building_type' => 'Residential', 'cost_ratio' => 2500),
                array('country' => 'United States', 'building_type' => 'Commercial Small', 'cost_ratio' => 2200),
                array('country' => 'United States', 'building_type' => 'Commercial Medium', 'cost_ratio' => 1800),
                array('country' => 'United States', 'building_type' => 'Commercial Large', 'cost_ratio' => 1500),
                array('country' => 'Rep. of Ireland', 'building_type' => 'Residential', 'cost_ratio' => 1500),
                array('country' => 'Rep. of Ireland', 'building_type' => 'Commercial Small', 'cost_ratio' => 1400),
                array('country' => 'Rep. of Ireland', 'building_type' => 'Commercial Medium', 'cost_ratio' => 1300),
                array('country' => 'Rep. of Ireland', 'building_type' => 'Commercial Large', 'cost_ratio' => 1100),
                array('country' => 'United Kingdom', 'building_type' => 'Residential', 'cost_ratio' => 2000),
                array('country' => 'United Kingdom', 'building_type' => 'Commercial Small', 'cost_ratio' => 1800),
                array('country' => 'United Kingdom', 'building_type' => 'Commercial Medium', 'cost_ratio' => 1600),
                array('country' => 'United Kingdom', 'building_type' => 'Commercial Large', 'cost_ratio' => 1300),
                array('country' => 'Canada', 'building_type' => 'Residential', 'cost_ratio' => 2300),
                array('country' => 'Canada', 'building_type' => 'Commercial Small', 'cost_ratio' => 2000),
                array('country' => 'Canada', 'building_type' => 'Commercial Medium', 'cost_ratio' => 1700),
                array('country' => 'Canada', 'building_type' => 'Commercial Large', 'cost_ratio' => 1400),
            );
        }
        
        $countries = array('United States', 'Canada', 'United Kingdom', 'Rep. of Ireland');
        $building_types = array('Residential', 'Commercial Small', 'Commercial Medium', 'Commercial Large');
        $currency = isset($this->options['currency']) ? $this->options['currency'] : '€';
        ?>
        <div class="system-size-table-wrapper">
            <style>
                .system-size-table-wrapper {
                    margin: 20px 0;
                }
                .system-size-table {
                    width: 100%;
                    border-collapse: collapse;
                    background: #fff;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .system-size-table th {
                    background: #f0f0f1;
                    padding: 12px;
                    text-align: left;
                    font-weight: 600;
                    border-bottom: 2px solid #ddd;
                }
                .system-size-table td {
                    padding: 10px 12px;
                    border-bottom: 1px solid #eee;
                }
                .system-size-table tr:hover {
                    background: #f9f9f9;
                }
                .system-size-table input[type="number"] {
                    width: 120px;
                    padding: 4px 8px;
                }
            </style>
            
            <table class="system-size-table">
                <thead>
                    <tr>
                        <th>Country & Building Type</th>
                        <th>System Cost Ratio (<?php echo esc_html($currency); ?>/kWp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $index = 0;
                    foreach ($countries as $country): 
                        foreach ($building_types as $type): 
                            // Find existing value or use default
                            $cost_ratio = 1500;
                            foreach ($system_sizes as $size) {
                                if ($size['country'] === $country && $size['building_type'] === $type) {
                                    $cost_ratio = $size['cost_ratio'];
                                    break;
                                }
                            }
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($country); ?></strong> - <?php echo esc_html($type); ?>
                            <input type="hidden" name="ksrad_options[system_size_table][<?php echo esc_attr($index); ?>][country]" value="<?php echo esc_attr($country); ?>" />
                            <input type="hidden" name="ksrad_options[system_size_table][<?php echo esc_attr($index); ?>][building_type]" value="<?php echo esc_attr($type); ?>" />
                        </td>
                        <td>
                            <input type="number" 
                                   name="ksrad_options[system_size_table][<?php echo esc_attr($index); ?>][cost_ratio]" 
                                   value="<?php echo esc_attr($cost_ratio); ?>" 
                                   step="1" 
                                   min="0"
                                   class="cost-ratio" />
                            <span class="description"><?php echo esc_html($currency); ?>/kWp</span>
                        </td>
                    </tr>
                    <?php 
                            $index++;
                        endforeach; 
                    endforeach; 
                    ?>
                </tbody>
            </table>
            
            <p class="description" style="margin-top: 15px;">
                Configure installation cost ratios per kilowatt peak (kWp) for different country and building type combinations. 
                These values will be used to calculate total installation costs based on system size.
            </p>
        </div>
        <?php
    }
}


// Initialize admin class if in admin area
if (is_admin()) {
    $ksrad_admin = new KSRAD_Admin();
}

/**
 * Helper function to get plugin options
 */
function ksrad_get_option($key, $default = '') {
    $options = get_option('ksrad_options');
    return isset($options[$key]) ? $options[$key] : $default;
}
