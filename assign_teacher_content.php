<?php
// assign_teacher_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle teacher assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_teacher'])) {
        $class_name = $_POST['class_name'];
        $section = $_POST['section'];
        $staff_id = intval($_POST['staff_id']);
        $academic_year = $_POST['academic_year'];
        $subjects = isset($_POST['subjects']) ? implode(',', $_POST['subjects']) : null;
        
        try {
            // Check if class already has a teacher assigned for this academic year
            $check_stmt = $db->prepare("SELECT id FROM class_teachers WHERE class_name = ? AND academic_year = ?");
            $check_stmt->execute([$class_name, $academic_year]);
            $existing_assignment = $check_stmt->fetch();
            
            if ($existing_assignment) {
                // Update existing assignment
                $stmt = $db->prepare("UPDATE class_teachers SET staff_id = ?, subjects = ?, status = 'active' WHERE class_name = ? AND academic_year = ?");
                $stmt->execute([$staff_id, $subjects, $class_name, $academic_year]);
                $success_message = "Class teacher assignment updated successfully!";
            } else {
                // Create new assignment
                $stmt = $db->prepare("INSERT INTO class_teachers (class_name, section, staff_id, academic_year, subjects) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$class_name, $section, $staff_id, $academic_year, $subjects]);
                $success_message = "Class teacher assigned successfully!";
            }
            
            // Also update the academic_classes table
            $update_class_stmt = $db->prepare("UPDATE academic_classes SET class_teacher_id = ? WHERE class_name = ? AND section = ?");
            $update_class_stmt->execute([$staff_id, $class_name, $section]);
            
        } catch (PDOException $e) {
            $error_message = "Failed to assign teacher: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['remove_assignment'])) {
        $assignment_id = intval($_POST['assignment_id']);
        $class_name = $_POST['class_name'];
        $section = $_POST['section'];
        
        try {
            $stmt = $db->prepare("UPDATE class_teachers SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$assignment_id]);
            
            // Also update the academic_classes table
            $update_class_stmt = $db->prepare("UPDATE academic_classes SET class_teacher_id = NULL WHERE class_name = ? AND section = ?");
            $update_class_stmt->execute([$class_name, $section]);
            
            $success_message = "Teacher assignment removed successfully!";
        } catch (PDOException $e) {
            $error_message = "Failed to remove assignment: " . $e->getMessage();
        }
    }
}

// Get current academic year
$current_year = date('Y');
$next_year = $current_year + 1;
$academic_year = "$current_year/$next_year";

// Get all active classes
$classes = $db->query("SELECT * FROM academic_classes WHERE status = 'active' ORDER BY section, class_name")->fetchAll();

// Get all active teachers
$teachers = $db->query("SELECT id, first_name, last_name, staff_id, department, subject_specialization FROM staff WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get all subjects for selection
$subjects = $db->query("SELECT * FROM subjects WHERE status = 'active' ORDER BY subject_name")->fetchAll();

// Get current class teacher assignments
$assignments = $db->query("SELECT ct.*, s.first_name, s.last_name, s.staff_id, s.department, 
                                  ac.level, ac.current_students
                           FROM class_teachers ct 
                           JOIN staff s ON ct.staff_id = s.id 
                           JOIN academic_classes ac ON ct.class_name = ac.class_name AND ct.section = ac.section
                           WHERE ct.status = 'active' 
                           ORDER BY ct.section, ct.class_name")->fetchAll();

// Get statistics
$total_classes = count($classes);
$classes_with_teachers = count($assignments);
$primary_assignments = $db->query("SELECT COUNT(*) FROM class_teachers WHERE section = 'primary' AND status = 'active'")->fetchColumn();
$secondary_assignments = $db->query("SELECT COUNT(*) FROM class_teachers WHERE section = 'secondary' AND status = 'active'")->fetchColumn();
?>

<!-- Assign Class Teacher Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Assign Class Teachers</h1>
            <p class="text-[#8d6a5e]">Manage teacher assignments to classes and subjects</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <button onclick="openAssignTeacherModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">person_add</span>
                Assign Teacher
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
                <p class="text-sm text-[#8d6a5e]">Total Classes</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_classes; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">class</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">With Teachers</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $classes_with_teachers; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">check_circle</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Primary Classes</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $primary_assignments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">child_care</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Secondary Classes</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $secondary_assignments; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">school</span>
        </div>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Current Assignments -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
            <span class="material-symbols-outlined mr-2 text-[#ff6933]">groups</span>
            Current Class Teachers
        </h3>
        
        <?php if (empty($assignments)): ?>
            <div class="text-center py-8 text-[#8d6a5e]">
                <span class="material-symbols-outlined text-4xl mb-2">group</span>
                <p class="text-sm">No teacher assignments yet</p>
                <p class="text-xs mt-1">Assign teachers to classes to get started</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($assignments as $assignment): ?>
                <div class="p-4 border border-[#e7deda] rounded-lg hover:border-[#ff6933] transition-colors">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-bold text-[#181210]"><?php echo htmlspecialchars($assignment['class_name']); ?></h4>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $assignment['section'] === 'primary' ? 'bg-orange-100 text-orange-800' : 'bg-purple-100 text-purple-800'; ?>">
                                <?php echo ucfirst($assignment['section']); ?> • 
                                <?php echo $assignment['level'] ? strtoupper($assignment['level']) : ''; ?>
                            </span>
                        </div>
                        <span class="text-xs text-[#8d6a5e]"><?php echo $assignment['academic_year']; ?></span>
                    </div>
                    
                    <div class="flex items-center mb-3">
                        <div class="flex-shrink-0 h-10 w-10 bg-[#ff6933]/10 rounded-full flex items-center justify-center mr-3">
                            <span class="material-symbols-outlined text-[#ff6933] text-sm">person</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-[#181210]">
                                <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?>
                            </p>
                            <p class="text-xs text-[#8d6a5e]">
                                <?php echo htmlspecialchars($assignment['staff_id']); ?> • 
                                <?php echo htmlspecialchars($assignment['department']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($assignment['subjects']): ?>
                    <div class="mb-3">
                        <p class="text-xs text-[#8d6a5e] mb-1">Subjects:</p>
                        <div class="flex flex-wrap gap-1">
                            <?php 
                            $subject_ids = explode(',', $assignment['subjects']);
                            foreach ($subject_ids as $subject_id): 
                                $subject_stmt = $db->prepare("SELECT subject_name FROM subjects WHERE id = ?");
                                $subject_stmt->execute([$subject_id]);
                                $subject = $subject_stmt->fetch();
                                if ($subject):
                            ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                </span>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between items-center text-xs text-[#8d6a5e]">
                        <span><?php echo $assignment['current_students']; ?> students</span>
                        <form method="POST" onsubmit="return confirm('Remove <?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?> from <?php echo htmlspecialchars($assignment['class_name']); ?>?')">
                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                            <input type="hidden" name="class_name" value="<?php echo htmlspecialchars($assignment['class_name']); ?>">
                            <input type="hidden" name="section" value="<?php echo htmlspecialchars($assignment['section']); ?>">
                            <button type="submit" name="remove_assignment" 
                                    class="text-red-600 hover:text-red-800 transition-colors">
                                Remove Assignment
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Available Classes & Quick Actions -->
    <div class="space-y-6">
        <!-- Available Classes -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">class</span>
                Available Classes
            </h3>
            
            <?php if (empty($classes)): ?>
                <div class="text-center py-4 text-[#8d6a5e]">
                    <p class="text-sm">No classes available</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php 
                    $assigned_classes = array_column($assignments, 'class_name');
                    foreach ($classes as $class): 
                        $is_assigned = in_array($class['class_name'], $assigned_classes);
                    ?>
                    <div class="flex items-center justify-between p-3 border border-[#e7deda] rounded-lg <?php echo $is_assigned ? 'bg-green-50 border-green-200' : ''; ?>">
                        <div>
                            <p class="text-sm font-medium text-[#181210]">
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </p>
                            <p class="text-xs text-[#8d6a5e]">
                                <?php echo ucfirst($class['section']); ?> • 
                                <?php echo $class['level'] ? strtoupper($class['level']) : ''; ?> •
                                Room <?php echo $class['room_number'] ?: 'N/A'; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <?php if ($is_assigned): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <span class="material-symbols-outlined text-xs mr-1">check</span>
                                    Assigned
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <span class="material-symbols-outlined text-xs mr-1">person</span>
                                    Needs Teacher
                                </span>
                            <?php endif; ?>
                            <p class="text-xs text-[#8d6a5e] mt-1">
                                <?php echo $class['current_students']; ?>/<?php echo $class['capacity']; ?> students
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">analytics</span>
                Assignment Overview
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Assignment Rate:</span>
                    <span class="font-medium text-[#181210]">
                        <?php echo $total_classes > 0 ? round(($classes_with_teachers / $total_classes) * 100, 1) : 0; ?>%
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Available Teachers:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($teachers); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Available Subjects:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($subjects); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Academic Year:</span>
                    <span class="font-medium text-[#181210]"><?php echo $academic_year; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Teacher Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="assignTeacherModal">
    <div class="bg-white rounded-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Assign Teacher to Class</h3>
                <button onclick="closeAssignTeacherModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="flex-1 overflow-y-auto p-4">
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Class</label>
                        <select name="class_name" id="classSelect" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="">Choose a class...</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['class_name']); ?>" data-section="<?php echo $class['section']; ?>">
                                    <?php echo htmlspecialchars($class['class_name']); ?> (<?php echo ucfirst($class['section']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="section" id="classSection">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Academic Year</label>
                        <input type="text" name="academic_year" value="<?php echo $academic_year; ?>" required
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Teacher</label>
                    <select name="staff_id" required
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Choose a teacher...</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                (<?php echo htmlspecialchars($teacher['staff_id']); ?>)
                                <?php if ($teacher['department']): ?>
                                    - <?php echo htmlspecialchars($teacher['department']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Assign Subjects (Optional)</label>
                    <div class="border border-[#e7deda] rounded-lg p-3 max-h-48 overflow-y-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <?php foreach ($subjects as $subject): ?>
                            <label class="flex items-center p-2 hover:bg-[#f8f6f5] rounded cursor-pointer">
                                <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>" 
                                       class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933] mr-2">
                                <span class="text-sm text-[#181210]">
                                    <?php echo htmlspecialchars($subject['subject_name']); ?>
                                    <span class="text-xs text-[#8d6a5e]">
                                        (<?php echo strtoupper($subject['level']); ?>)
                                    </span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="text-xs text-[#8d6a5e] mt-1">Select subjects this teacher will teach for this class</p>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeAssignTeacherModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="assign_teacher"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Assign Teacher
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAssignTeacherModal() {
        document.getElementById('assignTeacherModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAssignTeacherModal() {
        document.getElementById('assignTeacherModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Update section when class is selected
    document.getElementById('classSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const section = selectedOption.getAttribute('data-section');
        document.getElementById('classSection').value = section;
    });

    // Close modal when clicking outside
    document.getElementById('assignTeacherModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeAssignTeacherModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAssignTeacherModal();
        }
    });
</script>