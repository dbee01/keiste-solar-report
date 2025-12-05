# Changelog

All notable changes to the Keiste Solar Report plugin will be documented in this file.

## [1.0.0] - 2025-12-05

### Added
- Initial release of Keiste Solar Report plugin
- Interactive solar savings calculator with real-time calculations
- Lead capture form integrated with calculator
- Admin dashboard for viewing and managing solar leads
- Email notifications for new leads (admin and customer)
- CSV export functionality for leads
- Configurable settings page:
  - Email notification settings
  - Calculator default values (electricity rate, cost per watt, tax credit)
- Two shortcodes for flexible implementation:
  - `[keiste_solar_calculator]` - Full calculator with lead form
  - `[keiste_solar_lead_form]` - Standalone lead form
- Comprehensive calculation features:
  - System size recommendation
  - Cost estimation with federal tax credit
  - Annual and 25-year savings projections
  - Payback period calculation
  - CO₂ emissions offset calculation
- Responsive design for mobile, tablet, and desktop
- Database table for storing lead information
- Security features (nonce verification, data sanitization)
- AJAX form submission for better UX
- Professional styling with animations
- Phone number auto-formatting
- Input validation and error handling

### Features
- **Calculator Inputs:**
  - Monthly electric bill
  - Roof type selection (asphalt, metal, tile, flat, other)
  - Electricity rate (configurable default)

- **Calculator Outputs:**
  - Recommended system size (kW)
  - Estimated installation cost (after tax credits)
  - Annual savings
  - 25-year lifetime savings
  - Payback period (years)
  - CO₂ offset over 25 years

- **Lead Data Captured:**
  - Full name
  - Email address
  - Phone number
  - Property address
  - Calculator results (bill, system size, costs, savings)
  - Additional notes
  - IP address and user agent (for tracking)
  - Timestamps

- **Admin Features:**
  - View all leads in sortable table
  - Pagination for large lead lists
  - Individual lead detail view modal
  - Delete leads
  - Export all leads to CSV
  - Statistics dashboard
  - Settings configuration
  - Quick access to shortcodes

### Technical Details
- WordPress version: 5.8+
- PHP version: 7.4+
- Uses wpdb for database operations
- Follows WordPress coding standards
- Fully translatable (text domain: keiste-solar-report)
- GPL v2+ licensed

### Database Schema
- Table: `wp_ksrad_solar_leads`
- Fields: id, name, email, phone, address, monthly_bill, roof_type, estimated_system_size, estimated_cost, estimated_savings, notes, ip_address, user_agent, created_at, updated_at

### Known Limitations
- Calculator uses average U.S. solar production rates
- Results are estimates and should be verified by professional solar installers
- Email functionality depends on WordPress mail configuration
