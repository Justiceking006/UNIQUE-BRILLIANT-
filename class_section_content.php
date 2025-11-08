<?php
// class_section_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle class creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_class'])) {
    $class_name = $_POST['class_name'];
    $section = $_POST['section'];
    $level = $_POST['level'];
    $capacity = intval($_POST['capacity']);
    $room_number = $_POST['room_number'];
    $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;
    
    try {
        $stmt = $db->prepare("INSERT INTO academic_classes (class_name, section, level, capacity, room_number, class_teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$class_name, $section, $level, $capacity, $room_number, $class_teacher_id])) {
            $success_message = "Class created successfully!";
        } else {
            $error_message = "Failed to create class. Please try again.";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'unique_class') !== false) {
            $error_message = "A class with this name and section already exists.";
        } else {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle class update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_class'])) {
    $class_id = intval($_POST['class_id']);
    $class_name = $_POST['class_name'];
    $section = $_POST['section'];
    $level = $_POST['level'];
    $capacity = intval($_POST['capacity']);
    $room_number = $_POST['room_number'];
    $class_teacher_id = !empty($_POST['class_teacher_id']) ? intval($_POST['class_teacher_id']) : null;
    
    try {
        $stmt = $db->prepare("UPDATE academic_classes SET class_name = ?, section = ?, level = ?, capacity = ?, room_number = ?, class_teacher_id = ? WHERE id = ?");
        
        if ($stmt->execute([$class_name, $section, $level, $capacity, $room_number, $class_teacher_id, $class_id])) {
            $success_message = "Class updated successfully!";
        } else {
            $error_message = "Failed to update class. Please try again.";
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'unique_class') !== false) {
            $error_message = "A class with this name and section already exists.";
        } else {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Handle class deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
    $class_id = intval($_POST['class_id']);
    
    try {
        $stmt = $db->prepare("DELETE FROM academic_classes WHERE id = ?");
        
        if ($stmt->execute([$class_id])) {
            $success_message = "Class deleted successfully!";
        } else {
            $error_message = "Failed to delete class. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle class status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $class_id = intval($_POST['class_id']);
    $current_status = $_POST['current_status'];
    $new_status = $current_status === 'active' ? 'inactive' : 'active';
    
    $stmt = $db->prepare("UPDATE academic_classes SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $class_id])) {
        $success_message = "Class status updated!";
    } else {
        $error_message = "Failed to update class status.";
    }
}

// Get all classes with student count
$classes = $db->query("
    SELECT ac.*, s.first_name, s.last_name, s.staff_id 
    FROM academic_classes ac 
    LEFT JOIN staff s ON ac.class_teacher_id = s.id 
    ORDER BY ac.section, ac.class_name
")->fetchAll();

// Get active teachers for dropdown
$teachers = $db->query("SELECT id, first_name, last_name, staff_id FROM staff WHERE status = 'active' ORDER BY first_name, last_name")->fetchAll();

// Get statistics
$total_classes = count($classes);
$primary_classes = $db->query("SELECT COUNT(*) FROM academic_classes WHERE section = 'primary' AND status = 'active'")->fetchColumn();
$secondary_classes = $db->query("SELECT COUNT(*) FROM academic_classes WHERE section = 'secondary' AND status = 'active'")->fetchColumn();
$total_capacity = $db->query("SELECT SUM(capacity) FROM academic_classes WHERE status = 'active'")->fetchColumn();
$total_students = $db->query("SELECT COUNT(*) FROM students WHERE status = 'approved'")->fetchColumn();
?>

<!-- Class & Section Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Class & Section Management</h1>
            <p class="text-[#8d6a5e]">Manage primary and secondary classes, capacity, and assignments</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <button onclick="openCreateClassModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">add</span>
                Create New Class
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
                <p class="text-sm text-[#8d6a5e]">Primary Classes</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $primary_classes; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">child_care</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Secondary Classes</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $secondary_classes; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">school</span>
        </div>
    </div>
    
    <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-[#8d6a5e]">Total Capacity</p>
                <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_capacity; ?></p>
            </div>
            <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">groups</span>
        </div>
    </div>
</section>

<!-- Classes Grid -->
<section>
    <?php if (empty($classes)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-xl p-8 text-center shadow-sm border border-[#e7deda]">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">class</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Classes Created</h3>
            <p class="text-[#8d6a5e] mb-6">Get started by creating your first class.</p>
            <button onclick="openCreateClassModal()"
                    class="inline-flex items-center h-12 px-6 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined mr-2">add</span>
                Create First Class
            </button>
        </div>
    <?php else: ?>
        <!-- Classes Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($classes as $class): ?>
            <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] hover:shadow-md transition-shadow">
                <!-- Class Header -->
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-[#181210]"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                            <?php echo $class['section'] === 'primary' ? 'bg-orange-100 text-orange-800' : 'bg-purple-100 text-purple-800'; ?>">
                            <span class="material-symbols-outlined text-xs mr-1">
                                <?php echo $class['section'] === 'primary' ? 'child_care' : 'school'; ?>
                            </span>
                            <?php echo ucfirst($class['section']); ?> Section
                        </span>
                    </div>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                        <?php echo $class['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo ucfirst($class['status']); ?>
                    </span>
                </div>

                <!-- Class Details -->
                <div class="space-y-3 text-sm text-[#8d6a5e] mb-4">
                    <div class="flex justify-between">
                        <span>Level:</span>
                        <span class="font-medium text-[#181210]">
                            <?php echo $class['level'] ? strtoupper($class['level']) : 'Not set'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Capacity:</span>
                        <span class="font-medium text-[#181210]">
                            <?php echo $class['current_students']; ?>/<?php echo $class['capacity']; ?> students
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Room:</span>
                        <span class="font-medium text-[#181210]">
                            <?php echo $class['room_number'] ?: 'Not assigned'; ?>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span>Class Teacher:</span>
                        <span class="font-medium text-[#181210] text-right">
                            <?php if ($class['first_name']): ?>
                                <?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?>
                                <br>
                                <span class="text-xs text-[#8d6a5e]"><?php echo htmlspecialchars($class['staff_id']); ?></span>
                            <?php else: ?>
                                <span class="text-red-500 text-xs">Not assigned</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="mb-4">
                    <div class="flex justify-between text-xs text-[#8d6a5e] mb-1">
                        <span>Capacity Usage</span>
                        <span><?php echo round(($class['current_students'] / $class['capacity']) * 100, 1); ?>%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-[#ff6933] h-2 rounded-full" 
                             style="width: <?php echo min(100, ($class['current_students'] / $class['capacity']) * 100); ?>%"></div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-between gap-2 border-t border-[#e7deda] pt-4">
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                        <input type="hidden" name="current_status" value="<?php echo $class['status']; ?>">
                        <button type="submit" name="toggle_status" 
                                class="w-full flex items-center justify-center p-2 text-sm 
                                    <?php echo $class['status'] === 'active' ? 'text-orange-600 hover:bg-orange-50' : 'text-green-600 hover:bg-green-50'; ?> 
                                    rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-base mr-1">
                                <?php echo $class['status'] === 'active' ? 'pause_circle' : 'play_arrow'; ?>
                            </span>
                            <?php echo $class['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                        </button>
                    </form>
                    
                    <button onclick="editClass(<?php echo $class['id']; ?>)" 
                            class="flex-1 flex items-center justify-center p-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-base mr-1">edit</span>
                        Edit
                    </button>
                    
                    <button onclick="confirmDelete(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')" 
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

<!-- Create Class Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="createClassModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Create New Class</h3>
                <button onclick="closeCreateClassModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Name</label>
                    <input type="text" name="class_name" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="e.g., JSS1A, Primary 4B">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Section</label>
                        <select name="section" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Level</label>
                        <select name="level"
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="">Select Level</option>
                            <option value="nursery">Nursery</option>
                            <option value="primary">Primary</option>
                            <option value="jss">JSS</option>
                            <option value="sss">SSS</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Capacity</label>
                        <input type="number" name="capacity" required min="1" max="100" value="30"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Room Number</label>
                        <input type="text" name="room_number"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Room 101">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Teacher (Optional)</label>
                    <select name="class_teacher_id"
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">No teacher assigned</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                (<?php echo htmlspecialchars($teacher['staff_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeCreateClassModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="create_class"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Create Class
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="editClassModal">
    <div class="bg-white rounded-xl w-full max-w-md mx-4">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Edit Class</h3>
                <button onclick="closeEditClassModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4">
            <input type="hidden" name="class_id" id="editClassId">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Name</label>
                    <input type="text" name="class_name" id="editClassName" required
                           class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="e.g., JSS1A, Primary 4B">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Section</label>
                        <select name="section" id="editSection" required
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="primary">Primary</option>
                            <option value="secondary">Secondary</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Level</label>
                        <select name="level" id="editLevel"
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="">Select Level</option>
                            <option value="nursery">Nursery</option>
                            <option value="primary">Primary</option>
                            <option value="jss">JSS</option>
                            <option value="sss">SSS</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Capacity</label>
                        <input type="number" name="capacity" id="editCapacity" required min="1" max="100"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Room Number</label>
                        <input type="text" name="room_number" id="editRoomNumber"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Room 101">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class Teacher (Optional)</label>
                    <select name="class_teacher_id" id="editClassTeacher"
                            class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">No teacher assigned</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                (<?php echo htmlspecialchars($teacher['staff_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Current Status Display -->
                <div class="bg-[#f8f6f5] rounded-lg p-3">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Current Status</label>
                    <span id="editStatusDisplay" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"></span>
                    <p class="text-xs text-[#8d6a5e] mt-1">Use the toggle button on the class card to change status</p>
                </div>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button type="button" onclick="closeEditClassModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="update_class"
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Update Class
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
            <h3 class="text-xl font-bold text-[#181210] mb-2">Delete Class</h3>
            <p id="deleteMessage" class="text-[#8d6a5e] mb-6">Are you sure you want to delete this class?</p>
            <div class="flex space-x-3 w-full">
                <button onclick="closeDeleteModal()" class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <form method="POST" id="deleteForm" class="flex-1">
                    <input type="hidden" name="class_id" id="deleteClassId">
                    <button type="submit" name="delete_class" class="w-full h-12 rounded-lg bg-red-600 text-white font-bold hover:bg-red-700 transition-colors">
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Class data for editing
    const classData = <?php echo json_encode($classes); ?>;

    function openCreateClassModal() {
        document.getElementById('createClassModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateClassModal() {
        document.getElementById('createClassModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function openEditClassModal() {
        document.getElementById('editClassModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeEditClassModal() {
        document.getElementById('editClassModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function editClass(classId) {
        const classInfo = classData.find(cls => cls.id == classId);
        
        if (classInfo) {
            // Populate form fields
            document.getElementById('editClassId').value = classInfo.id;
            document.getElementById('editClassName').value = classInfo.class_name;
            document.getElementById('editSection').value = classInfo.section;
            document.getElementById('editLevel').value = classInfo.level || '';
            document.getElementById('editCapacity').value = classInfo.capacity;
            document.getElementById('editRoomNumber').value = classInfo.room_number || '';
            document.getElementById('editClassTeacher').value = classInfo.class_teacher_id || '';
            
            // Update status display
            const statusDisplay = document.getElementById('editStatusDisplay');
            statusDisplay.textContent = classInfo.status.charAt(0).toUpperCase() + classInfo.status.slice(1);
            statusDisplay.className = `inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                classInfo.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
            }`;
            
            openEditClassModal();
        }
    }

    function confirmDelete(classId, className) {
        document.getElementById('deleteClassId').value = classId;
        document.getElementById('deleteMessage').innerHTML = 
            `Are you sure you want to delete <strong>${className}</strong>? This action cannot be undone.`;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }

    // Close modals when clicking outside
    document.getElementById('createClassModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeCreateClassModal();
        }
    });

    document.getElementById('editClassModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditClassModal();
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
            closeCreateClassModal();
            closeEditClassModal();
            closeDeleteModal();
        }
    });

    // Auto-close modals on successful form submission
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    closeCreateClassModal();
                    closeEditClassModal();
                    closeDeleteModal();
                }, 1000);
            });
        });
    });
</script>