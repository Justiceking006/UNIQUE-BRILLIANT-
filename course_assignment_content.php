<?php
// course_assignment_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle course assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_course'])) {
        $subject_id = intval($_POST['subject_id']);
        $assigned_to_type = $_POST['assigned_to_type'];
        $assigned_to_id = intval($_POST['assigned_to_id']);
        $class_name = $_POST['class_name'];
        $academic_year = $_POST['academic_year'];
        
        try {
            $stmt = $db->prepare("INSERT INTO course_assignments (subject_id, assigned_to_type, assigned_to_id, class_name, academic_year, assigned_by) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$subject_id, $assigned_to_type, $assigned_to_id, $class_name, $academic_year, $_SESSION['user_id']])) {
                $success_message = "Course assigned successfully!";
            } else {
                $error_message = "Failed to assign course. Please try again.";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'unique_assignment') !== false) {
                $error_message = "This course is already assigned to the selected recipient for this academic year.";
            } else {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    if (isset($_POST['remove_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        
        try {
            $stmt = $db->prepare("DELETE FROM course_assignments WHERE id = ?");
            
            if ($stmt->execute([$assignment_id])) {
                $success_message = "Course assignment removed successfully!";
            } else {
                $error_message = "Failed to remove assignment. Please try again.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get current academic year
$current_year = date('Y');
$next_year = $current_year + 1;
$academic_year = "$current_year/$next_year";

// Get all active subjects
$subjects = $db->query("SELECT * FROM subjects WHERE status = 'active' ORDER BY level, subject_name")->fetchAll();

// Get all active students
$students = $db->query("SELECT id, first_name, last_name, student_code, class, section FROM students WHERE status = 'approved' ORDER BY class, first_name, last_name")->fetchAll();

// Get all active staff
$staff = $db->query("SELECT id, first_name, last_name, staff_id, department FROM staff WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get all active classes
$classes = $db->query("SELECT class_name, section FROM academic_classes WHERE status = 'active' ORDER BY section, class_name")->fetchAll();

// Get current course assignments - FIXED QUERY
$assignments = $db->query("SELECT ca.*, 
                                  s.subject_code, s.subject_name, s.level as subject_level,
                                  st.first_name as student_first_name, st.last_name as student_last_name, st.student_code,
                                  sf.first_name as staff_first_name, sf.last_name as staff_last_name, sf.staff_id
                           FROM course_assignments ca 
                           JOIN subjects s ON ca.subject_id = s.id 
                           LEFT JOIN students st ON ca.assigned_to_type = 'student' AND ca.assigned_to_id = st.id 
                           LEFT JOIN staff sf ON ca.assigned_to_type = 'staff' AND ca.assigned_to_id = sf.id 
                           WHERE ca.status = 'active' 
                           ORDER BY ca.academic_year DESC, ca.created_at DESC")->fetchAll();

// Get statistics
$total_assignments = count($assignments);
$student_assignments = $db->query("SELECT COUNT(*) FROM course_assignments WHERE assigned_to_type = 'student' AND status = 'active'")->fetchColumn();
$staff_assignments = $db->query("SELECT COUNT(*) FROM course_assignments WHERE assigned_to_type = 'staff' AND status = 'active'")->fetchColumn();
$current_year_assignments = $db->query("SELECT COUNT(*) FROM course_assignments WHERE academic_year = '$academic_year' AND status = 'active'")->fetchColumn();
?>

<!-- Course Assignment Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Course Assignment</h1>
            <p class="text-[#8d6a5e]">Assign courses to students and teaching staff</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <button onclick="openAssignCourseModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">assignment</span>
                Assign Course
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
<section class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Assignments</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_assignments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">assignment</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Student Assignments</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $student_assignments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">school</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Staff Assignments</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $staff_assignments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">badge</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">This Year</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $current_year_assignments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">calendar_month</span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2">
        <!-- Current Assignments -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">list</span>
                Current Course Assignments
            </h3>
            
            <?php if (empty($assignments)): ?>
                <div class="text-center py-8 text-[#8d6a5e]">
                    <span class="material-symbols-outlined text-4xl mb-2">assignment</span>
                    <p class="text-sm">No course assignments yet</p>
                    <p class="text-xs mt-1">Assign courses to students or staff to get started</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($assignments as $assignment): ?>
                    <div class="p-4 border border-[#e7deda] rounded-lg hover:border-[#ff6933] transition-colors">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h4 class="font-bold text-[#181210]"><?php echo htmlspecialchars($assignment['subject_name']); ?></h4>
                                <p class="text-sm text-[#8d6a5e]"><?php echo htmlspecialchars($assignment['subject_code']); ?></p>
                            </div>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                <?php echo $assignment['academic_year']; ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                            <div>
                                <p class="text-xs text-[#8d6a5e] mb-1">Assigned To:</p>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-2">
                                        <span class="material-symbols-outlined text-[#ff6933] text-sm">
                                            <?php echo $assignment['assigned_to_type'] === 'student' ? 'school' : 'badge'; ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-[#181210]">
                                            <?php if ($assignment['assigned_to_type'] === 'student'): ?>
                                                <?php echo htmlspecialchars($assignment['student_first_name'] . ' ' . $assignment['student_last_name']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($assignment['staff_first_name'] . ' ' . $assignment['staff_last_name']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-[#8d6a5e]">
                                            <?php if ($assignment['assigned_to_type'] === 'student'): ?>
                                                <?php echo htmlspecialchars($assignment['student_code']); ?>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($assignment['staff_id']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <p class="text-xs text-[#8d6a5e] mb-1">Details:</p>
                                <div class="space-y-1">
                                    <p class="text-sm text-[#181210]">
                                        <span class="font-medium">Type:</span> 
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                            <?php echo $assignment['assigned_to_type'] === 'student' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                                            <?php echo ucfirst($assignment['assigned_to_type']); ?>
                                        </span>
                                    </p>
                                    <?php if ($assignment['class_name']): ?>
                                    <p class="text-sm text-[#181210]">
                                        <span class="font-medium">Class:</span> <?php echo htmlspecialchars($assignment['class_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center text-xs text-[#8d6a5e] border-t border-[#e7deda] pt-3">
                            <span>
                                Assigned on <?php echo date('M j, Y', strtotime($assignment['created_at'])); ?>
                            </span>
                            <form method="POST" onsubmit="return confirm('Remove this course assignment?')">
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                <button type="submit" name="remove_assignment" 
                                        class="text-red-600 hover:text-red-800 transition-colors">
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">analytics</span>
                Assignment Overview
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Subjects:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($subjects); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Active Students:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($students); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Active Staff:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($staff); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Academic Year:</span>
                    <span class="font-medium text-[#181210]"><?php echo $academic_year; ?></span>
                </div>
            </div>
        </div>

        <!-- Available Resources -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">inventory</span>
                Available Resources
            </h3>
            
            <div class="space-y-4">
                <!-- Subjects by Level -->
                <div>
                    <p class="text-sm font-medium text-[#181210] mb-2">Subjects by Level</p>
                    <div class="space-y-2">
                        <?php
                        $levels = ['primary', 'jss', 'sss'];
                        foreach ($levels as $level):
                            $level_count = $db->query("SELECT COUNT(*) FROM subjects WHERE level = '$level' AND status = 'active'")->fetchColumn();
                        ?>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#8d6a5e]"><?php echo strtoupper($level); ?>:</span>
                            <span class="font-medium text-[#181210]"><?php echo $level_count; ?> subjects</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Students by Class -->
                <div>
                    <p class="text-sm font-medium text-[#181210] mb-2">Students by Section</p>
                    <div class="space-y-2">
                        <?php
                        $primary_students = $db->query("SELECT COUNT(*) FROM students WHERE section = 'primary' AND status = 'approved'")->fetchColumn();
                        $secondary_students = $db->query("SELECT COUNT(*) FROM students WHERE section = 'secondary' AND status = 'approved'")->fetchColumn();
                        ?>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#8d6a5e]">Primary:</span>
                            <span class="font-medium text-[#181210]"><?php echo $primary_students; ?> students</span>
                        </div>
                        <div class="flex justify-between items-center text-sm">
                            <span class="text-[#8d6a5e]">Secondary:</span>
                            <span class="font-medium text-[#181210]"><?php echo $secondary_students; ?> students</span>
                        </div>
                    </div>
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
                <a href="?page=subjects" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">menu_book</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Manage Subjects</p>
                        <p class="text-xs text-[#8d6a5e]">Add or edit subjects</p>
                    </div>
                </a>
                
                <a href="?page=assign-teacher" 
                   class="flex items-center p-3 border border-[#e7deda] rounded-lg hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors">
                    <span class="material-symbols-outlined mr-3 text-[#ff6933]">groups</span>
                    <div>
                        <p class="text-sm font-medium text-[#181210]">Class Teachers</p>
                        <p class="text-xs text-[#8d6a5e]">Assign teachers to classes</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Assign Course Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="assignCourseModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Assign Course</h3>
                <button onclick="closeAssignCourseModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="flex-1 overflow-y-auto p-4">
            <div class="space-y-4">
                <!-- Subject Selection -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Subject *</label>
                    <select name="subject_id" id="subjectSelect" required
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Choose a subject...</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" data-level="<?php echo $subject['level']; ?>">
                                <?php echo htmlspecialchars($subject['subject_name']); ?> 
                                (<?php echo htmlspecialchars($subject['subject_code']); ?>)
                                - <?php echo strtoupper($subject['level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Assignment Type -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Assign To *</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center p-3 border border-[#e7deda] rounded-lg cursor-pointer has-[:checked]:border-[#ff6933] has-[:checked]:bg-[#ff6933]/5">
                            <input type="radio" name="assigned_to_type" value="student" class="hidden" checked onchange="toggleAssigneeType()">
                            <span class="flex items-center text-sm">
                                <span class="material-symbols-outlined mr-2 text-base">school</span>
                                Student
                            </span>
                        </label>
                        <label class="flex items-center p-3 border border-[#e7deda] rounded-lg cursor-pointer has-[:checked]:border-[#ff6933] has-[:checked]:bg-[#ff6933]/5">
                            <input type="radio" name="assigned_to_type" value="staff" class="hidden" onchange="toggleAssigneeType()">
                            <span class="flex items-center text-sm">
                                <span class="material-symbols-outlined mr-2 text-base">badge</span>
                                Staff
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Student Selection -->
                <div id="studentSelection">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Student *</label>
                    <select name="assigned_to_id" id="studentSelect"
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Choose a student...</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>" data-class="<?php echo htmlspecialchars($student['class']); ?>">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                (<?php echo htmlspecialchars($student['student_code']); ?>)
                                - <?php echo htmlspecialchars($student['class']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Staff Selection -->
                <div id="staffSelection" class="hidden">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Staff *</label>
                    <select name="assigned_to_id" id="staffSelect"
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Choose a staff member...</option>
                        <?php foreach ($staff as $staff_member): ?>
                            <option value="<?php echo $staff_member['id']; ?>">
                                <?php echo htmlspecialchars($staff_member['first_name'] . ' ' . $staff_member['last_name']); ?>
                                (<?php echo htmlspecialchars($staff_member['staff_id']); ?>)
                                <?php if ($staff_member['department']): ?>
                                    - <?php echo htmlspecialchars($staff_member['department']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Class Selection -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class (Optional)</label>
                    <select name="class_name"
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Not specific to a class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class['class_name']); ?>">
                                <?php echo htmlspecialchars($class['class_name']); ?> (<?php echo ucfirst($class['section']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Academic Year -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Academic Year *</label>
                    <input type="text" name="academic_year" value="<?php echo $academic_year; ?>" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeAssignCourseModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="assign_course"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Assign Course
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAssignCourseModal() {
        document.getElementById('assignCourseModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAssignCourseModal() {
        document.getElementById('assignCourseModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function toggleAssigneeType() {
        const studentSelection = document.getElementById('studentSelection');
        const staffSelection = document.getElementById('staffSelection');
        const studentSelect = document.getElementById('studentSelect');
        const staffSelect = document.getElementById('staffSelect');
        
        const assignedToType = document.querySelector('input[name="assigned_to_type"]:checked').value;
        
        if (assignedToType === 'student') {
            studentSelection.classList.remove('hidden');
            staffSelection.classList.add('hidden');
            studentSelect.required = true;
            staffSelect.required = false;
            staffSelect.value = '';
        } else {
            studentSelection.classList.add('hidden');
            staffSelection.classList.remove('hidden');
            studentSelect.required = false;
            staffSelect.required = true;
            studentSelect.value = '';
        }
    }

    // Initialize the form
    document.addEventListener('DOMContentLoaded', function() {
        toggleAssigneeType();
    });

    // Close modal when clicking outside
    document.getElementById('assignCourseModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAssignCourseModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAssignCourseModal();
        }
    });
</script>