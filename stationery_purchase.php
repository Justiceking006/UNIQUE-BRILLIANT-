<?php
//stationery_purchase.php
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle purchase approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_purchase'])) {
        $purchase_id = intval($_POST['purchase_id']);
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        try {
            $stmt = $db->prepare("UPDATE purchase_requests SET status = 'approved', admin_notes = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
            if ($stmt->execute([$admin_notes, $_SESSION['user_id'], $purchase_id])) {
                $success_message = "Purchase request approved successfully!";
            }
        } catch (PDOException $e) {
            $error_message = "Error approving purchase: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['reject_purchase'])) {
        $purchase_id = intval($_POST['purchase_id']);
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        try {
            $stmt = $db->prepare("UPDATE purchase_requests SET status = 'rejected', admin_notes = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
            if ($stmt->execute([$admin_notes, $_SESSION['user_id'], $purchase_id])) {
                $success_message = "Purchase request rejected!";
            }
        } catch (PDOException $e) {
            $error_message = "Error rejecting purchase: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_purchase'])) {
        $purchase_id = intval($_POST['purchase_id']);
        
        try {
            // First delete related items
            $stmt = $db->prepare("DELETE FROM purchase_items WHERE purchase_request_id = ?");
            $stmt->execute([$purchase_id]);
            
            // Then delete the purchase request
            $stmt = $db->prepare("DELETE FROM purchase_requests WHERE id = ?");
            if ($stmt->execute([$purchase_id])) {
                $success_message = "Purchase request deleted successfully!";
            }
        } catch (PDOException $e) {
            $error_message = "Error deleting purchase: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query for purchase requests
$query = "SELECT pr.*, s.first_name, s.last_name, s.email, s.guardian_phone 
          FROM purchase_requests pr 
          JOIN students s ON pr.student_id = s.id 
          WHERE 1=1";
$params = [];

if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND pr.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR pr.student_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($date_from)) {
    $query .= " AND pr.purchase_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND pr.purchase_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY pr.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

// Get statistics
$total_purchases = $db->query("SELECT COUNT(*) FROM purchase_requests")->fetchColumn();
$pending_purchases = $db->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'pending'")->fetchColumn();
$approved_purchases = $db->query("SELECT COUNT(*) FROM purchase_requests WHERE status = 'approved'")->fetchColumn();
$total_revenue = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchase_requests WHERE status = 'approved'")->fetchColumn();
?>

<!-- Stationery Purchase Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Book & Stationery Purchases</h1>
            <p class="text-[#8d6a5e]">Manage and approve student purchase requests</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <a href="?page=inventory" 
               class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors mr-3">
                <span class="material-symbols-outlined mr-2 text-lg">inventory_2</span>
                View Inventory
            </a>
            <a href="?page=stationery" 
               class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">description</span>
                Stationery Items
            </a>
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

<!-- Statistics Overview -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Purchases</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_purchases; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">shopping_cart</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Pending Approval</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $pending_purchases; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">pending</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Approved</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $approved_purchases; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">check_circle</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Revenue</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($total_revenue, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">payments</span>
        </div>
    </div>
</section>

<!-- Filters -->
<section class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
    <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">filter_alt</span>
        Filter Purchases
    </h3>
    
    <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <input type="hidden" name="page" value="stationery-purchase">
        
        <!-- Search -->
        <div class="md:col-span-2 lg:col-span-2">
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Search Student</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                   placeholder="Search by student name or email">
        </div>
        
        <!-- Status Filter -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Status</label>
            <select name="status" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        
        <!-- Date From -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
        </div>
        
        <!-- Date To -->
        <div>
            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
        </div>
        
        <!-- Filter Buttons -->
        <div class="md:col-span-2 lg:col-span-5 flex gap-3 justify-end pt-2">
            <button type="submit" 
                    class="h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">search</span>
                Apply Filters
            </button>
            <a href="?page=stationery-purchase" 
               class="h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">refresh</span>
                Reset
            </a>
        </div>
    </form>
</section>

<!-- Purchase Requests Table -->
<section class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <h3 class="text-lg font-bold text-[#181210] mb-6 flex items-center">
        <span class="material-symbols-outlined mr-2 text-[#ff6933]">list_alt</span>
        Purchase Requests
    </h3>
    
    <?php if (empty($purchases)): ?>
        <!-- Empty State -->
        <div class="text-center py-12">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">shopping_cart</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Purchase Requests Found</h3>
            <p class="text-[#8d6a5e] mb-6"><?php echo !empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to) ? 'Try adjusting your filters' : 'No purchase requests have been submitted yet'; ?></p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#e7deda]">
                        <th class="text-left py-3 px-4 text-sm font-medium text-[#8d6a5e]">Student & Class</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-[#8d6a5e]">Purchase Details</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-[#8d6a5e]">Amount</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-[#8d6a5e]">Date</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-[#8d6a5e]">Status</th>
                        <th class="text-left py-3 px-4 text-sm font-medium text-[#8d6a5e]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase): 
                        $items = json_decode($purchase['items'], true);
                    ?>
                    <tr class="border-b border-[#e7deda] hover:bg-[#f8f6f5]">
                        <td class="py-4 px-4">
                            <div>
                                <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($purchase['student_name']); ?></p>
                                <p class="text-sm text-[#8d6a5e]"><?php echo htmlspecialchars($purchase['class']); ?></p>
                                <p class="text-xs text-[#8d6a5e]"><?php echo htmlspecialchars($purchase['email']); ?></p>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <div class="max-w-xs">
                                <?php if (is_array($items)): ?>
                                    <?php foreach (array_slice($items, 0, 3) as $item): ?>
                                        <p class="text-sm text-[#181210]">• <?php echo htmlspecialchars($item['name'] ?? $item['item_name']); ?> 
                                            <?php if (isset($item['quantity'])): ?>(x<?php echo $item['quantity']; ?>)<?php endif; ?>
                                        </p>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 3): ?>
                                        <p class="text-sm text-[#8d6a5e]">+<?php echo count($items) - 3; ?> more items</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-sm text-[#8d6a5e]">No items details</p>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-lg font-bold text-[#181210]">₦<?php echo number_format($purchase['total_amount'], 2); ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <p class="text-sm text-[#181210]"><?php echo date('M j, Y', strtotime($purchase['purchase_date'])); ?></p>
                            <p class="text-xs text-[#8d6a5e]"><?php echo date('g:i A', strtotime($purchase['created_at'])); ?></p>
                        </td>
                        <td class="py-4 px-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium 
                                <?php echo $purchase['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                         ($purchase['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'); ?>">
                                <span class="material-symbols-outlined text-xs mr-1">
                                    <?php echo $purchase['status'] === 'approved' ? 'check_circle' : 
                                           ($purchase['status'] === 'rejected' ? 'cancel' : 'pending'); ?>
                                </span>
                                <?php echo ucfirst($purchase['status']); ?>
                            </span>
                            <?php if ($purchase['approved_at']): ?>
                                <p class="text-xs text-[#8d6a5e] mt-1">By Admin</p>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-4">
                            <div class="flex space-x-2">
                                <button onclick="viewPurchaseDetails(<?php echo $purchase['id']; ?>)" 
                                        class="flex items-center p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="View Details">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </button>
                                
                                <!-- Add Receipt View Button -->
                                <?php if ($purchase['receipt_filename']): ?>
                                <button onclick="viewReceipt('<?php echo htmlspecialchars($purchase['receipt_filename']); ?>')" 
                                        class="flex items-center p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                        title="View Receipt">
                                    <span class="material-symbols-outlined text-base">receipt</span>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($purchase['status'] === 'pending'): ?>
                                    <button onclick="approvePurchase(<?php echo $purchase['id']; ?>)" 
                                            class="flex items-center p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                            title="Approve">
                                        <span class="material-symbols-outlined text-base">check</span>
                                    </button>
                                    
                                    <button onclick="rejectPurchase(<?php echo $purchase['id']; ?>)" 
                                            class="flex items-center p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Reject">
                                        <span class="material-symbols-outlined text-base">close</span>
                                    </button>
                                <?php endif; ?>
                                
                                <button onclick="confirmDeletePurchase(<?php echo $purchase['id']; ?>, '<?php echo htmlspecialchars($purchase['student_name']); ?>')" 
                                        class="flex items-center p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<!-- View Purchase Details Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="viewDetailsModal">
    <div class="bg-white rounded-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Purchase Details</h3>
                <button onclick="closeViewDetailsModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <div class="p-6" id="purchaseDetailsContent">
            <!-- Content will be loaded via AJAX -->
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

<!-- Approve/Reject Purchase Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="actionModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]" id="actionModalTitle">Approve Purchase</h3>
                <button onclick="closeActionModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <input type="hidden" name="purchase_id" id="actionPurchaseId">
            <input type="hidden" name="approve_purchase" id="approveInput">
            <input type="hidden" name="reject_purchase" id="rejectInput">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Admin Notes (Optional)</label>
                <textarea name="admin_notes" rows="3"
                          class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                          placeholder="Add any notes or comments..."></textarea>
            </div>
            
            <div class="flex space-x-3">
                <button type="button" onclick="closeActionModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" 
                        class="flex-1 h-12 rounded-lg text-white font-bold transition-colors"
                        id="actionButton">
                    Confirm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="deleteModal">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <div class="flex flex-col items-center text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-4">
                <span class="material-symbols-outlined text-3xl text-red-500">warning</span>
            </div>
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Purchase Request</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this purchase request?</p>
            <div class="flex space-x-3 w-full">
                <button onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="purchase_id" id="deletePurchaseId">
                    <button type="submit" name="delete_purchase" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function viewPurchaseDetails(purchaseId) {
        // Load purchase details via AJAX
        fetch(`get_purchase_details.php?id=${purchaseId}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('purchaseDetailsContent').innerHTML = data;
                document.getElementById('viewDetailsModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error loading purchase details:', error);
                document.getElementById('purchaseDetailsContent').innerHTML = '<p class="text-red-600">Error loading details</p>';
                document.getElementById('viewDetailsModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
    }

    function closeViewDetailsModal() {
        document.getElementById('viewDetailsModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function viewReceipt(receiptFilename) {
        const receiptPath = `uploads/receipts/${receiptFilename}`;
        document.getElementById('receiptImage').src = receiptPath;
        document.getElementById('receiptModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
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

    function approvePurchase(purchaseId) {
        document.getElementById('actionPurchaseId').value = purchaseId;
        document.getElementById('actionModalTitle').textContent = 'Approve Purchase';
        document.getElementById('approveInput').value = '1';
        document.getElementById('rejectInput').removeAttribute('name');
        document.getElementById('actionButton').textContent = 'Approve';
        document.getElementById('actionButton').className = 'flex-1 h-12 rounded-lg bg-green-600 text-white font-bold hover:bg-green-700 transition-colors';
        document.getElementById('actionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function rejectPurchase(purchaseId) {
        document.getElementById('actionPurchaseId').value = purchaseId;
        document.getElementById('actionModalTitle').textContent = 'Reject Purchase';
        document.getElementById('rejectInput').value = '1';
        document.getElementById('approveInput').removeAttribute('name');
        document.getElementById('actionButton').textContent = 'Reject';
        document.getElementById('actionButton').className = 'flex-1 h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors';
        document.getElementById('actionModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeActionModal() {
        document.getElementById('actionModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function confirmDeletePurchase(purchaseId, studentName) {
        document.getElementById('deletePurchaseId').value = purchaseId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete the purchase request for <strong>${studentName}</strong>? This action cannot be undone.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('viewDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeViewDetailsModal();
        }
    });

    document.getElementById('receiptModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReceiptModal();
        }
    });

    document.getElementById('actionModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeActionModal();
        }
    });

    document.getElementById('deleteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeViewDetailsModal();
            closeReceiptModal();
            closeActionModal();
            closeDeleteModal();
        }
    });
</script>