# TimeGrow - Project Description

## Overview

**TimeGrow** is an enterprise-grade WordPress plugin that combines intelligent time tracking, AI-powered expense management, and automated invoicing into a single, integrated platform.
---

## Core Purpose

TimeGrow solves three critical business challenges:

### 1. **Accurate Time Tracking**
Capture billable and non-billable hours across multiple projects and clients with both real-time clock tracking and manual entry options.

### 2. **Intelligent Expense Management**
Process receipts using multi-provider AI technology and automatically organize expenses for tax preparation with IRS Schedule C categories.

### 3. **Automated Invoicing**
Seamlessly convert tracked time and expenses into WooCommerce or PayPal invoices with custom order statuses and automatic payment recording.

---

## Key Differentiators

### 🤖 **Multi-AI Provider Choice**
- **First WordPress plugin** offering 3 AI provider options for receipt analysis
- Choose between **Google Gemini**, **OpenAI GPT-4**, or **Anthropic Claude**
- Switch providers based on cost, accuracy, or feature needs
- Provider-agnostic architecture allows easy addition of new AI services

### 🧠 **Smart AI That Respects User Data**
- AI auto-fills **only empty fields**, never overwrites manual entries
- **Mismatch detection** warns when AI analysis differs from user input
- **Confidence scoring** with configurable thresholds (0-100%)
- User approval required before AI makes any changes

### 📊 **Tax-Ready from Day One**
- All **33 IRS Schedule C categories** built-in
- Intelligent **vendor-to-category mapping**
- **Yearly tax reports** with category breakdowns
- **Monthly P&L reports** with year-to-date totals
- CSV export for QuickBooks/Xero integration

### 📱 **Mobile-First Architecture**
- **PIN-based authentication** (6-character alphanumeric)
- **Wordfence 2FA integration** for enterprise security
- **SMS notifications** for login and PIN generation
- Touch-optimized, **responsive UI**
- **Searchable project dropdowns** with autocomplete
- Edge-to-edge mobile layout with compact headers

### 🔗 **Comprehensive Integration Ecosystem**
- **WooCommerce:** Custom order statuses, automatic payment recording, PDF invoices
- **PayPal:** Auto-invoicing with catalog sync and sandbox support
- **REST API:** JWT authentication for external app integration
- **Wordfence:** Two-factor authentication support

---

## Technical Architecture

### **Modular Design**

TimeGrow is built with a clean, modular architecture consisting of 5 core modules:

#### 1. **Core Module**
- Foundation layer (models, utilities, settings)
- Database abstraction and ORM-like functionality
- Base classes for controllers and views
- Settings management and encryption utilities

#### 2. **Nexus Module**
- REST API endpoints (`/wp-json/nexus/v1/`)
- JWT authentication support
- External app integration (Nx-LCARS support)
- CORS configuration for development

#### 3. **WooCommerce Module**
- E-commerce integration and invoicing
- Custom order statuses (Invoice Paid, Invoice Sent, Partial Paid, etc.)
- Automatic payment recording
- Order lifecycle hooks
- PDF invoice integration

#### 4. **PayPal Module**
- Automated invoice generation
- PayPal Catalog Product synchronization
- Sandbox and Live environment support
- Encrypted API credential storage

#### 5. **Mobile Module**
- PIN-based authentication system
- Mobile device detection and redirect
- SMS notification integration
- Mobile-optimized UI components
- Wordfence 2FA workflow

---

### **Database Schema**

TimeGrow uses **7 custom MySQL tables** for optimized performance:

| Table Name | Purpose |
|------------|---------|
| `wp_timegrow_time_entry_tracker` | Time entries with billing status |
| `wp_timegrow_expense_tracker` | Expenses with categories and assignments |
| `wp_timegrow_project_tracker` | Projects with clients and flat fees |
| `wp_timegrow_team_member_tracker` | Team member profiles |
| `wp_timegrow_expense_receipt_tracker` | Receipt images and metadata |
| `wp_timegrow_company_tracker` | Company information |
| `wp_timegrow_mobile_pins` | Mobile PIN authentication data |

