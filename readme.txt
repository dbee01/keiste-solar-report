=== Keiste Solar Report ===
Contributors: dbee78
Tags: solar, solar panels, roi calculator, google solar api, energy analysis
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A comprehensive solar analysis tool for residential, commercial, community, business and farm buildings capable of handling solar panels on their roof. For the countries of the United States, Canada, the UK and the Republic of Ireland. Calculations include Return on investment, CO2 saved and solar project Breakeven time. Plugin includes a Google Solar API integration, interactive charts, and PDF report generation (premium only), a lead generation form (premium) and tons more features.

== Description ==

The Keiste Solar Report is a powerful WordPress plugin that provides comprehensive solar project analysis for US, Canadian, UK and Irish residential, commercial, community, business and farm buildings capable of handling solar panels on their roof. Using Google's Solar API, it delivers accurate solar potential assessments, financial projections, and detailed ROI calculations. The visitor simply enters a building address, their monthly electricity bill amount and their preferred System Size (ie. no of solar panels). They receive a full financial and technical breakdown along with a 10-16 page bespoke-designed personalized report download (premium only).

= Key Features (Free) =

* **Google Solar API Integration** - Real-time solar potential data for any address
* **Interactive Address Search** - Google Maps autocomplete for easy location selection
* **Financial Analysis** - Detailed ROI calculations with customizable parameters
* **Grant Integration** - Automatic grant calculations based on government schemes
* **Interactive Charts** - Visual break-even analysis and energy production projections
* **Mobile Responsive** - Fully optimized for all devices
* **Admin Settings Panel** - Easy configuration of API keys and default values

= Premium Features (Upgrade $49.99 / month) =

* **Lead Generation Form** - Collect contact details before showing results
* **GA4 Analytics Tracking** - Track form submissions as conversion events in Google Analytics 4
* **Social Media Share Buttons** - Enable social sharing to increase reach
* **Choose Your Country or Area** - Choose your individual country or area boundary with your own grant settings
* **Remove Branding** - White-label the plugin for your business
* **Remove Links** - Hide all Keiste.com attribution links
* **Email Support** - Support over email 

= Enterprise Features =

* **PDF Report Generation** - Bespoke 10-12 page personalized, downloadable reports for clients
* **Multiple Branch Support** - Do you have multiple branches? We will support you to cover different branches
* **Choose Multiple Boundaries** - Choose multiple boundaries to cover different areas of the map.
* **Lead Verification** - Verify each telephone number and email address to stop fake contact details
* **Personal Support** - Personal support requests

Special deals for multiple websites. Contact us at keiste.com

= Use Cases =

* Solar installation companies offering free assessments
* Energy consultants providing solar feasibility studies
* Property developers evaluating solar potential
* Homeowners researching solar panel investments
* Educational institutions teaching renewable energy

= Demo =

