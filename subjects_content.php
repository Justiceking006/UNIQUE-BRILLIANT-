<?php
// subjects_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle subject creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $level = $_POST['level'];
    $department = !empty($_POST['department']) ? $_POST['department'] : null;
    $credits = intval($_POST['credits']);
    
    try {
        $stmt = $db->prepare("INSERT INTO subjects (subject_code, subject_name, description, category, level, department, credits) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$subject_code, $subject_name, $description, $category, $level, $department, $credits])) {
            $success_message = "Subject created successfully!";
        } else {
            $error_message = "Failed to create subject. Please try again.";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'subject_code') !== false) {
            $error_message = "A subject with this code already exists.";
        } else {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle subject update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    $subject_id = intval($_POST['subject_id']);
    $subject_code = $_POST['subject_code'];
    $subject_name = $_POST['subject_name'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $level = $_POST['level'];
    $department = !empty($_POST['department']) ? $_POST['department'] : null;
    $credits = intval($_POST['credits']);
    
    try {
        $stmt = $db->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, description = ?, category = ?, level = ?, department = ?, credits = ? WHERE id = ?");
        
        if ($stmt->execute([$subject_code, $subject_name, $description, $category, $level, $department, $credits, $subject_id])) {
            $success_message = "Subject updated successfully!";
        } else {
            $error_message = "Failed to update subject. Please try again.";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'subject_code') !== false) {
            $error_message = "A subject with this code already exists.";
        } else {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle subject deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    $subject_id = intval($_POST['subject_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM subjects WHERE id = ?");
        
        if ($stmt->execute([$subject_id])) {
            $success_message = "Subject deleted successfully!";
        } else {
            $error_message = "Failed to delete subject. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle subject status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $subject_id = intval($_POST['subject_id']);
    $current_status = $_POST['current_status'];
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    $stmt = $db->prepare("UPDATE subjects SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $subject_id])) {
        $success_message = "Subject status updated!";
    } else {
        $error_message = "Failed to update subject status.";
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$department_filter = isset($_GET['department']) ? $_GET['department'] : '';

// Build query
$query = "SELECT * FROM subjects WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (subject_code LIKE ? OR subject_name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($level_filter) && $level_filter !== 'all') {
    $query .= " AND level = ?";
    $params[] = $level_filter;
}

if (!empty($category_filter) && $category_filter !== 'all') {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

if (!empty($department_filter) && $department_filter !== 'all') {
    $query .= " AND department = ?";
    $params[] = $department_filter;
}

$query .= " ORDER BY level, category, subject_name";

// Get subjects data
$stmt = $db->prepare($query);
$stmt->execute($params);
$subjects = $stmt->fetchAll();

// Get statistics
$total_subjects = count($subjects);
$active_subjects = $db->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'")->fetchColumn();
$core_subjects = $db->query("SELECT COUNT(*) FROM subjects WHERE category = 'core' AND status = 'active'")->fetchColumn();
$elective_subjects = $db->query("SELECT COUNT(*) FROM subjects WHERE category = 'elective' AND status = 'active'")->fetchColumn();
?>

<!-- Subjects/Courses Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Subjects & Courses</h1>
            <p class="text-[#8d6a5e]">Manage academic subjects and course catalog</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <button onclick="openCreateSubjectModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">add</span>
                Add New Subject
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
                <p class="text-sm text-[#8d6a5e]">Total Subjects</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_subjects; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">menu_book</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Active Subjects</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $active_subjects; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">check_circle</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Core Subjects</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $core_subjects; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">star</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Elective Subjects</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $elective_subjects; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">auto_awesome</span>
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
                Filter Subjects
            </h3>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <input type="hidden" name="page" value="subjects">
                
                <!-- Search -->
                <div class="md:col-span-2 lg:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Search</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Search by code, name, or description">
                </div>
                
                <!-- Level Filter -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Level</label>
                    <select name="level" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="all" <?php echo $level_filter === 'all' || empty($level_filter) ? 'selected' : ''; ?>>All Levels</option>
                        <option value="primary" <?php echo $level_filter === 'primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="jss" <?php echo $level_filter === 'jss' ? 'selected' : ''; ?>>JSS</option>
                        <option value="sss" <?php echo $level_filter === 'sss' ? 'selected' : ''; ?>>SSS</option>
                    </select>
                </div>
                
                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Category</label>
                    <select name="category" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="all" <?php echo $category_filter === 'all' || empty($category_filter) ? 'selected' : ''; ?>>All Categories</option>
                        <option value="core" <?php echo $category_filter === 'core' ? 'selected' : ''; ?>>Core</option>
                        <option value="elective" <?php echo $category_filter === 'elective' ? 'selected' : ''; ?>>Elective</option>
                        <option value="extra_curricular" <?php echo $category_filter === 'extra_curricular' ? 'selected' : ''; ?>>Extra Curricular</option>
                    </select>
                </div>
                
                <!-- Department Filter -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Department</label>
                    <select name="department" class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="all" <?php echo $department_filter === 'all' || empty($department_filter) ? 'selected' : ''; ?>>All Departments</option>
                        <option value="science" <?php echo $department_filter === 'science' ? 'selected' : ''; ?>>Science</option>
                        <option value="art" <?php echo $department_filter === 'art' ? 'selected' : ''; ?>>Art</option>
                        <option value="commercial" <?php echo $department_filter === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                    </select>
                </div>
                
                <!-- Filter Buttons -->
                <div class="md:col-span-2 lg:col-span-5 flex gap-3 justify-end pt-2">
                    <button type="submit" 
                            class="h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                        <span class="material-symbols-outlined mr-2">search</span>
                        Apply Filters
                    </button>
                    <a href="?page=subjects" 
                       class="h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                        <span class="material-symbols-outlined mr-2">refresh</span>
                        Reset
                    </a>
                </div>
            </form>
        </section>

        <!-- Subjects Table -->
        <section class="bg-white rounded-xl shadow-sm border border-[#e7deda] overflow-hidden">
            <?php if (empty($subjects)): ?>
                <div class="p-8 text-center text-[#8d6a5e]">
                    <span class="material-symbols-outlined text-4xl mb-2">menu_book</span>
                    <p class="text-lg font-medium">No subjects found</p>
                    <p class="text-sm mt-1"><?php echo !empty($search) || !empty($level_filter) || !empty($category_filter) || !empty($department_filter) ? 'Try adjusting your filters' : 'Get started by adding your first subject'; ?></p>
                    <?php if (empty($search) && empty($level_filter) && empty($category_filter) && empty($department_filter)): ?>
                        <button onclick="openCreateSubjectModal()" class="mt-4 inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                            <span class="material-symbols-outlined mr-2">add</span>
                            Add First Subject
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                            <tr>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Subject Code</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Subject Name</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] hidden lg:table-cell">Description</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Level</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Category</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] hidden md:table-cell">Credits</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Status</th>
                                <th class="p-4 text-sm font-semibold text-[#8d6a5e] text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e7deda]">
                            <?php foreach ($subjects as $subject): ?>
                            <tr class="hover:bg-[#f8f6f5] transition-colors">
                                <td class="p-4">
                                    <p class="text-sm font-medium text-[#181210]"><?php echo htmlspecialchars($subject['subject_code']); ?></p>
                                </td>
                                <td class="p-4">
                                    <p class="text-sm font-medium text-[#181210]"><?php echo htmlspecialchars($subject['subject_name']); ?></p>
                                    <?php if ($subject['department']): ?>
                                        <p class="text-xs text-[#8d6a5e]"><?php echo ucfirst($subject['department']); ?> Dept</p>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 hidden lg:table-cell">
                                    <p class="text-sm text-[#8d6a5e] truncate max-w-xs"><?php echo htmlspecialchars($subject['description'] ?: 'No description'); ?></p>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php echo $subject['level'] === 'primary' ? 'bg-orange-100 text-orange-800' : 
                                               ($subject['level'] === 'jss' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'); ?>">
                                        <?php echo strtoupper($subject['level']); ?>
                                    </span>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php echo $subject['category'] === 'core' ? 'bg-green-100 text-green-800' : 
                                               ($subject['category'] === 'elective' ? 'bg-yellow-100 text-yellow-800' : 'bg-pink-100 text-pink-800'); ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $subject['category'] === 'core' ? 'star' : 
                                                   ($subject['category'] === 'elective' ? 'auto_awesome' : 'sports_handball'); ?>
                                        </span>
                                        <?php echo ucfirst(str_replace('_', ' ', $subject['category'])); ?>
                                    </span>
                                </td>
                                <td class="p-4 hidden md:table-cell">
                                    <p class="text-sm text-[#181210] font-medium"><?php echo $subject['credits']; ?> credit<?php echo $subject['credits'] !== 1 ? 's' : ''; ?></p>
                                </td>
                                <td class="p-4">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?php echo $subject['status']; ?>">
                                        <button type="submit" name="toggle_status" 
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                                    <?php echo $subject['status'] === 'active' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-red-100 text-red-800 hover:bg-red-200'; ?> 
                                                    transition-colors cursor-pointer">
                                            <span class="material-symbols-outlined text-xs mr-1">
                                                <?php echo $subject['status'] === 'active' ? 'check_circle' : 'pause_circle'; ?>
                                            </span>
                                            <?php echo ucfirst($subject['status']); ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="p-4 flex justify-end gap-2">
                                    <button onclick="editSubject(<?php echo $subject['id']; ?>)" 
                                            class="inline-flex items-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                            title="Edit Subject">
                                        <span class="material-symbols-outlined text-base">edit</span>
                                    </button>
                                    <button onclick="confirmDelete(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['subject_name']); ?>')" 
                                            class="inline-flex items-center p-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                            title="Delete Subject">
                                        <span class="material-symbols-outlined text-base">delete</span>
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
                        Showing <?php echo $total_subjects; ?> subject<?php echo $total_subjects !== 1 ? 's' : ''; ?>
                        <?php if (!empty($search) || !empty($level_filter) || !empty($category_filter) || !empty($department_filter)): ?>
                            (filtered)
                        <?php endif; ?>
                    </p>
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
                    <span class="text-[#8d6a5e]">Total Subjects:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_subjects; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Active Subjects:</span>
                    <span class="font-medium text-[#181210]"><?php echo $active_subjects; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Core Subjects:</span>
                    <span class="font-medium text-[#181210]"><?php echo $core_subjects; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Elective Subjects:</span>
                    <span class="font-medium text-[#181210]"><?php echo $elective_subjects; ?></span>
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
                <button onclick="openCreateSubjectModal()" 
                       class="w-full flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors text-left">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">add</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Add New Subject</p>
                        <p class="text-xs text-[#8d6a5e]">Create a new subject</p>
                    </div>
                </button>
                
                <a href="?page=course-assignments" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">assignment</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Course Assignments</p>
                        <p class="text-xs text-[#8d6a5e]">Manage teacher assignments</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Level Distribution -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">pie_chart</span>
                Level Distribution
            </h3>
            
            <div class="space-y-3">
                <?php
                $level_counts = $db->query("SELECT level, COUNT(*) as count FROM subjects WHERE status = 'active' GROUP BY level")->fetchAll();
                foreach ($level_counts as $level): ?>
                <div class="flex justify-between items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-[#181210]"><?php echo strtoupper($level['level']); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-[#181210]"><?php echo $level['count']; ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Create Subject Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="createSubjectModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Add New Subject</h3>
                <button onclick="closeCreateSubjectModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="flex-1 overflow-y-auto p-4">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Subject Code *</label>
                        <input type="text" name="subject_code" required
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., MATH001, ENG101">
                        <p class="text-xs text-[#8d6a5e] mt-1">Unique identifier for the subject</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Subject Name *</label>
                        <input type="text" name="subject_name" required
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Mathematics, English Language">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Description</label>
                    <textarea name="description" rows="3"
                              class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                              placeholder="Brief description of the subject..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Category *</label>
                        <select name="category" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="core">Core</option>
                            <option value="elective">Elective</option>
                            <option value="extra_curricular">Extra Curricular</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Level *</label>
                        <select name="level" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="primary">Primary</option>
                            <option value="jss">JSS</option>
                            <option value="sss">SSS</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Department</label>
                        <select name="department"
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="">Not Applicable</option>
                            <option value="science">Science</option>
                            <option value="art">Art</option>
                            <option value="commercial">Commercial</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Credits</label>
                    <input type="number" name="credits" min="1" max="5" value="1"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    <p class="text-xs text-[#8d6a5e] mt-1">Number of credit units for this subject</p>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeCreateSubjectModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="create_subject"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Create Subject
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="editSubjectModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Edit Subject</h3>
                <button onclick="closeEditSubjectModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="flex-1 overflow-y-auto p-4">
            <input type="hidden" name="subject_id" id="editSubjectId">
            
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Subject Code *</label>
                        <input type="text" name="subject_code" id="editSubjectCode" required
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., MATH001, ENG101">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Subject Name *</label>
                        <input type="text" name="subject_name" id="editSubjectName" required
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Mathematics, English Language">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Description</label>
                    <textarea name="description" id="editDescription" rows="3"
                              class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                              placeholder="Brief description of the subject..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Category *</label>
                        <select name="category" id="editCategory" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="core">Core</option>
                            <option value="elective">Elective</option>
                            <option value="extra_curricular">Extra Curricular</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Level *</label>
                        <select name="level" id="editLevel" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="primary">Primary</option>
                            <option value="jss">JSS</option>
                            <option value="sss">SSS</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Department</label>
                        <select name="department" id="editDepartment"
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="">Not Applicable</option>
                            <option value="science">Science</option>
                            <option value="art">Art</option>
                            <option value="commercial">Commercial</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Credits</label>
                    <input type="number" name="credits" id="editCredits" min="1" max="5"
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeEditSubjectModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="update_subject"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Update Subject
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
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Subject</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this subject?</p>
            <div class="flex space-x-3 w-full">
                <button onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="subject_id" id="deleteSubjectId">
                    <button type="submit" name="delete_subject" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Subject data for editing
    const subjectData = <?php echo json_encode($subjects); ?>;

    function openCreateSubjectModal() {
        document.getElementById('createSubjectModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateSubjectModal() {
        document.getElementById('createSubjectModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openEditSubjectModal() {
        document.getElementById('editSubjectModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditSubjectModal() {
        document.getElementById('editSubjectModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function editSubject(subjectId) {
        const subjectInfo = subjectData.find(sub => sub.id == subjectId);
        
        if (subjectInfo) {
            // Populate form fields
            document.getElementById('editSubjectId').value = subjectInfo.id;
            document.getElementById('editSubjectCode').value = subjectInfo.subject_code;
            document.getElementById('editSubjectName').value = subjectInfo.subject_name;
            document.getElementById('editDescription').value = subjectInfo.description || '';
            document.getElementById('editCategory').value = subjectInfo.category;
            document.getElementById('editLevel').value = subjectInfo.level;
            document.getElementById('editDepartment').value = subjectInfo.department || '';
            document.getElementById('editCredits').value = subjectInfo.credits;
            
            openEditSubjectModal();
        }
    }

    function confirmDelete(subjectId, subjectName) {
        document.getElementById('deleteSubjectId').value = subjectId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete <strong>${subjectName}</strong>? This action cannot be undone.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('createSubjectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCreateSubjectModal();
        }
    });

    document.getElementById('editSubjectModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditSubjectModal();
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
            closeCreateSubjectModal();
            closeEditSubjectModal();
            closeDeleteModal();
        }
    });

    // Auto-close modals on successful form submission
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    closeCreateSubjectModal();
                    closeEditSubjectModal();
                    closeDeleteModal();
                }, 1000);
            });
        });
    });
</script>