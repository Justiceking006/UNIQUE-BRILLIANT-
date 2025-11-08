<?php
// payment_history_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle search and filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method_filter = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';

// Build query
$query = "SELECT sp.*, s.first_name, s.last_name, s.staff_id, s.department, s.position, s.account_name, s.account_number, s.bank_name
          FROM staff_payments sp 
          JOIN staff s ON sp.staff_id = s.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.staff_id LIKE ? OR sp.receipt_filename LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($staff_filter)) {
    $query .= " AND sp.staff_id = ?";
    $params[] = $staff_filter;
}

if (!empty($date_from)) {
    $query .= " AND sp.payment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND sp.payment_date <= ?";
    $params[] = $date_to;
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND sp.status = ?";
    $params[] = $status_filter;
}

if (!empty($payment_method_filter) && $payment_method_filter !== 'all') {
    $query .= " AND sp.payment_method = ?";
    $params[] = $payment_method_filter;
}

$query .= " ORDER BY sp.payment_date DESC, sp.created_at DESC";

// Get payment data
$stmt = $db->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Get all staff for filter dropdown
$all_staff = $db->query("SELECT id, first_name, last_name, staff_id FROM staff WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get statistics
$total_payments = count($payments);
$total_paid = $db->query("SELECT SUM(net_pay) FROM staff_payments WHERE status = 'paid'")->fetchColumn();
$this_month_total = $db->query("SELECT SUM(net_pay) FROM staff_payments WHERE status = 'paid' AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())")->fetchColumn();
$avg_payment = $db->query("SELECT AVG(net_pay) FROM staff_payments WHERE status = 'paid'")->fetchColumn();

// Get department-wise spending
$department_spending = $db->query("SELECT s.department, SUM(sp.net_pay) as total_paid, COUNT(sp.id) as payment_count 
                                  FROM staff_payments sp 
                                  JOIN staff s ON sp.staff_id = s.id 
                                  WHERE sp.status = 'paid' 
                                  GROUP BY s.department 
                                  ORDER BY total_paid DESC")->fetchAll();
?>

<!-- Staff Payment History Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Staff Payment History</h1>
            <p class="text-[#8d6a5e]">Comprehensive records of all staff payments and transactions</p>
        </div>
        <div class="mt-4 lg:mt-0 flex gap-3">
            <button onclick="exportPaymentHistory()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">download</span>
                Export
            </button>
            <button onclick="printPaymentReport()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">print</span>
                Print
            </button>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Payments</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_payments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">receipt_long</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Paid</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($total_paid, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">payments</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">This Month</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($this_month_total, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">calendar_month</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Average Payment</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($avg_payment, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">trending_up</span>
        </div>
    </div>
</section>

<!-- Advanced Filters -->
<section class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
    <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">filter_alt</span>
        Filter Payments
    </h3>
    
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <input type="hidden" name="page" value="payment-history">
        
        <!-- Search -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                   placeholder="Name, Staff ID, Receipt...">
        </div>
        
        <!-- Staff Filter -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Staff Member</label>
            <select name="staff" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                <option value="">All Staff</option>
                <?php foreach ($all_staff as $staff): ?>
                    <option value="<?php echo $staff['id']; ?>" <?php echo $staff_filter == $staff['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Date Range -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
        </div>
        
        <!-- Status & Method Filters -->
        <div class="flex gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Status</label>
                <select name="status" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    <option value="all" <?php echo $status_filter === 'all' || empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
                    <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            
            <div class="flex-1">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Method</label>
                <select name="payment_method" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    <option value="all" <?php echo $payment_method_filter === 'all' || empty($payment_method_filter) ? 'selected' : ''; ?>>All Methods</option>
                    <option value="bank_transfer" <?php echo $payment_method_filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="cash" <?php echo $payment_method_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
                    <option value="cheque" <?php echo $payment_method_filter === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                </select>
            </div>
        </div>
        
        <!-- Filter Buttons -->
        <div class="md:col-span-2 lg:col-span-5 flex gap-3 justify-end pt-2">
            <button type="submit" 
                    class="h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">search</span>
                Apply Filters
            </button>
            <a href="?page=payment-history" 
               class="h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">refresh</span>
                Reset
            </a>
        </div>
    </form>
</section>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-3">
        <!-- Payments Table -->
        <div class="bg-white rounded-xl shadow-sm border border-[#e7deda] overflow-hidden">
            <?php if (empty($payments)): ?>
                <div class="p-8 text-center text-[#8d6a5e]">
                    <span class="material-symbols-outlined text-4xl mb-2">search_off</span>
                    <p class="text-lg font-medium">No payments found</p>
                    <p class="text-sm mt-1">Try adjusting your search filters</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                            <tr>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Date</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Staff Member</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] hidden lg:table-cell">Period</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Amount</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] hidden md:table-cell">Method</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Status</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e7deda]">
                            <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-[#f8f6f5] transition-colors">
                                <td class="p-4">
                                    <p class="text-sm font-medium text-[#181210]"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></p>
                                    <p class="text-xs text-[#8d6a5e]"><?php echo date('g:i A', strtotime($payment['created_at'])); ?></p>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                                            <span class="material-symbols-outlined text-[#ff6933] text-sm">person</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-[#181210]">
                                                <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-[#8d6a5e]">
                                                <?php echo htmlspecialchars($payment['staff_id']); ?>
                                                <span class="hidden lg:inline">• <?php echo htmlspecialchars($payment['department']); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 hidden lg:table-cell">
                                    <p class="text-sm text-[#181210]"><?php echo htmlspecialchars($payment['payment_period']); ?></p>
                                </td>
                                <td class="p-4">
                                    <p class="text-sm font-bold text-[#181210]">₦<?php echo number_format($payment['net_pay'], 2); ?></p>
                                    <p class="text-xs text-[#8d6a5e]">
                                        Gross: ₦<?php echo number_format($payment['gross_salary'], 2); ?>
                                    </p>
                                </td>
                                <td class="p-4 hidden md:table-cell">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php echo $payment['payment_method'] === 'bank_transfer' ? 'bg-blue-100 text-blue-800' : 
                                               ($payment['payment_method'] === 'cash' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'); ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $payment['payment_method'] === 'bank_transfer' ? 'account_balance' : 
                                                   ($payment['payment_method'] === 'cash' ? 'payments' : 'description'); ?>
                                        </span>
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php echo $payment['status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                               ($payment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $payment['status'] === 'paid' ? 'check_circle' : 
                                                   ($payment['status'] === 'pending' ? 'pending' : 'error'); ?>
                                        </span>
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 flex justify-end gap-2">
                                    <button onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)" 
                                            class="inline-flex items-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            title="View Details">
                                        <span class="material-symbols-outlined text-base">visibility</span>
                                    </button>
                                    <button onclick="printReceipt(<?php echo $payment['id']; ?>)" 
                                            class="inline-flex items-center p-2 text-sm text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                            title="Print Receipt">
                                        <span class="material-symbols-outlined text-base">receipt</span>
                                    </button>
                                    <?php if ($payment['receipt_filename']): ?>
                                    <button onclick="viewReceipt('<?php echo $payment['receipt_filename']; ?>')" 
                                            class="inline-flex items-center p-2 text-sm text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                            title="View Receipt">
                                        <span class="material-symbols-outlined text-base">image</span>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Results Count -->
                <div class="p-4 border-t border-[#e7deda] bg-[#f8f6f5]">
                    <p class="text-sm text-[#8d6a5e]">
                        Showing <?php echo $total_payments; ?> payment<?php echo $total_payments !== 1 ? 's' : ''; ?>
                        <?php if (!empty($search) || !empty($staff_filter) || !empty($date_from) || !empty($date_to)): ?>
                            (filtered)
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Analytics Sidebar -->
    <div class="space-y-6">
        <!-- Department Spending -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">pie_chart</span>
                Department Spending
            </h3>
            
            <?php if (empty($department_spending)): ?>
                <div class="text-center py-4 text-[#8d6a5e]">
                    <p class="text-sm">No payment data available</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($department_spending as $dept): ?>
                    <div class="flex justify-between items-center">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-[#181210]"><?php echo htmlspecialchars($dept['department'] ?: 'Not Assigned'); ?></p>
                            <p class="text-xs text-[#8d6a5e]"><?php echo $dept['payment_count']; ?> payment<?php echo $dept['payment_count'] !== 1 ? 's' : ''; ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-[#181210]">₦<?php echo number_format($dept['total_paid'], 2); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Summary -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">summarize</span>
                Quick Summary
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Records:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_payments; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Amount:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($total_paid, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">This Month:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($this_month_total, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Avg Payment:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($avg_payment, 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">bolt</span>
                Quick Actions
            </h3>
            
            <div class="space-y-3">
                <a href="?page=pay-staff" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">payments</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Pay Staff</p>
                        <p class="text-xs text-[#8d6a5e]">Process individual payment</p>
                    </div>
                </a>
                
                <a href="?page=payroll" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">receipt_long</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Bulk Payroll</p>
                        <p class="text-xs text-[#8d6a5e]">Process multiple payments</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Payment Details Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="paymentModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Payment Details</h3>
                <button onclick="closePaymentModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4" id="paymentModalContent">
            <!-- Content will be loaded by JavaScript -->
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="receiptModal">
    <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Payment Receipt</h3>
                <div class="flex gap-2">
                    <button onclick="printReceiptImage()" 
                            class="inline-flex items-center px-3 py-1 text-sm bg-[#ff6933] text-white rounded-lg hover:bg-[#ff6933]/90 transition-colors">
                        <span class="material-symbols-outlined mr-1 text-base">print</span>
                        Print
                    </button>
                    <button onclick="closeReceiptModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4 flex items-center justify-center">
            <img id="receiptImage" src="" alt="Payment Receipt" class="max-w-full max-h-full object-contain">
        </div>
    </div>
</div>

<script>
    function viewPaymentDetails(paymentId) {
        // Fetch payment details via AJAX
        fetch(`get_payment_details.php?id=${paymentId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('paymentModalContent').innerHTML = data;
                document.getElementById('paymentModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                document.getElementById('paymentModalContent').innerHTML = `
                    <div class="text-center py-8 text-[#8d6a5e]">
                        <span class="material-symbols-outlined text-4xl mb-2">error</span>
                        <p>Failed to load payment details</p>
                        <p class="text-sm mt-1">Please try again</p>
                    </div>
                `;
                document.getElementById('paymentModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
    }

    function viewReceipt(receiptFilename) {
        const receiptPath = `uploads/receipts/${receiptFilename}`;
        document.getElementById('receiptImage').src = receiptPath;
        document.getElementById('receiptModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function printReceiptImage() {
        const receiptImg = document.getElementById('receiptImage');
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Receipt</title>
                <style>
                    body { margin: 0; padding: 20px; text-align: center; }
                    img { max-width: 100%; height: auto; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <img src="${receiptImg.src}" alt="Payment Receipt">
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    function printReceipt(paymentId) {
        // Generate printable receipt
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Payment Receipt</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; color: #333; }
                    .receipt { max-width: 400px; margin: 0 auto; border: 2px solid #333; padding: 20px; }
                    .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 15px; }
                    .header h1 { margin: 0; color: #ff6933; }
                    .details { margin-bottom: 15px; }
                    .detail-row { display: flex; justify-content: between; margin-bottom: 5px; }
                    .detail-label { font-weight: bold; flex: 1; }
                    .detail-value { flex: 2; }
                    .total { border-top: 2px solid #333; padding-top: 10px; font-weight: bold; font-size: 1.2em; }
                    .footer { text-align: center; margin-top: 20px; font-size: 0.8em; color: #666; }
                    @media print {
                        body { padding: 0; }
                        .receipt { border: none; box-shadow: none; }
                    }
                </style>
            </head>
            <body>
                <div class="receipt">
                    <div class="header">
                        <h1>PRIME LEGACY BANK</h1>
                        <p>Staff Payment Receipt</p>
                        <p>Payment ID: ${paymentId}</p>
                    </div>
                    <div class="details">
                        <div class="detail-row">
                            <span class="detail-label">Date:</span>
                            <span class="detail-value">${new Date().toLocaleDateString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Time:</span>
                            <span class="detail-value">${new Date().toLocaleTimeString()}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value">Paid</span>
                        </div>
                    </div>
                    <div class="total">
                        <div class="detail-row">
                            <span class="detail-label">Total Paid:</span>
                            <span class="detail-value">₦[Amount]</span>
                        </div>
                    </div>
                    <div class="footer">
                        <p>Thank you for your service!</p>
                        <p>Prime Legacy Bank - Staff Payment System</p>
                    </div>
                </div>
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    function exportPaymentHistory() {
        // Simple CSV export implementation
        const table = document.querySelector('table');
        const rows = table.querySelectorAll('tr');
        let csv = [];
        
        for (let i = 0; i < rows.length; i++) {
            let row = [], cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                // Remove action buttons from export
                if (!cols[j].querySelector('button')) {
                    row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
                }
            }
            
            csv.push(row.join(','));
        }

        const csvString = csv.join('\n');
        const blob = new Blob([csvString], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'payment_history.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    function printPaymentReport() {
        window.print();
    }

    // Close modals when clicking outside
    document.getElementById('paymentModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closePaymentModal();
        }
    });

    document.getElementById('receiptModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReceiptModal();
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePaymentModal();
            closeReceiptModal();
        }
    });
</script>

<!-- Print Styles -->
<style>
@media print {
    .bg-white { background: white !important; }
    .shadow-sm { box-shadow: none !important; }
    .border { border: 1px solid #ddd !important; }
    .hidden { display: none !important; }
    .lg\\:col-span-3 { grid-column: span 3 / span 3; }
    .space-y-6 > * + * { margin-top: 1.5rem; }
    .flex { display: flex; }
    .justify-between { justify-content: space-between; }
    .items-center { align-items: center; }
    .p-4 { padding: 1rem; }
    .p-6 { padding: 1.5rem; }
    .mb-6 { margin-bottom: 1.5rem; }
    .mb-4 { margin-bottom: 1rem; }
    .text-2xl { font-size: 1.5rem; }
    .text-lg { font-size: 1.125rem; }
    .text-sm { font-size: 0.875rem; }
    .text-xs { font-size: 0.75rem; }
    .font-bold { font-weight: bold; }
    .font-medium { font-weight: 500; }
    .text-\[\#181210\] { color: #181210; }
    .text-\[\#8d6a5e\] { color: #8d6a5e; }
    .rounded-xl { border-radius: 0.75rem; }
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .gap-4 { gap: 1rem; }
    .gap-6 { gap: 1.5rem; }
    
    /* Hide unnecessary elements for print */
    button, .material-symbols-outlined, 
    #paymentModal, #receiptModal,
    .lg\\:col-span-1 { display: none !important; }
    
    /* Show all table cells */
    .hidden { display: table-cell !important; }
}
</style>