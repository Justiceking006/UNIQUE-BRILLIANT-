<?php
// pay_staff_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $staff_id = intval($_POST['staff_id']);
    $payment_date = $_POST['payment_date'];
    $payment_period = $_POST['payment_period'];
    $gross_salary = floatval($_POST['gross_salary']);
    $tax_deduction = floatval($_POST['tax_deduction']);
    $pension_deduction = floatval($_POST['pension_deduction']);
    $other_deductions = floatval($_POST['other_deductions']);
    $payment_method = $_POST['payment_method'];
    $notes = $_POST['notes'];
    
    // Calculate net pay
    $net_pay = $gross_salary - $tax_deduction - $pension_deduction - $other_deductions;
    
    // Handle receipt upload
    $receipt_filename = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/receipts/';
        
        // Create uploads directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'webp'];
        
        if (in_array(strtolower($file_extension), $allowed_extensions)) {
            // Generate unique filename
            $receipt_filename = 'receipt_' . time() . '_' . $staff_id . '.' . $file_extension;
            $upload_path = $upload_dir . $receipt_filename;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                // File uploaded successfully
            } else {
                $error_message = "Failed to upload receipt file.";
            }
        } else {
            $error_message = "Invalid file type. Allowed: JPG, PNG, GIF, PDF, WEBP";
        }
    }
    
    if (!isset($error_message)) {
        try {
            $stmt = $db->prepare("INSERT INTO staff_payments (staff_id, payment_date, payment_period, gross_salary, tax_deduction, pension_deduction, other_deductions, net_pay, payment_method, receipt_filename, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?)");
            
            if ($stmt->execute([$staff_id, $payment_date, $payment_period, $gross_salary, $tax_deduction, $pension_deduction, $other_deductions, $net_pay, $payment_method, $receipt_filename, $notes, $_SESSION['user_id']])) {
                $success_message = "Payment processed successfully! Net Pay: ₦" . number_format($net_pay, 2);
            } else {
                $error_message = "Failed to process payment. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle account details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account'])) {
    $staff_id = intval($_POST['staff_id']);
    $account_name = $_POST['account_name'];
    $account_number = $_POST['account_number'];
    $bank_name = $_POST['bank_name'];
    
    try {
        $stmt = $db->prepare("UPDATE staff SET account_name = ?, account_number = ?, bank_name = ?, account_updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$account_name, $account_number, $bank_name, $staff_id])) {
            $success_message = "Account details updated successfully!";
            // Refresh staff data to get updated account info
            $staff_members = $db->query("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM staff WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();
        } else {
            $error_message = "Failed to update account details.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get all active staff members with account details
$staff_members = $db->query("SELECT *, CONCAT(first_name, ' ', last_name) as full_name FROM staff WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get recent payments with receipt info
$recent_payments = $db->query("SELECT sp.*, s.first_name, s.last_name, s.staff_id FROM staff_payments sp JOIN staff s ON sp.staff_id = s.id ORDER BY sp.created_at DESC LIMIT 5")->fetchAll();

// Get payment statistics
$total_payments = $db->query("SELECT COUNT(*) FROM staff_payments WHERE status = 'paid'")->fetchColumn();
$total_paid = $db->query("SELECT SUM(net_pay) FROM staff_payments WHERE status = 'paid'")->fetchColumn();
$this_month_payments = $db->query("SELECT COUNT(*) FROM staff_payments WHERE status = 'paid' AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())")->fetchColumn();
?>

<!-- Pay Staff Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Pay Staff</h1>
            <p class="text-[#8d6a5e]">Process individual staff salary payments</p>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (isset($success_message)): ?>
    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center">
        <span class="material-symbols-outlined mr-2 text-green-500">check_circle</span>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center">
        <span class="material-symbols-outlined mr-2 text-red-500">error</span>
        <?php echo $error_message; ?>
    </div>
<?php endif; ?>

<!-- Quick Stats -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Payments</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_payments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">payments</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Amount Paid</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($total_paid, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">attach_money</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">This Month</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $this_month_payments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">calendar_month</span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Payment Form -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933]">payments</span>
            Process Payment
        </h3>
        
        <form method="POST" id="paymentForm" enctype="multipart/form-data">
            <!-- Staff Selection -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Staff Member</label>
                <select name="staff_id" id="staffSelect" required 
                        class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    <option value="">Choose a staff member...</option>
                    <?php foreach ($staff_members as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" 
                                data-salary="<?php echo $staff['salary'] ?? 0; ?>"
                                data-account-name="<?php echo htmlspecialchars($staff['account_name'] ?? ''); ?>"
                                data-account-number="<?php echo htmlspecialchars($staff['account_number'] ?? ''); ?>"
                                data-bank-name="<?php echo htmlspecialchars($staff['bank_name'] ?? ''); ?>"
                                data-account-updated="<?php echo $staff['account_updated_at'] ?? ''; ?>">
                            <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?> 
                            (<?php echo htmlspecialchars($staff['staff_id']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Date</label>
                    <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Period</label>
                    <input type="text" name="payment_period" value="<?php echo date('F Y'); ?>" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="e.g., November 2025">
                </div>
            </div>

            <!-- Salary Details -->
            <div class="bg-[#f8f6f5] rounded-lg p-4 mb-4">
                <h4 class="font-semibold text-[#181210] mb-3 flex items-center text-sm">
                    <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">calculate</span>
                    Salary Details
                </h4>
                
                <div class="space-y-3">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Gross Salary (₦)</label>
                        <input type="number" name="gross_salary" id="grossSalary" step="0.01" required
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Tax (₦)</label>
                            <input type="number" name="tax_deduction" id="taxDeduction" step="0.01" value="0"
                                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Pension (₦)</label>
                            <input type="number" name="pension_deduction" id="pensionDeduction" step="0.01" value="0"
                                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Other (₦)</label>
                            <input type="number" name="other_deductions" id="otherDeductions" step="0.01" value="0"
                                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        </div>
                    </div>
                    
                    <!-- Net Pay Display -->
                    <div class="bg-white rounded-lg p-3 border border-[#e7deda]">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-[#8d6a5e]">Net Pay:</span>
                            <span id="netPayDisplay" class="text-lg font-bold text-[#181210]">₦0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Method -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Method</label>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <label class="flex items-center p-3 border border-[#e7deda] rounded-lg cursor-pointer has-[:checked]:border-[#ff6933] has-[:checked]:bg-[#ff6933]/5">
                        <input type="radio" name="payment_method" value="bank_transfer" class="hidden" checked>
                        <span class="flex items-center text-sm">
                            <span class="material-symbols-outlined mr-2 text-base">account_balance</span>
                            Bank Transfer
                        </span>
                    </label>
                    <label class="flex items-center p-3 border border-[#e7deda] rounded-lg cursor-pointer has-[:checked]:border-[#ff6933] has-[:checked]:bg-[#ff6933]/5">
                        <input type="radio" name="payment_method" value="cash" class="hidden">
                        <span class="flex items-center text-sm">
                            <span class="material-symbols-outlined mr-2 text-base">payments</span>
                            Cash
                        </span>
                    </label>
                    <label class="flex items-center p-3 border border-[#e7deda] rounded-lg cursor-pointer has-[:checked]:border-[#ff6933] has-[:checked]:bg-[#ff6933]/5">
                        <input type="radio" name="payment_method" value="cheque" class="hidden">
                        <span class="flex items-center text-sm">
                            <span class="material-symbols-outlined mr-2 text-base">description</span>
                            Cheque
                        </span>
                    </label>
                </div>

                <!-- Account Details Section -->
                <div id="accountDetailsSection" class="hidden bg-[#f8f6f5] rounded-lg p-4 border border-[#e7deda]">
                    <div class="flex items-center justify-between mb-3">
                        <h4 class="font-semibold text-[#181210] flex items-center text-sm">
                            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">account_balance</span>
                            Staff Account Details
                        </h4>
                        <button type="button" id="editAccountBtn" 
                                class="flex items-center text-sm text-[#ff6933] hover:text-[#ff6933]/80 transition-colors">
                            <span class="material-symbols-outlined mr-1 text-base">edit</span>
                            Edit
                        </button>
                    </div>
                    
                    <!-- Read-only Account Details -->
                    <div id="readonlyAccountDetails" class="space-y-2">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-[#8d6a5e] mb-1">Account Name</label>
                                <div class="p-2 bg-white rounded border border-[#e7deda] text-sm text-[#181210]" id="displayAccountName">-</div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#8d6a5e] mb-1">Account Number</label>
                                <div class="p-2 bg-white rounded border border-[#e7deda] text-sm text-[#181210]" id="displayAccountNumber">-</div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#8d6a5e] mb-1">Bank Name</label>
                                <div class="p-2 bg-white rounded border border-[#e7deda] text-sm text-[#181210]" id="displayBankName">-</div>
                            </div>
                        </div>
                        <p class="text-xs text-[#8d6a5e] mt-2" id="lastUpdatedText"></p>
                    </div>

                    <!-- Editable Account Details Form -->
                    <div id="editableAccountDetails" class="hidden space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-[#8d6a5e] mb-1">Account Name</label>
                                <input type="text" name="account_name" id="accountNameInput"
                                       class="w-full h-10 px-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#8d6a5e] mb-1">Account Number</label>
                                <input type="text" name="account_number" id="accountNumberInput"
                                       class="w-full h-10 px-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#8d6a5e] mb-1">Bank Name</label>
                                <input type="text" name="bank_name" id="bankNameInput"
                                       class="w-full h-10 px-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] text-sm">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" name="update_account" 
                                    class="flex-1 h-10 rounded-lg bg-[#ff6933] text-white font-medium hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center text-sm">
                                <span class="material-symbols-outlined mr-1 text-base">save</span>
                                Save Changes
                            </button>
                            <button type="button" id="cancelEditBtn"
                                    class="flex-1 h-10 rounded-lg border border-[#e7deda] text-[#8d6a5e] font-medium hover:bg-[#f8f6f5] transition-colors text-sm">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Upload -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Upload Payment Receipt</label>
                <div class="border-2 border-dashed border-[#e7deda] rounded-lg p-6 text-center hover:border-[#ff6933] transition-colors">
                    <input type="file" name="receipt" id="receiptInput" accept=".jpg,.jpeg,.png,.gif,.pdf,.webp" 
                           class="hidden">
                    <div id="receiptUploadArea" class="cursor-pointer">
                        <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">receipt</span>
                        <p class="text-sm text-[#8d6a5e] mb-1">
                            <span class="text-[#ff6933] font-medium">Click to upload</span> or drag and drop
                        </p>
                        <p class="text-xs text-[#8d6a5e]">JPG, PNG, GIF, PDF, WEBP (Max: 5MB)</p>
                    </div>
                    <div id="receiptPreview" class="hidden mt-4">
                        <div class="flex items-center justify-between bg-[#f8f6f5] rounded-lg p-3">
                            <div class="flex items-center">
                                <span class="material-symbols-outlined text-[#ff6933] mr-2">receipt</span>
                                <div>
                                    <p class="text-sm font-medium text-[#181210]" id="fileName"></p>
                                    <p class="text-xs text-[#8d6a5e]" id="fileSize"></p>
                                </div>
                            </div>
                            <button type="button" id="removeReceipt" class="text-red-500 hover:text-red-700">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Notes (Optional)</label>
                <textarea name="notes" rows="3"
                          class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                          placeholder="Additional payment notes..."></textarea>
            </div>

            <!-- Submit Button -->
            <button type="submit" name="process_payment"
                    class="w-full h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined mr-2">check_circle</span>
                Process Payment
            </button>
        </form>
    </div>

    <!-- Recent Payments & Staff Info -->
    <div class="space-y-6">
        <!-- Recent Payments -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">history</span>
                Recent Payments
            </h3>
            
            <?php if (empty($recent_payments)): ?>
                <div class="text-center py-8 text-[#8d6a5e]">
                    <span class="material-symbols-outlined text-4xl mb-2">payments</span>
                    <p class="text-sm">No recent payments</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_payments as $payment): ?>
                    <div class="flex items-center justify-between p-3 border border-[#e7deda] rounded-lg">
                        <div class="flex items-center">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 mr-3">
                                <span class="material-symbols-outlined text-green-500 text-sm">check_circle</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-[#181210]">
                                    <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                </p>
                                <p class="text-xs text-[#8d6a5e]"><?php echo htmlspecialchars($payment['payment_period']); ?></p>
                                <?php if ($payment['receipt_filename']): ?>
                                    <a href="uploads/receipts/<?php echo $payment['receipt_filename']; ?>" 
                                       target="_blank" 
                                       class="text-xs text-[#ff6933] hover:text-[#ff6933]/80 flex items-center mt-1">
                                        <span class="material-symbols-outlined mr-1 text-xs">receipt</span>
                                        View Receipt
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-[#181210]">₦<?php echo number_format($payment['net_pay'], 2); ?></p>
                            <p class="text-xs text-[#8d6a5e]"><?php echo date('M j', strtotime($payment['payment_date'])); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-4 text-center">
                    <a href="?page=payment-history" 
                       class="inline-flex items-center text-sm text-[#ff6933] hover:text-[#ff6933]/80 transition-colors">
                        <span>View All Payments</span>
                        <span class="material-symbols-outlined ml-1 text-base">arrow_forward</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">bolt</span>
                Quick Actions
            </h3>
            
            <div class="space-y-3">
                <a href="?page=payroll" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">receipt_long</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Bulk Payroll</p>
                        <p class="text-xs text-[#8d6a5e]">Process multiple staff payments</p>
                    </div>
                </a>
                
                <a href="?page=payment-history" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">history</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Payment History</p>
                        <p class="text-xs text-[#8d6a5e]">View all payment records</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Auto-fill gross salary when staff is selected
    document.getElementById('staffSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const salary = selectedOption.getAttribute('data-salary');
        if (salary && salary > 0) {
            document.getElementById('grossSalary').value = salary;
            calculateNetPay();
        }
        
        // Update account details
        updateAccountDetails();
    });

    // Calculate net pay in real-time
    function calculateNetPay() {
        const grossSalary = parseFloat(document.getElementById('grossSalary').value) || 0;
        const taxDeduction = parseFloat(document.getElementById('taxDeduction').value) || 0;
        const pensionDeduction = parseFloat(document.getElementById('pensionDeduction').value) || 0;
        const otherDeductions = parseFloat(document.getElementById('otherDeductions').value) || 0;
        
        const netPay = grossSalary - taxDeduction - pensionDeduction - otherDeductions;
        document.getElementById('netPayDisplay').textContent = '₦' + netPay.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Update account details display
    function updateAccountDetails() {
        const selectedOption = document.getElementById('staffSelect').options[document.getElementById('staffSelect').selectedIndex];
        const accountName = selectedOption.getAttribute('data-account-name');
        const accountNumber = selectedOption.getAttribute('data-account-number');
        const bankName = selectedOption.getAttribute('data-bank-name');
        const accountUpdated = selectedOption.getAttribute('data-account-updated');
        
        // Show/hide account details section based on whether staff is selected
        const accountSection = document.getElementById('accountDetailsSection');
        if (document.getElementById('staffSelect').value) {
            accountSection.classList.remove('hidden');
            
            // Update display fields
            document.getElementById('displayAccountName').textContent = accountName || '-';
            document.getElementById('displayAccountNumber').textContent = accountNumber || '-';
            document.getElementById('displayBankName').textContent = bankName || '-';
            
            // Update last updated text
            if (accountUpdated) {
                const updatedDate = new Date(accountUpdated);
                document.getElementById('lastUpdatedText').textContent = 'Last updated: ' + updatedDate.toLocaleDateString();
            } else {
                document.getElementById('lastUpdatedText').textContent = 'Account details not set';
            }
            
            // Update input fields for editing
            document.getElementById('accountNameInput').value = accountName || '';
            document.getElementById('accountNumberInput').value = accountNumber || '';
            document.getElementById('bankNameInput').value = bankName || '';
        } else {
            accountSection.classList.add('hidden');
        }
    }

    // Edit account details functionality
    document.getElementById('editAccountBtn').addEventListener('click', function() {
        document.getElementById('readonlyAccountDetails').classList.add('hidden');
        document.getElementById('editableAccountDetails').classList.remove('hidden');
    });

    document.getElementById('cancelEditBtn').addEventListener('click', function() {
        document.getElementById('readonlyAccountDetails').classList.remove('hidden');
        document.getElementById('editableAccountDetails').classList.add('hidden');
        // Reset form values to current data
        updateAccountDetails();
    });

    // Receipt upload functionality
    const receiptInput = document.getElementById('receiptInput');
    const receiptUploadArea = document.getElementById('receiptUploadArea');
    const receiptPreview = document.getElementById('receiptPreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeReceipt = document.getElementById('removeReceipt');

    receiptUploadArea.addEventListener('click', () => receiptInput.click());
    receiptUploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        receiptUploadArea.classList.add('border-[#ff6933]', 'bg-[#ff6933]/5');
    });
    receiptUploadArea.addEventListener('dragleave', () => {
        receiptUploadArea.classList.remove('border-[#ff6933]', 'bg-[#ff6933]/5');
    });
    receiptUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        receiptUploadArea.classList.remove('border-[#ff6933]', 'bg-[#ff6933]/5');
        if (e.dataTransfer.files.length) {
            receiptInput.files = e.dataTransfer.files;
            handleFileSelect();
        }
    });

    receiptInput.addEventListener('change', handleFileSelect);

    function handleFileSelect() {
        const file = receiptInput.files[0];
        if (file) {
            // Check file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                receiptInput.value = '';
                return;
            }

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            receiptUploadArea.classList.add('hidden');
            receiptPreview.classList.remove('hidden');
        }
    }

    removeReceipt.addEventListener('click', () => {
        receiptInput.value = '';
        receiptPreview.classList.add('hidden');
        receiptUploadArea.classList.remove('hidden');
    });

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Add event listeners for deduction fields
    ['grossSalary', 'taxDeduction', 'pensionDeduction', 'otherDeductions'].forEach(id => {
        document.getElementById(id).addEventListener('input', calculateNetPay);
    });

    // Initial calculations
    calculateNetPay();
    updateAccountDetails();
</script>