Check out our Keiste Solar Report demo at [Keiste.com](https://keiste.com/keiste-solar-report/)

= How It Works =

1. Users enter an address using Google Maps autocomplete
2. Plugin fetches solar data from Google Solar API
3. System calculates ROI based on user's electricity costs and financing options
4. Interactive charts display financial projections over 25 years
5. Results are displayed instantly (PDF reports and lead capture available in premium version)

= Requirements =

* Google Solar API key (obtain from Google Cloud Console)
* Active WordPress installation (5.8 or higher)
* PHP 7.4 or higher

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/keiste-solar-report/` or install via WordPress plugin installer
2. Activate the plugin through the 'Plugins' menu in WordPress
3. **IMPORTANT: Get your Google Solar API key** (see Configuration below). Enable Google Places API, Google Maps API and Google Solar API for this key. Add http website restrictions to the key on your Google Console for your domain only.
4. Go to **Solar Leads → Settings** and enter your API Key (or keys)
5. Add the shortcode `[keiste_solar_report]` to any page

== Configuration ==

**Step 1: Get Google Solar API Key (Required)**

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable these Google APIs:
   - Solar API
   - Maps JavaScript API
   - Places API (New)
   - Maps API
4. Create credentials → API Key
5. **Restrict your key:**
   - Application restrictions: HTTP referrers
   - Website restrictions: Add your domain (e.g., `yourdomain.com/*`)

**Step 2: Configure Plugin**

1. In WordPress, go to **Solar Leads → Settings**
2. Paste your Google Solar and Maps API key
3. Set your defaults (optional):
   - Electricity rate
   - Grant percentages
   - Installation costs
   - Loan terms

**Step 3: Add to Your Site**

Add this shortcode to any page: `[keiste_solar_report]`

**That's it!** Your solar calculator is ready.

== External services ==

This plugin connects to an API to obtain solar info, it's needed to show the iridescence information and access satellite photos of roofs of buildings.

This service is provided by "Google": terms of use, privacy policy

== Frequently Asked Questions ==

= Do I need a Google API key? =

Yes, you need a Google Solar API, Google Places API (New), Google Maps Javascript API and Google Maps API enabled key to fetch data. You can obtain one from the Google Cloud Console. The API is free for limited usage.

= Which countries are supported? =

Currently, the plugin is configured for:
* **Ireland** (Rep. of Ireland)
* **United Kingdom**
* **United States**
* **Canada**

**More countries will be added soon!** The plugin works wherever Google Solar API has coverage, but grant calculations, currency, and electricity rates are currently optimized for the four countries listed above.

== Country Coverage ==

The plugin leverages Google's Solar API, which theoretically provides solar potential data for many countries. You can use the calculator for any address where Google has solar data, but financial calculations (grants, tariffs) are pre-configured for Ireland, UK, USA, and Canada.

= Can I customize the ROI calculations? =

Yes, all financial parameters can be customized through the Settings > Keiste Solar admin panel, including installation costs, grant rates, electricity costs, export tariffs, and loan terms.

= Is the plugin mobile-friendly? =

Yes, the plugin is fully responsive and optimized for mobile devices, tablets, and desktops.

= Can users download reports? =

PDF report generation is available in the premium version. The free version displays all calculations and charts on screen.

= Does it work with any WordPress theme? =

Yes, the plugin is designed to work with any WordPress theme. It uses Bootstrap 5 for styling and is fully self-contained.

= Is there a cost for using this plugin? =

The plugin itself is free. You can also upgrade for premium features. You'll need a Google API key. Google has generous free tier usage limits. Check Google Cloud pricing for current rates.

= Can I use this on multiple sites? =

Yes, you can use the plugin on multiple WordPress installations. Each site will need its own configured Google Solar API key.

== Screenshots ==

1. Address search interface with Google Maps autocomplete
2. ROI results dashboard showing payback period and savings
3. Interactive break-even chart over 25 years
4. System size configuration with panel slider
5. Financial inputs and customization options

== Changelog ==

= 1.0.0 =
* Initial release
* Google Solar API integration
* ROI calculator with customizable parameters
* Solar grant calculations
* Interactive charts (Chart.js)
* PDF report generation (gamma.app)
* Google Maps address autocomplete
* Mobile-responsive design
* Admin settings panel
* Bootstrap 5 UI framework
* WordPress coding standards compliance
* Security best practices (nonces, sanitization, escaping)

== Upgrade Notice ==

= 1.0.0 =
Initial release of Keiste Solar Analysis plugin.

== Additional Information ==

= Support =

For support, please visit [keiste.com](https://keiste.com/) or contact us through our website.

= Credits =

* Developed by Keiste
* Uses Google Solar API for solar potential data
* Uses Google Maps API for rooftop satellite image
* Uses Google Maps Javascript API for logic
* Uses Google Places API for autocomplete address bar
* Charts powered by Chart.js
* PDF generation by gamma.app
* UI framework: Bootstrap 5

= Privacy Policy =

This plugin sends address data to Google for analysis. No personal data is stored by the plugin itself unless the user fills out the lead generation form (premium plan only). Users who download reports provide their name and email, which is handled according to your site's privacy policy.

= Links =

* [Plugin Homepage](https://keiste.com/solar-report/)
* [Support](https://keiste.com/)
