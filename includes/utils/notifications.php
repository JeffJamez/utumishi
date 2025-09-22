<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

function setNotification($type, $message, $title = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['notification'] = [
        'type' => $type,
        'message' => $message,
        'title' => $title,
        'timestamp' => time()
    ];
}

function getNotification() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        unset($_SESSION['notification']);
        return $notification;
    }

    return null;
}

function hasNotification() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['notification']);
}

function displayNotification() {
    $notification = getNotification();
    if ($notification) {
        $typeClass = getNotificationClass($notification['type']);
        $icon = getNotificationIcon($notification['type']);

        echo '<div class="notification ' . $typeClass . '" id="notification">';
        echo '<div class="notification-content">';

        if (!empty($notification['title'])) {
            echo '<strong>' . $icon . ' ' . htmlspecialchars($notification['title']) . '</strong><br>';
        }

        echo htmlspecialchars($notification['message']);
        echo '</div>';
        echo '<button class="notification-close" onclick="closeNotification()">&times;</button>';
        echo '</div>';
    }
}

function getNotificationClass($type) {
    $classes = [
        'success' => 'notification-success',
        'error' => 'notification-error',
        'warning' => 'notification-warning',
        'info' => 'notification-info',
        'danger' => 'notification-error'
    ];

    return $classes[$type] ?? 'notification-info';
}

function getNotificationIcon($type) {
    $icons = [
        'success' => '✅',
        'error' => '❌',
        'warning' => '⚠️',
        'info' => 'ℹ️',
        'danger' => '🚨'
    ];

    return $icons[$type] ?? 'ℹ️';
}

function notifySuccess($message, $title = 'Success') {
    setNotification('success', $message, $title);
}

function notifyError($message, $title = 'Error') {
    setNotification('error', $message, $title);
}

function notifyWarning($message, $title = 'Warning') {
    setNotification('warning', $message, $title);
}

function notifyInfo($message, $title = 'Information') {
    setNotification('info', $message, $title);
}

function notifyCaseStatusChange($caseOBNumber, $oldStatus, $newStatus) {
    $message = "Case {$caseOBNumber} status changed from " . 
               ucfirst(str_replace('_', ' ', $oldStatus)) . " to " . 
               ucfirst(str_replace('_', ' ', $newStatus));

    notifyInfo($message, 'Case Update');
}

function notifyCaseAssignment($caseOBNumber, $officerName) {
    $message = "Case {$caseOBNumber} has been assigned to {$officerName}";
    notifyInfo($message, 'Case Assignment');
}

function notifyEvidenceUploaded($caseOBNumber, $fileName) {
    $message = "Evidence file '{$fileName}' uploaded for case {$caseOBNumber}";
    notifySuccess($message, 'Evidence Added');
}

function generateBrowserNotification($title, $message, $icon = null) {
    $iconUrl = $icon ?: BASE_URL . '/assets/images/police-badge.png';

    return "
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('" . addslashes($title) . "', {
                body: '" . addslashes($message) . "',
                icon: '" . $iconUrl . "',
                badge: '" . $iconUrl . "'
            });
        } else if (Notification.permission === 'default') {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    new Notification('" . addslashes($title) . "', {
                        body: '" . addslashes($message) . "',
                        icon: '" . $iconUrl . "',
                        badge: '" . $iconUrl . "'
                    });
                }
            });
        }
    }
    ";
}

function logActivity($userId, $action, $description, $ipAddress = null) {
    try {
        $db = Database::getInstance();

        $activityData = [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => date('Y-m-d H:i:s')
        ];

        error_log("User Activity: User {$userId} - {$action} - {$description} - IP: " . $activityData['ip_address']);

        return true;
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
        return false;
    }
}

function generateToastNotification($type, $message, $duration = 5000) {
    $icon = getNotificationIcon($type);
    $class = getNotificationClass($type);

    return "
    function showToast() {
        const toast = document.createElement('div');
        toast.className = 'toast {$class}';
        toast.innerHTML = '<span>{$icon} " . addslashes($message) . "</span>';
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 6px;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;

        const colors = {
            'notification-success': '#28a745',
            'notification-error': '#dc3545',
            'notification-warning': '#ffc107',
            'notification-info': '#17a2b8'
        };
        toast.style.backgroundColor = colors['{$class}'] || '#17a2b8';

        document.body.appendChild(toast);

        setTimeout(() => toast.style.transform = 'translateX(0)', 100);

        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => document.body.removeChild(toast), 300);
        }, {$duration});
    }
    showToast();
    ";
}

function sendEmailNotification($to, $subject, $message, $priority = 'normal') {

    error_log("Email Notification: To: {$to}, Subject: {$subject}, Message: {$message}");
    return true;
}

function sendSMSNotification($phoneNumber, $message) {

    error_log("SMS Notification: To: {$phoneNumber}, Message: {$message}");
    return true;
}

function getUserNotificationPreferences($userId) {

    return [
        'email_notifications' => true,
        'browser_notifications' => true,
        'sms_notifications' => false,
        'case_updates' => true,
        'system_alerts' => true,
        'weekly_reports' => true
    ];
}

class NotificationQueue {
    private static $queue = [];

    public static function add($type, $userId, $title, $message, $data = []) {
        self::$queue[] = [
            'type' => $type,
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'created_at' => time()
        ];
    }

    public static function process() {
        foreach (self::$queue as $notification) {

            $preferences = getUserNotificationPreferences($notification['user_id']);

            if ($preferences['browser_notifications']) {

                $_SESSION['pending_notifications'][] = $notification;
            }

            if ($preferences['email_notifications']) {
                sendEmailNotification(
                    $notification['user_id'], 
                    $notification['title'], 
                    $notification['message']
                );
            }
        }

        self::$queue = [];
    }

    public static function getQueue() {
        return self::$queue;
    }
}

define('ALERT_LOW', 'low');
define('ALERT_MEDIUM', 'medium');
define('ALERT_HIGH', 'high');
define('ALERT_CRITICAL', 'critical');

function systemAlert($level, $title, $message, $component = 'system') {
    $alertData = [
        'level' => $level,
        'title' => $title,
        'message' => $message,
        'component' => $component,
        'timestamp' => time()
    ];

    error_log("System Alert [{$level}]: {$title} - {$message} [{$component}]");

    if ($level === ALERT_CRITICAL) {

        notifyError($message, "Critical System Alert: {$title}");
    }

    return $alertData;
}
?>
