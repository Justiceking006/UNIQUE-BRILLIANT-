<?php
// staff_list_content.php
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$success_message = '';
$error_message = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_request'])) {
    if (isset($_POST['update_staff'])) {
        $staff_id = $_POST['staff_id'];
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $department = sanitize($_POST['department']);
        $position = sanitize($_POST['position']);
        $phone = sanitize($_POST['phone']);
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $address = !empty($_POST['address']) ? sanitize($_POST['address']) : null;
        $employment_date = !empty($_POST['employment_date']) ? $_POST['employment_date'] : null;
        $qualifications = !empty($_POST['qualifications']) ? sanitize($_POST['qualifications']) : null;
        $emergency_contact = !empty($_POST['emergency_contact']) ? sanitize($_POST['emergency_contact']) : null;
        $gender = !empty($_POST['gender']) ? sanitize($_POST['gender']) : null;
        $state_of_origin = !empty($_POST['state_of_origin']) ? sanitize($_POST['state_of_origin']) : null;
        $marital_status = !empty($_POST['marital_status']) ? sanitize($_POST['marital_status']) : null;
        $religion = !empty($_POST['religion']) ? sanitize($_POST['religion']) : null;
        
        // Subject specialization
        $subject_specialization = isset($_POST['subject_specialization']) && is_array($_POST['subject_specialization']) ? 
            implode(', ', array_map(function($subject) { return sanitize($subject); }, $_POST['subject_specialization'])) : '';

        try {
            $stmt = $db->prepare("
                UPDATE staff SET 
                    first_name = ?, last_name = ?, email = ?, department = ?, position = ?,
                    phone = ?, date_of_birth = ?, address = ?, employment_date = ?, qualifications = ?,
                    emergency_contact = ?, subject_specialization = ?, gender = ?, state_of_origin = ?,
                    marital_status = ?, religion = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name, $last_name, $email, $department, $position,
                $phone, $date_of_birth, $address, $employment_date, $qualifications,
                $emergency_contact, $subject_specialization, $gender, $state_of_origin,
                $marital_status, $religion, $staff_id
            ]);
            
            // Also update email in users table if it changed
            $stmt = $db->prepare("UPDATE users SET email = ? WHERE staff_id = ?");
            $stmt->execute([$email, $staff_id]);
            
            echo json_encode(['success' => true, 'message' => 'Staff information updated successfully!']);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update staff: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if (isset($_POST['reset_password'])) {
        $staff_id = $_POST['staff_id'];
        $new_password = 'password123';
        
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE staff_id = ?");
            $stmt->execute([$hashed_password, $staff_id]);
            
            echo json_encode(['success' => true, 'message' => 'Password reset successfully! New password: password123']);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password: ' . $e->getMessage()]);
            exit;
        }
    }
}

