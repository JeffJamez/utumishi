# Utumishi - Kenya Police Service Digital Platform

Version 1.0.0 | Author: Jeff James

Utumishi is a web-based crime case management and predictive analytics platform for the Kenya Police Service. It provides role-based digital case recording, evidence management, workload balancing, reporting, and AI-driven crime forecasting.

---

## Table of Contents

- [Roles Overview](#roles-overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [Directory Structure](#directory-structure)
- [User Roles and Permissions](#user-roles-and-permissions)
- [Case Workflow](#case-workflow)
- [Security](#security)
- [License](#license)

---

## Roles Overview

| Role             | Identifier         | Description                                                                        |
| ---------------- | ------------------ | ---------------------------------------------------------------------------------- |
| County Commander | `county_commander` | National oversight across all stations within an assigned county                   |
| OCS              | `ocs`              | Station-level supervisor managing officers, workload, and station cases            |
| Officer          | `officer`          | Frontline officer recording cases, uploading evidence, and managing assigned cases |
| Citizen          | `citizen`          | Register cases, track reported cases, view public crime statistics                 |

---

## Features

### Case Management

- Digital Occurrence Book (OB) system with auto-generated OB numbers (format: `OB-{station_code}-{year}-{00000}`)
- Case lifecycle: reported, assigned, in_progress, resolved, closed
- Auto-assignment of cases to officers based on expertise and workload
- Case reassignment with reason tracking
- Search and filter cases by status, category, date range, county, and station
- GPS location capture via Google Places integration
- Reporter anonymization option
- 12 crime categories: Theft, Assault, Burglary, Cybercrime, Fraud, Domestic Violence, Traffic Offenses, Drug Offenses, Public Order, Sexual Offenses, Property Damage, Other

### Evidence Management

- Upload evidence files (PDF, JPG, JPEG, PNG) up to 5MB per file
- Maximum 10 evidence files per case
- MIME type validation on upload
- Secure file storage with directory-level .htaccess protection
- Evidence retrieval with access control per user role

### Crime Analysis and Prediction

- AI prediction engine with 7-day crime forecast
- Hotspot detection at constituency level with configurable timeframe and minimum case thresholds
- Hourly and weekly crime distribution analysis
- Risk score calculation based on day, hour, and location
- Cache-based predictions (24-hour TTL) stored in `includes/cache/predictions_cache.json`
- Predictive analytics with five components: crime_forecast, hotspot_predictions, risk_calendar, early_warnings, patrol_optimization
- Early warning system for crime spikes and resource shortages

### Reporting

- Monthly, annual, and performance reports
- County-level reports with station breakdown (via CountyReportsManager)
- Crime analysis reports with hotspot data and category trends
- Officer workload reports
- Station overview reports
- Report generation methods: generateMonthlyReport, generateAnnualReport, generatePerformanceReport, generateCrimeAnalysisReport, generateOfficerWorkloadReport, generateStationOverviewReport

### Workload Management

- Officer workload dashboard with status indicators (available, light, normal, high, overloaded)
- Thresholds: 0 cases = available, 1-5 = light, 6-10 = normal, 11-15 = high, >15 = overloaded
- Unassigned case tracking with urgency levels (normal, medium, high, critical)
- Expertise-based officer matching for specialized cases (Cybercrime, Domestic Violence, Drug Offenses)
- Auto-assignment of unassigned cases to optimal officers
- Officer performance ranking within stations

### County-Level Configuration

- All 47 Kenyan counties with their constituencies pre-configured
- 10 pre-configured police stations with station codes (NRB, LGT, KMB, MSA, ELD, NKR, KSM, MCK, MRU, KKG)
- Station-specific performance metrics and case trends
- County commander oversight restricted to their assigned county

---

## Technology Stack

| Component        | Technology                                           |
| ---------------- | ---------------------------------------------------- |
| Backend          | PHP 8.3+                                             |
| Database         | MySQL / MariaDB 10.11+                               |
| Frontend         | HTML5, CSS3, Vanilla JavaScript                      |
| Database Access  | PDO with prepared statements                         |
| Session Handling | PHP native sessions with timeout (24h default)       |
| Password Hashing | `password_hash()` with `PASSWORD_DEFAULT`            |
| Styling          | Custom CSS with CSS custom properties (no framework) |
| Charting         | Client-side JavaScript with inline SVG support       |
| MIME Validation  | `mime_content_type()` on upload                      |

---

## Installation

### Prerequisites

- PHP 8.3 or higher
- MySQL 8.0+ or MariaDB 10.11+
- Apache/Nginx web server
- `mod_rewrite` enabled (if using Apache)

### Setup Steps

1. Clone the repository into your web root:

```bash
git clone <repository-url> /var/www/html/utumishi
```

2. Create the MySQL database and import the schema:

```bash
mysql -u root -p < utumishi.sql
```

3. Configure database credentials in `includes/config/constants.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'utumishi');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');
```

4. Configure the base URL in `includes/config/constants.php`:

```php
define('BASE_URL', 'http://your-domain/utumishi');
```

5. Ensure the following directories are writable by the web server:
   - `assets/uploads/case_evidence/`
   - `includes/cache/`

6. Access the application at your configured BASE_URL.

### First-Time Setup

- The system auto-creates upload directories with `.htaccess` protection on first run (via `createUploadDirectories()` in db.php)
- Import the provided SQL dump to populate the database schema and sample data
- Login credentials are managed through the `users` table (password hashed with `password_hash()`)

---

## Configuration

Key configuration constants in `includes/config/constants.php`:

| Constant                      | Default | Description                                 |
| ----------------------------- | ------- | ------------------------------------------- |
| `SESSION_TIMEOUT`             | 86400   | Session timeout in seconds (24 hours)       |
| `PASSWORD_MIN_LENGTH`         | 8       | Minimum password length                     |
| `MAX_LOGIN_ATTEMPTS`          | 5       | Failed login attempts before lockout        |
| `LOGIN_LOCKOUT_TIME`          | 900     | Lockout duration in seconds (15 minutes)    |
| `MAX_FILE_SIZE`               | 5242880 | Maximum upload size in bytes (5MB)          |
| `EVIDENCE_MAX_FILES_PER_CASE` | 10      | Maximum evidence files per case             |
| `MAX_CASE_LOAD_PER_OFFICER`   | 15      | Maximum cases per officer before overloaded |
| `HIGH_CRIME_THRESHOLD`        | 50      | Threshold for high crime classification     |

Supported file types: `pdf`, `jpg`, `jpeg`, `png`.

---

## Database Setup

The initial database dump (`utumishi.sql`) includes:

- Complete table structure for `cases`, `users`, `officers`, `stations`, `case_evidence`, `case_updates`, `closure_requests`
- Sample case records with realistic Kenyan location data and GPS coordinates
- Pre-configured station data with station codes

### Key Tables

| Table              | Purpose                                                                                  |
| ------------------ | ---------------------------------------------------------------------------------------- |
| `users`            | All system users (all roles) with hashed passwords                                       |
| `officers`         | Officer-specific data (badge number, station assignment, workload, expertise categories) |
| `stations`         | Police station details with county, constituency, and station code                       |
| `cases`            | Case records with OB numbers, status, location, and assignment tracking                  |
| `case_evidence`    | Uploaded evidence file metadata                                                          |
| `case_updates`     | Audit trail of case status changes and officer actions                                   |
| `closure_requests` | Pending case closure approval requests                                                   |

---

## Directory Structure

```
utumishi/
  index.php                     - Entry point and role-based routing
  utumishi.sql                  - Database schema and sample data
  .gitignore
  assets/
    css/
      style.css                 - Main application styles (CSS custom properties)
      dashboard.css             - Dashboard-specific styles
    js/
      validation.js             - Client-side validation (national ID, phone, email)
    uploads/
      case_evidence/            - Evidence file storage (.htaccess protected)
  includes/
    cache/
      predictions_cache.json    - Cached AI prediction data (24h TTL)
    classes/
      AIPredictionEngine.php    - AI crime prediction engine with caching
      PredictiveAnalytics.php   - Predictive analytics and forecasting dashboard
      CrimeAnalyzer.php         - Crime pattern and hotspot analysis
      CaseManager.php           - Case CRUD, assignment, and OB number generation
      Officer.php               - Officer-specific operations (extends User)
      User.php                  - Base user class and profile management
      Station.php               - Station-level data and case queries
      WorkloadManager.php       - Workload tracking and case assignment logic
      ReportManager.php         - Multi-type report generation (monthly, annual, performance, etc.)
      CountyReportsManager.php  - County-level report generation with station breakdown
      AdminManager.php          - Commander-level officer and station management
    config/
      constants.php             - All application configuration constants
    core/
      db.php                    - Database singleton (PDO wrapper with helper methods)
      auth.php                  - Authentication, session management, CSRF protection
    layout/
      layout.php                - Main HTML layout template
      header.php                - Page header with user info and station display
      navigation.php            - Role-based navigation sidebar menus
      footer.php                - Page footer
    utils/
      sanitization.php          - Input sanitization (Sanitizer class with strip_tags + htmlspecialchars)
      validation.php            - Input validation (Validator class for national ID, phone, email, password)
      file_upload.php           - Evidence file upload handler with MIME validation
      ob_generator.php          - OB number generation (OB-{code}-{year}-{sequence})
      scope_validation.php      - Scope-based access control (station and county level)
  pages/
    auth/                       - Login, logout, password change, citizen registration
    citizen/                    - Citizen dashboard, case tracking, profile, public stats
    officer/                    - Officer dashboard, record/update/search cases, evidence, profile
    ocs/                        - OCS dashboard, station cases, workload, reports, analytics
    cc/                         - County Commander dashboard, manage officers/stations, reports
    shared/                     - AI predictions page and JavaScript
```

---

## User Roles and Permissions

### County Commander

- View county-wide dashboard with national statistics
- Manage all stations within assigned county
- Manage all officers within assigned county
- Generate county-level reports
- View AI crime predictions and predictive analysis
- Case search across county
- Access: dashboard, manage_officers, manage_stations, county_reports, case_search, predictive_analysis

### OCS (Officer Commanding Station)

- View station dashboard with performance metrics
- View and manage all station cases
- Assign and reassign cases to officers
- View officer workload and performance
- Generate station reports (monthly, performance, crime analysis, officer workload, station overview)
- Access AI predictions and predictive analysis
- Access: dashboard, station_cases, ai_predictions, predictive_analytics, officer_workload, reports

### Officer

- Record new cases in the Digital OB system
- View and update assigned cases
- Upload evidence to assigned or station cases
- Search for cases within station
- View personal workload and performance statistics
- Access crime prediction reports
- Update profile and change password
- Access: dashboard, record_case, my_cases, search_case, evidence, ai_predictions, profile

### Citizen

- Register for a citizen account (requires National ID - 8 digits)
- Report cases through the system
- Track reported cases by OB number
- View public crime statistics
- Reporter identity can be anonymized
- Access: dashboard, track_case

---

## Case Workflow

1. **Reported**: Case is created with an auto-generated OB number via OBGenerator
2. **Assigned**: Case is automatically assigned to the best-matched officer based on category expertise and current workload; or manually assigned by OCS
3. **In Progress**: Assigned officer updates case status to indicate active investigation
4. **Resolved**: Officer marks case as resolved, triggering a closure request to OCS
5. **Closed**: OCS approves closure; closure time is recorded and officer stats are updated

Case priority is determined by estimated_resolution_hours values per category (ranging from 12 hours for Public Order to 96 hours for Burglary and Cybercrime).

---

## Security

- All database queries use PDO prepared statements with parameterized queries
- CSRF protection via per-session tokens generated with `random_bytes(32)` and validated with `hash_equals()`
- Password hashing with `password_hash()` using `PASSWORD_DEFAULT`
- Input sanitization through Sanitizer class (strip_tags, htmlspecialchars with ENT_QUOTES)
- `.htaccess` files with `Deny from all` block direct directory access to upload directories
- Scope-based access control through ScopeValidation class (users restricted to their station or county)
- MIME type validation on file uploads via `mime_content_type()`
- Session timeout (24 hours default) with automatic logout on expiry
- Flash messaging system for user notifications
- Error messages suppressed for remote users (detailed errors shown only for localhost)
