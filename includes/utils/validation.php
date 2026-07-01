<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class Validator {

    public static function validateNationalId($nationalId) {
        $nationalId = trim($nationalId);

        if (empty($nationalId)) {
            return ['valid' => false, 'message' => 'National ID is required'];
        }

        if (!preg_match('/^\d{8}$/', $nationalId)) {
            return ['valid' => false, 'message' => 'National ID must be exactly 8 digits'];
        }

        return ['valid' => true, 'message' => 'Valid National ID'];
    }

    public static function validatePhone($phone) {
        $phone = trim($phone);

        if (empty($phone)) {
            return ['valid' => false, 'message' => 'Phone number is required'];
        }

        $phone = preg_replace('/[\s\-]/', '', $phone);

        $patterns = [
            '/^\+254[17]\d{8}$/',
            '/^254[17]\d{8}$/',
            '/^0[17]\d{8}$/',
        ];

        $isValid = false;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $phone)) {
                $isValid = true;
                break;
            }
        }

        if (!$isValid) {
            return ['valid' => false, 'message' => 'Invalid phone number format. Use +254XXXXXXXXX or 07XXXXXXXX'];
        }

        return ['valid' => true, 'message' => 'Valid phone number'];
    }

    public static function validateEmail($email) {
        if (empty($email)) {
            return ['valid' => true, 'message' => 'Email is optional'];
        }

        $email = trim($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }

        return ['valid' => true, 'message' => 'Valid email'];
    }

    public static function validatePassword($password) {
        if (empty($password)) {
            return ['valid' => false, 'message' => 'Password is required'];
        }

        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['valid' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain at least one letter and one number'];
        }

        return ['valid' => true, 'message' => 'Valid password'];
    }

    public static function validateName($name) {
        $name = trim($name);

        if (empty($name)) {
            return ['valid' => false, 'message' => 'Name is required'];
        }

        if (strlen($name) < 2) {
            return ['valid' => false, 'message' => 'Name must be at least 2 characters'];
        }

        if (strlen($name) > 100) {
            return ['valid' => false, 'message' => 'Name must not exceed 100 characters'];
        }

        if (!preg_match('/^[A-Za-z\s\-\'\.]+$/', $name)) {
            return ['valid' => false, 'message' => 'Name can only contain letters, spaces, hyphens, apostrophes, and periods'];
        }

        return ['valid' => true, 'message' => 'Valid name'];
    }

    public static function validateOBNumber($obNumber) {
        $obNumber = trim(strtoupper($obNumber));

        if (empty($obNumber)) {
            return ['valid' => false, 'message' => 'OB Number is required'];
        }

        if (!preg_match('/^OB-[A-Z0-9]+-\d{4}-\d{5}$/', $obNumber)) {
            return ['valid' => false, 'message' => 'Invalid OB Number format. Expected: OB-XXXX-YYYY-NNNNN'];
        }

        return ['valid' => true, 'message' => 'Valid OB Number'];
    }

    public static function validateCrimeCategory($category) {
        if (empty($category)) {
            return ['valid' => false, 'message' => 'Crime category is required'];
        }

        $validCategories = array_keys(CRIME_CATEGORIES);

        if (!in_array($category, $validCategories)) {
            return ['valid' => false, 'message' => 'Invalid crime category'];
        }

        return ['valid' => true, 'message' => 'Valid crime category'];
    }

    public static function validateLocation($county, $constituency) {
        if (empty($county)) {
            return ['valid' => false, 'message' => 'County is required'];
        }

        if (empty($constituency)) {
            return ['valid' => false, 'message' => 'Constituency is required'];
        }

        $counties = KENYAN_COUNTIES;

        if (!array_key_exists($county, $counties)) {
            return ['valid' => false, 'message' => 'Invalid county'];
        }

        if (!in_array($constituency, $counties[$county])) {
            return ['valid' => false, 'message' => 'Invalid constituency for the selected county'];
        }

        return ['valid' => true, 'message' => 'Valid location'];
    }

    public static function validateRole($role) {
        $validRoles = [ROLE_ADMIN, ROLE_OCS, ROLE_OFFICER, ROLE_CITIZEN];

        if (empty($role)) {
            return ['valid' => false, 'message' => 'Role is required'];
        }

        if (!in_array($role, $validRoles)) {
            return ['valid' => false, 'message' => 'Invalid role'];
        }

        return ['valid' => true, 'message' => 'Valid role'];
    }

    public static function validateCaseStatus($status) {
        $validStatuses = [CASE_REPORTED, CASE_ASSIGNED, CASE_IN_PROGRESS, CASE_RESOLVED, CASE_CLOSED];

        if (empty($status)) {
            return ['valid' => false, 'message' => 'Status is required'];
        }

        if (!in_array($status, $validStatuses)) {
            return ['valid' => false, 'message' => 'Invalid case status'];
        }

        return ['valid' => true, 'message' => 'Valid case status'];
    }

    public static function validateBadgeNumber($badgeNumber) {
        $badgeNumber = trim(strtoupper($badgeNumber));

        if (empty($badgeNumber)) {
            return ['valid' => false, 'message' => 'Badge number is required'];
        }

        if (!preg_match('/^KPS-\d{4}$/', $badgeNumber)) {
            return ['valid' => false, 'message' => 'Invalid badge number format. Expected: KPS-NNNN'];
        }

        return ['valid' => true, 'message' => 'Valid badge number'];
    }

    public static function validateText($text, $fieldName, $minLength = 1, $maxLength = 500) {
        $text = trim($text);

        if (empty($text)) {
            return ['valid' => false, 'message' => $fieldName . ' is required'];
        }

        if (strlen($text) < $minLength) {
            return ['valid' => false, 'message' => $fieldName . ' must be at least ' . $minLength . ' characters'];
        }

        if (strlen($text) > $maxLength) {
            return ['valid' => false, 'message' => $fieldName . ' must not exceed ' . $maxLength . ' characters'];
        }

        return ['valid' => true, 'message' => 'Valid ' . strtolower($fieldName)];
    }

    public static function validateNumber($number, $fieldName, $min = null, $max = null) {
        if (empty($number) && $number !== '0' && $number !== 0) {
            return ['valid' => false, 'message' => $fieldName . ' is required'];
        }

        if (!is_numeric($number)) {
            return ['valid' => false, 'message' => $fieldName . ' must be a valid number'];
        }

        $number = (float)$number;

        if ($min !== null && $number < $min) {
            return ['valid' => false, 'message' => $fieldName . ' must be at least ' . $min];
        }

        if ($max !== null && $number > $max) {
            return ['valid' => false, 'message' => $fieldName . ' must not exceed ' . $max];
        }

        return ['valid' => true, 'message' => 'Valid ' . strtolower($fieldName)];
    }

    public static function validateDate($date, $fieldName, $format = 'Y-m-d') {
        if (empty($date)) {
            return ['valid' => false, 'message' => $fieldName . ' is required'];
        }

        $dateObj = DateTime::createFromFormat($format, $date);

        if (!$dateObj || $dateObj->format($format) !== $date) {
            return ['valid' => false, 'message' => 'Invalid ' . strtolower($fieldName) . ' format'];
        }

        return ['valid' => true, 'message' => 'Valid ' . strtolower($fieldName)];
    }

    public static function validateDateTime($datetime, $fieldName, $allowFuture = false) {
        if (empty($datetime)) {
            return ['valid' => false, 'message' => $fieldName . ' is required'];
        }

        $dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        
        if (!$dateObj) {
            $dateObj = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
        }

        if (!$dateObj) {
            return ['valid' => false, 'message' => 'Invalid ' . strtolower($fieldName) . ' format. Use format: Jan 15, 2026 at 3:30 PM'];
        }

        if (!$allowFuture) {
            $now = new DateTime();
            if ($dateObj > $now) {
                return ['valid' => false, 'message' => $fieldName . ' cannot be in the future'];
            }
        }

        return ['valid' => true, 'message' => 'Valid ' . strtolower($fieldName), 'datetime' => $dateObj];
    }

    public static function validateFile($file, $fieldName = 'File') {
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valid' => false, 'message' => $fieldName . ' is required'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds maximum upload size',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];

            $message = $errors[$file['error']] ?? 'Unknown upload error';
            return ['valid' => false, 'message' => $message];
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            return ['valid' => false, 'message' => 'File size exceeds maximum allowed (' . round(MAX_FILE_SIZE/1024/1024, 1) . 'MB)'];
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
            return ['valid' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', ALLOWED_FILE_TYPES)];
        }

        return ['valid' => true, 'message' => 'Valid file'];
    }

    public static function validateFields($data, $rules) {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? '';

            foreach ($fieldRules as $rule => $params) {
                switch ($rule) {
                    case 'required':
                        if (empty($value) && $value !== '0') {
                            $errors[$field] = ucfirst($field) . ' is required';
                        }
                        break;

                    case 'national_id':
                        $result = self::validateNationalId($value);
                        if (!$result['valid']) {
                            $errors[$field] = $result['message'];
                        }
                        break;

                    case 'phone':
                        $result = self::validatePhone($value);
                        if (!$result['valid']) {
                            $errors[$field] = $result['message'];
                        }
                        break;

                    case 'email':
                        $result = self::validateEmail($value);
                        if (!$result['valid']) {
                            $errors[$field] = $result['message'];
                        }
                        break;

                    case 'min_length':
                        if (strlen($value) < $params) {
                            $errors[$field] = ucfirst($field) . ' must be at least ' . $params . ' characters';
                        }
                        break;

                    case 'max_length':
                        if (strlen($value) > $params) {
                            $errors[$field] = ucfirst($field) . ' must not exceed ' . $params . ' characters';
                        }
                        break;
                }
            }
        }

        return empty($errors) ? ['valid' => true, 'errors' => []] : ['valid' => false, 'errors' => $errors];
    }
}

function validateNationalId($id) {
    return Validator::validateNationalId($id);
}

function validatePhone($phone) {
    return Validator::validatePhone($phone);
}

function validateEmail($email) {
    return Validator::validateEmail($email);
}

function validatePassword($password) {
    return Validator::validatePassword($password);
}

function validateName($name) {
    return Validator::validateName($name);
}

function validateOBNumber($obNumber) {
    return Validator::validateOBNumber($obNumber);
}

function validateCrimeCategory($category) {
    return Validator::validateCrimeCategory($category);
}

function validateLocation($county, $constituency) {
    return Validator::validateLocation($county, $constituency);
}

function validateCaseStatus($status) {
    return Validator::validateCaseStatus($status);
}
?>
