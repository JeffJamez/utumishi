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
define('DB_USER', '');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_TIMEOUT', 86400);
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

define('MAX_FILE_SIZE', 5242880);
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png']);
define('EVIDENCE_MAX_FILES_PER_CASE', 10);

define('ROLE_ADMIN', 'county_commander');
define('ROLE_COUNTY_COMMANDER', 'county_commander');
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
    'Mombasa' => ['Mvita', 'Changamwe', 'Jomba', 'Kisauni', 'Nyali', 'Likoni'],
    'Kwale' => ['Msambweni', 'Lunga Lunga', 'Matuga', 'Kinango'],
    'Kilifi' => ['Kilifi North', 'Kilifi South', 'Kaloleni', 'Rabai', 'Ganze', 'Malindi', 'Magarini'],
    'Tana River' => ['Bura', 'Galole', 'Garsen'],
    'Lamu' => ['Lamu East', 'Lamu West'],
    'Taita-Taveta' => ['Taveta', 'Wundanyi', 'Mwatate', 'Voi'],
    'Garissa' => ['Garissa Township', 'Fafi', 'Ijara', 'Dadaab', 'Balambala', 'Lagdera'],
    'Wajir' => ['Wajir North', 'Wajir East', 'Tarbaj', 'Wajir West', 'Eldas', 'Wajir South'],
    'Mandera' => ['Mandera West', 'Banisa', 'Mandera North', 'Mandera Central', 'Mandera East', 'Lafey'],
    'Marsabit' => ['Moyale', 'North Horr', 'Saku', 'Laisamis'],
    'Isiolo' => ['Isiolo North', 'Isiolo South'],
    'Meru' => ['Igembe Central', 'Igembe North', 'Igembe South', 'Tigania East', 'Tigania West', 'North Imenti', 'South Imenti', 'Central Imenti'],
    'Tharaka-Nithi' => ['Tharaka', 'Chuka', 'Igambang\'ombe', 'Maara'],
    'Embu' => ['Manyatta', 'Runyenjes', 'Mbeere North', 'Mbeere South'],
    'Kitui' => ['Kitui West', 'Kitui Rural', 'Kitui Central', 'Kitui East', 'Kitui South'],
    'Machakos' => ['Machakos Town', 'Mavoko', 'Masinga', 'Yatta', 'Kangundo', 'Matungulu'],
    'Makueni' => ['Makueni', 'Kilome', 'Kaiti', 'Mbooni'],
    'Nyandarua' => ['Kinangop', 'Kipipiri', 'Ol Kalou', 'Ol Jorok', 'Ndaragwa'],
    'Nyeri' => ['Tetu', 'Kieni', 'Mathira', 'Othaya', 'Mukurweini', 'Nyeri Town'],
    'Kirinyaga' => ['Mwea', 'Gichugu', 'Ndia', 'Kirinyaga Central'],
    'Murang\'a' => ['Kangema', 'Mathioya', 'Kiharu', 'Kigumo', 'Maragwa', 'Kandara', 'Gatanga'],
    'Kiambu' => ['Kiambu Town', 'Thika Town', 'Ruiru', 'Githunguri', 'Kiambaa', 'Limuru', 'Kabete', 'Kikuyu', 'Lari'],
    'Turkana' => ['Turkana North', 'Turkana West', 'Turkana Central', 'Turkana South', 'Turkana East', 'Loima'],
    'West Pokot' => ['Kapenguria', 'Sigor', 'Kacheliba', 'Pokot South'],
    'Samburu' => ['Samburu West', 'Samburu North', 'Samburu East'],
    'Trans Nzoia' => ['Kwanza', 'Endebess', 'Saboti', 'Kiminini', 'Cherangany'],
    'Uasin Gishu' => ['Eldoret East', 'Eldoret West', 'Turbo', 'Moiben', 'Ainabkoi', 'Kesses', 'Kapseret'],
    'Elgeyo-Marakwet' => ['Marakwet East', 'Marakwet West', 'Keiyo North', 'Keiyo South'],
    'Nandi' => ['Tinderet', 'Aldai', 'Nandi Hills', 'Chesumei', 'Emgwen', 'Mosop'],
    'Baringo' => ['Tiaty', 'Baringo East', 'Baringo West', 'Mogotio', 'Eldama Ravine', 'Baringo Central'],
    'Laikipia' => ['Laikipia West', 'Laikipia East', 'Laikipia North'],
    'Nakuru' => ['Nakuru Town East', 'Nakuru Town West', 'Gilgil', 'Naivasha', 'Molo', 'Njoro', 'Kuresoi North', 'Kuresoi South', 'Subukia', 'Rongai', 'Bahati'],
    'Narok' => ['Kilgoris', 'Emurua Dikirr', 'Narok North', 'Narok East', 'Narok South', 'Narok West'],
    'Kajiado' => ['Kajiado East', 'Kajiado North', 'Kajiado Central', 'Kajiado West'],
    'Kericho' => ['Kipkelion East', 'Kipkelion West', 'Ainamoi', 'Bureti', 'Belgut', 'Sigowet/Soin'],
    'Bomet' => ['Sotik', 'Chepalungu', 'Bomet East', 'Bomet Central', 'Konoin'],
    'Kakamega' => ['Lugari', 'Likuyani', 'Malava', 'Lurambi', 'Navakholo', 'Mumias East', 'Mumias West', 'Matungu', 'Butere', 'Khwisero', 'Shinyalu', 'Ikolomani'],
    'Vihiga' => ['Vihiga', 'Sabatia', 'Hamisi', 'Luanda', 'Emuhaya'],
    'Bungoma' => ['Mount Elgon', 'Sirisia', 'Kabuchai', 'Bumula', 'Kanduyi', 'Webuye East', 'Webuye West', 'Kimilili', 'Tongaren'],
    'Busia' => ['Teso North', 'Teso South', 'Nambale', 'Matayos', 'Butula', 'Funyula', 'Budalangi'],
    'Siaya' => ['Ugenya', 'Ugunja', 'Alego Usonga', 'Gem', 'Bondo', 'Rarieda'],
    'Kisumu' => ['Kisumu East', 'Kisumu West', 'Kisumu Central', 'Muhoroni', 'Nyando', 'Nyakach', 'Seme', 'Kisumu Rural'],
    'Homa Bay' => ['Homa Bay Town', 'Ndhiwa', 'Rachuonyo North', 'Rachuonyo East', 'Rachuonyo South', 'Suba North', 'Suba South', 'Mbita'],
    'Migori' => ['Rongo', 'Awendo', 'Suna East', 'Suna West', 'Uriri', 'Nyatike', 'Kuria East', 'Kuria West'],
    'Kisii' => ['Bonchari', 'South Mugirango', 'Bomachoge Borabu', 'Bobasi', 'Bomachoge Chache', 'Nyaribari Masaba', 'Nyaribari Chache', 'Kitutu Chache North', 'Kitutu Chache South'],
    'Nyamira' => ['Kitutu Masaba', 'North Mugirango', 'West Mugirango', 'North Mugirango', 'Borabu'],
    'Nairobi' => ['Starehe', 'Westlands', 'Langata', 'Kasarani', 'Embakasi', 'Kamukunji', 'Mathare', 'Dagoretti', 'Roysambu', 'Ruaraka', 'Kibra']
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
