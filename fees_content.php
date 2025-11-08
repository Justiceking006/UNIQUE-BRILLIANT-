<?php
// fees_content.php - Student Fee Management
session_start();
require_once 'connect.php';

// Error logging function
function logError($message, $context = []) {
    error_log("FEES ERROR: " . $message . " | Context: " . json_encode($context));
}

// Check if user is student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'student') {
    logError("Unauthorized access attempt", ['session' => $_SESSION]);
    header('Location: login.php');
    exit;
}

try {
    $db = getDBConnection();
    if (!$db) {
        throw new Exception("Database connection failed");
    }
} catch (Exception $e) {
    logError("Database connection error: " . $e->getMessage());
    die("System temporarily unavailable. Please try again later.");
}

$student_id = $_SESSION['student_id'] ?? null;
$success_message = '';
$error_message = '';

// Validate student ID
if (!$student_id || !is_numeric($student_id)) {
    logError("Invalid student ID in session", ['student_id' => $student_id, 'session' => $_SESSION]);
    $error_message = "Invalid student session. Please login again.";
}

// Handle receipt upload for fee payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    try {
        if (!$student_id) {
            throw new Exception("Student session expired. Please login again.");
        }
        
        $term = sanitize($_POST['term'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = sanitize($_POST['payment_method'] ?? '');
        $transaction_ref = sanitize($_POST['transaction_ref'] ?? '');
        
        // Validate inputs
        if (empty($term) || $amount <= 0 || empty($payment_method)) {
            throw new Exception("Please fill all required fields with valid data.");
        }
        
        // Handle file upload
        if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== 0) {
            throw new Exception("Please select a valid receipt file.");
        }
        
        $file = $_FILES['receipt'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception("Invalid file type. Allowed: JPG, PNG, GIF, PDF");
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("File size too large. Maximum 5MB allowed.");
        }
        
        $upload_dir = 'uploads/fee_receipts/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Cannot create upload directory.");
            }
        }
        
        $new_filename = 'fee_receipt_' . $student_id . '_' . time() . '.' . $file_extension;
        $file_path = $upload_dir . $new_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception("Error uploading file. Please try again.");
        }
        
        // Create payment transaction
        $stmt = $db->prepare("
            INSERT INTO fee_transactions 
            (student_id, term, fee_type, description, amount, transaction_type, status, receipt_image, receipt_filename, created_at)
            VALUES (?, ?, 'tuition', ?, ?, 'payment', 'unpaid', ?, ?, NOW())
        ");
        $description = "Fee Payment - " . $payment_method . ($transaction_ref ? " (Ref: $transaction_ref)" : "");
        
        if (!$stmt->execute([$student_id, $term, $description, $amount, $file_path, $new_filename])) {
            throw new Exception("Failed to save payment record.");
        }
        
        $success_message = "Payment receipt uploaded successfully! Waiting for admin approval.";
        logError("Payment submitted successfully", ['student_id' => $student_id, 'amount' => $amount, 'term' => $term]);
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        logError("Payment submission failed: " . $e->getMessage(), [
            'student_id' => $student_id,
            'post_data' => $_POST
        ]);
    }
}

// Initialize variables with default values
$fee_summary = [];
$issued_fees = [];
$transactions = [];
$overall_issued = 0;
$overall_paid = 0;
$overall_balance = 0;
$overall_pending = 0;

