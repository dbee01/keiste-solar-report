# Keiste Solar Report - Setup Instructions

## Production Setup Steps

### 1. Create a WordPress Page

1. Go to your WordPress Admin: `https://keiste.com/wp-admin/`
2. Navigate to **Pages → Add New**
3. Create a new page with:
   - **Title**: Solar Report (or any title you prefer)
   - **Permalink/Slug**: `keiste-solar-report`
4. In the page content editor, add ONLY this shortcode:
   ```
   [keiste_solar_report]
   ```
5. **Publish** the page

### 2. Verify Assets Are Loading

After creating the page, visit `https://keiste.com/keiste-solar-report/`

Open browser DevTools (F12) and check:
- **Console tab**: Look for any JavaScript errors
- **Network tab**: Verify these files load with status 200:
  - `/wp-content/plugins/keiste-solar-report/assets/js/roi-calculator.js`
  - `/wp-content/plugins/keiste-solar-report/assets/js/modal-handler.js`
  - `/wp-content/plugins/keiste-solar-report/assets/css/solar-analysis.css`

### 3. Clear Cache

If you use caching plugins or CDN:
- **Clear WordPress cache** (WP Super Cache, W3 Total Cache, etc.)
- **Clear Cloudflare cache** if applicable
- **Hard refresh browser**: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)

## Common Issues

### Assets Not Loading (404 errors)

**Symptom**: JavaScript files show 404 in Network tab

**Fix**: 
1. Verify the page has the shortcode `[keiste_solar_report]`
2. Clear all caches
3. Check file permissions: `chmod 644` for JS files

### Calculations Show €0

**Symptom**: All ROI values show €0

**Fix**:
1. Enter a value in "Monthly Electricity Bill" field
2. Drag the panel count slider to select panels
3. Values should update automatically

### Localhost Works but Production Doesn't

**Common causes**:
1. **Page doesn't have shortcode** - Create the page with `[keiste_solar_report]`
2. **Cache** - Clear all caches (WordPress, CDN, browser)
3. **File permissions** - Run: `find /path/to/plugin -type f -exec chmod 644 {} \;`
4. **Plugin not activated** - Check WordPress Admin → Plugins

## Testing

To verify the plugin is working:

1. Visit the page URL
2. You should see the solar calculator form
3. Enter a monthly bill amount (e.g., 200)
4. Drag the panel count slider
5. ROI calculations should update automatically

## Support

If issues persist, check:
- WordPress Admin → Settings → Keiste Solar Report (for API keys)
- Browser Console for JavaScript errors
- WordPress Debug Log (`wp-content/debug.log`)
