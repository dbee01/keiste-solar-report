/**
 * Chart Initialization for Solar Calculator
 * Handles Break Even and Energy Production chart setup
 */
(() => {
    'use strict';

    // Configuration variables - will be set from PHP config
    let CURRENCY_SYMBOL = '€';
    let solarConfigurations = [];

    /**
     * Calculate Break Even Data for chart
     */
    function calculateBreakEvenDataSimple(config) {
        const yearlyEnergy = config.yearlyEnergyDcKwh;
        const panelCount = config.panelsCount;
        const electricityRate = parseFloat(document.getElementById('electricityRate')?.value) || 0.45;
        // exportRate in the UI is expressed as a percent (e.g. "40" for 40%).
        // Normalize to a fraction here so calling code can assume 0..1.
        const exportRate = (() => {
            const v = document.getElementById('exportRate')?.value;
            const p = parseFloat(String(v || '').replace(/[^0-9.\-]/g, ''));
            return Number.isFinite(p) ? (p / 100) : 0.4;
        })();
        const inclGrant = document.getElementById('inclGrant')?.checked;
        const inclACA = document.getElementById('inclACA')?.checked;
        const inclLoan = document.getElementById('inclLoan')?.checked;
        const degradation = 0.005;
        // Compute installation cost from yearlyEnergy using sliding-scale per kWp
        const installedCapacityKwp = yearlyEnergy / 1000;
        let computedInstallCost = 0;
        if (installedCapacityKwp <= 100) {
            computedInstallCost = installedCapacityKwp * 1500;
        } else if (installedCapacityKwp <= 250) {
            computedInstallCost = (100 * 1500) + ((installedCapacityKwp - 100) * 1300);
        } else {
            computedInstallCost = (100 * 1500) + (150 * 1300) + ((installedCapacityKwp - 250) * 1100);
        }
        const seaiGrant = inclGrant ? Math.min(computedInstallCost * 0.3, 162000) : 0;
        const acaGrant = inclACA ? (computedInstallCost * 0.125) : 0;
        const totalGrant = seaiGrant + acaGrant;
        const totalCost = computedInstallCost - totalGrant;
        const years = Array.from({ length: 25 }, (_, i) => i); // 0 to 24 years (25-year horizon)
        const savings = years.map(year => {
            const yearDegradation = Math.pow(1 - degradation, year);
            const yearlyEnergyProduction = yearlyEnergy * yearDegradation;
            const selfConsumedEnergy = yearlyEnergyProduction * (1 - exportRate);
            const exportedEnergy = yearlyEnergyProduction * exportRate;
            const yearlySaving = (selfConsumedEnergy * electricityRate) + (exportedEnergy * exportRate);
            const totalSaving = year === 0 ? 0 : years
                .slice(1, year + 1)
                .reduce((acc, y) => {
                    const yDegradation = Math.pow(1 - degradation, y);
                    const yEnergyProduction = yearlyEnergy * yDegradation;
                    const ySelfConsumed = yEnergyProduction * (1 - exportRate);
                    const yExported = yEnergyProduction * exportRate;
                    return acc + (ySelfConsumed * electricityRate) + (yExported * exportRate);
                }, 0);
            return totalSaving - totalCost;
        });
        return { cost: totalCost, savings: savings, breakEvenYear: savings.findIndex(saving => saving >= 0) };
    }

    /**
     * Update Energy Chart with current panel count
     */
    function updateEnergyChart() {
        try {
            if (!window.energyChart) return;
            const panelCount = parseInt(document.getElementById('panelCount')?.value || 0);
            const yearlyEnergy = typeof window.estimateEnergyProduction === 'function' 
                ? window.estimateEnergyProduction(panelCount) 
                : 0;
            const degradation = parseFloat(document.getElementById('degradation')?.value) / 100 || 0.005; // default 0.5% -> 0.005
            const years = Array.from({ length: 25 }, (_, i) => i);
            const data = years.map(year => {
                return yearlyEnergy * Math.pow(1 - degradation, year);
            });
            window.energyChart.data.datasets[0].data = data;
            window.energyChart.data.datasets[0].label = `${panelCount} panels — Annual Production (kWh)`;
            window.energyChart.update();
        } catch (e) {
            console.error('updateEnergyChart error', e);
        }
    }

    /**
     * Initialize charts when Chart.js is available
     */
    function initializeChartsWhenReady() {
        if (typeof Chart === 'undefined') {
            console.log('Chart.js not yet loaded, waiting...');
            setTimeout(initializeChartsWhenReady, 50);
            return;
        }
        console.log('Chart.js loaded! Version:', Chart.version);
        initializeCharts();
    }

    /**
     * Initialize both Break Even and Energy Production charts
     */
    function initializeCharts() {
        console.log('initializeCharts called', {
            alreadyInit: window.ksradChartsInitialized,
            hasBreakEvenChart: !!window.breakEvenChart,
            hasEnergyChart: !!window.energyChart
        });
        
        if (window.ksradChartsInitialized) {
            console.log('Charts already initialized, skipping');
            return;
        }
        window.ksradChartsInitialized = true;
        
        // Check if charts already exist (from external js files)
        if (window.breakEvenChart || window.energyChart) {
            console.log('Charts already exist externally, skipping');
            return;
        }
        
        console.log('Starting chart creation...');
        
        // --- Break Even Chart ---
        try {
            const breakEvenCanvas = document.getElementById('breakEvenChart');
            if (!breakEvenCanvas) {
                console.log('Break Even Chart canvas not found - charts will initialize when form loads');
                window.ksradChartsInitialized = false; // Reset so it can try again later
                return;
            }
            console.log('Initializing Break Even Chart...', breakEvenCanvas);
            const breakEvenCtx = breakEvenCanvas.getContext('2d');
            
            // Initialize with zero data - actual data will populate on user interaction
            const data = { cost: 0, savings: Array(25).fill(0), breakEvenYear: -1 };
            console.log('Creating Chart.js instance with data:', data);
            
            window.breakEvenChart = new Chart(breakEvenCtx, {
                type: 'line',
                data: {
                    labels: Array.from({ length: 25 }, (_, i) => `Year ${i}`),
                    datasets: [{
                        label: `0 Panels`,
                        data: data.savings,
                        borderColor: 'rgba(42, 157, 143, 1)',
                        backgroundColor: 'rgba(42, 157, 143, 0.08)',
                        fill: false,
                        tension: 0.3,
                        pointRadius: window.innerWidth > 600 ? 2 : 0,
                        pointHoverRadius: 4,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: window.innerWidth > 600,
                            text: window.innerWidth > 600 ? 'Investment Return Over Time' : '',
                            font: { size: window.innerWidth > 600 ? 16 : 12, family: "'Inter', sans-serif", weight: '600' }
                        },
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const value = context.raw;
                                    const formatCurrency = window.formatCurrency || ((v) => CURRENCY_SYMBOL + Math.round(v).toLocaleString('en-IE'));
                                    return value >= 0
                                        ? `Profit: ${formatCurrency(value, 0)}`
                                        : `Investment: ${formatCurrency(Math.abs(value), 0)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: window.innerWidth > 600,
                                text: window.innerWidth > 600 ? 'Net Financial Position (' + CURRENCY_SYMBOL + ')' : '',
                                font: { weight: '500', size: window.innerWidth > 600 ? 14 : 11 }
                            },
                            ticks: {
                                callback: function (value) {
                                    return CURRENCY_SYMBOL + value.toLocaleString('en-IE', { maximumFractionDigits: 0 });
                                },
                                font: { size: window.innerWidth > 600 ? 13 : 10 }
                            },
                            grid: { display: window.innerWidth > 600 }
                        },
                        x: {
                            title: {
                                display: window.innerWidth > 600,
                                text: window.innerWidth > 600 ? 'Years' : '',
                                font: { weight: '500', size: window.innerWidth > 600 ? 14 : 11 }
                            },
                            ticks: { font: { size: window.innerWidth > 600 ? 13 : 10 }, autoSkip: true, maxTicksLimit: window.innerWidth > 600 ? 12 : 6 },
                            grid: { display: window.innerWidth > 600 }
                        }
                    }
                }
            });
            console.log('Break Even Chart created successfully!', window.breakEvenChart);
        } catch (error) {
            console.error('Error creating Break Even Chart:', error);
        }
        
        // --- Energy Production Chart ---
        try {
            const ctx = document.getElementById('energyChart').getContext('2d');
            
            window.energyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Array.from({ length: 25 }, (_, i) => `Year ${i}`),
                    datasets: [{
                        label: 'Projected Annual Energy Production (kWh)',
                        data: Array.from({ length: 25 }, () => 0),
                        backgroundColor: 'rgba(40, 167, 69, 0.6)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Annual Energy Production (kWh)'
                            }
                        },
                        x: {
                            ticks: { autoSkip: true, maxTicksLimit: window.innerWidth > 600 ? 12 : 6 }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Projected Energy Production (per year)'
                        }
                    }
                }
            });
            console.log('Energy Chart created successfully!', window.energyChart);
        } catch (error) {
            console.error('Error creating Energy Chart:', error);
        }
        
        // Don't trigger calculateROI automatically - let it be triggered by user interaction
        // This prevents showing non-zero values on initial page load from cached data
        console.log('Charts initialized and ready for user interaction');
    }

    /**
     * Initialize module with configuration from PHP
     */
    function init() {
        if (window.KSRAD_ChartConfig) {
            CURRENCY_SYMBOL = window.KSRAD_ChartConfig.currencySymbol || '€';
            solarConfigurations = window.KSRAD_ChartConfig.solarConfigurations || [];
        }
        
        // Expose functions globally
        window.calculateBreakEvenDataSimple = calculateBreakEvenDataSimple;
        window.updateEnergyChart = updateEnergyChart;
        
        // Listen for reinit event (triggered after AJAX content load)
        document.addEventListener('ksrad-reinit-charts', () => {
            console.log('Received ksrad-reinit-charts event');
            window.ksradChartsInitialized = false;
            window.breakEvenChart = null;
            window.energyChart = null;
            initializeChartsWhenReady();
        });
        
        // Prevent duplicate initialization
        if (window.ksradChartsInitialized) return;
        
        // Start checking for Chart.js availability after DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeChartsWhenReady);
        } else {
            initializeChartsWhenReady();
        }
    }

    // Auto-initialize
    init();
})();