try {
    if ($student_id) {
        // Get detailed fee breakdown by term
        $detailed_summary_stmt = $db->prepare("
            SELECT 
                term,
                SUM(CASE WHEN transaction_type = 'fee_issued' THEN amount ELSE 0 END) as issued_fees,
                SUM(CASE WHEN transaction_type = 'fee_issued' THEN 1 ELSE 0 END) as fee_count,
                SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN transaction_type = 'payment' AND status = 'unpaid' THEN amount ELSE 0 END) as pending_payments,
                SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN transaction_type = 'payment' AND status = 'unpaid' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN transaction_type = 'fee_issued' THEN amount ELSE 0 END) - 
                SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as balance_due
            FROM fee_transactions 
            WHERE student_id = ?
            GROUP BY term
            ORDER BY 
                CASE term 
                    WHEN 'first_term' THEN 1
                    WHEN 'second_term' THEN 2  
                    WHEN 'third_term' THEN 3
                END
        ");
        
        if (!$detailed_summary_stmt->execute([$student_id])) {
            throw new Exception("Failed to execute fee summary query");
        }
        
        $fee_summary = $detailed_summary_stmt->fetchAll();

        // Get issued fees (what student actually needs to pay)
        $issued_fees_stmt = $db->prepare("
            SELECT * FROM fee_transactions 
            WHERE student_id = ? 
            AND transaction_type = 'fee_issued'
            ORDER BY term, created_at DESC
        ");
        
        if (!$issued_fees_stmt->execute([$student_id])) {
            throw new Exception("Failed to execute issued fees query");
        }
        
        $issued_fees = $issued_fees_stmt->fetchAll();

        // Get all fee transactions for history
        $transactions_stmt = $db->prepare("
            SELECT * FROM fee_transactions 
            WHERE student_id = ? 
            ORDER BY created_at DESC
        ");
        
        if (!$transactions_stmt->execute([$student_id])) {
            throw new Exception("Failed to execute transactions query");
        }
        
        $transactions = $transactions_stmt->fetchAll();

        // Calculate overall totals
        foreach ($fee_summary as $summary) {
            $overall_issued += $summary['issued_fees'];
            $overall_paid += $summary['total_paid'];
            $overall_balance += $summary['balance_due'];
            $overall_pending += $summary['pending_payments'];
        }
    }
} catch (PDOException $e) {
    $error_message = "Error loading fee data. Please try again later.";
    logError("Database query failed: " . $e->getMessage(), [
        'student_id' => $student_id,
        'error_info' => $db->errorInfo()
    ]);
} catch (Exception $e) {
    $error_message = $e->getMessage();
    logError("Fee data processing failed: " . $e->getMessage(), ['student_id' => $student_id]);
}

// Check if student has any issued fees
$has_issued_fees = $overall_issued > 0;
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210]">Fee Management</h1>
    <p class="text-[#8d6a5e]">View and manage your school fees</p>
</div>

<!-- System Error Messages -->
<?php if (!empty($error_message)): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <div>
        <p class="text-sm font-medium text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
        <p class="text-xs text-red-600 mt-1">If this persists, please contact administration.</p>
    </div>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-red-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<!-- Success Messages -->
<?php if (!empty($success_message)): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<!-- Database Connection Warning -->
<?php if (!$db): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-yellow-100 p-4 border border-yellow-300">
    <span class="material-symbols-outlined text-yellow-600 text-2xl">warning</span>
    <div>
        <p class="text-sm font-medium text-yellow-700">Temporary system issue</p>
        <p class="text-xs text-yellow-600 mt-1">Some features may be unavailable. Please try again later.</p>
    </div>
</div>
<?php endif; ?>

