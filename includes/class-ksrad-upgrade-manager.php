<?php
/**
 * Upgrade Manager for Keiste Solar Report
 * 
 * Handles detection of premium version and upgrade paths.
 *
 * @package Keiste_Solar_Report
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class KSRAD_Upgrade_Manager {
    
    /**
     * Premium plugin slug
     */
    const PREMIUM_SLUG = 'keiste-solar-premium';
    
    /**
     * Premium plugin file
     */
    const PREMIUM_FILE = 'keiste-solar-premium/keiste-solar-premium.php';
    
    /**
     * Initialize the upgrade manager
     */
    public static function init() {
        // Add admin notices
        add_action('admin_notices', array(__CLASS__, 'show_admin_notices'));
        
        // Add upgrade menu item
        add_action('admin_menu', array(__CLASS__, 'add_upgrade_menu'), 100);
        
        // Check for premium plugin on activation
        add_action('admin_init', array(__CLASS__, 'check_premium_conflict'));
        
        // Add upgrade callout in admin pages
        add_action('admin_footer', array(__CLASS__, 'add_upgrade_callout'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }
    
    /**
     * Check if premium version is installed
     *
     * @return bool
     */
    public static function is_premium_installed() {
        $plugin_file = WP_PLUGIN_DIR . '/' . self::PREMIUM_FILE;
        return file_exists($plugin_file);
    }
    
    /**
     * Check if premium version is active
     *
     * @return bool
     */
    public static function is_premium_active() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        return is_plugin_active(self::PREMIUM_FILE);
    }
    
    /**
     * Check for conflicts with premium version
     */
    public static function check_premium_conflict() {
        // If premium is active, show notice to deactivate free version
        if (self::is_premium_active()) {
            add_action('admin_notices', array(__CLASS__, 'show_premium_active_notice'));
            
            // Auto-deactivate free version
            if (current_user_can('activate_plugins')) {
                deactivate_plugins(KSRAD_PLUGIN_BASENAME);
            }
        }
    }
    
    /**
     * Show notice when premium is active
     */
    public static function show_premium_active_notice() {
        ?>
        <div class="notice notice-info">
            <p>
                <strong><?php esc_html_e('Keiste Solar Report Premium is active!', 'keiste-solar-report'); ?></strong>
                <?php esc_html_e('The free version has been automatically deactivated. You can safely delete the free plugin.', 'keiste-solar-report'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Show admin notices about upgrades
     */
    public static function show_admin_notices() {
        // Don't show if premium is active
        if (self::is_premium_active()) {
            return;
        }
        
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'keiste-solar-report') === false) {
            return;
        }
        
        // Check if notice was dismissed
        if (get_option('ksrad_upgrade_notice_dismissed', false)) {
            return;
        }
        
        ?>
        <div class="notice notice-warning is-dismissible ksrad-upgrade-notice">
            <h3><?php esc_html_e('🚀 Upgrade to Premium for Advanced Features!', 'keiste-solar-report'); ?></h3>
            <p><?php esc_html_e('Get access to:', 'keiste-solar-report'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php esc_html_e('Google Maps integration for accurate solar data', 'keiste-solar-report'); ?></li>
                <li><?php esc_html_e('Roof area measurement and analysis', 'keiste-solar-report'); ?></li>
                <li><?php esc_html_e('Real-time solar potential calculations', 'keiste-solar-report'); ?></li>
                <li><?php esc_html_e('Advanced lead management and CRM integration', 'keiste-solar-report'); ?></li>
                <li><?php esc_html_e('Custom branding and white-label options', 'keiste-solar-report'); ?></li>
                <li><?php esc_html_e('Priority support and updates', 'keiste-solar-report'); ?></li>
            </ul>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=keiste-solar-report-upgrade')); ?>" class="button button-primary">
                    <?php esc_html_e('Learn More About Premium', 'keiste-solar-report'); ?>
                </a>
                <a href="#" class="button ksrad-dismiss-notice">
                    <?php esc_html_e('Dismiss', 'keiste-solar-report'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Add upgrade menu item
     */
    public static function add_upgrade_menu() {
        // Don't show if premium is active
        if (self::is_premium_active()) {
            return;
        }
        
        add_submenu_page(
            'keiste-solar-report',
            __('Upgrade to Premium', 'keiste-solar-report'),
            '<span style="color: #FCB214;">⭐ ' . esc_html__('Upgrade', 'keiste-solar-report') . '</span>',
            'manage_options',
            'keiste-solar-report-upgrade',
            array(__CLASS__, 'render_upgrade_page')
        );
    }
    
    /**
     * Render upgrade page
     */
    public static function render_upgrade_page() {
        ?>
        <div class="wrap ksrad-upgrade-page">
            <h1><?php esc_html_e('Upgrade to Keiste Solar Report Premium', 'keiste-solar-report'); ?></h1>
            
            <div class="ksrad-upgrade-hero">
                <h2><?php esc_html_e('Take Your Solar Lead Generation to the Next Level', 'keiste-solar-report'); ?></h2>
                <p class="subtitle"><?php esc_html_e('Get accurate solar data with Google Maps integration and advanced features', 'keiste-solar-report'); ?></p>
            </div>
            
            <div class="ksrad-feature-comparison">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40%;"><?php esc_html_e('Feature', 'keiste-solar-report'); ?></th>
                            <th style="text-align: center; width: 30%;"><?php esc_html_e('Free Version', 'keiste-solar-report'); ?></th>
                            <th style="text-align: center; width: 30%; background: #FCB214;"><?php esc_html_e('Premium Version', 'keiste-solar-report'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Basic Solar Calculator', 'keiste-solar-report'); ?></strong></td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Lead Capture Forms', 'keiste-solar-report'); ?></strong></td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Email Notifications', 'keiste-solar-report'); ?></strong></td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('CSV Export', 'keiste-solar-report'); ?></strong></td>
                            <td style="text-align: center;">✅</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Google Maps Integration', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Accurate location-based calculations', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Solar API Integration', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Real-time solar data from Google', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Roof Area Measurement', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Automatic roof detection and sizing', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Shading Analysis', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Account for trees and obstructions', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Custom Branding', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Add your logo and colors', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('CRM Integration', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Connect with popular CRMs', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Advanced Lead Management', 'keiste-solar-report'); ?></strong><br>
                                <small><?php esc_html_e('Lead scoring, tags, and workflows', 'keiste-solar-report'); ?></small>
                            </td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Priority Support', 'keiste-solar-report'); ?></strong></td>
                            <td style="text-align: center;">❌</td>
                            <td style="text-align: center;">✅</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="ksrad-pricing">
                <h2><?php esc_html_e('Simple, Transparent Pricing', 'keiste-solar-report'); ?></h2>
                <div class="ksrad-pricing-card">
                    <h3><?php esc_html_e('Premium License', 'keiste-solar-report'); ?></h3>
                    <div class="price">$99 <span>/year</span></div>
                    <ul>
                        <li>✅ <?php esc_html_e('All Premium Features', 'keiste-solar-report'); ?></li>
                        <li>✅ <?php esc_html_e('1 Year of Updates', 'keiste-solar-report'); ?></li>
                        <li>✅ <?php esc_html_e('1 Year of Support', 'keiste-solar-report'); ?></li>
                        <li>✅ <?php esc_html_e('Unlimited Sites', 'keiste-solar-report'); ?></li>
                        <li>✅ <?php esc_html_e('30-Day Money Back Guarantee', 'keiste-solar-report'); ?></li>
                    </ul>
                    <a href="https://keiste.com/solar-report-premium" class="button button-primary button-hero" target="_blank">
                        <?php esc_html_e('Upgrade Now →', 'keiste-solar-report'); ?>
                    </a>
                </div>
            </div>
            
            <div class="ksrad-upgrade-faq">
                <h2><?php esc_html_e('Frequently Asked Questions', 'keiste-solar-report'); ?></h2>
                
                <h3><?php esc_html_e('Will my existing data be preserved?', 'keiste-solar-report'); ?></h3>
                <p><?php esc_html_e('Yes! When you upgrade to premium, all your existing leads and settings will be automatically imported. Nothing is lost in the transition.', 'keiste-solar-report'); ?></p>
                
                <h3><?php esc_html_e('Do I need a Google API key?', 'keiste-solar-report'); ?></h3>
                <p><?php esc_html_e('Yes, the premium version requires a Google API key for Maps and Solar API access. We provide detailed instructions on getting your free API key (includes $200 free credit monthly).', 'keiste-solar-report'); ?></p>
                
                <h3><?php esc_html_e('Can I use premium on multiple sites?', 'keiste-solar-report'); ?></h3>
                <p><?php esc_html_e('Yes! One license covers unlimited sites that you own or manage.', 'keiste-solar-report'); ?></p>
                
                <h3><?php esc_html_e('What happens when my license expires?', 'keiste-solar-report'); ?></h3>
                <p><?php esc_html_e('The plugin continues to work, but you won\'t receive updates or support. You can renew at any time at a discounted rate.', 'keiste-solar-report'); ?></p>
                
                <h3><?php esc_html_e('Is there a money-back guarantee?', 'keiste-solar-report'); ?></h3>
                <p><?php esc_html_e('Yes! We offer a 30-day money-back guarantee. If you\'re not satisfied, we\'ll refund your purchase, no questions asked.', 'keiste-solar-report'); ?></p>
            </div>
            
            <div class="ksrad-upgrade-cta">
                <h2><?php esc_html_e('Ready to Upgrade?', 'keiste-solar-report'); ?></h2>
                <p><?php esc_html_e('Get accurate solar calculations with Google integration and close more leads.', 'keiste-solar-report'); ?></p>
                <a href="https://keiste.com/solar-report-premium" class="button button-primary button-hero" target="_blank">
                    <?php esc_html_e('Get Premium Now →', 'keiste-solar-report'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add upgrade callout to calculator results
     */
    public static function add_upgrade_callout() {
        // Only show on plugin admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'keiste-solar-report') === false) {
            return;
        }
        
        // Don't show if premium is active
        if (self::is_premium_active()) {
            return;
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'keiste-solar-report') === false) {
            return;
        }
        
        wp_enqueue_script(
            'ksrad-admin',
            KSRAD_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            KSRAD_VERSION,
            true
        );
        
        wp_localize_script(
            'ksrad-admin',
            'ksradAdmin',
            array(
                'dismissNonce' => wp_create_nonce('ksrad_dismiss_notice')
            )
        );
    }
    
    /**
     * Get data migration status
     *
     * @return array Migration status information
     */
    public static function get_migration_status() {
        return array(
            'total_leads' => KSRAD_Database::get_total_leads(),
            'database_version' => get_option('ksrad_db_version', '1.0.0'),
            'settings' => array(
                'notification_email' => get_option('ksrad_notification_email'),
                'send_confirmation' => get_option('ksrad_send_confirmation'),
                'electricity_rate' => get_option('ksrad_default_electricity_rate'),
                'cost_per_watt' => get_option('ksrad_cost_per_watt'),
                'tax_credit' => get_option('ksrad_tax_credit'),
            )
        );
    }
    
    /**
     * Prepare data for premium migration
     * This creates a backup that premium can import
     *
     * @return string Path to export file
     */
    public static function export_for_premium() {
        $data = array(
            'version' => KSRAD_VERSION,
            'exported_at' => current_time('mysql'),
            'leads' => array(),
            'settings' => self::get_migration_status()['settings']
        );
        
        // Get all leads
        global $wpdb;
        $table_name = $wpdb->prefix . 'ksrad_solar_leads';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration export, table name is safe (wpdb prefix + hardcoded string)
        $leads = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC", ARRAY_A);
        
        if (!empty($leads)) {
            $data['leads'] = $leads;
        }
        
        // Create export file
        $upload_dir = wp_upload_dir();
        $export_file = $upload_dir['basedir'] . '/ksrad-premium-migration-' . time() . '.json';
        
        file_put_contents($export_file, wp_json_encode($data, JSON_PRETTY_PRINT));
        
        return $export_file;
    }
}
