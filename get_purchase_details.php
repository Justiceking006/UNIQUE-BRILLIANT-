<?php
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

$purchase_id = intval($_GET['id']);
$db = getDBConnection();

// Get purchase details
$stmt = $db->prepare("
    SELECT pr.*, s.first_name, s.last_name, s.email, s.guardian_phone, s.guardian_name,
           st.first_name as admin_first, st.last_name as admin_last
    FROM purchase_requests pr 
    JOIN students s ON pr.student_id = s.id 
    LEFT JOIN staff st ON pr.approved_by = st.id
    WHERE pr.id = ?
");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch();

if (!$purchase) {
    echo '<p class="text-red-600">Purchase request not found</p>';
    exit;
}

$items = json_decode($purchase['items'], true);
?>

<div class="space-y-6">
    <!-- Student Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h4 class="text-lg font-bold text-[#181210] mb-3">Student Information</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Name:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['student_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Class:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['class']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Email:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['email']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Guardian:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['guardian_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Guardian Phone:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['guardian_phone']); ?></span>
                </div>
            </div>
        </div>

        <!-- Purchase Information -->
        <div>
            <h4 class="text-lg font-bold text-[#181210] mb-3">Purchase Information</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Purchase Date:</span>
                    <span class="font-medium text-[#181210]"><?php echo date('F j, Y', strtotime($purchase['purchase_date'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Submitted:</span>
                    <span class="font-medium text-[#181210]"><?php echo date('F j, Y g:i A', strtotime($purchase['created_at'])); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Status:</span>
                    <span class="font-medium text-[#181210]">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                            <?php echo $purchase['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                     ($purchase['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'); ?>">
                            <?php echo ucfirst($purchase['status']); ?>
                        </span>
                    </span>
                </div>
                <?php if ($purchase['approved_at']): ?>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Processed By:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['admin_first'] . ' ' . $purchase['admin_last']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Processed At:</span>
                    <span class="font-medium text-[#181210]"><?php echo date('F j, Y g:i A', strtotime($purchase['approved_at'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Purchased Items -->
    <div>
        <h4 class="text-lg font-bold text-[#181210] mb-3">Purchased Items</h4>
        <div class="bg-[#f8f6f5] rounded-lg p-4">
            <?php if (is_array($items) && !empty($items)): ?>
                <div class="space-y-3">
                    <?php foreach ($items as $item): ?>
                    <div class="flex justify-between items-center py-2 border-b border-[#e7deda] last:border-b-0">
                        <div>
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($item['name'] ?? $item['item_name']); ?></p>
                            <p class="text-sm text-[#8d6a5e]">
                                <?php if (isset($item['type'])): ?>
                                    Type: <?php echo ucfirst($item['type']); ?> 
                                <?php endif; ?>
                                <?php if (isset($item['quantity'])): ?>
                                    • Quantity: <?php echo $item['quantity']; ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-[#181210]">₦<?php echo number_format($item['price'] ?? $item['unit_price'], 2); ?></p>
                            <?php if (isset($item['quantity']) && $item['quantity'] > 1): ?>
                                <p class="text-sm text-[#8d6a5e]">₦<?php echo number_format(($item['price'] ?? $item['unit_price']) * $item['quantity'], 2); ?> total</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-[#8d6a5e] text-center py-4">No item details available</p>
            <?php endif; ?>
            
            <!-- Total Amount -->
            <div class="flex justify-between items-center pt-4 mt-4 border-t border-[#e7deda]">
                <span class="text-lg font-bold text-[#181210]">Total Amount:</span>
                <span class="text-xl font-bold text-[#ff6933]">₦<?php echo number_format($purchase['total_amount'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Receipt Image -->
    <?php if ($purchase['receipt_image']): ?>
    <div>
        <h4 class="text-lg font-bold text-[#181210] mb-3">Receipt</h4>
        <div class="bg-[#f8f6f5] rounded-lg p-4 text-center">
            <img src="<?php echo htmlspecialchars($purchase['receipt_image']); ?>" 
                 alt="Purchase Receipt" 
                 class="max-w-full h-auto mx-auto rounded-lg max-h-64 object-contain">
            <p class="text-sm text-[#8d6a5e] mt-2">Uploaded receipt image</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Admin Notes -->
    <?php if ($purchase['admin_notes']): ?>
    <div>
        <h4 class="text-lg font-bold text-[#181210] mb-3">Admin Notes</h4>
        <div class="bg-[#f8f6f5] rounded-lg p-4">
            <p class="text-[#181210]"><?php echo nl2br(htmlspecialchars($purchase['admin_notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>