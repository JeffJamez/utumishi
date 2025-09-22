<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class Auth {
    private $db;
    private $sessionTimeout;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->sessionTimeout = SESSION_TIMEOUT;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->checkSessionTimeout();
    }

    public function login($nationalId, $password, $role = null) {
        try {

            $nationalId = trim($nationalId);

            if (empty($nationalId) || empty($password)) {
                return ['success' => false, 'message' => 'National ID and password are required'];
            }

            $whereClause = "national_id = :national_id AND is_active = 1";
            $params = ['national_id' => $nationalId];

            if ($role && in_array($role, [ROLE_ADMIN, ROLE_OCS, ROLE_OFFICER, ROLE_CITIZEN])) {
                $whereClause .= " AND role = :role";
                $params['role'] = $role;
            }

            $sql = "SELECT id, national_id, name, email, phone, password, role, station_id, last_login 
                    FROM users 
                    WHERE {$whereClause}";

            $user = $this->db->fetchOne($sql, $params);

            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials or user not found'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            $this->db->update('users', 
                ['last_login' => date('Y-m-d H:i:s')], 
                'id = :id', 
                ['id' => $user['id']]
            );

            $this->createSession($user);

            return [
                'success' => true, 
                'message' => MSG_LOGIN_SUCCESS,
                'user' => $user,
                'redirect' => $this->getRedirectUrl($user['role'])
            ];

        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }

    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['national_id'] = $user['national_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['station_id'] = $user['station_id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        session_regenerate_id(true);
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']);
    }

    private function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
        }
        return true;
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }

         $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT id, national_id, name, phone, email, role, station_id 
                FROM users 
                WHERE id = :id AND is_active = 1";

        $user = $db->fetchOne($sql, ['id' => $userId]);

        if (!$user) {
            $this->logout();
            return null;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['national_id'] = $user['national_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['phone'] = $user['phone'];      
        $_SESSION['email'] = $user['email'];      
        $_SESSION['role'] = $user['role'];
        $_SESSION['station_id'] = $user['station_id'];

        return [
            'id' => $user['id'],
            'national_id' => $user['national_id'],
            'name' => $user['name'],
            'phone' => $user['phone'],           
            'email' => $user['email'],           
            'role' => $user['role'],
            'station_id' => $user['station_id']
        ];
    }

    public function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    public function hasAnyRole($roles) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }
        return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
    }

    public function belongsToStation($stationId) {
        return isset($_SESSION['station_id']) && $_SESSION['station_id'] == $stationId;
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ' . BASE_URL . '/pages/auth/login.php');
            exit;
        }
    }

    public function requireRole($role) {
        $this->requireLogin();

        if (!$this->hasRole($role)) {
            $this->accessDenied();
        }
    }

    public function requireAnyRole($roles) {
        $this->requireLogin();

        if (!$this->hasAnyRole($roles)) {
            $this->accessDenied();
        }
    }

    private function accessDenied() {
        http_response_code(403);
        die('<h1>Access Denied</h1><p>' . MSG_ACCESS_DENIED . '</p><p><a href="' . BASE_URL . '">Go to Home</a></p>');
    }

    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public function logout() {

        session_unset();

        session_destroy();

        session_start();
        session_regenerate_id(true);
    }

    private function getRedirectUrl($role) {
        $urls = [
            ROLE_ADMIN => BASE_URL . '/pages/admin/dashboard.php',
            ROLE_OCS => BASE_URL . '/pages/ocs/dashboard.php',
            ROLE_OFFICER => BASE_URL . '/pages/officer/dashboard.php',
            ROLE_CITIZEN => BASE_URL . '/pages/citizen/dashboard.php'
        ];

        return $urls[$role] ?? BASE_URL . '/index.php';
    }

    public function registerCitizen($data) {
        try {

            $required = ['national_id', 'name', 'phone', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return ['success' => false, 'message' => ucfirst($field) . ' is required'];
                }
            }

            if ($this->db->exists('users', 'national_id = :national_id', ['national_id' => $data['national_id']])) {
                return ['success' => false, 'message' => 'National ID already registered'];
            }

            if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }

            $userData = [
                'national_id' => trim($data['national_id']),
                'name' => trim($data['name']),
                'phone' => trim($data['phone']),
                'email' => !empty($data['email']) ? trim($data['email']) : null,
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => ROLE_CITIZEN,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $userId = $this->db->insert('users', $userData);

            if ($userId) {
                return [
                    'success' => true, 
                    'message' => 'Registration successful. You can now login.',
                    'user_id' => $userId
                ];
            } else {
                return ['success' => false, 'message' => 'Registration failed. Please try again.'];
            }

        } catch (Exception $e) {
            error_log("Registration Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
    }

    public function changePassword($userId, $oldPassword, $newPassword) {
        try {

            $user = $this->db->fetchOne('SELECT password FROM users WHERE id = :id', ['id' => $userId]);

            if (!$user) {
                return ['success' => false, 'message' => 'User not found'];
            }

            if (!password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect'];
            }

            if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
                return ['success' => false, 'message' => 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
            }

            $updated = $this->db->update('users', 
                ['password' => password_hash($newPassword, PASSWORD_DEFAULT)], 
                'id = :id', 
                ['id' => $userId]
            );

            return $updated > 0 ? 
                ['success' => true, 'message' => 'Password changed successfully'] :
                ['success' => false, 'message' => 'Failed to change password'];

        } catch (Exception $e) {
            error_log("Change Password Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to change password'];
        }
    }

    public function getUserStation($userId = null) {
        $userId = $userId ?? $_SESSION['user_id'] ?? null;

        if (!$userId) {
            return null;
        }

        $sql = "SELECT s.* FROM stations s 
                JOIN users u ON s.id = u.station_id 
                WHERE u.id = :user_id";

        return $this->db->fetchOne($sql, ['user_id' => $userId]);
    }

    public function canAccessCase($caseId) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        if ($this->hasAnyRole([ROLE_ADMIN, ROLE_OCS])) {
            return true;
        }

        if ($this->hasRole(ROLE_OFFICER)) {
            $sql = "SELECT c.id FROM cases c 
                    LEFT JOIN officers o ON c.assigned_officer_id = o.id 
                    WHERE c.id = :case_id 
                    AND (c.station_id = :station_id OR o.user_id = :user_id)";

            $result = $this->db->fetchOne($sql, [
                'case_id' => $caseId,
                'station_id' => $_SESSION['station_id'],
                'user_id' => $_SESSION['user_id']
            ]);

            return $result !== false;
        }

        if ($this->hasRole(ROLE_CITIZEN)) {
            $sql = "SELECT 1 FROM cases WHERE id = :case_id AND reported_by_citizen_id = :user_id";
            $result = $this->db->fetchOne($sql, [
                'case_id' => $caseId,
                'user_id' => $_SESSION['user_id']
            ]);

            return $result !== false;
        }

        return false;
    }

    public function logActivity($action, $description = '', $affectedId = null) {
        if (!$this->isLoggedIn()) {
            return false;
        }

        try {
            $logData = [
                'user_id' => $_SESSION['user_id'],
                'action' => $action,
                'description' => $description,
                'affected_id' => $affectedId,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ];

            error_log("User Activity: User ID {$_SESSION['user_id']} - {$action} - {$description}");

            return true;
        } catch (Exception $e) {
            error_log("Activity Log Error: " . $e->getMessage());
            return false;
        }
    }
}

function getAuth() {
    static $auth = null;
    if ($auth === null) {
        $auth = new Auth();
    }
    return $auth;
}

function isLoggedIn() {
    return getAuth()->isLoggedIn();
}

function getCurrentUser() {
    return getAuth()->getCurrentUser();
}

function hasRole($role) {
    return getAuth()->hasRole($role);
}

function hasAnyRole($roles) {
    return getAuth()->hasAnyRole($roles);
}

function requireLogin() {
    getAuth()->requireLogin();
}

function requireRole($role) {
    getAuth()->requireRole($role);
}

function requireAnyRole($roles) {
    getAuth()->requireAnyRole($roles);
}

function csrfToken() {
    return getAuth()->generateCSRFToken();
}

function validateCSRF($token) {
    return getAuth()->validateCSRFToken($token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function logout() {
    getAuth()->logout();
}

function setFlashMessage($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }

    return null;
}

function flashMessage() {
    $message = getFlashMessage();
    if ($message) {
        $class = 'flash-' . $message['type'];
        echo '<div class="' . $class . '">' . htmlspecialchars($message['message']) . '</div>';
    }
}
?>
