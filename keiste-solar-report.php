<?php
/**
 * Plugin Name: Keiste Solar Report
 * Plugin URI: https://keiste.com/keiste-solar-report
 * Description: Simple solar panel calculator and lead capture form for solar businesses.
 * Version: 1.0.0
 * Author: Dara Burke, Keiste
 * Author URI: https://keiste.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: keiste-solar-report
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version
define('KSRAD_VERSION', '1.0.0');
define('KSRAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KSRAD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KSRAD_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once KSRAD_PLUGIN_DIR . 'includes/class-ksrad-calculator.php';
require_once KSRAD_PLUGIN_DIR . 'includes/class-ksrad-lead-form.php';
require_once KSRAD_PLUGIN_DIR . 'includes/class-ksrad-admin.php';
require_once KSRAD_PLUGIN_DIR . 'includes/class-ksrad-database.php';
require_once KSRAD_PLUGIN_DIR . 'includes/class-ksrad-upgrade-manager.php';

/**
 * Initialize the plugin
 */
function ksrad_init() {
    // Initialize components
    KSRAD_Calculator::init();
    KSRAD_Lead_Form::init();
    KSRAD_Admin::init();
    KSRAD_Upgrade_Manager::init();
}
add_action('plugins_loaded', 'ksrad_init');

/**
 * Activation hook
 */
function ksrad_activate() {
    KSRAD_Database::create_tables();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ksrad_activate');

/**
 * Deactivation hook
 */
function ksrad_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'ksrad_deactivate');
