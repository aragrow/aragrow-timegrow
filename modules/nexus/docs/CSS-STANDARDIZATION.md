# CSS Standardization for TimeGrow Plugins

## Overview
Consolidated all modern design patterns into a single global CSS file for consistency across all TimeGrow plugins.

## Global CSS File
**Location:** `timegrow-modern.css`
- Nexus: `/aragrow-timegrow-nexus/assets/css/timegrow-modern.css`
- TimeGrow: `/aragrow-timegrow/assets/css/timegrow-modern.css`

## Standardized Class Names

### Layout Classes
- `.timegrow-modern-wrapper` - Main container for all modern pages
- `.timegrow-cards-container` - Grid container for cards
- `.timegrow-footer` - Footer section

### Header Classes
- `.timegrow-modern-header` - Gradient header with illustration
- `.timegrow-header-content` - Header text content
- `.timegrow-header-illustration` - Header icon/illustration

### Card Classes
- `.timegrow-card` - Standard card with hover effects
- `.timegrow-card-header` - Card header section
- `.timegrow-card-body` - Card body section
- `.timegrow-card-footer` - Card footer section
- `.timegrow-card-title` - Card title container
- `.timegrow-card-description` - Card description text

### Icon Classes
- `.timegrow-icon` - Base icon container
- `.timegrow-icon-primary` - Purple gradient icon
- `.timegrow-icon-woocommerce` - WooCommerce purple icon
- `.timegrow-icon-paypal` - PayPal blue icon
- `.timegrow-icon-success` - Green gradient icon
- `.timegrow-icon-warning` - Orange gradient icon
- `.timegrow-icon-disabled` - Gray gradient icon

### Badge Classes
- `.timegrow-badge` - Base badge style
- `.timegrow-badge-primary` - Blue badge
- `.timegrow-badge-success` / `.timegrow-badge-active` - Green badge
- `.timegrow-badge-warning` - Yellow badge
- `.timegrow-badge-inactive` - Red badge
- `.timegrow-badge-info` - Cyan badge

### Feature Classes
- `.timegrow-features` - Feature badges container
- `.timegrow-feature-badge` - Small pill-style feature badge
- `.timegrow-feature-item` - List-style feature with toggle
- `.timegrow-feature-info` - Feature description

### Toggle Switch Classes
- `.timegrow-toggle-switch` - Toggle switch container
- `.timegrow-toggle-slider` - Toggle slider element

### Notice/Info Box Classes
- `.timegrow-notice` - Alert/notice box
- `.timegrow-notice-warning` - Yellow warning notice
- `.timegrow-notice-info` - Blue info notice
- `.timegrow-notice-success` - Green success notice
- `.timegrow-info-box` - Lighter info box style

### Help Section Classes
- `.timegrow-help-section` - Help section container
- `.timegrow-help-icon` - Help icon
- `.timegrow-help-content` - Help text content
- `.timegrow-help-links` - Help links container
- `.timegrow-help-link` - Individual help link

### Action Link Classes
- `.timegrow-action-link` - Clickable action link with arrow

## Design Patterns

### Color Scheme
- Primary Gradient: `linear-gradient(135deg, #667eea 0%, #764ba2 100%)`
- WooCommerce: `linear-gradient(135deg, #96588a 0%, #7f4a7a 100%)`
- PayPal: `linear-gradient(135deg, #0070ba 0%, #1546a0 100%)`

### Animations
- Keyframe: `timegrowFadeInUp` - Fade in from bottom
- Cards animate on load with staggered delays

### Responsive Breakpoints
- Mobile: `@media (max-width: 768px)`
- Tablet: `@media (max-width: 1024px)`

## Updated Files

### Nexus Plugin - Fully Modernized Pages
1. **TimeGrowNexusView.php** - Dashboard view with modern header and card layout
2. **TimeGrowNexusSettingsView.php** - Settings view with modern header and cards
3. **TimeGrowNexusReportView.php** - Reports dashboard with modern header and cards

### Nexus Plugin - Modern Headers Added
4. **TimeGrowNexusClockView.php** - Clock in/out with modern header (functional content preserved)
5. **TimeGrowNexusManualView.php** - Manual time entry with modern header (functional content preserved)
6. **TimeGrowNexusExpenseView.php** - Expenses with modern header (functional content preserved)

### Nexus Plugin - CSS Enqueuing
7. **TimeGrowNexus.php** - Enqueues `timegrow-modern.css` for all pages (alongside page-specific CSS where needed)

### TimeGrow Plugin - Form/List Pages
1. **TimeGrowIntegration.php** - Updated to use global classes
2. **TimeGrowCompanyView.php** - Added modern headers to list, add, edit views
3. **TimeGrowCompany.php** - Enqueues timegrow-modern.css, forms.css, company.css
4. **TimeGrowTeamMemberView.php** - Added modern headers to list, add, edit views
5. **TimeGrowTeamMember.php** - Enqueues timegrow-modern.css, forms.css, team_member.css
6. **TimeGrowProjectView.php** - Added modern headers to list, add, edit views
7. **TimeGrowProject.php** - Enqueues timegrow-modern.css, forms.css, project.css
8. **TimeGrowExpenseView.php** - Added modern headers to display_expenses, add_expense, edit_expense views
9. **TimeGrowExpense.php** - Enqueues timegrow-modern.css, forms.css, expense.css
10. **TimeGrowClientController.php** - Added modern header to WooCommerce integration notice
11. **TimeGrowClient.php** - Enqueues timegrow-modern.css, forms.css, company.css

