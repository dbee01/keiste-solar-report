# Keiste Solar Report - Installation Guide

## Quick Start

### 1. Plugin Installation

Since the plugin is already in your WordPress plugins directory, you just need to:

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins > Installed Plugins**
3. Find "Keiste Solar Report" in the list
4. Click **Activate**

### 2. Initial Configuration

After activation:

1. Go to **Solar Leads > Settings** in your WordPress admin menu
2. Configure the following settings:

   **Email Settings:**
   - Set your notification email address (where you want to receive lead notifications)
   - Enable/disable customer confirmation emails

   **Calculator Settings:**
   - Default Electricity Rate: Set to your local average (e.g., 0.13 for $0.13/kWh)
   - Cost Per Watt: Average installation cost in your area (e.g., 2.75)
   - Federal Tax Credit: Current percentage (e.g., 0.30 for 30%)

3. Click **Save Settings**

### 3. Add Calculator to Your Site

**Option A: Using the Block Editor (Gutenberg)**
1. Edit any page or post
2. Add a "Shortcode" block
3. Enter: `[keiste_solar_calculator]`
4. Publish or Update the page

**Option B: Using the Classic Editor**
1. Edit any page or post
2. In the content area, add: `[keiste_solar_calculator]`
3. Publish or Update the page

**Option C: Using PHP in Theme Files**
```php
<?php echo do_shortcode('[keiste_solar_calculator]'); ?>
```

### 4. Test the Calculator

1. Visit the page where you added the calculator
2. Enter a sample monthly bill amount (e.g., 150)
3. Select a roof type
4. Click "Calculate Savings"
5. Verify results are displayed
6. Fill out the lead form
7. Submit and check if:
   - Success message appears
   - Email notification is received
   - Lead appears in **Solar Leads > All Leads**

## Shortcode Options

### Full Calculator
```
[keiste_solar_calculator]
```

### Calculator with Custom Title
```
[keiste_solar_calculator title="Find Your Solar Savings"]
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
[keiste_solar_lead_form button_text="Get Your Free Solar Quote"]
```

## Managing Leads

### View Leads
- Go to **Solar Leads > All Leads**
- Click "View" on any lead to see full details
- Click "Delete" to remove a lead

### Export Leads
- Go to **Solar Leads > All Leads**
- Click **Export to CSV** at the top
- Opens/downloads a CSV file with all lead data
- Import into your CRM or spreadsheet software

## Troubleshooting

### Calculator Not Displaying
- Check if jQuery is loaded on your site
- Ensure there are no JavaScript conflicts
- Try deactivating other plugins temporarily

### Emails Not Being Received
- Check WordPress email configuration
- Test with an SMTP plugin (WP Mail SMTP recommended)
- Check spam/junk folders
- Verify notification email in settings

### Styling Issues
- Check for theme CSS conflicts
- Try adding custom CSS in **Appearance > Customize > Additional CSS**
- Ensure the page has enough width for the calculator

### Database Issues
- Deactivate and reactivate the plugin to recreate tables
- Check database permissions
- View leads table: `wp_ksrad_solar_leads`

## Customization

### Custom CSS
Add to **Appearance > Customize > Additional CSS**:

```css
/* Change calculator primary color */
.ksrad-btn-primary {
    background: linear-gradient(135deg, #your-color 0%, #your-darker-color 100%);
}

/* Change result card hover color */
.ksrad-result-card:hover {
    border-color: #your-color;
}
```

### Modify Calculations
Edit `/includes/class-ksrad-calculator.php` and modify the `calculate_solar_savings()` method

### Change Email Templates
Edit `/includes/class-ksrad-lead-form.php` and modify:
- `send_admin_notification()` - Admin email template
- `send_lead_confirmation()` - Customer email template

## Best Practices

1. **Set Accurate Defaults** - Use local electricity rates and installation costs
2. **Test Regularly** - Submit test leads to ensure everything works
3. **Export Regularly** - Back up your leads by exporting to CSV
4. **Monitor Email Delivery** - Check that notifications are being sent
5. **Mobile Testing** - Test calculator on various devices
6. **Page Optimization** - Place calculator on a dedicated landing page
7. **Call to Action** - Add compelling text around the calculator

## Support

For questions or support:
- Website: https://keiste.com
- Documentation: See README.md
- Issues: Check CHANGELOG.md for known issues

## Next Steps

After installation:
1. ✅ Activate the plugin
2. ✅ Configure settings
3. ✅ Add shortcode to page
4. ✅ Test calculator and form
5. ✅ Verify email notifications
6. ✅ Create dedicated landing page
7. ✅ Drive traffic to calculator
8. ✅ Monitor and follow up with leads

## File Structure

```
keiste-solar-free/
├── keiste-solar-report.php       # Main plugin file
├── README.md                       # Documentation
├── CHANGELOG.md                    # Version history
├── INSTALLATION.md                 # This file
├── includes/
│   ├── class-ksrad-admin.php      # Admin interface
│   ├── class-ksrad-calculator.php # Calculator logic
│   ├── class-ksrad-database.php   # Database operations
│   └── class-ksrad-lead-form.php  # Lead form handling
└── assets/
    ├── css/
    │   ├── admin.css              # Admin styles
    │   ├── calculator.css         # Calculator styles
    │   └── lead-form.css          # Form styles
    └── js/
        ├── calculator.js          # Calculator functionality
        └── lead-form.js           # Form functionality
```

Happy lead generation! 🌞
