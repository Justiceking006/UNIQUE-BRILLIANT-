<?php
// add_staff_content.php
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$success_message = '';
$error_message = '';

// Define curriculum structure
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['first_name'])) {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'staff_id', 'department', 'position', 'phone'];
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field]))) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Sanitize inputs
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $staff_id = sanitize($_POST['staff_id']);
        $department = sanitize($_POST['department']);
        $position = sanitize($_POST['position']);
        $phone = sanitize($_POST['phone']);
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $address = !empty($_POST['address']) ? sanitize($_POST['address']) : null;
        $employment_date = !empty($_POST['employment_date']) ? $_POST['employment_date'] : date('Y-m-d');
        $qualifications = !empty($_POST['qualifications']) ? sanitize($_POST['qualifications']) : null;
        $emergency_contact = !empty($_POST['emergency_contact']) ? sanitize($_POST['emergency_contact']) : null;
        $gender = !empty($_POST['gender']) ? sanitize($_POST['gender']) : null;
        $state_of_origin = !empty($_POST['state_of_origin']) ? sanitize($_POST['state_of_origin']) : null;
        $marital_status = !empty($_POST['marital_status']) ? sanitize($_POST['marital_status']) : null;
        $religion = !empty($_POST['religion']) ? sanitize($_POST['religion']) : null;
        
        // Subject specialization (optional)
        $subject_specialization = isset($_POST['subject_specialization']) && is_array($_POST['subject_specialization']) ? 
            implode(', ', array_map(function($subject) { return sanitize($subject); }, $_POST['subject_specialization'])) : '';

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Check database connection
        if (!$db) {
            throw new Exception("Database connection failed. Please try again.");
        }

        // Check if email already exists
        $check_stmt = $db->prepare("SELECT id FROM staff WHERE email = ?");
        if (!$check_stmt->execute([$email])) {
            throw new Exception("Database error while checking email.");
        }
        
        if ($check_stmt->fetch()) {
            throw new Exception("Email already registered! Please use a different email.");
        }

        // Check if staff ID already exists
        $check_stmt = $db->prepare("SELECT id FROM staff WHERE staff_id = ?");
        if (!$check_stmt->execute([$staff_id])) {
            throw new Exception("Database error while checking staff ID.");
        }
        
        if ($check_stmt->fetch()) {
            throw new Exception("Staff ID already exists! Please generate a new one.");
        }

        // Start transaction
        $db->beginTransaction();

        // Insert into staff table
        $stmt = $db->prepare("
            INSERT INTO staff (
                first_name, last_name, email, staff_id, department, position, 
                phone, date_of_birth, address, employment_date, qualifications,
                emergency_contact, subject_specialization, gender, state_of_origin,
                marital_status, religion, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $execute_result = $stmt->execute([
            $first_name, $last_name, $email, $staff_id, $department, $position,
            $phone, $date_of_birth, $address, $employment_date, $qualifications,
            $emergency_contact, $subject_specialization, $gender, $state_of_origin,
            $marital_status, $religion
        ]);
        
        if (!$execute_result) {
            throw new Exception("Failed to insert staff record.");
        }
        
        $staff_id_inserted = $db->lastInsertId();

        // Generate default password
        $password = 'password123';
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Create user account
        $user_stmt = $db->prepare("
            INSERT INTO users (staff_id, email, password, user_type) 
            VALUES (?, ?, ?, 'staff')
        ");
        
        if (!$user_stmt->execute([$staff_id_inserted, $email, $hashed_password])) {
            throw new Exception("Failed to create user account.");
        }

        // Commit transaction
        $db->commit();

        $success_message = "Staff member added successfully! Staff ID: " . $staff_id . ". Default password: password123";
        
        // Clear form fields on success
        $_POST = array();

    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = "Database error occurred. Please try again.";
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error_message = $e->getMessage();
    }
}

function generateStaffID() {
    return 'STAFF' . date('Y') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}
?>

<!-- Add Staff Content -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210] mb-2">Add New Staff Member</h1>
    <p class="text-[#8d6a5e]">Register new teaching and non-teaching staff</p>
</div>

<!-- Status Modals -->
<?php if ($success_message): ?>
    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center animate-fade-in">
        <span class="material-symbols-outlined mr-2 text-green-500">check_circle</span>
        <div class="flex-1">
            <strong>Success!</strong> <?php echo $success_message; ?>
        </div>
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

