# Upgrade Path: Free to Premium

## Strategy Overview

This document explains how to handle the transition between the free and premium versions of Keiste Solar Report.

---

## Architecture

### Two Separate Plugins

**Free Version:** `keiste-solar-report` (WordPress.org)
- No API keys required
- Basic calculations using averages
- Lead capture and management
- Passes WordPress.org guidelines

**Premium Version:** `keiste-solar-premium` (Your Site)
- Requires Google API key
- Advanced Google Maps integration
- Solar API for accurate data
- Enhanced features

---

## Transition Mechanisms

### 1. Auto-Detection & Deactivation

When premium is installed and activated:

```php
// Free plugin detects premium and auto-deactivates
if (KSRAD_Upgrade_Manager::is_premium_active()) {
    deactivate_plugins(KSRAD_PLUGIN_BASENAME);
    // Show notice: "Premium active, free version deactivated"
}
```

**Benefits:**
- No plugin conflicts
- Clean transition
- User-friendly

### 2. Data Preservation

Both plugins use the same database table prefix: `ksrad_`

**Free Version Tables:**
- `wp_ksrad_solar_leads`

**Premium Version:**
- Reads from same `wp_ksrad_solar_leads` table
- Adds additional columns if needed
- Imports all existing data

**Settings Migration:**
```php
// Premium checks for free version settings
$free_settings = array(
    'notification_email' => get_option('ksrad_notification_email'),
    'send_confirmation' => get_option('ksrad_send_confirmation'),
    'electricity_rate' => get_option('ksrad_default_electricity_rate'),
    'cost_per_watt' => get_option('ksrad_cost_per_watt'),
    'tax_credit' => get_option('ksrad_tax_credit'),
);

// Import into premium settings
if (!get_option('ks_notification_email')) {
    update_option('ks_notification_email', $free_settings['notification_email']);
}
```

### 3. In-Plugin Upgrade Promotion

**Upgrade Page:**
- Accessible via `Solar Leads > ⭐ Upgrade`
- Shows feature comparison
- Links to premium purchase page

**Admin Notices:**
- Dismissible upgrade banner
- Shows on plugin pages only
- Highlights premium features

**Callouts in Results:**
- "Upgrade for more accurate data with Google Maps"
- Shown after calculator results

---

## Implementation in Premium Plugin

### In Your Premium Plugin (`keiste-solar-premium`)

#### 1. Create Migration Class

