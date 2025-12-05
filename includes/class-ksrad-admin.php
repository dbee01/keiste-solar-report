<?php
/**
 * Admin class for Keiste Solar Report
 * 
 * Handles WordPress admin interface, settings, and leads management.
 *
 * @package Keiste_Solar_Report
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class KSRAD_Admin {
    
    /**
     * Initialize the admin
     */
    public static function init() {
        // Add admin menu
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        
        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . KSRAD_PLUGIN_BASENAME, array(__CLASS__, 'add_plugin_action_links'));
        
        // Handle CSV export
        add_action('admin_post_ksrad_export_leads', array(__CLASS__, 'export_leads'));
        
        // Handle lead deletion
        add_action('admin_post_ksrad_delete_lead', array(__CLASS__, 'delete_lead'));
    }
    
    /**
     * Add admin menu pages
     */
    public static function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('Solar Leads', 'keiste-solar-report'),
            __('Solar Leads', 'keiste-solar-report'),
            'manage_options',
            'keiste-solar-report',
            array(__CLASS__, 'render_leads_page'),
            'dashicons-chart-area',
            30
        );
        
        // Leads submenu (default)
        add_submenu_page(
            'keiste-solar-report',
            __('All Leads', 'keiste-solar-report'),
            __('All Leads', 'keiste-solar-report'),
            'manage_options',
            'keiste-solar-report',
            array(__CLASS__, 'render_leads_page')
        );
        
        // Settings submenu
        add_submenu_page(
            'keiste-solar-report',
            __('Settings', 'keiste-solar-report'),
            __('Settings', 'keiste-solar-report'),
            'manage_options',
            'keiste-solar-report-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }
    
    /**
     * Register plugin settings
     */
    public static function register_settings() {
        register_setting('ksrad_settings', 'ksrad_notification_email', array(
            'sanitize_callback' => 'sanitize_email'
        ));
        register_setting('ksrad_settings', 'ksrad_send_confirmation', array(
            'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')
        ));
        register_setting('ksrad_settings', 'ksrad_default_electricity_rate', array(
            'sanitize_callback' => array(__CLASS__, 'sanitize_float')
        ));
        register_setting('ksrad_settings', 'ksrad_cost_per_watt', array(
            'sanitize_callback' => array(__CLASS__, 'sanitize_float')
        ));
        register_setting('ksrad_settings', 'ksrad_tax_credit', array(
            'sanitize_callback' => array(__CLASS__, 'sanitize_float')
        ));
    }
    
    /**
     * Sanitize checkbox value
     */
    public static function sanitize_checkbox($value) {
        return $value ? '1' : '';
    }
    
    /**
     * Sanitize float value
     */
    public static function sanitize_float($value) {
        return floatval($value);
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'keiste-solar-report') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ksrad-admin',
            KSRAD_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            KSRAD_VERSION
        );
    }
    
    /**
     * Add settings link to plugins page
     */
    public static function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=keiste-solar-report-settings'),
            __('Settings', 'keiste-solar-report')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Render leads management page
     */
    public static function render_leads_page() {
        // Handle pagination
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public page view, no state change
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        
        // Get leads
        $leads = KSRAD_Database::get_leads($page, $per_page);
        $total_leads = KSRAD_Database::get_total_leads();
        $total_pages = ceil($total_leads / $per_page);
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Solar Leads', 'keiste-solar-report'); ?></h1>
            
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ksrad_export_leads'), 'ksrad_export_leads')); ?>" class="page-title-action">
                <?php esc_html_e('Export to CSV', 'keiste-solar-report'); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Status message display only
            if (isset($_GET['deleted']) && $_GET['deleted'] === '1') : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Lead deleted successfully.', 'keiste-solar-report'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="ksrad-stats">
                <div class="ksrad-stat-box">
                    <h3><?php echo number_format($total_leads); ?></h3>
                    <p><?php esc_html_e('Total Leads', 'keiste-solar-report'); ?></p>
                </div>
            </div>
            
            <?php if (empty($leads)) : ?>
                <p><?php esc_html_e('No leads found yet. Add the calculator shortcode to your pages:', 'keiste-solar-report'); ?></p>
                <code>[keiste_solar_calculator]</code>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Name', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Email', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Phone', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Monthly Bill', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('System Size', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Est. Cost', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Date', 'keiste-solar-report'); ?></th>
                            <th><?php esc_html_e('Actions', 'keiste-solar-report'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead) : ?>
                            <tr>
                                <td><?php echo esc_html($lead->id); ?></td>
                                <td><strong><?php echo esc_html($lead->name); ?></strong></td>
                                <td><a href="mailto:<?php echo esc_attr($lead->email); ?>"><?php echo esc_html($lead->email); ?></a></td>
                                <td><a href="tel:<?php echo esc_attr($lead->phone); ?>"><?php echo esc_html($lead->phone); ?></a></td>
                                <td>$<?php echo esc_html(number_format(floatval($lead->monthly_bill), 2)); ?></td>
                                <td><?php echo esc_html(number_format(floatval($lead->estimated_system_size), 2)); ?> kW</td>
                                <td>$<?php echo esc_html(number_format(floatval($lead->estimated_cost), 2)); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($lead->created_at))); ?></td>
                                <td>
                                    <a href="#" onclick='showLeadDetails(<?php echo json_encode($lead); ?>); return false;'>
                                        <?php esc_html_e('View', 'keiste-solar-report'); ?>
                                    </a> |
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ksrad_delete_lead&lead_id=' . $lead->id), 'delete_lead_' . $lead->id)); ?>" 
                                       onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this lead?', 'keiste-solar-report'); ?>');">
                                        <?php esc_html_e('Delete', 'keiste-solar-report'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links is safe
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;', 'keiste-solar-report'),
                                'next_text' => __('&raquo;', 'keiste-solar-report'),
                                'total' => $total_pages,
                                'current' => $page
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <!-- Lead Details Modal -->
            <div id="ksrad-lead-modal" style="display: none;">
                <div class="ksrad-modal-content">
                    <span class="ksrad-modal-close" onclick="closeLeadModal();">&times;</span>
                    <div id="ksrad-lead-details"></div>
                </div>
            </div>
        </div>
        
        <script>
        function showLeadDetails(lead) {
            var html = '<h2>' + lead.name + '</h2>';
            html += '<table class="form-table">';
            html += '<tr><th>Email:</th><td><a href="mailto:' + lead.email + '">' + lead.email + '</a></td></tr>';
            html += '<tr><th>Phone:</th><td><a href="tel:' + lead.phone + '">' + lead.phone + '</a></td></tr>';
            html += '<tr><th>Address:</th><td>' + lead.address + '</td></tr>';
            html += '<tr><th>Monthly Bill:</th><td>$' + parseFloat(lead.monthly_bill).toFixed(2) + '</td></tr>';
            html += '<tr><th>Roof Type:</th><td>' + lead.roof_type + '</td></tr>';
            html += '<tr><th>System Size:</th><td>' + parseFloat(lead.estimated_system_size).toFixed(2) + ' kW</td></tr>';
            html += '<tr><th>Estimated Cost:</th><td>$' + parseFloat(lead.estimated_cost).toFixed(2) + '</td></tr>';
            html += '<tr><th>Annual Savings:</th><td>$' + parseFloat(lead.estimated_savings).toFixed(2) + '</td></tr>';
            if (lead.notes) {
                html += '<tr><th>Notes:</th><td>' + lead.notes + '</td></tr>';
            }
            html += '<tr><th>IP Address:</th><td>' + lead.ip_address + '</td></tr>';
            html += '<tr><th>Date:</th><td>' + lead.created_at + '</td></tr>';
            html += '</table>';
            
            document.getElementById('ksrad-lead-details').innerHTML = html;
            document.getElementById('ksrad-lead-modal').style.display = 'block';
        }
        
        function closeLeadModal() {
            document.getElementById('ksrad-lead-modal').style.display = 'none';
        }
        </script>
        
        <style>
        .ksrad-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .ksrad-stat-box {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            min-width: 150px;
        }
        .ksrad-stat-box h3 {
            margin: 0;
            font-size: 32px;
            color: #2271b1;
        }
        .ksrad-stat-box p {
            margin: 10px 0 0;
            color: #666;
        }
        #ksrad-lead-modal {
            position: fixed;
            z-index: 100000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .ksrad-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 800px;
            border-radius: 4px;
        }
        .ksrad-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .ksrad-modal-close:hover {
            color: #000;
        }
        </style>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'keiste-solar-report'));
        }
        
        // Save settings
        if (isset($_POST['ksrad_settings_submit']) && check_admin_referer('ksrad_settings')) {
            // Validate and sanitize email
            $notification_email = isset($_POST['ksrad_notification_email']) ? sanitize_email(wp_unslash($_POST['ksrad_notification_email'])) : '';
            if (!empty($notification_email) && is_email($notification_email)) {
                update_option('ksrad_notification_email', $notification_email);
            }
            
            // Sanitize checkbox
            update_option('ksrad_send_confirmation', isset($_POST['ksrad_send_confirmation']) ? '1' : '0');
            
            // Validate and sanitize electricity rate (0.01 to 10.00)
            $electricity_rate = isset($_POST['ksrad_default_electricity_rate']) ? floatval($_POST['ksrad_default_electricity_rate']) : 0.13;
            if ($electricity_rate > 0 && $electricity_rate <= 10) {
                update_option('ksrad_default_electricity_rate', $electricity_rate);
            }
            
            // Validate and sanitize cost per watt (0.50 to 20.00)
            $cost_per_watt = isset($_POST['ksrad_cost_per_watt']) ? floatval($_POST['ksrad_cost_per_watt']) : 2.75;
            if ($cost_per_watt >= 0.50 && $cost_per_watt <= 20) {
                update_option('ksrad_cost_per_watt', $cost_per_watt);
            }
            
            // Validate and sanitize tax credit (0 to 1)
            $tax_credit = isset($_POST['ksrad_tax_credit']) ? floatval($_POST['ksrad_tax_credit']) : 0.30;
            if ($tax_credit >= 0 && $tax_credit <= 1) {
                update_option('ksrad_tax_credit', $tax_credit);
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'keiste-solar-report') . '</p></div>';
        }
        
        // Get current settings
        $notification_email = get_option('ksrad_notification_email', get_option('admin_email'));
        $send_confirmation = get_option('ksrad_send_confirmation', '1');
        $electricity_rate = get_option('ksrad_default_electricity_rate', '0.13');
        $cost_per_watt = get_option('ksrad_cost_per_watt', '2.75');
        $tax_credit = get_option('ksrad_tax_credit', '0.30');
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Keiste Solar Report Settings', 'keiste-solar-report'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ksrad_settings'); ?>
                
                <h2><?php esc_html_e('Email Settings', 'keiste-solar-report'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ksrad_notification_email"><?php esc_html_e('Notification Email', 'keiste-solar-report'); ?></label>
                        </th>
                        <td>
                            <input type="email" name="ksrad_notification_email" id="ksrad_notification_email" 
                                   value="<?php echo esc_attr($notification_email); ?>" class="regular-text" required>
                            <p class="description"><?php esc_html_e('Email address to receive new lead notifications.', 'keiste-solar-report'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ksrad_send_confirmation"><?php esc_html_e('Send Confirmation Email', 'keiste-solar-report'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" name="ksrad_send_confirmation" id="ksrad_send_confirmation" 
                                   value="1" <?php checked($send_confirmation, '1'); ?>>
                            <label for="ksrad_send_confirmation"><?php esc_html_e('Send confirmation email to leads', 'keiste-solar-report'); ?></label>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Calculator Settings', 'keiste-solar-report'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ksrad_default_electricity_rate"><?php esc_html_e('Default Electricity Rate', 'keiste-solar-report'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="ksrad_default_electricity_rate" id="ksrad_default_electricity_rate" 
                                   value="<?php echo esc_attr($electricity_rate); ?>" step="0.001" min="0.01" max="10" class="small-text" required>
                            <span>$/kWh</span>
                            <p class="description"><?php esc_html_e('Average electricity rate in dollars per kilowatt-hour (0.01-10.00).', 'keiste-solar-report'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ksrad_cost_per_watt"><?php esc_html_e('Cost Per Watt', 'keiste-solar-report'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="ksrad_cost_per_watt" id="ksrad_cost_per_watt" 
                                   value="<?php echo esc_attr($cost_per_watt); ?>" step="0.01" min="0.50" max="20" class="small-text" required>
                            <span>$/W</span>
                            <p class="description"><?php esc_html_e('Average installation cost per watt (0.50-20.00).', 'keiste-solar-report'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="ksrad_tax_credit"><?php esc_html_e('Federal Tax Credit', 'keiste-solar-report'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="ksrad_tax_credit" id="ksrad_tax_credit" 
                                   value="<?php echo esc_attr($tax_credit); ?>" step="0.01" min="0" max="1" class="small-text" required>
                            <span>(<?php echo esc_html(round($tax_credit * 100)); ?>%)</span>
                            <p class="description"><?php esc_html_e('Federal solar tax credit percentage (0.30 = 30%).', 'keiste-solar-report'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php esc_html_e('Shortcodes', 'keiste-solar-report'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Calculator', 'keiste-solar-report'); ?></th>
                        <td>
                            <code>[keiste_solar_calculator]</code>
                            <p class="description"><?php esc_html_e('Display the solar calculator with integrated lead form.', 'keiste-solar-report'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Lead Form Only', 'keiste-solar-report'); ?></th>
                        <td>
                            <code>[keiste_solar_lead_form]</code>
                            <p class="description"><?php esc_html_e('Display only the lead capture form.', 'keiste-solar-report'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(__('Save Settings', 'keiste-solar-report'), 'primary', 'ksrad_settings_submit'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Export leads to CSV
     */
    public static function export_leads() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to export leads.', 'keiste-solar-report'));
        }
        
        // Verify nonce if coming from admin page
        if (isset($_GET['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));
            if (!wp_verify_nonce($nonce, 'ksrad_export_leads')) {
                wp_die(esc_html__('Security check failed.', 'keiste-solar-report'));
            }
        }
        
        $csv = KSRAD_Database::export_to_csv();
        
        if (empty($csv)) {
            wp_die(esc_html__('No leads to export.', 'keiste-solar-report'));
        }
        
        // Security headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="solar-leads-' . sanitize_file_name(gmdate('Y-m-d')) . '.csv"');
        header('Pragma: no-cache');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV data is already sanitized
        echo $csv;
        exit;
    }
    
    /**
     * Delete a lead
     */
    public static function delete_lead() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to delete leads.', 'keiste-solar-report'));
        }
        
        $lead_id = isset($_GET['lead_id']) ? absint($_GET['lead_id']) : 0;
        
        if ($lead_id <= 0) {
            wp_die(esc_html__('Invalid lead ID.', 'keiste-solar-report'));
        }
        
        check_admin_referer('delete_lead_' . $lead_id);
        
        // Verify lead exists before deletion
        $lead = KSRAD_Database::get_lead($lead_id);
        if (!$lead) {
            wp_die(esc_html__('Lead not found.', 'keiste-solar-report'));
        }
        
        $result = KSRAD_Database::delete_lead($lead_id);
        
        if ($result === false) {
            wp_die(esc_html__('Error deleting lead.', 'keiste-solar-report'));
        }
        
        wp_safe_redirect(admin_url('admin.php?page=keiste-solar-report&deleted=1'));
        exit;
    }
}
