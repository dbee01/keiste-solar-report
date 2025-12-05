# Security Documentation

## Security Measures Implemented in Keiste Solar Report Plugin

This document outlines the comprehensive security measures implemented throughout the plugin to protect against common vulnerabilities.

---

## 1. Input Validation & Sanitization

### Calculator Input Validation
**File:** `includes/class-ksrad-calculator.php`

- **Monthly Bill:** Validated to be between $0 and $999,999
- **Electricity Rate:** Validated to be between $0.01 and $10.00 per kWh
- **Roof Type:** Whitelist validation against allowed values (asphalt, metal, tile, flat, other)

```php
// Validate monthly bill
if ($monthly_bill <= 0 || $monthly_bill > 999999) {
    wp_send_json_error();
}

// Whitelist roof type
$allowed_roof_types = array('asphalt', 'metal', 'tile', 'flat', 'other');
if (!in_array($roof_type, $allowed_roof_types, true)) {
    $roof_type = 'asphalt';
}
```

### Lead Form Input Validation
**File:** `includes/class-ksrad-lead-form.php`

- **Name:** Length validation (2-255 characters), sanitized with `sanitize_text_field()`
- **Email:** Validated with `is_email()`, sanitized with `sanitize_email()`
- **Phone:** Length validation (10-50 characters), sanitized with `sanitize_text_field()`
- **Address:** Length validation (5-500 characters), sanitized with `sanitize_textarea_field()`
- **Notes:** Length limit (2000 characters), sanitized with `sanitize_textarea_field()`
- **Consent:** Validated to ensure checkbox is checked

```php
// Email validation
$email = sanitize_email($_POST['email']);
if (!is_email($email)) {
    wp_send_json_error();
}

// Name validation
if (strlen($name) < 2 || strlen($name) > 255) {
    wp_send_json_error();
}
```

---

## 2. Output Escaping

### HTML Context
All dynamic content displayed in HTML is escaped using appropriate functions:

- `esc_html()` - For plain text output
- `esc_attr()` - For HTML attributes
- `esc_url()` - For URLs (when applicable)
- `esc_html__()` / `esc_attr__()` - For translated strings
- `esc_html_e()` / `esc_attr_e()` - For echoing translated strings

**Examples:**
```php
// Admin page
echo esc_html($lead->name);
echo esc_html(number_format(floatval($lead->monthly_bill), 2));

// Attributes
<input value="<?php echo esc_attr($notification_email); ?>">
<a href="mailto:<?php echo esc_attr($lead->email); ?>">
```

### JavaScript Context
- JSON data is properly encoded using `json_encode()` with appropriate flags
- User data passed to JavaScript is escaped
- HTML in JavaScript strings uses proper escaping

---

## 3. Nonce Verification

### AJAX Requests
All AJAX handlers verify nonces before processing:

**Calculator:**
```php
check_ajax_referer('ksrad_calculate_nonce', 'nonce');
```

**Lead Form:**
```php
check_ajax_referer('ksrad_lead_nonce', 'nonce');
```

### Admin Actions
All admin post actions verify nonces:

**Settings Page:**
```php
check_admin_referer('ksrad_settings');
```

**Delete Lead:**
```php
check_admin_referer('delete_lead_' . $lead_id);
```

**Export Leads:**
```php
wp_verify_nonce($_GET['_wpnonce'], 'ksrad_export_leads');
```

---

## 4. Capability Checks

All admin functions verify user permissions:

```php
if (!current_user_can('manage_options')) {
    wp_die(esc_html__('You do not have permission...'));
}
```

**Protected Actions:**
- View leads dashboard
- Export leads to CSV
- Delete leads
- Access settings page
- Modify plugin settings

---

## 5. SQL Injection Prevention

### Prepared Statements
All database queries use prepared statements with proper placeholders:

```php
$wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE id = %d",
    $id
);

$wpdb->prepare(
    "SELECT * FROM {$table_name} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
    $per_page,
    $offset
);
```

### Column Whitelisting
Database queries with dynamic ORDER BY clauses use whitelisting:

```php
$allowed_orderby = array('id', 'name', 'email', 'created_at', 'monthly_bill');
if (!in_array($orderby, $allowed_orderby, true)) {
    $orderby = 'created_at';
}

$order = strtoupper($order);
if (!in_array($order, array('ASC', 'DESC'), true)) {
    $order = 'DESC';
}
```

### Integer Sanitization
Database IDs are properly sanitized:

```php
$lead_id = absint($_GET['lead_id']);
$page = max(1, absint($page));
$per_page = max(1, min(100, absint($per_page)));
```

---

## 6. Data Sanitization

### Input Data Types

| Field Type | Sanitization Function | Validation |
|-----------|----------------------|------------|
| Text (single line) | `sanitize_text_field()` | Length limits |
| Text (multi-line) | `sanitize_textarea_field()` | Length limits |
| Email | `sanitize_email()` | `is_email()` check |
| URL | `esc_url_raw()` | - |
| Integer | `absint()` or `intval()` | Range validation |
| Float | `floatval()` | Range validation |
| Checkbox | Value comparison | Strict equality |

### Database Insert
All data is sanitized before insertion:

```php
$wpdb->insert(
    $table_name,
    array(
        'name' => sanitize_text_field($data['name']),
        'email' => sanitize_email($data['email']),
        'phone' => sanitize_text_field($data['phone']),
        'address' => sanitize_textarea_field($data['address']),
        'monthly_bill' => floatval($data['monthly_bill']),
        // ...
    ),
    array('%s', '%s', '%s', '%s', '%f', ...)
);
```

