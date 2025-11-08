<?php
// student_details_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_student'])) {
        $student_id = $_POST['student_id'];
        try {
            $db->beginTransaction();
            
            // Delete from users table first (foreign key constraint)
            $stmt = $db->prepare("DELETE FROM users WHERE student_id = ?");
            $stmt->execute([$student_id]);
            
            // Delete from students table
            $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            
            $db->commit();
            $success_message = "Student deleted successfully!";
            
            // Refresh the page to show updated list
            echo "<script>window.location.href = '?page=student-details&success=deleted';</script>";
            exit;
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error_message = "Failed to delete student: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['toggle_status'])) {
        $student_id = $_POST['student_id'];
        $current_status = $_POST['current_status'];
        $new_status = $current_status === 'approved' ? 'pending' : 'approved';
        
        try {
            $stmt = $db->prepare("UPDATE students SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $student_id])) {
                $success_message = "Student status updated successfully!";
                
                // Refresh the page to show updated status
                echo "<script>window.location.href = '?page=student-details&success=status_updated';</script>";
                exit;
            } else {
                $error_message = "Failed to update student status.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
    
    // Handle student update
    if (isset($_POST['update_student'])) {
        $student_id = $_POST['student_id'];
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $section = sanitize($_POST['section']);
        $level = isset($_POST['level']) ? sanitize($_POST['level']) : null;
        $class = sanitize($_POST['class']);
        $department = isset($_POST['department']) ? sanitize($_POST['department']) : null;
        $guardian_name = sanitize($_POST['guardian_name']);
        $guardian_phone = sanitize($_POST['guardian_phone']);
        
        try {
            $stmt = $db->prepare("
                UPDATE students 
                SET first_name = ?, last_name = ?, email = ?, section = ?, level = ?, 
                    class = ?, department = ?, guardian_name = ?, guardian_phone = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name, $last_name, $email, $section, $level, 
                $class, $department, $guardian_name, $guardian_phone, $student_id
            ]);
            
            // Also update email in users table if it changed
            $stmt = $db->prepare("UPDATE users SET email = ? WHERE student_id = ?");
            $stmt->execute([$email, $student_id]);
            
            $success_message = "Student information updated successfully!";
            echo "<script>window.location.href = '?page=student-details&success=updated';</script>";
            exit;
            
        } catch (PDOException $e) {
            $error_message = "Failed to update student: " . $e->getMessage();
        }
    }
    
    // Handle password reset
    if (isset($_POST['reset_password'])) {
        $student_id = $_POST['student_id'];
        $new_password = 'password123'; // Default password
        
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE student_id = ?");
            $stmt->execute([$hashed_password, $student_id]);
            
            $success_message = "Password reset successfully! New password: <strong>password123</strong>";
            echo "<script>window.location.href = '?page=student-details&success=password_reset';</script>";
            exit;
            
        } catch (PDOException $e) {
            $error_message = "Failed to reset password: " . $e->getMessage();
        }
    }
}

// Check for success messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'deleted') {
        $success_message = "Student deleted successfully!";
    } elseif ($_GET['success'] === 'status_updated') {
        $success_message = "Student status updated successfully!";
    } elseif ($_GET['success'] === 'updated') {
        $success_message = "Student information updated successfully!";
    } elseif ($_GET['success'] === 'password_reset') {
        $success_message = "Password reset successfully! New password: <strong>password123</strong>";
    }
}

// Get search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$class_filter = isset($_GET['class']) ? sanitize($_GET['class']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$query = "SELECT * FROM students WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR student_code LIKE ? OR guardian_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($class_filter) && $class_filter !== 'All') {
    $query .= " AND class = ?";
    $params[] = $class_filter;
}

if (!empty($status_filter) && $status_filter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC, class, first_name, last_name";

// Get student data
$stmt = $db->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get class list for filter
$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);

// Get counts
$total_students = count($students);
$approved_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'approved'")->fetchColumn();
$pending_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'pending'")->fetchColumn();
$primary_count = $db->query("SELECT COUNT(*) FROM students WHERE section = 'primary' AND status = 'approved'")->fetchColumn();
$secondary_count = $db->query("SELECT COUNT(*) FROM students WHERE section = 'secondary' AND status = 'approved'")->fetchColumn();
?>

<!-- Student Details Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Student Details</h1>
            <p class="text-[#8d6a5e]">Complete student database and information management</p>
        </div>
        <div class="mt-4 lg:mt-0 flex gap-3">
            <a href="?page=create-admission" 
               class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">person_add</span>
                Add Student
            </a>
            <button onclick="exportStudentData()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">download</span>
                Export
            </button>
        </div>
    </div>
</div>

