# Aragrow TimeGrow v2.0.0

A comprehensive WordPress time tracking and invoicing plugin with modular architecture.

## Overview

TimeGrow is now organized into isolated modules, making it easier to maintain and extend. All four previously separate plugins have been consolidated into one plugin with a modular structure.

## Module Structure

```
aragrow-timegrow/
├── aragrow-timegrow.php       # Main plugin file - loads all modules
├── modules/
│   ├── core/                  # Core time tracking functionality
│   │   ├── aragrow-timegrow-core.php
│   │   ├── includes/
│   │   └── assets/
│   ├── nexus/                 # REST API for Nx-LCARS app
│   │   └── aragrow-timegrow-nexus/
│   ├── woocommerce-integration/   # WooCommerce integration features
│   │   └── aragrow-woocommerce-intengration.php
│   └── paypal-invoicer/      # PayPal auto-invoicing
│       └── aragrow-wc-paypal-auto-invoicer.php
└── README.md
```

## Modules

### Core Module
**Location:** `modules/core/`

Features:
- Companies, Clients, and Projects management
- Team Members management
- Time Entries (Manual & Clock In/Out)
- Expenses with AI-powered receipt analysis
- Reports and Settings
- WooCommerce Products integration

### Nexus Module
**Location:** `modules/nexus/`

Features:
- Custom REST API endpoints for Nx-LCARS app
- JWT authentication support
- CORS configuration for localhost development
- User profile API customizations

### WooCommerce Integration Module
**Location:** `modules/woocommerce-integration/`

Features:
- Custom order statuses (Invoice Paid, Invoice Sent, Partial Paid)
- Manual payment recording
- PDF invoice integration
- User EIN/SSN fields
- Custom table headers for time invoices
- Auto-payment recording hooks

### PayPal Auto Invoicer Module
**Location:** `modules/paypal-invoicer/`

Features:
- Automatic PayPal invoice creation from WooCommerce orders
- PayPal Catalog Product synchronization
- Sandbox and Live environment support
- Encrypted API credentials storage
- Custom order status (Send Invoice to PayPal, Invoice Sent to PayPal)

## Installation

1. Upload the `aragrow-timegrow` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings under TimeGrow > Settings

## Migration from Separate Plugins

If you previously had the four separate plugins installed:

1. **Backup your database** before proceeding
2. Deactivate all four separate plugins:
   - Aragrow - TimeGrow
   - Aragrow - TimeGrow Nexus
   - Aragrow - WooCommerce Integration
   - Aragrow - WC PayPal Auto Invoicer
3. Activate the new consolidated Aragrow - TimeGrow plugin
4. All your data and settings should remain intact

**Note:** Do NOT delete the old plugins until you've verified everything works correctly.

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WooCommerce 4.0+ (for WooCommerce Integration and PayPal Invoicer modules)

## Configuration

### Core Module
Configure under **TimeGrow > Settings**:
- General settings (timezone, currency)
- AI Provider settings for receipt analysis

### Nexus Module
No additional configuration needed. REST API endpoints are available at `/wp-json/nexus/v1/`

### WooCommerce Integration Module
Automatically integrates when WooCommerce is active.

### PayPal Invoicer Module
Configure under **Settings > WC PayPal Auto Invoicer**:
- Environment (Sandbox/Live)
- Client ID and Secret
- Merchant Email
- Send Behavior

## Development

### Adding a New Module

1. Create a new directory under `modules/`
2. Create a main PHP file to bootstrap your module
3. Add a require statement in `aragrow-timegrow.php`:

```php
if ( file_exists( TIMEGROW_MODULES_DIR . 'your-module/your-module.php' ) ) {
    require_once TIMEGROW_MODULES_DIR . 'your-module/your-module.php';
}
```

### Module Independence

Each module is isolated and can:
- Define its own constants
- Have its own class autoloader
- Register its own hooks and filters
- Have its own activation/deactivation logic

## Support

For issues, feature requests, or questions:
- Website: https://aragrow.me/
- Email: support@aragrow.me

## License

GPL2

## Changelog

### 2.0.0 - 2025-02-12
- **Major refactor:** Consolidated four separate plugins into modular architecture
- Modules: Core, Nexus, WooCommerce Integration, PayPal Invoicer
- Each module isolated in its own directory
- Backward compatibility maintained
- Improved organization and maintainability

### 1.1.2
- Previous version with separate plugins
