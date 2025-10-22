# 🌟 Recovero - Abandoned Cart Recovery for WooCommerce

**Recovero** is a powerful WordPress plugin designed to help you recover abandoned carts and boost sales with **automated email and WhatsApp notifications

## 🚀 Features

### Core Features
- **Cart Tracking:** Automatically track abandoned carts.
- **Email Recovery:** Send personalized recovery emails to customers.
- **Analytics Dashboard:** Comprehensive statistics and recovery metrics.
- **Geo Tracking:** Track customer locations for better insights.
- **Admin Interface:** Easy-to-use dashboard for managing abandoned carts.


### Pro Features (Available with License)
- **WhatsApp Recovery:** Send recovery messages via WhatsApp.
- **Exit-Intent Popups:** Capture customers before they leave.
- **Push Notifications:** Browser-based recovery notifications.
- **Advanced Triggers:** Custom recovery triggers and rules.
- **Recovery Coupons:** Automatic discount codes for abandoned carts.
- **Heatmaps:** Visual analytics of user behavior.


## ⚙️ Installation

1. Download the plugin ZIP file.
2. Go to **WordPress Admin → Plugins → Add New**.
3. Click **Upload Plugin** and select the ZIP file.
4. Activate the plugin.
5. Configure settings in **WordPress Admin → Recovero**.


## 📝 Requirements

- WordPress **5.0** or higher  
- WooCommerce **3.0** or higher  
- PHP **7.4** or higher  
- MySQL **5.6** or higher  


## 🔧 Configuration

### Basic Settings
**Email Settings:**
- Set the **"From Email"** address
- Customize email subject lines
- Configure delay before sending recovery emails

**Tracking Settings:**
- Enable/disable cart tracking
- Configure geographic tracking
- Set maximum cart age for recovery

**Recovery Methods:**
- Enable email recovery
- Configure WhatsApp recovery (**Pro**)

### Advanced Settings
- **Data Retention:** Set how long to keep recovery data  
- **Test Email:** Send test recovery emails  
- **Cron Jobs:** Configure automated recovery processes  


## 📊 Usage

### Monitoring Abandoned Carts
- **Dashboard:** `Recovero → Dashboard` for overview statistics  
- **Carts:** `Recovero → Carts` to view all abandoned carts  
- **Analytics:** `Recovero → Analytics` for detailed insights  

### Managing Recovery
- **Manual Recovery:** Click "Resend Email" for specific carts  
- **Bulk Actions:** Select multiple carts for bulk operations  
- **Export Data:** Export cart data for analysis  


## 🎨 Customization

**Email Templates:**  
```php
assets/views/email-template.php
```
**Styling:**
```
assets/css/admin.css   /* Admin interface */
assets/css/public.css  /* Frontend elements */
```
## 🛠 Troubleshooting
**Common Issues**
- Plugin not activating: Ensure WooCommerce is installed, check PHP version, verify file permissions.
- Emails not sending: Check WordPress email configuration, "From Email" settings, or use the "Send Test Email" feature.
- Carts not tracking: Ensure cart tracking is enabled, check for JavaScript conflicts, verify WooCommerce cart functionality.


**Debug Mode**

Add to `wp-config.php`:
```bash
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
Then check the debug log for Recovero-related errors.
```

## 🔌 API Reference
### AJAX Actions
**Public Actions**
- `recovero_track_page_view`: Track cart abandonment
- `recovero_save_geo`: Save geographic data

**Admin Actions**
- `recovero_get_stats`: Get dashboard statistics
- `recovero_resend_email`: Resend recovery email
- `recovero_export_cart`: Export cart data

**Hooks**
**Actions*
- `recovero_cron_hook`: Main recovery process
- `recovero_cleanup_hook`: Data cleanup process

**Filters**
- `recovero_email_subject`: Filter email subject
- `recovero_delay_hours`: Filter recovery delay

## 🗂 Development
**File Structure**
```
recovero/
├── recovero.php                 # Main plugin file
├── includes/
│   ├── class-recovero-*.php    # Core classes
│   └── helpers.php              # Helper functions
├── pro/
│   └── class-recovero-*.php    # Pro features
├── assets/
│   ├── css/
│   ├── js/
│   ├── views/
│   └── images/
└── languages/
    └── recovero.pot             # Translation template
```
### Contributing
- Fork the repository
- Create a feature branch
- Make your changes
- Test thoroughly
- Submit a pull request

### Coding Standards
- Follow WordPress Coding Standards
- Sanitize all input and escape all output
- Use nonces for AJAX requests
- Follow proper security practices

## 📄 License
This plugin is licensed under GPL v3 or later.

## 🆘 Support
- Visit the plugin documentation
- Check the troubleshooting section
- Contact support for premium features

## 📜 Changelog
- Version 1.0.0
- Initial release
- Core cart tracking functionality
- Email recovery system
- Admin dashboard
- Basic analytics
- Pro features framework

Note: Always test in a staging environment before deploying to production.

<div align="center">
<p>Developed by <a href="https://github.com/nabilaminhridoy" target="_blank">Nabil Amin Hridoy</a></p>