<!-- Overall Fee Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Issued Fees</p>
                <p class="text-xl font-bold text-[#181210]">₦<?php echo number_format($overall_issued, 2); ?></p>
                <p class="text-xs text-[#8d6a5e]">Total amount billed</p>
            </div>
            <span class="material-symbols-outlined text-2xl text-blue-500">receipt</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Approved Payments</p>
                <p class="text-xl font-bold text-green-600">₦<?php echo number_format($overall_paid, 2); ?></p>
                <p class="text-xs text-[#8d6a5e]">Verified payments</p>
            </div>
            <span class="material-symbols-outlined text-2xl text-green-500">payments</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Pending Approval</p>
                <p class="text-xl font-bold text-orange-600">₦<?php echo number_format($overall_pending, 2); ?></p>
                <p class="text-xs text-[#8d6a5e]">Awaiting verification</p>
            </div>
            <span class="material-symbols-outlined text-2xl text-orange-500">pending</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Balance Due</p>
                <p class="text-xl font-bold <?php echo $overall_balance > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                    ₦<?php echo number_format($overall_balance, 2); ?>
                </p>
                <p class="text-xs text-[#8d6a5e]">
                    <?php echo $overall_balance > 0 ? 'Amount to pay' : 'All fees cleared'; ?>
                </p>
            </div>
            <span class="material-symbols-outlined text-2xl <?php echo $overall_balance > 0 ? 'text-red-500' : 'text-green-500'; ?>">
                <?php echo $overall_balance > 0 ? 'warning' : 'check_circle'; ?>
            </span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Make Payment Section -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933]">payments</span>
            Make Payment
        </h3>
        
        <?php if (!$has_issued_fees): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <div class="flex items-center">
                    <span class="material-symbols-outlined text-yellow-600 mr-2">info</span>
                    <p class="text-yellow-800 text-sm">No fees have been issued to your account yet. Please check back later or contact the administration.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Payment Instructions -->
        <div class="bg-[#f8f6f5] rounded-lg p-4 mb-4">
            <h4 class="font-bold text-[#181210] mb-2">Payment Details:</h4>
            <div class="text-sm space-y-1">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Bank:</span>
                    <span class="font-medium">Union Bank</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Account Number:</span>
                    <span class="font-medium">0060368349</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Account Name:</span>
                    <span class="font-medium">Unique Brilliant Schools</span>
                </div>
            </div>
            <p class="text-xs text-[#8d6a5e] mt-2">
                Make payment first, then upload your receipt below for verification.
            </p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="return validatePaymentForm()">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Term</label>
                    <select name="term" required 
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Select Term</option>
                        <option value="first_term">First Term</option>
                        <option value="second_term">Second Term</option>
                        <option value="third_term">Third Term</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Amount Paid (₦)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter amount paid">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Method</label>
                    <select name="payment_method" required
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Select Method</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="pos">POS</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                
            </div>
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Upload Payment Receipt</label>
                <div class="border-2 border-dashed border-[#e7deda] rounded-lg p-6 bg-[#f8f6f5] hover:bg-[#f0ecea] transition-colors cursor-pointer text-center"
                     onclick="document.getElementById('receiptFile').click()">
                    <span class="material-symbols-outlined text-3xl text-[#8d6a5e] mb-2">cloud_upload</span>
                    <p class="text-[#181210] font-medium">Click to upload receipt</p>
                    <p class="text-[#8d6a5e] text-sm mt-1">JPG, PNG, GIF, or PDF (Max 5MB)</p>
                    <input type="file" id="receiptFile" name="receipt" accept=".jpg,.jpeg,.png,.gif,.pdf" 
                           class="hidden" onchange="updateFileName(this)" required>
                </div>
                <div id="fileNameDisplay" class="text-center text-sm text-[#8d6a5e] mt-2 hidden">
                    Selected file: <span id="fileName" class="font-medium text-[#ff6933]"></span>
                </div>
            </div>
            
            <button type="submit" name="submit_payment" 
                    class="w-full h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center gap-2"
                    <?php echo !$has_issued_fees ? 'disabled' : ''; ?>>
                <span class="material-symbols-outlined">upload</span>
                <?php echo $has_issued_fees ? 'Submit Payment Receipt' : 'No Fees Issued'; ?>
            </button>
            
            <?php if (!$has_issued_fees): ?>
                <p class="text-xs text-[#8d6a5e] text-center">
                    Payment submission is disabled until fees are issued to your account.
                </p>
            <?php endif; ?>
        </form>
    </div>

    <!-- Fee Breakdown & History -->
    <div class="space-y-6">
        <!-- Issued Fees Details -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">receipt_long</span>
                Issued Fees Details
            </h3>
            
            <div class="space-y-4">
                <?php if (empty($issued_fees)): ?>
                    <div class="text-center py-6">
                        <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">receipt_long</span>
                        <p class="text-[#8d6a5e]">No fees have been issued yet</p>
                        <p class="text-sm text-[#8d6a5e] mt-1">Fees will appear here once issued by administration</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($issued_fees as $fee): ?>
                        <div class="p-4 bg-[#f8f6f5] rounded-lg border-l-4 border-blue-500">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($fee['description']); ?></p>
                                    <p class="text-sm text-[#8d6a5e] capitalize">
                                        <?php echo str_replace('_', ' ', $fee['term']); ?> • 
                                        Issued: <?php echo date('M j, Y', strtotime($fee['created_at'])); ?>
                                    </p>
                                </div>
                                <p class="text-lg font-bold text-blue-600">
                                    ₦<?php echo number_format($fee['amount'], 2); ?>
                                </p>
                            </div>
                            <?php if ($fee['due_date']): ?>
                                <p class="text-xs text-[#8d6a5e]">
                                    Due: <?php echo date('M j, Y', strtotime($fee['due_date'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Term-wise Summary -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">analytics</span>
                Term-wise Summary
            </h3>
            
            <div class="space-y-4">
                <?php if (empty($fee_summary)): ?>
                    <div class="text-center py-6">
                        <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">analytics</span>
                        <p class="text-[#8d6a5e]">No fee records found</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($fee_summary as $term_data): ?>
                        <?php if ($term_data['issued_fees'] > 0): ?>
                            <div class="p-4 bg-[#f8f6f5] rounded-lg">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-bold text-[#181210] capitalize">
                                        <?php echo str_replace('_', ' ', $term_data['term']); ?>
                                    </h4>
                                    <span class="text-sm font-medium <?php echo $term_data['balance_due'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $term_data['balance_due'] > 0 ? 'Balance: ₦' . number_format($term_data['balance_due'], 2) : 'Paid in Full'; ?>
                                    </span>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                                    <div class="text-center p-2 bg-white rounded border">
                                        <p class="text-[#8d6a5e]">Issued</p>
                                        <p class="font-bold text-blue-600">₦<?php echo number_format($term_data['issued_fees'], 2); ?></p>
                                    </div>
                                    <div class="text-center p-2 bg-white rounded border">
                                        <p class="text-[#8d6a5e]">Approved</p>
                                        <p class="font-bold text-green-600">₦<?php echo number_format($term_data['total_paid'], 2); ?></p>
                                    </div>
                                    <div class="text-center p-2 bg-white rounded border">
                                        <p class="text-[#8d6a5e]">Pending</p>
                                        <p class="font-bold text-orange-600">₦<?php echo number_format($term_data['pending_payments'], 2); ?></p>
                                    </div>
                                    <div class="text-center p-2 bg-white rounded border">
                                        <p class="text-[#8d6a5e]">Balance</p>
                                        <p class="font-bold <?php echo $term_data['balance_due'] > 0 ? 'text-red-600' : 'text-green-600'; ?>">
                                            ₦<?php echo number_format($term_data['balance_due'], 2); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Progress bar -->
                                <div class="mt-3">
                                    <div class="flex justify-between text-xs text-[#8d6a5e] mb-1">
                                        <span>Payment Progress</span>
                                        <span><?php echo $term_data['issued_fees'] > 0 ? round(($term_data['total_paid'] / $term_data['issued_fees']) * 100, 1) : 0; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-600 h-2 rounded-full" 
                                             style="width: <?php echo $term_data['issued_fees'] > 0 ? min(100, ($term_data['total_paid'] / $term_data['issued_fees']) * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mt-6">
    <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">history</span>
        All Transactions
    </h3>
    
    <div class="space-y-3 max-h-96 overflow-y-auto">
        <?php if (empty($transactions)): ?>
            <div class="text-center py-8">
                <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">receipt_long</span>
                <p class="text-[#8d6a5e]">No transactions yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($transactions as $transaction): ?>
                <div class="flex items-center justify-between p-3 bg-[#f8f6f5] rounded-lg">
                    <div class="flex items-center space-x-3">
                        <span class="material-symbols-outlined 
                            <?php 
                            if ($transaction['transaction_type'] === 'fee_issued') {
                                echo 'text-blue-500';
                            } else if ($transaction['status'] === 'paid') {
                                echo 'text-green-500';
                            } else if ($transaction['status'] === 'unpaid') {
                                echo 'text-orange-500';
                            } else {
                                echo 'text-red-500';
                            }
                            ?>">
                            <?php 
                            if ($transaction['transaction_type'] === 'fee_issued') {
                                echo 'receipt_long';
                            } else if ($transaction['status'] === 'paid') {
                                echo 'check_circle';
                            } else if ($transaction['status'] === 'unpaid') {
                                echo 'pending';
                            } else {
                                echo 'cancel';
                            }
                            ?>
                        </span>
                        <div>
                            <p class="font-medium text-[#181210] text-sm">
                                <?php echo htmlspecialchars($transaction['description']); ?>
                            </p>
                            <p class="text-xs text-[#8d6a5e] capitalize">
                                <?php echo str_replace('_', ' ', $transaction['term']); ?> • 
                                <?php echo $transaction['transaction_type'] === 'fee_issued' ? 'Fee Issued' : 'Payment'; ?> •
                                <?php echo date('M j, Y', strtotime($transaction['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-[#181210] text-sm
                            <?php echo $transaction['transaction_type'] === 'payment' ? 'text-green-600' : 'text-blue-600'; ?>">
                            <?php echo $transaction['transaction_type'] === 'payment' ? '+' : ''; ?>
                            ₦<?php echo number_format($transaction['amount'], 2); ?>
                        </p>
                        <p class="text-xs text-[#8d6a5e] capitalize">
                            <?php 
                            if ($transaction['transaction_type'] === 'fee_issued') {
                                echo 'Fee Charged';
                            } else {
                                echo $transaction['status'];
                            }
                            ?>
                        </p>
                        <?php if ($transaction['receipt_image']): ?>
                            <a href="<?php echo $transaction['receipt_image']; ?>" target="_blank" 
                               class="text-xs text-[#ff6933] hover:underline">View Receipt</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function updateFileName(input) {
    const fileNameDisplay = document.getElementById('fileNameDisplay');
    const fileName = document.getElementById('fileName');
    
    if (input.files.length > 0) {
        fileName.textContent = input.files[0].name;
        fileNameDisplay.classList.remove('hidden');
    } else {
        fileNameDisplay.classList.add('hidden');
    }
}

function validatePaymentForm() {
    const amount = document.querySelector('input[name="amount"]').value;
    const term = document.querySelector('select[name="term"]').value;
    const method = document.querySelector('select[name="payment_method"]').value;
    const file = document.querySelector('input[name="receipt"]').files[0];
    
    if (!term) {
        alert('Please select a term');
        return false;
    }
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid amount');
        return false;
    }
    
    if (!method) {
        alert('Please select a payment method');
        return false;
    }
    
    if (!file) {
        alert('Please select a receipt file');
        return false;
    }
    
    // Check file size
    if (file.size > 5 * 1024 * 1024) {
        alert('File size must be less than 5MB');
        return false;
    }
    
    return confirm('Are you sure you want to submit this payment?');
}

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