---

## 7. Email Security

### Email Validation
All email addresses are validated before use:

```php
$admin_email = get_option('ksrad_notification_email');
if (empty($admin_email) || !is_email($admin_email)) {
    return;
}
```

### Content Sanitization
Email content is sanitized to prevent injection:

```php
$subject = sprintf(
    __('[%s] New Solar Lead: %s'),
    sanitize_text_field(get_bloginfo('name')),
    sanitize_text_field($lead_data['name'])
);
```

### Headers
Proper email headers prevent header injection:

```php
$headers = array(
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . sanitize_text_field(get_bloginfo('name')) . 
    ' <' . sanitize_email(get_option('admin_email')) . '>'
);
```

---

## 8. CSRF Protection

### Nonce Implementation
All forms include nonces:

**Frontend Forms:**
```php
wp_create_nonce('ksrad_lead_nonce');
wp_create_nonce('ksrad_calculate_nonce');
```

**Admin Forms:**
```php
wp_nonce_field('ksrad_settings');
```

**Admin Links:**
```php
wp_nonce_url(admin_url('admin-post.php?action=...'), 'action_name');
```

---

## 9. File Security

### Direct Access Prevention
All PHP files check for WordPress context:

```php
if (!defined('WPINC')) {
    die;
}

if (!defined('ABSPATH')) {
    exit;
}
```

### Safe Redirects
All redirects use WordPress safe functions:

```php
wp_safe_redirect(admin_url('admin.php?page=...'));
exit;
```

---

## 10. CSV Export Security

### Filename Sanitization
```php
$filename = 'solar-leads-' . sanitize_file_name(date('Y-m-d')) . '.csv';
```

### Content Escaping
CSV content properly escapes special characters:

```php
$row[] = '"' . str_replace('"', '""', $value) . '"';
```

### Headers
Security headers prevent caching and force download:

```php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
```

---

## 11. JavaScript Security

### XSS Prevention
- User data is escaped before output
- AJAX responses use `wp_send_json_success()` and `wp_send_json_error()`
- jQuery is used properly to prevent DOM-based XSS

### Input Validation
Client-side validation in addition to server-side:

```javascript
if (!monthlyBill || monthlyBill <= 0) {
    showError('Please enter a valid amount');
    return;
}

if (!isValidEmail(email)) {
    showError('Invalid email format');
    return;
}
```

---

## 12. Settings Security

### Value Validation
All settings are validated before saving:

```php
// Electricity rate (0.01 to 10.00)
$electricity_rate = floatval($_POST['rate']);
if ($electricity_rate > 0 && $electricity_rate <= 10) {
    update_option('ksrad_default_electricity_rate', $electricity_rate);
}

// Tax credit (0 to 1)
$tax_credit = floatval($_POST['tax_credit']);
if ($tax_credit >= 0 && $tax_credit <= 1) {
    update_option('ksrad_tax_credit', $tax_credit);
}
```

### HTML Form Attributes
HTML5 validation attributes prevent invalid input:

```html
<input type="number" min="0.01" max="10" step="0.001" required>
<input type="email" required>
```

---

## 13. Rate Limiting Considerations

While not implemented in this version, consider adding:
- Limiting number of lead submissions per IP per hour
- CAPTCHA for form submissions
- Honeypot fields for spam prevention

---

## Security Checklist

- ✅ All user input is validated
- ✅ All user input is sanitized
- ✅ All output is escaped
- ✅ All AJAX requests use nonces
- ✅ All admin actions verify capabilities
- ✅ All database queries use prepared statements
- ✅ SQL injection prevention (whitelisting, validation)
- ✅ XSS prevention (escaping, sanitization)
- ✅ CSRF protection (nonces)
- ✅ Direct file access prevention
- ✅ Safe redirects
- ✅ Email validation and sanitization
- ✅ File operation security
- ✅ Settings validation

---

## Best Practices Followed

1. **Defense in Depth:** Multiple layers of security
2. **Least Privilege:** Users only have necessary permissions
3. **Fail Securely:** Errors don't expose sensitive information
4. **Input Validation:** Never trust user input
5. **Output Encoding:** Always escape output
6. **Secure Defaults:** Safe default values
7. **WordPress Standards:** Follow WordPress coding and security standards

---

## Testing Recommendations

### Security Testing
1. Test with different user roles (admin, editor, subscriber)
2. Test SQL injection attempts in all input fields
3. Test XSS attempts in all text fields
4. Test CSRF by removing nonces
5. Test direct file access
6. Test with malformed data
7. Verify data sanitization in database
8. Check email headers for injection attempts

### Tools
- WordPress Plugin Check
- PHP_CodeSniffer with WordPress rules
- OWASP ZAP for penetration testing
- SQL injection testing tools
- XSS testing tools

---

## Maintenance

### Regular Updates
- Monitor WordPress security announcements
- Update sanitization/escaping functions as WordPress evolves
- Review and update validation rules
- Audit database queries regularly

### Security Monitoring
- Monitor for unusual lead submission patterns
- Check server logs for suspicious activity
- Review database for anomalies
- Monitor email sending patterns

---

## Reporting Security Issues

If you discover a security vulnerability, please email: security@keiste.com

**Do not** disclose security issues publicly until they are resolved.

---

*Last Updated: December 5, 2025*
*Plugin Version: 1.0.0*
