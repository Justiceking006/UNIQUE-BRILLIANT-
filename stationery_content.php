<?php
// stationery_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle stationery item creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stationery'])) {
    $item_name = $_POST['item_name'];
    $description = $_POST['description'];
    $price = floatval($_POST['price']);
    $category = $_POST['category'];
    
    try {
        $stmt = $db->prepare("INSERT INTO stationery_items (item_name, description, price, category) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$item_name, $description, $price, $category])) {
            $success_message = "Stationery item added successfully!";
        } else {
            $error_message = "Failed to add stationery item. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle stationery item update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stationery'])) {
    $item_id = intval($_POST['item_id']);
    $item_name = $_POST['item_name'];
    $description = $_POST['description'];
    $price = floatval($_POST['price']);
    $category = $_POST['category'];
    
    try {
        $stmt = $db->prepare("UPDATE stationery_items SET item_name = ?, description = ?, price = ?, category = ? WHERE id = ?");
        
        if ($stmt->execute([$item_name, $description, $price, $category, $item_id])) {
            $success_message = "Stationery item updated successfully!";
        } else {
            $error_message = "Failed to update stationery item. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle stationery item deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stationery'])) {
    $item_id = intval($_POST['item_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM stationery_items WHERE id = ?");
        
        if ($stmt->execute([$item_id])) {
            $success_message = "Stationery item deleted successfully!";
        } else {
            $error_message = "Failed to delete stationery item. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $item_id = intval($_POST['item_id']);
    $current_status = $_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    $stmt = $db->prepare("UPDATE stationery_items SET is_available = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $item_id])) {
        $success_message = "Stationery item availability updated!";
    } else {
        $error_message = "Failed to update stationery item availability.";
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Build query
$query = "SELECT * FROM stationery_items WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (item_name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter) && $category_filter !== 'all') {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

$query .= " ORDER BY category, item_name";

// Get stationery items data
$stmt = $db->prepare($query);
$stmt->execute($params);
$stationery_items = $stmt->fetchAll();

// Get unique categories for filter
$categories = $db->query("SELECT DISTINCT category FROM stationery_items WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$total_items = count($stationery_items);
$available_items = $db->query("SELECT COUNT(*) FROM stationery_items WHERE is_available = 1")->fetchColumn();
$total_categories = count($categories);
$average_price = $db->query("SELECT AVG(price) FROM stationery_items")->fetchColumn();
$total_value = $db->query("SELECT SUM(price) FROM stationery_items WHERE is_available = 1")->fetchColumn();
?>

<!-- Stationery Items Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Stationery Items</h1>
            <p class="text-[#8d6a5e]">Manage stationery stock and pricing</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <button onclick="openAddStationeryModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">add</span>
                Add New Item
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

<!-- Statistics Overview -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Items</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_items; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">inventory_2</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Available</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $available_items; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">check_circle</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Categories</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_categories; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">category</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Avg Price</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]">₦<?php echo number_format($average_price, 2); ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">attach_money</span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-3">
        <!-- Filters -->
        <section class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">filter_alt</span>
                Filter Items
            </h3>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <input type="hidden" name="page" value="stationery">
                
                <!-- Search -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Search by item name or description">
                </div>
                
                <!-- Category Filter -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Category</label>
                    <select name="category" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="all" <?php echo $category_filter === 'all' || empty($category_filter) ? 'selected' : ''; ?>>All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filter Buttons -->
                <div class="md:col-span-4 flex gap-3 justify-end pt-2">
                    <button type="submit" 
                            class="h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                        <span class="material-symbols-outlined mr-2">search</span>
                        Apply Filters
                    </button>
                    <a href="?page=stationery" 
                       class="h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                        <span class="material-symbols-outlined mr-2">refresh</span>
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Stationery Items Grid -->
        <section>
            <?php if (empty($stationery_items)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
                    <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">inventory_2</span>
                    <h3 class="text-xl font-bold text-[#181210] mb-2">No Stationery Items Found</h3>
                    <p class="text-[#8d6a5e] mb-6"><?php echo !empty($search) || !empty($category_filter) ? 'Try adjusting your filters' : 'Get started by adding your first stationery item'; ?></p>
                    <?php if (empty($search) && empty($category_filter)): ?>
                        <button onclick="openAddStationeryModal()"
                                class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                            <span class="material-symbols-outlined mr-2">add</span>
                            Add First Item
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Stationery Items Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($stationery_items as $item): ?>
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] hover:shadow-md transition-shadow">
                        <!-- Item Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-[#181210] mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                                <p class="text-sm text-[#8d6a5e]"><?php echo htmlspecialchars($item['category'] ?: 'General'); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $item['is_available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <span class="material-symbols-outlined text-xs mr-1">
                                    <?php echo $item['is_available'] ? 'check_circle' : 'block'; ?>
                                </span>
                                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </div>

                        <!-- Item Details -->
                        <div class="space-y-3 text-sm text-[#8d6a5e] mb-4">
                            <?php if ($item['description']): ?>
                            <div>
                                <p class="text-sm text-[#8d6a5e]"><?php echo htmlspecialchars($item['description']); ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between">
                                <span>Price:</span>
                                <span class="font-medium text-[#181210]">₦<?php echo number_format($item['price'], 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Added:</span>
                                <span class="font-medium text-[#181210]"><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-between gap-2 border-t border-[#e7deda] pt-4">
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $item['is_available']; ?>">
                                <button type="submit" name="toggle_availability" 
                                        class="w-full flex items-center justify-center p-2 text-sm 
                                            <?php echo $item['is_available'] ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> 
                                            rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-base mr-1">
                                        <?php echo $item['is_available'] ? 'block' : 'check_circle'; ?>
                                    </span>
                                    <?php echo $item['is_available'] ? 'Mark Unavailable' : 'Mark Available'; ?>
                                </button>
                            </form>
                            
                            <button onclick="editStationery(<?php echo $item['id']; ?>)" 
                                    class="flex-1 flex items-center justify-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-base mr-1">edit</span>
                                Edit
                            </button>
                            
                            <button onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')" 
                                    class="flex-1 flex items-center justify-center p-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-base mr-1">delete</span>
                                Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Quick Summary -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">summarize</span>
                Quick Summary
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Items:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_items; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Available Items:</span>
                    <span class="font-medium text-[#181210]"><?php echo $available_items; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Categories:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_categories; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Avg Price:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($average_price, 2); ?></span>
                </div>
                <div class="flex justify-between border-t border-[#e7deda] pt-2">
                    <span class="text-[#8d6a5e] font-medium">Total Value:</span>
                    <span class="font-bold text-[#181210]">₦<?php echo number_format($total_value, 2); ?></span>
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
                <button onclick="openAddStationeryModal()" 
                       class="w-full flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors text-left">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">add</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Add New Item</p>
                        <p class="text-xs text-[#8d6a5e]">Add new stationery item</p>
                    </div>
                </button>
                
                <a href="?page=inventory" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">menu_book</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Book Inventory</p>
                        <p class="text-xs text-[#8d6a5e]">Manage textbook inventory</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Popular Items -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">trending_up</span>
                Common Categories
            </h3>
            
            <div class="space-y-3">
                <?php
                $category_counts = $db->query("SELECT category, COUNT(*) as count FROM stationery_items GROUP BY category ORDER BY count DESC LIMIT 5")->fetchAll();
                foreach ($category_counts as $category): ?>
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-[#181210]"><?php echo htmlspecialchars($category['category'] ?: 'General'); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-[#181210]"><?php echo $category['count']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Stationery Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="addStationeryModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Add New Stationery Item</h3>
                <button onclick="closeAddStationeryModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Item Name *</label>
                    <input type="text" name="item_name" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="e.g., Exercise Book 40 Leaves">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Description</label>
                    <textarea name="description" rows="2"
                              class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                              placeholder="Brief description of the item..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Price (₦) *</label>
                        <input type="number" name="price" required step="0.01" min="0"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Category</label>
                        <input type="text" name="category"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Writing, Drawing">
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeAddStationeryModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_stationery"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Add Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Stationery Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="editStationeryModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Edit Stationery Item</h3>
                <button onclick="closeEditStationeryModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <input type="hidden" name="item_id" id="editItemId">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Item Name *</label>
                    <input type="text" name="item_name" id="editItemName" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="e.g., Exercise Book 40 Leaves">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Description</label>
                    <textarea name="description" id="editDescription" rows="2"
                              class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                              placeholder="Brief description of the item..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Price (₦) *</label>
                        <input type="number" name="price" id="editPrice" required step="0.01" min="0"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="0.00">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Category</label>
                        <input type="text" name="category" id="editCategory"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Writing, Drawing">
                    </div>
                </div>

                <!-- Current Status Display -->
                <div class="bg-[#f8f6f5] rounded-lg p-3">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Current Status</label>
                    <span id="editStatusDisplay" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"></span>
                    <p class="text-xs text-[#8d6a5e] mt-1">Use the toggle button on the item card to change availability</p>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeEditStationeryModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="update_stationery"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Update Item
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
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Item</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this item?</p>
            <div class="flex space-x-3 w-full">
                <button onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="item_id" id="deleteItemId">
                    <button type="submit" name="delete_stationery" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Stationery data for editing
    const stationeryData = <?php echo json_encode($stationery_items); ?>;

    function openAddStationeryModal() {
        document.getElementById('addStationeryModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAddStationeryModal() {
        document.getElementById('addStationeryModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openEditStationeryModal() {
        document.getElementById('editStationeryModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditStationeryModal() {
        document.getElementById('editStationeryModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function editStationery(itemId) {
        const itemInfo = stationeryData.find(item => item.id == itemId);
        
        if (itemInfo) {
            // Populate form fields
            document.getElementById('editItemId').value = itemInfo.id;
            document.getElementById('editItemName').value = itemInfo.item_name;
            document.getElementById('editDescription').value = itemInfo.description || '';
            document.getElementById('editPrice').value = itemInfo.price;
            document.getElementById('editCategory').value = itemInfo.category || '';
            
            // Update status display
            const statusDisplay = document.getElementById('editStatusDisplay');
            statusDisplay.textContent = itemInfo.is_available ? 'Available' : 'Unavailable';
            statusDisplay.className = `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                itemInfo.is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`;
            
            openEditStationeryModal();
        }
    }

    function confirmDelete(itemId, itemName) {
        document.getElementById('deleteItemId').value = itemId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete <strong>${itemName}</strong>? This action cannot be undone.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('addStationeryModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddStationeryModal();
        }
    });

    document.getElementById('editStationeryModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditStationeryModal();
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
            closeAddStationeryModal();
            closeEditStationeryModal();
            closeDeleteModal();
        }
    });

    // Auto-close modals on successful form submission
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    closeAddStationeryModal();
                    closeEditStationeryModal();
                    closeDeleteModal();
                }, 1000);
            });
        });
    });
</script>