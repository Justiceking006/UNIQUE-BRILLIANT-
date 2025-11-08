<?php
// fee_transactions_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle receipt approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_payment'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $admin_notes = sanitize($_POST['admin_notes'] ?? '');
        
        try {
            $stmt = $db->prepare("
                UPDATE fee_transactions 
                SET status = 'paid', approved_by = ?, approved_at = NOW(), admin_notes = ?
                WHERE id = ? AND status = 'unpaid'
            ");
            $stmt->execute([$_SESSION['user_id'], $admin_notes, $transaction_id]);
            
            $success_message = "Payment approved successfully!";
            
        } catch (PDOException $e) {
            $error_message = "Failed to approve payment: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject_payment'])) {
        $transaction_id = intval($_POST['transaction_id']);
        $admin_notes = sanitize($_POST['admin_notes'] ?? '');
        
        try {
            $stmt = $db->prepare("
                UPDATE fee_transactions 
                SET status = 'cancelled', approved_by = ?, approved_at = NOW(), admin_notes = ?
                WHERE id = ? AND status = 'unpaid'
            ");
            $stmt->execute([$_SESSION['user_id'], $admin_notes, $transaction_id]);
            
            $success_message = "Payment rejected successfully!";
            
        } catch (PDOException $e) {
            $error_message = "Failed to reject payment: " . $e->getMessage();
        }
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$student_filter = isset($_GET['student']) ? intval($_GET['student']) : '';
$term_filter = isset($_GET['term']) ? $_GET['term'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "SELECT ft.*, s.first_name, s.last_name, s.student_code, s.class, s.section
          FROM fee_transactions ft 
          JOIN students s ON ft.student_id = s.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR ft.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($student_filter)) {
    $query .= " AND ft.student_id = ?";
    $params[] = $student_filter;
}

if (!empty($term_filter) && $term_filter !== 'all') {
    $query .= " AND ft.term = ?";
    $params[] = $term_filter;
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND ft.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter) && $type_filter !== 'all') {
    $query .= " AND ft.transaction_type = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $query .= " AND DATE(ft.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(ft.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY ft.created_at DESC";

// Get transaction data
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get all students for filter dropdown
$all_students = $db->query("SELECT id, first_name, last_name, student_code, class FROM students WHERE status = 'approved' ORDER BY first_name, last_name")->fetchAll();

// Get statistics
$total_transactions = count($transactions);
$total_fees_issued = $db->query("SELECT COALESCE(SUM(amount), 0) FROM fee_transactions WHERE transaction_type = 'fee_issued'")->fetchColumn();
$total_payments_received = $db->query("SELECT COALESCE(SUM(amount), 0) FROM fee_transactions WHERE transaction_type = 'payment' AND status = 'paid'")->fetchColumn();
$pending_approvals = $db->query("SELECT COUNT(*) FROM fee_transactions WHERE status = 'unpaid' AND receipt_filename IS NOT NULL")->fetchColumn();

// Get term-wise totals
$term_totals = $db->query("SELECT term, 
                           SUM(CASE WHEN transaction_type = 'fee_issued' THEN amount ELSE 0 END) as fees_issued,
                           SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as payments_received
                           FROM fee_transactions 
                           GROUP BY term 
                           ORDER BY FIELD(term, 'first_term', 'second_term', 'third_term')")->fetchAll();
?>

<!-- Headline Text -->
<h1 class="text-[32px] font-bold leading-tight tracking-tight text-[#181210]">Fee Transactions</h1>
<p class="text-base font-normal leading-normal text-[#8d6a5e] pt-1">Manage student fee payments and receipt approvals</p>

<!-- Success/Error Messages -->
<?php if (!empty($success_message)): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo $success_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <p class="text-sm font-medium text-red-700"><?php echo $error_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-red-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<!-- Statistics Overview -->
<section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Transactions</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_transactions; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">receipt_long</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Fees Issued</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($total_fees_issued, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">request_quote</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Payments Received</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($total_payments_received, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">payments</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Pending Approvals</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $pending_approvals; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">pending</span>
        </div>
    </div>
</section>

<!-- Advanced Filters -->
<section class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
    <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">filter_alt</span>
        Filter Transactions
    </h3>
    
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <input type="hidden" name="page" value="fee-transactions">
        
        <!-- Search -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Search</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                   placeholder="Name, Student Code, Description...">
        </div>
        
        <!-- Student Filter -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student</label>
            <select name="student" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                <option value="">All Students</option>
                <?php foreach ($all_students as $student): ?>
                    <option value="<?php echo $student['id']; ?>" <?php echo $student_filter == $student['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_code'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Term Filter -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Term</label>
            <select name="term" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                <option value="all" <?php echo $term_filter === 'all' || empty($term_filter) ? 'selected' : ''; ?>>All Terms</option>
                <option value="first_term" <?php echo $term_filter === 'first_term' ? 'selected' : ''; ?>>First Term</option>
                <option value="second_term" <?php echo $term_filter === 'second_term' ? 'selected' : ''; ?>>Second Term</option>
                <option value="third_term" <?php echo $term_filter === 'third_term' ? 'selected' : ''; ?>>Third Term</option>
            </select>
        </div>
        
        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Status</label>
            <select name="status" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                <option value="all" <?php echo $status_filter === 'all' || empty($status_filter) ? 'selected' : ''; ?>>All Status</option>
                <option value="unpaid" <?php echo $status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        
        <!-- Type Filter -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Type</label>
            <select name="type" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                <option value="all" <?php echo $type_filter === 'all' || empty($type_filter) ? 'selected' : ''; ?>>All Types</option>
                <option value="fee_issued" <?php echo $type_filter === 'fee_issued' ? 'selected' : ''; ?>>Fee Issued</option>
                <option value="payment" <?php echo $type_filter === 'payment' ? 'selected' : ''; ?>>Payment</option>
                <option value="adjustment" <?php echo $type_filter === 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
            </select>
        </div>
        
        <!-- Date Range -->
        <div class="md:col-span-2 lg:col-span-6 grid grid-cols-2 gap-4">
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
        </div>
        
        <!-- Filter Buttons -->
        <div class="md:col-span-2 lg:col-span-6 flex gap-3 justify-end pt-2">
            <button type="submit" 
                    class="h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">search</span>
                Apply Filters
            </button>
            <a href="?page=fee-transactions" 
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
        <!-- Transactions Table -->
        <div class="bg-white rounded-xl shadow-sm border border-[#e7deda] overflow-hidden">
            <?php if (empty($transactions)): ?>
                <div class="p-8 text-center text-[#8d6a5e]">
                    <span class="material-symbols-outlined text-4xl mb-2">search_off</span>
                    <p class="text-lg font-medium">No transactions found</p>
                    <p class="text-sm mt-1">Try adjusting your search filters</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                            <tr>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Date</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Student</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] hidden lg:table-cell">Term</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Description</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Amount</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Type</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Status</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e7deda]">
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="hover:bg-[#f8f6f5] transition-colors">
                                <td class="p-4">
                                    <p class="text-sm font-medium text-[#181210]"><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></p>
                                    <p class="text-xs text-[#8d6a5e]"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></p>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                                            <span class="material-symbols-outlined text-[#ff6933] text-sm">person</span>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-[#181210]">
                                                <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                                            </p>
                                            <p class="text-xs text-[#8d6a5e]">
                                                <?php echo htmlspecialchars($transaction['student_code']); ?>
                                                <span class="hidden lg:inline">• <?php echo htmlspecialchars($transaction['class']); ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 hidden lg:table-cell">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['term'])); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <p class="text-sm text-[#181210]"><?php echo htmlspecialchars($transaction['description']); ?></p>
                                </td>
                                <td class="p-4">
                                    <p class="text-sm font-bold text-[#181210] 
                                        <?php echo $transaction['transaction_type'] === 'payment' ? 'text-green-600' : 'text-orange-600'; ?>">
                                        <?php echo $transaction['transaction_type'] === 'payment' ? '+' : '-'; ?>
                                        ₦<?php echo number_format($transaction['amount'], 2); ?>
                                    </p>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php echo $transaction['transaction_type'] === 'payment' ? 'bg-green-100 text-green-800' : 
                                               ($transaction['transaction_type'] === 'fee_issued' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $transaction['transaction_type'] === 'payment' ? 'payments' : 
                                                   ($transaction['transaction_type'] === 'fee_issued' ? 'request_quote' : 'tune'); ?>
                                        </span>
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php echo $transaction['status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                               ($transaction['status'] === 'unpaid' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $transaction['status'] === 'paid' ? 'check_circle' : 
                                                   ($transaction['status'] === 'unpaid' ? 'pending' : 'cancel'); ?>
                                        </span>
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 flex justify-end gap-2">
                                    <?php if ($transaction['status'] === 'unpaid' && $transaction['receipt_filename']): ?>
                                        <button onclick="openReceiptApproval(<?php echo $transaction['id']; ?>)" 
                                                class="inline-flex items-center p-2 text-sm text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                                title="Approve Payment">
                                            <span class="material-symbols-outlined text-base">verified</span>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($transaction['receipt_filename']): ?>
                                    <button onclick="viewReceipt('<?php echo $transaction['receipt_filename']; ?>')" 
                                            class="inline-flex items-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            title="View Receipt">
                                        <span class="material-symbols-outlined text-base">receipt</span>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button onclick="viewTransactionDetails(<?php echo $transaction['id']; ?>)" 
                                            class="inline-flex items-center p-2 text-sm text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                            title="View Details">
                                        <span class="material-symbols-outlined text-base">visibility</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Results Count -->
                <div class="p-4 border-t border-[#e7deda] bg-[#f8f6f5]">
                    <p class="text-sm text-[#8d6a5e]">
                        Showing <?php echo $total_transactions; ?> transaction<?php echo $total_transactions !== 1 ? 's' : ''; ?>
                        <?php if (!empty($search) || !empty($student_filter) || !empty($term_filter) || !empty($status_filter)): ?>
                            (filtered)
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Analytics Sidebar -->
    <div class="space-y-6">
        <!-- Term-wise Summary -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">summarize</span>
                Term Summary
            </h3>
            
            <?php if (empty($term_totals)): ?>
                <div class="text-center py-4 text-[#8d6a5e]">
                    <p class="text-sm">No transaction data available</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($term_totals as $term): ?>
                    <div class="border border-[#e7deda] rounded-lg p-3">
                        <h4 class="font-semibold text-[#181210] text-sm mb-2">
                            <?php echo ucfirst(str_replace('_', ' ', $term['term'])); ?>
                        </h4>
                        <div class="space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="text-[#8d6a5e]">Fees Issued:</span>
                                <span class="font-medium text-orange-600">₦<?php echo number_format($term['fees_issued'], 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-[#8d6a5e]">Payments Received:</span>
                                <span class="font-medium text-green-600">₦<?php echo number_format($term['payments_received'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Summary -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">analytics</span>
                Quick Stats
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Transactions:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_transactions; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Fees Issued:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($total_fees_issued, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Payments Received:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($total_payments_received, 2); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Pending Approvals:</span>
                    <span class="font-medium text-[#181210]"><?php echo $pending_approvals; ?></span>
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
                <a href="?page=collect-fees" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">payments</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Collect Fees</p>
                        <p class="text-xs text-[#8d6a5e]">Issue new fees to students</p>
                    </div>
                </a>
                
                <button onclick="showPendingApprovals()" 
                        class="w-full flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">pending</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Pending Approvals</p>
                        <p class="text-xs text-[#8d6a5e]">Review receipt submissions</p>
                    </div>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Approval Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="approvalModal">
    <div class="bg-white rounded-xl w-full max-w-md">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Approve Payment</h3>
                <button onclick="closeApprovalModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4 space-y-4" id="approvalForm">
            <input type="hidden" name="transaction_id" id="approvalTransactionId">
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Admin Notes (Optional)</label>
                <textarea name="admin_notes" rows="3"
                          class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                          placeholder="Add any notes about this payment..."></textarea>
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="submit" name="reject_payment" 
                        class="flex-1 h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                    Reject
                </button>
                <button type="submit" name="approve_payment" 
                        class="flex-1 h-12 rounded-lg bg-green-600 text-white font-bold hover:bg-green-700 transition-colors">
                    Approve
                </button>
            </div>
        </form>
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
<!-- Transaction Details Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="transactionModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Transaction Details</h3>
                <button onclick="closeTransactionModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4" id="transactionModalContent">
            <!-- Content will be loaded by JavaScript -->
        </div>
    </div>
</div>
<script>
    // View Transaction Details
    function viewTransactionDetails(transactionId) {
        // Show loading state
        document.getElementById('transactionModalContent').innerHTML = `
            <div class="flex items-center justify-center py-12">
                <span class="material-symbols-outlined animate-spin text-[#ff6933] mr-2">refresh</span>
                Loading transaction details...
            </div>
        `;
        
        document.getElementById('transactionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Fetch transaction details
        fetch(`get_transaction_details.php?id=${transactionId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(transaction => {
                displayTransactionDetails(transaction);
            })
            .catch(error => {
                console.error('Error loading transaction details:', error);
                document.getElementById('transactionModalContent').innerHTML = `
                    <div class="text-center py-12 text-[#8d6a5e]">
                        <span class="material-symbols-outlined text-4xl mb-2">error</span>
                        <p class="text-lg font-medium">Failed to load transaction details</p>
                        <p class="text-sm mt-1">Please try again later</p>
                    </div>
                `;
            });
    }

    function displayTransactionDetails(transaction) {
        // Format dates
        const createdDate = new Date(transaction.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        const createdTime = new Date(transaction.created_at).toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        const dueDate = transaction.due_date ? new Date(transaction.due_date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : 'Not set';
        
        const approvedDate = transaction.approved_at ? new Date(transaction.approved_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        }) : 'Not approved';

        // Status badge
        const statusBadge = {
            'paid': 'bg-green-100 text-green-800',
            'unpaid': 'bg-yellow-100 text-yellow-800',
            'cancelled': 'bg-red-100 text-red-800'
        }[transaction.status] || 'bg-gray-100 text-gray-800';

        // Type badge
        const typeBadge = {
            'fee_issued': 'bg-orange-100 text-orange-800',
            'payment': 'bg-green-100 text-green-800',
            'adjustment': 'bg-blue-100 text-blue-800'
        }[transaction.transaction_type] || 'bg-gray-100 text-gray-800';

        // Term badge
        const termDisplay = transaction.term ? transaction.term.replace('_', ' ').toUpperCase() : 'Not set';

        const modalContent = `
            <div class="space-y-6">
                <!-- Header -->
                <div class="text-center border-b border-[#e7deda] pb-6">
                    <div class="flex justify-center mb-4">
                        <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#ff6933]/20">
                            <span class="material-symbols-outlined text-2xl text-[#ff6933]">
                                ${transaction.transaction_type === 'payment' ? 'payments' : 'receipt_long'}
                            </span>
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-[#181210]">Transaction Details</h3>
                    <p class="text-sm text-[#8d6a5e]">ID: ${transaction.id}</p>
                </div>

                <!-- Student Information -->
                <div class="bg-[#f8f6f5] rounded-lg p-4">
                    <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                        <span class="material-symbols-outlined mr-2 text-[#ff6933]">person</span>
                        Student Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-[#8d6a5e] block">Student Name:</span>
                            <span class="font-medium text-[#181210]">${transaction.first_name} ${transaction.last_name}</span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Student Code:</span>
                            <span class="font-medium text-[#181210]">${transaction.student_code}</span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Class:</span>
                            <span class="font-medium text-[#181210]">${transaction.class}</span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Section:</span>
                            <span class="font-medium text-[#181210] capitalize">${transaction.section}</span>
                        </div>
                    </div>
                </div>

                <!-- Transaction Information -->
                <div class="bg-[#f8f6f5] rounded-lg p-4">
                    <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                        <span class="material-symbols-outlined mr-2 text-[#ff6933]">receipt_long</span>
                        Transaction Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-[#8d6a5e] block">Description:</span>
                            <span class="font-medium text-[#181210]">${transaction.description || 'No description'}</span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Amount:</span>
                            <span class="text-lg font-bold ${transaction.transaction_type === 'payment' ? 'text-green-600' : 'text-orange-600'}">
                                ${transaction.transaction_type === 'payment' ? '+' : '-'}₦${parseFloat(transaction.amount).toLocaleString()}
                            </span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Transaction Type:</span>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${typeBadge}">
                                <span class="material-symbols-outlined text-xs mr-1">
                                    ${transaction.transaction_type === 'payment' ? 'payments' : 
                                      transaction.transaction_type === 'fee_issued' ? 'request_quote' : 'tune'}
                                </span>
                                ${transaction.transaction_type ? transaction.transaction_type.replace('_', ' ').charAt(0).toUpperCase() + transaction.transaction_type.replace('_', ' ').slice(1) : 'Unknown'}
                            </span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Fee Type:</span>
                            <span class="font-medium text-[#181210] capitalize">${transaction.fee_type || 'Not specified'}</span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Term:</span>
                            <span class="font-medium text-[#181210]">${termDisplay}</span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Due Date:</span>
                            <span class="font-medium text-[#181210]">${dueDate}</span>
                        </div>
                    </div>
                </div>

                <!-- Status & Timeline -->
                <div class="bg-[#f8f6f5] rounded-lg p-4">
                    <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                        <span class="material-symbols-outlined mr-2 text-[#ff6933]">schedule</span>
                        Status & Timeline
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-[#8d6a5e] block">Status:</span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${statusBadge}">
                                <span class="material-symbols-outlined text-sm mr-1">
                                    ${transaction.status === 'paid' ? 'check_circle' : 
                                      transaction.status === 'unpaid' ? 'pending' : 'cancel'}
                                </span>
                                ${transaction.status ? transaction.status.charAt(0).toUpperCase() + transaction.status.slice(1) : 'Unknown'}
                            </span>
                        </div>
                        <div>
                            <span class="text-[#8d6a5e] block">Created:</span>
                            <span class="font-medium text-[#181210]">${createdDate} at ${createdTime}</span>
                        </div>
                        ${transaction.approved_by ? `
                        <div>
                            <span class="text-[#8d6a5e] block">Approved By:</span>
                            <span class="font-medium text-[#181210]">Admin ID: ${transaction.approved_by}</span>
                        </div>
                        ` : ''}
                        <div>
                            <span class="text-[#8d6a5e] block">Approved Date:</span>
                            <span class="font-medium text-[#181210]">${approvedDate}</span>
                        </div>
                    </div>
                </div>

                <!-- Receipt Information -->
                ${transaction.receipt_filename ? `
                <div class="bg-[#f8f6f5] rounded-lg p-4">
                    <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                        <span class="material-symbols-outlined mr-2 text-[#ff6933]">receipt</span>
                        Receipt Information
                    </h4>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[#8d6a5e] block">Receipt File:</span>
                                <span class="font-medium text-[#181210] text-sm">${transaction.receipt_filename}</span>
                            </div>
                            <button onclick="viewReceipt('${transaction.receipt_filename}')" 
                                    class="inline-flex items-center px-3 py-2 rounded-lg bg-purple-100 text-purple-600 text-sm font-medium hover:bg-purple-200 transition-colors">
                                <span class="material-symbols-outlined mr-1 text-base">visibility</span>
                                View Receipt
                            </button>
                        </div>
                    </div>
                </div>
                ` : ''}

                <!-- Admin Notes -->
                ${transaction.admin_notes ? `
                <div class="bg-[#f8f6f5] rounded-lg p-4">
                    <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                        <span class="material-symbols-outlined mr-2 text-[#ff6933]">notes</span>
                        Admin Notes
                    </h4>
                    <div class="bg-white rounded-lg p-3 border border-[#e7deda]">
                        <p class="text-sm text-[#181210]">${transaction.admin_notes}</p>
                    </div>
                </div>
                ` : ''}

                <!-- Action Buttons -->
                <div class="flex gap-3 pt-4 border-t border-[#e7deda]">
                    <button onclick="closeTransactionModal()" 
                            class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center justify-center">
                        <span class="material-symbols-outlined mr-2">close</span>
                        Close
                    </button>
                    ${transaction.status === 'unpaid' && transaction.receipt_filename ? `
                    <button onclick="closeTransactionModal(); openReceiptApproval(${transaction.id});" 
                            class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                        <span class="material-symbols-outlined mr-2">verified</span>
                        Approve/Reject
                    </button>
                    ` : ''}
                </div>
            </div>
        `;

        document.getElementById('transactionModalContent').innerHTML = modalContent;
    }

    function closeTransactionModal() {
        document.getElementById('transactionModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openReceiptApproval(transactionId) {
        document.getElementById('approvalTransactionId').value = transactionId;
        document.getElementById('approvalModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeApprovalModal() {
        document.getElementById('approvalModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function viewReceipt(receiptFilename) {
        const receiptPath = `uploads/receipts/${receiptFilename}`;
        document.getElementById('receiptImage').src = receiptPath;
        document.getElementById('receiptModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        
        // Close transaction modal when viewing receipt
        closeTransactionModal();
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

    function showPendingApprovals() {
        // Filter to show only pending approvals
        window.location.href = '?page=fee-transactions&status=unpaid';
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.id === 'transactionModal') {
            closeTransactionModal();
        }
        if (e.target.id === 'approvalModal') {
            closeApprovalModal();
        }
        if (e.target.id === 'receiptModal') {
            closeReceiptModal();
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeTransactionModal();
            closeApprovalModal();
            closeReceiptModal();
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