<!-- Success/Error Messages -->
<?php if (!empty($success_message)): ?>
    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center animate-fade-in">
        <span class="material-symbols-outlined mr-2 text-green-500">check_circle</span>
        <?php echo $success_message; ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center animate-fade-in">
        <span class="material-symbols-outlined mr-2 text-red-500">error</span>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Stats Overview -->
<section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <!-- ... stats section remains the same ... -->
</section>

<!-- Filtering & Controls -->
<section class="flex flex-col sm:flex-row gap-4 mb-6">
    <!-- ... filtering section remains the same ... -->
</section>

<!-- Student List / Table -->
<section>
    <?php if (empty($students)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">school</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Students Found</h3>
            <p class="text-[#8d6a5e] mb-6">Get started by adding your first student.</p>
            <a href="?page=create-admission" 
               class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2">person_add</span>
                Add New Student
            </a>
        </div>
    <?php else: ?>
        <!-- Desktop Table -->
        <div class="hidden md:block bg-white rounded-xl shadow-sm border border-[#e7deda] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-[#f8f6f5] border-b border-[#e7deda]">
                        <tr>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Student Code</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Student Name</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Section/Class</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Guardian</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Status</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e]">Date Joined</th>
                            <th class="p-4 text-sm font-semibold text-[#8d6a5e] text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e7deda]">
                        <?php foreach ($students as $student): ?>
                        <tr class="hover:bg-[#f8f6f5] transition-colors">
                            <td class="p-4 text-[#181210] font-medium"><?php echo htmlspecialchars($student['student_code']); ?></td>
                            <td class="p-4 text-[#181210]">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                                        <span class="material-symbols-outlined text-[#ff6933] text-sm">person</span>
                                    </div>
                                    <div>
                                        <p class="font-medium"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                        <p class="text-sm text-[#8d6a5e]"><?php echo htmlspecialchars($student['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4 text-[#181210]">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($student['class']); ?></p>
                                    <p class="text-sm text-[#8d6a5e] capitalize">
                                        <?php echo htmlspecialchars($student['section']); ?>
                                        <?php if ($student['level']): ?>
                                            • <?php echo strtoupper($student['level']); ?>
                                        <?php endif; ?>
                                        <?php if ($student['department']): ?>
                                            • <?php echo ucfirst($student['department']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </td>
                            <td class="p-4 text-[#181210]">
                                <p class="font-medium"><?php echo htmlspecialchars($student['guardian_name']); ?></p>
                                <p class="text-sm text-[#8d6a5e]"><?php echo htmlspecialchars($student['guardian_phone']); ?></p>
                            </td>
                            <td class="p-4">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $student['status']; ?>">
                                    <button type="submit" name="toggle_status" 
                                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium cursor-pointer transition-colors
                                                <?php echo $student['status'] === 'approved' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 
                                                       ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-red-100 text-red-800 hover:bg-red-200'); ?>">
                                        <span class="material-symbols-outlined text-xs mr-1">
                                            <?php echo $student['status'] === 'approved' ? 'check_circle' : 
                                                   ($student['status'] === 'pending' ? 'pending' : 'cancel'); ?>
                                        </span>
                                        <?php echo ucfirst($student['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td class="p-4 text-[#181210] text-sm">
                                <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                            </td>
                            <td class="p-4 flex justify-end gap-2">
                                <button onclick="viewStudent(<?php echo $student['id']; ?>)" 
                                        class="inline-flex items-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="View Details">
                                    <span class="material-symbols-outlined text-base">visibility</span>
                                </button>
                                <button onclick="editStudent(<?php echo $student['id']; ?>)" 
                                        class="inline-flex items-center p-2 text-sm text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                        title="Edit Student">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </button>
                                <button onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')" 
                                        class="inline-flex items-center p-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete Student">
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
            <?php foreach ($students as $student): ?>
            <div class="bg-white rounded-xl p-4 shadow-sm border border-[#e7deda]">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 h-10 w-10 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                            <span class="material-symbols-outlined text-[#ff6933]">person</span>
                        </div>
                        <div>
                            <p class="text-[#181210] font-bold">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </p>
                            <p class="text-[#8d6a5e] text-sm"><?php echo htmlspecialchars($student['student_code']); ?></p>
                        </div>
                    </div>
                    <form method="POST" class="inline">
                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                        <input type="hidden" name="current_status" value="<?php echo $student['status']; ?>">
                        <button type="submit" name="toggle_status" 
                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium cursor-pointer transition-colors
                                    <?php echo $student['status'] === 'approved' ? 'bg-green-100 text-green-800 hover:bg-green-200' : 
                                           ($student['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-red-100 text-red-800 hover:bg-red-200'); ?>">
                            <?php echo ucfirst($student['status']); ?>
                        </button>
                    </form>
                </div>
                
                <div class="text-sm text-[#8d6a5e] border-t border-[#e7deda] pt-3 space-y-2">
                    <div class="grid grid-cols-2 gap-2">
                        <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class']); ?></p>
                        <p><strong>Section:</strong> <?php echo ucfirst($student['section']); ?></p>
                    </div>
                    <?php if ($student['level']): ?>
                    <p><strong>Level:</strong> <?php echo strtoupper($student['level']); ?></p>
                    <?php endif; ?>
                    <?php if ($student['department']): ?>
                    <p><strong>Department:</strong> <?php echo ucfirst($student['department']); ?></p>
                    <?php endif; ?>
                    <p><strong>Guardian:</strong> <?php echo htmlspecialchars($student['guardian_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['guardian_phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                    <p><strong>Joined:</strong> <?php echo date('M j, Y', strtotime($student['created_at'])); ?></p>
                </div>
                
                <div class="flex justify-end gap-2 border-t border-[#e7deda] pt-3 mt-3">
                    <button onclick="viewStudent(<?php echo $student['id']; ?>)" 
                            class="text-blue-600 flex items-center gap-1 bg-blue-50 px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base">visibility</span> View
                    </button>
                    <button onclick="editStudent(<?php echo $student['id']; ?>)" 
                            class="text-green-600 flex items-center gap-1 bg-green-50 px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base">edit</span> Edit
                    </button>
                    <button onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars(addslashes($student['first_name'] . ' ' . $student['last_name'])); ?>')" 
                            class="text-red-600 flex items-center gap-1 bg-red-50 px-3 py-2 text-sm rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base">delete</span> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- View Student Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="viewModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Student Details</h3>
                <button onclick="closeViewModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <!-- Modal Content - Scrollable -->
        <div class="flex-1 overflow-y-auto p-4" id="viewModalContent">
            <!-- Content will be loaded by JavaScript -->
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="editModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Edit Student</h3>
                <button onclick="closeEditModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <!-- Modal Content - Scrollable -->
        <div class="flex-1 overflow-y-auto p-4" id="editModalContent">
            <!-- Content will be loaded by JavaScript -->
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
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Student</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this student?</p>
            <div class="flex space-x-3 w-full">
                <button type="button" onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="student_id" id="deleteStudentId">
                    <button type="submit" name="delete_student" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // View Student Modal
    function viewStudent(studentId) {
        // Fetch student data via AJAX
        fetch(`get_student_details.php?id=${studentId}`)
            .then(response => response.json())
            .then(student => {
                const modalContent = document.getElementById('viewModalContent');
                
                // Format the level and department display
                let levelDisplay = student.level ? student.level.toUpperCase() : 'N/A';
                let departmentDisplay = student.department ? 
                    student.department.charAt(0).toUpperCase() + student.department.slice(1) : 'N/A';
                
                modalContent.innerHTML = `
                    <div class="space-y-6">
                        <!-- Student Basic Info -->
                        <div class="text-center mb-4">
                            <div class="flex justify-center mb-3">
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#ff6933]/20">
                                    <span class="material-symbols-outlined text-2xl text-[#ff6933]">person</span>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold text-[#181210]">${student.first_name} ${student.last_name}</h4>
                            <p class="text-sm text-[#8d6a5e]">${student.email}</p>
                            <p class="text-xs text-[#8d6a5e] mt-1">Student Code: ${student.student_code}</p>
                            ${student.student_pin ? `<p class="text-xs text-[#8d6a5e]">PIN: ${student.student_pin}</p>` : ''}
                        </div>

                        <!-- Academic Information -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">school</span>
                                Academic Information
                            </h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[#8d6a5e]">Section:</span>
                                    <p class="font-medium text-[#181210] capitalize">${student.section}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Class:</span>
                                    <p class="font-medium text-[#181210]">${student.class}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Level:</span>
                                    <p class="font-medium text-[#181210]">${levelDisplay}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Department:</span>
                                    <p class="font-medium text-[#181210]">${departmentDisplay}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Guardian Information -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">family_restroom</span>
                                Guardian Information
                            </h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[#8d6a5e]">Name:</span>
                                    <p class="font-medium text-[#181210]">${student.guardian_name}</p>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Phone:</span>
                                    <p class="font-medium text-[#181210]">${student.guardian_phone}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="bg-[#f8f6f5] rounded-lg p-4">
                            <h5 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">badge</span>
                                Account Information
                            </h5>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-[#8d6a5e]">Status:</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        ${student.status === 'approved' ? 'bg-green-100 text-green-800' : 
                                         student.status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'}">
                                        ${student.status.charAt(0).toUpperCase() + student.status.slice(1)}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-[#8d6a5e]">Date Joined:</span>
                                    <p class="font-medium text-[#181210]">${new Date(student.created_at).toLocaleDateString()}</p>
                                </div>
                                ${student.approved_at ? `
                                <div>
                                    <span class="text-[#8d6a5e]">Approved On:</span>
                                    <p class="font-medium text-[#181210]">${new Date(student.approved_at).toLocaleDateString()}</p>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
                document.getElementById('viewModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error fetching student details:', error);
                alert('Error loading student details');
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Edit Student Modal
    function editStudent(studentId) {
        // Fetch student data via AJAX
        fetch(`get_student_details.php?id=${studentId}`)
            .then(response => response.json())
            .then(student => {
                const modalContent = document.getElementById('editModalContent');
                
                modalContent.innerHTML = `
                    <form method="POST" class="space-y-6" onsubmit="return validateEditForm(this)">
                        <input type="hidden" name="student_id" value="${student.id}">
                        
                        <!-- Personal Information -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">person</span>
                                Personal Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">First Name *</label>
                                    <input type="text" name="first_name" value="${student.first_name}" required 
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Last Name *</label>
                                    <input type="text" name="last_name" value="${student.last_name}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Email Address *</label>
                                    <input type="email" name="email" value="${student.email}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                            </div>
                        </div>

                        <!-- Academic Information -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">school</span>
                                Academic Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Section *</label>
                                    <select name="section" required class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="primary" ${student.section === 'primary' ? 'selected' : ''}>Primary</option>
                                        <option value="secondary" ${student.section === 'secondary' ? 'selected' : ''}>Secondary</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class *</label>
                                    <input type="text" name="class" value="${student.class}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Level</label>
                                    <select name="level" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Level</option>
                                        <option value="jss" ${student.level === 'jss' ? 'selected' : ''}>JSS</option>
                                        <option value="sss" ${student.level === 'sss' ? 'selected' : ''}>SSS</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Department</label>
                                    <select name="department" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                        <option value="">Select Department</option>
                                        <option value="science" ${student.department === 'science' ? 'selected' : ''}>Science</option>
                                        <option value="art" ${student.department === 'art' ? 'selected' : ''}>Art</option>
                                        <option value="commercial" ${student.department === 'commercial' ? 'selected' : ''}>Commercial</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Guardian Information -->
                        <div>
                            <h4 class="font-semibold text-[#181210] mb-3 flex items-center">
                                <span class="material-symbols-outlined mr-2 text-[#ff6933]">family_restroom</span>
                                Guardian Information
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Name *</label>
                                    <input type="text" name="guardian_name" value="${student.guardian_name}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Phone *</label>
                                    <input type="tel" name="guardian_phone" value="${student.guardian_phone}" required
                                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
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
                                    Reset this student's password to the default password.
                                </p>
                                <form method="POST" onsubmit="return confirm('Reset password for ${student.first_name} ${student.last_name}?')">
                                    <input type="hidden" name="student_id" value="${student.id}">
                                    <button type="submit" name="reset_password" 
                                            class="w-full h-12 rounded-lg bg-yellow-500 text-white text-base font-bold hover:bg-yellow-600 transition-colors flex items-center justify-center">
                                        <span class="material-symbols-outlined mr-2">lock_reset</span>
                                        Reset Password to Default
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-[#e7deda]">
                            <button type="submit" name="update_student" 
                                    class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white text-base font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                                <span class="material-symbols-outlined mr-2">save</span>
                                Update Student
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
            })
            .catch(error => {
                console.error('Error fetching student details:', error);
                alert('Error loading student details for editing');
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function validateEditForm(form) {
        const email = form.email.value;
        const phone = form.guardian_phone.value;
        
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address');
            return false;
        }
        
        if (!isValidPhone(phone)) {
            alert('Please enter a valid phone number');
            return false;
        }
        
        return confirm('Are you sure you want to update this student?');
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidPhone(phone) {
        const phoneRegex = /^\+?[\d\s\-\(\)]{10,}$/;
        return phoneRegex.test(phone);
    }

    // Delete Modal functions (remain the same)
    function confirmDelete(studentId, studentName) {
        document.getElementById('deleteStudentId').value = studentId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete <strong>${studentName}</strong>?<br><span class="text-red-600 text-sm">This action cannot be undone.</span>`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Export function (remains the same)
    function exportStudentData() {
        // ... existing export code ...
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.id === 'viewModal') closeViewModal();
        if (e.target.id === 'editModal') closeEditModal();
        if (e.target.id === 'deleteModal') closeDeleteModal();
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeViewModal();
            closeEditModal();
            closeDeleteModal();
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

<style>
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>