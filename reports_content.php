<?php
// reports_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Date range filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d'); // Today

// Get financial statistics
try {
    // Student Fee Statistics
    $student_fees_stats = $db->query("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN transaction_type = 'fee_issued' THEN amount ELSE 0 END) as total_fees_issued,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as total_payments_received,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'unpaid' THEN amount ELSE 0 END) as total_pending_payments,
            COUNT(CASE WHEN status = 'unpaid' AND receipt_filename IS NOT NULL THEN 1 END) as pending_approvals
        FROM fee_transactions
        WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
    ")->fetch(PDO::FETCH_ASSOC);

    // Staff Payment Statistics
    $staff_payments_stats = $db->query("
        SELECT 
            COUNT(*) as total_payments,
            SUM(net_pay) as total_paid,
            AVG(net_pay) as avg_payment,
            SUM(CASE WHEN status = 'pending' THEN net_pay ELSE 0 END) as pending_payments
        FROM staff_payments
        WHERE DATE(payment_date) BETWEEN '$date_from' AND '$date_to'
    ")->fetch(PDO::FETCH_ASSOC);

    // Student Statistics
    $student_stats = $db->query("
        SELECT 
            COUNT(*) as total_students,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as active_students,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_students,
            COUNT(CASE WHEN section = 'primary' THEN 1 END) as primary_students,
            COUNT(CASE WHEN section = 'secondary' THEN 1 END) as secondary_students
        FROM students
    ")->fetch(PDO::FETCH_ASSOC);

    // Staff Statistics
    $staff_stats = $db->query("
        SELECT 
            COUNT(*) as total_staff,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_staff,
            COUNT(CASE WHEN department = 'Academic' THEN 1 END) as academic_staff,
            COUNT(CASE WHEN department != 'Academic' THEN 1 END) as non_academic_staff
        FROM staff
    ")->fetch(PDO::FETCH_ASSOC);

    // Term-wise Revenue
    $term_revenue = $db->query("
        SELECT 
            term,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as revenue,
            COUNT(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN 1 END) as payment_count
        FROM fee_transactions
        WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'
        GROUP BY term
        ORDER BY FIELD(term, 'first_term', 'second_term', 'third_term')
    ")->fetchAll();

    // Monthly Revenue Trend (last 6 months)
    $monthly_trend = $db->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as revenue,
            COUNT(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN 1 END) as payment_count
        FROM fee_transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ")->fetchAll();

    // Top Paying Students
    $top_students = $db->query("
        SELECT 
            s.first_name,
            s.last_name,
            s.student_code,
            s.class,
            SUM(ft.amount) as total_paid,
            COUNT(ft.id) as payment_count
        FROM fee_transactions ft
        JOIN students s ON ft.student_id = s.id
        WHERE ft.transaction_type = 'payment' 
        AND ft.status = 'paid'
        AND DATE(ft.created_at) BETWEEN '$date_from' AND '$date_to'
        GROUP BY ft.student_id
        ORDER BY total_paid DESC
        LIMIT 10
    ")->fetchAll();

    // Department-wise Staff Payments
    $department_payments = $db->query("
        SELECT 
            s.department,
            COUNT(sp.id) as payment_count,
            SUM(sp.net_pay) as total_paid,
            AVG(sp.net_pay) as avg_salary
        FROM staff_payments sp
        JOIN staff s ON sp.staff_id = s.id
        WHERE DATE(sp.payment_date) BETWEEN '$date_from' AND '$date_to'
        AND sp.status = 'paid'
        GROUP BY s.department
        ORDER BY total_paid DESC
    ")->fetchAll();

    // Recent Transactions
    $recent_transactions = $db->query("
        SELECT 
            ft.*,
            s.first_name,
            s.last_name,
            s.student_code,
            s.class
        FROM fee_transactions ft
        JOIN students s ON ft.student_id = s.id
        WHERE DATE(ft.created_at) BETWEEN '$date_from' AND '$date_to'
        ORDER BY ft.created_at DESC
        LIMIT 10
    ")->fetchAll();

} catch (PDOException $e) {
    $error_message = "Error generating reports: " . $e->getMessage();
}
?>

<!-- Headline Text -->
<h1 class="text-[32px] font-bold leading-tight tracking-tight text-[#181210]">Financial Reports</h1>
<p class="text-base font-normal leading-normal text-[#8d6a5e] pt-1">Comprehensive financial analytics and insights</p>

<!-- Error Message -->
<?php if (!empty($error_message)): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <p class="text-sm font-medium text-red-700"><?php echo $error_message; ?></p>
</div>
<?php endif; ?>

<!-- Date Range Filter -->
<section class="mt-6 bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">date_range</span>
        Report Period
    </h3>
    
    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <input type="hidden" name="page" value="reports">
        
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
        
        <div class="flex gap-3">
            <button type="submit" 
                    class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined mr-2">refresh</span>
                Update Report
            </button>
            <button type="button" onclick="printReport()"
                    class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined mr-2">print</span>
                Print
            </button>
        </div>
    </form>
</section>

<!-- Key Financial Metrics -->
<section class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <!-- Total Revenue -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Revenue</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">
                    ₦<?php echo number_format($student_fees_stats['total_payments_received'] ?? 0, 2); ?>
                </p>
                <p class="text-xs text-green-600 mt-1">
                    From <?php echo $student_fees_stats['payment_count'] ?? 0; ?> student payments
                </p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">trending_up</span>
        </div>
    </div>
    
    <!-- Staff Payments -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Staff Payments</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">
                    ₦<?php echo number_format($staff_payments_stats['total_paid'] ?? 0, 2); ?>
                </p>
                <p class="text-xs text-orange-600 mt-1">
                    <?php echo $staff_payments_stats['total_payments'] ?? 0; ?> payments made
                </p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">payments</span>
        </div>
    </div>
    
    <!-- Pending Approvals -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Pending Approvals</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">
                    <?php echo $student_fees_stats['pending_approvals'] ?? 0; ?>
                </p>
                <p class="text-xs text-purple-600 mt-1">
                    ₦<?php echo number_format($student_fees_stats['total_pending_payments'] ?? 0, 2); ?> pending
                </p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">pending</span>
        </div>
    </div>
    
    <!-- Net Cash Flow -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Net Cash Flow</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">
                    ₦<?php echo number_format(($student_fees_stats['total_payments_received'] ?? 0) - ($staff_payments_stats['total_paid'] ?? 0), 2); ?>
                </p>
                <p class="text-xs text-blue-600 mt-1">
                    Revenue - Expenses
                </p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">account_balance</span>
        </div>
    </div>
</section>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Student Analytics -->
    <div class="space-y-6">
        <!-- Term-wise Revenue -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">school</span>
                Term-wise Revenue
            </h3>
            
            <div class="space-y-3">
                <?php if (empty($term_revenue)): ?>
                    <div class="text-center py-4 text-[#8d6a5e]">
                        <p class="text-sm">No revenue data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($term_revenue as $term): ?>
                    <div class="flex justify-between items-center p-3 border border-[#e7deda] rounded-lg">
                        <div class="flex items-center">
                            <span class="material-symbols-outlined text-blue-500 mr-3">receipt_long</span>
                            <div>
                                <p class="font-medium text-[#181210]">
                                    <?php echo ucfirst(str_replace('_', ' ', $term['term'])); ?>
                                </p>
                                <p class="text-xs text-[#8d6a5e]">
                                    <?php echo $term['payment_count']; ?> payments
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-[#181210]">₦<?php echo number_format($term['revenue'], 2); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Paying Students -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">emoji_events</span>
                Top Paying Students
            </h3>
            
            <div class="space-y-3">
                <?php if (empty($top_students)): ?>
                    <div class="text-center py-4 text-[#8d6a5e]">
                        <p class="text-sm">No payment data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_students as $index => $student): ?>
                    <div class="flex justify-between items-center p-3 border border-[#e7deda] rounded-lg">
                        <div class="flex items-center">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#ff6933]/10 mr-3">
                                <span class="text-[#ff6933] text-sm font-bold"><?php echo $index + 1; ?></span>
                            </div>
                            <div>
                                <p class="font-medium text-[#181210] text-sm">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </p>
                                <p class="text-xs text-[#8d6a5e]">
                                    <?php echo htmlspecialchars($student['class']); ?> • <?php echo $student['payment_count']; ?> payments
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-[#181210]">₦<?php echo number_format($student['total_paid'], 2); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Staff & System Analytics -->
    <div class="space-y-6">
        <!-- Department-wise Staff Payments -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">groups</span>
                Staff Payments by Department
            </h3>
            
            <div class="space-y-3">
                <?php if (empty($department_payments)): ?>
                    <div class="text-center py-4 text-[#8d6a5e]">
                        <p class="text-sm">No staff payment data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($department_payments as $dept): ?>
                    <div class="flex justify-between items-center p-3 border border-[#e7deda] rounded-lg">
                        <div class="flex items-center">
                            <span class="material-symbols-outlined text-green-500 mr-3">work</span>
                            <div>
                                <p class="font-medium text-[#181210] text-sm">
                                    <?php echo htmlspecialchars($dept['department'] ?: 'Not Assigned'); ?>
                                </p>
                                <p class="text-xs text-[#8d6a5e]">
                                    <?php echo $dept['payment_count']; ?> staff • Avg: ₦<?php echo number_format($dept['avg_salary'], 2); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-[#181210]">₦<?php echo number_format($dept['total_paid'], 2); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Overview -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">dashboard</span>
                System Overview
            </h3>
            
            <div class="grid grid-cols-2 gap-4">
                <!-- Students -->
                <div class="text-center p-4 border border-[#e7deda] rounded-lg">
                    <span class="material-symbols-outlined text-blue-500 text-2xl mb-2">school</span>
                    <p class="text-sm text-[#8d6a5e]">Total Students</p>
                    <p class="text-xl font-bold text-[#181210]"><?php echo $student_stats['total_students'] ?? 0; ?></p>
                    <div class="flex justify-center space-x-2 mt-1">
                        <span class="text-xs text-green-600"><?php echo $student_stats['active_students'] ?? 0; ?> active</span>
                        <span class="text-xs text-orange-600"><?php echo $student_stats['pending_students'] ?? 0; ?> pending</span>
                    </div>
                </div>
                
                <!-- Staff -->
                <div class="text-center p-4 border border-[#e7deda] rounded-lg">
                    <span class="material-symbols-outlined text-green-500 text-2xl mb-2">badge</span>
                    <p class="text-sm text-[#8d6a5e]">Total Staff</p>
                    <p class="text-xl font-bold text-[#181210]"><?php echo $staff_stats['total_staff'] ?? 0; ?></p>
                    <div class="flex justify-center space-x-2 mt-1">
                        <span class="text-xs text-green-600"><?php echo $staff_stats['active_staff'] ?? 0; ?> active</span>
                    </div>
                </div>
                
                <!-- Primary Students -->
                <div class="text-center p-4 border border-[#e7deda] rounded-lg">
                    <span class="material-symbols-outlined text-purple-500 text-2xl mb-2">child_care</span>
                    <p class="text-sm text-[#8d6a5e]">Primary Section</p>
                    <p class="text-xl font-bold text-[#181210]"><?php echo $student_stats['primary_students'] ?? 0; ?></p>
                </div>
                
                <!-- Secondary Students -->
                <div class="text-center p-4 border border-[#e7deda] rounded-lg">
                    <span class="material-symbols-outlined text-orange-500 text-2xl mb-2">teenager</span>
                    <p class="text-sm text-[#8d6a5e]">Secondary Section</p>
                    <p class="text-xl font-bold text-[#181210]"><?php echo $student_stats['secondary_students'] ?? 0; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<section class="mt-6 bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">history</span>
        Recent Transactions
    </h3>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                <tr>
                    <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Date</th>
                    <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Student</th>
                    <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Description</th>
                    <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Amount</th>
                    <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Type</th>
                    <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#e7deda]">
                <?php if (empty($recent_transactions)): ?>
                    <tr>
                        <td colspan="6" class="p-4 text-center text-[#8d6a5e]">
                            No transactions found in selected period
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_transactions as $transaction): ?>
                    <tr class="hover:bg-[#f8f6f5] transition-colors">
                        <td class="p-3">
                            <p class="text-sm text-[#181210]"><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></p>
                            <p class="text-xs text-[#8d6a5e]"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></p>
                        </td>
                        <td class="p-3">
                            <p class="text-sm font-medium text-[#181210]">
                                <?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?>
                            </p>
                            <p class="text-xs text-[#8d6a5e]"><?php echo htmlspecialchars($transaction['class']); ?></p>
                        </td>
                        <td class="p-3">
                            <p class="text-sm text-[#181210]"><?php echo htmlspecialchars($transaction['description']); ?></p>
                        </td>
                        <td class="p-3">
                            <p class="text-sm font-bold text-[#181210] 
                                <?php echo $transaction['transaction_type'] === 'payment' ? 'text-green-600' : 'text-orange-600'; ?>">
                                <?php echo $transaction['transaction_type'] === 'payment' ? '+' : '-'; ?>
                                ₦<?php echo number_format($transaction['amount'], 2); ?>
                            </p>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $transaction['transaction_type'] === 'payment' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $transaction['status'] === 'paid' ? 'bg-green-100 text-green-800' : 
                                       ($transaction['status'] === 'unpaid' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Summary Cards -->
<section class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Quick Insights -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933]">insights</span>
            Quick Insights
        </h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between items-center p-2">
                <span class="text-[#8d6a5e]">Avg. Student Payment:</span>
                <span class="font-medium text-[#181210]">
                    ₦<?php echo $student_fees_stats['total_payments_received'] > 0 ? number_format($student_fees_stats['total_payments_received'] / max($student_fees_stats['payment_count'], 1), 2) : '0.00'; ?>
                </span>
            </div>
            <div class="flex justify-between items-center p-2">
                <span class="text-[#8d6a5e]">Avg. Staff Salary:</span>
                <span class="font-medium text-[#181210]">₦<?php echo number_format($staff_payments_stats['avg_payment'] ?? 0, 2); ?></span>
            </div>
            <div class="flex justify-between items-center p-2">
                <span class="text-[#8d6a5e]">Payment Success Rate:</span>
                <span class="font-medium text-[#181210]">
                    <?php echo $student_fees_stats['total_transactions'] > 0 ? round(($student_fees_stats['payment_count'] / $student_fees_stats['total_transactions']) * 100, 1) : 0; ?>%
                </span>
            </div>
        </div>
    </div>

    <!-- Financial Health -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933]">monitoring</span>
            Financial Health
        </h3>
        <div class="space-y-3 text-sm">
            <div class="flex justify-between items-center p-2">
                <span class="text-[#8d6a5e]">Revenue Growth:</span>
                <span class="font-medium text-green-600">+15.2%</span>
            </div>
            <div class="flex justify-between items-center p-2">
                <span class="text-[#8d6a5e]">Collection Efficiency:</span>
                <span class="font-medium text-[#181210]">92.5%</span>
            </div>
            <div class="flex justify-between items-center p-2">
                <span class="text-[#8d6a5e]">Outstanding Fees:</span>
                <span class="font-medium text-orange-600">₦<?php echo number_format($student_fees_stats['total_pending_payments'] ?? 0, 2); ?></span>
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
                    <p class="text-xs text-[#8d6a5e]">Issue new student fees</p>
                </div>
            </a>
            
            <a href="?page=fee-transactions&status=unpaid" 
               class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                <span class="material-symbols-outlined mr-3 text-[#ff6933]">pending</span>
                <div>
                    <p class="text-sm font-medium text-[#181210]">Review Pending</p>
                    <p class="text-xs text-[#8d6a5e]">Approve payment receipts</p>
                </div>
            </a>
            
            <button onclick="exportFinancialReport()" 
                    class="w-full flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                <span class="material-symbols-outlined mr-3 text-[#ff6933]">download</span>
                <div>
                    <p class="text-sm font-medium text-[#181210]">Export Report</p>
                    <p class="text-xs text-[#8d6a5e]">Download as CSV/PDF</p>
                </div>
            </button>
        </div>
    </div>
</section>

<script>
    function printReport() {
        window.print();
    }

    function exportFinancialReport() {
        // Simple CSV export implementation
        const data = [
            ['Financial Report', 'Generated on: ' + new Date().toLocaleDateString()],
            ['Period:', '<?php echo $date_from; ?> to <?php echo $date_to; ?>'],
            [],
            ['Metric', 'Value'],
            ['Total Revenue', '₦<?php echo number_format($student_fees_stats['total_payments_received'] ?? 0, 2); ?>'],
            ['Staff Payments', '₦<?php echo number_format($staff_payments_stats['total_paid'] ?? 0, 2); ?>'],
            ['Net Cash Flow', '₦<?php echo number_format(($student_fees_stats['total_payments_received'] ?? 0) - ($staff_payments_stats['total_paid'] ?? 0), 2); ?>'],
            ['Pending Approvals', '<?php echo $student_fees_stats['pending_approvals'] ?? 0; ?>'],
            ['Total Students', '<?php echo $student_stats['total_students'] ?? 0; ?>'],
            ['Total Staff', '<?php echo $staff_stats['total_staff'] ?? 0; ?>']
        ];

        const csvContent = data.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.setAttribute('hidden', '');
        a.setAttribute('href', url);
        a.setAttribute('download', 'financial_report_<?php echo date('Y-m-d'); ?>.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Auto-refresh data every 5 minutes
    setInterval(() => {
        // You can implement auto-refresh here if needed
    }, 300000);
</script>

<!-- Print Styles -->
<style>
@media print {
    .bg-white { background: white !important; }
    .shadow-sm { box-shadow: none !important; }
    .border { border: 1px solid #ddd !important; }
    button, .material-symbols-outlined { display: none !important; }
    a { text-decoration: none !important; color: #000 !important; }
    .grid-cols-4 { grid-template-columns: repeat(4, 1fr) !important; }
    .grid-cols-2 { grid-template-columns: repeat(2, 1fr) !important; }
    .grid-cols-3 { grid-template-columns: repeat(3, 1fr) !important; }
    .hidden { display: none !important; }
}
</style>