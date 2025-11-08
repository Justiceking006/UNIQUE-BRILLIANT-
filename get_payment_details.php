<?php
// get_payment_details.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$payment_id = intval($_GET['id']);
$db = getDBConnection();

$stmt = $db->prepare("SELECT sp.*, s.first_name, s.last_name, s.staff_id, s.department, s.position, 
                             s.account_name, s.account_number, s.bank_name, u.email as processed_by
                      FROM staff_payments sp 
                      JOIN staff s ON sp.staff_id = s.id 
                      JOIN users u ON sp.created_by = u.id 
                      WHERE sp.id = ?");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    echo '<div class="text-center py-8 text-[#8d6a5e]">
            <span class="material-symbols-outlined text-4xl mb-2">error</span>
            <p>Payment not found</p>
          </div>';
    exit;
}
?>

<div class="space-y-6">
    <!-- Payment Header -->
    <div class="flex justify-between items-start">
        <div>
            <h4 class="text-xl font-bold text-[#181210]">Payment #<?php echo $payment['id']; ?></h4>
            <p class="text-[#8d6a5e]">Processed on <?php echo date('F j, Y \a\t g:i A', strtotime($payment['created_at'])); ?></p>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
            <?php echo $payment['status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                   ($payment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
            <?php echo ucfirst($payment['status']); ?>
        </span>
    </div>

    <!-- Staff Information -->
    <div class="bg-[#f8f6f5] rounded-lg p-4">
        <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">person</span>
            Staff Information
        </h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-[#8d6a5e]">Staff Name</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></p>
            </div>
            <div>
                <p class="text-sm text-[#8d6a5e]">Staff ID</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['staff_id']); ?></p>
            </div>
            <div>
                <p class="text-sm text-[#8d6a5e]">Department</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['department']); ?></p>
            </div>
            <div>
                <p class="text-sm text-[#8d6a5e]">Position</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['position']); ?></p>
            </div>
        </div>
    </div>

    <!-- Payment Details -->
    <div class="bg-[#f8f6f5] rounded-lg p-4">
        <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">payments</span>
            Payment Details
        </h5>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-[#8d6a5e]">Payment Date</p>
                <p class="font-medium text-[#181210]"><?php echo date('F j, Y', strtotime($payment['payment_date'])); ?></p>
            </div>
            <div>
                <p class="text-sm text-[#8d6a5e]">Payment Period</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['payment_period']); ?></p>
            </div>
            <div>
                <p class="text-sm text-[#8d6a5e]">Payment Method</p>
                <p class="font-medium text-[#181210]">
                    <span class="inline-flex items-center">
                        <span class="material-symbols-outlined mr-1 text-base">
                            <?php echo $payment['payment_method'] === 'bank_transfer' ? 'account_balance' : 
                                   ($payment['payment_method'] === 'cash' ? 'payments' : 'description'); ?>
                        </span>
                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                    </span>
                </p>
            </div>
            <div>
                <p class="text-sm text-[#8d6a5e]">Processed By</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['processed_by']); ?></p>
            </div>
        </div>
    </div>

    <!-- Salary Breakdown -->
    <div class="bg-[#f8f6f5] rounded-lg p-4">
        <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">calculate</span>
            Salary Breakdown
        </h5>
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-[#8d6a5e]">Gross Salary</span>
                <span class="font-medium text-[#181210]">₦<?php echo number_format($payment['gross_salary'], 2); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#8d6a5e]">Tax Deduction</span>
                <span class="font-medium text-[#181210]">-₦<?php echo number_format($payment['tax_deduction'], 2); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#8d6a5e]">Pension Deduction</span>
                <span class="font-medium text-[#181210]">-₦<?php echo number_format($payment['pension_deduction'], 2); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#8d6a5e]">Other Deductions</span>
                <span class="font-medium text-[#181210]">-₦<?php echo number_format($payment['other_deductions'], 2); ?></span>
            </div>
            <div class="flex justify-between border-t border-[#e7deda] pt-2">
                <span class="font-bold text-[#181210]">Net Pay</span>
                <span class="font-bold text-[#181210]">₦<?php echo number_format($payment['net_pay'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Account Details -->
    <?php if ($payment['payment_method'] === 'bank_transfer' && ($payment['account_name'] || $payment['account_number'] || $payment['bank_name'])): ?>
    <div class="bg-[#f8f6f5] rounded-lg p-4">
        <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">account_balance</span>
            Bank Account Details
        </h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php if ($payment['account_name']): ?>
            <div>
                <p class="text-sm text-[#8d6a5e]">Account Name</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['account_name']); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($payment['account_number']): ?>
            <div>
                <p class="text-sm text-[#8d6a5e]">Account Number</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['account_number']); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($payment['bank_name']): ?>
            <div>
                <p class="text-sm text-[#8d6a5e]">Bank Name</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['bank_name']); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Receipt -->
    <?php if ($payment['receipt_filename']): ?>
    <div class="bg-[#f8f6f5] rounded-lg p-4">
        <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">receipt</span>
            Payment Receipt
        </h5>
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <p class="text-sm text-[#8d6a5e]">Receipt File</p>
                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($payment['receipt_filename']); ?></p>
            </div>
            <button onclick="viewReceipt('<?php echo $payment['receipt_filename']; ?>')" 
                    class="inline-flex items-center px-4 py-2 bg-[#ff6933] text-white rounded-lg hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-base">visibility</span>
                View Receipt
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notes -->
    <?php if ($payment['notes']): ?>
    <div class="bg-[#f8f6f5] rounded-lg p-4">
        <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">notes</span>
            Additional Notes
        </h5>
        <p class="text-[#181210]"><?php echo nl2br(htmlspecialchars($payment['notes'])); ?></p>
    </div>
    <?php endif; ?>
</div>