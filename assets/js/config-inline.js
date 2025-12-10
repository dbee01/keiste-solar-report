/**
 * Keiste Solar Report - Inline Configuration
 * This file contains configuration that was previously inline in the PHP file
 * Note: This file is dynamically loaded and configured via wp_localize_script
 */

// Maps Configuration (set via wp_localize_script as KSRAD_MapsConfig)
// window.KSRAD_MapsConfig = {
//     apiKey: '',
//     businessName: '',
//     lat: 0,
//     lng: 0,
//     country: ''
// };

// Utility Configuration (set via wp_localize_script as KSRAD_UtilityConfig)
// window.KSRAD_UtilityConfig = {
//     solarConfigs: [],
//     currencySymbol: '',
//     grantsTable: []
// };

// Chart Configuration (set via wp_localize_script as KSRAD_ChartConfig)
// window.KSRAD_ChartConfig = {
//     currencySymbol: '',
//     solarConfigurations: []
// };

// Calculator Configuration (set via wp_localize_script as KSRAD_CalcConfig)
// window.KSRAD_CalcConfig = {
//     currencySymbol: '',
//     seaiGrantRate: 0,
//     seaiGrantCap: 0
// };

// Global utility functions
window.getPanelCount = function getPanelCount() {
    const range = document.querySelector('input[type="range"]#panelCount');
    if (range) return parseInt(range.value, 10) || 0;
    const disp = document.getElementById('panelCountValue') || document.getElementById('panelCountDisplay');
    if (disp) return parseInt(disp.textContent.trim(), 10) || 0;
    return 0;
};

// Currency symbol (set dynamically)
// window.CURRENCY_SYMBOL = '';
