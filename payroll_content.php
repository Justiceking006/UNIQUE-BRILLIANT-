<?php
// payroll_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle bulk payroll processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payroll'])) {
        $selected_staff = $_POST['selected_staff'] ?? [];
        $payment_date = $_POST['payment_date'];
        $payment_period = $_POST['payment_period'];
        
        if (empty($selected_staff)) {
            $error_message = "Please select at least one staff member to process payroll.";
        } else {
            $success_count = 0;
            $error_count = 0;
            
            foreach ($selected_staff as $staff_id) {
                $staff_id = intval($staff_id);
                
                // Get staff details and salary
                $staff_stmt = $db->prepare("SELECT * FROM staff WHERE id = ?");
                $staff_stmt->execute([$staff_id]);
                $staff = $staff_stmt->fetch();
                
                if ($staff && $staff['salary'] > 0) {
                    $gross_salary = floatval($staff['salary']);
                    
                    // Calculate standard deductions (you can customize these percentages)
                    $tax_deduction = $gross_salary * 0.05; // 5% tax
                    $pension_deduction = $gross_salary * 0.075; // 7.5% pension
                    $other_deductions = 0; // No other deductions by default
                    $net_pay = $gross_salary - $tax_deduction - $pension_deduction - $other_deductions;
                    
                    try {
                        $stmt = $db->prepare("INSERT INTO staff_payments (staff_id, payment_date, payment_period, gross_salary, tax_deduction, pension_deduction, other_deductions, net_pay, payment_method, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'bank_transfer', 'paid', ?)");
                        
                        if ($stmt->execute([$staff_id, $payment_date, $payment_period, $gross_salary, $tax_deduction, $pension_deduction, $other_deductions, $net_pay, $_SESSION['user_id']])) {
                            $success_count++;
                        } else {
                            $error_count++;
                        }
                    } catch (PDOException $e) {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
            
            if ($success_count > 0) {
                $success_message = "Payroll processed successfully! {$success_count} payments completed.";
                if ($error_count > 0) {
                    $success_message .= " {$error_count} payments failed.";
                }
            } else {
                $error_message = "Failed to process payroll. Please check staff salaries and try again.";
            }
        }
    }
}

// Get payroll period from URL or use current month
$current_period = isset($_GET['period']) ? $_GET['period'] : date('F Y');
$payment_date = date('Y-m-d');

// Get all active staff members with their salaries
$staff_members = $db->query("SELECT * FROM staff WHERE status = 'active' ORDER BY department, first_name, last_name")->fetchAll();

// Check which staff have already been paid for the current period
$paid_staff = [];
if (!empty($staff_members)) {
    $staff_ids = array_column($staff_members, 'id');
    $placeholders = str_repeat('?,', count($staff_ids) - 1) . '?';
    $paid_stmt = $db->prepare("SELECT staff_id FROM staff_payments WHERE staff_id IN ($placeholders) AND payment_period = ? AND status = 'paid'");
    $paid_stmt->execute(array_merge($staff_ids, [$current_period]));
    $paid_staff = $paid_stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get payroll statistics
$total_staff = count($staff_members);
$staff_with_salary = $db->query("SELECT COUNT(*) FROM staff WHERE status = 'active' AND salary IS NOT NULL AND salary > 0")->fetchColumn();
$total_monthly_payroll = $db->query("SELECT SUM(salary) FROM staff WHERE status = 'active' AND salary IS NOT NULL")->fetchColumn();

// Get recent payroll runs
$recent_payroll = $db->query("SELECT payment_period, COUNT(*) as staff_count, SUM(net_pay) as total_paid, MAX(created_at) as last_paid FROM staff_payments WHERE status = 'paid' GROUP BY payment_period ORDER BY last_paid DESC LIMIT 5")->fetchAll();
?>

<!-- Payroll Management Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Payroll Management</h1>
            <p class="text-[#8d6a5e]">Bulk salary processing and payroll management</p>
        </div>
        <div class="mt-4 lg:mt-0 flex gap-3">
            <button onclick="generatePayrollReport()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">download</span>
                Export Report
            </button>
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

<!-- Payroll Overview -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Active Staff</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_staff; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">groups</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">With Salary Set</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $staff_with_salary; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">attach_money</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Monthly Payroll</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($total_monthly_payroll, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">receipt_long</span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Payroll Processing Form -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                <h3 class="text-lg font-bold text-[#181210] flex items-center">
                    <span class="material-symbols-outlined mr-2 text-[#ff6933]">receipt_long</span>
                    Process Payroll - <?php echo htmlspecialchars($current_period); ?>
                </h3>
                <div class="mt-2 sm:mt-0">
                    <select onchange="window.location.href='?page=payroll&period='+this.value" 
                            class="h-10 px-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] text-sm">
                        <option value="<?php echo date('F Y'); ?>" <?php echo $current_period === date('F Y') ? 'selected' : ''; ?>>Current Month</option>
                        <option value="<?php echo date('F Y', strtotime('last month')); ?>" <?php echo $current_period === date('F Y', strtotime('last month')) ? 'selected' : ''; ?>>Last Month</option>
                        <option value="<?php echo date('F Y', strtotime('+1 month')); ?>" <?php echo $current_period === date('F Y', strtotime('+1 month')) ? 'selected' : ''; ?>>Next Month</option>
                    </select>
                </div>
            </div>

            <form method="POST" id="payrollForm">
                <input type="hidden" name="payment_date" value="<?php echo $payment_date; ?>">
                <input type="hidden" name="payment_period" value="<?php echo $current_period; ?>">

                <!-- Staff Selection Table -->
                <div class="overflow-hidden border border-[#e7deda] rounded-lg">
                    <table class="w-full text-left">
                        <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                            <tr>
                                <th class="p-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="selectAll" class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]">
                                        <span class="ml-2 text-sm font-semibold text-[#8d6a5e]">Select All</span>
                                    </label>
                                </th>
                                <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Staff Member</th>
                                <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Department</th>
                                <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Salary</th>
                                <th class="p-3 text-sm font-semibold text-[#8d6a5e]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e7deda]">
                            <?php if (empty($staff_members)): ?>
                                <tr>
                                    <td colspan="5" class="p-8 text-center text-[#8d6a5e]">
                                        <span class="material-symbols-outlined text-4xl mb-2">group</span>
                                        <p>No active staff members found.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staff_members as $staff): ?>
                                <?php 
                                $is_paid = in_array($staff['id'], $paid_staff);
                                $has_salary = !empty($staff['salary']) && $staff['salary'] > 0;
                                $can_process = $has_salary && !$is_paid;
                                ?>
                                <tr class="hover:bg-[#f8f6f5] transition-colors <?php echo $is_paid ? 'bg-green-50' : ''; ?>">
                                    <td class="p-3">
                                        <?php if ($can_process): ?>
                                            <input type="checkbox" name="selected_staff[]" value="<?php echo $staff['id']; ?>" 
                                                   class="staff-checkbox rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]">
                                        <?php else: ?>
                                            <span class="material-symbols-outlined text-gray-400 text-sm">block</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-8 w-8 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                                                <span class="material-symbols-outlined text-[#ff6933] text-sm">person</span>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-[#181210]">
                                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                </p>
                                                <p class="text-xs text-[#8d6a5e]"><?php echo htmlspecialchars($staff['staff_id']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm text-[#181210]">
                                        <?php echo htmlspecialchars($staff['department'] ?? 'Not assigned'); ?>
                                    </td>
                                    <td class="p-3 text-sm text-[#181210] font-medium">
                                        <?php if ($has_salary): ?>
                                            ₦<?php echo number_format($staff['salary'], 2); ?>
                                        <?php else: ?>
                                            <span class="text-red-500 text-xs">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3">
                                        <?php if ($is_paid): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <span class="material-symbols-outlined text-xs mr-1">check_circle</span>
                                                Paid
                                            </span>
                                        <?php elseif (!$has_salary): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                <span class="material-symbols-outlined text-xs mr-1">error</span>
                                                No Salary
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <span class="material-symbols-outlined text-xs mr-1">pending</span>
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Payroll Summary -->
                <div class="mt-6 bg-[#f8f6f5] rounded-lg p-4">
                    <h4 class="font-semibold text-[#181210] mb-3 flex items-center text-sm">
                        <span class="material-symbols-outlined mr-2 text-[#ff6933] text-base">summarize</span>
                        Payroll Summary
                    </h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-[#8d6a5e]">Selected Staff</p>
                            <p id="selectedCount" class="font-bold text-[#181210]">0</p>
                        </div>
                        <div>
                            <p class="text-[#8d6a5e]">Total Salary</p>
                            <p id="totalSalary" class="font-bold text-[#181210]">₦0.00</p>
                        </div>
                        <div>
                            <p class="text-[#8d6a5e]">Estimated Tax</p>
                            <p id="estimatedTax" class="font-bold text-[#181210]">₦0.00</p>
                        </div>
                        <div>
                            <p class="text-[#8d6a5e]">Net Payout</p>
                            <p id="netPayout" class="font-bold text-[#181210]">₦0.00</p>
                        </div>
                    </div>
                </div>

                <!-- Process Button -->
                <div class="mt-6">
                    <button type="submit" name="process_payroll" id="processButton"
                            class="w-full h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                        <span class="material-symbols-outlined mr-2">play_arrow</span>
                        Process Payroll for <span id="staffCountText">0 Staff</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Recent Payroll Runs -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">history</span>
                Recent Payroll
            </h3>
            
            <?php if (empty($recent_payroll)): ?>
                <div class="text-center py-8 text-[#8d6a5e]">
                    <span class="material-symbols-outlined text-4xl mb-2">receipt_long</span>
                    <p class="text-sm">No payroll runs yet</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recent_payroll as $payroll): ?>
                    <div class="p-3 border border-[#e7deda] rounded-lg">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm font-medium text-[#181210]"><?php echo htmlspecialchars($payroll['payment_period']); ?></p>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                <?php echo $payroll['staff_count']; ?> staff
                            </span>
                        </div>
                        <p class="text-lg font-bold text-[#181210]">₦<?php echo number_format($payroll['total_paid'], 2); ?></p>
                        <p class="text-xs text-[#8d6a5e]"><?php echo date('M j, Y', strtotime($payroll['last_paid'])); ?></p>
                    </div>
                    <?php endforeach; ?>
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
                <a href="?page=pay-staff" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">payments</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Individual Payment</p>
                        <p class="text-xs text-[#8d6a5e]">Process single staff payment</p>
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
                
                <a href="?page=staff-list" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">badge</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Manage Staff</p>
                        <p class="text-xs text-[#8d6a5e]">Update staff salaries</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    // Select All functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.staff-checkbox');
        checkboxes.forEach(checkbox => {
            if (!checkbox.disabled) {
                checkbox.checked = this.checked;
            }
        });
        updatePayrollSummary();
    });

    // Update payroll summary when checkboxes change
    document.querySelectorAll('.staff-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updatePayrollSummary);
    });

    function updatePayrollSummary() {
        const selectedCheckboxes = document.querySelectorAll('.staff-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;
        
        let totalSalary = 0;
        
        selectedCheckboxes.forEach(checkbox => {
            const staffRow = checkbox.closest('tr');
            const salaryText = staffRow.querySelector('td:nth-child(4)').textContent;
            const salary = parseFloat(salaryText.replace('₦', '').replace(/,/g, '')) || 0;
            totalSalary += salary;
        });
        
        const estimatedTax = totalSalary * 0.05; // 5% tax
        const estimatedPension = totalSalary * 0.075; // 7.5% pension
        const netPayout = totalSalary - estimatedTax - estimatedPension;
        
        // Update display
        document.getElementById('selectedCount').textContent = selectedCount;
        document.getElementById('totalSalary').textContent = '₦' + totalSalary.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('estimatedTax').textContent = '₦' + estimatedTax.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        document.getElementById('netPayout').textContent = '₦' + netPayout.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        // Update button
        const processButton = document.getElementById('processButton');
        const staffCountText = document.getElementById('staffCountText');
        
        if (selectedCount > 0) {
            processButton.disabled = false;
            staffCountText.textContent = selectedCount + ' Staff';
        } else {
            processButton.disabled = true;
            staffCountText.textContent = '0 Staff';
        }
    }

    function generatePayrollReport() {
        alert('Payroll report generation will be implemented - This will download a comprehensive payroll report as PDF/Excel');
        // Implementation for PDF/Excel report generation
    }

    // Initial summary update
    updatePayrollSummary();
</script>