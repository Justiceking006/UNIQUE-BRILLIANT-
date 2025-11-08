<?php
// inventory_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle book creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) {
    $title = $_POST['title'];
    $author = $_POST['author'];
    $price = floatval($_POST['price']);
    $category = $_POST['category'];
    $term = $_POST['term'];
    $class_level = $_POST['class_level'];
    
    try {
        $stmt = $db->prepare("INSERT INTO books (title, author, price, category, term, class_level) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$title, $author, $price, $category, $term, $class_level])) {
            $success_message = "Book added successfully!";
        } else {
            $error_message = "Failed to add book. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle book update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_book'])) {
    $book_id = intval($_POST['book_id']);
    $title = $_POST['title'];
    $author = $_POST['author'];
    $price = floatval($_POST['price']);
    $category = $_POST['category'];
    $term = $_POST['term'];
    $class_level = $_POST['class_level'];
    
    try {
        $stmt = $db->prepare("UPDATE books SET title = ?, author = ?, price = ?, category = ?, term = ?, class_level = ? WHERE id = ?");
        
        if ($stmt->execute([$title, $author, $price, $category, $term, $class_level, $book_id])) {
            $success_message = "Book updated successfully!";
        } else {
            $error_message = "Failed to update book. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle book deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) {
    $book_id = intval($_POST['book_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM books WHERE id = ?");
        
        if ($stmt->execute([$book_id])) {
            $success_message = "Book deleted successfully!";
        } else {
            $error_message = "Failed to delete book. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_availability'])) {
    $book_id = intval($_POST['book_id']);
    $current_status = $_POST['current_status'];
    $new_status = $current_status ? 0 : 1;
    
    $stmt = $db->prepare("UPDATE books SET is_available = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $book_id])) {
        $success_message = "Book availability updated!";
    } else {
        $error_message = "Failed to update book availability.";
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$term_filter = isset($_GET['term']) ? $_GET['term'] : '';
$class_filter = isset($_GET['class_level']) ? $_GET['class_level'] : '';

// Build query
$query = "SELECT * FROM books WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter) && $category_filter !== 'all') {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

if (!empty($term_filter) && $term_filter !== 'all') {
    $query .= " AND term = ?";
    $params[] = $term_filter;
}

if (!empty($class_filter) && $class_filter !== 'all') {
    $query .= " AND class_level = ?";
    $params[] = $class_filter;
}

$query .= " ORDER BY class_level, term, title";

// Get books data
$stmt = $db->prepare($query);
$stmt->execute($params);
$books = $stmt->fetchAll();

// Get unique categories for filter
$categories = $db->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get unique terms
$terms = $db->query("SELECT DISTINCT term FROM books WHERE term IS NOT NULL ORDER BY term")->fetchAll(PDO::FETCH_COLUMN);

// Get unique class levels
$class_levels = $db->query("SELECT DISTINCT class_level FROM books WHERE class_level IS NOT NULL ORDER BY class_level")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$total_books = count($books);
$available_books = $db->query("SELECT COUNT(*) FROM books WHERE is_available = 1")->fetchColumn();
$total_categories = count($categories);
$average_price = $db->query("SELECT AVG(price) FROM books")->fetchColumn();
?>

<!-- Book Inventory Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Book Inventory</h1>
            <p class="text-[#8d6a5e]">Manage textbook stock and availability</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <button onclick="openAddBookModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">add</span>
                Add New Book
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
                <p class="text-sm text-[#8d6a5e]">Total Books</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_books; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">menu_book</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Available</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $available_books; ?></p>
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
                Filter Books
            </h3>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <input type="hidden" name="page" value="inventory">
                
                <!-- Search -->
                <div class="md:col-span-2 lg:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Search by title or author">
                </div>
                
                <!-- Category Filter -->
                <div>
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
                
                <!-- Term Filter -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Term</label>
                    <select name="term" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="all" <?php echo $term_filter === 'all' || empty($term_filter) ? 'selected' : ''; ?>>All Terms</option>
                        <option value="all_terms" <?php echo $term_filter === 'all_terms' ? 'selected' : ''; ?>>All Terms</option>
                        <option value="first_term" <?php echo $term_filter === 'first_term' ? 'selected' : ''; ?>>First Term</option>
                        <option value="second_term" <?php echo $term_filter === 'second_term' ? 'selected' : ''; ?>>Second Term</option>
                        <option value="third_term" <?php echo $term_filter === 'third_term' ? 'selected' : ''; ?>>Third Term</option>
                    </select>
                </div>
                
                <!-- Class Level Filter -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Level</label>
                    <select name="class_level" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="all" <?php echo $class_filter === 'all' || empty($class_filter) ? 'selected' : ''; ?>>All Classes</option>
                        <option value="all_classes" <?php echo $class_filter === 'all_classes' ? 'selected' : ''; ?>>All Classes</option>
                        <option value="primary" <?php echo $class_filter === 'primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="jss1" <?php echo $class_filter === 'jss1' ? 'selected' : ''; ?>>JSS 1</option>
                        <option value="jss2" <?php echo $class_filter === 'jss2' ? 'selected' : ''; ?>>JSS 2</option>
                        <option value="jss3" <?php echo $class_filter === 'jss3' ? 'selected' : ''; ?>>JSS 3</option>
                        <option value="ss1" <?php echo $class_filter === 'ss1' ? 'selected' : ''; ?>>SS 1</option>
                        <option value="ss2" <?php echo $class_filter === 'ss2' ? 'selected' : ''; ?>>SS 2</option>
                        <option value="ss3" <?php echo $class_filter === 'ss3' ? 'selected' : ''; ?>>SS 3</option>
                    </select>
                </div>
                
                <!-- Filter Buttons -->
                <div class="md:col-span-2 lg:col-span-5 flex gap-3 justify-end pt-2">
                    <button type="submit" 
                            class="h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                        <span class="material-symbols-outlined mr-2">search</span>
                        Apply Filters
                    </button>
                    <a href="?page=inventory" 
                       class="h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                        <span class="material-symbols-outlined mr-2">refresh</span>
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Books Grid -->
        <section>
            <?php if (empty($books)): ?>
                <!-- Empty State -->
                <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
                    <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">menu_book</span>
                    <h3 class="text-xl font-bold text-[#181210] mb-2">No Books Found</h3>
                    <p class="text-[#8d6a5e] mb-6"><?php echo !empty($search) || !empty($category_filter) || !empty($term_filter) || !empty($class_filter) ? 'Try adjusting your filters' : 'Get started by adding your first book'; ?></p>
                    <?php if (empty($search) && empty($category_filter) && empty($term_filter) && empty($class_filter)): ?>
                        <button onclick="openAddBookModal()"
                                class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                            <span class="material-symbols-outlined mr-2">add</span>
                            Add First Book
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Books Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($books as $book): ?>
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] hover:shadow-md transition-shadow">
                        <!-- Book Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-[#181210] mb-1"><?php echo htmlspecialchars($book['title']); ?></h3>
                                <p class="text-sm text-[#8d6a5e]">by <?php echo htmlspecialchars($book['author'] ?: 'Unknown Author'); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $book['is_available'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                <span class="material-symbols-outlined text-xs mr-1">
                                    <?php echo $book['is_available'] ? 'check_circle' : 'block'; ?>
                                </span>
                                <?php echo $book['is_available'] ? 'Available' : 'Unavailable'; ?>
                            </span>
                        </div>

                        <!-- Book Details -->
                        <div class="space-y-3 text-sm text-[#8d6a5e] mb-4">
                            <div class="flex justify-between">
                                <span>Category:</span>
                                <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($book['category'] ?: 'Uncategorized'); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Price:</span>
                                <span class="font-medium text-[#181210]">₦<?php echo number_format($book['price'], 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Term:</span>
                                <span class="font-medium text-[#181210]">
                                    <?php 
                                    $term_display = [
                                        'all_terms' => 'All Terms',
                                        'first_term' => 'First Term',
                                        'second_term' => 'Second Term', 
                                        'third_term' => 'Third Term'
                                    ];
                                    echo $term_display[$book['term']] ?? ucfirst(str_replace('_', ' ', $book['term']));
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Class:</span>
                                <span class="font-medium text-[#181210]">
                                    <?php 
                                    $class_display = [
                                        'all_classes' => 'All Classes',
                                        'primary' => 'Primary',
                                        'jss1' => 'JSS 1',
                                        'jss2' => 'JSS 2',
                                        'jss3' => 'JSS 3',
                                        'ss1' => 'SS 1',
                                        'ss2' => 'SS 2',
                                        'ss3' => 'SS 3'
                                    ];
                                    echo $class_display[$book['class_level']] ?? ucfirst($book['class_level']);
                                    ?>
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Added:</span>
                                <span class="font-medium text-[#181210]"><?php echo date('M j, Y', strtotime($book['created_at'])); ?></span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-between gap-2 border-t border-[#e7deda] pt-4">
                            <form method="POST" class="flex-1">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $book['is_available']; ?>">
                                <button type="submit" name="toggle_availability" 
                                        class="w-full flex items-center justify-center p-2 text-sm 
                                            <?php echo $book['is_available'] ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> 
                                            rounded-lg transition-colors">
                                    <span class="material-symbols-outlined text-base mr-1">
                                        <?php echo $book['is_available'] ? 'block' : 'check_circle'; ?>
                                    </span>
                                    <?php echo $book['is_available'] ? 'Mark Unavailable' : 'Mark Available'; ?>
                                </button>
                            </form>
                            
                            <button onclick="editBook(<?php echo $book['id']; ?>)" 
                                    class="flex-1 flex items-center justify-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                <span class="material-symbols-outlined text-base mr-1">edit</span>
                                Edit
                            </button>
                            
                            <button onclick="confirmDelete(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>')" 
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
                    <span class="text-[#8d6a5e]">Total Books:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_books; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Available Books:</span>
                    <span class="font-medium text-[#181210]"><?php echo $available_books; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Categories:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_categories; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Avg Price:</span>
                    <span class="font-medium text-[#181210]">₦<?php echo number_format($average_price, 2); ?></span>
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
                <button onclick="openAddBookModal()" 
                       class="w-full flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors text-left">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">add</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Add New Book</p>
                        <p class="text-xs text-[#8d6a5e]">Add a new book to inventory</p>
                    </div>
                </button>
                
                <a href="?page=stationery" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">inventory_2</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Stationery Items</p>
                        <p class="text-xs text-[#8d6a5e]">Manage other inventory items</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Term Distribution -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">pie_chart</span>
                Term Distribution
            </h3>
            
            <div class="space-y-3">
                <?php
                $term_counts = $db->query("SELECT term, COUNT(*) as count FROM books GROUP BY term")->fetchAll();
                foreach ($term_counts as $term): ?>
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-[#181210]">
                            <?php 
                            $term_names = [
                                'all_terms' => 'All Terms',
                                'first_term' => 'First Term',
                                'second_term' => 'Second Term',
                                'third_term' => 'Third Term'
                            ];
                            echo $term_names[$term['term']] ?? ucfirst(str_replace('_', ' ', $term['term']));
                            ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-[#181210]"><?php echo $term['count']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Book Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="addBookModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Add New Book</h3>
                <button onclick="closeAddBookModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Book Title *</label>
                    <input type="text" name="title" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter book title">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Author</label>
                    <input type="text" name="author"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter author name">
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
                               placeholder="e.g., Mathematics, Science">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Term *</label>
                        <select name="term" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="all_terms">All Terms</option>
                            <option value="first_term">First Term</option>
                            <option value="second_term">Second Term</option>
                            <option value="third_term">Third Term</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Level *</label>
                        <select name="class_level" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="all_classes">All Classes</option>
                            <option value="primary">Primary</option>
                            <option value="jss1">JSS 1</option>
                            <option value="jss2">JSS 2</option>
                            <option value="jss3">JSS 3</option>
                            <option value="ss1">SS 1</option>
                            <option value="ss2">SS 2</option>
                            <option value="ss3">SS 3</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeAddBookModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="add_book"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Add Book
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Book Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="editBookModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Edit Book</h3>
                <button onclick="closeEditBookModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <input type="hidden" name="book_id" id="editBookId">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Book Title *</label>
                    <input type="text" name="title" id="editTitle" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter book title">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Author</label>
                    <input type="text" name="author" id="editAuthor"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter author name">
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
                               placeholder="e.g., Mathematics, Science">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Term *</label>
                        <select name="term" id="editTerm" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="all_terms">All Terms</option>
                            <option value="first_term">First Term</option>
                            <option value="second_term">Second Term</option>
                            <option value="third_term">Third Term</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Level *</label>
                        <select name="class_level" id="editClassLevel" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="all_classes">All Classes</option>
                            <option value="primary">Primary</option>
                            <option value="jss1">JSS 1</option>
                            <option value="jss2">JSS 2</option>
                            <option value="jss3">JSS 3</option>
                            <option value="ss1">SS 1</option>
                            <option value="ss2">SS 2</option>
                            <option value="ss3">SS 3</option>
                        </select>
                    </div>
                </div>

                <!-- Current Status Display -->
                <div class="bg-[#f8f6f5] rounded-lg p-3">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Current Status</label>
                    <span id="editStatusDisplay" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"></span>
                    <p class="text-xs text-[#8d6a5e] mt-1">Use the toggle button on the book card to change availability</p>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeEditBookModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="update_book"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Update Book
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
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Book</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this book?</p>
            <div class="flex space-x-3 w-full">
                <button onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="book_id" id="deleteBookId">
                    <button type="submit" name="delete_book" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Book data for editing
    const bookData = <?php echo json_encode($books); ?>;

    function openAddBookModal() {
        document.getElementById('addBookModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAddBookModal() {
        document.getElementById('addBookModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openEditBookModal() {
        document.getElementById('editBookModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditBookModal() {
        document.getElementById('editBookModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function editBook(bookId) {
        const bookInfo = bookData.find(book => book.id == bookId);
        
        if (bookInfo) {
            // Populate form fields
            document.getElementById('editBookId').value = bookInfo.id;
            document.getElementById('editTitle').value = bookInfo.title;
            document.getElementById('editAuthor').value = bookInfo.author || '';
            document.getElementById('editPrice').value = bookInfo.price;
            document.getElementById('editCategory').value = bookInfo.category || '';
            document.getElementById('editTerm').value = bookInfo.term || 'all_terms';
            document.getElementById('editClassLevel').value = bookInfo.class_level || 'all_classes';
            
            // Update status display
            const statusDisplay = document.getElementById('editStatusDisplay');
            statusDisplay.textContent = bookInfo.is_available ? 'Available' : 'Unavailable';
            statusDisplay.className = `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                bookInfo.is_available ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`;
            
            openEditBookModal();
        }
    }

    function confirmDelete(bookId, bookTitle) {
        document.getElementById('deleteBookId').value = bookId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete <strong>${bookTitle}</strong>? This action cannot be undone.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('addBookModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAddBookModal();
        }
    });

    document.getElementById('editBookModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditBookModal();
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
            closeAddBookModal();
            closeEditBookModal();
            closeDeleteModal();
        }
    });

    // Auto-close modals on successful form submission
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    closeAddBookModal();
                    closeEditBookModal();
                    closeDeleteModal();
                }, 1000);
            });
        });
    });
</script>