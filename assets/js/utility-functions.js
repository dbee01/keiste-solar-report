/**
 * Utility Functions for Solar Calculator
 * Provides energy estimation, cost calculation, currency formatting, and grant lookup
 */
(() => {
    'use strict';

    // Configuration variables - will be set from PHP config
    let solarConfigs = [];
    let CURRENCY_SYMBOL = '€';
    let GRANTS_TABLE = [];

    /**
     * Estimate energy production based on panel count
     * Uses interpolation between known solar configurations
     */
    function estimateEnergyProduction(panelCount) {
        // Find the closest configurations
        let lowerConfig = null;
        let upperConfig = null;
        for (const config of solarConfigs) {
            if (config.panelsCount <= panelCount) {
                lowerConfig = config;
            }
            if (config.panelsCount >= panelCount && !upperConfig) {
                upperConfig = config;
            }
        }
        // If exact match found
        if (lowerConfig && lowerConfig.panelsCount === panelCount) {
            return lowerConfig.yearlyEnergyDcKwh;
        }
        // If panel count is less than smallest configuration
        if (!lowerConfig) {
            const smallestConfig = solarConfigs[0];
            return (panelCount / smallestConfig.panelsCount) * smallestConfig.yearlyEnergyDcKwh;
        }
        // If panel count is more than largest configuration
        if (!upperConfig) {
            const largestConfig = solarConfigs[solarConfigs.length - 1];
            return (panelCount / largestConfig.panelsCount) * largestConfig.yearlyEnergyDcKwh;
        }
        // Interpolate between configurations
        const panelDiff = upperConfig.panelsCount - lowerConfig.panelsCount;
        const energyDiff = upperConfig.yearlyEnergyDcKwh - lowerConfig.yearlyEnergyDcKwh;
        const ratio = (panelCount - lowerConfig.panelsCount) / panelDiff;
        return lowerConfig.yearlyEnergyDcKwh + (energyDiff * ratio);
    }

    /**
     * Calculate installation cost based on yearly energy production
     * Uses sliding scale pricing for different system sizes
     */
    function calculateInstallationCost(yearlyEnergyKwh) {
        // Convert yearly energy (kWh) to installed capacity (kWp)
        // Using average solar irradiance: 1 kWp produces ~1000 kWh/year in Ireland
        const installedCapacityKwp = yearlyEnergyKwh / 1000;
        // Apply sliding scale pricing
        let totalCost = 0;
        if (installedCapacityKwp <= 100) {
            // 0-100 kWp: €1,500 per kWp
            totalCost = installedCapacityKwp * 1500;
        } else if (installedCapacityKwp <= 250) {
            // 100-250 kWp: €1,300 per kWp
            // First 100 kWp at €1,500, remainder at €1,300
            totalCost = (100 * 1500) + ((installedCapacityKwp - 100) * 1300);
        } else {
            // 250+ kWp: €1,100 per kWp
            // First 100 kWp at €1,500, next 150 kWp at €1,300, remainder at €1,100
            totalCost = (100 * 1500) + (150 * 1300) + ((installedCapacityKwp - 250) * 1100);
        }
        return totalCost;
    }

    /**
     * Format currency with symbol and locale formatting
     */
    function formatCurrency(value, decimals = 0) {
        // Use locale formatting and prefix with currency symbol from settings
        const num = Number(value) || 0;
        return CURRENCY_SYMBOL + num.toLocaleString('en-IE', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    }

    /**
     * Get grant information for specific country and building type
     */
    function getGrantForCountryAndType(country, buildingType) {
        // Map dropdown values to full country names used in grants table
        const countryMap = {
            'USA': 'United States',
            'Canada': 'Canada',
            'UK': 'UK',
            'Ireland': 'Rep. of Ireland'
        };
        const fullCountry = countryMap[country] || country;
        
        // Treat anything non-residential as non-residential for grant purposes
        const grantBuildingType = (buildingType === 'Residential') ? 'Residential' : 'Non-Residential';
        
        const grant = GRANTS_TABLE.find(g => 
            g.country === fullCountry && g.building_type === grantBuildingType
        );
        
        if (grant) {
            return {
                rate: grant.grant_percentage / 100,
                cap: grant.grant_max
            };
        }
        
        // Default fallback
        return { rate: 0, cap: 0 };
    }

    /**
     * Initialize utility functions with configuration from PHP
     */
    function init() {
        if (window.KSRAD_UtilityConfig) {
            solarConfigs = window.KSRAD_UtilityConfig.solarConfigs || [];
            CURRENCY_SYMBOL = window.KSRAD_UtilityConfig.currencySymbol || '€';
            GRANTS_TABLE = window.KSRAD_UtilityConfig.grantsTable || [];
        }
    }

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose functions globally for other scripts
    window.estimateEnergyProduction = estimateEnergyProduction;
    window.calculateInstallationCost = calculateInstallationCost;
    window.formatCurrency = formatCurrency;
    window.getGrantForCountryAndType = getGrantForCountryAndType;
})();
