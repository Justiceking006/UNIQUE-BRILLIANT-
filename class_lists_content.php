<?php
// class_lists_content.php

// Don't start session here since it's already started in admin_dashboard.php
// session_start(); // Remove this line

require_once 'connect.php';

// Check if user is admin - use existing session
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    // If we need to redirect, we should handle this differently in an included file
    // For now, just exit if not authorized
    exit('Unauthorized access');
}

$db = getDBConnection();
$success_message = '';
$error_message = '';

// Handle AJAX requests - output only JSON for AJAX calls
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_request'])) {
    // For AJAX requests, we need to handle the response differently
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
            
            echo json_encode(['success' => true, 'message' => 'Student information updated successfully!']);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update student: ' . $e->getMessage()]);
            exit;
        }
    }
    
    if (isset($_POST['reset_password'])) {
        $student_id = $_POST['student_id'];
        $new_password = 'password123';
        
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE student_id = ?");
            $stmt->execute([$hashed_password, $student_id]);
            
            echo json_encode(['success' => true, 'message' => 'Password reset successfully! New password: password123']);
            exit;
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to reset password: ' . $e->getMessage()]);
            exit;
        }
    }
}

// For regular page loads, continue with HTML output
// Get class list (only approved students)
$classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND status = 'approved' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);

// Get selected class
$selected_class = isset($_GET['class']) ? sanitize($_GET['class']) : (count($classes) > 0 ? $classes[0] : '');

// Get students for selected class (only approved)
$students = [];
$total_students = 0;
$primary_count = 0;
$secondary_count = 0;

if (!empty($selected_class)) {
    $stmt = $db->prepare("SELECT * FROM students WHERE class = ? AND status = 'approved' ORDER BY first_name, last_name");
    $stmt->execute([$selected_class]);
    $students = $stmt->fetchAll();
    
    $total_students = count($students);
    $primary_count = $db->query("SELECT COUNT(*) FROM students WHERE class = '$selected_class' AND section = 'primary' AND status = 'approved'")->fetchColumn();
    $secondary_count = $db->query("SELECT COUNT(*) FROM students WHERE class = '$selected_class' AND section = 'secondary' AND status = 'approved'")->fetchColumn();
}
?>

<!-- Class Lists Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Class Lists</h1>
            <p class="text-[#8d6a5e]">View and manage students by class and section</p>
        </div>
        <div class="mt-4 lg:mt-0 flex gap-3">
            <button onclick="printClassList()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">print</span>
                Print List
            </button>
            <button onclick="exportClassList()"
                    class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">download</span>
                Export
            </button>
        </div>
    </div>
</div>

<!-- Class Selection -->
<section class="mb-6">
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-[#181210] mb-1">Select Class</h3>
                <p class="text-[#8d6a5e] text-sm">Choose a class to view student list</p>
            </div>
            <div class="flex gap-3">
                <select onchange="window.location.href='?page=class-lists&class='+this.value" 
                        class="w-full sm:w-64 h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    <?php if (empty($classes)): ?>
                        <option value="">No classes available</option>
                    <?php else: ?>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class); ?>" 
                                    <?php echo $selected_class === $class ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($selected_class)): ?>
<!-- Class Overview -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Students</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_students; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">groups</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Primary Section</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $primary_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">child_care</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Secondary Section</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $secondary_count; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">teenager</span>
        </div>
    </div>
</section>

