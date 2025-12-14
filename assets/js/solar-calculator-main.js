/**
 * Solar Calculator Main Logic
 * Handles all ROI calculations, energy production, and financial modeling
 */
(() => {
    'use strict';

    // ========= 1) CONSTANTS & DEFAULTS =========
    // Calendar
    const DAYS_IN_YR = 365.4;
    const MONTHS_IN_YR = 12.3;
    const HOURS_IN_DAY = 24;

    // Financial - will be set from PHP config
    const CORPORATION_TAX = 0.125;        // 12.5%
    let CURRENCY_SYMBOL = '€';  // Default, will be overridden by config
    let SEAI_GRANT_RATE = 0.30;         // Grant rate from admin settings
    let SEAI_GRANT_CAP = 162000;        // Grant cap from admin settings
    const LOAN_APR_DEFAULT = 0.07;        // 7% APR
    const FEED_IN_TARIFF_DEFAULT = 0.21;  // €/kWh
    const COMPOUND_7_YRS = 1.07;          // coefficient @5% over 7years
    const ANNUAL_INCREASE = 0.05;         // 5% bill increase
    const SOLAR_PANEL_DEGRADATION = 0.005;// 0.5%/yr
    const LENGTH_OF_PAYBACK = 7;          // loan length, years (used when needed)

    // Energy
    const PANEL_POWER_W = 400;            // W per panel (kWp = panels*0.4)
    const YRS_OF_SYSTEM = 25;
    // CO2 coefficient: tonnes CO2 avoided per kWh generated.
    // Typical grid factor ~400 g CO2/kWh => 0.0004 tonnes/kWh
    const CO2_COEFFICIENT_TONNES = 0.0004;  // tonnes per kWh
    const DAY_POWER_AVG = 1.85;           // kWh/day per 400W panel on average in Ireland

    // UI defaults
    const DEFAULT_PANELS = 0;
    const DEFAULT_EXPORT_PERCENT = 0.4;   // 40% of production exported (assumption if no slider)
    const DEFAULT_RETAIL_RATE = 0.35;     // €/kWh – sensible default if blank
    const DEFAULT_FEED_IN_TARIFF = FEED_IN_TARIFF_DEFAULT;
    const DEFAULT_APR = LOAN_APR_DEFAULT;

    // ========= 2) HELPERS =========
    const byId = id => document.getElementById(id);
    const num = v => {
        if (v === null || v === undefined) return 0;
        const n = typeof v === 'number' ? v : parseFloat(String(v).replace(/[^\d.\-]/g, ''));
        return Number.isFinite(n) ? n : 0;
    };
    const clamp = (x, lo, hi) => Math.min(Math.max(x, lo), hi);

    function fmtEuro(x) {
        try { return CURRENCY_SYMBOL + Math.round(x).toLocaleString('en-IE'); }
        catch { return `${CURRENCY_SYMBOL}${Math.round(x).toLocaleString('en-IE')}`; }
    }
    function fmtNum(x, digits = 2) {
        return Number(x || 0).toLocaleString('en-IE', { maximumFractionDigits: digits });
    }

    // parse solar panel configurations from the DOM tables and normalize fields
    const parsedSolarConfigs = [];
    const parseNumber = (s) => {
        if (s === null || s === undefined) return 0;
        const str = String(s).trim();
        if (!str) return 0;
        // Remove non-numeric except dot and minus, but also handle comma as thousands sep
        const cleaned = str.replace(/,/g, '').replace(/[^0-9.\-]/g, '');
        const n = parseFloat(cleaned);
        return Number.isFinite(n) ? n : 0;
    };

    Array.from(document.getElementsByClassName('table-striped')).forEach(table => {
        try {
            const cfg = { panelsCount: 0, yearlyEnergyDcKwh: 0 };
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 2) return;
                const key = cells[0].innerText.trim().toLowerCase();
                const value = cells[1].innerText.trim();
                if (key.includes('panel')) {
                    cfg.panelsCount = parseInt(parseNumber(value)) || cfg.panelsCount;
                } else if (key.includes('energy') || key.includes('annual')) {
                    cfg.yearlyEnergyDcKwh = parseNumber(value) || cfg.yearlyEnergyDcKwh;
                } else if (key.includes('kwh')) {
                    cfg.yearlyEnergyDcKwh = parseNumber(value) || cfg.yearlyEnergyDcKwh;
                } else {
                    // store raw fallback for any other key
                    cfg[key] = value;
                }
            });
            // only push cfgs that have at least a panels count or energy estimate
            if ((cfg.panelsCount && cfg.panelsCount > 0) || (cfg.yearlyEnergyDcKwh && cfg.yearlyEnergyDcKwh > 0)) {
                parsedSolarConfigs.push(cfg);
            }
        } catch (e) {
            // ignore malformed tables
        }
    });

    // expose parsed configs for debugging / fallback
    window.__parsedSolarConfigs = parsedSolarConfigs;

    // ========= 3) COST MODEL =========
    /**
     * Estimate total installed cost for solar system in Ireland.
     * @param {number} panels - number of solar panels (each assumed 400 W)
     * @param {number} batteryKWh - optional battery size in kWh
     * @param {boolean} includeDiverter - whether to include a diverter (€550)
     * @returns {number} total cost (€)
     */
    function estimateSolarCost(panels, batteryKWh = 0, includeDiverter = true) {
        const panelWatt = 400;              // average panel size
        const costPerKwP = 1200;            // €/kWp installed
        const batteryCostPerKWh = 500;      // €/kWh installed
        const diverterCost = 550;           // one-off
        const systemKwP = (panels * panelWatt) / 1000;
        const panelCost = systemKwP * costPerKwP;
        const batteryCost = batteryKWh * batteryCostPerKWh;
        // Only include diverter cost when at least one panel is selected.
        // This prevents a default €550 cost appearing when panels = 0.
        const diverter = includeDiverter && panels > 0 ? diverterCost : 0;
        return panelCost + batteryCost + diverter;
    }

    // ========= 4) CORE CALCULATIONS =========
    function readInputs() {
        // sliders / checkboxes
        const inclGrant = !!(byId('inclGrant')?.checked);
        const inclACA = !!(byId('inclACA')?.checked);
        const inclLoan = !!(byId('inclLoan')?.checked);

        const panelCountEl = byId('panelCount');
        const panels = panelCountEl ? clamp(num(panelCountEl.value), 0, 10000) : DEFAULT_PANELS;

        // key text inputs
        const exportRate = (() => {
            const val = byId('exportRate')?.value;
            const p = num(val) / 100; // assume percent in UI
            return Number.isFinite(p) && p > 0 ? clamp(p, 0, 1) : DEFAULT_EXPORT_PERCENT;
        })();

        const electricityRate = (() => {
            const val = byId('electricityRate')?.value;
            const r = num(val);
            return r > 0 ? r : DEFAULT_RETAIL_RATE;
        })();

        const feedInTariff = FEED_IN_TARIFF_DEFAULT; // can be extended to be editable if needed
        // Allow user-specified loan APR if there's an input (id=loanApr), otherwise fall back to default
        const parsedLoanApr = (() => {
            const v = byId('loanApr')?.value;
            const n = Number.parseFloat(String(v || '').replace(/[^0-9.\-]/g, ''));
            return Number.isFinite(n) && n > 0 ? n : LOAN_APR_DEFAULT;
        })();
        // APR to use for loan calculations (0 if loan not selected)
        const APR = inclLoan ? parsedLoanApr : 0;

        // bill (required to run figs)
        const billMonthly = (() => {
            const val = byId('electricityBill')?.value;
            // Ensure we never treat a negative input as a negative bill - clamp to 0
            const r = Math.max(0, num(val));
            return r;
        })();
        
        // TOGGLED OFF: Download Report button disabled


        // add an energy production estimate based on panels and available solar configs
        let yearlyEnergy = 0;
        // prefer parsed DOM configs, then server-provided `solarConfigs` (may be a top-level const, not on window)
        const availableConfigs = (Array.isArray(window.__parsedSolarConfigs) && window.__parsedSolarConfigs.length > 0)
            ? window.__parsedSolarConfigs
            : (typeof solarConfigs !== 'undefined' && Array.isArray(solarConfigs) && solarConfigs.length > 0 ? solarConfigs : []);
        if (availableConfigs.length > 0) {
            // helper: try to extract a numeric kWh value from a config object safely
            const extractKwh = (cfg) => {
                if (!cfg) return 0;
                // try common keys first
                const candidates = [cfg['yearlyEnergyDcKwh'], cfg['yearlyEnergy'], cfg['Annual Energy Production'], cfg['Annual Energy Production (kWh)'], cfg['Annual Energy Production kWh'], cfg['Annual Energy Production:'], cfg['Annual Energy Production\u00A0']];
                for (const cand of candidates) {
                    if (cand && typeof cand === 'string') {
                        const n = parseFloat(cand.replace(/[^0-9.\-]/g, '').replace(/,/g, ''));
                        if (Number.isFinite(n) && n > 0) return n;
                    }
                    if (Number.isFinite(cand) && cand > 0) return Number(cand);
                }
                // fallback: inspect any string value on the config object and pick first number-like token
                for (const v of Object.values(cfg)) {
                    if (!v) continue;
                    const s = String(v);
                    const m = s.match(/([0-9\.,]+)\s*/);
                    if (m) {
                        const n = parseFloat(m[1].replace(/,/g, ''));
                        if (Number.isFinite(n) && n > 0) return n;
                    }
                }
                return 0;
            };

            // find config matching panel count
            for (let i = 0; i < availableConfigs.length; i++) {
                const config = availableConfigs[i];
                const configPanels = parseInt(config['panelsCount'] || config['panels'] || config['Number of Panels'] || config['Panels']) || 0;
                if (configPanels === panels) {
                    yearlyEnergy = extractKwh(config) || 0;
                    break;
                }
            }
            // if there isn't an exact matching panels, choose the closest lower one
            if (yearlyEnergy === 0) {
                let closestDiff = Infinity;
                for (let i = 0; i < availableConfigs.length; i++) {
                    const config = availableConfigs[i];
                    const configPanels = parseInt(config['panelsCount'] || config['panels'] || config['Number of Panels'] || config['Panels']) || 0;
                    const diff = panels - configPanels;
                    if (diff >= 0 && diff < closestDiff) {
                        closestDiff = diff;
                        yearlyEnergy = extractKwh(config) || 0;
                    }
                }
            }
        }

        return { inclGrant, inclACA, inclLoan, panels, exportRate, electricityRate, feedInTariff, APR, billMonthly, yearlyEnergy };
    }

    function keyFigures(state) {
        const { inclGrant, inclACA, inclLoan, panels, exportRate, electricityRate: RETAIL, feedInTariff: FIT, APR, billMonthly, yearlyEnergy } = state;
        // Derived system constants
        const kWp = (panels * PANEL_POWER_W) / 1000;
        const baseCost = Math.round(estimateSolarCost(state.panels)); // €
        console.log('baseCost', baseCost);

        const seaiGrant = state.inclGrant ? Math.min(Number(baseCost * SEAI_GRANT_RATE), SEAI_GRANT_CAP) : 0;
        console.log('seaiGrant', seaiGrant);
        const acaAllowance = state.inclACA ? Math.min(Number(baseCost - seaiGrant), Number(baseCost) * CORPORATION_TAX ) : 0;
        console.log('acaAllowance', acaAllowance);

        // interest multiplier used when including loan-related uplift (simple proxy)
        const interest = Math.min(Number(APR ? APR * LENGTH_OF_PAYBACK : 0) || 0, 0.5); // cap at 50%
        console.log('interest', interest);
        
        // Install costs
        // convert computed floats to rounded integers for display/consistency
        const install_cost = Math.round(Number(baseCost));
        console.log('install_cost', install_cost);

        const net_install_cost = Math.round(Number(baseCost - seaiGrant + (inclLoan ? (baseCost - seaiGrant) * interest : 0)));
        console.log('net_install_cost', net_install_cost);

        // Production
        const yearlyEnergyKWhYr0 = yearlyEnergy; // year 0 nominal from config
        const yearlyEnergyKWh = yearlyEnergyKWhYr0; // displayed headline (yr0  value)

        // Loan modelling (monthly amortisation uses the loan term, not the 25-year system life)
        const m = 12, n = LENGTH_OF_PAYBACK * m;
        const principal = Math.max(0, baseCost - seaiGrant);
        const r = APR / m;
        const monthlyRepay = Math.round(inclLoan ? principal * (r / (1 - Math.pow(1 + r, -n))) : 0);
        const yearlyLoanCost = Math.round(inclLoan ? monthlyRepay * 12 : 0);

        // Total 25-year savings (benefits - cost + ACA if included)
        const benefits25 = Array.from({ length: YRS_OF_SYSTEM }, (_, y) => {
            const pvYear = panels * DAY_POWER_AVG * DAYS_IN_YR * Math.pow(1 - SOLAR_PANEL_DEGRADATION, y);
            const self = pvYear * (1 - exportRate);
            const exp = pvYear * exportRate;
            const retailY = RETAIL * Math.pow(1 + ANNUAL_INCREASE, y);
            const fitY = FIT; // could escalate, left constant per provided spec
            return self * retailY + exp * fitY;
        }).reduce((a, b) => a + b, 0);

        // Total loan payments over the evaluation window: stop counting repayments after the loan term
        const loanYearsCount = Math.min(YRS_OF_SYSTEM, LENGTH_OF_PAYBACK);
        const loanCost25 = inclLoan ? (monthlyRepay * 12 * loanYearsCount) : principal; // total paid on loan within evaluation window
        const total_yr_savings = benefits25 - loanCost25 + (inclACA ? acaAllowance : 0);

        // First-year annual saving (calculate this BEFORE payback so we can use it)
        const savings_year0 = (() => {
            // Current electricity usage based on their bill
            const current_usage_kwh = (billMonthly * 12) / RETAIL;
            
            // Total solar production
            const annual_solar_kwh = panels * DAY_POWER_AVG * DAYS_IN_YR;
            
            // Self-consumption: what they can use themselves (capped by their actual usage)
            const max_self_consumption_kwh = annual_solar_kwh * (1 - exportRate);
            const actual_self_consumption_kwh = Math.min(max_self_consumption_kwh, current_usage_kwh);
            
            // Export: everything not self-consumed
            const export_kwh = annual_solar_kwh - actual_self_consumption_kwh;
            
            // Financial benefits
            const bill_savings = actual_self_consumption_kwh * RETAIL;  // Avoided electricity cost
            const export_income = export_kwh * FIT;                     // Export income
            const loan_cost = inclLoan ? yearlyLoanCost : 0;
            const acaBump = (inclACA ? acaAllowance : 0);
            
            return bill_savings + export_income - loan_cost + acaBump;
        })();

        // Monthly charge (Year 0) - based on actual savings
        const monthly_charge = (savings_year0 / 12) - billMonthly;

        // Payback period (years) - uses actual net annual savings
        const payback_period = (() => {
            // Investment amount to pay back
            const investment = inclLoan ? loanCost25 : net_install_cost;
            
            // Net annual savings (year 0) - this is what actually goes back into your pocket
            const annualSavings = savings_year0;
            
            // Payback = Investment / Annual Savings
            return annualSavings > 0 ? investment / annualSavings : 0;
        })();

        // ROI over 25 years (%)
        const ROI_25Y = (() => {
            const cost = inclLoan ? (monthlyRepay * 12 * loanYearsCount) : principal;
            return cost > 0 ? ((benefits25 - cost) / cost) * 100 : 0;
        })();

        // CO2 reduction over life
        const co2_reduction = CO2_COEFFICIENT_TONNES *
            Array.from({ length: YRS_OF_SYSTEM }, (_, y) =>
                panels * DAY_POWER_AVG * Math.pow(1 - SOLAR_PANEL_DEGRADATION, y) * DAYS_IN_YR
            ).reduce((a, b) => a + b, 0);

        return {
            baseCost, seaiGrant, acaAllowance,
            install_cost, net_install_cost,
            yearlyEnergyKWh, monthly_charge, total_yr_savings,
            payback_period, ROI_25Y, savings_year0, co2_reduction
        };
    }

    // ========= 5) GUI UPDATES =========
    function updateResults(state, figs) {
        const setTxt = (id, txt) => {
            document.querySelectorAll('#' + id).forEach(el => {
                if (!el) return;
                // Do not overwrite form inputs (slider). Only update visible text elements.
                if (el.tagName && el.tagName.toUpperCase() === 'INPUT') return;
                el.textContent = txt;
            });
        };
        // #results
        setTxt('installationCost', fmtEuro(figs.install_cost));
        setTxt('grant', fmtEuro(figs.seaiGrant));
        setTxt('panelCount', fmtNum(state.panels, 0));
        setTxt('yearlyEnergy', fmtNum(figs.yearlyEnergyKWh, 0) + ' kWh');
        setTxt('monthlyBill', fmtEuro(state.billMonthly));
        setTxt('annualIncrease', (ANNUAL_INCREASE * 100).toFixed(1) + '%');
        // if the income is negative, show in red (format/sign is handled here)
        if (figs.monthly_charge < 0) {
            setTxt('netIncome', '-' + fmtEuro(Math.abs(figs.monthly_charge)));
            const el = byId('netIncome');
            if (el) el.style.color = 'red';
        } else {
            setTxt('netIncome', '+' +  fmtEuro(figs.monthly_charge));
            const el = byId('netIncome');
            if (el) el.style.color = 'black';
        }
        setTxt('exportRate', (state.exportRate * 100).toFixed(0) + '%');
        setTxt('electricityRate', fmtEuro(state.electricityRate));
    }

    function updateInstallationDetails(state, figs) {
        const setTxt = (id, txt) => {
            document.querySelectorAll('#' + id).forEach(el => {
                if (!el) return;
                if (el.tagName && el.tagName.toUpperCase() === 'INPUT') return;
                el.textContent = txt;
            });
        };
        // #installation-details
        setTxt('netCost', fmtEuro(figs.net_install_cost));
        setTxt('totalSavings', fmtEuro(figs.total_yr_savings));
        setTxt('roi', fmtNum(figs.ROI_25Y, 1) + '%');
        setTxt('panelCount', fmtNum(state.panels, 0));

        // show one decimal for CO₂ reductions for readability
        setTxt('co2Reduction', fmtNum(figs.co2_reduction, 1) + ' t');
        setTxt('annualSavings', fmtEuro(figs.savings_year0));
        setTxt('paybackPeriod', fmtNum(figs.payback_period, 2) + ' years');
    }

    // Minimal canvas updates so the IDs react without external libs
    function updateBreakEvenChart(state, figs) {
        console.log('updateBreakEvenChart called', { 
            hasChart: !!window.breakEvenChart,
            hasChartData: !!(window.breakEvenChart && window.breakEvenChart.data),
            hasFunc: typeof window.calculateBreakEvenDataSimple === 'function',
            billMonthly: state.billMonthly,
            panels: state.panels 
        });
        
        // Prefer updating Chart.js instance when available (keeps rendering consistent)
        if (window.breakEvenChart && window.breakEvenChart.data && typeof window.calculateBreakEvenDataSimple === 'function') {
            try {
                // On page load with no bill, show zero baseline
                if (!state.billMonthly || state.billMonthly === 0) {
                    console.log('Setting chart to zero baseline');
                    window.breakEvenChart.data.datasets[0].data = Array(25).fill(0);
                    window.breakEvenChart.data.datasets[0].label = '0 Panels';
                    window.breakEvenChart.update('none');
                    return;
                }
                
                const cfg = { panelsCount: state.panels, yearlyEnergyDcKwh: figs.yearlyEnergyKWh || (state.panels * DAY_POWER_AVG * DAYS_IN_YR) };
                console.log('Calling calculateBreakEvenDataSimple with:', cfg);
                const be = window.calculateBreakEvenDataSimple(cfg);
                console.log('Break even data:', be);
                
                if (be && be.savings && window.breakEvenChart.data && window.breakEvenChart.data.datasets && window.breakEvenChart.data.datasets[0]) {
                    console.log('Updating chart with', be.savings.length, 'data points');
                    window.breakEvenChart.data.datasets[0].data = [...be.savings];
                    window.breakEvenChart.data.datasets[0].label = `${state.panels} Panels`;
                    // Force chart to recalculate scales and redraw
                    window.breakEvenChart.options.scales.y.min = undefined;
                    window.breakEvenChart.options.scales.y.max = undefined;
                    window.breakEvenChart.update('active');
                    console.log('Chart updated successfully');
                    return;
                } else {
                    console.warn('Missing chart data structure:', { 
                        hasBe: !!be, 
                        hasSavings: be?.savings, 
                        hasChartData: !!window.breakEvenChart.data 
                    });
                }
            } catch (e) {
                console.error('Chart update error:', e);
                // fall back to canvas drawing below
            }
        } else {
            console.warn('Chart not available:', {
                hasChart: !!window.breakEvenChart,
                hasFunc: typeof window.calculateBreakEvenDataSimple
            });
        }

        const c = byId('breakEvenChart');
        if (!c || !c.getContext) return;
        const ctx = c.getContext('2d');
        const W = c.width, H = c.height;
        ctx.clearRect(0, 0, W, H);
        // Simple two-bar visual: Cost vs 25y Benefits
        const cost = Math.max(1, figs.net_install_cost);
        const benefit = Math.max(1, figs.total_yr_savings + figs.net_install_cost);
        const maxV = Math.max(cost, benefit);
        const barW = W * 0.3, gap = W * 0.1;
        const scale = (v) => (v / maxV) * (H * 0.8);
        ctx.fillRect(W * 0.15, H - scale(cost), barW, scale(cost));
        ctx.fillRect(W * 0.55, H - scale(benefit), barW, scale(benefit));
    }

    function updateEnergyChart(state, figs) {
        // If Chart.js instance exists, update its dataset instead of raw canvas drawing
        if (window.energyChart) {
            try {
                const years = YRS_OF_SYSTEM;
                const degradation = SOLAR_PANEL_DEGRADATION;
                const data = Array.from({ length: years }, (_, y) => {
                    return state.panels * DAY_POWER_AVG * DAYS_IN_YR * Math.pow(1 - degradation, y);
                });
                window.energyChart.data.datasets[0].data = data;
                window.energyChart.data.datasets[0].label = `${state.panels} panels — Annual Production (kWh)`;
                window.energyChart.update();
                return;
            } catch (e) {
                // fall back to canvas drawing
            }
        }

        const c = byId('energyChart');
        if (!c || !c.getContext) return;
        const ctx = c.getContext('2d');
        const W = c.width, H = c.height;
        ctx.clearRect(0, 0, W, H);
        // Plot annual production decline by degradation
        const years = YRS_OF_SYSTEM;
        const values = Array.from({ length: years }, (_, y) => state.panels * DAY_POWER_AVG * DAYS_IN_YR * Math.pow(1 - SOLAR_PANEL_DEGRADATION, y));
        const maxV = Math.max(...values);
        const xstep = W / (years + 1);
        ctx.beginPath();
        values.forEach((v, i) => {
            const x = xstep * (i + 1);
            const y = H - (v / maxV) * (H * 0.85);
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        });
        ctx.stroke();
    }

    function updateSolarInvestmentAnalysis(state, figs) {
        const setTxt = (id, txt) => {
            document.querySelectorAll('#' + id).forEach(el => {
                if (!el) return;
                if (el.tagName && el.tagName.toUpperCase() === 'INPUT') return;
                el.textContent = txt;
            });
        };
        setTxt('panelCountValue', fmtNum(state.panels, 0));
        setTxt('installCost', fmtEuro(figs.baseCost));
    }

    // ========= 6) MASTER =========
    let modalPopupTimer = null;
    let modalShown = false; // Track if modal has been shown
    
    // Use window.formSubmittedSuccessfully so it can be updated by modal handler
    if (typeof window.formSubmittedSuccessfully === 'undefined') {
        window.formSubmittedSuccessfully = false;
    }
    
    // Track user interaction - set by event listeners only
    window.ksradUserInteracted = false;

    function calculateROI() {
        console.log('[calculateROI] Function called');
        const state = readInputs();
        console.log('[calculateROI] State:', state);
        
        // Modal popup timer - only trigger ONCE after user interaction
        // Check window.ksradUserInteracted which is set by event listeners
        if (!window.formSubmittedSuccessfully && window.ksradUserInteracted && !modalShown) {
            // Clear any existing timer
            if (modalPopupTimer) {
                clearTimeout(modalPopupTimer);
            }
            
            // Schedule modal to appear after 3 seconds (only once)
            modalPopupTimer = setTimeout(() => {
                if (!window.formSubmittedSuccessfully && !modalShown && typeof window.showModal === 'function') {
                    console.log('Opening modal popup after 3 seconds of interaction');
                    window.showModal();
                    modalShown = true; // Prevent modal from showing again
                }
            }, 3000);
        }
        
        // Guard: require monthly bill to compute robustly
        if (!state.billMonthly) {
            // still update zero-ish defaults so UI doesn't look stale
            const zeroFigs = keyFigures({ ...state, billMonthly: 0 });
            updateResults(state, zeroFigs);
            updateInstallationDetails(state, zeroFigs);
            // Only update charts if they exist
            if (window.breakEvenChart) updateBreakEvenChart(state, zeroFigs);
            if (window.energyChart) updateEnergyChart(state, zeroFigs);
            updateSolarInvestmentAnalysis(state, zeroFigs);
            return;
        }
        const figs = keyFigures(state);
        updateResults(state, figs);
        updateInstallationDetails(state, figs);
        // Only update charts if they exist
        if (window.breakEvenChart) updateBreakEvenChart(state, figs);
        if (window.energyChart) updateEnergyChart(state, figs);
        updateSolarInvestmentAnalysis(state, figs);
    }

    // ========= 7) EVENT WIRING =========
    function wireEvents() {
        console.log('[wireEvents] Attaching event listeners');
        const onChangeRecalc = (id, ev = 'change') => {
            const el = byId(id);
            if (el) {
                console.log('[wireEvents] Attached', ev, 'listener to', id);
                el.addEventListener(ev, () => {
                    console.log('[Event]', id, ev, 'fired');
                    // Mark that user has interacted
                    window.ksradUserInteracted = true;
                    calculateROI();
                });
            } else {
                console.warn('[wireEvents] Element not found:', id);
            }
        };
        onChangeRecalc('inclGrant', 'change');
        onChangeRecalc('inclACA', 'change');
        onChangeRecalc('inclLoan', 'change');
        onChangeRecalc('panelCount', 'input');

        // keyup handlers for text inputs
        onChangeRecalc('exportRate', 'keyup');
        onChangeRecalc('electricityRate', 'keyup');

        const btn = byId('openRoiModalButton');
        if (btn) {
            btn.addEventListener('click', () => {
                window.ksradUserInteracted = true;
                calculateROI();
            });
        }

        // Also recalc on electricityBill keyup so users see instant feedback
        onChangeRecalc('electricityBill', 'keyup');
    }

    // ========= 8) INITIALISE =========
    function initialPopulate() {
        // Explicitly set all ROI values to zero on page load
        const setZero = (id, txt) => {
            const el = byId(id);
            if (el && el.tagName !== 'INPUT') el.textContent = txt;
        };
        setZero('netIncome', CURRENCY_SYMBOL + '0');
        setZero('netCost', CURRENCY_SYMBOL + '0');
        setZero('paybackPeriod', '0 yrs');
        setZero('annualSavings', CURRENCY_SYMBOL + '0');
        setZero('totalSavings', CURRENCY_SYMBOL + '0');
        setZero('roi', '0%');
        setZero('co2Reduction', '0');
        
        // Set installation details to zero
        setZero('installationCost', '0');
        setZero('installCost', '0');
        setZero('grant', '0');
        
        // Initialize charts with zero data if they exist and are fully initialized
        if (window.breakEvenChart && window.breakEvenChart.data && window.breakEvenChart.data.datasets && window.breakEvenChart.data.datasets[0]) {
            window.breakEvenChart.data.datasets[0].data = Array(25).fill(0);
            window.breakEvenChart.data.datasets[0].label = '0 Panels';
            window.breakEvenChart.update('none');
        }
    }

    // ========= 9) INITIALIZATION =========
    function init() {
        console.log('[INIT] solar-calculator-main.js initializing...');
        console.log('[INIT] document.readyState:', document.readyState);
        // Apply configuration from PHP if available
        if (window.KSRAD_CalcConfig) {
            console.log('[INIT] KSRAD_CalcConfig found:', window.KSRAD_CalcConfig);
            CURRENCY_SYMBOL = window.KSRAD_CalcConfig.currencySymbol || CURRENCY_SYMBOL;
            SEAI_GRANT_RATE = window.KSRAD_CalcConfig.seaiGrantRate || SEAI_GRANT_RATE;
            SEAI_GRANT_CAP = window.KSRAD_CalcConfig.seaiGrantCap || SEAI_GRANT_CAP;
            
            // Also expose globally for consistency with other scripts
            window.CURRENCY_SYMBOL = CURRENCY_SYMBOL;
        } else {
            console.warn('[INIT] KSRAD_CalcConfig not found');
        }
        
        console.log('[INIT] Calling wireEvents...');
        wireEvents();
        console.log('[INIT] Calling initialPopulate...');
        initialPopulate();
        
        // Ensure values stay at zero after a short delay (to override any other initialization)
        setTimeout(() => {
            initialPopulate();
        }, 200);
        
        // Additional check after longer delay to handle cached page loads
        setTimeout(() => {
            initialPopulate();
        }, 500);
    }

    // Expose functions globally for external access (before initialization)
    window.calculateROI = calculateROI;
    window.KSRAD_initCalculator = init; // Expose init for AJAX re-initialization
    console.log('[SCRIPT LOAD] solar-calculator-main.js loaded, readyState:', document.readyState);
    
    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        console.log('[SCRIPT LOAD] readyState is loading, waiting for DOMContentLoaded');
        document.addEventListener('DOMContentLoaded', init);
    } else {
        console.log('[SCRIPT LOAD] readyState is', document.readyState, ', initializing immediately');
        // DOM already loaded - initialize immediately
        init();
    }
    
    // Note: window.formSubmittedSuccessfully is already initialized at the top of the file
    // and used directly throughout, so no need to re-assign here
})();
