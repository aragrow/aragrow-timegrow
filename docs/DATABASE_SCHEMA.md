# TimeGrow Database Schema Documentation

**Plugin:** Aragrow - TimeGrow
**Version:** 1.1.1
**Last Updated:** 2026-02-12

---

## Table of Contents

1. [Overview](#overview)
2. [Database Tables](#database-tables)
   - [time_entry_tracker](#1-time_entry_tracker)
   - [expense_tracker](#2-expense_tracker)
   - [project_tracker](#3-project_tracker)
   - [team_member_tracker](#4-team_member_tracker)
   - [expense_receipt_tracker](#5-expense_receipt_tracker)
   - [company_tracker](#6-company_tracker)
3. [WordPress Tables Usage](#wordpress-tables-usage)
4. [Relationships Diagram](#relationships-diagram)
5. [Custom Capabilities](#custom-capabilities)

---

## Overview

The TimeGrow plugin uses a relational database structure with custom tables prefixed with `wp_timegrow_`. The schema supports:

- **Time Tracking:** Manual and clock-based time entries
- **Expense Management:** Receipt tracking with AI-powered analysis
- **Project Management:** Client projects with billing integration
- **Team Management:** Team member profiles and assignments
- **WooCommerce Integration:** Links to products and orders for billing

**Table Prefix:** All custom tables use `wp_timegrow_` prefix (adjusts to site prefix)

---

## Database Tables

### 1. time_entry_tracker

**Table Name:** `wp_timegrow_time_entry_tracker`
**Description:** Stores all time entries (both manual and clock-based)

```sql
CREATE TABLE IF NOT EXISTS wp_timegrow_time_entry_tracker (
    ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    project_id bigint(20) unsigned NOT NULL,
    member_id bigint(20) unsigned NOT NULL,
    clock_in_date datetime,
    clock_out_date datetime,
    date datetime,
    hours decimal(5,2),
    billable tinyint(1),
    billed tinyint(1) DEFAULT 0,
    description text,
    entry_type varchar(10),
    billed_order_id bigint(20) unsigned DEFAULT NULL,
    created_at timestamp,
    updated_at timestamp,
    PRIMARY KEY (ID),
    FOREIGN KEY (project_id) REFERENCES wp_timegrow_project_tracker(ID),
    FOREIGN KEY (member_id) REFERENCES wp_timegrow_team_member_tracker(ID),
    FOREIGN KEY (billed_order_id) REFERENCES wp_wc_orders(ID)
);
```

#### Columns

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `ID` | bigint(20) unsigned | NO | AUTO_INCREMENT | Primary key |
| `project_id` | bigint(20) unsigned | NO | - | FK to project_tracker |
| `member_id` | bigint(20) unsigned | NO | - | FK to team_member_tracker |
| `clock_in_date` | datetime | YES | NULL | Clock-in timestamp |
| `clock_out_date` | datetime | YES | NULL | Clock-out timestamp |
| `date` | datetime | YES | NULL | Manual entry date |
| `hours` | decimal(5,2) | YES | NULL | Hours for manual entries |
| `billable` | tinyint(1) | YES | NULL | Is entry billable? (0/1) |
| `billed` | tinyint(1) | NO | 0 | Has been billed? (0/1) |
| `description` | text | YES | NULL | Entry description |
| `entry_type` | varchar(10) | YES | NULL | 'CLOCK', 'MAN', 'IN' |
| `billed_order_id` | bigint(20) unsigned | YES | NULL | FK to WooCommerce order |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update timestamp |

#### Entry Types

- **CLOCK**: Clock-based entry (uses `clock_in_date` and `clock_out_date`)
- **MAN**: Manual entry (uses `date` and `hours`)
- **IN**: Active clock-in (no clock-out yet)

#### Important Notes

- For clock entries: Hours calculated as `TIMESTAMPDIFF(SECOND, clock_in_date, clock_out_date) / 3600`
- For manual entries: Hours stored directly in `hours` column
- `billable` = 1 means entry can be billed to client
- `billed` = 1 means entry has been invoiced via WooCommerce

---

### 2. expense_tracker

**Table Name:** `wp_timegrow_expense_tracker`
**Description:** Stores all expense records

```sql
CREATE TABLE IF NOT EXISTS wp_timegrow_expense_tracker (
    ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_name VARCHAR(255) NOT NULL,
    expense_description text NOT NULL,
    expense_date date NOT NULL,
    expense_payment_method ENUM('personal_card', 'company_card', 'bank_transfer', 'cash', 'other') NOT NULL DEFAULT 'company_card',
    amount DECIMAL(10,2) NOT NULL,
    category VARCHAR(255) NOT NULL,
    assigned_to ENUM('project', 'client', 'general') NOT NULL,
    assigned_to_id mediumint(9) NULL,
    created_at timestamp,
    updated_at timestamp,
    PRIMARY KEY (id)
);
```

#### Columns

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `ID` | BIGINT(20) unsigned | NO | AUTO_INCREMENT | Primary key |
| `expense_name` | VARCHAR(255) | NO | - | Expense name/title |
| `expense_description` | text | NO | - | Detailed description |
| `expense_date` | date | NO | - | Date of expense |
| `expense_payment_method` | ENUM | NO | 'company_card' | Payment method used |
| `amount` | DECIMAL(10,2) | NO | - | Expense amount (currency) |
| `category` | VARCHAR(255) | NO | - | Expense category |
| `assigned_to` | ENUM | NO | - | Assignment type |
| `assigned_to_id` | mediumint(9) | YES | NULL | ID of assigned entity |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update timestamp |

#### Payment Methods (ENUM)

- `personal_card`
- `company_card` (default)
- `bank_transfer`
- `cash`
- `other`

#### Assignment Types (ENUM)

- `project`: Assigned to a specific project (`assigned_to_id` = project ID)
- `client`: Assigned to a specific client (`assigned_to_id` = client/user ID)
- `general`: General expense (not assigned to project/client)

---

### 3. project_tracker

**Table Name:** `wp_timegrow_project_tracker`
**Description:** Stores all client projects

```sql
CREATE TABLE IF NOT EXISTS wp_timegrow_project_tracker (
    ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    client_id bigint(20) unsigned NOT NULL,
    product_id bigint(20) unsigned NULL,
    name varchar(255) NOT NULL,
    description text,
    status varchar(50) DEFAULT 'active',
    default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
    start_date date,
    end_date date,
    billable smallint(1) NOT NULL DEFAULT 1,
    estimate_hours smallint(4) NULL,
    created_by bigint(20) unsigned,
    created_at timestamp,
    updated_at timestamp,
    PRIMARY KEY (ID),
    FOREIGN KEY (client_id) REFERENCES wp_users(ID),
    FOREIGN KEY (product_id) REFERENCES wp_posts(ID),
    FOREIGN KEY (created_by) REFERENCES wp_users(ID)
);
```

#### Columns

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `ID` | bigint(20) unsigned | NO | AUTO_INCREMENT | Primary key |
| `client_id` | bigint(20) unsigned | NO | - | FK to wp_users (client) |
| `product_id` | bigint(20) unsigned | YES | NULL | FK to wp_posts (WooCommerce product) |
| `name` | varchar(255) | NO | - | Project name |
| `description` | text | YES | NULL | Project description |
| `status` | varchar(50) | YES | 'active' | Project status |
| `default_flat_fee` | DECIMAL(10,2) | YES | 0.00 | Default billing amount |
| `start_date` | date | YES | NULL | Project start date |
| `end_date` | date | YES | NULL | Project end date |
| `billable` | smallint(1) | NO | 1 | Is project billable? (0/1) |
| `estimate_hours` | smallint(4) | YES | NULL | Estimated hours for project |
| `created_by` | bigint(20) unsigned | YES | NULL | FK to wp_users (creator) |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update timestamp |

#### WooCommerce Integration

- `product_id` links to WooCommerce products table (`wp_posts` where `post_type = 'product'`)
- Enables automatic invoice generation from time entries

---

### 4. team_member_tracker

**Table Name:** `wp_timegrow_team_member_tracker`
**Description:** Stores team member profiles

```sql
CREATE TABLE IF NOT EXISTS wp_timegrow_team_member_tracker (
    ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL UNIQUE,
    company_id bigint(20) unsigned NOT NULL,
    name varchar(25) NOT NULL,
    email varchar(255),
    phone varchar(20),
    title varchar(255),
    bio text,
    status smallint(1) NOT NULL DEFAULT 1,
    created_at timestamp,
    updated_at timestamp,
    PRIMARY KEY (ID),
    FOREIGN KEY (company_id) REFERENCES wp_timegrow_company_tracker(ID),
    FOREIGN KEY (user_id) REFERENCES wp_users(ID)
);
```

#### Columns

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `ID` | bigint(20) unsigned | NO | AUTO_INCREMENT | Primary key |
| `user_id` | bigint(20) unsigned | NO | - | FK to wp_users (UNIQUE) |
| `company_id` | bigint(20) unsigned | NO | - | FK to company_tracker |
| `name` | varchar(25) | NO | - | Team member name |
| `email` | varchar(255) | YES | NULL | Email address |
| `phone` | varchar(20) | YES | NULL | Phone number |
| `title` | varchar(255) | YES | NULL | Job title |
| `bio` | text | YES | NULL | Biography/description |
| `status` | smallint(1) | NO | 1 | Active status (0/1) |
| `created_at` | timestamp | YES | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | timestamp | YES | CURRENT_TIMESTAMP | Last update timestamp |

#### Constraints

- **UNIQUE** constraint on `user_id` - one team member record per WordPress user
- Each team member must belong to a company

---

### 5. expense_receipt_tracker

**Table Name:** `wp_timegrow_expense_receipt_tracker`
**Description:** Stores uploaded receipts and AI analysis data

```sql
CREATE TABLE IF NOT EXISTS wp_timegrow_expense_receipt_tracker (
    ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    expense_id BIGINT(20) UNSIGNED NOT NULL,
    file_url TEXT NOT NULL,
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    extracted_data LONGTEXT,
    gemini_confidence DECIMAL(3,2),
    analyzed_at DATETIME,
    PRIMARY KEY (id),
    FOREIGN KEY (expense_id) REFERENCES wp_timegrow_expense_tracker(id) ON DELETE CASCADE
);
```

#### Columns

| Column | Type | Null | Default | Description |
|--------|------|------|---------|-------------|
| `ID` | BIGINT(20) unsigned | NO | AUTO_INCREMENT | Primary key |
| `expense_id` | BIGINT(20) unsigned | NO | - | FK to expense_tracker |
| `file_url` | TEXT | NO | - | URL to receipt file |
| `upload_date` | DATETIME | YES | CURRENT_TIMESTAMP | Upload timestamp |
| `extracted_data` | LONGTEXT | YES | NULL | Gemini AI JSON response |
| `gemini_confidence` | DECIMAL(3,2) | YES | NULL | AI confidence (0.00-1.00) |
| `analyzed_at` | DATETIME | YES | NULL | AI analysis timestamp |

#### AI Integration Features

- **extracted_data**: Stores JSON response from Google Gemini Vision API
- **gemini_confidence**: Confidence score from 0.00 to 1.00
- **Cascade Delete**: When expense is deleted, receipts are automatically removed

---

### 6. company_tracker

**Table Name:** `wp_timegrow_company_tracker`
**Description:** Stores company information for team members

> **Note:** Schema details not fully extracted. This table stores basic company information that team members belong to.

---

## WordPress Tables Usage

TimeGrow integrates with standard WordPress tables:

### wp_users

**Usage:** Stores client and user information

**Key Fields Used:**
- `ID` - Primary key, referenced by projects and team members
- `display_name` - Client/user display name
- `user_email` - User email address

**Client Query Pattern:**
```sql
SELECT a.*
FROM wp_users a
INNER JOIN wp_usermeta b
    ON a.ID = b.user_id
    AND b.meta_key = 'wp_capabilities'
    AND b.meta_value LIKE '%customer%'
ORDER BY a.display_name
```

### wp_posts

**Usage:** WooCommerce products table

**Referenced By:** `project_tracker.product_id`

### wp_wc_orders

**Usage:** WooCommerce orders table

**Referenced By:** `time_entry_tracker.billed_order_id`

---

## Relationships Diagram

```
┌─────────────────────────┐
│   wp_users              │
│   (WordPress Users)     │◄─────────┐
└────────┬────────────────┘          │
         │                            │
         │ client_id                  │ user_id (UNIQUE)
         │                            │
         ▼                            │
┌─────────────────────────┐          │
│ project_tracker         │          │
│ - Projects              │          │
└────────┬────────────────┘          │
         │                            │
         │ project_id                 │
         │                            │
         ▼                            │
┌─────────────────────────┐          │
│ time_entry_tracker      │          │
│ - Time Entries          │◄─────────┤
└─────────────────────────┘          │
         │                            │
         │ member_id                  │
         │                            │
         ▼                            │
┌─────────────────────────┐          │
│ team_member_tracker     │──────────┘
│ - Team Members          │
└─────────────────────────┘
         │
         │ company_id
         │
         ▼
┌─────────────────────────┐
│ company_tracker         │
│ - Companies             │
└─────────────────────────┘


┌─────────────────────────┐
│ expense_tracker         │
│ - Expenses              │
└────────┬────────────────┘
         │
         │ expense_id (CASCADE DELETE)
         │
         ▼
┌─────────────────────────┐
│ expense_receipt_tracker │
│ - Receipt Files & AI    │
└─────────────────────────┘
```

### Key Relationships

1. **Time Entries → Projects → Clients**
   - Time entries belong to projects
   - Projects belong to clients (WordPress users)

2. **Time Entries → Team Members → Users**
   - Time entries logged by team members
   - Team members linked to WordPress users (1:1)

3. **Expenses → Projects/Clients**
   - Flexible assignment via `assigned_to` ENUM
   - Can be assigned to project, client, or marked as general

4. **Receipts → Expenses**
   - Multiple receipts per expense
   - Cascade delete when expense removed

5. **Projects → WooCommerce**
   - Projects can link to products
   - Time entries can link to billed orders

---

## Custom Capabilities

TimeGrow uses custom WordPress capabilities for granular permission control.

### Report Capabilities

#### Admin-Only Capabilities
- `view_team_summary_hours` - Team Hours Summary report
- `view_project_profitability` - Project Financials report
- `view_client_activity_summary` - Client Activity Summary report
- `view_all_expenses_overview` - All Expenses Overview report
- `view_time_entry_audit_log` - Time Entry Audit Log report

#### Team Member Capabilities
- `view_yearly_tax_report` - Yearly Tax Report (individual)
- `view_my_time_entries_detailed` - My Detailed Time Log
- `view_my_hours_by_project` - My Hours by Project
- `view_my_expenses_report` - My Expenses Report

### Capability Assignment

**Administrator Role:**
- Has all admin-only AND team member capabilities

**Team Member Role:**
- Has only team member capabilities (personal reports)

### Checking Capabilities in Code

```php
// Check if user can view a specific report
if (current_user_can('view_client_activity_summary')) {
    // Show Client Activity Summary
}

// Check capability from report definition
if (user_can($user, $report['capability'])) {
    // User has access to this report
}
```

---

## Common Query Patterns

### Get Time Entries with Calculated Hours

```sql
SELECT
    te.*,
    p.name as project_name,
    m.name as member_name,
    COALESCE(te.date, DATE(te.clock_in_date)) as entry_date,
    CASE
        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
        ELSE 0
    END as calculated_hours
FROM wp_timegrow_time_entry_tracker te
INNER JOIN wp_timegrow_project_tracker p ON te.project_id = p.ID
INNER JOIN wp_timegrow_team_member_tracker m ON te.member_id = m.ID
WHERE YEAR(COALESCE(te.date, te.clock_in_date)) = 2026
ORDER BY entry_date DESC;
```

### Get Client Activity Summary

```sql
SELECT
    c.ID as client_id,
    c.display_name as client_name,
    COUNT(DISTINCT p.ID) as project_count,
    SUM(CASE
        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
        ELSE 0
    END) as total_hours,
    (SELECT COALESCE(SUM(e.amount), 0)
     FROM wp_timegrow_expense_tracker e
     INNER JOIN wp_timegrow_project_tracker p2 ON e.assigned_to = 'project' AND e.assigned_to_id = p2.ID
     WHERE p2.client_id = c.ID) as total_expenses
FROM wp_users c
INNER JOIN wp_timegrow_project_tracker p ON c.ID = p.client_id
LEFT JOIN wp_timegrow_time_entry_tracker te ON p.ID = te.project_id
GROUP BY c.ID, c.display_name
HAVING total_hours > 0 OR total_expenses > 0;
```

---

## File References

**Model Files Location:**
```
/wp-content/plugins/aragrow-timegrow/includes/models/
├── TimeGrowTimeEntryModel.php       (time_entry_tracker)
├── TimeGrowExpenseModel.php         (expense_tracker)
├── TimeGrowProjectModel.php         (project_tracker)
├── TimeGrowTeamMemberModel.php      (team_member_tracker)
├── TimeGrowExpenseReceiptModel.php  (expense_receipt_tracker)
└── TimeGrowClientModel.php          (uses wp_users)
```

**Main Plugin File:**
```
/wp-content/plugins/aragrow-timegrow/aragrow-timegrow.php
```

---

## Notes & Best Practices

1. **Always use COALESCE for dates:**
   ```sql
   COALESCE(te.date, te.clock_in_date)
   ```
   This handles both manual and clock entries correctly.

2. **Calculate hours dynamically:**
   - Don't rely solely on the `hours` column
   - Use CASE statement to calculate from timestamps when needed

3. **Check entry types:**
   - Clock entries: `clock_in_date` and `clock_out_date` populated
   - Manual entries: `date` and `hours` populated

4. **Expense assignment:**
   - Always check `assigned_to` before using `assigned_to_id`
   - Join appropriately based on assignment type

5. **Capability checks:**
   - Use `current_user_can($capability)` before displaying reports
   - Check capabilities in both PHP and SQL queries

---

**Document Version:** 1.0
**Last Updated:** 2026-02-12
**Maintained By:** ARAGROW, LLC
