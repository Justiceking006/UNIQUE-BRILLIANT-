<!-- Student Dashboard Overview -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210] mb-2">Dashboard</h1>
    <p class="text-[#8d6a5e]">Good day, <?php echo htmlspecialchars($student['first_name']); ?>! 👋</p>
</div>

<!-- Profile Card -->
<div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
    <div class="flex justify-between items-start mb-4">
        <h2 class="text-xl font-bold text-[#181210]">Profile</h2>
        <a href="?page=profile" class="text-[#ff6933] hover:underline flex items-center text-sm font-medium">
            Check your profile →
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="space-y-1">
            <p class="text-sm text-[#8d6a5e]">Matric. No.</p>
            <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($student['student_code']); ?></p>
        </div>
        <div class="space-y-1">
            <p class="text-sm text-[#8d6a5e]">Level</p>
            <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($level_display); ?></p>
        </div>
        <div class="space-y-1">
            <p class="text-sm text-[#8d6a5e]">Examination Centre</p>
            <p class="font-bold text-[#181210]">Main Campus</p>
        </div>
        <div class="space-y-1">
            <p class="text-sm text-[#8d6a5e]">Programme</p>
            <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($programme_display); ?></p>
        </div>
    </div>
</div>

<!-- Financial Overview -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Account Status</p>
                <p class="text-2xl font-bold text-[#181210] capitalize">
                    <?php echo htmlspecialchars($student['status']); ?>
                </p>
                <?php if ($student['status'] === 'approved' && $student['student_pin']): ?>
                    <p class="text-xs text-[#8d6a5e] mt-1">PIN: <?php echo htmlspecialchars($student['student_pin']); ?></p>
                <?php endif; ?>
            </div>
            <span class="material-symbols-outlined text-3xl 
                <?php echo $student['status'] === 'approved' ? 'text-green-500' : 'text-yellow-500'; ?>">
                <?php echo $student['status'] === 'approved' ? 'verified' : 'pending'; ?>
            </span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Paid</p>
                <p class="text-2xl font-bold text-green-600">₦<?php echo number_format($fee_stats['total_paid'] ?? 0, 2); ?></p>
                <p class="text-xs text-[#8d6a5e] mt-1">Approved payments</p>
            </div>
            <span class="material-symbols-outlined text-3xl text-green-500">payments</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Pending Payments</p>
                <p class="text-2xl font-bold text-orange-600"><?php echo $fee_stats['pending_payments'] ?? 0; ?></p>
                <p class="text-xs text-[#8d6a5e] mt-1">₦<?php echo number_format($fee_stats['total_pending'] ?? 0, 2); ?> awaiting approval</p>
            </div>
            <span class="material-symbols-outlined text-3xl text-orange-500">pending</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Upcoming Events</p>
                <p class="text-2xl font-bold text-[#181210]"><?php echo $upcoming_events; ?></p>
                <p class="text-xs text-[#8d6a5e] mt-1">Next 30 days</p>
            </div>
            <span class="material-symbols-outlined text-3xl text-purple-500">event</span>
        </div>
    </div>
</div>

<!-- Recent Fee Transactions -->
<div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold text-[#181210]">Recent Fee Transactions</h3>
        <a href="?page=fees" class="text-[#ff6933] hover:underline flex items-center text-sm font-medium">
            View all fees →
        </a>
    </div>
    
    <div class="space-y-3">
        <?php if (empty($recent_transactions)): ?>
            <div class="text-center py-4">
                <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">receipt_long</span>
                <p class="text-[#8d6a5e]">No fee transactions yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($recent_transactions as $transaction): ?>
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
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($transaction['description']); ?></p>
                            <p class="text-sm text-[#8d6a5e] capitalize">
                                <?php echo str_replace('_', ' ', $transaction['term']); ?> • 
                                <?php echo $transaction['transaction_type'] === 'payment' ? 'Payment' : 'Fee Issued'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-[#181210] 
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
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>