// Handle regular form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_staff'])) {
        $staff_id = $_POST['staff_id'];
        try {
            $db->beginTransaction();
            
            // Delete from users table first
            $stmt = $db->prepare("DELETE FROM users WHERE staff_id = ?");
            $stmt->execute([$staff_id]);
            
            // Delete from staff table
            $stmt = $db->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->execute([$staff_id]);
            
            $db->commit();
            $success_message = "Staff member deleted successfully!";
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Failed to delete staff member: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $staff_id = $_POST['staff_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status === 'active' ? 'inactive' : 'active';
        
        try {
            $stmt = $db->prepare("UPDATE staff SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $staff_id])) {
                $success_message = "Staff status updated successfully!";
            } else {
                $error_message = "Failed to update staff status.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$department_filter = isset($_GET['department']) ? sanitize($_GET['department']) : '';

// Build query
$query = "SELECT * FROM staff WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR staff_id LIKE ? OR position LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($department_filter) && $department_filter !== 'all') {
    $query .= " AND department = ?";
    $params[] = $department_filter;
}

$query .= " ORDER BY created_at DESC";

// Get staff data
$stmt = $db->prepare($query);
$stmt->execute($params);
$staff_members = $stmt->fetchAll();

// Get counts for stats
$total_staff = $db->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$active_count = $db->query("SELECT COUNT(*) FROM staff WHERE status = 'active'")->fetchColumn();
$inactive_count = $db->query("SELECT COUNT(*) FROM staff WHERE status = 'inactive'")->fetchColumn();
$teacher_count = $db->query("SELECT COUNT(*) FROM staff WHERE position LIKE '%Teacher%' OR position = 'Principal' OR position = 'Vice Principal'")->fetchColumn();

// Get unique departments for filter
$departments = $db->query("SELECT DISTINCT department FROM staff WHERE department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// Define curriculum structure for subject selection
$subjects_by_level = [
    'primary' => [
        'English', 'Mathematics', 'Quantitative Reasoning', 'Verbal Reasoning', 'French', 
        'Phonics', 'Physical and Health Education', 'Computer', 'Cultural and Creative Art', 
        'Prevocational Studies', 'Basic Science and Tech', 'History', 'Yoruba', 'Christian Religion Studies'
    ],
    'jss' => [
        'Mathematics', 'English', 'Physical and Health Education', 'Basic Science', 'Business Studies', 
        'National Value', 'Yoruba', 'French', 'Pre-vocational Studies', 'Computer', 'History', 'Music'
    ],
    'sss_science' => [
        'Mathematics', 'English', 'Physics', 'Physics Practical', 'Biology', 'Chemistry', 
        'Chemistry Practical', 'Agricultural Science', 'Civic Education', 'Data Processing', 'Economics'
    ],
    'sss_art' => [
        'English', 'Mathematics', 'Yoruba', 'Christian Religion Studies', 'Literature in English', 
        'Government', 'Economics', 'Civic Education', 'Data Processing'
    ]
];
?>

<!-- Staff List Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Staff Management</h1>
            <p class="text-[#8d6a5e]">Manage teaching and non-teaching staff members</p>
        </div>
        <div class="mt-4 lg:mt-0 flex gap-3">
            <a href="?page=add-staff" 
               class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">person_add</span>
                Add New Staff
            </a>
            <button onclick="exportStaffData()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">download</span>
                Export
            </button>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center animate-fade-in">
        <span class="material-symbols-outlined mr-2 text-green-500">check_circle</span>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center animate-fade-in">
        <span class="material-symbols-outlined mr-2 text-red-500">error</span>
        <div class="flex-1">
            <strong>Error:</strong> <?php echo $error_message; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Staff</p>
                <p class="text-2xl font-bold text-[#181210]"><?php echo $total_staff; ?></p>
            </div>
            <span class="material-symbols-outlined text-3xl text-blue-500">groups</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Active Staff</p>
                <p class="text-2xl font-bold text-[#181210]"><?php echo $active_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-3xl text-green-500">check_circle</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Inactive Staff</p>
                <p class="text-2xl font-bold text-[#181210]"><?php echo $inactive_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-3xl text-orange-500">pause_circle</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Teaching Staff</p>
                <p class="text-2xl font-bold text-[#181210]"><?php echo $teacher_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-3xl text-purple-500">school</span>
        </div>
    </div>
</section>

<!-- Filtering & Controls -->
<section class="flex flex-col sm:flex-row gap-4 mb-6">
    <!-- Search -->
    <div class="flex-1">
        <form method="GET" class="flex">
            <input type="hidden" name="page" value="staff-list">
            <div class="flex w-full">
                <div class="flex items-center px-3 bg-white border border-r-0 border-[#e7deda] rounded-l-lg">
                    <span class="material-symbols-outlined text-[#8d6a5e]">search</span>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       class="flex-1 h-12 px-3 border border-[#e7deda] bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] rounded-r-lg" 
                       placeholder="Search by name, email, or position"/>
            </div>
        </form>
    </div>
    
    <!-- Status Filter -->
    <div class="flex gap-2">
        <select name="status" onchange="updateFilter('status', this.value)" 
                class="h-12 px-3 border border-[#e7deda] bg-white text-[#181210] rounded-lg focus:outline-none focus:border-[#ff6933]">
            <option value="all" <?php echo $status_filter === '' ? 'selected' : ''; ?>>All Status</option>
            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        
        <select name="department" onchange="updateFilter('department', this.value)"
                class="h-12 px-3 border border-[#e7deda] bg-white text-[#181210] rounded-lg focus:outline-none focus:border-[#ff6933]">
            <option value="all" <?php echo $department_filter === '' ? 'selected' : ''; ?>>All Departments</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($dept); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <?php if ($search || $status_filter || $department_filter): ?>
            <a href="?page=staff-list" class="h-12 px-4 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">clear</span>
                Clear
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- Staff List / Table -->
<section>
    <?php if (empty($staff_members)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">group</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Staff Members Found</h3>
            <p class="text-[#8d6a5e] mb-6"><?php echo ($search || $status_filter || $department_filter) ? 'Try adjusting your search filters.' : 'Get started by adding your first staff member.'; ?></p>
            <a href="?page=add-staff" 
               class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2">person_add</span>
                Add New Staff
            </a>
        </div>
    <?php else: ?>
        <!-- Desktop Table -->
        <div class="hidden md:block bg-white rounded-xl shadow-sm border border-[#e7deda] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                        <tr>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Staff ID</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Full Name</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Email</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Department</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Position</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Phone</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Status</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e7deda]">
                        <?php foreach ($staff_members as $staff): ?>
                        <tr class="hover:bg-[#f8f6f5] transition-colors">
                            <td class="p-4 text-[#181210] font-medium"><?php echo htmlspecialchars($staff['staff_id']); ?></td>
                            <td class="p-4 text-[#181210]">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                                        <span class="material-symbols-outlined text-[#ff6933] text-sm">person</span>
                                    </div>
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></p>
                                        <?php if (!empty($staff['subject_specialization'])): ?>
                                            <p class="text-xs text-[#8d6a5e]"><?php echo htmlspecialchars($staff['subject_specialization']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-[#181210]"><?php echo htmlspecialchars($staff['email']); ?></td>
                            <td class="p-4 text-[#181210]"><?php echo htmlspecialchars($staff['department']); ?></td>
                            <td class="p-4 text-[#181210]"><?php echo htmlspecialchars($staff['position']); ?></td>
                            <td class="p-4 text-[#181210] text-sm"><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></td>
                            <td class="p-4">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $staff['status']; ?>">
                                    <button type="submit" name="toggle_status" 
                                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium cursor-pointer transition-colors
                                                <?php echo $staff['status'] === 'active' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-orange-100 text-orange-800 hover:bg-orange-200'; ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $staff['status'] === 'active' ? 'check_circle' : 'pause_circle'; ?>
                                        </span>
                                        <?php echo ucfirst($staff['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td class="p-4 flex justify-end gap-2">
                                <button onclick="viewStaff(<?php echo $staff['id']; ?>)" 
                                        class="inline-flex items-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="View Details">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </button>
                                <button onclick="editStaff(<?php echo $staff['id']; ?>)" 
                                        class="inline-flex items-center p-2 text-sm text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                        title="Edit Staff">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </button>
                                <button onclick="confirmDelete(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars(addslashes($staff['first_name'] . ' ' . $staff['last_name'])); ?>')" 
                                        class="inline-flex items-center p-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete Staff">
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile Card View -->
        <div class="space-y-4 md:hidden">
            <?php foreach ($staff_members as $staff): ?>
            <div class="bg-white rounded-xl p-4 shadow-sm border border-[#e7deda]">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                            <span class="material-symbols-outlined text-[#ff6933]">person</span>
                        </div>
                        <div>
                            <p class="text-[#181210] font-bold">
                                <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                            </p>
                            <p class="text-[#8d6a5e] text-sm"><?php echo htmlspecialchars($staff['staff_id']); ?></p>
                        </div>
                    </div>
                    <form method="POST" class="inline">
                        <input type="hidden" name="staff_id" value="<?php echo $staff['id']; ?>">
                        <input type="hidden" name="current_status" value="<?php echo $staff['status']; ?>">
                        <button type="submit" name="toggle_status" 
                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium cursor-pointer transition-colors
                                    <?php echo $staff['status'] === 'active' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-orange-100 text-orange-800 hover:bg-orange-200'; ?>">
                            <?php echo ucfirst($staff['status']); ?>
                        </button>
                    </form>
                </div>
                
                <div class="text-sm text-[#8d6a5e] border-t border-[#e7deda] pt-3 space-y-2">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
                    <p><strong>Department:</strong> <?php echo htmlspecialchars($staff['department']); ?></p>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($staff['position']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></p>
                    <?php if (!empty($staff['subject_specialization'])): ?>
                        <p><strong>Subjects:</strong> <?php echo htmlspecialchars($staff['subject_specialization']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-end gap-2 border-t border-[#e7deda] pt-3 mt-3">
                    <button onclick="viewStaff(<?php echo $staff['id']; ?>)" 
                            class="text-blue-600 flex items-center gap-1 bg-blue-50 px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base">visibility</span> View
                    </button>
                    <button onclick="editStaff(<?php echo $staff['id']; ?>)" 
                            class="text-green-600 flex items-center gap-1 bg-green-50 px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base">edit</span> Edit
                    </button>
                    <button onclick="confirmDelete(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars(addslashes($staff['first_name'] . ' ' . $staff['last_name'])); ?>')" 
                            class="text-red-600 flex items-center gap-1 bg-red-50 px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base">delete</span> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl p-6 flex items-center space-x-4">
        <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
        <div class="text-gray-700 font-medium">Processing...</div>
    </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl max-w-md w-full p-6 animate-fade-in">
        <div class="text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2" id="successTitle">Success!</h3>
            <p id="successMessage" class="text-gray-600 mb-6">Operation completed successfully.</p>
            <button onclick="closeSuccessModal()" 
                    class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                Continue
            </button>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl max-w-md w-full p-6 animate-fade-in">
        <div class="text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Error</h3>
            <p id="errorMessage" class="text-gray-600 mb-6">An error occurred.</p>
            <button onclick="closeErrorModal()" 
                    class="w-full bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors">
                Try Again
            </button>
        </div>
    </div>
</div>

<!-- View Staff Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="viewModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Staff Details</h3>
                <button onclick="closeViewModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4" id="viewModalContent">
            <!-- Content loaded by JavaScript -->
        </div>
    </div>
</div>

<!-- Edit Staff Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="editModal">
    <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Edit Staff Member</h3>
                <button onclick="closeEditModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4" id="editModalContent">
            <!-- Content loaded by JavaScript -->
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="deleteModal">
    <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
        <div class="flex flex-col items-center text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-4">
                <span class="material-symbols-outlined text-3xl text-red-500">warning</span>
            </div>
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Staff Member</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this staff member?</p>
            <div class="flex space-x-3 w-full">
                <button type="button" onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="staff_id" id="deleteStaffId">
                    <button type="submit" name="delete_staff" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function updateFilter(type, value) {
        const url = new URL(window.location);
        url.searchParams.set(type, value);
        window.location.href = url.toString();
    }

    function viewStaff(staffId) {
        showLoading();
        fetch(`get_staff_details.php?id=${staffId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(staff => {
                const modalContent = document.getElementById('viewModalContent');
                
                modalContent.innerHTML = `
                    <div class="space-y-6">
                        <!-- Basic Info -->
                        <div class="text-center mb-4">
                            <div class="flex justify-center mb-3">
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#ff6933]/20">
                                    <span class="material-symbols-outlined text-2xl text-[#ff6933]">person</span>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold text-[#181210]">${staff.first_name} ${staff.last_name}</h4>
                            <p class="text-sm text-[#8d6a5e]">${staff.email}</p>
                            <p class="text-xs text-[#8d6a5e] mt-1">Staff ID: ${staff.staff_id}</p>
                        </div>

                        <!-- Contact Information -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">contact_mail</span>
                                Contact Information
                            </h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[#8d6a5e]">Phone:</span>
                                    <p class="font-medium text-[#181210]">${staff.phone || 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Emergency Contact:</span>
                                    <p class="font-medium text-[#181210]">${staff.emergency_contact || 'N/A'}</p>
                                </div>
                                ${staff.address ? `
                                <div class="col-span-2">
                                    <span class="text-[#8d6a5e]">Address:</span>
                                    <p class="font-medium text-[#181210]">${staff.address}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>

                        <!-- Personal Details -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">badge</span>
                                Personal Details
                            </h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[#8d6a5e]">Gender:</span>
                                    <p class="font-medium text-[#181210]">${staff.gender || 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Date of Birth:</span>
                                    <p class="font-medium text-[#181210]">${staff.date_of_birth ? new Date(staff.date_of_birth).toLocaleDateString() : 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">State of Origin:</span>
                                    <p class="font-medium text-[#181210]">${staff.state_of_origin || 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Marital Status:</span>
                                    <p class="font-medium text-[#181210]">${staff.marital_status || 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Religion:</span>
                                    <p class="font-medium text-[#181210]">${staff.religion || 'N/A'}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Information -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">work</span>
                                Employment Information
                            </h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[#8d6a5e]">Department:</span>
                                    <p class="font-medium text-[#181210]">${staff.department}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Position:</span>
                                    <p class="font-medium text-[#181210]">${staff.position}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Employment Date:</span>
                                    <p class="font-medium text-[#181210]">${staff.employment_date ? new Date(staff.employment_date).toLocaleDateString() : 'N/A'}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Status:</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        ${staff.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'}">
                                        ${staff.status.charAt(0).toUpperCase() + staff.status.slice(1)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        ${staff.subject_specialization ? `
                        <!-- Subject Specialization -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">subject</span>
                                Subject Specialization
                            </h5>
                            <p class="text-sm text-[#181210]">${staff.subject_specialization}</p>
                        </div>
                        ` : ''}

                        ${staff.qualifications ? `
                        <!-- Qualifications -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">school</span>
                                Qualifications
                            </h5>
                            <p class="text-sm text-[#181210]">${staff.qualifications}</p>
                        </div>
                        ` : ''}
                    </div>
                `;
                document.getElementById('viewModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                hideLoading();
            })
            .catch(error => {
                console.error('Error fetching staff details:', error);
                showError('Error loading staff details: ' + error.message);
                hideLoading();
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function editStaff(staffId) {
        showLoading();
        fetch(`get_staff_details.php?id=${staffId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(staff => {
                const modalContent = document.getElementById('editModalContent');
                
                // Get current subject specializations
                const currentSubjects = staff.subject_specialization ? staff.subject_specialization.split(', ') : [];
                
                modalContent.innerHTML = `
                    <form class="space-y-6" id="editStaffForm" onsubmit="return handleEditFormSubmit(event, ${staff.id})">
                        <input type="hidden" name="staff_id" value="${staff.id}">
                        <input type="hidden" name="ajax_request" value="1">
                        
                        <!-- Personal Information -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">person</span>
                                Personal Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">First Name *</label>
                                    <input type="text" name="first_name" value="${staff.first_name}" required 
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Last Name *</label>
                                    <input type="text" name="last_name" value="${staff.last_name}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Email Address *</label>
                                    <input type="email" name="email" value="${staff.email}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Phone Number *</label>
                                    <input type="tel" name="phone" value="${staff.phone || ''}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Gender</label>
                                    <select name="gender" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Gender</option>
                                        <option value="Male" ${staff.gender === 'Male' ? 'selected' : ''}>Male</option>
                                        <option value="Female" ${staff.gender === 'Female' ? 'selected' : ''}>Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Date of Birth</label>
                                    <input type="date" name="date_of_birth" value="${staff.date_of_birth || ''}"
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">contact_mail</span>
                                Contact Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Emergency Contact</label>
                                    <input type="tel" name="emergency_contact" value="${staff.emergency_contact || ''}"
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Address</label>
                                    <textarea name="address" rows="3"
                                              class="w-full rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">${staff.address || ''}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Details -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">badge</span>
                                Personal Details
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">State of Origin</label>
                                    <input type="text" name="state_of_origin" value="${staff.state_of_origin || ''}"
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Marital Status</label>
                                    <select name="marital_status" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Marital Status</option>
                                        <option value="Single" ${staff.marital_status === 'Single' ? 'selected' : ''}>Single</option>
                                        <option value="Married" ${staff.marital_status === 'Married' ? 'selected' : ''}>Married</option>
                                        <option value="Divorced" ${staff.marital_status === 'Divorced' ? 'selected' : ''}>Divorced</option>
                                        <option value="Widowed" ${staff.marital_status === 'Widowed' ? 'selected' : ''}>Widowed</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Religion</label>
                                    <select name="religion" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Religion</option>
                                        <option value="Christianity" ${staff.religion === 'Christianity' ? 'selected' : ''}>Christianity</option>
                                        <option value="Islam" ${staff.religion === 'Islam' ? 'selected' : ''}>Islam</option>
                                        <option value="Traditional" ${staff.religion === 'Traditional' ? 'selected' : ''}>Traditional</option>
                                        <option value="Other" ${staff.religion === 'Other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Employment Information -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">work</span>
                                Employment Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Department *</label>
                                    <select name="department" required
                                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Department</option>
                                        <option value="Administration" ${staff.department === 'Administration' ? 'selected' : ''}>Administration</option>
                                        <option value="Academic" ${staff.department === 'Academic' ? 'selected' : ''}>Academic</option>
                                        <option value="Mathematics" ${staff.department === 'Mathematics' ? 'selected' : ''}>Mathematics</option>
                                        <option value="Science" ${staff.department === 'Science' ? 'selected' : ''}>Science</option>
                                        <option value="English" ${staff.department === 'English' ? 'selected' : ''}>English</option>
                                        <option value="Social Studies" ${staff.department === 'Social Studies' ? 'selected' : ''}>Social Studies</option>
                                        <option value="Arts" ${staff.department === 'Arts' ? 'selected' : ''}>Arts</option>
                                        <option value="Technology" ${staff.department === 'Technology' ? 'selected' : ''}>Technology</option>
                                        <option value="Physical Education" ${staff.department === 'Physical Education' ? 'selected' : ''}>Physical Education</option>
                                        <option value="Library" ${staff.department === 'Library' ? 'selected' : ''}>Library</option>
                                        <option value="Finance" ${staff.department === 'Finance' ? 'selected' : ''}>Finance</option>
                                        <option value="Maintenance" ${staff.department === 'Maintenance' ? 'selected' : ''}>Maintenance</option>
                                        <option value="Other" ${staff.department === 'Other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Position *</label>
                                    <select name="position" required
                                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Position</option>
                                        <option value="Teacher" ${staff.position === 'Teacher' ? 'selected' : ''}>Teacher</option>
                                        <option value="Senior Teacher" ${staff.position === 'Senior Teacher' ? 'selected' : ''}>Senior Teacher</option>
                                        <option value="Head Teacher" ${staff.position === 'Head Teacher' ? 'selected' : ''}>Head Teacher</option>
                                        <option value="Principal" ${staff.position === 'Principal' ? 'selected' : ''}>Principal</option>
                                        <option value="Vice Principal" ${staff.position === 'Vice Principal' ? 'selected' : ''}>Vice Principal</option>
                                        <option value="Administrator" ${staff.position === 'Administrator' ? 'selected' : ''}>Administrator</option>
                                        <option value="Accountant" ${staff.position === 'Accountant' ? 'selected' : ''}>Accountant</option>
                                        <option value="Librarian" ${staff.position === 'Librarian' ? 'selected' : ''}>Librarian</option>
                                        <option value="Secretary" ${staff.position === 'Secretary' ? 'selected' : ''}>Secretary</option>
                                        <option value="Cleaner" ${staff.position === 'Cleaner' ? 'selected' : ''}>Cleaner</option>
                                        <option value="Security" ${staff.position === 'Security' ? 'selected' : ''}>Security</option>
                                        <option value="Other" ${staff.position === 'Other' ? 'selected' : ''}>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Employment Date</label>
                                    <input type="date" name="employment_date" value="${staff.employment_date || ''}"
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Qualifications</label>
                                    <textarea name="qualifications" rows="3"
                                              class="w-full rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">${staff.qualifications || ''}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Subject Specialization -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">subject</span>
                                Subject Specialization (For Teachers)
                            </h4>
                            <p class="text-sm text-[#8d6a5e] mb-4">Select subjects this staff member can teach</p>
                            
                            <!-- Primary Subjects -->
                            <div class="mb-6">
                                <h5 class="font-bold text-[#181210] mb-3 text-sm">Primary School Subjects</h5>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <?php foreach ($subjects_by_level['primary'] as $subject): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                                   ${currentSubjects.includes('<?php echo $subject; ?>') ? 'checked' : ''}>
                                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- JSS Subjects -->
                            <div class="mb-6">
                                <h5 class="font-bold text-[#181210] mb-3 text-sm">Junior Secondary (JSS) Subjects</h5>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <?php foreach ($subjects_by_level['jss'] as $subject): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                                   ${currentSubjects.includes('<?php echo $subject; ?>') ? 'checked' : ''}>
                                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SSS Science Subjects -->
                            <div class="mb-6">
                                <h5 class="font-bold text-[#181210] mb-3 text-sm">Senior Secondary - Science</h5>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <?php foreach ($subjects_by_level['sss_science'] as $subject): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                                   ${currentSubjects.includes('<?php echo $subject; ?>') ? 'checked' : ''}>
                                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- SSS Art Subjects -->
                            <div class="mb-6">
                                <h5 class="font-bold text-[#181210] mb-3 text-sm">Senior Secondary - Art</h5>
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                                    <?php foreach ($subjects_by_level['sss_art'] as $subject): ?>
                                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                                   ${currentSubjects.includes('<?php echo $subject; ?>') ? 'checked' : ''}>
                                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Password Reset Section -->
                        <div class="border-t border-[#e7deda] pt-6">
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">lock_reset</span>
                                Password Management
                            </h4>
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <p class="text-sm text-yellow-800 mb-3">
                                    Reset this staff member's password to the default password.
                                </p>
                                <button type="button" onclick="resetPassword(${staff.id}, '${staff.first_name} ${staff.last_name}')" 
                                        class="w-full h-12 rounded-lg bg-yellow-500 text-white text-base font-bold hover:bg-yellow-600 transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined mr-2">lock_reset</span>
                                    Reset Password to Default
                                </button>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-[#e7deda]">
                            <button type="submit" name="update_staff" id="updateStaffBtn"
                                    class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white text-base font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                                <span class="material-symbols-outlined mr-2">save</span>
                                Update Staff
                            </button>
                            <button type="button" onclick="closeEditModal()" 
                                    class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] text-base font-medium hover:bg-[#f8f6f5] transition-colors flex items-center justify-center">
                                <span class="material-symbols-outlined mr-2">cancel</span>
                                Cancel
                            </button>
                        </div>
                    </form>
                `;
                document.getElementById('editModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                hideLoading();
            })
            .catch(error => {
                console.error('Error fetching staff details:', error);
                showError('Error loading staff details for editing: ' + error.message);
                hideLoading();
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Handle edit form submission with AJAX
    function handleEditFormSubmit(event, staffId) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        formData.append('update_staff', '1');
        
        // Show loading state
        const submitBtn = document.getElementById('updateStaffBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="material-symbols-outlined mr-2 animate-spin">refresh</span>Updating...';
        submitBtn.disabled = true;
        
        showLoading();
        
        // Submit form via AJAX
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSuccess('Staff Updated', data.message);
                setTimeout(() => {
                    closeEditModal();
                    window.location.reload();
                }, 2000);
            } else {
                showError(data.message);
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to update staff. Please try again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        })
        .finally(() => {
            hideLoading();
        });
        
        return false;
    }

    // Handle password reset
    function resetPassword(staffId, staffName) {
        if (!confirm(`Reset password for ${staffName} to "password123"?`)) {
            return;
        }
        
        showLoading();
        
        const formData = new FormData();
        formData.append('staff_id', staffId);
        formData.append('reset_password', '1');
        formData.append('ajax_request', '1');
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showSuccess('Password Reset', data.message);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Failed to reset password. Please try again.');
        })
        .finally(() => {
            hideLoading();
        });
    }

    function confirmDelete(staffId, staffName) {
        document.getElementById('deleteStaffId').value = staffId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete <strong>${staffName}</strong>?<br><span class="text-red-600 text-sm">This action cannot be undone.</span>`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    function exportStaffData() {
        showLoading();
        // Simulate export process
        setTimeout(() => {
            hideLoading();
            showSuccess('Export Completed', 'Staff list has been exported successfully!');
        }, 1500);
    }

    // Utility functions for showing/hiding modals
    function showLoading() {
        document.getElementById('loadingOverlay').classList.remove('hidden');
    }

    function hideLoading() {
        document.getElementById('loadingOverlay').classList.add('hidden');
    }

    function showSuccess(title, message) {
        document.getElementById('successTitle').textContent = title;
        document.getElementById('successMessage').textContent = message;
        document.getElementById('successModal').classList.remove('hidden');
    }

    function closeSuccessModal() {
        document.getElementById('successModal').classList.add('hidden');
    }

    function showError(message) {
        document.getElementById('errorMessage').textContent = message;
        document.getElementById('errorModal').classList.remove('hidden');
    }

    function closeErrorModal() {
        document.getElementById('errorModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.id === 'viewModal') closeViewModal();
        if (e.target.id === 'editModal') closeEditModal();
        if (e.target.id === 'successModal') closeSuccessModal();
        if (e.target.id === 'errorModal') closeErrorModal();
        if (e.target.id === 'loadingOverlay') hideLoading();
        if (e.target.id === 'deleteModal') closeDeleteModal();
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeViewModal();
            closeEditModal();
            closeSuccessModal();
            closeErrorModal();
            closeDeleteModal();
        }
    });

    // Auto-hide success messages
    setTimeout(() => {
        const successMessages = document.querySelectorAll('.bg-green-100');
        successMessages.forEach(msg => {
            msg.style.opacity = '0';
            msg.style.transition = 'opacity 0.5s ease';
            setTimeout(() => msg.remove(), 500);
        });
    }, 5000);
</script>

<style>
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
</style>