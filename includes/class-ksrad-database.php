<?php
/**
 * Database management class for Keiste Solar Report
 * 
 * Handles database table creation and operations for storing solar leads.
 *
 * @package Keiste_Solar_Report
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

class KSRAD_Database {
    
    /**
     * Table name for storing leads
     */
    private static $table_name = 'ksrad_solar_leads';
    
    /**
     * Create database tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . self::$table_name;
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            phone varchar(50) DEFAULT NULL,
            address text DEFAULT NULL,
            monthly_bill decimal(10,2) NOT NULL,
            roof_type varchar(100) DEFAULT NULL,
            estimated_system_size decimal(10,2) DEFAULT NULL,
            estimated_cost decimal(10,2) DEFAULT NULL,
            estimated_savings decimal(10,2) DEFAULT NULL,
            notes text DEFAULT NULL,
            ip_address varchar(100) DEFAULT NULL,
            user_agent text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Insert a new lead into the database
     *
     * @param array $data Lead data to insert
     * @return int|false The number of rows inserted, or false on error
     */
    public static function insert_lead($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        $defaults = array(
            'name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'monthly_bill' => 0,
            'roof_type' => '',
            'estimated_system_size' => 0,
            'estimated_cost' => 0,
            'estimated_savings' => 0,
            'notes' => '',
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'name' => sanitize_text_field($data['name']),
                'email' => sanitize_email($data['email']),
                'phone' => sanitize_text_field($data['phone']),
                'address' => sanitize_textarea_field($data['address']),
                'monthly_bill' => floatval($data['monthly_bill']),
                'roof_type' => sanitize_text_field($data['roof_type']),
                'estimated_system_size' => floatval($data['estimated_system_size']),
                'estimated_cost' => floatval($data['estimated_cost']),
                'estimated_savings' => floatval($data['estimated_savings']),
                'notes' => sanitize_textarea_field($data['notes']),
                'ip_address' => $data['ip_address'],
                'user_agent' => $data['user_agent'],
            ),
            array(
                '%s', // name
                '%s', // email
                '%s', // phone
                '%s', // address
                '%f', // monthly_bill
                '%s', // roof_type
                '%f', // estimated_system_size
                '%f', // estimated_cost
                '%f', // estimated_savings
                '%s', // notes
                '%s', // ip_address
                '%s', // user_agent
            )
        );
        
        return $result;
    }
    
    /**
     * Get all leads with pagination
     *
     * @param int $page Current page number
     * @param int $per_page Number of items per page
     * @param string $orderby Column to order by
     * @param string $order ASC or DESC
     * @return array Array of lead objects
     */
    public static function get_leads($page = 1, $per_page = 20, $orderby = 'created_at', $order = 'DESC') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        // Validate and sanitize pagination
        $page = max(1, absint($page));
        $per_page = max(1, min(100, absint($per_page)));
        $offset = ($page - 1) * $per_page;
        
        // Validate orderby column (whitelist)
        $allowed_orderby = array('id', 'name', 'email', 'created_at', 'monthly_bill');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'created_at';
        }
        
        // Validate order direction
        $order = strtoupper($order);
        if (!in_array($order, array('ASC', 'DESC'), true)) {
            $order = 'DESC';
        }
        
        // Build safe query
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get total number of leads
     *
     * @return int Total number of leads
     */
    public static function get_total_leads() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    /**
     * Get a single lead by ID
     *
     * @param int $id Lead ID
     * @return object|null Lead object or null if not found
     */
    public static function get_lead($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Delete a lead by ID
     *
     * @param int $id Lead ID
     * @return int|false Number of rows affected or false on error
     */
    public static function delete_lead($id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        
        return $wpdb->delete(
            $table_name,
            array('id' => $id),
            array('%d')
        );
    }
    
    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * Export leads to CSV
     *
     * @return string CSV content
     */
    public static function export_to_csv() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . self::$table_name;
        $leads = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
        
        if (empty($leads)) {
            return '';
        }
        
        // Create CSV header
        $csv = '';
        $header = array_keys($leads[0]);
        $csv .= implode(',', $header) . "\n";
        
        // Add data rows
        foreach ($leads as $lead) {
            $row = array();
            foreach ($lead as $value) {
                $row[] = '"' . str_replace('"', '""', $value) . '"';
            }
            $csv .= implode(',', $row) . "\n";
        }
        
        return $csv;
    }
}
