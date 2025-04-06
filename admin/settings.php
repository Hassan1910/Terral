<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

// Include database connection
require_once '../api/config/Database.php';
$database = new Database();
$conn = $database->getConnection();

// Include the Setting model
require_once '../api/models/Setting.php';
$setting = new Setting($conn);

// Get the active tab from the URL or default to 'general'
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Get all setting groups for tabs
$setting_groups = $setting->getGroups();

// Get ALL settings instead of just for active tab
$stmt = $setting->getAll();
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[] = $row;
}

// Handle setting updates via AJAX
$is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($is_ajax_request && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $response = ['success' => true, 'message' => 'Settings updated successfully'];
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        foreach ($_POST['settings'] as $key => $value) {
            if (!$setting->updateValue($key, $value)) {
                throw new Exception("Failed to update setting: {$key}");
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollBack();
        
        $response = ['success' => false, 'message' => $e->getMessage()];
        echo json_encode($response);
        exit;
    }
}

// Handle image upload via AJAX
if ($is_ajax_request && isset($_POST['action']) && $_POST['action'] === 'upload_image' && isset($_POST['setting_key'])) {
    $response = ['success' => false, 'message' => 'Image upload failed'];
    
    try {
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $setting_key = $_POST['setting_key'];
            
            // Check if setting exists and is of type image
            if ($setting->getByKey($setting_key) && $setting->setting_type === 'image') {
                $result = $setting->uploadImage($setting_key, $_FILES['image']);
                
                if ($result) {
                    $response = [
                        'success' => true, 
                        'message' => 'Image uploaded successfully',
                        'file_path' => $result
                    ];
                }
            }
        }
        
        echo json_encode($response);
        exit;
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => $e->getMessage()];
        echo json_encode($response);
        exit;
    }
}

// Helper function to format setting group name for display
function formatGroupName($group) {
    return ucwords(str_replace('_', ' ', $group));
}

