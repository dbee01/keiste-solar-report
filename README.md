# Keiste Solar Report - Free WordPress Plugin

A professional solar panel calculator and lead capture plugin for WordPress. Perfect for solar businesses to generate qualified leads from their website.

## Features

- **Interactive Solar Calculator** - Visitors can calculate potential savings based on their electric bill
- **Lead Capture Form** - Integrated form to collect prospect information
- **Admin Dashboard** - View and manage all solar leads
- **Email Notifications** - Get notified when new leads come in
- **CSV Export** - Export all leads for use in your CRM
- **Customizable Settings** - Configure electricity rates, costs, and tax credits
- **Responsive Design** - Works perfectly on desktop, tablet, and mobile devices

## Installation

1. Upload the plugin files to `/wp-content/plugins/keiste-solar-free/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Solar Leads > Settings' to configure your settings
4. Add the calculator to any page using the shortcode: `[keiste_solar_calculator]`

## Shortcodes

### Solar Calculator with Lead Form
```
[keiste_solar_calculator]
```

### Calculator with Custom Title
```
[keiste_solar_calculator title="Calculate Your Solar Savings"]
```

### Calculator Without Lead Form
```
[keiste_solar_calculator show_form="no"]
```

### Lead Form Only
```
[keiste_solar_lead_form]
```

### Lead Form with Custom Button
```
[keiste_solar_lead_form button_text="Request Free Quote"]
```

## Configuration

### Email Settings
- **Notification Email** - Where to send new lead notifications
- **Send Confirmation** - Toggle customer confirmation emails

### Calculator Settings
- **Default Electricity Rate** - Average $/kWh in your area
- **Cost Per Watt** - Average installation cost
- **Federal Tax Credit** - Current tax credit percentage

## How It Works

1. **Visitor Input** - Users enter their monthly electric bill and select their roof type
2. **Calculation** - Plugin calculates system size, cost, savings, and payback period
3. **Results Display** - Shows comprehensive savings estimate including:
   - Recommended system size
   - Estimated installation cost (after incentives)
   - Annual and 25-year savings
   - Payback period
   - CO₂ emissions offset
4. **Lead Capture** - Interested visitors fill out the integrated form
5. **Admin Notification** - You receive an email with the lead details
6. **Lead Management** - View, export, or delete leads from the WordPress admin

## Calculator Methodology

The calculator uses industry-standard formulas:
- Average solar production: 1,400 kWh per kW per year
- Roof efficiency adjustments based on roof type
- 30% federal tax credit (configurable)
- 3% annual electricity rate increase
- 25-year system lifetime
- 90% energy offset from solar

## Database

The plugin creates one table: `wp_ksrad_solar_leads`

Stores:
- Contact information (name, email, phone, address)
- Calculation data (bill amount, system size, costs, savings)
- Additional notes and metadata
- Timestamps and IP addresses

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Support

For support, feature requests, or bug reports, visit [keiste.com](https://keiste.com)

## License

GPL v2 or later

## Credits

Developed by Keiste - Solar Marketing Solutions

## Version History

### 1.0.0
- Initial release
- Solar calculator with lead capture
- Admin dashboard for lead management
- Email notifications
- CSV export functionality
- Configurable settings
