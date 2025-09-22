<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

define('APP_NAME', 'Utumishi');
define('APP_VERSION', '1.0.0');
define('APP_AUTHOR', 'Jeff James');

define('ROOT_PATH', dirname(dirname(__DIR__)));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('PAGES_PATH', ROOT_PATH . '/pages');
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ROOT_PATH . '/assets/uploads');
define('EVIDENCE_PATH', UPLOADS_PATH . '/case_evidence');

define('BASE_URL', 'http://localhost/utumishi');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/assets/uploads');

define('DB_HOST', 'localhost');
define('DB_NAME', 'utumishi');
define('DB_USER', 'jeff');
define('DB_PASS', 'webmaster');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_TIMEOUT', 1800);
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
define('EVIDENCE_MAX_FILES_PER_CASE', 10);

define('ROLE_ADMIN', 'admin');
define('ROLE_OCS', 'ocs');
define('ROLE_OFFICER', 'officer');
define('ROLE_CITIZEN', 'citizen');

define('CASE_REPORTED', 'reported');
define('CASE_ASSIGNED', 'assigned');
define('CASE_IN_PROGRESS', 'in_progress');
define('CASE_RESOLVED', 'resolved');
define('CASE_CLOSED', 'closed');

define('CRIME_CATEGORIES', [
    'Theft' => 'Theft and Robbery',
    'Assault' => 'Physical Assault',
    'Burglary' => 'Breaking and Entering',
    'Cybercrime' => 'Online and Digital Crimes',
    'Fraud' => 'Financial Fraud and Scams',
    'Domestic Violence' => 'Family and Domestic Disputes',
    'Traffic Offenses' => 'Road Traffic Violations',
    'Drug Offenses' => 'Narcotics and Substance Abuse',
    'Public Order' => 'Disturbance of Peace',
    'Sexual Offenses' => 'Sexual Assault and Harassment',
    'Property Damage' => 'Vandalism and Destruction',
    'Other' => 'Other Criminal Activities'
]);

define('DEFAULT_RESOLUTION_HOURS', [
    'Theft' => 72,
    'Assault' => 48,
    'Burglary' => 96,
    'Cybercrime' => 96,
    'Fraud' => 72,
    'Domestic Violence' => 24,
    'Traffic Offenses' => 24,
    'Drug Offenses' => 48,
    'Public Order' => 12,
    'Sexual Offenses' => 24,
    'Property Damage' => 48,
    'Other' => 72
]);

define('KENYAN_COUNTIES', [
    'Nairobi' => ['Starehe', 'Westlands', 'Langata', 'Kasarani', 'Embakasi', 'Kamukunji', 'Mathare', 'Dagoretti'],
    'Mombasa' => ['Mvita', 'Changamwe', 'Jomba', 'Kisauni', 'Nyali', 'Likoni'],
    'Kiambu' => ['Kiambu Town', 'Thika Town', 'Ruiru', 'Githunguri', 'Kiambaa', 'Limuru'],
    'Nakuru' => ['Nakuru Town East', 'Nakuru Town West', 'Gilgil', 'Naivasha', 'Molo', 'Njoro'],
    'Uasin Gishu' => ['Eldoret East', 'Eldoret West', 'Turbo', 'Moiben', 'Ainabkoi', 'Kesses'],
    'Machakos' => ['Machakos Town', 'Mavoko', 'Masinga', 'Yatta', 'Kangundo', 'Matungulu'],
    'Meru' => ['Igembe Central', 'Igembe North', 'Igembe South', 'Tigania East', 'Tigania West'],
    'Kisumu' => ['Kisumu East', 'Kisumu West', 'Kisumu Central', 'Muhoroni', 'Nyando', 'Nyakach'],
    'Kakamega' => ['Lugari', 'Likuyani', 'Malava', 'Lurambi', 'Navakholo', 'Mumias East']
]);

define('STATION_CODES', [
    'Nairobi Central Police Station' => 'NRB',
    'Langata Police Station' => 'LGT',
    'Kiambu Police Station' => 'KMB',
    'Mombasa Central Police Station' => 'MSA',
    'Eldoret Police Station' => 'ELD',
    'Nakuru Police Station' => 'NKR',
    'Kisumu Police Station' => 'KSM',
    'Machakos Police Station' => 'MCK',
    'Meru Police Station' => 'MRU',
    'Kakamega Police Station' => 'KKG'
]);

define('HIGH_CRIME_THRESHOLD', 50);
define('MAX_CASE_LOAD_PER_OFFICER', 15);
define('EXCELLENT_RESOLUTION_RATE', 85);
define('GOOD_RESOLUTION_RATE', 70);
define('POOR_RESOLUTION_RATE', 50);

define('MSG_LOGIN_SUCCESS', 'Login successful. Welcome to Utumishi.');
define('MSG_LOGIN_FAILED', 'Invalid credentials. Please try again.');
define('MSG_ACCESS_DENIED', 'Access denied. You do not have permission to view this page.');
define('MSG_SESSION_EXPIRED', 'Your session has expired. Please login again.');
define('MSG_CASE_CREATED', 'Case successfully recorded in the Digital OB System.');
define('MSG_CASE_UPDATED', 'Case information has been updated successfully.');
define('MSG_FILE_UPLOADED', 'Evidence file uploaded successfully.');
define('MSG_INVALID_FILE', 'Invalid file type or size. Please check file requirements.');

define('STATUS_COLORS', [
    CASE_REPORTED => 'status-reported',
    CASE_ASSIGNED => 'status-assigned',
    CASE_IN_PROGRESS => 'status-progress',
    CASE_RESOLVED => 'status-resolved',
    CASE_CLOSED => 'status-closed'
]);

define('PRIORITY_LOW', 'low');
define('PRIORITY_NORMAL', 'normal');
define('PRIORITY_HIGH', 'high');
define('PRIORITY_URGENT', 'urgent');

define('ERR_DB_CONNECTION', 'DB_001');
define('ERR_INVALID_INPUT', 'INPUT_001');
define('ERR_FILE_UPLOAD', 'FILE_001');
define('ERR_ACCESS_DENIED', 'AUTH_001');
define('ERR_SESSION_EXPIRED', 'AUTH_002');
define('ERR_INVALID_CREDENTIALS', 'AUTH_003');

define('SUCCESS_LOGIN', 'AUTH_100');
define('SUCCESS_LOGOUT', 'AUTH_101');
define('SUCCESS_CASE_CREATED', 'CASE_100');
define('SUCCESS_CASE_UPDATED', 'CASE_101');
define('SUCCESS_FILE_UPLOADED', 'FILE_100');

?>
