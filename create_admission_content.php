<?php
// create_admission_content.php - Manual Student Registration for Admins
session_start();
require_once 'connect.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$success_message = '';
$error_message = '';
$student_data = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_admission'])) {
    try {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'section', 'guardian_name', 'guardian_phone'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Sanitize inputs
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $section = sanitize($_POST['section']);
        $level = isset($_POST['level']) ? sanitize($_POST['level']) : null;
        $guardian_name = sanitize($_POST['guardian_name']);
        $guardian_phone = sanitize($_POST['guardian_phone']);
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Validate phone format (basic validation)
        if (!preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $guardian_phone)) {
            throw new Exception("Please enter a valid phone number.");
        }

        // Determine class based on section
        if ($section === 'primary') {
            if (empty($_POST['class_primary'])) {
                throw new Exception("Please enter class for primary section.");
            }
            $class = sanitize($_POST['class_primary']);
            $department = null;
        } else {
            if (empty($level)) {
                throw new Exception("Please select level for secondary section.");
            }
            
            if ($level === 'jss') {
                if (empty($_POST['class_jss'])) {
                    throw new Exception("Please select class for JSS level.");
                }
                $class = sanitize($_POST['class_jss']);
            } else {
                if (empty($_POST['class_sss'])) {
                    throw new Exception("Please select class for SSS level.");
                }
                if (empty($_POST['department'])) {
                    throw new Exception("Please select department for SSS level.");
                }
                $class = sanitize($_POST['class_sss']);
                $department = sanitize($_POST['department']);
            }
        }
        
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM students WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Email already registered! Please use a different email address.");
        }

        // Start transaction for data consistency
        $db->beginTransaction();

        try {
            // Generate unique student code and PIN
            $student_code = 'UBS' . date('Y') . strtoupper(bin2hex(random_bytes(4)));
            $student_pin = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
            
            // Insert student with immediate approval
            $stmt = $db->prepare("
                INSERT INTO students (first_name, last_name, email, section, level, class, department, guardian_name, guardian_phone, student_code, student_pin, status, approved_at, approved_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', NOW(), ?)
            ");
            
            $stmt->execute([
                $first_name, $last_name, $email, $section, $level, $class, $department, 
                $guardian_name, $guardian_phone, $student_code, $student_pin, $_SESSION['user_id']
            ]);
            $student_id = $db->lastInsertId();
            
            // Create user account
            $default_password = password_hash('password123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (student_id, email, password, user_type) VALUES (?, ?, ?, 'student')");
            $stmt->execute([$student_id, $email, $default_password]);
            
            // Commit transaction
            $db->commit();
            
            // Store success data for modal
            $student_data = [
                'code' => $student_code,
                'pin' => $student_pin,
                'name' => $first_name . ' ' . $last_name,
                'email' => $email,
                'class' => $class
            ];
            
            // Clear form fields
            $_POST = array();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Database error in create_admission: " . $e->getMessage());
        $error_message = "A database error occurred. Please try again.";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!-- Success Modal -->
<?php if ($student_data): ?>
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6 animate-fade-in">
        <div class="text-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Student Created Successfully!</h3>
            <p class="text-gray-600 mb-6">Student account has been created and approved.</p>
            
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Name:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($student_data['name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Email:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($student_data['email']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Class:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($student_data['class']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Student Code:</span>
                        <span class="font-medium text-blue-600"><?php echo htmlspecialchars($student_data['code']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">PIN:</span>
                        <span class="font-medium text-blue-600"><?php echo htmlspecialchars($student_data['pin']); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-6">
                <p class="text-sm text-yellow-800 text-center">
                    <strong>Important:</strong> Default password is <strong>password123</strong><br>
                    Student should change this after first login
                </p>
            </div>
            
            <button onclick="closeSuccessModal()" 
                    class="w-full bg-green-600 text-white py-3 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                Continue
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Error Modal -->
<?php if ($error_message): ?>
<div id="errorModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl max-w-md w-full p-6 animate-fade-in">
        <div class="text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Registration Failed</h3>
            <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($error_message); ?></p>
            
            <div class="flex gap-3">
                <button onclick="closeErrorModal()" 
                        class="flex-1 bg-gray-500 text-white py-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                    Try Again
                </button>
                <button onclick="closeErrorModalAndReset()" 
                        class="flex-1 bg-red-600 text-white py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors">
                    Clear Form
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-xl p-6 flex items-center space-x-4">
        <div class="w-8 h-8 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin"></div>
        <div class="text-gray-700 font-medium">Creating student account...</div>
    </div>
</div>

<!-- Page Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210] mb-2">Create Student Admission</h1>
    <p class="text-[#8d6a5e]">Manually register new students into the system</p>
</div>

<!-- Registration Form -->
<div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
    <form method="POST" class="space-y-6" id="admissionForm" onsubmit="return validateForm()">
        <!-- Personal Information -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">person</span>
                Personal Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">First Name *</label>
                    <input type="text" name="first_name" required 
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter first name" 
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Last Name *</label>
                    <input type="text" name="last_name" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter last name"
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Email Address *</label>
                    <input type="email" name="email" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="student@school.com"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">school</span>
                Academic Information
            </h3>
            
            <!-- Section Selection -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Section *</label>
                <div class="flex h-12 rounded-xl bg-gray-200 p-1">
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="section" value="primary" class="hidden peer" 
                               <?php echo (!isset($_POST['section']) || $_POST['section'] === 'primary') ? 'checked' : ''; ?>>
                        <span class="block h-full rounded-lg peer-checked:bg-white peer-checked:text-[#181210] text-gray-500 text-sm font-medium leading-normal flex items-center justify-center">Primary</span>
                    </label>
                    <label class="flex-1 text-center cursor-pointer">
                        <input type="radio" name="section" value="secondary" class="hidden peer"
                               <?php echo (isset($_POST['section']) && $_POST['section'] === 'secondary') ? 'checked' : ''; ?>>
                        <span class="block h-full rounded-lg peer-checked:bg-white peer-checked:text-[#181210] text-gray-500 text-sm font-medium leading-normal flex items-center justify-center">Secondary</span>
                    </label>
                </div>
            </div>

            <!-- Dynamic Academic Fields -->
            <div id="academic-fields" class="space-y-4">
                <!-- Primary fields -->
                <div id="primary-fields">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Enter Class *</label>
                    <input type="text" name="class_primary" id="class_primary" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="e.g., Primary 4 or Nursery 2"
                           value="<?php echo (isset($_POST['section']) && $_POST['section'] === 'primary' && isset($_POST['class_primary'])) ? htmlspecialchars($_POST['class_primary']) : ''; ?>">
                </div>

                <!-- Secondary fields -->
                <div id="secondary-fields" class="hidden space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Level *</label>
                        <select name="level" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="jss" <?php echo (isset($_POST['level']) && $_POST['level'] === 'jss') ? 'selected' : ''; ?>>Junior Secondary (JSS)</option>
                            <option value="sss" <?php echo (isset($_POST['level']) && $_POST['level'] === 'sss') ? 'selected' : ''; ?>>Senior Secondary (SSS)</option>
                        </select>
                    </div>

                    <!-- JSS Fields -->
                    <div id="jss-fields">
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Class *</label>
                        <select name="class_jss" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="JSS1" <?php echo (isset($_POST['class_jss']) && $_POST['class_jss'] === 'JSS1') ? 'selected' : ''; ?>>JSS 1</option>
                            <option value="JSS2" <?php echo (isset($_POST['class_jss']) && $_POST['class_jss'] === 'JSS2') ? 'selected' : ''; ?>>JSS 2</option>
                            <option value="JSS3" <?php echo (isset($_POST['class_jss']) && $_POST['class_jss'] === 'JSS3') ? 'selected' : ''; ?>>JSS 3</option>
                        </select>
                    </div>

                    <!-- SSS Fields -->
                    <div id="sss-fields" class="hidden space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Class *</label>
                            <select name="class_sss" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                <option value="SSS1" <?php echo (isset($_POST['class_sss']) && $_POST['class_sss'] === 'SSS1') ? 'selected' : ''; ?>>SSS 1</option>
                                <option value="SSS2" <?php echo (isset($_POST['class_sss']) && $_POST['class_sss'] === 'SSS2') ? 'selected' : ''; ?>>SSS 2</option>
                                <option value="SSS3" <?php echo (isset($_POST['class_sss']) && $_POST['class_sss'] === 'SSS3') ? 'selected' : ''; ?>>SSS 3</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Department *</label>
                            <select name="department" class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                <option value="science" <?php echo (isset($_POST['department']) && $_POST['department'] === 'science') ? 'selected' : ''; ?>>Science</option>
                                <option value="art" <?php echo (isset($_POST['department']) && $_POST['department'] === 'art') ? 'selected' : ''; ?>>Art</option>
                                <option value="commercial" <?php echo (isset($_POST['department']) && $_POST['department'] === 'commercial') ? 'selected' : ''; ?>>Commercial</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Guardian Information -->
        <div>
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">family_restroom</span>
                Guardian Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Name *</label>
                    <input type="text" name="guardian_name" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Parent/Guardian full name"
                           value="<?php echo isset($_POST['guardian_name']) ? htmlspecialchars($_POST['guardian_name']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Phone *</label>
                    <input type="tel" name="guardian_phone" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="+234 XXX XXX XXXX"
                           value="<?php echo isset($_POST['guardian_phone']) ? htmlspecialchars($_POST['guardian_phone']) : ''; ?>">
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex flex-col sm:flex-row gap-4 pt-4 border-t border-[#e7deda]">
            <button type="submit" name="create_admission" 
                    class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white text-base font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined mr-2">person_add</span>
                Create Student Account
            </button>
            <button type="reset" 
                    class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] text-base font-medium hover:bg-[#f8f6f5] transition-colors flex items-center justify-center">
                <span class="material-symbols-outlined mr-2">refresh</span>
                Clear Form
            </button>
        </div>

        <!-- Info Note -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div class="flex items-start space-x-2">
                <span class="material-symbols-outlined text-blue-500 text-lg">info</span>
                <div class="text-sm text-blue-700">
                    <p class="font-medium">Student will be automatically approved and can login immediately.</p>
                    <p class="mt-1">Default password: <strong>password123</strong> - Student should change this after first login.</p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sectionRadios = document.querySelectorAll('input[name="section"]');
        const primaryFields = document.getElementById('primary-fields');
        const secondaryFields = document.getElementById('secondary-fields');
        const levelSelect = document.querySelector('select[name="level"]');
        const jssFields = document.getElementById('jss-fields');
        const sssFields = document.getElementById('sss-fields');
        const form = document.getElementById('admissionForm');
        const loadingOverlay = document.getElementById('loadingOverlay');

        function updateFormFields() {
            const isPrimary = document.querySelector('input[name="section"]:checked').value === 'primary';
            
            if (isPrimary) {
                primaryFields.classList.remove('hidden');
                secondaryFields.classList.add('hidden');
                document.getElementById('class_primary').required = true;
            } else {
                primaryFields.classList.add('hidden');
                secondaryFields.classList.remove('hidden');
                document.getElementById('class_primary').required = false;
                updateSecondaryFields();
            }
        }

        function updateSecondaryFields() {
            const isSSS = levelSelect.value === 'sss';
            
            // Show/hide the correct class fields
            jssFields.classList.toggle('hidden', isSSS);
            sssFields.classList.toggle('hidden', !isSSS);
            
            // Update required fields
            if (isSSS) {
                document.querySelector('select[name="class_jss"]').required = false;
                document.querySelector('select[name="class_sss"]').required = true;
                document.querySelector('select[name="department"]').required = true;
            } else {
                document.querySelector('select[name="class_jss"]').required = true;
                document.querySelector('select[name="class_sss"]').required = false;
                document.querySelector('select[name="department"]').required = false;
            }
        }

        // Event listeners
        sectionRadios.forEach(radio => {
            radio.addEventListener('change', updateFormFields);
        });

        if (levelSelect) {
            levelSelect.addEventListener('change', updateSecondaryFields);
        }

        // Initialize form fields
        updateFormFields();

        // Form submission loading state
        form.addEventListener('submit', function() {
            if (validateForm()) {
                loadingOverlay.classList.remove('hidden');
            }
        });

        // Reset form handler
        form.addEventListener('reset', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<span class="material-symbols-outlined mr-2">person_add</span>Create Student Account';
            submitBtn.classList.remove('opacity-50');
            updateFormFields();
        });
    });

    function validateForm() {
        const form = document.getElementById('admissionForm');
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        // Clear previous errors
        form.querySelectorAll('.border-red-500').forEach(field => {
            field.classList.remove('border-red-500');
        });
        form.querySelectorAll('.error-message').forEach(error => error.remove());

        // Validate required fields
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                showFieldError(field, 'This field is required');
                isValid = false;
            }
        });

        // Validate email format
        const emailField = form.querySelector('input[name="email"]');
        if (emailField.value && !isValidEmail(emailField.value)) {
            showFieldError(emailField, 'Please enter a valid email address');
            isValid = false;
        }

        // Validate phone format
        const phoneField = form.querySelector('input[name="guardian_phone"]');
        if (phoneField.value && !isValidPhone(phoneField.value)) {
            showFieldError(phoneField, 'Please enter a valid phone number');
            isValid = false;
        }

        return isValid;
    }

    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    function isValidPhone(phone) {
        const phoneRegex = /^\+?[\d\s\-\(\)]{10,}$/;
        return phoneRegex.test(phone);
    }

    function showFieldError(field, message) {
        field.classList.add('border-red-500');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message text-red-500 text-sm mt-1';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
    }

    function closeSuccessModal() {
        document.getElementById('successModal').remove();
    }

    function closeErrorModal() {
        document.getElementById('errorModal').remove();
    }

    function closeErrorModalAndReset() {
        document.getElementById('errorModal').remove();
        document.getElementById('admissionForm').reset();
        document.querySelector('input[name="section"][value="primary"]').checked = true;
        updateFormFields();
    }
</script>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    
    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }
</style>