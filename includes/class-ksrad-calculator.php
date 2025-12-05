<?php
/**
 * Solar Calculator class for Keiste Solar Report
 * 
 * Handles solar panel calculations and renders the calculator frontend.
 *
 * @package Keiste_Solar_Report
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class KSRAD_Calculator {
    
    /**
     * Initialize the calculator
     */
    public static function init() {
        // Register shortcode
        add_shortcode('keiste_solar_calculator', array(__CLASS__, 'render_calculator'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        
        // Register AJAX handlers
        add_action('wp_ajax_ksrad_calculate', array(__CLASS__, 'ajax_calculate'));
        add_action('wp_ajax_nopriv_ksrad_calculate', array(__CLASS__, 'ajax_calculate'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_assets() {
        // Only load on pages with the shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'keiste_solar_calculator')) {
            wp_enqueue_style(
                'ksrad-calculator',
                KSRAD_PLUGIN_URL . 'assets/css/calculator.css',
                array(),
                KSRAD_VERSION
            );
            
            wp_enqueue_script(
                'ksrad-calculator',
                KSRAD_PLUGIN_URL . 'assets/js/calculator.js',
                array('jquery'),
                KSRAD_VERSION,
                true
            );
            
            wp_localize_script('ksrad-calculator', 'ksradAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => esc_js(wp_create_nonce('ksrad_calculate_nonce')),
            ));
        }
    }
    
    /**
     * Render the calculator shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_calculator($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Solar Savings Calculator', 'keiste-solar-report'),
            'show_form' => 'yes',
        ), $atts, 'keiste_solar_calculator');
        
        ob_start();
        ?>
        <div class="ksrad-calculator-wrapper">
            <?php if (!empty($atts['title'])) : ?>
                <h2 class="ksrad-calculator-title"><?php echo esc_html($atts['title']); ?></h2>
            <?php endif; ?>
            
            <div class="ksrad-calculator-form">
                <div class="ksrad-form-group">
                    <label for="ksrad-monthly-bill">
                        <?php esc_html_e('Average Monthly Electric Bill ($)', 'keiste-solar-report'); ?>
                    </label>
                    <input 
                        type="number" 
                        id="ksrad-monthly-bill" 
                        name="monthly_bill" 
                        min="0" 
                        step="0.01" 
                        placeholder="150.00"
                        required
                    />
                </div>
                
                <div class="ksrad-form-group">
                    <label for="ksrad-roof-type">
                        <?php esc_html_e('Roof Type', 'keiste-solar-report'); ?>
                    </label>
                    <select id="ksrad-roof-type" name="roof_type">
                        <option value="asphalt"><?php esc_html_e('Asphalt Shingles', 'keiste-solar-report'); ?></option>
                        <option value="metal"><?php esc_html_e('Metal', 'keiste-solar-report'); ?></option>
                        <option value="tile"><?php esc_html_e('Tile', 'keiste-solar-report'); ?></option>
                        <option value="flat"><?php esc_html_e('Flat', 'keiste-solar-report'); ?></option>
                        <option value="other"><?php esc_html_e('Other', 'keiste-solar-report'); ?></option>
                    </select>
                </div>
                
                <div class="ksrad-form-group">
                    <label for="ksrad-electricity-rate">
                        <?php esc_html_e('Electricity Rate ($/kWh)', 'keiste-solar-report'); ?>
                    </label>
                    <input 
                        type="number" 
                        id="ksrad-electricity-rate" 
                        name="electricity_rate" 
                        min="0" 
                        step="0.001" 
                        value="0.13" 
                        placeholder="0.13"
                    />
                    <small class="ksrad-help-text"><?php esc_html_e('Average U.S. rate is $0.13/kWh', 'keiste-solar-report'); ?></small>
                </div>
                
                <button type="button" id="ksrad-calculate-btn" class="ksrad-btn ksrad-btn-primary">
                    <?php esc_html_e('Calculate Savings', 'keiste-solar-report'); ?>
                </button>
            </div>
            
            <div class="ksrad-results" style="display: none;">
                <h3><?php esc_html_e('Your Solar Savings Estimate', 'keiste-solar-report'); ?></h3>
                
                <div class="ksrad-results-grid">
                    <div class="ksrad-result-card">
                        <div class="ksrad-result-label"><?php esc_html_e('Recommended System Size', 'keiste-solar-report'); ?></div>
                        <div class="ksrad-result-value" id="ksrad-system-size">-</div>
                    </div>
                    
                    <div class="ksrad-result-card">
                        <div class="ksrad-result-label"><?php esc_html_e('Estimated Cost', 'keiste-solar-report'); ?></div>
                        <div class="ksrad-result-value" id="ksrad-estimated-cost">-</div>
                    </div>
                    
                    <div class="ksrad-result-card">
                        <div class="ksrad-result-label"><?php esc_html_e('Annual Savings', 'keiste-solar-report'); ?></div>
                        <div class="ksrad-result-value" id="ksrad-annual-savings">-</div>
                    </div>
                    
                    <div class="ksrad-result-card">
                        <div class="ksrad-result-label"><?php esc_html_e('25-Year Savings', 'keiste-solar-report'); ?></div>
                        <div class="ksrad-result-value" id="ksrad-lifetime-savings">-</div>
                    </div>
                    
                    <div class="ksrad-result-card">
                        <div class="ksrad-result-label"><?php esc_html_e('Payback Period', 'keiste-solar-report'); ?></div>
                        <div class="ksrad-result-value" id="ksrad-payback-period">-</div>
                    </div>
                    
                    <div class="ksrad-result-card">
                        <div class="ksrad-result-label"><?php esc_html_e('CO₂ Offset (25 years)', 'keiste-solar-report'); ?></div>
                        <div class="ksrad-result-value" id="ksrad-co2-offset">-</div>
                    </div>
                </div>
                
                <?php if ($atts['show_form'] === 'yes') : ?>
                    <div class="ksrad-lead-form-wrapper">
                        <h3><?php esc_html_e('Get Your Free Solar Quote', 'keiste-solar-report'); ?></h3>
                        <p><?php esc_html_e('Fill out the form below and we\'ll send you a detailed solar report with a custom quote for your home.', 'keiste-solar-report'); ?></p>
                        <?php echo do_shortcode('[keiste_solar_lead_form]'); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="ksrad-loading" style="display: none;">
                <div class="ksrad-spinner"></div>
                <p><?php esc_html_e('Calculating...', 'keiste-solar-report'); ?></p>
            </div>
            
            <div class="ksrad-error" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for solar calculations
     */
    public static function ajax_calculate() {
        check_ajax_referer('ksrad_calculate_nonce', 'nonce');
        
        // Validate and sanitize monthly bill
        $monthly_bill = isset($_POST['monthly_bill']) ? floatval($_POST['monthly_bill']) : 0;
        if ($monthly_bill <= 0 || $monthly_bill > 999999) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid monthly bill amount between $0 and $999,999.', 'keiste-solar-report')
            ));
        }
        
        // Validate and sanitize electricity rate
        $electricity_rate = isset($_POST['electricity_rate']) ? floatval($_POST['electricity_rate']) : 0.13;
        if ($electricity_rate <= 0 || $electricity_rate > 10) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid electricity rate between $0.01 and $10.00 per kWh.', 'keiste-solar-report')
            ));
        }
        
        // Validate and sanitize roof type
        $allowed_roof_types = array('asphalt', 'metal', 'tile', 'flat', 'other');
        $roof_type = isset($_POST['roof_type']) ? sanitize_text_field(wp_unslash($_POST['roof_type'])) : 'asphalt';
        if (!in_array($roof_type, $allowed_roof_types, true)) {
            $roof_type = 'asphalt';
        }
        
        $calculations = self::calculate_solar_savings($monthly_bill, $electricity_rate, $roof_type);
        
        wp_send_json_success($calculations);
    }
    
    /**
     * Calculate solar savings based on inputs
     *
     * @param float $monthly_bill Average monthly electric bill
     * @param float $electricity_rate Cost per kWh
     * @param string $roof_type Type of roof
     * @return array Calculation results
     */
    public static function calculate_solar_savings($monthly_bill, $electricity_rate = 0.13, $roof_type = 'asphalt') {
        // Calculate annual consumption in kWh
        $annual_bill = $monthly_bill * 12;
        $annual_consumption = $annual_bill / $electricity_rate;
        
        // Calculate system size needed (kW)
        // Assuming average 1,400 kWh per kW of solar per year (varies by location)
        $production_ratio = 1400;
        $system_size_kw = $annual_consumption / $production_ratio;
        
        // Adjust for roof type efficiency
        $roof_efficiency = array(
            'asphalt' => 1.0,
            'metal' => 1.05,
            'tile' => 0.95,
            'flat' => 0.90,
            'other' => 0.95,
        );
        $efficiency = $roof_efficiency[$roof_type] ?? 1.0;
        $system_size_kw = $system_size_kw / $efficiency;
        
        // Calculate costs
        // Average cost per watt is $2.75 (varies by location and installer)
        $cost_per_watt = 2.75;
        $system_cost = $system_size_kw * 1000 * $cost_per_watt;
        
        // Apply federal tax credit (30% as of 2024)
        $tax_credit = 0.30;
        $cost_after_incentives = $system_cost * (1 - $tax_credit);
        
        // Calculate savings
        $annual_savings = $annual_bill * 0.90; // Assuming 90% offset
        $electricity_increase = 0.03; // 3% annual increase
        
        // Calculate 25-year savings with electricity rate increase
        $lifetime_savings = 0;
        for ($year = 1; $year <= 25; $year++) {
            $savings_this_year = $annual_savings * pow(1 + $electricity_increase, $year - 1);
            $lifetime_savings += $savings_this_year;
        }
        
        // Calculate payback period
        $payback_period = $cost_after_incentives / $annual_savings;
        
        // Calculate CO2 offset
        // Average 0.92 lbs CO2 per kWh in the US
        $co2_per_kwh = 0.92;
        $annual_co2_offset = $annual_consumption * $co2_per_kwh;
        $lifetime_co2_offset = $annual_co2_offset * 25;
        
        return array(
            'system_size_kw' => round($system_size_kw, 2),
            'system_cost' => round($system_cost, 2),
            'cost_after_incentives' => round($cost_after_incentives, 2),
            'annual_savings' => round($annual_savings, 2),
            'lifetime_savings' => round($lifetime_savings, 2),
            'payback_period' => round($payback_period, 1),
            'annual_co2_offset' => round($annual_co2_offset, 2),
            'lifetime_co2_offset' => round($lifetime_co2_offset, 2),
            'annual_consumption' => round($annual_consumption, 2),
        );
    }
}
