<?php
// approve_payments_content.php - Admin Payment Approval
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$success_message = '';
$error_message = '';

// Handle payment approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_payment'])) {
    $transaction_id = intval($_POST['transaction_id']);
    
    try {
        $stmt = $db->prepare("
            UPDATE fee_transactions 
            SET status = 'paid', approved_by = ?, approved_at = NOW() 
            WHERE id = ? AND transaction_type = 'payment' AND status = 'unpaid'
        ");
        $stmt->execute([$_SESSION['user_id'], $transaction_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "Payment approved successfully!";
        } else {
            $error_message = "Payment not found or already processed.";
        }
        
    } catch (PDOException $e) {
        $error_message = "Failed to approve payment: " . $e->getMessage();
    }
}

// Handle payment rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_payment'])) {
    $transaction_id = intval($_POST['transaction_id']);
    $rejection_reason = sanitize($_POST['rejection_reason']);
    
    try {
        $stmt = $db->prepare("
            UPDATE fee_transactions 
            SET status = 'cancelled', admin_notes = ?, approved_by = ?, approved_at = NOW() 
            WHERE id = ? AND transaction_type = 'payment' AND status = 'unpaid'
        ");
        $stmt->execute([$rejection_reason, $_SESSION['user_id'], $transaction_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = "Payment rejected successfully!";
        } else {
            $error_message = "Payment not found or already processed.";
        }
        
    } catch (PDOException $e) {
        $error_message = "Failed to reject payment: " . $e->getMessage();
    }
}

// Get pending payments with student details
$pending_stmt = $db->prepare("
    SELECT 
        ft.*,
        s.first_name,
        s.last_name, 
        s.student_code,
        s.class,
        s.section,
        s.level,
        s.department
    FROM fee_transactions ft
    JOIN students s ON ft.student_id = s.id
    WHERE ft.transaction_type = 'payment' 
    AND ft.status = 'unpaid'
    ORDER BY ft.created_at DESC
");
$pending_stmt->execute();
$pending_payments = $pending_stmt->fetchAll();

// Get recently approved payments for reference
$approved_stmt = $db->prepare("
    SELECT 
        ft.*,
        s.first_name,
        s.last_name,
        s.student_code,
        s.class,
        u.email as approved_by_email
    FROM fee_transactions ft
    JOIN students s ON ft.student_id = s.id
    LEFT JOIN users u ON ft.approved_by = u.id
    WHERE ft.transaction_type = 'payment' 
    AND ft.status = 'paid'
    ORDER BY ft.approved_at DESC
    LIMIT 10
");
$approved_stmt->execute();
$approved_payments = $approved_stmt->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210]">Approve Student Payments</h1>
    <p class="text-[#8d6a5e]">Review and approve pending fee payments after bank verification</p>
</div>

<!-- Success/Error Messages -->
<?php if (!empty($success_message)): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo $success_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <p class="text-sm font-medium text-red-700"><?php echo $error_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-red-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Pending Payments -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-[#181210] flex items-center">
                    <span class="material-symbols-outlined mr-2 text-orange-500">pending</span>
                    Pending Payments (<?php echo count($pending_payments); ?>)
                </h3>
                <div class="text-sm text-[#8d6a5e]">
                    Verify bank transfers before approving
                </div>
            </div>
            
            <?php if (empty($pending_payments)): ?>
                <div class="text-center py-12">
                    <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">check_circle</span>
                    <h3 class="text-xl font-bold text-[#181210] mb-2">All Caught Up!</h3>
                    <p class="text-[#8d6a5e] max-w-md mx-auto">
                        No pending payments to approve. Check back later for new payment submissions.
                    </p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pending_payments as $payment): ?>
                    <div class="flex items-start justify-between p-4 bg-[#f8f6f5] rounded-lg border border-orange-200">
                        <div class="flex items-start space-x-4 flex-1">
                            <!-- Receipt Preview -->
                            <?php if ($payment['receipt_image']): ?>
                            <div class="flex-shrink-0">
                                <a href="<?php echo $payment['receipt_image']; ?>" target="_blank" 
                                   class="block w-20 h-20 bg-white rounded-lg border-2 border-orange-300 hover:border-[#ff6933] transition-colors overflow-hidden">
                                    <?php if (strpos($payment['receipt_image'], '.pdf') !== false): ?>
                                        <div class="w-full h-full flex items-center justify-center bg-red-50">
                                            <span class="material-symbols-outlined text-red-500 text-2xl">picture_as_pdf</span>
                                        </div>
                                    <?php else: ?>
                                        <img src="<?php echo $payment['receipt_image']; ?>" 
                                             alt="Payment Receipt" 
                                             class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </a>
                                <p class="text-xs text-[#8d6a5e] text-center mt-1">View Receipt</p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Payment Details -->
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <p class="font-bold text-[#181210]">
                                            <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        </p>
                                        <p class="text-sm text-[#8d6a5e]">
                                            <?php echo $payment['student_code']; ?> • 
                                            <?php echo $payment['class']; ?> •
                                            <?php echo $payment['section']; ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xl font-bold text-green-600">
                                            ₦<?php echo number_format($payment['amount'], 2); ?>
                                        </p>
                                        <p class="text-xs text-[#8d6a5e] capitalize">
                                            <?php echo str_replace('_', ' ', $payment['term']); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <p class="text-sm text-[#181210] mb-2">
                                    <?php echo htmlspecialchars($payment['description']); ?>
                                </p>
                                
                                <div class="flex items-center text-xs text-[#8d6a5e]">
                                    <span class="material-symbols-outlined text-xs mr-1">schedule</span>
                                    Submitted: <?php echo date('M j, Y g:i A', strtotime($payment['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="flex flex-col space-y-2 ml-4">
                            <form method="POST" class="inline">
                                <input type="hidden" name="transaction_id" value="<?php echo $payment['id']; ?>">
                                <button type="submit" name="approve_payment" 
                                        class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium flex items-center justify-center">
                                    <span class="material-symbols-outlined mr-1 text-sm">check</span>
                                    Approve
                                </button>
                            </form>
                            
                            <button type="button" onclick="openRejectModal(<?php echo $payment['id']; ?>)" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-medium flex items-center justify-center">
                                <span class="material-symbols-outlined mr-1 text-sm">close</span>
                                Reject
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recently Approved & Instructions -->
    <div class="lg:col-span-1">
        <!-- Verification Instructions -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-blue-500">verified</span>
                Verification Process
            </h3>
            <div class="space-y-3 text-sm">
                <div class="flex items-start space-x-2">
                    <span class="material-symbols-outlined text-green-500 text-sm mt-0.5">check_circle</span>
                    <span>Check bank statement for matching amount</span>
                </div>
                <div class="flex items-start space-x-2">
                    <span class="material-symbols-outlined text-green-500 text-sm mt-0.5">check_circle</span>
                    <span>Verify student details match receipt</span>
                </div>
                <div class="flex items-start space-x-2">
                    <span class="material-symbols-outlined text-green-500 text-sm mt-0.5">check_circle</span>
                    <span>Ensure payment date is reasonable</span>
                </div>
                <div class="flex items-start space-x-2">
                    <span class="material-symbols-outlined text-green-500 text-sm mt-0.5">check_circle</span>
                    <span>Click approve only after verification</span>
                </div>
            </div>
        </div>

        <!-- Recently Approved -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-green-500">history</span>
                Recently Approved
            </h3>
            
            <div class="space-y-3 max-h-80 overflow-y-auto">
                <?php if (empty($approved_payments)): ?>
                    <p class="text-[#8d6a5e] text-sm text-center py-4">No recently approved payments</p>
                <?php else: ?>
                    <?php foreach ($approved_payments as $payment): ?>
                    <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex justify-between items-start mb-1">
                            <p class="font-medium text-[#181210] text-sm">
                                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                            </p>
                            <p class="text-sm font-bold text-green-600">
                                ₦<?php echo number_format($payment['amount'], 2); ?>
                            </p>
                        </div>
                        <p class="text-xs text-[#8d6a5e]">
                            <?php echo $payment['class']; ?> • 
                            <?php echo date('M j, g:i A', strtotime($payment['approved_at'])); ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="rejectModal">
    <div class="bg-white rounded-xl w-full max-w-md">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Reject Payment</h3>
                <button onclick="closeRejectModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4 space-y-4">
            <input type="hidden" name="transaction_id" id="rejectTransactionId">
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Reason for Rejection</label>
                <textarea name="rejection_reason" rows="4" required
                          class="w-full rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                          placeholder="Please provide a reason for rejecting this payment..."></textarea>
            </div>
            
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeRejectModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="reject_payment" 
                        class="flex-1 h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                    Reject Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Rejection Modal Functions
function openRejectModal(transactionId) {
    document.getElementById('rejectTransactionId').value = transactionId;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'rejectModal') {
        closeRejectModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});

// Auto-hide success messages after 5 seconds
setTimeout(() => {
    const successMessages = document.querySelectorAll('.bg-green-100');
    successMessages.forEach(msg => {
        msg.style.opacity = '0';
        msg.style.transition = 'opacity 0.5s ease';
        setTimeout(() => msg.remove(), 500);
    });
}, 5000);
</script>