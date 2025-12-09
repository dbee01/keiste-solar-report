/**
 * Google Maps Integration
 * Handles map initialization, autocomplete, and location selection
 */

// Global configuration (set by PHP)
window.KSRAD_MapsConfig = window.KSRAD_MapsConfig || {};

async function initMaps() {
    // Get config from global object set by PHP
    const config = window.KSRAD_MapsConfig;
    const KEY = config.apiKey;
    
    // Check if API key exists
    if (!KEY || KEY === '') {
        console.error("[boot] No Google Maps API key configured. Please add your API key in the WordPress admin settings.");
        const pacContainer = document.getElementById("pacContainer");
        if (pacContainer) {
            pacContainer.innerHTML = '<div style="padding: 1rem; background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; margin: 1rem 0;"><strong>⚠️ Configuration Required:</strong> Google Maps API key is not configured. Please add your API key in the plugin settings.</div>';
        }
        return;
    }
    
    const BUSINESS_NAME = config.businessName || 'Location';
    const DEFAULT_CENTER = {
        lat: Number.isFinite(config.lat) ? config.lat : 51.886656,
        lng: Number.isFinite(config.lng) ? config.lng : -8.535580
    };
    
    // Removed API key logging for security
    console.log('Map will center at:', DEFAULT_CENTER);
    
    // Country restriction - check user selection first, then dashboard settings
    window.COUNTRY_SETTING = config.country || 'Rep. of Ireland';
    const userCountrySelect = document.getElementById('userCountry');
    if (userCountrySelect && userCountrySelect.value) {
        const countryNameMap = {
            'USA': 'United States',
            'Canada': 'Canada',
            'UK': 'UK',
            'Ireland': 'Rep. of Ireland'
        };
        window.COUNTRY_SETTING = countryNameMap[userCountrySelect.value] || window.COUNTRY_SETTING;
    }
    
    // Building type - capture from dropdown for later use
    window.BUILDING_TYPE = '';
    const userBuildingTypeSelect = document.getElementById('userBuildingType');
    if (userBuildingTypeSelect && userBuildingTypeSelect.value) {
        window.BUILDING_TYPE = userBuildingTypeSelect.value;
    }
    
    const COUNTRY_CODE_MAP = {
        'Rep. of Ireland': 'ie',
        'UK': 'gb',
        'United States': 'us',
        'Canada': 'ca'
    };
    let REGION_CODE = COUNTRY_CODE_MAP[window.COUNTRY_SETTING] || 'us';

    const mapEl = document.getElementById("map");
    const pacMount = document.getElementById("pacContainer");
    if (!mapEl || !pacMount) {
        console.error("[boot] Missing required elements (#map or #pacContainer).");
        return;
    }

    // Load Maps JS if needed
    async function ensureMapsLoaded() {
        if (window.google?.maps?.importLibrary) return true;

        const already = [...document.getElementsByTagName("script")].some(s =>
            s.src && s.src.includes("maps.googleapis.com/maps/api/js")
        );
        
        if (!already) {
            const s = document.createElement("script");
            s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(KEY)}&v=weekly`;
            s.async = true;
            s.defer = true;
            document.head.appendChild(s);
        }

        const start = Date.now();
        while (!window.google?.maps?.importLibrary) {
            if (Date.now() - start > 15000) {
                console.error("[boot] Maps JS never ready (check key, network, CSP/referrer).");
                return false;
            }
            await new Promise(r => setTimeout(r, 50));
        }
        return true;
    }

    if (!(await ensureMapsLoaded())) return;

    // Import modern libraries
    let GoogleMap;
    try {
        const mapsLib = await google.maps.importLibrary("maps");
        GoogleMap = mapsLib.Map;
        await google.maps.importLibrary("places");
    } catch (e) {
        console.error("[boot] Error importing libraries:", e);
        return;
    }

    // Create map
    const map = new google.maps.Map(mapEl, {
        center: DEFAULT_CENTER,
        zoom: 18,
        mapTypeId: "satellite",
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false
    });

    window.map = map;
    window.marker = null;

    // Place Autocomplete Element
    const pac = document.getElementById('pac');
    if (!pac) {
        console.error('[places] Could not find place autocomplete element');
        return;
    }
    
    if (pacMount) {
        pacMount.style.display = 'block';
    }

    const inputEl = pac.querySelector("#pacInput");
    const geocoder = new google.maps.Geocoder();

    pac.includedRegionCodes = [REGION_CODE];
    
    // Update region restriction when user changes country
    if (userCountrySelect) {
        userCountrySelect.addEventListener('change', function() {
            const countryNameMap = {
                'USA': 'United States',
                'Canada': 'Canada',
                'UK': 'UK',
                'Ireland': 'Rep. of Ireland'
            };
            window.COUNTRY_SETTING = countryNameMap[this.value] || window.COUNTRY_SETTING;
            REGION_CODE = COUNTRY_CODE_MAP[window.COUNTRY_SETTING] || 'us';
            pac.includedRegionCodes = [REGION_CODE];
            console.log('Country changed to:', window.COUNTRY_SETTING, 'Region code:', REGION_CODE);
            
            // Update currency symbol based on country
            const currencyMap = {
                'United States': '$',
                'Canada': '$',
                'UK': '£',
                'Rep. of Ireland': '€'
            };
            
            // Update all instances of CURRENCY_SYMBOL across different script contexts
            window.CURRENCY_SYMBOL = currencyMap[window.COUNTRY_SETTING] || '€';
            
            // Also update in KSRAD_CalcConfig if it exists
            if (window.KSRAD_CalcConfig) {
                window.KSRAD_CalcConfig.currencySymbol = window.CURRENCY_SYMBOL;
            }
            
            // Update currency symbols in DOM
            document.querySelectorAll('.currency-symbol').forEach(el => {
                el.textContent = window.CURRENCY_SYMBOL;
            });
            
            console.log('Currency updated to:', window.CURRENCY_SYMBOL);
            
            // Force recalculation with new country settings (only if form elements exist)
            if (typeof window.calculateROI === 'function' && document.getElementById('panelCount')) {
                console.log('Recalculating with new country settings...');
                window.calculateROI();
            }
        });
    }
    
    // Update building type when user changes selection
    if (userBuildingTypeSelect) {
        userBuildingTypeSelect.addEventListener('change', function() {
            window.BUILDING_TYPE = this.value;
            console.log('Building type changed to:', window.BUILDING_TYPE);
            
            // Recalculate with new building type settings (only if form elements exist)
            if (typeof window.calculateROI === 'function' && document.getElementById('panelCount')) {
                window.calculateROI();
            }
        });
    }

    // Add event listener for place selection
    pac.addEventListener('gmp-select', async ({ placePrediction }) => {
        const place = placePrediction.toPlace();
        
        await place.fetchFields({
            fields: ['displayName', 'formattedAddress', 'location', 'viewport']
        });

        if (place.viewport) {
            map.fitBounds(place.viewport);
        } else if (place.location) {
            map.setCenter(place.location);
            map.setZoom(17);
        }

        if (place.location) {
            applyCoords(
                {
                    lat: place.location.lat(),
                    lng: place.location.lng()
                },
                place.formattedAddress || place.displayName
            );
        } else {
            console.error('[places] No location data in place result');
        }
    });

    // Helper functions
    const toNums = (loc) => {
        if (!loc) return null;
        const lat = typeof loc.lat === "function" ? loc.lat() : loc.lat;
        const lng = typeof loc.lng === "function" ? loc.lng() : loc.lng;
        return (Number.isFinite(lat) && Number.isFinite(lng)) ? { lat, lng } : null;
    };
    
    function applyCoords(coords, label) {
        if (!coords || typeof coords.lat !== "number" || typeof coords.lng !== "number") {
            console.error("[location] Invalid coordinates:", coords);
            return;
        }

        // Save user's selected country and building type BEFORE AJAX call
        const savedCountry = window.COUNTRY_SETTING;
        const savedBuildingType = window.BUILDING_TYPE;
        console.log('[ajax] Saving user selections before AJAX:', savedCountry, savedBuildingType);

        const ll = new google.maps.LatLng(coords.lat, coords.lng);
        if (typeof map !== 'undefined' && map && typeof map.panTo === 'function') {
            map.panTo(ll);
            map.setZoom(18); // Ensure proper zoom level
        }

        const loader = document.getElementById("ajaxLoader");
        if (loader) loader.style.display = "flex";

        const params = new URLSearchParams({
            lat: String(coords.lat),
            lng: String(coords.lng),
            business_name: label || "Location",
        });

        // Update URL in browser without reload (preserves map state)
        const newUrl = `/keiste-solar-report/?${params.toString()}`;
        window.history.pushState({ lat: coords.lat, lng: coords.lng, label: label }, '', newUrl);
        
        // Fetch solar data via AJAX instead of page reload
        fetch(`${window.location.pathname}?${params.toString()}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.text();
        })
        .then(html => {
            // Parse the HTML response
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newContainer = doc.querySelector('.newContainer');
            
            if (newContainer) {
                const oldContainer = document.querySelector('.newContainer');
                if (oldContainer) {
                    oldContainer.innerHTML = newContainer.innerHTML;
                    
                    // Re-initialize charts after content is loaded
                    window.ksradChartsInitialized = false;
                    if (typeof Chart !== 'undefined') {
                        // Find and call the initialization function from chart-initialization.js
                        const initScript = document.createElement('script');
                        initScript.textContent = 'if (window.ksradChartsInitialized === false) { ' +
                            'setTimeout(() => { ' +
                            'if (document.getElementById("breakEvenChart")) { ' +
                            'const event = new Event("ksrad-reinit-charts"); ' +
                            'document.dispatchEvent(event); ' +
                            '}}, 100); }';
                        document.body.appendChild(initScript);
                        document.body.removeChild(initScript);
                    }
                    
                    // RESTORE user's selected country and building type FIRST (before ANY calculator init)
                    if (savedCountry) {
                        window.COUNTRY_SETTING = savedCountry;
                        console.log('[ajax] Restored COUNTRY_SETTING to:', savedCountry);
                    }
                    if (savedBuildingType) {
                        window.BUILDING_TYPE = savedBuildingType;
                        console.log('[ajax] Restored BUILDING_TYPE to:', savedBuildingType);
                    }
                    
                    // Update currency and settings based on RESTORED country selection (BEFORE ANY calculator init)
                    const currencyMap = {
                        'United States': '$',
                        'Canada': '$',
                        'UK': '£',
                        'Rep. of Ireland': '€'
                    };
                    if (window.COUNTRY_SETTING) {
                        window.CURRENCY_SYMBOL = currencyMap[window.COUNTRY_SETTING] || '€';
                        if (window.KSRAD_CalcConfig) {
                            window.KSRAD_CalcConfig.currencySymbol = window.CURRENCY_SYMBOL;
                        }
                        console.log('[ajax] Updated CURRENCY_SYMBOL to:', window.CURRENCY_SYMBOL);
                    }
                    
                    // NOW re-run initialization scripts with correct currency
                    if (window.KSRAD_initializeCalculator) {
                        console.log('[ajax] Running KSRAD_initializeCalculator with currency:', window.CURRENCY_SYMBOL);
                        window.KSRAD_initializeCalculator();
                    }
                    
                    // Re-initialize calculator event handlers
                    if (typeof window.KSRAD_initCalculator === 'function') {
                        console.log('[ajax] Re-initializing calculator with currency:', window.CURRENCY_SYMBOL);
                        window.KSRAD_initCalculator();
                    }
                    
                    // Re-initialize modal handlers
                    if (typeof window.initModalHandlers === 'function') {
                        window.initModalHandlers();
                    }
                    
                    // Update currency symbols in DOM after a brief delay to ensure all elements are rendered
                    setTimeout(() => {
                        const symbols = document.querySelectorAll('.currency-symbol');
                        console.log('[ajax] Found', symbols.length, 'currency symbols to update');
                        console.log('[ajax] COUNTRY_SETTING:', window.COUNTRY_SETTING, 'New currency:', window.CURRENCY_SYMBOL);
                        symbols.forEach((el, idx) => {
                            const oldValue = el.textContent;
                            el.textContent = window.CURRENCY_SYMBOL;
                            console.log(`[ajax] Symbol ${idx}: "${oldValue}" → "${el.textContent}" (ID: ${el.id || 'none'})`);
                        });
                        console.log('[ajax] Currency update complete');
                    }, 250);
                    
                    // Recalculate with user's selected country/building type
                    if (typeof window.calculateROI === 'function') {
                        console.log('[ajax] Recalculating with selected settings...');
                        setTimeout(() => window.calculateROI(), 200);
                    }
                } else {
                    console.error('[ajax] Could not find .newContainer in current page');
                }
            } else {
                console.error('[ajax] Could not find .newContainer in response');
            }
            
            if (loader) loader.style.display = "none";
        })
        .catch(error => {
            console.error('[ajax] Error fetching solar data:', error);
            if (loader) loader.style.display = "none";
            // Fallback to page reload on error
            window.location.reload();
        });
    }

    async function geocodeText(text) {
        if (!text) return null;
        try {
            const { results } = await geocoder.geocode({ address: text });
            const loc = results?.[0]?.geometry?.location;
            const nums = toNums(loc);
            return nums ? { coords: nums, label: results?.[0]?.formatted_address || text } : null;
        } catch (e) {
            return null;
        }
    }

    mapEl.style.display = 'block';
    google.maps.event.trigger(map, 'resize');

    // User pressed Enter without selecting
    inputEl.addEventListener("keydown", async (ev) => {
        if (ev.key !== "Enter") return;
        ev.preventDefault();
        const text = inputEl.value?.trim();
        if (!text) return;
        const resolved = await geocodeText(text);
        if (resolved) applyCoords(resolved.coords, resolved.label);
    });
}

// Update currency symbols on page load based on current country setting
function updateCurrencySymbols() {
    const currencyMap = {
        'United States': '$',
        'Canada': '$',
        'UK': '£',
        'Rep. of Ireland': '€'
    };
    
    if (window.COUNTRY_SETTING) {
        window.CURRENCY_SYMBOL = currencyMap[window.COUNTRY_SETTING] || '€';
        if (window.KSRAD_CalcConfig) {
            window.KSRAD_CalcConfig.currencySymbol = window.CURRENCY_SYMBOL;
        }
        // Update all currency symbols in the DOM
        document.querySelectorAll('.currency-symbol').forEach(el => {
            el.textContent = window.CURRENCY_SYMBOL;
        });
    }
}

// Expose and run initializer
window.initMaps = initMaps;

// Auto-initialize if DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initMaps();
        updateCurrencySymbols();
    });
} else {
    initMaps();
    updateCurrencySymbols();
}
