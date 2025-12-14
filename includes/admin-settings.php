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
        
        // Required API Configuration
        add_settings_section(
            'required_api_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Required API Configuration</span>',
            array($this, 'required_api_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'google_solar_api_key',
            'Google API Credentials Key',
            array($this, 'google_solar_api_key_callback'),
            'keiste-solar-admin',
            'required_api_section'
        );
        
        // Display Options
        add_settings_section(
            'display_options_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Display Options</span>',
            array($this, 'display_options_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'country',
            'Country',
            array($this, 'country_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
        
        add_settings_field(
            'financial_analysis_notes',
            'Read Me Notes',
            array($this, 'financial_analysis_notes_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
        
        add_settings_field(
            'enable_pdf_export',
            'Enable PDF Export',
            array($this, 'enable_pdf_export_callback'),
            'keiste-solar-admin',
            'display_options_section'
        );
        
        // Admin Notification Email Section
        add_settings_section(
            'admin_notification_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Admin Notification Email</span>',
            array($this, 'admin_notification_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'admin_notification_email',
            'Admin Notification Email',
            array($this, 'admin_notification_email_callback'),
            'keiste-solar-admin',
            'admin_notification_section'
        );
        
        // Branding Section
        add_settings_section(
            'branding_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Branding</span>',
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
        
        add_settings_field(
            'logo_url_text',
            'Report Logo URL',
            array($this, 'logo_url_text_callback'),
            'keiste-solar-admin',
            'branding_section'
        );
        
        // Default Values Section
        add_settings_section(
            'default_values_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Default Values</span>',
            array($this, 'default_values_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'default_electricity_rate',
            'Default Electricity Rate (€/kWh)',
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
            'Feed-in Tariff (€/kWh)',
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
        
        // Grant Configuration Section
        add_settings_section(
            'grant_configuration_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Grant Configuration</span>',
            array($this, 'grant_configuration_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'grant_rate_domestic',
            'Grant Rate - Domestic (%)',
            array($this, 'grant_rate_domestic_callback'),
            'keiste-solar-admin',
            'grant_configuration_section'
        );
        
        add_settings_field(
            'grant_cap_domestic',
            'Grant Cap - Domestic',
            array($this, 'grant_cap_domestic_callback'),
            'keiste-solar-admin',
            'grant_configuration_section'
        );
        
        add_settings_field(
            'grant_rate_non_domestic',
            'Grant Rate - Non-Domestic (%)',
            array($this, 'grant_rate_non_domestic_callback'),
            'keiste-solar-admin',
            'grant_configuration_section'
        );
        
        add_settings_field(
            'grant_cap_non_domestic',
            'Grant Cap - Non-Domestic',
            array($this, 'grant_cap_non_domestic_callback'),
            'keiste-solar-admin',
            'grant_configuration_section'
        );
        
        add_settings_field(
            'aca_rate',
            'Saving Allowance Rate (%)',
            array($this, 'aca_rate_callback'),
            'keiste-solar-admin',
            'grant_configuration_section'
        );
        
        // System Size Configuration Section
        add_settings_section(
            'system_size_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ System Size Configuration</span>',
            array($this, 'system_size_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'cost_domestic',
            'Cost - Domestic ($/kWp)',
            array($this, 'cost_domestic_callback'),
            'keiste-solar-admin',
            'system_size_section'
        );
        
        add_settings_field(
            'cost_small',
            'Cost - Small Commercial ($/kWp)',
            array($this, 'cost_small_callback'),
            'keiste-solar-admin',
            'system_size_section'
        );
        
        add_settings_field(
            'cost_medium',
            'Cost - Medium Commercial ($/kWp)',
            array($this, 'cost_medium_callback'),
            'keiste-solar-admin',
            'system_size_section'
        );
        
        add_settings_field(
            'cost_large',
            'Cost - Large Commercial ($/kWp)',
            array($this, 'cost_large_callback'),
            'keiste-solar-admin',
            'system_size_section'
        );
        
        // Other APIs Section
        add_settings_section(
            'other_apis_section',
            '<span style="margin-top: 20px; display: inline-block;">▸ Other APIs</span>',
            array($this, 'other_apis_info'),
            'keiste-solar-admin'
        );
        
        add_settings_field(
            'report_key',
            'Report Key',
            array($this, 'report_key_callback'),
            'keiste-solar-admin',
            'other_apis_section'
        );
        
        add_settings_field(
            'gamma_api_key',
            'Gamma API Key',
            array($this, 'gamma_api_key_callback'),
            'keiste-solar-admin',
            'other_apis_section'
        );
        
        add_settings_field(
            'gamma_template_id',
            'Gamma Template ID',
            array($this, 'gamma_template_id_callback'),
            'keiste-solar-admin',
            'other_apis_section'
        );
        
        add_settings_field(
            'twilio_account_sid',
            'Twilio Account SID',
            array($this, 'twilio_account_sid_callback'),
            'keiste-solar-admin',
            'other_apis_section'
        );
        
        add_settings_field(
            'twilio_auth_token',
            'Twilio Auth Token',
            array($this, 'twilio_auth_token_callback'),
            'keiste-solar-admin',
            'other_apis_section'
        );
        
        add_settings_field(
            'ga4_measurement_id',
            'GA4 Measurement ID',
            array($this, 'ga4_measurement_id_callback'),
            'keiste-solar-admin',
            'other_apis_section'
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
            $new_input['default_export_rate'] = ($rate >= 0 && $rate <= 100) ? $rate : 40;
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
        
        if (isset($input['grant_rate_domestic'])) {
            $rate = floatval($input['grant_rate_domestic']);
            $new_input['grant_rate_domestic'] = ($rate >= 0 && $rate <= 100) ? $rate : 30;
        }
        
        if (isset($input['grant_cap_domestic'])) {
            $cap = floatval($input['grant_cap_domestic']);
            $new_input['grant_cap_domestic'] = ($cap >= 0 && $cap <= 1000000) ? $cap : 7500;
        }
        
        if (isset($input['grant_rate_non_domestic'])) {
            $rate = floatval($input['grant_rate_non_domestic']);
            $new_input['grant_rate_non_domestic'] = ($rate >= 0 && $rate <= 100) ? $rate : 30;
        }
        
        if (isset($input['grant_cap_non_domestic'])) {
            $cap = floatval($input['grant_cap_non_domestic']);
            $new_input['grant_cap_non_domestic'] = ($cap >= 0 && $cap <= 1000000) ? $cap : 50000;
        }
        
        if (isset($input['aca_rate'])) {
            $aca = $input['aca_rate'];
            if ($aca === '' || $aca === null) {
                $new_input['aca_rate'] = '';
            } else {
                $aca = floatval($aca);
                $new_input['aca_rate'] = ($aca >= 0 && $aca <= 100) ? $aca : '';
            }
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
        
        if (isset($input['logo_url'])) {
            $new_input['logo_url'] = esc_url_raw($input['logo_url']);
        }
        
        if (isset($input['logo_url_text'])) {
            $new_input['logo_url_text'] = esc_url_raw($input['logo_url_text']);
        }
        
        // Sanitize system size costs
        if (isset($input['cost_domestic'])) {
            $cost = floatval($input['cost_domestic']);
            $new_input['cost_domestic'] = ($cost >= 0 && $cost <= 10000) ? $cost : 2500;
        }
        
        if (isset($input['cost_small'])) {
            $cost = floatval($input['cost_small']);
            $new_input['cost_small'] = ($cost >= 0 && $cost <= 10000) ? $cost : 2000;
        }
        
        if (isset($input['cost_medium'])) {
            $cost = floatval($input['cost_medium']);
            $new_input['cost_medium'] = ($cost >= 0 && $cost <= 10000) ? $cost : 1700;
        }
        
        if (isset($input['cost_large'])) {
            $cost = floatval($input['cost_large']);
            $new_input['cost_large'] = ($cost >= 0 && $cost <= 10000) ? $cost : 1500;
        }
        
        return $new_input;
    }
    
    /**
     * Section info callbacks
     */
    public function required_api_info() {
        echo '<p>Enter your Google API key. This is required for the solar analysis tool to function.</p>';
    }
    
    public function other_apis_info() {
        echo '<p>Optional API integrations for enhanced features like lead capture, SMS verification, analytics, and automated presentations.</p>';
    }
    
    public function branding_info() {
        echo '<p>Customize the appearance of your solar reports with your company logo.</p>';
    }
    
    public function admin_notification_info() {
        echo '<p>Configure the email address that receives lead capture notifications from the solar calculator.</p>';
    }
    
    public function default_values_info() {
        echo '<p>Set default values for financial calculations. Users can override these in the calculator.</p>';
    }
    
    public function grant_configuration_info() {
        echo '<p>Configure grant percentages and maximum amounts for domestic (residential) and non-domestic (commercial) installations.</p>';
    }
    
    public function system_size_info() {
        echo '<p>Set the installation cost per kilowatt-peak (kWp) for different system sizes. Costs typically decrease as system size increases.</p>';
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
    
    public function logo_url_text_callback() {
        printf(
            '<input type="text" id="logo_url_text" name="ksrad_options[logo_url_text]" value="%s" class="regular-text" placeholder="https://example.com/logo.png" />',
            isset($this->options['logo_url_text']) ? esc_attr($this->options['logo_url_text']) : ''
        );
        echo '<p class="description">Enter a direct URL to your logo image (alternative to uploading).</p>';
    }
    
    public function default_electricity_rate_callback() {
        printf(
            '<input type="number" step="0.01" id="default_electricity_rate" name="ksrad_options[default_electricity_rate]" value="%s" />',
            isset($this->options['default_electricity_rate']) ? esc_attr($this->options['default_electricity_rate']) : '0.35'
        );
        echo ' <span class="description">€/kWh (e.g., 0.35 for €0.35/kWh)</span>';
    }
    
    public function default_export_rate_callback() {
        printf(
            '<input type="number" step="1" id="default_export_rate" name="ksrad_options[default_export_rate]" value="%s" />',
            isset($this->options['default_export_rate']) ? esc_attr($this->options['default_export_rate']) : '40'
        );
        echo ' <span class="description">% (percentage of energy exported to grid)</span>';
    }
    
    public function default_feed_in_tariff_callback() {
        printf(
            '<input type="number" step="0.01" id="default_feed_in_tariff" name="ksrad_options[default_feed_in_tariff]" value="%s" />',
            isset($this->options['default_feed_in_tariff']) ? esc_attr($this->options['default_feed_in_tariff']) : '0.21'
        );
        echo ' <span class="description">€/kWh</span>';
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
            'United States' => 'United States ($)',
            'Canada' => 'Canada ($)',
            'UK' => 'UK (£)',
            'Rep. of Ireland' => 'Rep. of Ireland (€)'
        );
        
        echo '<fieldset>';
        foreach ($countries as $value => $label) {
            printf(
                '<label style="display: block; margin-bottom: 8px;"><input type="radio" name="ksrad_options[country]" value="%s"%s /> %s</label>',
                esc_attr($value),
                checked($current, $value, false),
                esc_html($label)
            );
        }
        echo '</fieldset>';
        echo '<p class="description">Select the country/market for your solar calculator. This sets the default currency, grants, and financial parameters. Users will not see a country selector on the frontend.</p>';
    }
    
    public function grant_rate_domestic_callback() {
        printf(
            '<input type="number" step="0.1" id="grant_rate_domestic" name="ksrad_options[grant_rate_domestic]" value="%s" />',
            isset($this->options['grant_rate_domestic']) ? esc_attr($this->options['grant_rate_domestic']) : '30'
        );
        echo ' <span class="description">% (grant percentage for residential/domestic installations)</span>';
    }
    
    public function grant_cap_domestic_callback() {
        $currency = isset($this->options['currency']) ? esc_html($this->options['currency']) : '$';
        printf(
            '<input type="number" step="1" id="grant_cap_domestic" name="ksrad_options[grant_cap_domestic]" value="%s" />',
            isset($this->options['grant_cap_domestic']) ? esc_attr($this->options['grant_cap_domestic']) : '7500'
        );
        echo ' <span class="description">' . $currency . ' (maximum grant for residential/domestic)</span>';
    }
    
    public function grant_rate_non_domestic_callback() {
        printf(
            '<input type="number" step="0.1" id="grant_rate_non_domestic" name="ksrad_options[grant_rate_non_domestic]" value="%s" />',
            isset($this->options['grant_rate_non_domestic']) ? esc_attr($this->options['grant_rate_non_domestic']) : '30'
        );
        echo ' <span class="description">% (grant percentage for commercial/non-domestic installations)</span>';
    }
    
    public function grant_cap_non_domestic_callback() {
        $currency = isset($this->options['currency']) ? esc_html($this->options['currency']) : '$';
        printf(
            '<input type="number" step="1" id="grant_cap_non_domestic" name="ksrad_options[grant_cap_non_domestic]" value="%s" />',
            isset($this->options['grant_cap_non_domestic']) ? esc_attr($this->options['grant_cap_non_domestic']) : '50000'
        );
        echo ' <span class="description">' . $currency . ' (maximum grant for commercial/non-domestic)</span>';
    }
    
    public function aca_rate_callback() {
        printf(
            '<input type="number" step="0.1" id="aca_rate" name="ksrad_options[aca_rate]" value="%s" placeholder="Optional" />',
            isset($this->options['aca_rate']) ? esc_attr($this->options['aca_rate']) : ''
        );
        echo ' <span class="description">% (optional accelerated capital allowance rate)</span>';
    }
    
    public function financial_analysis_notes_callback() {
        printf(
            '<textarea id="financial_analysis_notes" name="ksrad_options[financial_analysis_notes]" rows="6" class="large-text" style="max-width: 600px; width: 100%%;">%s</textarea>',
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
    
    public function grants_table_callback_DISABLED() {
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
    
    public function cost_domestic_callback() {
        $currency = isset($this->options['currency']) ? esc_html($this->options['currency']) : '$';
        printf(
            '<input type="number" step="1" id="cost_domestic" name="ksrad_options[cost_domestic]" value="%s" />',
            isset($this->options['cost_domestic']) ? esc_attr($this->options['cost_domestic']) : '2500'
        );
        echo ' <span class="description">' . $currency . '/kWp (cost per kW for residential/domestic installations)</span>';
    }
    
    public function cost_small_callback() {
        $currency = isset($this->options['currency']) ? esc_html($this->options['currency']) : '$';
        printf(
            '<input type="number" step="1" id="cost_small" name="ksrad_options[cost_small]" value="%s" />',
            isset($this->options['cost_small']) ? esc_attr($this->options['cost_small']) : '2000'
        );
        echo ' <span class="description">' . $currency . '/kWp (cost per kW for small commercial, <50kW)</span>';
    }
    
    public function cost_medium_callback() {
        $currency = isset($this->options['currency']) ? esc_html($this->options['currency']) : '$';
        printf(
            '<input type="number" step="1" id="cost_medium" name="ksrad_options[cost_medium]" value="%s" />',
            isset($this->options['cost_medium']) ? esc_attr($this->options['cost_medium']) : '1700'
        );
        echo ' <span class="description">' . $currency . '/kWp (cost per kW for medium commercial, 50-250kW)</span>';
    }
    
    public function cost_large_callback() {
        $currency = isset($this->options['currency']) ? esc_html($this->options['currency']) : '$';
        printf(
            '<input type="number" step="1" id="cost_large" name="ksrad_options[cost_large]" value="%s" />',
            isset($this->options['cost_large']) ? esc_attr($this->options['cost_large']) : '1500'
        );
        echo ' <span class="description">' . $currency . '/kWp (cost per kW for large commercial, >250kW)</span>';
    }
    
    public function system_size_table_callback_DISABLED() {
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
