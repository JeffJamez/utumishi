<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class Sanitizer {

    public static function sanitizeText($input) {
        if (is_null($input)) {
            return null;
        }

        $input = trim($input);
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        return $input;
    }

    public static function sanitizeEmail($email) {
        if (is_null($email)) {
            return null;
        }

        $email = trim($email);
        $email = strtolower($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);

        return $email;
    }

    public static function sanitizePhone($phone) {
        if (is_null($phone)) {
            return null;
        }

        $phone = trim($phone);

        $phone = preg_replace('/[^\d+]/', '', $phone);

        return $phone;
    }

    public static function sanitizeNationalId($nationalId) {
        if (is_null($nationalId)) {
            return null;
        }

        $nationalId = trim($nationalId);
        $nationalId = preg_replace('/\D/', '', $nationalId);

        return $nationalId;
    }

    public static function sanitizeName($name) {
        if (is_null($name)) {
            return null;
        }

        $name = trim($name);

        $name = preg_replace('/[^A-Za-zÀ-ÿ\s\-\'\.]/', '', $name);

        $name = preg_replace('/\s+/', ' ', $name);

        return $name;
    }

    public static function sanitizeDescription($description) {
        if (is_null($description)) {
            return null;
        }

        $description = trim($description);

        $description = strip_tags($description);

        $description = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        $description = preg_replace('/\r\n|\r|\n/', "\n", $description);

        return $description;
    }

    public static function sanitizeNumber($number, $allowFloat = false) {
        if (is_null($number) || $number === '') {
            return null;
        }

        $number = trim($number);

        if ($allowFloat) {

            $number = preg_replace('/[^\d\.\-]/', '', $number);
            return is_numeric($number) ? (float)$number : 0;
        } else {

            $number = preg_replace('/[^\d\-]/', '', $number);
            return is_numeric($number) ? (int)$number : 0;
        }
    }

    public static function sanitizeOBNumber($obNumber) {
        if (is_null($obNumber)) {
            return null;
        }

        $obNumber = trim(strtoupper($obNumber));

        $obNumber = preg_replace('/[^A-Z0-9\-]/', '', $obNumber);

        return $obNumber;
    }

    public static function sanitizeBadgeNumber($badgeNumber) {
        if (is_null($badgeNumber)) {
            return null;
        }

        $badgeNumber = trim(strtoupper($badgeNumber));

        $badgeNumber = preg_replace('/[^A-Z0-9\-]/', '', $badgeNumber);

        return $badgeNumber;
    }

    public static function sanitizeFilename($filename) {
        if (is_null($filename)) {
            return null;
        }

        $filename = trim($filename);

        $pathInfo = pathinfo($filename);
        $extension = isset($pathInfo['extension']) ? '.' . strtolower($pathInfo['extension']) : '';
        $basename = $pathInfo['filename'];

        $basename = preg_replace('/[^A-Za-z0-9\-_]/', '_', $basename);

        $basename = preg_replace('/_+/', '_', $basename);

        $basename = trim($basename, '_');

        if (empty($basename)) {
            $basename = 'file_' . date('YmdHis');
        }

        return $basename . $extension;
    }

    public static function sanitizeUrl($url) {
        if (is_null($url)) {
            return null;
        }

        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);

        return $url;
    }

    public static function sanitizeArray($array, $rules = []) {
        if (!is_array($array)) {
            return [];
        }

        $sanitized = [];

        foreach ($array as $key => $value) {
            $key = self::sanitizeText($key);

            if (isset($rules[$key])) {
                switch ($rules[$key]) {
                    case 'text':
                        $sanitized[$key] = self::sanitizeText($value);
                        break;
                    case 'email':
                        $sanitized[$key] = self::sanitizeEmail($value);
                        break;
                    case 'phone':
                        $sanitized[$key] = self::sanitizePhone($value);
                        break;
                    case 'name':
                        $sanitized[$key] = self::sanitizeName($value);
                        break;
                    case 'description':
                        $sanitized[$key] = self::sanitizeDescription($value);
                        break;
                    case 'number':
                        $sanitized[$key] = self::sanitizeNumber($value);
                        break;
                    case 'float':
                        $sanitized[$key] = self::sanitizeNumber($value, true);
                        break;
                    case 'national_id':
                        $sanitized[$key] = self::sanitizeNationalId($value);
                        break;
                    case 'ob_number':
                        $sanitized[$key] = self::sanitizeOBNumber($value);
                        break;
                    case 'badge_number':
                        $sanitized[$key] = self::sanitizeBadgeNumber($value);
                        break;
                    case 'url':
                        $sanitized[$key] = self::sanitizeUrl($value);
                        break;
                    default:
                        $sanitized[$key] = self::sanitizeText($value);
                }
            } else {

                $sanitized[$key] = is_array($value) ? self::sanitizeArray($value) : self::sanitizeText($value);
            }
        }

        return $sanitized;
    }

    public static function sanitizePost($rules = []) {
        return self::sanitizeArray($_POST, $rules);
    }

    public static function sanitizeGet($rules = []) {
        return self::sanitizeArray($_GET, $rules);
    }

    public static function removeDangerousChars($input) {
        if (is_null($input)) {
            return null;
        }

        $dangerous = [
            '<script',
            '</script>',
            '<iframe',
            '</iframe>',
            'javascript:',
            'vbscript:',
            'onload=',
            'onerror=',
            'onclick=',
            'onmouseover=',
            'eval(',
            'expression(',
            'document.cookie',
            'document.write',
            'window.location',
            'SELECT ',
            'INSERT ',
            'UPDATE ',
            'DELETE ',
            'DROP ',
            'CREATE ',
            'ALTER ',
            'EXEC ',
            'UNION ',
            '--',

            'xp_'
        ];

        $input = str_ireplace($dangerous, '', $input);

        return $input;
    }

    public static function cleanForDatabase($input) {
        if (is_null($input)) {
            return null;
        }

        $input = self::sanitizeText($input);
        $input = self::removeDangerousChars($input);

        return $input;
    }

    public static function sanitizeForJson($input) {
        if (is_null($input)) {
            return null;
        }

        if (is_array($input)) {
            return array_map([self::class, 'sanitizeForJson'], $input);
        }

        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeSearchQuery($query) {
        if (is_null($query)) {
            return null;
        }

        $query = trim($query);
        $query = self::sanitizeText($query);

        $query = str_replace(['%', '_'], ['\\%', '\\_'], $query);

        $query = substr($query, 0, 100);

        return $query;
    }
}

function sanitizeText($input) {
    return Sanitizer::sanitizeText($input);
}

function sanitizeEmail($email) {
    return Sanitizer::sanitizeEmail($email);
}

function sanitizePhone($phone) {
    return Sanitizer::sanitizePhone($phone);
}

function sanitizeName($name) {
    return Sanitizer::sanitizeName($name);
}

function sanitizeNumber($number, $allowFloat = false) {
    return Sanitizer::sanitizeNumber($number, $allowFloat);
}

function sanitizePost($rules = []) {
    return Sanitizer::sanitizePost($rules);
}

function sanitizeGet($rules = []) {
    return Sanitizer::sanitizeGet($rules);
}

function cleanForDatabase($input) {
    return Sanitizer::cleanForDatabase($input);
}

function sanitizeForJson($input) {
    return Sanitizer::sanitizeForJson($input);
}

function sanitizeSearchQuery($query) {
    return Sanitizer::sanitizeSearchQuery($query);
}
?>
