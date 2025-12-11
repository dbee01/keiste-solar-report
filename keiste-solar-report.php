<?php
/**
 * Plugin Name: Keiste Solar Report
 * Plugin URI: https://keiste.com/keiste-solar-report
 * Description: Comprehensive solar panel analysis tool with ROI calculations, Google Solar API integration, interactive charts, and PDF report generation.
 * Version: 1.0.11
 * Author: Dara Burke, Keiste
 * Author URI: https://keiste.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: keiste-solar-report
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * Solar Analysis Report
 * Financial and energy analysis based on Google's Solar API data
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Set error reporting to show only errors, not warnings
error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);

// Load Composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define plugin constants (only once) with ksrad_ namespace
if (!defined('KSRAD_VERSION')) {
    define('KSRAD_VERSION', '1.0.11');
}
if (!defined('KSRAD_PLUGIN_DIR')) {
    define('KSRAD_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('KSRAD_PLUGIN_URL')) {
    define('KSRAD_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('KSRAD_PLUGIN_BASENAME')) {
    define('KSRAD_PLUGIN_BASENAME', plugin_basename(__FILE__));
}
if (!defined('KSRAD_RENDERING')) {
    define('KSRAD_RENDERING_CONST', false);
}

// GOOGLE SOLAR API URL
if (!defined('KSRAD_GOOGLE_SOLAR_API_URL')) {
    define('KSRAD_GOOGLE_SOLAR_API_URL', 'https://solar.googleapis.com/v1/buildingInsights:findClosest');
}

// Load plugin initialization (WordPress integration) - only once
if (!class_exists('KSRAD_Plugin')) {
    require_once KSRAD_PLUGIN_DIR . 'includes/plugin-init.php';
}

// Stop here if we're just activating the plugin
// The rest of the code should only run when actually rendering the shortcode
if (!defined('KSRAD_RENDERING')) {
    return;
}

// HTTP Authentication removed - access is now managed by WordPress

// Function to fetch solar data from Google Solar API
if (!function_exists('ksrad_fetch_solar_data')) {
    function ksrad_fetch_solar_data($lat, $lng)
    {
        $apiKey = ksrad_get_option('google_solar_api_key', NULL);
        if (empty($apiKey)) {
            throw new Exception("Google Solar API key is not configured. Please add your API key in the WordPress admin settings page.");
        }

    // Construct the URL with proper encoding
    $params = http_build_query([
        'location.latitude' => $lat,
        'location.longitude' => $lng,
        'requiredQuality' => 'MEDIUM',
        'key' => $apiKey
    ]);

    $url = KSRAD_GOOGLE_SOLAR_API_URL . '?' . $params;

    $response = wp_remote_get($url, array(
        'timeout' => 15,
        'sslverify' => true,
        'headers' => array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $apiKey,
            'Referer' => home_url('/')
        )
    ));

    if (is_wp_error($response)) {
        $error = $response->get_error_message();
        throw new Exception(esc_html($error));
    }

    $httpCode = wp_remote_retrieve_response_code($response);
    $response = wp_remote_retrieve_body($response);

    if ($httpCode !== 200) {
        $errorResponse = json_decode($response, true);
        $errorMessage = isset($errorResponse['error']['message'])
            ? $errorResponse['error']['message']
            : "API request failed with status code: " . $httpCode;
        // If the API reports the entity was not found, return a 404 to the client
        if (isset($errorResponse['error']['code']) && intval($errorResponse['error']['code']) === 404) {
            // Send a 404 response and a friendly message
            if (!headers_sent()) {
                $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_PROTOCOL'])) : 'HTTP/1.1';
                header($protocol . ' 404 Not Found', true, 404);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo '<div style="font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; border: 1px solid #dc3545; border-radius: 8px; background: #fff;">';
            echo '<h2 style="color: #dc3545;">⚠️ Solar Data Not Found (404)</h2>';
            echo '<p>The Google Solar API reports that the requested entity was not found for the provided coordinates.</p>';
            if (isset($errorResponse['error']['message'])) {
                echo '<p><strong>Message:</strong> ' . esc_html($errorResponse['error']['message']) . '</p>';
            }
            echo '<p>Please verify the latitude and longitude, or try a location within Google Solar coverage.</p>';
            echo '</div>';
            exit;
        }
        throw new Exception(esc_html($errorMessage));
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse API response: " . esc_html(json_last_error_msg()));
    }

    return $data;
    }
}

// Get coordinates from query parameters or use defaults (Dublin as default)
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET parameters are for display only, not form submission
$ksrad_latitude = isset($_GET['lat']) ? floatval(sanitize_text_field(wp_unslash($_GET['lat']))) : 51.886656;
$ksrad_longitude = isset($_GET['lng']) ? floatval(sanitize_text_field(wp_unslash($_GET['lng']))) : -8.535580;
$ksrad_business_name = isset($_GET['business_name']) ? sanitize_text_field(wp_unslash($_GET['business_name'])) : '';

// Check if this is an AJAX request (has lat/lng parameters) or initial page load
$ksrad_isAjaxRequest = isset($_GET['lat']) && isset($_GET['lng']);
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$ksrad_solarDataAvailable = false;
$ksrad_errorMessage = null;
$ksrad_solarData = null;

// Only fetch solar data if AJAX request with coordinates
if ($ksrad_isAjaxRequest) {
    try {
        $ksrad_solarData = ksrad_fetch_solar_data($ksrad_latitude, $ksrad_longitude);
        if (!empty($ksrad_solarData)) {
            $ksrad_solarDataAvailable = true;
        } else {
            $ksrad_solarData = null;
        }
    } catch (Exception $e) {
        $ksrad_errorMessage = $e->getMessage();
        $ksrad_solarData = null;
    }
}

// Helper function to format kWh numbers
if (!function_exists('ksrad_format_kwh')) {
    function ksrad_format_kwh($kwh)
    {
        return number_format($kwh, 2);
    }
}

// Helper function to format area in m²
if (!function_exists('ksrad_format_area')) {
    function ksrad_format_area($area)
    {
        return number_format($area, 2);
    }
}

// Function to get satellite imagery from Google Maps Static API
if (!function_exists('ksrad_get_maps_static_satellite_image')) {
    function ksrad_get_maps_static_satellite_image($latitude, $longitude, $apiKey)
    {
    // Returns URL for satellite image from Google Maps Static API
    // Works globally for any building coordinates
    $zoom = 20; // Bird's-eye rooftop view (0-21, 19 is optimal for buildings)
    $size = "800x500";
    $url = "https://maps.googleapis.com/maps/api/staticmap"
        . "?center=" . urlencode("$latitude,$longitude")
        . "&zoom=$zoom"
        . "&size=$size"
        . "&maptype=satellite"
        . "&key=" . urlencode($apiKey);

    return $url;
    }
}

// Get satellite image from Google Maps Static API (only for AJAX requests)
$ksrad_mapsStaticImageUrl = '';
$ksrad_imageSource = '';
if ($ksrad_isAjaxRequest) {
    $ksrad_mapsStaticImageUrl = ksrad_get_maps_static_satellite_image($ksrad_latitude, $ksrad_longitude, ksrad_get_option('google_solar_api_key', NULL));
    $ksrad_imageSource = 'Google Maps Satellite (Rooftop View)';
}

?>
<!-- Keiste Solar Report - WordPress Shortcode Output -->
<div id="keiste-solar-report-wrapper" class="ksrad-wrapper">
    <!-- Loading Indicator -->
    <div id="ajaxLoader"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; flex-direction: column;">
        <div style="background: white; padding: 2rem; border-radius: 8px; text-align: left;">
            <div
                style="border: 4px solid #f3f3f3; border-top: 4px solid #1B4D3E; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;">
            </div>
            <p style="color: #1B4D3E; font-weight: 600; margin: 0;">Loading solar data...</p>
        </div>
    </div>


    <!-- ROI Modal Popup (jQuery-powered, accessible) -->
    <?php
    // Check if lead capture modal is enabled (premium feature)
    $ksrad_enable_lead_modal = apply_filters('ksrad_is_premium', false);
    if ($ksrad_enable_lead_modal):
    ?>
    <dialog id="roiModal">
        <button type="button" class="roi-modal-close" style="display: none;" onclick="hideModal()" aria-label="Close">&times;</button>
        <form id="roiForm" method="dialog" class="roi-modal-form">
            <?php wp_nonce_field('ksrad_roi_form', 'ksrad_roi_nonce'); ?>
            <h3 class="roi-modal-title">Enter your details to continue</h3>
            
            <!-- Full Name -->
            <div class="roi-form-group">
                <label for="roiFullName">Name <span class="required">*</span></label>
                <input id="roiFullName" name="fullName" type="text" class="roi-input" required autofocus
                    placeholder="Enter your first name">
            </div>
            
            <!-- Email -->
            <div class="roi-form-group">
                <label for="roiEmail">Email <span class="required">*</span></label>
                <input id="roiEmail" name="email" type="email" class="roi-input" required
                    placeholder="Enter your email address">
            </div>
            
            <!-- Phone with Country Code -->
            <div class="roi-form-group">
                <label for="roiPhone">Phone <span class="required">*</span></label>
                <div class="phone-input-wrapper">
                    <select id="roiPhoneCountry" name="phoneCountry" class="phone-country-select">
                        <option value="+1" data-flag="🇺🇸">🇺🇸 +1</option>
                        <option value="+1" data-flag="🇨🇦">🇨🇦 +1</option>
                        <option value="+44" data-flag="🇬🇧">🇬🇧 +44</option>
                        <option value="+353" data-flag="🇮🇪" selected>🇮🇪 +353</option>
                    </select>
                    <input id="roiPhone" name="phone" type="tel" class="roi-input phone-input" required
                        placeholder="Enter your phone number" pattern="[0-9\s\-\(\)]+">
                </div>
            </div>
            
            <!-- Consent Checkbox - Required -->
            <div class="roi-form-group roi-checkbox-group">
                <input id="roiTerms" name="terms" type="checkbox" required>
                <label for="roiTerms" class="roi-checkbox-label">
                    I agree to be contacted regarding my solar installation and accept the <a href="#" target="_blank" style="color: #1B4D3E; text-decoration: underline;">Data Use Policy</a> <span class="required">*</span>
                </label>
            </div>
            
            <!-- Marketing Consent - Optional -->
            <div class="roi-form-group roi-checkbox-group">
                <input id="roiMarketing" name="marketing" type="checkbox">
                <label for="roiMarketing" class="roi-checkbox-label">
                    I agree to receive marketing materials including a personalized 10 page solar report
                </label>
            </div>
            
            <!-- Disclaimer -->
            <div class="roi-form-group roi-disclaimer">
                <span class="roi-disclaimer-text">Disclaimer: This is an estimate, not a formal quote. Actual figures may vary.</span>
            </div>
            
            <!-- Action Buttons -->
            
            <menu class="roi-modal-menu"></menu>
                <button value="cancel" id="roiCancelBtn" type="button" class="roi-btn roi-btn-cancel">Exit</button>
                <button value="submit" id="roiSubmitBtn" class="roi-btn roi-btn-submit">Continue</button>
            </menu>
        
        </form>
    </dialog>
    <?php endif; // End lead modal check ?>

    <div class="container">

        <div class="text-center mt-4">
            <?php
            $ksrad_logo_url = ksrad_get_option('logo_url', '');
            if (empty($ksrad_logo_url)) {
                // Fallback to default plugin logo if no custom logo uploaded
                $ksrad_logo_url = KSRAD_PLUGIN_URL . 'assets/images/keiste-logo.png';
            }
            ?>
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo esc_url($ksrad_logo_url); ?>" alt="Company Logo"
                    class="img-fluid logo-image" style="max-width: 130px; width: 100%;">
            </a>

        </div>

        <div class="text-center mt-4">
            <h1>Solar Report<sup style="color: red;font-size: 16px;vertical-align: middle;top: -14px;" > BETA</sup></h1>
        </div>


        <!-- Initial Page (when NOT an AJAX request) -->
        <?php if (!$ksrad_isAjaxRequest): ?>
            <div id="ajaxHeader" class="alert alert-info" role="alert"
                style="text-align: center; background: #FDFDFB; border: 2px solid #E8E8E6; border-radius: 12px; padding: 2rem; margin: 2rem auto; max-width: 600px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
                <h3 style="color: #2A2A28; margin-bottom: 1rem; font-size: 1.3em; font-weight: 600;">🔍 How Much Can You Save With Solar? 
                </h3>
                <h5 style="font-size: 1rem; color: #2a2a28; font-family: 'Brush Script MT', cursive;">by <a href="https://keiste.com" target="_blank" rel="noopener noreferrer">Keiste.com</a></h5>
  
                <?php if (apply_filters('ksrad_is_premium', false)): ?>
  
                <!-- Social Media Share Buttons -->
                <div class="social-share-buttons" style="margin: 1.8rem 0;">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink()); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="social-btn facebook"
                       aria-label="Share on Facebook">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode(get_permalink()); ?>&text=<?php echo urlencode('Check out your solar potential with Keiste Solar Report'); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="social-btn twitter"
                       aria-label="Share on X (Twitter)">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="https://www.instagram.com/" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="social-btn instagram"
                       aria-label="Share on Instagram"
                       onclick="alert('Copy the link and share on Instagram!'); navigator.clipboard.writeText('<?php echo esc_js(get_permalink()); ?>'); return false;">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="https://www.tiktok.com/" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="social-btn tiktok"
                       aria-label="Share on TikTok"
                       onclick="alert('Copy the link and share on TikTok!'); navigator.clipboard.writeText('<?php echo esc_js(get_permalink()); ?>'); return false;">
                        <i class="fab fa-tiktok"></i>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode(get_permalink()); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="social-btn linkedin"
                       aria-label="Share on LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="mailto:?subject=<?php echo urlencode('Keiste Solar Report'); ?>&body=<?php echo urlencode('Check out this solar analysis tool: ' . get_permalink()); ?>" 
                       class="social-btn email"
                       aria-label="Share via Email">
                        <i class="fa fa-envelope"></i>
                    </a>
                </div>
                
                <?php endif; ?>
  
                <p style="color: #3A3A38; margin-bottom: 0.5rem; font-weight: 500;">Use the search box below to select an
                    address.</p>
                <p style="color: #5A5A58; font-size: 1rem; margin-bottom: 0;">We'll analyze solar potential
                    and show you financial projections for your building.</p>
            </div>

            <!-- Country and Building Type Selection Form -->
            <div class="location-form-wrapper" style="margin: 2rem auto; max-width: 600px;">
                <div class="row g-3">
                    <!-- Country Selection -->
                    <div class="col-md-6">
                        <label for="userCountry" class="form-label" style="font-weight: 600; color: #2A2A28; margin-bottom: 0.5rem;">Country <span style="color: #dc3545;">*</span></label>
                        <select id="userCountry" name="userCountry" class="form-select" required style="padding: 0.75rem; border: 1px solid #E8E8E6; border-radius: 6px; font-size: 1rem;">
                            <option value="">Select your country</option>
                            <option value="USA" data-flag="🇺🇸">🇺🇸 United States</option>
                            <option value="Canada" data-flag="🇨🇦">🇨🇦 Canada</option>
                            <option value="UK" data-flag="🇬🇧">🇬🇧 United Kingdom</option>
                            <option value="Ireland" data-flag="🇮🇪">🇮🇪 Rep. of Ireland</option>
                        </select>
                    </div>
                    
                    <!-- Building Type Selection -->
                    <div class="col-md-6">
                        <label for="userBuildingType" class="form-label" style="font-weight: 600; color: #2A2A28; margin-bottom: 0.5rem;">Building Type <span style="color: #dc3545;">*</span></label>
                        <select id="userBuildingType" name="userBuildingType" class="form-select" required style="padding: 0.75rem; border: 1px solid #E8E8E6; border-radius: 6px; font-size: 1rem;">
                            <option value="">Select building type</option>
                            <option value="Residential" selected>🏠  Residential</option>
                            <option value="Commercial">🏢  Commercial</option>
                            <option value="Farm">🚜  Farm</option>
                            <option value="Community">🏘️  Community</option>
                            <option value="Business">💼  Business</option>
                        </select>
                    </div>
                </div>
            </div>
            
        <?php endif; ?>


        <div class="text-center mb-4">

            <div class="report-header" id="reportHeader">
                <h3><em><?php echo esc_html($ksrad_business_name); ?></em></h3>
            </div>

        </div>



        <div id="pacContainer" style="display: none;">
            <gmp-place-autocomplete id="pac" fields="id,location,formattedAddress,displayName" style="min-width: 320px;
                placeholder: 'Search your address';
                background-color: #fff;
                color: #222;
                text-align: left;
                font-size: 16px;
                padding: 8px 12px;
                border: 1px solid #ccc;
                border-radius: 15px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <input id="pacInput" placeholder="Search your address" />
            </gmp-place-autocomplete>

        </div>

        <div class="row">
            <div class="section map-section col-md-12" id="map-section">
                <div id="map" style="height: 400px;width: 100%;"></div>
            </div>
        </div>

        <script>
            // Configuration for maps-integration.js
            window.KSRAD_MapsConfig = {
                apiKey: <?php echo wp_json_encode(ksrad_get_option('google_solar_api_key', NULL)); ?>,
                businessName: <?php echo wp_json_encode(isset($ksrad_business_name) ? (string) $ksrad_business_name : 'Location'); ?>,
                lat: Number(<?php echo wp_json_encode(isset($ksrad_latitude) ? (float) $ksrad_latitude : 51.886656); ?>),
                lng: Number(<?php echo wp_json_encode(isset($ksrad_longitude) ? (float) $ksrad_longitude : -8.535580); ?>),
                country: <?php echo wp_json_encode(ksrad_get_option('country', 'Rep. of Ireland')); ?>
            };
        </script>
        <!-- Maps integration script enqueued via WordPress -->
        
        <!-- Container for AJAX-loaded content -->
        <div class="newContainer">
        <?php if ($ksrad_isAjaxRequest): ?>
            <?php
            // Display Google Maps Static API satellite image
            $ksrad_imageSource = 'Google Maps Satellite (Rooftop View)';
            ?>

            <div class="newContainer">

                <?php if (!$ksrad_solarDataAvailable): ?>
                    <div class="alert alert-warning" role="alert"
                        style="text-align: left; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 1.5rem; margin: 2rem auto; max-width: 600px;">
                        <h4 style="color: #856404; margin-bottom: 1rem;">⚠️ Solar Data Not Available</h4>
                        <p style="color: #856404; margin-bottom: 0.5rem;">Unfortunately, Google Solar API does not have coverage
                            for this location yet. Please <a href="<?php echo esc_url(home_url('/')); ?>">try another location</a>.</p>
                        <p style="color: #856404; margin-bottom: 1rem;"><strong>Location:</strong>
                            <?php echo esc_html($ksrad_business_name); ?></p>
                        <p style="color: #856404; font-size: 0.9rem; margin-bottom: 0;">
                            <strong>Coordinates:</strong> <?php echo number_format($ksrad_latitude, 6); ?>,
                            <?php echo number_format($ksrad_longitude, 6); ?>
                        </p>
                        <?php if ($ksrad_errorMessage): ?>
                            <p
                                style="color: #721c24; background: #f8d7da; padding: 0.75rem; border-radius: 4px; margin-top: 1rem; font-size: 0.85rem;">
                                <strong>Error:</strong> <?php echo esc_html($ksrad_errorMessage); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                <?php else: ?>

                     <div class="results-section">
                        <h4 class="text-center">Read Me</h4>
                        <!-- END .section.site-overview -->
                        <div class="row mt-4">
                            <div class="col-md-2">&nbsp;</div>
                            <p class="col-md-8 mb-3 text-center small" >
                                <!-- pull in notes from the dashboard settings page -->
                                <?php echo nl2br(esc_html(ksrad_get_option('financial_analysis_notes', ''))); ?>
                            </p>
                            <div class="col-md-2">&nbsp;</div>
                        </div>
                    </div>

                    <form id="solarForm" class="needs-validation mt-4" novalidate>
                        <?php wp_nonce_field('ksrad_solar_form', 'ksrad_solar_nonce'); ?>

                        <!-- Solar Investment Analysis -->
                        <div class="solar-investment-analysis section" id="solar-investment-analysis">
                            <div class="row">
                                <h4 class="text-center mb-4">Choose Your System Size</h4>
                                <div class="col-md-2 mb-4"></div>

                                <div class="col-md-8 mb-4">
                                    <?php $ksrad_maxPanels = $ksrad_solarData['solarPotential']['maxArrayPanelsCount']; ?>
                                    <div class="d-flex justify-content-between align-items-baseline mb-2">
                                        <label for="panelCount" class="form-label mb-0">Panels: <span
                                                id="panelCountValue">0</span></label>
                                        <div class="system-size">
                                            <?php
                                            // Server-side default for compact installCost (mirror logic used later)
                                            $ksrad_header_four_panel_yearly = null;
                                            if (isset($ksrad_solarData['solarPotential']['solarPanelConfigs'])) {
                                                foreach ($ksrad_solarData['solarPotential']['solarPanelConfigs'] as $ksrad_c) {
                                                    if (!empty($ksrad_c['panelsCount']) && $ksrad_c['panelsCount'] == 4) {
                                                        $ksrad_header_four_panel_yearly = $ksrad_c['yearlyEnergyDcKwh'];
                                                        break;
                                                    }
                                                }
                                            }
                                            if ($ksrad_header_four_panel_yearly) {
                                                $ksrad_header_installed_kwp = floatval($ksrad_header_four_panel_yearly) / 1000.0;
                                            } else {
                                                $ksrad_header_installed_kwp = (4 * 400) / 1000.0; // fallback 1.6 kWp
                                            }
                                            if ($ksrad_header_installed_kwp <= 100) {
                                                $ksrad_header_default_install = $ksrad_header_installed_kwp * 1500;
                                            } elseif ($ksrad_header_installed_kwp <= 250) {
                                                $ksrad_header_default_install = (100 * 1500) + (($ksrad_header_installed_kwp - 100) * 1300);
                                            } else {
                                                $ksrad_header_default_install = (100 * 1500) + (150 * 1300) + (($ksrad_header_installed_kwp - 250) * 1100);
                                            }
                                            ?>
                                            <label for="panelCount" class="form-label mb-0">Cost: <span
                                                    id="installCost">0</span>
                                                </label>
                                        </div>
                                    </div>
                                    <div class="slider-container">
                                        <input type="range" class="form-range custom-slider" id="panelCount" min="0"
                                            max="<?php echo esc_attr($ksrad_maxPanels); ?>" value="0" step="1" required>
                                        <div class="slider-labels">
                                            <span>0</span>
                                            <span><?php echo esc_html($ksrad_maxPanels); ?></span>
                                        </div>
                                    </div>
                                    <div class="input-help text-center">Drag slider to adjust number of panels (Maximum
                                        capacity: <?php echo esc_html($ksrad_maxPanels); ?> panels)</div>
                                    <div class="col-md-2 mb-4"></div>
                                </div>
                                <div class="col-md-6 mb-3" style="display: none;">
                                    <label for="systemSize" class="form-label">System Size (kW)</label>
                                    <input type="number" class="form-control" id="systemSize" step="0.1" required>
                                    <div class="input-help">Based on 400W panels</div>
                                </div>
                                <style>

                                    /* ===== Range Slider ===== */
                                    input[type="range"] {
                                        width: 100%;
                                        height: 8px;
                                        border-radius: 5px;
                                        background: linear-gradient(to right, var(--primary-color) 0%, var(--primary-color) 50%, #ddd 50%, #ddd 100%);
                                        outline: none;
                                        -webkit-appearance: none;
                                    }

                                    input[type="range"]::-webkit-slider-thumb {
                                        -webkit-appearance: none;
                                        appearance: none;
                                        width: 20px;
                                        height: 20px;
                                        border-radius: 50%;
                                        background: var(--primary-color);
                                        cursor: pointer;
                                        box-shadow: var(--shadow-sm);
                                    }

                                    input[type="range"]::-moz-range-thumb {
                                        width: 20px;
                                        height: 20px;
                                        border-radius: 50%;
                                        background: var(--primary-color);
                                        cursor: pointer;
                                        box-shadow: var(--shadow-sm);
                                    }

                                </style>

                                <h5 class="text-center mb-4">Your Finances</h5>

                                <div class="mb-4 mt-4">
                                    <div class="row">
                        <div class="col-md-6 mb-3 elecbill"
                            style="text-align: right;border-right: 1px #ccc solid;padding-right: 2rem;">
                            <div>
                                <label for="electricityBill" class="form-label"
                                    style="color: var(--primary-green);">Your Monthly Electricity Bill
                                    (<span class="currency-symbol" id="elecBillCurrency"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>)</label>
                            </div>
                            <input type="number" min="0" class="form-control"
                                style="margin-right: unset;display: inline-block;text-align: right;"
                                id="electricityBill" maxlength="12" placeholder="0" required>
                        </div>                                        <div class="col-md-6 mb-3 align-center grant-box" style="padding-left: 2rem;">
                                            <div>
                                                <input type="checkbox" id="inclGrant" checked required>
                                                <label for="inclGrant" class="form-label">
                                                    <I>Include Solar Grant 
                                                        <span class="tooltip-icon" title="Government rebate (if available) to reduce installation costs">ⓘ</span>
                                                    </I>
                                                </label>
                                            </div>
                                            <div style="display: none;" >
                                                <input type="checkbox" id="inclACA" required>
                                                <label for="inclACA" class="form-label">
                                                    <I>Include ACA Saving Allowance (ACA) 
                                                        <span class="tooltip-icon" title="Accelerated Capital Allowance for tax benefits">ⓘ</span>
                                                    </I>
                                                </label>
                                            </div>
                                            <div>
                                                <input type="checkbox" id="inclLoan" required>
                                                <label for="inclLoan" class="form-label">
                                                    <I>Loan (<?php echo esc_html(ksrad_get_option('loan_term', '7')); ?> year @ <?php echo esc_html(ksrad_get_option('default_loan_apr', '5')); ?>% APR)
                                                        <span class="tooltip-icon" title="Finance your solar installation over <?php echo esc_html(ksrad_get_option('loan_term', '7')); ?> years">ⓘ</span>
                                                    </I>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="mb-4 mt-4">
                                            <div class="row">
                                                <h6 class="col-md-12 mb-3 text-center" style="font-size: smaller;" >
                                                    * Installation costs are estimates based on system size and will vary
                                                    based on site conditions and specific requirements.
                                                </h6>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>                          
                        
                        <div class="section financial-form">

                            <!-- Results Section -->
                            <div class="results-section" id="results">
                                <h4 class="text-center">Your Return on Investment (ROI)</h4>
                                <!-- END .section.site-overview -->
                                <div class="row mt-4">
                                    <div class="col-md-4 ml-auto mr-auto text-center"></div>
                                    <div class="col-md-4 ml-auto mr-auto text-center">
                                        <div class="colDirection">
                                            <div class="colFlex">
                                                <i class="fas fa-exchange-alt"></i>
                                                <div class="resultsCol" style="border-left: unset;">
                                                    <span class="highlight" id="netIncome">0</span>
                                                    <div class="ms-2 underWrite">MONTHLY INC/EXP</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 ml-auto mr-auto text-center"></div>
                                </div>
                                <div class="row mt-4">
                                    <div class="col-md-6 ml-auto mr-auto">
                                        <div class="colDirection">
                                            <div class="colFlexRight">
                                                <i class="fas fa-euro-sign"></i>
                                                <div class="resultsCol">
                                                    <span class="highlight" id="netCost"><span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>0</span>
                                                    <div class="ms-2 underWrite">NET INSTALL COST</div>
                                                </div>
                                            </div>
                                            <div class="colFlexRight">
                                                <i class="fas fa-clock"></i>
                                                <div class="resultsCol">
                                                    <span class="highlight" id="paybackPeriod">0 <span
                                                            style="font-size: medium;"> yrs</span></span>
                                                    <div class="ms-2 underWrite">PAYBACK PERIOD</div>
                                                </div>
                                            </div>
                                            <div class="colFlexRight">
                                                <i class="fas fa-piggy-bank"></i>
                                                <div class="resultsCol">
                                                    <span class="highlight" id="annualSavings"><span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>0</span>
                                                    <div class="ms-2 underWrite">ANNUAL SAVINGS (YEAR 1)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 ml-auto mr-auto">
                                        <div class="colDirection">
                                            <div class="colFlexLeft">
                                                <i class="fas fa-chart-line"></i>
                                                <div class="resultsCol">
                                                    <span class="highlight" id="totalSavings"><span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>0</span>
                                                    <div class="ms-2 underWrite">25-YEAR SAVINGS</div>
                                                </div>
                                            </div>
                                            <div class="colFlexLeft">
                                                <i class="fas fa-percentage"></i>
                                                <div class="resultsCol">
                                                    <span class="highlight" id="roi">0%</span>
                                                    <div class="ms-2 underWrite">ROI (25 YEARS)</div>
                                                </div>
                                            </div>
                                            <div class="colFlexLeft">
                                                <i class="fas fa-leaf"></i>
                                                <div class="resultsCol">
                                                    <span class="highlight" id="co2Reduction">0</span>
                                                    <div class="ms-2 underWrite">CO₂ REDUCTION (TONNES)</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <div class="row mt-4">
                                    <div class="col-md-12 text-center mt-4">

                                        <!-- Modal handler script enqueued via WordPress -->
                                        <script>
                                            // Expose getPanelCount function for modal handler
                                            window.getPanelCount = function getPanelCount() {
                                                const range = document.querySelector('input[type="range"]#panelCount');
                                                if (range) return parseInt(range.value, 10) || 0;
                                                const disp = document.getElementById('panelCountValue') || document.getElementById('panelCountDisplay');
                                                if (disp) return parseInt(disp.textContent.trim(), 10) || 0;
                                                return 0;
                                            };

                                        </script>

                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- END .financial-form.section -->

                        <!-- Combined Chart Page: Break Even & Energy Production -->
                        <div class="mt-5">
                            <h4 class="text-center mb-4">Financial Analysis Charts</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="chart-container" style="position: relative; height: 300px; background: transparent;">
                                        <canvas id="breakEvenChart" style="background: transparent;"></canvas>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="chart-container" style="position: relative; height: 300px; background: transparent;">
                                        <canvas id="energyChart" style="background: transparent;"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                        </div>

                        <!-- Solar Investment Analysis -->
                        <div class="section financial-form mt-5" id="installation-details">
                            <h4 class="text-center mb-4">Your Installation Details</h4>
                            <!-- System Size Section -->
                            <div class="install-details-grid">
                                <div class="install-details-row">
                                    <div class="install-details-cell">
                                        <label for="installationCost" class="form-label-left">Upfront Installation Cost
                                            (<span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>)</label>
                                        <?php
                                        // Default to 0 panels - user will adjust slider to calculate cost
                                        $ksrad_default_install_cost = 0;
                                        ?>
                                        <div class="energy-display-left"><span id="installationCost"
                                                class="highlighted-value"><?php echo number_format(round($ksrad_default_install_cost), 0); ?></span>
                                        </div>
                                        <div class="input-help-left">Total installation cost (not including grant)</div>
                                    </div>
                                    <div class="install-details-cell install-details-border">
                                        <label for="grant" class="form-label-right">Available Grant (<span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>)</label>
                                        <div class="energy-display-right"><span id="grant"
                                                class="highlighted-value">0</span></div>
                                        <div class="input-help-right"><?php echo esc_html(ksrad_get_option('seai_grant_rate', '%')); ?>% Grant (max <?php echo esc_html(ksrad_get_option('seai_grant_cap', '€')); ?>)</div>
                                    </div>
                                </div>
                                <div class="install-details-row">
                                    <div class="install-details-cell">
                                        <label for="panelCount" class="form-label-left">Number of Panels</label>
                                        <div class="energy-display-left"><span id="panelCount"
                                                class="highlighted-value">0</span></div>
                                        <div class="input-help-left">Total solar panels to be installed</div>
                                    </div>
                                    <div class="install-details-cell install-details-border">
                                        <label for="electricityRate" class="form-label-right">Electricity Rate (<span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>/kWh)</label>
                                        <input type="number" class="form-control" id="electricityRate" value="<?php echo esc_attr(ksrad_get_option('default_electricity_rate', '0.45')); ?>" step="0.01"
                                            min="0" required>
                                        <div class="input-help-right">Enter your current unit cost per kWh</div>
                                    </div>
                                </div>
                                <div class="install-details-row">
                                    <div class="install-details-cell">
                                        <label for="yearlyEnergy" class="form-label-left">Annual Energy Production (KWh)</label>
                                        <div class="energy-display-left">
                                            <span id="yearlyEnergyValue" class="highlighted-value">0</span>
                                        </div>
                                        <input type="hidden" id="yearlyEnergy" value="0" required>
                                        <div class="input-help-left">Estimated from solar analysis</div>
                                    </div>
                                    <div class="install-details-cell install-details-border">
                                        <label for="exportRate" class="form-label-right">Feed-in Tariff (<span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>/kWh)</label>
                                        <input type="number" class="form-control" id="exportRate" value="<?php echo esc_attr(ksrad_get_option('default_feed_in_tariff', '0.21')); ?>" step="0.01"
                                            min="0" required>
                                        <div class="input-help-right">Clean Export Guarantee / Feed-in tariff</div>
                                    </div>
                                </div>
                                <div class="install-details-row">
                                    <div class="install-details-cell">
                                        <label for="monthlyBill" class="form-label-left">Electricity Bill (Monthly)</label>
                                        <div class="energy-display-left"><span id="monthlyBill"
                                                class="highlighted-value">0</span></div>
                                        <div class="input-help-left">Current monthly electricity expense (<span class="currency-symbol"><?php echo esc_html(ksrad_get_option('currency', '€')); ?></span>)</div>
                                    </div>
                                    <div class="install-details-cell install-details-border">
                                        <label for="annualIncrease" class="form-label-right">Annual Price Increase</label>
                                        <div class="energy-display-right"><span id="annualIncrease"
                                                class="highlighted-value"><?php echo esc_html(ksrad_get_option('annual_price_increase', '5')); ?></span></div>
                                        <div class="input-help-right">Expected electricity price inflation</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <script>
                            // Configuration for utility-functions.js
                            window.KSRAD_UtilityConfig = {
                                solarConfigs: <?php echo wp_json_encode(!empty($ksrad_solarData['solarPotential']['solarPanelConfigs']) ? $ksrad_solarData['solarPotential']['solarPanelConfigs'] : []); ?>,
                                currencySymbol: '<?php echo esc_js(ksrad_get_option('currency', '€')); ?>',
                                grantsTable: <?php 
                                    $grants_table = ksrad_get_option('grants_table', array());
                                    if (empty($grants_table)) {
                                        // Default grants if not configured
                                        $grants_table = array(
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
                                    echo wp_json_encode($grants_table);
                                ?>
                            };
                        </script>
                        <!-- Utility functions script enqueued via WordPress -->

                        <script>
                            // Currency symbol and getPanelCount - expose globally for external scripts
                            window.CURRENCY_SYMBOL = '<?php echo esc_js(ksrad_get_option('currency', '€')); ?>';
                            
                            window.getPanelCount = function getPanelCount() {
                                const range = document.querySelector('input[type="range"]#panelCount');
                                if (range) return parseInt(range.value, 10) || 0;
                                const disp = document.getElementById('panelCountValue') || document.getElementById('panelCountDisplay');
                                if (disp) return parseInt(disp.textContent.trim(), 10) || 0;
                                return 0;
                            };
                            
                            // Configuration for event-handlers.js
                            window.KSRAD_EventConfig = {
                                currencySymbol: '<?php echo esc_js(ksrad_get_option('currency', '€')); ?>',
                                seaiGrantRate: <?php echo esc_js(ksrad_get_option('seai_grant_rate', '30') / 100); ?>,
                                seaiGrantCap: <?php echo esc_js(ksrad_get_option('seai_grant_cap', '162000')); ?>,
                                acaRate: <?php echo esc_js(ksrad_get_option('aca_rate', '12.5') / 100); ?>
                            };
                        </script>
                        <!-- Event handlers script enqueued via WordPress -->
                </div>
                <!-- Building Overview -->


                <div class="section site-overview mt-4 pdf-page">

                    <div class="middle-column">

                        <h4 class="text-center mb-4">Building Overview</h4>

                        <div class="overview-grid">
                            <div class="overview-item text-center">
                                <h6>Location</h6>
                                <div class="value"><?php echo esc_html($ksrad_business_name); ?></div>
                            </div>

                            <div class="overview-item text-center">
                                <h6>Coordinates</h6>
                                <div class="value"><?php echo number_format($ksrad_latitude, 6); ?>,
                                    <?php echo number_format($ksrad_longitude, 6); ?></div>
                            </div>

                            <div class="overview-item text-center">
                                <h6>Roof Orientation / Azimuth (°)</h6>
                                <div class="value">
                                    <?php
                                    $ksrad_azimuth = null;
                                    if (isset($ksrad_solarData['roofSegmentStats'][0]['azimuthDegrees'])) {
                                        $ksrad_azimuth = $ksrad_solarData['roofSegmentStats'][0]['azimuthDegrees'];
                                    } elseif (isset($ksrad_solarData['solarPotential']['roofSegmentStats'][0]['azimuthDegrees'])) {
                                        $ksrad_azimuth = $ksrad_solarData['solarPotential']['roofSegmentStats'][0]['azimuthDegrees'];
                                    }
                                    function ksrad_azimuthToCompass($azimuth)
                                    {
                                        if (!is_numeric($azimuth))
                                            return '';
                                        $directions = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW', 'N'];
                                        $normalized = fmod((float) $azimuth, 360.0);
                                        $ix = (int) round($normalized / 45.0);
                                        return $directions[$ix];
                                    }
                                    if ($ksrad_azimuth !== null) {
                                        $ksrad_azimuthVal = floatval($ksrad_azimuth);
                                        $ksrad_compass = ksrad_azimuthToCompass($ksrad_azimuthVal);
                                        echo esc_html(number_format($ksrad_azimuthVal, 2)) . '°';
                                        if ($ksrad_compass)   
                                            echo ' (' . esc_html($ksrad_compass) . ')';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>

                            <div class="overview-item text-center">
                                <h6>Roof Pitch (°)</h6>
                                <div class="value">
                                    <?php
                                    $ksrad_pitch = null;
                                    if (isset($ksrad_solarData['roofSegmentStats'][0]['pitchDegrees'])) {
                                        $ksrad_pitch = $ksrad_solarData['roofSegmentStats'][0]['pitchDegrees'];
                                    } elseif (isset($ksrad_solarData['solarPotential']['roofSegmentStats'][0]['pitchDegrees'])) {
                                        $ksrad_pitch = $ksrad_solarData['solarPotential']['roofSegmentStats'][0]['pitchDegrees'];
                                    }
                                    echo ($ksrad_pitch !== null) ? floatval($ksrad_pitch) : 'N/A';
                                    ?>
                                </div>
                            </div>

                            <div class="overview-item text-center">
                                <h6>Solar Potential</h6>
                                <div class="value">
                                    <?php
                                    // Robustly find a yearlyEnergyDcKwh value to display.
                                    $ksrad_last_kwh = 0;
                                    // Prefer the roofSegmentStats inside solarPotential when present
                                    if (!empty($ksrad_solarData['solarPotential']['roofSegmentStats']) && is_array($ksrad_solarData['solarPotential']['roofSegmentStats'])) {
                                        $ksrad_lastSeg = end($ksrad_solarData['solarPotential']['roofSegmentStats']);
                                        if (isset($ksrad_lastSeg['yearlyEnergyDcKwh'])) {
                                            $ksrad_last_kwh = (float) $ksrad_lastSeg['yearlyEnergyDcKwh'];
                                        } elseif (isset($ksrad_lastSeg['stats']['yearlyEnergyDcKwh'])) {
                                            $ksrad_last_kwh = (float) $ksrad_lastSeg['stats']['yearlyEnergyDcKwh'];
                                        }
                                    }
                                    // Fallback: take the first solarPanelConfig yearlyEnergy if available
                                    if (empty($ksrad_last_kwh) && !empty($ksrad_solarData['solarPotential']['solarPanelConfigs']) && isset($ksrad_solarData['solarPotential']['solarPanelConfigs'][0]['yearlyEnergyDcKwh'])) {
                                        $ksrad_last_kwh = (float) $ksrad_solarData['solarPotential']['solarPanelConfigs'][0]['yearlyEnergyDcKwh'];
                                    }
                                    if (!empty($ksrad_last_kwh) && $ksrad_last_kwh > 0) {
                                        echo esc_html(ksrad_format_kwh($ksrad_last_kwh));
                                    } else {
                                        echo esc_html('N/A');
                                    }
                                    ?>
                                </div>

                            <?php if (isset($ksrad_solarData['solarPotential']['maxArrayPanelsCount'])): ?>
                                <div class="overview-item text-center">
                                    <h6>Max Panel Capacity</h6>
                                    <div class="value"><?php echo esc_html($ksrad_solarData['solarPotential']['maxArrayPanelsCount']); ?> panels
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($ksrad_solarData['solarPotential']['maxArrayAreaMeters2'])): ?>
                                <div class="overview-item text-center">
                                    <h6>Max Array Area</h6>
                                    <div class="value">
                                        <?php echo esc_html(ksrad_format_area($ksrad_solarData['solarPotential']['maxArrayAreaMeters2'])); ?> m²</div>
                                </div>
                            <?php endif; ?>

                        </div>

                    </div>

                </div>


                <!-- Solar Panel Configurations -->
                <div class="section mt-5">
                    <h2 class="text-center mb-3">
                        <button class="btn btn-link" type="button" data-bs-toggle="collapse"
                            data-bs-target="#configurationsCollapse" aria-expanded="false"
                            aria-controls="configurationsCollapse"
                            style="text-decoration: none; color: #fff; padding: 20px 40px;">
                            <large>Recommended Solar Panel Configurations</large>
                            <small>(click to open)</small>
                        </button>
                    </h2>
                    <div class="collapse" id="configurationsCollapse">
                        <?php foreach ($ksrad_solarData['solarPotential']['solarPanelConfigs'] as $ksrad_index => $ksrad_config): ?>
                            <div class="panel-config">
                                <h3>Configuration <?php echo esc_html($ksrad_index + 1); ?></h3>
                                <div class="data-row">
                                    <span class="data-label">Number of Panels:</span>
                                    <span><?php echo esc_html($ksrad_config['panelsCount']); ?></span>
                                </div>
                                <div class="data-row">
                                    <span class="data-label">Annual Energy Production:</span>
                                    <span class="display-grant">
                                        <?php
                                        // Always show the 4-panel config value as placeholder
                                        $ksrad_fourPanelConfig2 = null;
                                        foreach ($ksrad_solarData['solarPotential']['solarPanelConfigs'] as $ksrad_c) {
                                            if ($ksrad_c['panelsCount'] == 4) {
                                                $ksrad_fourPanelConfig2 = $ksrad_c;
                                                break;
                                            }
                                        }
                                        if ($ksrad_fourPanelConfig2) {
                                            echo esc_html(ksrad_format_kwh($ksrad_fourPanelConfig2['yearlyEnergyDcKwh']));
                                        } else {
                                            echo esc_html(ksrad_format_kwh($ksrad_config['yearlyEnergyDcKwh']));
                                        }
                                        ?> kWh
                                    </span>
                                </div>

                                <!-- Configuration Details Table -->
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Roof Segment</th>
                                            <th>Panels</th>
                                            <th>Pitch (°)</th>
                                            <th>Azimuth (°)</th>
                                            <th>Energy (kWh/year)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ksrad_config['roofSegmentSummaries'] as $ksrad_segment): ?>
                                            <tr>
                                                <td><?php echo esc_html($ksrad_segment['segmentIndex'] + 1); ?></td>
                                                <td><?php echo esc_html($ksrad_segment['panelsCount']); ?></td>
                                                <td><?php echo esc_html(number_format($ksrad_segment['pitchDegrees'], 1)); ?>°</td>
                                                <td><?php echo esc_html(number_format($ksrad_segment['azimuthDegrees'], 1)); ?>°</td>
                                                <td><?php echo esc_html(ksrad_format_kwh($ksrad_segment['yearlyEnergyDcKwh'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>
    </div><!-- End .newContainer -->

    <?php if ($ksrad_solarDataAvailable): ?>
        <script>
            // Configuration for chart-initialization.js
            window.KSRAD_ChartConfig = {
                currencySymbol: '<?php echo esc_js(ksrad_get_option('currency', '€')); ?>',
                solarConfigurations: <?php echo wp_json_encode($ksrad_solarData['solarPotential']['solarPanelConfigs']); ?>
            };
        </script>
        <!-- Chart initialization script enqueued via WordPress -->
    <?php endif; ?>

        <script>
            // Configuration for solar-calculator-main.js
            window.KSRAD_CalcConfig = {
                currencySymbol: '<?php echo esc_js(ksrad_get_option('currency', '€')); ?>',
                seaiGrantRate: <?php echo esc_js(ksrad_get_option('seai_grant_rate', '30') / 100); ?>,
                seaiGrantCap: <?php echo esc_js(ksrad_get_option('seai_grant_cap', '162000')); ?>
            };
        </script>
        <!-- Solar calculator main script enqueued via WordPress -->

        <!-- Solar Calculator v1.0.9 -->

        <?php if (!apply_filters('ksrad_is_premium', false)): ?>
        <h6 style="color: #2a2a28; text-align: center;font-family: 'Brush Script MT', cursive;"><a href="https://keiste.com" target="_blank" rel="noopener noreferrer">Get Your Keiste Solar Report</a></h6>
        <?php endif; ?>

</div><!-- #keiste-solar-report-wrapper -->
<?php
// Return the buffered content to shortcode handler
// Do NOT call ob_end_flush() - let the shortcode handler return the content

// AJAX Handler for Gamma PDF Generation
if (!function_exists('ksrad_verify_email')) {
    function ksrad_verify_email($email) {
        // Use the public emailchecker API
        $api_url = 'https://emailcheck-api.thexos.dev/check/' . urlencode($email);
        
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            @error_log('Email verification API error: ' . $response->get_error_message());
            // Don't block on API failure, just log it
            return true;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            @error_log('Email verification API returned status: ' . $status_code);
            // Don't block on API failure
            return true;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            @error_log('Email verification: Failed to parse API response');
            return true;
        }
        
        // Check the prediction risk level
        if (isset($data['prediction']['risk_level'])) {
            $risk_level = $data['prediction']['risk_level'];
            $reasons = isset($data['prediction']['reasons']) ? $data['prediction']['reasons'] : array();
            
            @error_log('Email verification for ' . $email . ': ' . $risk_level . ' - ' . json_encode($reasons));
            
            // Block high risk emails (disposable, suspicious patterns, etc.)
            if ($risk_level === 'high') {
                return array(
                    'valid' => false,
                    'risk_level' => $risk_level,
                    'reasons' => $reasons
                );
            }
            
            // Allow low and medium risk
            return array(
                'valid' => true,
                'risk_level' => $risk_level,
                'reasons' => $reasons
            );
        }
        
        // If no prediction data, allow the email
        return true;
    }
}

if (!function_exists('ksrad_verify_phone_number')) {
    function ksrad_verify_phone_number($phone, $account_sid, $auth_token) {
        // Debug log credentials (masked)
        @error_log('Twilio verification - SID: ' . substr($account_sid, 0, 8) . '... Token: ' . substr($auth_token, 0, 8) . '... Phone: ' . $phone);
        
        // Check if Twilio SDK is available via Composer
        if (!class_exists('Twilio\Rest\Client')) {
            @error_log('Twilio SDK not found, attempting direct API call');
            
            // Fallback to direct HTTP request if SDK is not available
            $url = 'https://lookups.twilio.com/v2/PhoneNumbers/' . urlencode($phone);
            
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($account_sid . ':' . $auth_token)
                ),
                'timeout' => 10
            ));
            
            if (is_wp_error($response)) {
                @error_log('Twilio API error: ' . $response->get_error_message());
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            if ($status_code !== 200) {
                @error_log('Twilio API returned status: ' . $status_code . ' Body: ' . $body);
                return false;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['valid']) && $data['valid'] === true) {
                return array(
                    'phone_number' => $data['phone_number'] ?? $phone,
                    'national_format' => $data['national_format'] ?? '',
                    'country_code' => $data['country_code'] ?? '',
                    'valid' => true
                );
            }
            
            return false;
        }
        
        // Use Twilio SDK if available
        try {
            @error_log('Using Twilio SDK for phone verification');
            $twilio = new \Twilio\Rest\Client($account_sid, $auth_token);
            $phone_number = $twilio->lookups->v2->phoneNumbers($phone)->fetch();
            
            if ($phone_number->valid) {
                @error_log('Phone number validated successfully via SDK');
                return array(
                    'phone_number' => $phone_number->phoneNumber,
                    'national_format' => $phone_number->nationalFormat ?? '',
                    'country_code' => $phone_number->countryCode ?? '',
                    'valid' => true
                );
            }
            
            @error_log('Phone number validation failed - marked invalid by Twilio');
            return false;
        } catch (Exception $e) {
            @error_log('Twilio SDK error: ' . $e->getMessage() . ' | Code: ' . $e->getCode() . ' | Class: ' . get_class($e));
            return false;
        }
    }
}

if (!function_exists('ksrad_handle_gamma_pdf_generation')) {
    function ksrad_handle_gamma_pdf_generation() {
    // Send a test response first to see if function is even called
    error_log('=== GAMMA PDF GENERATION FUNCTION CALLED ===');
    
    // Check if this is even an AJAX request
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        error_log('ERROR: Not an AJAX request');
        wp_send_json_error(array('message' => 'Not an AJAX request'));
        return;
    }
    
    error_log('POST data: ' . print_r($_POST, true));
    
    // Verify nonce - but don't die on failure, log it
    $nonce_valid = check_ajax_referer('ksrad_gamma_pdf', 'nonce', false);
    if (!$nonce_valid) {
        error_log('NONCE VERIFICATION FAILED');
        wp_send_json_error(array(
            'message' => 'Security check failed. Please refresh the page and try again.',
            'error_type' => 'nonce_failure'
        ));
        wp_die();
    }
    
    // Get form data
    $full_name = sanitize_text_field($_POST['fullName'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $panel_count = intval($_POST['panelCount'] ?? 0);
    $location = sanitize_text_field($_POST['location'] ?? '');
    
    @error_log('Form data received: Name=' . $full_name . ', Email=' . $email . ', Phone=' . $phone . ', Panels=' . $panel_count);
    
    // Verify email using emailchecker API
    if (!empty($email)) {
        $email_check = ksrad_verify_email($email);
        
        if (is_array($email_check) && isset($email_check['valid']) && !$email_check['valid']) {
            @error_log('Email verification failed for: ' . $email);
            $reasons = isset($email_check['reasons']) ? implode(', ', $email_check['reasons']) : 'Unknown reason';
            wp_send_json_error(array(
                'message' => 'Invalid or disposable email address. Please use a valid email. Reason: ' . $reasons,
                'error_type' => 'email_verification_failed'
            ));
            return;
        }
    }
    
    // Verify phone number using Twilio Lookup API
    $twilio_sid = ksrad_get_option('twilio_account_sid', NULL);
    $twilio_token = ksrad_get_option('twilio_auth_token', NULL);
    
    if (!empty($twilio_sid) && !empty($twilio_token) && !empty($phone)) {
        $phone_verified = ksrad_verify_phone_number($phone, $twilio_sid, $twilio_token);
        
        if ($phone_verified === false) {
            @error_log('Phone verification failed for: ' . $phone);
            wp_send_json_error(array(
                'message' => 'Invalid phone number. Please check and try again.',
                'error_type' => 'phone_verification_failed'
            ));
            return;
        } else if (is_array($phone_verified)) {
            // Log the verified phone details
            @error_log('Phone verified: ' . json_encode($phone_verified));
            // Use the formatted phone number from Twilio
            $phone = $phone_verified['phone_number'];
        }
    }
    
    // Get API key from settings
    $gamma_api_key = ksrad_get_option('gamma_api_key', NULL);
    $gamma_template_id = ksrad_get_option('gamma_template_id', NULL);
    // gamma folder id is needed 
    $gamma_folder_id = ksrad_get_option('gamma_folder_id', NULL);

    if (empty($gamma_api_key) || empty($gamma_template_id)) {
        wp_send_json_error('Gamma API key or template ID not configured');
        return;
    }
    
    // Get essential solar data for the report
    $ksrad_solarData = ksrad_get_option(ksrad_get_default_solar_data(), NULL);
    
    // Extract only key metrics to avoid overwhelming the API
    $max_panels = $ksrad_solarData['solarPotential']['maxArrayPanelsCount'] ?? 0;
    $max_area = $ksrad_solarData['solarPotential']['maxArrayAreaMeters2'] ?? 0;
    $yearly_energy = 0;
    
    // Find energy production for selected panel count
    if (isset($ksrad_solarData['solarPotential']['solarPanelConfigs'])) {
        foreach ($ksrad_solarData['solarPotential']['solarPanelConfigs'] as $config) {
            if (intval($config['panelsCount'] ?? 0) === $panel_count) {
                $yearly_energy = floatval($config['yearlyEnergyDcKwh'] ?? 0);
                break;
            }
        }
    }
    
    // Create concise data summary
    $solar_summary = sprintf(
        "Max capacity: %d panels on %.1f m². Selected system size (chosen by user): %d panels producing ~%.0f kWh/year.",
        $max_panels,
        $max_area,
        $panel_count,
        $yearly_energy
    );
    
    // Build prompt with essential data only
    $prompt = sprintf( 
        "Generate a professional solar report for %s at %s.\n\nChosen system Details:\n- %d x 400W solar panels\n- Annual production: %.0f kWh\n- Contact: %s\n- Phone: %s\n\nProperty Analysis:\n%s",
        $full_name,
        $location,
        $panel_count,
        $yearly_energy,
        $email,
        $phone,
        $solar_summary
    );
    
    // Prepare Gamma API request body
    $request_body = array(
        'gammaId' => $gamma_template_id,
        'prompt' => $prompt,
        'themeId' => 'default-light',
        'exportAs' => 'pdf',
        'imageOptions' => array(
            'source' => 'aiGenerated',
            'model' => 'imagen-4-pro',
            'style' => 'minimal black and white colour theme with black and white line art. Do not use background images with text overlays.'
        ),
        'sharingOptions' => array(
            'workspaceAccess' => 'view',
            'externalAccess' => 'view'
        )
    );
    
    // Encode JSON with proper options
    $json_body = json_encode($request_body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    // Log request for debugging
    error_log('Gamma API Request - Template ID: ' . $gamma_template_id);
    error_log('Gamma API Request - Email: ' . $email);
    error_log('Gamma API Request - Prompt length: ' . strlen($prompt));
    error_log('Gamma API Request Body: ' . $json_body);
    error_log('Gamma API Request Body Length: ' . strlen($json_body));
    error_log('=== CURL COMMAND FOR BROWSER/TESTING ===');
    error_log('curl -X POST https://public-api.gamma.app/v1.0/generations/from-template \\');
    error_log('  -H "Content-Type: application/json" \\');
    error_log('  -H "X-API-KEY: ' . $gamma_api_key . '" \\');
    error_log('  -d \'' . $json_body . '\'');
    error_log('=== END CURL COMMAND ===');
    
    // Call Gamma API
    $response = wp_remote_post('https://public-api.gamma.app/v1.0/generations/from-template', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-API-KEY' => $gamma_api_key
        ),
        'body' => $json_body,
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $response_code = wp_remote_retrieve_response_code($response);
    
    // Log detailed error information for debugging
    error_log('Gamma API Response Code: ' . $response_code);
    error_log('Gamma API Response Body: ' . $body);
    
    if ($response_code !== 200) {
        $error_message = $data['message'] ?? $data['error'] ?? 'Unknown error from Gamma API (Status: ' . $response_code . ')';
        error_log('Gamma API Error: ' . $error_message);
        
        // Build curl command for debugging
        $curl_command = "curl -X POST 'https://public-api.gamma.app/v1.0/generations/from-template' \\\n  -H 'Content-Type: application/json' \\\n  -H 'X-API-KEY: " . $gamma_api_key . "' \\\n  -d '" . str_replace("'", "'\\''", $json_body) . "'";
        
        // Return detailed debug info even on error
        wp_send_json_error(array(
            'message' => $error_message,
            'curl_command' => $curl_command,
            'debug' => array(
                'url' => 'https://public-api.gamma.app/v1.0/generations/from-template',
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-KEY' => substr($gamma_api_key, 0, 10) . '...'
                ),
                'body' => $request_body,
                'response_code' => $response_code,
                'response_body' => $data
            )
        ));
        return;
    }
    
    // Build curl command for debugging
    $curl_command = "curl -X POST 'https://public-api.gamma.app/v1.0/generations/from-template' \\\n  -H 'Content-Type: application/json' \\\n  -H 'X-API-KEY: " . $gamma_api_key . "' \\\n  -d '" . str_replace("'", "'\\''", $json_body) . "'";
    
    // Log the generation (optional)
    error_log(sprintf(
        'Gamma PDF generated for %s (%s) - Panel count: %d',
        $full_name,
        $email,
        $panel_count
    ));
    
    // Send notification email to admin
    $to = ksrad_get_option('notification_email', get_option('admin_email'));
    $subject = 'Solar Form Submitted';
    $message = sprintf(
        "New Solar Report Request\n\n" .
        "Full Name: %s\n" .
        "Email: %s\n" .
        "Phone: %s\n" .
        "Panel Count: %d\n" .
        "Location: %s\n\n" .
        "Submitted on: %s",
        $full_name,
        $email,
        $phone,
        $panel_count,
        $location,
        current_time('mysql')
    );
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    wp_mail($to, $subject, $message, $headers);

    wp_send_json_success(array(
        'message' => 'PDF generated successfully',
        'gamma_response' => $data,
        'curl_command' => $curl_command,
        'debug' => array(
            'url' => 'https://public-api.gamma.app/v1.0/generations/from-template',
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-KEY' => substr($gamma_api_key, 0, 10) . '...' // Partial key for security
            ),
            'body' => $request_body
        )
    ));
    }
}

// Register AJAX handlers OUTSIDE function_exists check
add_action('wp_ajax_ksrad_generate_gamma_pdf', 'ksrad_handle_gamma_pdf_generation');
add_action('wp_ajax_nopriv_ksrad_generate_gamma_pdf', 'ksrad_handle_gamma_pdf_generation');
?>