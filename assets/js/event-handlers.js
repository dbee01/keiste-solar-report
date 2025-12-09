/**
 * Event Handlers for Solar Calculator
 * Wires up UI events to trigger calculations and chart updates
 */
(() => {
    'use strict';

    // Configuration variables - will be set from PHP config
    let CURRENCY_SYMBOL = '€';
    let SEAI_GRANT_RATE = 0.3;
    let SEAI_GRANT_CAP = 162000;
    let ACA_RATE = 0.125;

    /**
     * Format currency value
     */
    function fmt(v, d = 0) {
        return (typeof window.formatCurrency === 'function') 
            ? window.formatCurrency(v, d) 
            : (CURRENCY_SYMBOL + Number(v).toLocaleString());
    }

    /**
     * Update cost displays when panel count or options change
     */
    function updateCosts() {
        try {
            const panelCount = typeof window.getPanelCount === 'function' 
                ? window.getPanelCount() 
                : 0;
            
            // Determine yearly energy using existing helper if available
            const yearlyEnergy = (typeof window.estimateEnergyProduction === 'function')
                ? window.estimateEnergyProduction(panelCount)
                : (parseFloat(document.getElementById('yearlyEnergy')?.value || 0) || 0);

            // Installation cost uses existing helper if present
            const installCost = (typeof window.calculateInstallationCost === 'function')
                ? window.calculateInstallationCost(yearlyEnergy)
                : (yearlyEnergy / 1000) * 1500;

            // Get current country and building type selections
            const selectedCountry = (typeof window.COUNTRY_SETTING !== 'undefined' && window.COUNTRY_SETTING) 
                ? (window.COUNTRY_SETTING === 'United States' ? 'USA' : window.COUNTRY_SETTING === 'UK' ? 'UK' : window.COUNTRY_SETTING === 'Canada' ? 'Canada' : 'Ireland')
                : 'USA';
            const selectedBuildingType = (typeof window.BUILDING_TYPE !== 'undefined' && window.BUILDING_TYPE) || 'Residential';
            
            // Get dynamic grant based on selections
            if (typeof window.getGrantForCountryAndType === 'function') {
                const grantInfo = window.getGrantForCountryAndType(selectedCountry, selectedBuildingType);
                SEAI_GRANT_RATE = grantInfo.rate;
                SEAI_GRANT_CAP = grantInfo.cap;
            }

            const inclGrant = !!document.getElementById('inclGrant')?.checked;
            const inclACA = !!document.getElementById('inclACA')?.checked;

            const seaiGrant = inclGrant ? Math.min(installCost * SEAI_GRANT_RATE, SEAI_GRANT_CAP) : 0;
            const acaGrant = inclACA ? (installCost * ACA_RATE) : 0;
            const totalGrant = seaiGrant + acaGrant;

            const netCost = Math.max(0, installCost - totalGrant);

            // Update DOM elements (handle duplicate IDs/duplicate blocks by updating all matches)
            // Update visible cost and panel count displays. Note: there are duplicate
            // elements with id="panelCount" in the markup (a range input and a
            // display span). We intentionally skip input elements when updating
            // textual displays so the range input is not modified here.
            const idsToSet = ['installCost', 'installationCost', 'netCost', 'grant', 'panelCountValue', 'panelCountDisplay', 'panelCount'];
            idsToSet.forEach(id => {
                document.querySelectorAll('#' + id).forEach(el => {
                    if (!el) return;
                    // Don't overwrite form inputs (the slider) when updating
                    // the displayed panel count — only update non-inputs.
                    if (el.tagName && el.tagName.toUpperCase() === 'INPUT') return;
                    switch (id) {
                        case 'installCost':
                        case 'installationCost':
                            el.textContent = fmt(installCost, 0);
                            break;
                        case 'netCost':
                            el.textContent = fmt(netCost, 0);
                            break;
                        case 'grant':
                            el.textContent = fmt(totalGrant, 0);
                            break;
                        case 'panelCountValue':
                        case 'panelCountDisplay':
                        case 'panelCount':
                            el.textContent = String(panelCount);
                            break;
                        default:
                            break;
                    }
                });
            });

            // NOTE: netIncome / monthly charge is handled by the main calculateROI/keyFigures flow
            // to avoid conflicting parallel listeners. Do not set #netIncome here.

            // Trigger charts/update hooks
            if (typeof window.updateEnergyChart === 'function') {
                window.updateEnergyChart();
            }
            
            if (typeof window.calculateBreakEvenDataSimple === 'function' && window.breakEvenChart) {
                try {
                    const cfg = { panelsCount: panelCount, yearlyEnergyDcKwh: yearlyEnergy };
                    const be = window.calculateBreakEvenDataSimple(cfg);
                    if (be && be.savings && window.breakEvenChart && window.breakEvenChart.data && window.breakEvenChart.data.datasets && window.breakEvenChart.data.datasets[0]) {
                        // Create a new array reference to ensure Chart.js detects the change
                        window.breakEvenChart.data.datasets[0].data = [...be.savings];
                        window.breakEvenChart.data.datasets[0].label = `${panelCount} Panels`;
                        // Force chart to recalculate scales and redraw
                        window.breakEvenChart.options.scales.y.min = undefined;
                        window.breakEvenChart.options.scales.y.max = undefined;
                        window.breakEvenChart.update('active');
                    }
                } catch (e) {
                    console.error('Chart update error:', e);
                }
            }
        } catch (err) {
            console.error('updateCosts error', err);
        }
    }

    /**
     * Attach event listeners to form elements
     */
    function attachHooks() {
        const range = document.querySelector('input[type="range"]#panelCount');
        if (range) {
            range.addEventListener('input', () => {
                console.log('Slider input - window.calculateROI exists?', typeof window.calculateROI);
                if (typeof window.calculateROI === 'function') {
                    window.calculateROI();
                } else {
                    console.error('window.calculateROI is not a function!');
                }
            });
            range.addEventListener('change', () => {
                console.log('Slider change - window.calculateROI exists?', typeof window.calculateROI);
                if (typeof window.calculateROI === 'function') {
                    window.calculateROI();
                }
            });
        }
        
        ['inclGrant', 'inclACA', 'inclLoan'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', () => {
                    if (typeof window.calculateROI === 'function') {
                        window.calculateROI();
                    }
                });
            }
        });
        
        ['electricityRate', 'exportRate', 'degradation', 'loanApr', 'loanTerm'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', () => {
                    if (typeof window.calculateROI === 'function') {
                        window.calculateROI();
                    }
                });
            }
        });
        
        // Don't run initial calculation - keep ROI values at zero until user interacts
    }

    /**
     * Initialize module with configuration from PHP
     */
    function init() {
        if (window.KSRAD_EventConfig) {
            CURRENCY_SYMBOL = window.KSRAD_EventConfig.currencySymbol || '€';
            SEAI_GRANT_RATE = window.KSRAD_EventConfig.seaiGrantRate || 0.3;
            SEAI_GRANT_CAP = window.KSRAD_EventConfig.seaiGrantCap || 162000;
            ACA_RATE = window.KSRAD_EventConfig.acaRate || 0.125;
        }
        
        // Expose updateCosts globally if needed by other scripts
        window.updateCosts = updateCosts;
        
        // Attach event handlers after a delay to ensure all dependencies are loaded
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(attachHooks, 500); // Increased delay to ensure window.calculateROI is defined
        } else {
            document.addEventListener('DOMContentLoaded', () => {
                setTimeout(attachHooks, 500); // Wait for window.calculateROI to be exposed
            });
        }
    }

    // Auto-initialize
    init();
})();