**Key Relationships:**
- Projects → Clients (many-to-one)
- Time Entries → Projects (many-to-one)
- Expenses → Receipts (one-to-many)
- Team Members → Users (one-to-one)

---

### **Security Features**

- **Encrypted API key storage** for AI providers (AES-256)
- **PIN hash + salt authentication** (never plain text)
- **Failed attempt tracking** with automatic lockout protection
- **JWT token-based** REST API authentication
- **WordPress nonce verification** throughout all forms
- **Capability-based permissions** with 9 custom capabilities
- **SQL injection prevention** with prepared statements
- **XSS protection** with proper escaping

---

### **Technology Stack**

- **Backend:** PHP 7.4+ (OOP architecture)
- **Framework:** WordPress 5.0+
- **Database:** MySQL 5.7+
- **API:** REST API with JWT authentication
- **AI Integration:** Multi-provider SDK (Google, OpenAI, Anthropic)
- **Frontend:** Vanilla JavaScript with jQuery
- **CSS:** Responsive, mobile-first design
- **Security:** WordPress nonces, encrypted storage, hashed PINs

---

## Feature Set

### ⏱️ **Time Tracking**

#### Clock In/Out System
- Real-time clock-based tracking with start/stop functionality
- **Dual timezone display** (GMT + user timezone)
- Project-based time assignment
- Active clock state management
- Clock entry type tracking (CLOCK, MAN, IN)

#### Manual Time Entry
- Manual entry widget for retroactive logging
- **Decimal hour format** (e.g., 2.5 hours)
- Date and time assignment flexibility
- Description/notes field for context
- Entry type distinction for reporting

#### Billable Time Management
- Mark entries as **billable or non-billable**
- **Billed status tracking** (records which order entries were invoiced)
- **Unbilling functionality** (reverse billing if orders are deleted)
- Integration with WooCommerce order lifecycle
- Automatic sync between order deletion and time entry status

---

### 💰 **Expense Management**

#### Core Expense Tracking
- Expense entry with name, description, date, and amount
- **Payment method tracking** (personal card, company card, bank transfer, cash, other)
- **Category assignment** (33 IRS Schedule C categories)
- Assignment to projects, clients, or general expenses
- Receipt/proof-of-expense storage
- Timestamps for created_at and updated_at

#### AI-Powered Receipt Analysis

**Multi-Provider Support:**
- **Google Gemini** (gemini-1.5-flash, gemini-1.5-pro, gemini-2.0-flash-exp)
- **OpenAI GPT-4** (gpt-4o, gpt-4o-mini, gpt-4-turbo)
- **Anthropic Claude** (claude-3-5-sonnet, claude-3-opus, claude-3-haiku)

**AI Features:**
- Automatic receipt image analysis
- **Smart field population** (only fills empty fields, never overwrites)
- **Intelligent mismatch detection** and warnings
- **Confidence scoring** (0-100%) with configurable threshold
- **Client/Project auto-assignment** via receipt text patterns
- **33 IRS Schedule C tax category mapping**
- **Vendor-to-category intelligent mapping**
- Loading indicators and visual feedback

**User Approval System:**
- Checkbox-based approval for AI analysis
- Auto-appears when receipt uploaded
- Auto-checked by default for convenience
- Provider name display in approval text
- Graceful degradation if AI not configured

#### IRS Schedule C Categories

**Part II (22 categories):**
- Advertising
- Car & Truck Expenses
- Commissions & Fees
- Contract Labor
- Depreciation
- Employee Benefit Programs
- Insurance
- Interest (Mortgage)
- Interest (Other)
- Legal & Professional Services
- Office Expense
- Pension & Profit-Sharing Plans
- Rent or Lease (Vehicles, Machinery, Equipment)
- Rent or Lease (Other Business Property)
- Repairs & Maintenance
- Supplies
- Taxes & Licenses
- Travel
- Meals
- Utilities
- Wages
- Depletion

**Part V (11 categories):**
- Online & Web Fees
- Business Telephone
- Education & Training
- Membership Dues
- Books & Publications
- Photography & Stock Images
- Marketing Materials
- Shipping & Postage
- Bank Fees
- Credit Card Processing Fees
- Other Expenses