// Helper function to render the appropriate input for each setting type
function renderSettingInput($setting) {
    // Ensure all required keys exist with default values if missing
    $setting_value = isset($setting['setting_value']) ? $setting['setting_value'] : '';
    $setting_key = isset($setting['setting_key']) ? $setting['setting_key'] : 'unknown';
    $setting_type = isset($setting['setting_type']) ? $setting['setting_type'] : 'text';
    
    $value = htmlspecialchars($setting_value);
    $id = "setting_" . $setting_key;
    $name = "settings[" . $setting_key . "]";
    $required = '';
    
    switch ($setting_type) {
        case 'text':
            echo "<input type='text' class='form-control' id='$id' name='$name' value='$value' $required>";
            break;
            
        case 'textarea':
            echo "<textarea class='form-control' id='$id' name='$name' rows='3' $required>$value</textarea>";
            break;
            
        case 'number':
            echo "<input type='number' class='form-control' id='$id' name='$name' value='$value' $required>";
            break;
            
        case 'boolean':
            $checked = $value == '1' ? 'checked' : '';
            echo "
                <div class='form-check form-switch'>
                    <input class='form-check-input' type='checkbox' id='$id' name='$name' value='1' $checked>
                    <input type='hidden' name='$name' value='0'>
                </div>
            ";
            break;
            
        case 'select':
            echo "<select class='form-control' id='$id' name='$name' $required>";
            
            if (!empty($setting['setting_options'])) {
                $options = json_decode($setting['setting_options'], true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($options)) {
                    foreach ($options as $option) {
                        $option_value = isset($option['value']) ? $option['value'] : $option;
                        $option_label = isset($option['label']) ? $option['label'] : $option;
                        $selected = $value == $option_value ? 'selected' : '';
                        
                        echo "<option value='$option_value' $selected>$option_label</option>";
                    }
                }
            }
            
            echo "</select>";
            break;
            
        case 'color':
            echo "<input type='color' class='form-control form-control-color' id='$id' name='$name' value='$value' $required>";
            break;
            
        case 'image':
            echo "
                <div class='row'>
                    <div class='col-md-8'>
                        <div class='input-group'>
                            <input type='text' class='form-control' id='$id' name='$name' value='$value' readonly>
                            <button type='button' class='btn btn-primary image-upload-btn' data-setting-key='{$setting_key}'>Upload</button>
                        </div>
                    </div>
                    <div class='col-md-4'>
                        <div class='image-preview-container'>
                            <img src='../$value' class='img-thumbnail image-preview' alt='Image preview' style='max-height: 100px;'>
                        </div>
                    </div>
                </div>
            ";
            break;
            
        case 'json':
            echo "<textarea class='form-control json-editor' id='$id' name='$name' rows='5' $required>$value</textarea>";
            break;
            
        default:
            echo "<input type='text' class='form-control' id='$id' name='$name' value='$value' $required>";
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - Terral Online Production System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- CodeMirror for JSON editing -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .CodeMirror {
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .setting-group {
            margin-bottom: 40px;
        }
        .setting-item {
            margin-bottom: 20px;
        }
        .nav-pills .nav-link.active {
            background-color: #0d6efd;
        }
        .tab-content {
            padding: 20px 0;
        }
        .image-preview-container {
            text-align: center;
            margin-top: 10px;
        }
        .upload-modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .upload-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 5px;
        }
        .settings-saved-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            display: none;
        }
        /* Add payment methods specific styles */
        .payment-method-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 10px;
            background-color: #fff;
        }
        .payment-method-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .payment-method-icon {
            font-size: 24px;
            width: 40px;
            text-align: center;
        }
        .payment-method-details h5 {
            margin: 0;
            font-size: 16px;
        }
        .payment-method-details p {
            margin: 0;
            font-size: 14px;
            color: #6c757d;
        }
        .payment-method-toggle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .payment-method-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .payment-method-toggle .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .payment-method-toggle .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        .payment-method-toggle input:checked + .slider {
            background-color: #2196F3;
        }
        .payment-method-toggle input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-3 text-white">
                <h2 class="h5 mb-4">Terral Admin</h2>
                <ul class="nav flex-column">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link text-white"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
                    <li class="nav-item"><a href="products.php" class="nav-link text-white"><i class="fas fa-box me-2"></i> Products</a></li>
                    <li class="nav-item"><a href="orders.php" class="nav-link text-white"><i class="fas fa-shopping-cart me-2"></i> Orders</a></li>
                    <li class="nav-item"><a href="customers.php" class="nav-link text-white"><i class="fas fa-users me-2"></i> Customers</a></li>
                    <li class="nav-item"><a href="categories.php" class="nav-link text-white"><i class="fas fa-tags me-2"></i> Categories</a></li>
                    <li class="nav-item"><a href="reports.php" class="nav-link text-white"><i class="fas fa-chart-bar me-2"></i> Reports</a></li>
                    <li class="nav-item"><a href="settings.php" class="nav-link text-white active bg-primary"><i class="fas fa-cog me-2"></i> Settings</a></li>
                    <li class="nav-item mt-5"><a href="../logout.php" class="nav-link text-white"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Main content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">System Settings</h1>
                    <button type="button" id="save-settings" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i> Save Settings
                    </button>
                </div>
                
                <div class="alert alert-success settings-saved-alert" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Settings saved successfully!
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <form id="settings-form" method="post">
                            <input type="hidden" name="action" value="update_settings">
                            
                            <!-- Settings tabs -->
                            <ul class="nav nav-pills mb-3" id="settings-tabs" role="tablist">
                                <?php foreach ($setting_groups as $index => $group): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?php echo ($group === $active_tab) ? 'active' : ''; ?>" 
                                                id="<?php echo $group; ?>-tab" 
                                                data-bs-toggle="pill" 
                                                data-bs-target="#<?php echo $group; ?>-pane" 
                                                type="button" 
                                                role="tab" 
                                                aria-controls="<?php echo $group; ?>-pane" 
                                                aria-selected="<?php echo ($group === $active_tab) ? 'true' : 'false'; ?>">
                                            <?php echo formatGroupName($group); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            
                            <!-- Tab content -->
                            <div class="tab-content" id="settings-tab-content">
                                <?php foreach ($setting_groups as $group): ?>
                                    <div class="tab-pane fade <?php echo ($group === $active_tab) ? 'show active' : ''; ?>" 
                                         id="<?php echo $group; ?>-pane" 
                                         role="tabpanel" 
                                         aria-labelledby="<?php echo $group; ?>-tab">
                                        
                                        <?php if ($group === 'payment'): ?>
                                            <!-- Payment Methods Section -->
                                            <div class="setting-group mb-5">
                                                <h3 class="mb-4">Payment Methods</h3>
                                                <div class="payment-methods-container">
                                                    <?php
                                                    // Get payment methods setting
                                                    $payment_methods_setting = array_filter($settings, function($setting) {
                                                        return $setting['setting_key'] === 'payment_methods';
                                                    });
                                                    
                                                    if (!empty($payment_methods_setting)) {
                                                        $payment_methods = json_decode(reset($payment_methods_setting)['setting_value'], true);
                                                        
                                                        foreach ($payment_methods as $method) {
                                                            $icon = '';
                                                            switch ($method['id']) {
                                                                case 'mpesa':
                                                                    $icon = 'fa-mobile-alt';
                                                                    break;
                                                                case 'bank_transfer':
                                                                    $icon = 'fa-university';
                                                                    break;
                                                                case 'cash_on_delivery':
                                                                    $icon = 'fa-money-bill-wave';
                                                                    break;
                                                                default:
                                                                    $icon = 'fa-credit-card';
                                                            }
                                                            ?>
                                                            <div class="payment-method-item">
                                                                <div class="payment-method-info">
                                                                    <div class="payment-method-icon">
                                                                        <i class="fas <?php echo $icon; ?>"></i>
                                                                    </div>
                                                                    <div class="payment-method-details">
                                                                        <h5><?php echo htmlspecialchars($method['name']); ?></h5>
                                                                        <p>Payment ID: <?php echo htmlspecialchars($method['id']); ?></p>
                                                                    </div>
                                                                </div>
                                                                <label class="payment-method-toggle">
                                                                    <input type="checkbox" 
                                                                           name="payment_method_status" 
                                                                           value="<?php echo htmlspecialchars($method['id']); ?>"
                                                                           <?php echo $method['enabled'] ? 'checked' : ''; ?>
                                                                           onchange="updatePaymentMethod(this)">
                                                                    <span class="slider"></span>
                                                                </label>
                                                            </div>
                                                            <?php
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <!-- M-Pesa Settings Section -->
                                            <div class="setting-group mb-5">
                                                <h3 class="mb-4">M-Pesa Settings</h3>
                                                <?php
                                                $mpesa_settings = array_filter($settings, function($setting) {
                                                    return in_array($setting['setting_key'], [
                                                        'mpesa_business_shortcode',
                                                        'mpesa_passkey',
                                                        'mpesa_consumer_key',
                                                        'mpesa_consumer_secret'
                                                    ]);
                                                });

                                                foreach ($mpesa_settings as $setting): ?>
                                                    <div class="setting-item row mb-3">
                                                        <div class="col-md-4">
                                                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                                                <?php echo $setting['setting_label']; ?>
                                                            </label>
                                                            <?php if (!empty($setting['setting_description'])): ?>
                                                                <p class="text-muted small"><?php echo $setting['setting_description']; ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <?php renderSettingInput($setting); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>

                                            <!-- Currency Settings Section -->
                                            <div class="setting-group">
                                                <h3 class="mb-4">Currency Settings</h3>
                                                <?php
                                                $currency_settings = array_filter($settings, function($setting) {
                                                    return in_array($setting['setting_key'], [
                                                        'currency_code',
                                                        'currency_symbol',
                                                        'minimum_order_amount'
                                                    ]);
                                                });

                                                foreach ($currency_settings as $setting): ?>
                                                    <div class="setting-item row mb-3">
                                                        <div class="col-md-4">
                                                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                                                <?php echo $setting['setting_label']; ?>
                                                            </label>
                                                            <?php if (!empty($setting['setting_description'])): ?>
                                                                <p class="text-muted small"><?php echo $setting['setting_description']; ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <?php renderSettingInput($setting); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="setting-group">
                                                <h3 class="h4 mb-4"><?php echo formatGroupName($group); ?> Settings</h3>
                                                
                                                <?php 
                                                // Filter settings for the current group
                                                $group_settings = array_filter($settings, function($setting) use ($group) {
                                                    return isset($setting['setting_group']) && $setting['setting_group'] === $group;
                                                });
                                                
                                                foreach ($group_settings as $setting): 
                                                ?>
                                                    <div class="setting-item row mb-3">
                                                        <div class="col-md-4">
                                                            <label for="setting_<?php echo $setting['setting_key']; ?>" class="form-label fw-bold">
                                                                <?php echo $setting['setting_label']; ?>
                                                            </label>
                                                            <?php if (!empty($setting['setting_description'])): ?>
                                                                <p class="text-muted small"><?php echo $setting['setting_description']; ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <?php renderSettingInput($setting); ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <?php if (empty($group_settings)): ?>
                                                    <div class="alert alert-info">
                                                        No settings defined for this group.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Upload Modal -->
    <div id="upload-modal" class="upload-modal">
        <div class="upload-modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Image</h5>
                <button type="button" class="btn-close close-modal"></button>
            </div>
            <div class="modal-body">
                <form id="image-upload-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_image">
                    <input type="hidden" name="setting_key" id="upload-setting-key">
                    
                    <div class="mb-3">
                        <label for="image-file" class="form-label">Select Image</label>
                        <input type="file" class="form-control" id="image-file" name="image" accept="image/*" required>
                    </div>
                    
                    <div class="upload-preview-container mb-3" style="display: none;">
                        <img id="upload-preview" class="img-fluid" alt="Upload preview">
                    </div>
                    
                    <div class="alert alert-danger upload-error" style="display: none;"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-upload">Upload</button>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CodeMirror JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/javascript/javascript.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize CodeMirror for JSON fields
            var jsonEditors = [];
            
            $('.json-editor').each(function() {
                var editor = CodeMirror.fromTextArea(this, {
                    mode: "application/json",
                    theme: "monokai",
                    lineNumbers: true,
                    autoCloseBrackets: true,
                    matchBrackets: true,
                    indentUnit: 2,
                    tabSize: 2,
                    lineWrapping: true,
                    viewportMargin: Infinity
                });
                
                // Store the editor instance
                jsonEditors.push(editor);
                
                // Update textarea when editor changes
                editor.on('change', function(instance) {
                    instance.save();
                });
            });
            
            // Handle tabs with URL
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            
            if (tabParam) {
                const tab = document.getElementById(tabParam + '-tab');
                if (tab) {
                    tab.click();
                }
            }
            
            // Save settings
            $('#save-settings').on('click', function() {
                // Update CodeMirror content to textareas
                jsonEditors.forEach(function(editor) {
                    editor.save();
                });
                
                // Disable form validation since none of our fields are actually required
                // AJAX form submission
                $.ajax({
                    url: 'settings.php',
                    type: 'POST',
                    data: $('#settings-form').serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('.settings-saved-alert').fadeIn().delay(3000).fadeOut();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('An error occurred while saving settings. Please try again.');
                        console.error(xhr.responseText);
                    }
                });
            });
            
            // Image upload handling
            $('.image-upload-btn').on('click', function() {
                const settingKey = $(this).data('setting-key');
                $('#upload-setting-key').val(settingKey);
                $('#image-file').val('');
                $('.upload-preview-container').hide();
                $('.upload-error').hide();
                $('#upload-modal').show();
            });
            
            // Close modal
            $('.close-modal').on('click', function() {
                $('#upload-modal').hide();
            });
            
            // Image preview
            $('#image-file').on('change', function() {
                if (this.files && this.files[0]) {
                    var reader = new FileReader();
                    
                    reader.onload = function(e) {
                        $('#upload-preview').attr('src', e.target.result);
                        $('.upload-preview-container').show();
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Submit image upload
            $('#submit-upload').on('click', function() {
                const fileInput = $('#image-file')[0];
                
                if (!fileInput.files || !fileInput.files[0]) {
                    $('.upload-error').text('Please select an image file.').show();
                    return;
                }
                
                const formData = new FormData($('#image-upload-form')[0]);
                
                $.ajax({
                    url: 'settings.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update image preview and input value
                            const settingKey = $('#upload-setting-key').val();
                            const inputField = $('input[name="settings[' + settingKey + ']"]');
                            const previewImg = inputField.closest('.row').find('.image-preview');
                            
                            inputField.val(response.file_path);
                            previewImg.attr('src', '../' + response.file_path);
                            
                            // Close modal
                            $('#upload-modal').hide();
                            
                            // Show success message
                            $('.settings-saved-alert').text('Image uploaded successfully!').fadeIn().delay(3000).fadeOut();
                        } else {
                            $('.upload-error').text('Error: ' + response.message).show();
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.upload-error').text('An error occurred during upload.').show();
                        console.error(xhr.responseText);
                    }
                });
            });
            
            // Handle checkbox for boolean settings
            $('.form-check-input[type="checkbox"]').on('change', function() {
                const hiddenInput = $(this).next('input[type="hidden"]');
                if (this.checked) {
                    hiddenInput.prop('disabled', true);
                } else {
                    hiddenInput.prop('disabled', false);
                }
            });
            
            // Initialize checkboxes state
            $('.form-check-input[type="checkbox"]').each(function() {
                if (this.checked) {
                    $(this).next('input[type="hidden"]').prop('disabled', true);
                }
            });
            
            // Close modal when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).is('.upload-modal')) {
                    $('#upload-modal').hide();
                }
            });
        });

        function updatePaymentMethod(checkbox) {
            const methodId = checkbox.value;
            const isEnabled = checkbox.checked;
            
            // Get current payment methods
            let paymentMethods = <?php 
                $payment_setting = array_filter($settings, function($s) { 
                    return $s['setting_key'] === 'payment_methods'; 
                });
                echo !empty($payment_setting) ? reset($payment_setting)['setting_value'] : '[]';
            ?>;
            
            // Update the enabled status
            paymentMethods = paymentMethods.map(method => {
                if (method.id === methodId) {
                    return { ...method, enabled: isEnabled };
                }
                return method;
            });
            
            // Save the updated settings
            const formData = new FormData();
            formData.append('action', 'update_settings');
            formData.append('settings[payment_methods]', JSON.stringify(paymentMethods));
            
            fetch('settings.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Payment method updated successfully');
                } else {
                    showAlert('danger', 'Failed to update payment method');
                    checkbox.checked = !isEnabled; // Revert the checkbox
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while updating the payment method');
                checkbox.checked = !isEnabled; // Revert the checkbox
            });
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show settings-saved-alert`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Show the alert
            setTimeout(() => alertDiv.style.display = 'block', 100);
            
            // Hide and remove the alert after 3 seconds
            setTimeout(() => {
                alertDiv.style.display = 'none';
                setTimeout(() => alertDiv.remove(), 500);
            }, 3000);
        }
    </script>
</body>
</html> 