```php
<?php
// includes/class-ks-migration.php

class KS_Migration {
    
    /**
     * Check if free version data exists
     */
    public static function has_free_version_data() {
        global $wpdb;
        $table = $wpdb->prefix . 'ksrad_solar_leads';
        return $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
    }
    
    /**
     * Import data from free version
     */
    public static function import_from_free() {
        if (!self::has_free_version_data()) {
            return false;
        }
        
        global $wpdb;
        
        // Copy leads to premium table (or use same table)
        $free_table = $wpdb->prefix . 'ksrad_solar_leads';
        $premium_table = $wpdb->prefix . 'ks_solar_leads';
        
        // Option A: Use same table (recommended)
        // Just add new columns if needed
        $wpdb->query("ALTER TABLE {$free_table} ADD COLUMN IF NOT EXISTS google_place_id VARCHAR(255)");
        $wpdb->query("ALTER TABLE {$free_table} ADD COLUMN IF NOT EXISTS roof_data TEXT");
        
        // Option B: Copy to new table
        // $wpdb->query("INSERT INTO {$premium_table} SELECT * FROM {$free_table}");
        
        // Import settings
        self::import_settings();
        
        // Mark migration as complete
        update_option('ks_migrated_from_free', true);
        update_option('ks_migration_date', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Import settings from free version
     */
    private static function import_settings() {
        $settings_map = array(
            'ksrad_notification_email' => 'ks_notification_email',
            'ksrad_send_confirmation' => 'ks_send_confirmation',
            'ksrad_default_electricity_rate' => 'ks_default_electricity_rate',
            'ksrad_cost_per_watt' => 'ks_cost_per_watt',
            'ksrad_tax_credit' => 'ks_tax_credit',
        );
        
        foreach ($settings_map as $free_option => $premium_option) {
            $value = get_option($free_option);
            if ($value && !get_option($premium_option)) {
                update_option($premium_option, $value);
            }
        }
    }
    
    /**
     * Show migration notice
     */
    public static function show_migration_notice() {
        if (!self::has_free_version_data()) {
            return;
        }
        
        if (get_option('ks_migration_notice_dismissed')) {
            return;
        }
        
        $lead_count = KSRAD_Database::get_total_leads();
        ?>
        <div class="notice notice-info is-dismissible">
            <h3><?php _e('Welcome to Keiste Solar Premium!', 'keiste-solar-premium'); ?></h3>
            <p>
                <?php 
                printf(
                    __('We found %d leads from your free version. Would you like to import them?', 'keiste-solar-premium'),
                    $lead_count
                );
                ?>
            </p>
            <p>
                <button class="button button-primary" onclick="ksMigrateData()">
                    <?php _e('Import Data Now', 'keiste-solar-premium'); ?>
                </button>
                <button class="button" onclick="ksDismissMigration()">
                    <?php _e('Skip Import', 'keiste-solar-premium'); ?>
                </button>
            </p>
        </div>
        
        <script>
        function ksMigrateData() {
            jQuery.post(ajaxurl, {
                action: 'ks_migrate_free_data',
                nonce: '<?php echo wp_create_nonce('ks_migrate'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('<?php _e('Data imported successfully!', 'keiste-solar-premium'); ?>');
                    location.reload();
                }
            });
        }
        
        function ksDismissMigration() {
            jQuery.post(ajaxurl, {
                action: 'ks_dismiss_migration',
                nonce: '<?php echo wp_create_nonce('ks_dismiss'); ?>'
            });
            jQuery('.notice').fadeOut();
        }
        </script>
        <?php
    }
}
```

#### 2. Handle Migration on Premium Activation

```php
// In premium plugin's activation hook

function ks_plugin_activate() {
    // Create premium tables
    KS_Database::create_tables();
    
    // Check for free version data
    if (KS_Migration::has_free_version_data()) {
        // Auto-import or prompt user
        $auto_import = true; // or false to prompt
        
        if ($auto_import) {
            KS_Migration::import_from_free();
        } else {
            // Show notice to prompt import
            add_option('ks_show_migration_notice', true);
        }
    }
    
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'ks_plugin_activate');
```

---

## User Experience Flow

### Scenario 1: User Installs Premium First

1. User installs and activates premium plugin
2. User enters Google API key
3. Premium features work immediately
4. No migration needed

### Scenario 2: User Upgrades from Free

1. User has free version active with leads
2. User purchases premium
3. User installs premium plugin
4. On premium activation:
   - Free version auto-deactivates
   - Migration prompt appears
   - User clicks "Import Data"
   - All leads and settings imported
   - User can delete free plugin

5. User enters Google API key
6. Premium features enabled
7. Old calculations still available, new ones use Google data

---

## Database Strategy

### Recommended: Shared Table Name

Use the same table name in both plugins:

**Benefit:** Zero-migration needed, data just works

```php
// Both plugins use:
$table_name = $wpdb->prefix . 'ksrad_solar_leads';
```

Premium just adds columns:
```sql
ALTER TABLE wp_ksrad_solar_leads
ADD COLUMN google_place_id VARCHAR(255),
ADD COLUMN roof_area_sqft DECIMAL(10,2),
ADD COLUMN solar_panel_config TEXT,
ADD COLUMN annual_solar_flux DECIMAL(10,2);
```

### Alternative: Separate Tables with Migration

If you prefer separate tables:

```php
// Free: wp_ksrad_solar_leads
// Premium: wp_ks_premium_leads

// Migration copies data:
INSERT INTO wp_ks_premium_leads 
SELECT * FROM wp_ksrad_solar_leads;
```

---

## Settings Strategy

### Option Naming Convention

**Free Version:**
- `ksrad_notification_email`
- `ksrad_send_confirmation`
- `ksrad_default_electricity_rate`
- etc.

