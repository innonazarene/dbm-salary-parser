<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhSalaryGrade\SalaryGradeParser;

// ── CORS headers (adjust origin to your React dev/prod URL) ──────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Must have a file uploaded under the key "pdf"
if (empty($_FILES['pdf'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded. Send the PDF as form-data with key "pdf".']);
    exit;
}

$file = $_FILES['pdf'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the form.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    http_response_code(400);
    echo json_encode(['error' => $errors[$file['error']] ?? 'Unknown upload error.']);
    exit;
}

// Validate MIME type
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if ($mimeType !== 'application/pdf') {
    http_response_code(415);
    echo json_encode(['error' => 'Uploaded file must be a PDF. Got: ' . $mimeType]);
    exit;
}

// Parse and return JSON
try {
    $data = SalaryGradeParser::parseFile($file['tmp_name']);
    http_response_code(200);
    echo json_encode($data);
} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    // Clean up temp file
    if (file_exists($file['tmp_name'])) {
        @unlink($file['tmp_name']);
    }
}
