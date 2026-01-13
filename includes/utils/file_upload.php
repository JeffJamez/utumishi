<?php

if (!defined('UTUMISHI_WEB_APP')) {
    die('Direct access not permitted');
}

class FileUploadHandler {
    private $db;
    private $uploadPath;
    private $maxFileSize;
    private $allowedTypes;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadPath = EVIDENCE_PATH;
        $this->maxFileSize = MAX_FILE_SIZE;
        $this->allowedTypes = ALLOWED_FILE_TYPES;

        $this->createUploadDirectory();
    }

    public function uploadEvidence($file, $caseId, $officerId, $description = '') {
        try {

            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return $validation;
            }

            if (!$this->canUploadToCase($caseId, $officerId)) {
                return ['success' => false, 'message' => 'You do not have permission to upload evidence to this case'];
            }

            if (!$this->checkFileLimitForCase($caseId)) {
                return ['success' => false, 'message' => 'Maximum number of evidence files reached for this case'];
            }

            $fileName = $this->generateUniqueFilename($file['name'], $caseId);

            $caseDir = $this->uploadPath . '/case_' . $caseId;
            if (!file_exists($caseDir)) {
                if (!mkdir($caseDir, 0755, true)) {
                    return ['success' => false, 'message' => 'Failed to create upload directory'];
                }
            }

            $filePath = $caseDir . '/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }

            $fileData = [
                'case_id' => $caseId,
                'file_path' => 'case_' . $caseId . '/' . $fileName,
                'original_filename' => $file['name'],
                'file_type' => strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)),
                'uploaded_by_officer_id' => $officerId,
                'uploaded_at' => date('Y-m-d H:i:s'),
                'description' => sanitizeText($description)
            ];

            $fileId = $this->db->insert('case_evidence', $fileData);

            if ($fileId) {
                return [
                    'success' => true,
                    'message' => 'Evidence file uploaded successfully',
                    'file_id' => $fileId,
                    'file_path' => $filePath,
                    'file_name' => $fileName
                ];
            } else {

                unlink($filePath);
                return ['success' => false, 'message' => 'Failed to save file information'];
            }

        } catch (Exception $e) {
            error_log("File Upload Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'File upload failed'];
        }
    }

    private function validateFile($file) {

        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valid' => false, 'message' => 'No file selected'];
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

        if ($file['size'] > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1024 / 1024, 1);
            return ['valid' => false, 'message' => "File size exceeds maximum allowed ({$maxSizeMB}MB)"];
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $this->allowedTypes)) {
            $allowedStr = implode(', ', $this->allowedTypes);
            return ['valid' => false, 'message' => "Invalid file type. Allowed types: {$allowedStr}"];
        }

        $allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png'
        ];

        $expectedMimeType = $allowedMimeTypes[$fileExtension] ?? null;
        $actualMimeType = mime_content_type($file['tmp_name']);

        if ($expectedMimeType && $actualMimeType !== $expectedMimeType) {
            return ['valid' => false, 'message' => 'File type does not match file content'];
        }

        return ['valid' => true, 'message' => 'File is valid'];
    }

    private function canUploadToCase($caseId, $officerId) {

        $case = $this->db->fetchOne(
            "SELECT c.*, o.user_id FROM cases c 
             LEFT JOIN officers o ON c.assigned_officer_id = o.id 
             WHERE c.id = :case_id",
            ['case_id' => $caseId]
        );

        if (!$case) {
            return false;
        }

        $officer = $this->db->fetchOne(
            "SELECT u.*, o.* FROM users u 
             JOIN officers o ON u.id = o.user_id 
             WHERE u.id = :officer_id",
            ['officer_id' => $officerId]
        );

        if (!$officer) {
            return false;
        }

        return ($case['user_id'] == $officerId) || 
               ($case['station_id'] == $officer['station_id']) ||
               ($case['recorded_by_officer_id'] == $officerId);
    }

    private function checkFileLimitForCase($caseId) {
        $count = $this->db->count('case_evidence', 'case_id = :case_id', ['case_id' => $caseId]);
        return $count < EVIDENCE_MAX_FILES_PER_CASE;
    }

    private function generateUniqueFilename($originalName, $caseId) {
        $pathInfo = pathinfo($originalName);
        $extension = isset($pathInfo['extension']) ? '.' . strtolower($pathInfo['extension']) : '';

        $timestamp = date('YmdHis');
        $random = substr(uniqid(), -6);

        return "evidence_case{$caseId}_{$timestamp}_{$random}{$extension}";
    }

    private function createUploadDirectory() {
        if (!file_exists($this->uploadPath)) {
            if (!mkdir($this->uploadPath, 0755, true)) {
                throw new Exception("Failed to create upload directory: " . $this->uploadPath);
            }
        }

        $htaccess = $this->uploadPath . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "Options -Indexes\n";
            $content .= "Deny from all\n";
            $content .= "<Files ~ \"\\.(pdf|jpg|jpeg|png)$\">\n";
            $content .= "    # Allow access only through application\n";
            $content .= "    Deny from all\n";
            $content .= "</Files>\n";

            file_put_contents($htaccess, $content);
        }

        $index = $this->uploadPath . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php header("HTTP/1.0 403 Forbidden"); exit; ?>');
        }
    }

    public function getCaseEvidence($caseId) {
        $sql = "SELECT e.*, u.name as uploaded_by_name 
                FROM case_evidence e
                JOIN users u ON e.uploaded_by_officer_id = u.id
                WHERE e.case_id = :case_id
                ORDER BY e.uploaded_at DESC";

        return $this->db->fetchAll($sql, ['case_id' => $caseId]);
    }

    public function deleteEvidence($evidenceId, $officerId) {
        try {

            $evidence = $this->db->fetchOne(
                "SELECT * FROM case_evidence WHERE id = :id",
                ['id' => $evidenceId]
            );

            if (!$evidence) {
                return ['success' => false, 'message' => 'Evidence file not found'];
            }

            if (!$this->canDeleteEvidence($evidence, $officerId)) {
                return ['success' => false, 'message' => 'You do not have permission to delete this evidence'];
            }

            $filePath = $this->uploadPath . '/' . $evidence['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $deleted = $this->db->delete('case_evidence', 'id = :id', ['id' => $evidenceId]);

            if ($deleted > 0) {
                return ['success' => true, 'message' => 'Evidence file deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete evidence record'];
            }

        } catch (Exception $e) {
            error_log("Delete Evidence Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete evidence file'];
        }
    }

    private function canDeleteEvidence($evidence, $officerId) {

        if ($evidence['uploaded_by_officer_id'] == $officerId) {
            return true;
        }

        $case = $this->db->fetchOne(
            "SELECT assigned_officer_id FROM cases WHERE id = :id",
            ['id' => $evidence['case_id']]
        );

        if ($case) {
            $assignedOfficer = $this->db->fetchOne(
                "SELECT user_id FROM officers WHERE id = :id",
                ['id' => $case['assigned_officer_id']]
            );

            return $assignedOfficer && $assignedOfficer['user_id'] == $officerId;
        }

        return false;
    }

    public function serveEvidenceFile($evidenceId, $userId) {
        try {

            $sql = "SELECT e.*, c.reported_by_citizen_id, c.assigned_officer_id, c.station_id
                    FROM case_evidence e
                    JOIN cases c ON e.case_id = c.id
                    WHERE e.id = :evidence_id";

            $evidence = $this->db->fetchOne($sql, ['evidence_id' => $evidenceId]);

            if (!$evidence) {
                return ['success' => false, 'message' => 'Evidence file not found'];
            }

            if (!getAuth()->canAccessCase($evidence['case_id'])) {
                return ['success' => false, 'message' => 'Access denied'];
            }

            $filePath = $this->uploadPath . '/' . $evidence['file_path'];

            if (!file_exists($filePath)) {
                return ['success' => false, 'message' => 'File not found on server'];
            }

            return [
                'success' => true,
                'file_path' => $filePath,
                'original_name' => $evidence['original_filename'],
                'file_type' => $evidence['file_type']
            ];

        } catch (Exception $e) {
            error_log("Serve Evidence Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to access file'];
        }
    }
}

function uploadEvidence($file, $caseId, $officerId, $description = '') {
    $handler = new FileUploadHandler();
    return $handler->uploadEvidence($file, $caseId, $officerId, $description);
}

function getCaseEvidence($caseId) {
    $handler = new FileUploadHandler();
    return $handler->getCaseEvidence($caseId);
}

function deleteEvidence($evidenceId, $officerId) {
    $handler = new FileUploadHandler();
    return $handler->deleteEvidence($evidenceId, $officerId);
}

function serveEvidenceFile($evidenceId, $userId) {
    $handler = new FileUploadHandler();
    return $handler->serveEvidenceFile($evidenceId, $userId);
}

function uploadCitizenIdDocument($file, $nationalId) {
    $uploadPath = UPLOADS_PATH . '/citizen_docs';

    if (!file_exists($uploadPath)) {
        if (!mkdir($uploadPath, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    $htaccess = $uploadPath . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "Options -Indexes\nDeny from all");
    }

    $index = $uploadPath . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, '<?php header("HTTP/1.0 403 Forbidden"); exit; ?>');
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
        return ['success' => false, 'message' => $message];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed (5MB)'];
    }

    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($fileExtension !== 'pdf') {
        return ['success' => false, 'message' => 'Only PDF files are allowed for citizen ID documents'];
    }

    $mimeType = mime_content_type($file['tmp_name']);
    if ($mimeType !== 'application/pdf') {
        return ['success' => false, 'message' => 'File type does not match PDF content'];
    }

    $timestamp = date('YmdHis');
    $random = substr(uniqid(), -6);
    $fileName = "citizen_{$nationalId}_{$timestamp}_{$random}.pdf";
    $filePath = $uploadPath . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => false, 'message' => 'Failed to upload file'];
    }

    return [
        'success' => true,
        'message' => 'Citizen ID document uploaded successfully',
        'file_path' => 'citizen_docs/' . $fileName,
        'file_name' => $fileName
    ];
}
?>