<!-- Processing Modal -->
<div id="processingModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl p-8 max-w-md w-full mx-4">
        <div class="flex flex-col items-center">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-[#ff6933] mb-4"></div>
            <h3 class="text-xl font-bold text-[#181210] mb-2">Adding Staff Member</h3>
            <p class="text-[#8d6a5e] text-center">Please wait while we process your request...</p>
        </div>
    </div>
</div>

<!-- Staff Registration Form -->
<div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <form method="POST" class="space-y-6" id="staffForm">
        <!-- Personal Information Section -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">person</span>
                Personal Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">First Name *</label>
                    <input type="text" name="first_name" required 
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           placeholder="Enter first name"
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Last Name *</label>
                    <input type="text" name="last_name" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           placeholder="Enter last name"
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Gender</label>
                    <select name="gender" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Date of Birth</label>
                    <input type="date" name="date_of_birth"
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                </div>
            </div>
        </div>

        <!-- Contact Information Section -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">contact_mail</span>
                Contact Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Email Address *</label>
                    <input type="email" name="email" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           placeholder="staff@uniquebrilliant.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Phone Number *</label>
                    <input type="tel" name="phone" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           placeholder="+234 XXX XXX XXXX"
                           value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Emergency Contact</label>
                    <input type="tel" name="emergency_contact"
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           placeholder="Emergency contact number"
                           value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Address</label>
                    <textarea name="address" rows="3"
                              class="w-full rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                              placeholder="Enter full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Personal Details Section -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">badge</span>
                Personal Details
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">State of Origin</label>
                    <input type="text" name="state_of_origin"
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           placeholder="Enter state of origin"
                           value="<?php echo isset($_POST['state_of_origin']) ? htmlspecialchars($_POST['state_of_origin']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Marital Status</label>
                    <select name="marital_status" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors">
                        <option value="">Select Marital Status</option>
                        <option value="Single" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                        <option value="Married" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                        <option value="Divorced" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                        <option value="Widowed" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Religion</label>
                    <select name="religion" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors">
                        <option value="">Select Religion</option>
                        <option value="Christianity" <?php echo (isset($_POST['religion']) && $_POST['religion'] === 'Christianity') ? 'selected' : ''; ?>>Christianity</option>
                        <option value="Islam" <?php echo (isset($_POST['religion']) && $_POST['religion'] === 'Islam') ? 'selected' : ''; ?>>Islam</option>
                        <option value="Traditional" <?php echo (isset($_POST['religion']) && $_POST['religion'] === 'Traditional') ? 'selected' : ''; ?>>Traditional</option>
                        <option value="Other" <?php echo (isset($_POST['religion']) && $_POST['religion'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Employment Information Section -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">work</span>
                Employment Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Staff ID *</label>
                    <div class="flex space-x-2">
                        <input type="text" name="staff_id" required
                               class="flex-1 h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                               placeholder="e.g., STAFF2025001"
                               value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : generateStaffID(); ?>">
                        <button type="button" onclick="generateNewStaffID()" 
                                class="h-12 px-4 rounded-lg bg-[#ff6933]/10 text-[#ff6933] border border-[#ff6933] hover:bg-[#ff6933] hover:text-white transition-colors">
                            <span class="material-symbols-outlined text-sm">refresh</span>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Position *</label>
                    <select name="position" required
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors">
                        <option value="">Select Position</option>
                        <option value="Teacher" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Teacher') ? 'selected' : ''; ?>>Teacher</option>
                        <option value="Senior Teacher" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Senior Teacher') ? 'selected' : ''; ?>>Senior Teacher</option>
                        <option value="Head Teacher" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Head Teacher') ? 'selected' : ''; ?>>Head Teacher</option>
                        <option value="Principal" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Principal') ? 'selected' : ''; ?>>Principal</option>
                        <option value="Vice Principal" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Vice Principal') ? 'selected' : ''; ?>>Vice Principal</option>
                        <option value="Administrator" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Administrator') ? 'selected' : ''; ?>>Administrator</option>
                        <option value="Accountant" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Accountant') ? 'selected' : ''; ?>>Accountant</option>
                        <option value="Librarian" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Librarian') ? 'selected' : ''; ?>>Librarian</option>
                        <option value="Secretary" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Secretary') ? 'selected' : ''; ?>>Secretary</option>
                        <option value="Cleaner" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Cleaner') ? 'selected' : ''; ?>>Cleaner</option>
                        <option value="Security" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Security') ? 'selected' : ''; ?>>Security</option>
                        <option value="Other" <?php echo (isset($_POST['position']) && $_POST['position'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Department *</label>
                    <select name="department" required
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors">
                        <option value="">Select Department</option>
                        <option value="Administration" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Administration') ? 'selected' : ''; ?>>Administration</option>
                        <option value="Academic" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Academic') ? 'selected' : ''; ?>>Academic</option>
                        <option value="Mathematics" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Mathematics') ? 'selected' : ''; ?>>Mathematics</option>
                        <option value="Science" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Science') ? 'selected' : ''; ?>>Science</option>
                        <option value="English" <?php echo (isset($_POST['department']) && $_POST['department'] === 'English') ? 'selected' : ''; ?>>English</option>
                        <option value="Social Studies" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Social Studies') ? 'selected' : ''; ?>>Social Studies</option>
                        <option value="Arts" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Arts') ? 'selected' : ''; ?>>Arts</option>
                        <option value="Technology" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Technology') ? 'selected' : ''; ?>>Technology</option>
                        <option value="Physical Education" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Physical Education') ? 'selected' : ''; ?>>Physical Education</option>
                        <option value="Library" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Library') ? 'selected' : ''; ?>>Library</option>
                        <option value="Finance" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Finance') ? 'selected' : ''; ?>>Finance</option>
                        <option value="Maintenance" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="Other" <?php echo (isset($_POST['department']) && $_POST['department'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Employment Date</label>
                    <input type="date" name="employment_date"
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                           value="<?php echo isset($_POST['employment_date']) ? htmlspecialchars($_POST['employment_date']) : date('Y-m-d'); ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Qualifications</label>
                    <textarea name="qualifications" rows="3"
                              class="w-full rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933] transition-colors"
                              placeholder="List academic and professional qualifications"><?php echo isset($_POST['qualifications']) ? htmlspecialchars($_POST['qualifications']) : ''; ?></textarea>
                </div>
            </div>
        </div>

        <!-- Subject Specialization Section -->
        <div id="subject-section">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">subject</span>
                Subject Specialization (For Teachers)
            </h3>
            <p class="text-sm text-[#8d6a5e] mb-4">Select subjects this staff member can teach (multiple selection available)</p>
            
            <!-- Primary Subjects -->
            <div class="mb-6">
                <h4 class="font-bold text-[#181210] mb-3 text-sm">Primary School Subjects</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($subjects_by_level['primary'] as $subject): ?>
                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                   <?php echo (isset($_POST['subject_specialization']) && in_array($subject, $_POST['subject_specialization'])) ? 'checked' : ''; ?>>
                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- JSS Subjects -->
            <div class="mb-6">
                <h4 class="font-bold text-[#181210] mb-3 text-sm">Junior Secondary (JSS) Subjects</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($subjects_by_level['jss'] as $subject): ?>
                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                   <?php echo (isset($_POST['subject_specialization']) && in_array($subject, $_POST['subject_specialization'])) ? 'checked' : ''; ?>>
                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SSS Science Subjects -->
            <div class="mb-6">
                <h4 class="font-bold text-[#181210] mb-3 text-sm">Senior Secondary - Science</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($subjects_by_level['sss_science'] as $subject): ?>
                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                   <?php echo (isset($_POST['subject_specialization']) && in_array($subject, $_POST['subject_specialization'])) ? 'checked' : ''; ?>>
                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- SSS Art Subjects -->
            <div class="mb-6">
                <h4 class="font-bold text-[#181210] mb-3 text-sm">Senior Secondary - Art</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <?php foreach ($subjects_by_level['sss_art'] as $subject): ?>
                        <label class="flex items-center space-x-2 cursor-pointer hover:bg-[#f8f6f5] p-2 rounded transition-colors">
                            <input type="checkbox" name="subject_specialization[]" value="<?php echo htmlspecialchars($subject); ?>" 
                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                   <?php echo (isset($_POST['subject_specialization']) && in_array($subject, $_POST['subject_specialization'])) ? 'checked' : ''; ?>>
                            <span class="text-sm text-[#181210]"><?php echo $subject; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4 pt-4">
            <a href="?page=staff-list" class="h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">arrow_back</span>
                Staff List
            </a>
            <button type="submit" name="add_staff" id="submitBtn"
                    class="h-12 px-8 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center">
                <span class="material-symbols-outlined mr-2">person_add</span>
                Add Staff
            </button>
        </div>
    </form>
</div>

<script>
    function generateNewStaffID() {
        const staffIDInput = document.querySelector('input[name="staff_id"]');
        const newID = 'STAFF<?php echo date('Y'); ?>' + Math.floor(1000 + Math.random() * 9000);
        staffIDInput.value = newID;
    }

    function showProcessingModal() {
        const modal = document.getElementById('processingModal');
        const submitBtn = document.getElementById('submitBtn');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-symbols-outlined mr-2">pending</span>Processing...';
        modal.classList.remove('hidden');
    }

    // Form validation and submission
    document.getElementById('staffForm').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Check required fields
        const requiredFields = this.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.style.borderColor = '#ef4444';
                isValid = false;
            } else {
                field.style.borderColor = '#e7deda';
            }
        });
        
        // Validate email format
        const emailField = this.querySelector('input[name="email"]');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (emailField.value && !emailRegex.test(emailField.value)) {
            emailField.style.borderColor = '#ef4444';
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly.');
            return false;
        }
        
        // Show processing modal
        showProcessingModal();
        return true;
    });

    // Real-time validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('staffForm');
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#e7deda';
                }
            });
            
            input.addEventListener('input', function() {
                this.style.borderColor = '#e7deda';
            });
        });

        // Re-enable button if there was an error
        <?php if ($error_message): ?>
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="material-symbols-outlined mr-2">person_add</span>Add Staff';
            document.getElementById('processingModal').classList.add('hidden');
        <?php endif; ?>

        // Clear form on success
        <?php if ($success_message): ?>
            document.getElementById('staffForm').reset();
            document.getElementById('processingModal').classList.add('hidden');
        <?php endif; ?>
    });
</script>

<style>
    .animate-fade-in {
        animation: fadeIn 0.5s ease-in-out;
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