**Premium Version (Two Options):**

**Option A: Keep Same Names (Recommended)**
- Use exact same option names
- Settings automatically work
- No migration needed

**Option B: Different Names**
- `ks_notification_email`
- `ks_send_confirmation`
- `ks_api_key`
- Import free settings on first run

---

## Marketing Strategy

### In Free Plugin

1. **Upgrade Menu Item**
   - Highlighted in gold/yellow
   - Always visible in sidebar
   - Links to comparison page

2. **Feature Comparison Table**
   - Clear visual of what premium adds
   - Emphasize Google integration
   - Show pricing

3. **Contextual Prompts**
   - After calculator: "Get more accurate data with Premium"
   - In leads list: "Export to CRM with Premium"
   - In settings: "Add your branding with Premium"

4. **Admin Notices**
   - Dismissible upgrade banner
   - Shows 3 times, then remembers dismissal
   - Reappears after 30 days

### On Your Website

1. **Clear Upgrade Path**
   - keiste.com/solar-report-premium
   - Purchase with license key
   - Download premium plugin

2. **License Management**
   - User gets license key after purchase
   - Premium plugin validates license
   - Automatic updates via license

---

## Technical Checklist

### In Free Plugin ✅

- [x] Upgrade manager class created
- [x] Auto-detect premium plugin
- [x] Auto-deactivate when premium active
- [x] Show upgrade page and notices
- [x] Export function for data backup
- [x] Use consistent table/option names

### In Premium Plugin (Your Implementation)

- [ ] Migration class to import free data
- [ ] Check for free version on activation
- [ ] Import leads from free version table
- [ ] Import settings from free version
- [ ] Show migration success notice
- [ ] Add columns to existing table (if using shared table)
- [ ] Google API key field in settings
- [ ] License validation system
- [ ] Feature detection (use Google if API key present, fallback to basic)

---

## Code Example: Premium Plugin Detection

### In Your Premium Plugin

```php
// Check if free version is active
function ks_check_free_version() {
    if (is_plugin_active('keiste-solar-report/keiste-solar-report.php')) {
        add_action('admin_notices', 'ks_free_version_notice');
    }
}
add_action('admin_init', 'ks_check_free_version');

function ks_free_version_notice() {
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php _e('Keiste Solar Premium is active!', 'keiste-solar-premium'); ?></strong>
            <?php _e('You can safely deactivate and delete the free version. All your data has been imported.', 'keiste-solar-premium'); ?>
        </p>
    </div>
    <?php
}
```

---

## Testing Checklist

1. **Install Free Only**
   - [ ] Create test leads
   - [ ] Configure settings
   - [ ] Verify functionality

2. **Install Premium (Free Already Active)**
   - [ ] Activate premium
   - [ ] Verify free auto-deactivates
   - [ ] Check migration notice appears
   - [ ] Run migration
   - [ ] Verify all leads imported
   - [ ] Verify settings imported
   - [ ] Test premium features

3. **Install Premium Only (Fresh)**
   - [ ] No migration notices
   - [ ] Direct setup experience
   - [ ] API key configuration

4. **Upgrade Path**
   - [ ] Free upgrade page displays correctly
   - [ ] Links work
   - [ ] Feature comparison clear
   - [ ] Purchase process smooth

---

## Support Documentation

Create these docs for users:

1. **Upgrading from Free to Premium**
   - Step-by-step guide
   - What happens to data
   - How to get API key

2. **Getting Started with Premium**
   - API key setup
   - First calculation
   - Advanced features

3. **Troubleshooting**
   - Migration didn't work
   - Can't find old leads
   - API key issues

---

## Summary

The free→premium transition is handled through:

1. **Auto-detection** - Plugins detect each other
2. **Auto-deactivation** - Free deactivates when premium active
3. **Shared data** - Same table names = zero migration
4. **Import on demand** - Premium can import if using different tables
5. **Clear upgrade path** - In-plugin marketing and comparison
6. **Smooth UX** - Users don't lose data or settings

This approach passes WordPress.org guidelines (no API key in free version) while providing a seamless upgrade experience.