---

### 📊 **Reporting & Analytics**

#### Admin Reports (6 Reports)

1. **Team Hours Summary**
   - Total hours by team member across all projects
   - Billable vs. non-billable breakdown
   - Date range filtering

2. **Project Financials**
   - Hours, expenses, and billing overview per project
   - Revenue calculations
   - Profitability analysis

3. **Client Activity Report**
   - Hours and expenses aggregated by client
   - Multi-project view per client
   - Client profitability insights

4. **All Expenses Overview**
   - Detailed expense breakdown with filtering
   - Category-based organization
   - Payment method tracking

5. **Time Entry Audit Log**
   - Comprehensive log of all time entries
   - Edit and deletion tracking
   - Complete audit trail with timestamps

6. **Monthly Profit & Loss Report**
   - Monthly income/expense breakdown
   - Year-to-date totals
   - Tax-ready organization

#### Team Member Reports (4 Reports)

1. **My Detailed Time Log**
   - Personal time tracking history
   - Clocked hours and manual entries
   - Project assignment details

2. **My Hours by Project**
   - Project-based time breakdown
   - Personal productivity insights
   - Billable hour tracking

3. **My Expenses Report**
   - Personal expense listing
   - Category breakdowns
   - Receipt tracking

4. **Yearly Tax Report**
   - Tax-ready data with IRS category breakdowns
   - Annual expense summary
   - Schedule C organization

#### Report Features
- **CSV export** functionality for all reports
- **Date range filtering** with custom date pickers
- **Category-based filtering** for expenses
- **Project and client filtering** options
- **Visual charts** and data visualization
- **Monthly breakdowns** with aggregations
- **Tax-specific** data organization

---

### 👥 **User Management & Permissions**

#### Three-Tier Role System

1. **Administrator**
   - Full system access to all reports and data
   - User management capabilities
   - Settings configuration
   - AI provider setup

2. **Team Member**
   - Access to personal reports only
   - Time tracking capabilities
   - Expense entry and receipt upload
   - Personal mobile PIN management

3. **Owner/Operator** (TIMEGROW_OWNER_CAP)
   - Access to management functions
   - Team oversight capabilities
   - Project and client management

#### Fine-Grained Capabilities (9 Custom Capabilities)

**Admin-Only:**
- `view_team_hours_summary`
- `view_project_financials`
- `view_client_activity_report`
- `view_all_expenses_overview`
- `view_time_entry_audit_log`
- `view_monthly_profit_loss`
- `manage_expense_categories`

**Team Member Access:**
- `view_yearly_tax_report`
- `view_my_detailed_time_log`
- `view_my_hours_by_project`
- `view_my_expenses_report`

**Capability Features:**
- Automatic capability registration on plugin activation
- PublishPress Capabilities integration for UI management
- Fallback role-based checking
- Per-report capability enforcement

---

### 📱 **Mobile Features**

#### Mobile PIN Authentication
- **6-character alphanumeric PIN** generation
- Mobile device detection and redirect
- **PIN hash storage** (never plain text)
- **PIN salt** for enhanced security
- **Failed attempt tracking** with automatic lockout
- Active/inactive PIN status management
- Last login timestamp tracking
- Admin generation or user self-generation

#### Mobile 2FA Integration
- Automatic **Wordfence 2FA detection**
- **Two-step login:** PIN → 2FA code
- **TOTP** (Time-based One-Time Password) support
- Wordfence recovery codes support
- Session-based 2FA state tracking

#### Mobile SMS Notifications
- **PIN generation SMS alerts**
- **Login notification SMS**
- E.164 phone number format support
- Phone number validation
- SMS service integration hooks
- Twilio pre-integration
- Logging for testing/development mode

