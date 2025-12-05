<?php
/**
 * Lead Form class for Keiste Solar Report
 * 
 * Handles lead capture form rendering and submission.
 *
 * @package Keiste_Solar_Report
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class KSRAD_Lead_Form {
    
    /**
     * Initialize the lead form
     */
    public static function init() {
        // Register shortcode
        add_shortcode('keiste_solar_lead_form', array(__CLASS__, 'render_form'));
        
        // Register AJAX handlers
        add_action('wp_ajax_ksrad_submit_lead', array(__CLASS__, 'ajax_submit_lead'));
        add_action('wp_ajax_nopriv_ksrad_submit_lead', array(__CLASS__, 'ajax_submit_lead'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_assets() {
        // Only load on pages with either shortcode
        global $post;
        if (is_a($post, 'WP_Post') && 
            (has_shortcode($post->post_content, 'keiste_solar_lead_form') || 
             has_shortcode($post->post_content, 'keiste_solar_calculator'))) {
            
            wp_enqueue_style(
                'ksrad-lead-form',
                KSRAD_PLUGIN_URL . 'assets/css/lead-form.css',
                array(),
                KSRAD_VERSION
            );
            
            wp_enqueue_script(
                'ksrad-lead-form',
                KSRAD_PLUGIN_URL . 'assets/js/lead-form.js',
                array('jquery'),
                KSRAD_VERSION,
                true
            );
            
            wp_localize_script('ksrad-lead-form', 'ksradLeadAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ksrad_lead_nonce'),
            ));
        }
    }
    
    /**
     * Render the lead form shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_form($atts) {
        $atts = shortcode_atts(array(
            'title' => '',
            'button_text' => __('Get My Free Quote', 'keiste-solar-report'),
        ), $atts, 'keiste_solar_lead_form');
        
        ob_start();
        ?>
        <div class="ksrad-lead-form-wrapper">
            <?php if (!empty($atts['title'])) : ?>
                <h3 class="ksrad-form-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <form id="ksrad-lead-form" class="ksrad-lead-form">
                <input type="hidden" name="action" value="ksrad_submit_lead">
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('ksrad_lead_nonce'); ?>">
                
                <!-- Hidden fields for calculator data -->
                <input type="hidden" name="monthly_bill" id="ksrad-lead-monthly-bill" value="">
                <input type="hidden" name="roof_type" id="ksrad-lead-roof-type" value="">
                <input type="hidden" name="estimated_system_size" id="ksrad-lead-system-size" value="">
                <input type="hidden" name="estimated_cost" id="ksrad-lead-cost" value="">
                <input type="hidden" name="estimated_savings" id="ksrad-lead-savings" value="">
                
                <div class="ksrad-form-row">
                    <div class="ksrad-form-group">
                        <label for="ksrad-lead-name">
                            <?php _e('Full Name', 'keiste-solar-report'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="ksrad-lead-name" 
                            name="name" 
                            required
                            placeholder="<?php esc_attr_e('John Doe', 'keiste-solar-report'); ?>"
                        />
                    </div>
                    
                    <div class="ksrad-form-group">
                        <label for="ksrad-lead-email">
                            <?php _e('Email Address', 'keiste-solar-report'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="ksrad-lead-email" 
                            name="email" 
                            required
                            placeholder="<?php esc_attr_e('john@example.com', 'keiste-solar-report'); ?>"
                        />
                    </div>
                </div>
                
                <div class="ksrad-form-row">
                    <div class="ksrad-form-group">
                        <label for="ksrad-lead-phone">
                            <?php _e('Phone Number', 'keiste-solar-report'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="tel" 
                            id="ksrad-lead-phone" 
                            name="phone" 
                            required
                            placeholder="<?php esc_attr_e('(555) 123-4567', 'keiste-solar-report'); ?>"
                        />
                    </div>
                    
                    <div class="ksrad-form-group">
                        <label for="ksrad-lead-address">
                            <?php _e('Address', 'keiste-solar-report'); ?> <span class="required">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="ksrad-lead-address" 
                            name="address" 
                            required
                            placeholder="<?php esc_attr_e('123 Main St, City, State', 'keiste-solar-report'); ?>"
                        />
                    </div>
                </div>
                
                <div class="ksrad-form-group">
                    <label for="ksrad-lead-notes">
                        <?php _e('Additional Notes (Optional)', 'keiste-solar-report'); ?>
                    </label>
                    <textarea 
                        id="ksrad-lead-notes" 
                        name="notes" 
                        rows="4"
                        placeholder="<?php esc_attr_e('Any specific questions or requirements?', 'keiste-solar-report'); ?>"
                    ></textarea>
                </div>
                
                <div class="ksrad-form-group ksrad-form-consent">
                    <label>
                        <input type="checkbox" name="consent" required>
                        <?php _e('I agree to receive information about solar solutions and understand my information will be used according to the privacy policy.', 'keiste-solar-report'); ?>
                        <span class="required">*</span>
                    </label>
                </div>
                
                <button type="submit" class="ksrad-btn ksrad-btn-primary ksrad-submit-btn">
                    <?php echo esc_html($atts['button_text']); ?>
                </button>
                
                <div class="ksrad-form-message" style="display: none;"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for lead form submission
     */
    public static function ajax_submit_lead() {
        check_ajax_referer('ksrad_lead_nonce', 'nonce');
        
        // Validate required fields
        $required_fields = array('name', 'email', 'phone', 'address');
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                wp_send_json_error(array(
                    'message' => sprintf(__('Please fill in the %s field.', 'keiste-solar-report'), esc_html($field))
                ));
            }
        }
        
        // Validate and sanitize name
        $name = sanitize_text_field($_POST['name']);
        if (strlen($name) < 2 || strlen($name) > 255) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid name (2-255 characters).', 'keiste-solar-report')
            ));
        }
        
        // Validate email
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid email address.', 'keiste-solar-report')
            ));
        }
        
        // Validate and sanitize phone
        $phone = sanitize_text_field($_POST['phone']);
        if (strlen($phone) < 10 || strlen($phone) > 50) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid phone number.', 'keiste-solar-report')
            ));
        }
        
        // Validate and sanitize address
        $address = sanitize_textarea_field($_POST['address']);
        if (strlen($address) < 5 || strlen($address) > 500) {
            wp_send_json_error(array(
                'message' => __('Please enter a valid address (5-500 characters).', 'keiste-solar-report')
            ));
        }
        
        // Check for consent
        if (empty($_POST['consent']) || $_POST['consent'] !== 'on') {
            wp_send_json_error(array(
                'message' => __('Please agree to the terms to continue.', 'keiste-solar-report')
            ));
        }
        
        // Validate and sanitize numeric fields
        $monthly_bill = isset($_POST['monthly_bill']) ? floatval($_POST['monthly_bill']) : 0;
        $estimated_system_size = isset($_POST['estimated_system_size']) ? floatval($_POST['estimated_system_size']) : 0;
        $estimated_cost = isset($_POST['estimated_cost']) ? floatval($_POST['estimated_cost']) : 0;
        $estimated_savings = isset($_POST['estimated_savings']) ? floatval($_POST['estimated_savings']) : 0;
        
        // Validate roof type
        $allowed_roof_types = array('asphalt', 'metal', 'tile', 'flat', 'other', '');
        $roof_type = isset($_POST['roof_type']) ? sanitize_text_field($_POST['roof_type']) : '';
        if (!in_array($roof_type, $allowed_roof_types, true)) {
            $roof_type = '';
        }
        
        // Sanitize notes
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        if (strlen($notes) > 2000) {
            $notes = substr($notes, 0, 2000);
        }
        
        // Prepare lead data
        $lead_data = array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'monthly_bill' => $monthly_bill,
            'roof_type' => $roof_type,
            'estimated_system_size' => $estimated_system_size,
            'estimated_cost' => $estimated_cost,
            'estimated_savings' => $estimated_savings,
            'notes' => $notes,
        );
        
        // Insert lead into database
        $result = KSRAD_Database::insert_lead($lead_data);
        
        if ($result === false) {
            wp_send_json_error(array(
                'message' => __('There was an error submitting your information. Please try again.', 'keiste-solar-report')
            ));
        }
        
        // Send notification email to admin
        self::send_admin_notification($lead_data);
        
        // Send confirmation email to lead
        self::send_lead_confirmation($lead_data);
        
        // Success response
        wp_send_json_success(array(
            'message' => __('Thank you! Your information has been submitted. We\'ll contact you soon with your personalized solar quote.', 'keiste-solar-report')
        ));
    }
    
    /**
     * Send notification email to admin
     *
     * @param array $lead_data Lead information
     */
    private static function send_admin_notification($lead_data) {
        $admin_email = get_option('ksrad_notification_email', get_option('admin_email'));
        
        // Validate admin email
        if (empty($admin_email) || !is_email($admin_email)) {
            return;
        }
        
        // Sanitize subject line
        $subject = sprintf(
            __('[%s] New Solar Lead: %s', 'keiste-solar-report'), 
            sanitize_text_field(get_bloginfo('name')), 
            sanitize_text_field($lead_data['name'])
        );
        
        // Build sanitized message
        $message = sprintf(
            __("New solar lead received:\n\nName: %s\nEmail: %s\nPhone: %s\nAddress: %s\nMonthly Bill: $%s\nRoof Type: %s\nEstimated System Size: %s kW\nEstimated Cost: $%s\nEstimated Savings: $%s\nNotes: %s\n\nView all leads in the admin panel.", 'keiste-solar-report'),
            sanitize_text_field($lead_data['name']),
            sanitize_email($lead_data['email']),
            sanitize_text_field($lead_data['phone']),
            sanitize_text_field($lead_data['address']),
            number_format(floatval($lead_data['monthly_bill']), 2),
            sanitize_text_field($lead_data['roof_type']),
            number_format(floatval($lead_data['estimated_system_size']), 2),
            number_format(floatval($lead_data['estimated_cost']), 2),
            number_format(floatval($lead_data['estimated_savings']), 2),
            sanitize_textarea_field($lead_data['notes'])
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . sanitize_text_field(get_bloginfo('name')) . ' <' . sanitize_email(get_option('admin_email')) . '>'
        );
        
        wp_mail($admin_email, $subject, $message, $headers);
    }
    
    /**
     * Send confirmation email to lead
     *
     * @param array $lead_data Lead information
     */
    private static function send_lead_confirmation($lead_data) {
        $send_confirmation = get_option('ksrad_send_confirmation', '1');
        
        if ($send_confirmation !== '1') {
            return;
        }
        
        // Validate recipient email
        $recipient_email = sanitize_email($lead_data['email']);
        if (!is_email($recipient_email)) {
            return;
        }
        
        // Sanitize subject line
        $subject = sprintf(
            __('Thank you for your interest in solar - %s', 'keiste-solar-report'), 
            sanitize_text_field(get_bloginfo('name'))
        );
        
        // Build sanitized message
        $message = sprintf(
            __("Hi %s,\n\nThank you for requesting a solar quote! We have received your information and will be in touch shortly with a personalized solar analysis for your home.\n\nYour Estimated Results:\n- System Size: %s kW\n- Estimated Cost: $%s\n- Annual Savings: $%s\n\nOur team will review your information and contact you within 24-48 hours.\n\nBest regards,\n%s", 'keiste-solar-report'),
            sanitize_text_field($lead_data['name']),
            number_format(floatval($lead_data['estimated_system_size']), 2),
            number_format(floatval($lead_data['estimated_cost']), 2),
            number_format(floatval($lead_data['estimated_savings']), 2),
            sanitize_text_field(get_bloginfo('name'))
        );
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . sanitize_text_field(get_bloginfo('name')) . ' <' . sanitize_email(get_option('admin_email')) . '>'
        );
        
        wp_mail($recipient_email, $subject, $message, $headers);
    }
}
