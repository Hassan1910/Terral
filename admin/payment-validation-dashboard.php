/**
 * Payment Validation Dashboard Enhancement
 * Adds payment validation alerts and warnings to the admin interface
 */

// Enhanced payment validation functions for the dashboard
function addPaymentValidationAlerts() {
    ?>
    <script>
    // Payment validation enhancement for dashboard
    function checkPaymentValidation(orderId, callback) {
        fetch('ajax-get-payment-validation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'order_id=' + orderId
        })
        .then(response => response.json())
        .then(data => {
            if (callback) callback(data);
        })
        .catch(error => {
            console.error('Payment validation check failed:', error);
        });
    }
    
    function showPaymentValidationWarning(validation) {
        if (!validation.valid) {
            let warningClass = '';
            let warningIcon = '';
            
            switch(validation.code) {
                case 'PAYMENT_NOT_COMPLETED':
                    warningClass = 'alert-warning';
                    warningIcon = 'fa-exclamation-triangle';
                    break;
                case 'PAYMENT_REQUIRED_FOR_DELIVERY':
                    warningClass = 'alert-danger';
                    warningIcon = 'fa-ban';
                    break;
                default:
                    warningClass = 'alert-info';
                    warningIcon = 'fa-info-circle';
            }
            
            return `
                <div class="alert ${warningClass} payment-validation-alert" style="margin-bottom: 15px;">
                    <i class="fas ${warningIcon}"></i>
                    <strong>Payment Validation:</strong> ${validation.message}
                    ${validation.required_action ? `<br><small><strong>Action Required:</strong> ${validation.required_action.replace('_', ' ')}</small>` : ''}
                </div>
            `;
        }
        return '';
    }
    
    function validateOrderStatusUpdate(orderId, newStatus, callback) {
        fetch('ajax-validate-status-update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `order_id=${orderId}&new_status=${newStatus}`
        })
        .then(response => response.json())
        .then(data => {
            if (callback) callback(data);
        })
        .catch(error => {
            console.error('Status update validation failed:', error);
            if (callback) callback({allowed: false, message: 'Validation check failed'});
        });
    }
    
    // Enhanced order modal opening with payment validation
    function openOrderModalWithValidation(orderId) {
        // First check payment validation
        checkPaymentValidation(orderId, function(validationData) {
            openOrderModal(orderId, validationData);
        });
    }
    
    // Override the original openOrderModal function
    const originalOpenOrderModal = window.openOrderModal;
    window.openOrderModal = function(orderId, validationData = null) {
        if (validationData) {
            // Call original function first
            originalOpenOrderModal(orderId);
            
            // Then add validation warnings
            setTimeout(function() {
                const modalContent = document.querySelector('#orderModal .modal-body');
                if (modalContent && validationData.validation) {
                    const warningHtml = showPaymentValidationWarning(validationData.validation);
                    if (warningHtml) {
                        modalContent.insertAdjacentHTML('afterbegin', warningHtml);
                    }
                }
            }, 100);
        } else {
            // If no validation data provided, get it first
            openOrderModalWithValidation(orderId);
        }
    };
    
    // Enhanced status update with validation
    function updateOrderStatusWithValidation(orderId) {
        const form = document.getElementById('updateStatusForm');
        const formData = new FormData(form);
        const newStatus = formData.get('status');
        
        // Validate the status update first
        validateOrderStatusUpdate(orderId, newStatus, function(validation) {
            if (!validation.allowed) {
                alert('Cannot update status: ' + validation.message);
                return;
            }
            
            // If validation passes, proceed with update
            updateOrderStatus(orderId);
        });
    }
    
    // Override the original updateOrderStatus function
    const originalUpdateOrderStatus = window.updateOrderStatus;
    window.updateOrderStatus = function(orderId) {
        updateOrderStatusWithValidation(orderId);
    };
    
    // Add payment status indicators to order rows
    function addPaymentStatusIndicators() {
        document.querySelectorAll('.order-row').forEach(function(row) {
            const orderId = row.dataset.orderId;
            const paymentStatus = row.dataset.paymentStatus;
            const orderStatus = row.dataset.orderStatus;
            
            // Add visual indicators based on payment validation
            if ((orderStatus === 'processing' || orderStatus === 'shipped') && paymentStatus !== 'completed') {
                row.classList.add('payment-warning');
                row.style.borderLeft = '4px solid #ff9800';
            }
            
            if (orderStatus === 'delivered' && paymentStatus !== 'completed') {
                row.classList.add('payment-error');
                row.style.borderLeft = '4px solid #f44336';
            }
        });
    }
    
    // Initialize payment validation on page load
    document.addEventListener('DOMContentLoaded', function() {
        addPaymentStatusIndicators();
        
        // Add payment validation legend
        const orderTable = document.querySelector('.orders-table');
        if (orderTable) {
            const legend = `
                <div class="payment-validation-legend" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                    <strong>Payment Validation Legend:</strong>
                    <span style="margin-left: 15px;"><span style="width: 20px; height: 3px; background: #ff9800; display: inline-block; margin-right: 5px;"></span>Payment pending for processing/shipped orders</span>
                    <span style="margin-left: 15px;"><span style="width: 20px; height: 3px; background: #f44336; display: inline-block; margin-right: 5px;"></span>Payment required for delivered orders</span>
                </div>
            `;
            orderTable.insertAdjacentHTML('beforebegin', legend);
        }
    });
    
    </script>
    
    <style>
    .payment-validation-alert {
        border-radius: 5px;
        padding: 12px;
        margin-bottom: 15px;
    }
    
    .payment-warning {
        background-color: #fff3cd !important;
    }
    
    .payment-error {
        background-color: #f8d7da !important;
    }
    
    .order-row.payment-warning:hover {
        background-color: #ffeaa7 !important;
    }
    
    .order-row.payment-error:hover {
        background-color: #fab1c1 !important;
    }
    
    .payment-validation-legend {
        font-size: 0.9em;
        color: #6c757d;
    }
    
    .payment-validation-legend span {
        margin-right: 15px;
    }
    </style>
    <?php
}

// Helper function to get payment validation status for orders
function getOrderPaymentValidationStatus($order_id, $conn) {
    require_once ROOT_PATH . '/api/helpers/PaymentValidationHelper.php';
    $validator = new PaymentValidationHelper($conn);
    return $validator->validateOrderForProcessing($order_id);
}

// Function to display payment status badge with validation
function getPaymentStatusBadgeWithValidation($payment_status, $order_status, $validation_result) {
    $badge_class = '';
    $icon = '';
    $tooltip = '';
    
    switch($payment_status) {
        case 'completed':
        case 'paid':
            $badge_class = 'badge-success';
            $icon = 'fa-check';
            $tooltip = 'Payment completed';
            break;
        case 'pending':
            $badge_class = 'badge-warning';
            $icon = 'fa-clock';
            $tooltip = 'Payment pending';
            break;
        case 'failed':
            $badge_class = 'badge-danger';
            $icon = 'fa-times';
            $tooltip = 'Payment failed';
            break;
        default:
            $badge_class = 'badge-secondary';
            $icon = 'fa-question';
            $tooltip = 'Unknown status';
    }
    
    // Add validation warning if needed
    if (!$validation_result['valid']) {
        $badge_class = 'badge-danger';
        $icon = 'fa-exclamation-triangle';
        $tooltip = $validation_result['message'];
    }
    
    return [
        'class' => $badge_class,
        'icon' => $icon,
        'tooltip' => $tooltip
    ];
}