#### Mobile UI Optimizations
- **Touch-friendly form inputs** (min-height: 50px)
- **Responsive layout** with edge-to-edge design
- **Searchable project dropdowns** with autocomplete
- **Compact headers** (24px h1, 14px subtitle on mobile)
- **Hidden decorative icons** on mobile
- **WordPress admin spacing removal** (#wpcontent padding: 0)
- **Mobile-optimized buttons** with proper touch targets

---

### 🔗 **Integration Capabilities**

#### WooCommerce Integration

**Custom Order Statuses:**
- Invoice Paid
- Invoice Sent
- Partial Paid
- Send Invoice to PayPal (PayPal Invoicer)
- Invoice Sent to PayPal (PayPal Invoicer)

**Features:**
- Manual payment recording
- **Order-to-time-entry linking**
- **Automatic payment recording** on status changes
- EIN/SSN user fields
- **PDF invoice integration** with "WooCommerce PDF Invoices & Packing Slips"
- Custom table headers for time invoices
- **Auto-sync order deletion** with time entry unbilling

#### PayPal Auto Invoicer

- **Automatic invoice creation** from WooCommerce orders
- **PayPal Catalog Product synchronization**
- Sandbox and Live environment support
- **Encrypted API credential storage**
- Custom PayPal invoice statuses
- Auto-send to PayPal functionality

#### REST API (Nexus Module)

**Custom Endpoints:**
- `/wp-json/nexus/v1/auth` - Login authentication
- `/wp-json/nexus/v1/auth-me` - Current user info
- `/wp-json/nexus/v1/query` - Custom data queries

**Features:**
- **JWT authentication** support
- **Application password login** alternative
- **CORS configuration** for localhost development (port 5173)
- Base namespace: `jwt-auth/v2`
- Extensible endpoint architecture
- Built for **Nx-LCARS app** integration

#### WooCommerce Integration Settings
- Client integration checkbox
- Invoice integration checkbox
- Product integration checkbox
- Per-client currency settings
- Default flat fee per client
- Team member admin access control

---

### 🎯 **Project & Client Management**

#### Company Management
- Company profiles with contact information
- Multiple companies support
- Company-level settings and defaults
- Tax ID (EIN/SSN) tracking

#### Client Management
- Client creation and editing interface
- Client assignment to projects
- Client contact information storage
- Integration with WordPress users
- EIN/SSN tracking for tax reporting
- Default currency and flat fee settings

#### Project Management
- **CRUD operations** (Create, Read, Update, Delete)
- Client assignment (many-to-one relationship)
- Project status tracking (active/inactive)
- Default flat fee per project
- Start and end date tracking
- Billable status control
- Estimated hours for project planning
- Project creator tracking
- **WooCommerce product linking** for invoicing

#### Team Member Management
- Team member profiles linked to WordPress users
- User-to-team-member mapping
- Project assignment capabilities
- Role and capability assignment
- **Mobile PIN generation/management**
- Phone number storage for SMS notifications

---

## Unique & Standout Features

### 1. **Multi-AI Provider Support**
First WordPress plugin to offer choice between Google Gemini, OpenAI GPT-4, and Anthropic Claude for receipt analysis. Provider-agnostic architecture allows easy addition of new AI services.

### 2. **Smart Field Population with Mismatch Detection**
AI never overwrites user-entered data. System warns about discrepancies between receipt analysis and user input, giving users full control.

### 3. **IRS Schedule C Category Auto-Mapping**
Official 33-category tax system with intelligent vendor-to-category mapping. Tax-ready expense organization eliminates manual categorization.

### 4. **Mobile PIN Authentication**
Lightweight alternative to full WordPress login. 6-character PIN for fast mobile access that integrates seamlessly with Wordfence 2FA.

### 5. **SMS Integration for Mobile**
Mobile PIN SMS notifications and login activity alerts. Extensible SMS provider hooks support Twilio and custom services.

### 6. **Automatic Invoice Status Sync**
Order deletion automatically triggers time entry unbilling. Status changes auto-record payments. Custom order statuses for complete invoicing workflow.

### 7. **Modular Architecture**
Isolated module system with Core, Nexus (API), WooCommerce, PayPal, and Mobile modules. Each can be independently managed with clean separation of concerns.

### 8. **REST API for External Apps**
JWT authentication with custom endpoints for external integrations. CORS support for development. Built specifically to support the Nx-LCARS companion app.

### 9. **Capability-Based Reporting Access**
PublishPress integration with fine-grained permission control. Automatic admin notice integration with fallback to role-based access.

### 10. **Comprehensive Tax Reporting**
Yearly tax report with category breakdowns, monthly P&L report with year-to-date totals, Schedule C category organization, and tax-ready CSV export.

---

## Target Audience

### **Consulting Firms**
Multi-client time tracking with billable hour management, project-based expense allocation, and professional invoicing with WooCommerce integration.

### **Creative Agencies**
Team time tracking across projects, expense tracking for client reimbursement, comprehensive project financials, and client activity reporting.

### **Field Service Teams**
Mobile-first design for on-site work, PIN authentication for quick access, touch-optimized UI for field use, and SMS notifications for team coordination.

### **Freelancers**
Professional invoicing with time-to-invoice automation, tax-ready expense categorization, yearly tax reports for IRS filing, and all-in-one business management.

### **Small Businesses**
Complete time, expense, and invoicing solution, no monthly SaaS fees (one-time plugin cost), WordPress integration with existing website, and data ownership and privacy.

### **Remote Teams**
Cloud-based tracking accessible from anywhere, mobile apps for distributed teams, real-time synchronization, and comprehensive audit trails.

---

## Business Value Proposition

### **For Business Owners:**
- **Complete visibility** into team time and project profitability
- **Automatic invoicing** reduces administrative overhead by 80%
- **Tax-ready expense reports** save thousands in accounting costs
- **No monthly SaaS fees** - one-time WordPress plugin investment
- **Data ownership** - your data stays on your server

### **For Team Members:**
- **Fast mobile time tracking** with PIN authentication (5 seconds vs. 30+ seconds traditional login)
- **AI receipt analysis** eliminates manual data entry (saves 2-3 minutes per receipt)
- **Clear project assignment** and billable status tracking
- **Self-service reports** for personal productivity insights

### **For Accountants:**
- **IRS Schedule C categories** pre-organized
- **CSV export** for easy QuickBooks/Xero import
- **Monthly P&L and yearly tax reports** ready for filing
- **Comprehensive audit trail** of all entries and edits

### **For IT/DevOps:**
- **Self-hosted** WordPress plugin (no external dependencies)
- **REST API** for custom integrations
- **Modular architecture** for easy customization
- **Encrypted credential storage** for compliance
- **Comprehensive logging** for debugging

---

## Current Development Status

### **Active Branch:** 2.2.0

### **Latest Work (February 2026):**
- ✅ Mobile UI optimization for Nexus pages (manual entry, clock in/out, expenses)
- ✅ Searchable project dropdowns with HTML5 datalist autocomplete
- ✅ Compact mobile headers with WordPress admin spacing fixes
- ✅ Expense page reorganization (receipts moved to top, consistent styling)
- ✅ Fixed parameter errors in expense view controller
- ✅ Improved mobile responsiveness across all three Nexus pages
- ✅ Added button styles to expense page matching manual entry
- ✅ Removed "Administrator Access" heading from mobile pages
- ✅ Edge-to-edge mobile layout with #wpcontent padding removal

### **Recent Commits:**
- `f163ec5` - Optimize mobile UI for nexus pages with compact headers and responsive layout
- `d8b3370` - Fix expense page parameter error and add mobile responsive styles
- `b9ea443` - Fix expense page mobile issues and move dropdown styles to page CSS
- `1c1ed91` - Hide project tiles on expense page to match manual/clock pages
- `09f5d37` - Add searchable dropdown to clock and expense pages

---

## Future Roadmap

### **Short-term (Next 3-6 months):**
- Complete mobile UI optimization
- Enhance AI receipt analysis accuracy with confidence tuning
- Expand WooCommerce integration features (custom product types)
- Improve reporting visualizations with Chart.js integration
- Add batch operations for time entry management

### **Medium-term (6-12 months):**
- **Native mobile apps** (iOS/Android with React Native)
- **Geofencing** for clock-in verification
- **Biometric authentication** (fingerprint/Face ID)
- **QR code quick-login** for shared devices
- **Advanced scheduling** & forecasting tools
- **Team performance analytics** dashboard

### **Long-term (12+ months):**
- **Multi-currency expense support** with real-time exchange rates
- **Real-time collaboration** features (live editing, comments)
- **Advanced AI features** (expense predictions, anomaly detection)
- **Third-party integrations** (Slack, Microsoft Teams, Zapier)
- **White-label options** for agencies
- **Multi-company support** for enterprise use cases

---

## Development Philosophy

### **User Data Sovereignty**
AI assists, never overwrites. Users maintain complete control over their data. No data is ever sent to third parties without explicit consent.

### **Mobile-First**
Touch-optimized, responsive design from the ground up. Mobile users are first-class citizens, not an afterthought.

### **Security by Design**
Encryption, hashing, lockouts, and capability-based permissions built into the foundation, not bolted on later.

### **Modular Architecture**
Clean separation of concerns with isolated modules. Each feature can be independently developed, tested, and disabled.

### **Tax-Ready**
IRS compliance from day one. Expense categories and reports designed with tax preparation in mind.

### **Integration-Friendly**
REST API, WordPress hooks, and extensible architecture. Built to play nicely with other tools in your stack.

### **Open to Innovation**
Multi-AI provider support demonstrates willingness to adopt best-of-breed technologies rather than lock-in.

### **Performance Conscious**
Optimized database queries, minimal external dependencies, and efficient caching strategies.

---

## Technical Highlights

### **Code Quality:**
- **OOP architecture** with models, views, controllers
- **Prepared statements** for all database queries
- **WordPress coding standards** compliance
- **Comprehensive error handling** with graceful degradation
- **Extensive logging** for debugging and audit trails

### **Database Performance:**
- **7 custom tables** with proper indexing
- **Foreign key relationships** for data integrity
- **Optimized queries** with minimal JOINs
- **Pagination** for large datasets

### **API Design:**
- **RESTful endpoints** following WordPress conventions
- **JWT authentication** with refresh tokens
- **Rate limiting** to prevent abuse
- **CORS support** for cross-origin requests
- **Versioned endpoints** (v1, v2) for backwards compatibility

### **Frontend:**
- **Progressive enhancement** (works without JavaScript)
- **Vanilla JavaScript** with minimal jQuery dependency
- **Mobile-first CSS** with media queries
- **Touch-optimized** form controls (50px minimum height)
- **Responsive images** and lazy loading

### **AI Integration:**
- **Factory pattern** for provider abstraction
- **Graceful fallback** if AI service unavailable
- **Confidence thresholding** to prevent low-quality suggestions
- **Rate limiting** to manage API costs
- **Local caching** of AI responses to reduce API calls

---

## Installation & Requirements

### **System Requirements:**
- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- HTTPS enabled (required for JWT)
- SSL certificate (required for secure API)

### **Recommended:**
- WordPress 6.0+
- PHP 8.0+
- MySQL 8.0+
- WooCommerce 6.0+ (for invoicing features)
- Wordfence 7.0+ (for 2FA integration)

### **Optional Integrations:**
- WooCommerce PDF Invoices & Packing Slips plugin
- PublishPress Capabilities plugin (for UI capability management)
- Twilio account (for SMS notifications)

---

## Support & Documentation

### **Documentation:**
- Comprehensive user guide (in development)
- API documentation for developers
- Video tutorials for common workflows
- FAQ and troubleshooting guide

### **Developer Resources:**
- Code examples and snippets
- Hook and filter reference
- REST API endpoint documentation
- Database schema diagrams

---

## License & Ownership

**License:** Proprietary
**Copyright:** Aragrow
**Repository:** https://github.com/aragrow/aragrow-timegrow

---

## Conclusion

TimeGrow represents a **new generation of business management tools** - combining the power of cutting-edge AI with the flexibility of WordPress, all while maintaining complete data ownership and privacy.

Built for businesses that value their **time**, their **data**, and their **independence**.

---

**#WordPress #AI #TimeTracking #ExpenseManagement #BusinessManagement #OpenSource**