## Old Files (Can be deprecated)
- `/aragrow-timegrow-nexus/assets/css/settings.css` - Superseded by global CSS
- `/aragrow-timegrow-nexus/assets/css/nexus_dashboard.css` - Superseded by global CSS
- `/aragrow-timegrow-nexus/assets/css/nexus_project_bc.css` - No longer needed for dashboard
- `/aragrow-timegrow/assets/css/integration.css` - Superseded by global CSS

## Dashboard Updates
### Nexus Plugin
- **TimeGrowNexusView.php** - Updated "Record Expenses" card:
  - Changed from "Coming Soon" to "Active" status
  - Updated link to point to `page=timegrow-expenses-list`
  - Changed icon from disabled to primary gradient
  - Added action link "Add Expense"

## Advanced Features Added

### Table Enhancements (Companies, Team Members, Projects, Expenses)

**Files Modified:**
- **Companies:**
  - `TimeGrowCompanyController.php` - Added filtering, sorting, and search functionality; Fixed table name issue
  - `TimeGrowCompanyView.php` - Added sortable columns, filters UI, search box, and default value handling
  - `company.js` - Added filter and search JavaScript handlers with Enter key support

- **Team Members:**
  - `TimeGrowTeamMemberController.php` - Added filtering, sorting, and search functionality
  - `TimeGrowTeamMemberView.php` - Added sortable columns, filters UI, status badges
  - `team_member.js` - Added filter and search JavaScript handlers

- **Projects:**
  - `TimeGrowProjectController.php` - Added filtering, sorting, and search functionality
  - `TimeGrowProjectView.php` - Added sortable columns, filters UI, status and billable badges
  - `project.js` - Added filter and search JavaScript handlers

- **Expenses:**
  - `TimeGrowExpenseController.php` - Added filtering, sorting, and search functionality
  - `TimeGrowExpenseView.php` - Added sortable columns, filters UI, improved column display
  - `expense.js` - Added filter and search JavaScript handlers

- **Global:**
  - `forms.css` (both plugins) - Added modern form styling with optimized spacing
  - `timegrow-modern.css` (both plugins) - Reduced header margin-bottom for better spacing

**Features:**
1. **Sortable Columns** - All tables now have sortable columns
   - **Companies:** Name, Legal Name, State, City, Country, Status
   - **Team Members:** Name, Company, Email, Title, Status
   - **Projects:** Project Name, Client, Start Date, End Date, Status, Billable
   - **Expenses:** Date, Amount, Client, Project
   - Click column header to sort ascending/descending
   - Visual indicators show current sort direction
   - Maintains filters when sorting
   - Validated column names and order directions for security

2. **Multi-Filter System:**
   - **Companies:** State, City, Country, Status dropdowns + keyword search
   - **Team Members:** Company, Title, Status dropdowns + keyword search
   - **Projects:** Client, Status, Billable dropdowns + keyword search
   - **Expenses:** Client, Project dropdowns + keyword search
   - All filters work together with AND logic
   - wpdb->prepare() used for SQL injection prevention
   - Searches across multiple relevant fields per table

3. **UI Improvements:**
   - "Filter" button with distinctive purple gradient styling
   - "Clear Filters" button appears when filters are active
   - Enter key triggers filter action from any input
   - Filters persist when sorting
   - Status badges with color coding (green for Active, red for Inactive)
   - Modern styled inputs and selects matching global design:
     - 2px solid borders with #e2e8f0 color
     - 6px border radius
     - Purple focus states (#667eea) with subtle shadow
     - Custom dropdown arrows matching theme
     - Consistent 8px-14px padding
   - Optimized spacing:
     - Modern header margin-bottom: 20px (reduced from 30px)
     - Tablenav padding: 4px (reduced from 16px)
     - Removed margins on wp-list-table
   - Filter controls positioned below "Add New Company" button
   - No gaps between table and navigation elements

4. **Added/Updated Columns:**
   - **Companies:** Added Country and Status columns with badges
   - **Team Members:** Added Status column with badges
   - **Projects:** Updated Status and Billable columns with color-coded badges
   - **Expenses:** Updated to show Client and Project names via JOIN queries

5. **Default Behavior:**
   - All items display by default (no filters applied)
   - Default sort varies by table (Companies/Team Members/Projects: Name ASC, Expenses: Date DESC)
   - Empty filter values properly handled with array_merge defaults
   - Proper table names using TIMEGROW_PREFIX constant

6. **Status Badges:**
   - Green badge for Active status
   - Red badge for Inactive status
   - Blue badge for Billable (Yes)
   - Yellow badge for Non-Billable (No)
   - Consistent badge styling across all tables

7. **Bug Fixes:**
   - Fixed table name from incorrect 'timegrow_companies' to correct table using TIMEGROW_PREFIX constant
   - Fixed spacing issues between header and content
   - Fixed large gaps in tablenav areas
   - Fixed JOIN queries to properly display related data (client names, project names, company names)

## Benefits
1. **Consistency** - All pages use the same design language
2. **Maintainability** - Single source of truth for styles
3. **File Size** - Reduce CSS duplication across plugins
4. **Scalability** - Easy to add new pages with consistent styling
5. **Form Standardization** - All form/list pages now have consistent modern headers and styling
6. **Advanced Table Features** - Sortable columns, multi-filter system, keyword search