<!-- Student List -->
<section>
    <?php if (empty($students)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">school</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Students in <?php echo htmlspecialchars($selected_class); ?></h3>
            <p class="text-[#8d6a5e] mb-6">There are no approved students in this class.</p>
            <a href="?page=online-admissions" 
               class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2">how_to_reg</span>
                Review Applications
            </a>
        </div>
    <?php else: ?>
        <!-- Class Header -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-bold text-[#181210]"><?php echo htmlspecialchars($selected_class); ?> - Student List</h2>
                    <p class="text-[#8d6a5e]">
                        Total: <?php echo $total_students; ?> students 
                        (<?php echo $primary_count; ?> primary, <?php echo $secondary_count; ?> secondary)
                    </p>
                </div>
                <div class="mt-4 lg:mt-0">
                    <p class="text-[#8d6a5e] text-sm">
                        <strong>Status:</strong> All students are approved and active
                    </p>
                </div>
            </div>
        </div>

        <!-- Student Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="classList">
            <?php foreach ($students as $student): ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] hover:shadow-md transition-shadow">
                <div class="text-center">
                    <!-- Student Avatar -->
                    <div class="flex justify-center mb-4">
                        <div class="h-16 w-16 rounded-full bg-[#ff6933]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl text-[#ff6933]">person</span>
                        </div>
                    </div>
                    
                    <!-- Student Info -->
                    <h3 class="text-lg font-bold text-[#181210] mb-1">
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </h3>
                    <p class="text-[#8d6a5e] text-sm mb-2">ID: <?php echo htmlspecialchars($student['student_code']); ?></p>
                    
                    <!-- Section Badge -->
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium mb-4
                        <?php echo $student['section'] === 'primary' ? 'bg-orange-100 text-orange-800' : 'bg-purple-100 text-purple-800'; ?>">
                        <span class="material-symbols-outlined text-xs mr-1">
                            <?php echo $student['section'] === 'primary' ? 'child_care' : 'teenager'; ?>
                        </span>
                        <?php echo ucfirst($student['section']); ?> Section
                    </span>
                    
                    <!-- Academic Info -->
                    <div class="text-left text-sm text-[#8d6a5e] space-y-2 mt-4 border-t border-[#e7deda] pt-4">
                        <div class="flex items-center">
                            <span class="material-symbols-outlined text-base mr-2">class</span>
                            <span><?php echo htmlspecialchars($student['class']); ?></span>
                        </div>
                        <?php if ($student['level']): ?>
                        <div class="flex items-center">
                            <span class="material-symbols-outlined text-base mr-2">school</span>
                            <span><?php echo strtoupper($student['level']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($student['department']): ?>
                        <div class="flex items-center">
                            <span class="material-symbols-outlined text-base mr-2">business_center</span>
                            <span><?php echo ucfirst($student['department']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center">
                            <span class="material-symbols-outlined text-base mr-2">person</span>
                            <span class="truncate"><?php echo htmlspecialchars($student['guardian_name']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex justify-center gap-2 border-t border-[#e7deda] pt-4 mt-4">
                        <button onclick="viewStudent(<?php echo $student['id']; ?>)" 
                                class="inline-flex items-center p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                title="View Profile">
                            <span class="material-symbols-outlined text-base">visibility</span>
                        </button>
                        <button onclick="editStudent(<?php echo $student['id']; ?>)" 
                                class="inline-flex items-center p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                title="Edit Student">
                            <span class="material-symbols-outlined text-base">edit</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Summary -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mt-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-[#181210] mb-2">Class Summary</h3>
                    <p class="text-[#8d6a5e]">
                        Total Students: <strong><?php echo $total_students; ?></strong> | 
                        Primary: <strong><?php echo $primary_count; ?></strong> | 
                        Secondary: <strong><?php echo $secondary_count; ?></strong>
                    </p>
                </div>
                <div class="mt-4 sm:mt-0">
                    <p class="text-[#8d6a5e] text-sm">
                        Last updated: <?php echo date('F j, Y'); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php else: ?>
    <!-- No Classes Available -->
    <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
        <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">class</span>
        <h3 class="text-xl font-bold text-[#181210] mb-2">No Classes Available</h3>
        <p class="text-[#8d6a5e] mb-6">There are no classes with approved students in the system.</p>
        <a href="?page=online-admissions" 
           class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
            <span class="material-symbols-outlined mr-2">how_to_reg</span>
            Review Applications
        </a>
    </div>
<?php endif; ?>

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

<script>
    // View Student Modal
    function viewStudent(studentId) {
        showLoading();
        fetch(`get_student_details.php?id=${studentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(student => {
                const modalContent = document.getElementById('viewModalContent');
                
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
                hideLoading();
            })
            .catch(error => {
                console.error('Error fetching student details:', error);
                showError('Error loading student details: ' + error.message);
                hideLoading();
            });
    }

    function closeViewModal() {
        document.getElementById('viewModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Edit Student Modal
    function editStudent(studentId) {
        showLoading();
        fetch(`get_student_details.php?id=${studentId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(student => {
                const modalContent = document.getElementById('editModalContent');
                
                modalContent.innerHTML = `
                    <form class="space-y-6" id="editStudentForm" onsubmit="return handleEditFormSubmit(event, ${student.id})">
                        <input type="hidden" name="student_id" value="${student.id}">
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
                                <button type="button" onclick="resetPassword(${student.id}, '${student.first_name} ${student.last_name}')" 
                                        class="w-full h-12 rounded-lg bg-yellow-500 text-white text-base font-bold hover:bg-yellow-600 transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined mr-2">lock_reset</span>
                                    Reset Password to Default
                                </button>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-[#e7deda]">
                            <button type="submit" name="update_student" id="updateStudentBtn"
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
                hideLoading();
            })
            .catch(error => {
                console.error('Error fetching student details:', error);
                showError('Error loading student details for editing: ' + error.message);
                hideLoading();
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Handle edit form submission with AJAX
    function handleEditFormSubmit(event, studentId) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        formData.append('update_student', '1');
        
        // Validate form
        if (!validateEditForm(form)) {
            return false;
        }
        
        // Show loading state
        const submitBtn = document.getElementById('updateStudentBtn');
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
                showSuccess('Student Updated', data.message);
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
            showError('Failed to update student. Please try again.');
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        })
        .finally(() => {
            hideLoading();
        });
        
        return false;
    }

    // Handle password reset
    function resetPassword(studentId, studentName) {
        if (!confirm(`Reset password for ${studentName} to "password123"?`)) {
            return;
        }
        
        showLoading();
        
        const formData = new FormData();
        formData.append('student_id', studentId);
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

    function validateEditForm(form) {
        const email = form.email.value;
        const phone = form.guardian_phone.value;
        
        if (!isValidEmail(email)) {
            showError('Please enter a valid email address');
            return false;
        }
        
        if (!isValidPhone(phone)) {
            showError('Please enter a valid phone number');
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

    // Print and Export functions
    function printClassList() {
        const classList = document.getElementById('classList');
        if (classList) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Class List - <?php echo htmlspecialchars($selected_class); ?></title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                            .student-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; }
                            .student-card { border: 1px solid #ddd; padding: 15px; border-radius: 8px; }
                            .student-name { font-weight: bold; margin-bottom: 5px; }
                            .student-id { color: #666; font-size: 12px; margin-bottom: 10px; }
                            @media print {
                                .no-print { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Class List - <?php echo htmlspecialchars($selected_class); ?></h1>
                            <p>Generated on: <?php echo date('F j, Y'); ?></p>
                            <p>Total Students: <?php echo $total_students; ?> (Primary: <?php echo $primary_count; ?>, Secondary: <?php echo $secondary_count; ?>)</p>
                        </div>
                        ${classList.innerHTML}
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        } else {
            showError('No class list to print.');
        }
    }

    function exportClassList() {
        showLoading();
        // Simulate export process
        setTimeout(() => {
            hideLoading();
            showSuccess('Export Completed', 'Class list has been exported successfully!');
        }, 1500);
    }

    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.id === 'viewModal') closeViewModal();
        if (e.target.id === 'editModal') closeEditModal();
        if (e.target.id === 'successModal') closeSuccessModal();
        if (e.target.id === 'errorModal') closeErrorModal();
        if (e.target.id === 'loadingOverlay') hideLoading();
    });

    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeViewModal();
            closeEditModal();
            closeSuccessModal();
            closeErrorModal();
        }
    });
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