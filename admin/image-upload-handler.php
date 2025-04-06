<?php
/**
 * Admin Image Upload Handler
 * 
 * This script processes image uploads from admin forms for products, categories, etc.
 * It supports multiple image formats and provides proper error handling.
 */

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include necessary files
require_once '../api/config/Database.php';
require_once '../api/helpers/ImageUploadHelper.php';

// Start session for user authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Process only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'No action specified'
];

// Check if action is specified
if (!isset($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Initialize image upload helper
$imageUploader = new ImageUploadHelper();

// Process based on action
$action = $_POST['action'];

switch ($action) {
    case 'upload':
        // Upload image
        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            $response = [
                'success' => false,
                'message' => 'No file uploaded'
            ];
            break;
        }
        
        // Check if upload type is specified
        if (!isset($_POST['type']) || empty($_POST['type'])) {
            $response = [
                'success' => false,
                'message' => 'Upload type not specified'
            ];
            break;
        }
        
        // Get upload type (products, categories, etc.)
        $uploadType = $_POST['type'];
        
        // Optional custom name
        $customName = isset($_POST['custom_name']) ? $_POST['custom_name'] : null;
        
        // Process upload
        $response = $imageUploader->uploadImage($_FILES['image'], $uploadType, $customName);
        break;
        
    case 'validate':
        // Validate image without uploading
        if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
            $response = [
                'success' => false,
                'message' => 'No file uploaded'
            ];
            break;
        }
        
        // Process validation
        $response = $imageUploader->validateImage($_FILES['image']);
        break;
        
    case 'delete':
        // Delete image
        if (!isset($_POST['filename']) || empty($_POST['filename'])) {
            $response = [
                'success' => false,
                'message' => 'Filename not specified'
            ];
            break;
        }
        
        if (!isset($_POST['type']) || empty($_POST['type'])) {
            $response = [
                'success' => false,
                'message' => 'Upload type not specified'
            ];
            break;
        }
        
        // Process deletion
        $response = $imageUploader->deleteImage($_POST['filename'], $_POST['type']);
        break;
        
    default:
        $response = [
            'success' => false,
            'message' => 'Invalid action specified'
        ];
        break;
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 