<?php
session_start();
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDBConnection();
    
    // Sanitize inputs
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $email = sanitize($_POST['email']);
    $section = sanitize($_POST['section']);
    $level = isset($_POST['level']) ? sanitize($_POST['level']) : null;
    $guardian_name = sanitize($_POST['guardian_name']);
    $guardian_phone = sanitize($_POST['guardian_phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Determine class based on section
    if ($section === 'primary') {
        $class = sanitize($_POST['class_primary']);
        $department = null;
    } else {
        // FIXED: Get the correct class based on level
        if ($level === 'jss') {
            $class = sanitize($_POST['class_jss']);
        } else {
            $class = sanitize($_POST['class_sss']);
        }
        $department = ($level === 'sss') ? sanitize($_POST['department']) : null;
    }
    
    // Validate passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        try {
            // Check if email already exists
            $stmt = $db->prepare("SELECT id FROM students WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Email already registered!";
            } else {
                // Generate unique student code AND PIN
                $student_code = $database->generateStudentCode();
                $student_pin = $database->generateStudentPIN();
                
                // Insert student WITH PIN
                $stmt = $db->prepare("
                    INSERT INTO students (first_name, last_name, email, section, level, class, department, guardian_name, guardian_phone, student_code, student_pin, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $stmt->execute([$first_name, $last_name, $email, $section, $level, $class, $department, $guardian_name, $guardian_phone, $student_code, $student_pin]);
                $student_id = $db->lastInsertId();
                
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (student_id, email, password, user_type) VALUES (?, ?, ?, 'student')");
                $stmt->execute([$student_id, $email, $hashed_password]);
                
                $_SESSION['student_code'] = $student_code;
                header('Location: registration_success.php');
                exit;
            }
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            $error = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Unique Brilliant Schools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Lexend', sans-serif; background-color: #f8f6f5; }
        .form-input:focus { border-color: #ff6933; --tw-ring-color: transparent; }
        .hidden { display: none; }
    </style>
</head>
<body class="min-h-screen bg-[#f8f6f5]">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="flex justify-center mb-8">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-[#ff6933]/20">
                    <span class="material-symbols-outlined text-4xl text-[#ff6933]">school</span>
                </div>
            </div>

            <!-- Headline -->
            <h1 class="text-center text-3xl font-bold leading-tight text-[#181210]">Create Your Student Account</h1>

            <!-- Error Message -->
            <?php if (isset($error)): ?>
                <div class="mt-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" class="mt-8 space-y-5" id="registrationForm">
                <!-- Personal Information -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">First Name *</label>
                        <input type="text" name="first_name" required 
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Jessica" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Last Name *</label>
                        <input type="text" name="last_name" required
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Smith" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student Email *</label>
                    <input type="email" name="email" required
                           class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="j.smith@brilliantschools.edu" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <!-- Section Selection -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Section *</label>
                    <div class="flex h-12 rounded-xl bg-gray-200 p-1">
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="section" value="primary" class="hidden peer" <?php echo (!isset($_POST['section']) || $_POST['section'] === 'primary') ? 'checked' : ''; ?>>
                            <span class="block h-full rounded-lg peer-checked:bg-white peer-checked:text-[#181210] text-gray-500 text-sm font-medium leading-normal flex items-center justify-center">Primary</span>
                        </label>
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="section" value="secondary" class="hidden peer" <?php echo (isset($_POST['section']) && $_POST['section'] === 'secondary') ? 'checked' : ''; ?>>
                            <span class="block h-full rounded-lg peer-checked:bg-white peer-checked:text-[#181210] text-gray-500 text-sm font-medium leading-normal flex items-center justify-center">Secondary</span>
                        </label>
                    </div>
                </div>

                <!-- Dynamic Academic Fields -->
                <div id="academic-fields">
                    <!-- Primary fields -->
                    <div id="primary-fields">
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Enter Class *</label>
                        <input type="text" name="class_primary" id="class_primary" required
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="e.g., Primary 4 or Nursery 2" value="<?php echo (isset($_POST['section']) && $_POST['section'] === 'primary' && isset($_POST['class_primary'])) ? htmlspecialchars($_POST['class_primary']) : ''; ?>">
                    </div>

                    <!-- Secondary fields -->
                    <div id="secondary-fields" class="hidden space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Level *</label>
                            <select name="level" class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                <option value="jss" <?php echo (isset($_POST['level']) && $_POST['level'] === 'jss') ? 'selected' : ''; ?>>Junior Secondary (JSS)</option>
                                <option value="sss" <?php echo (isset($_POST['level']) && $_POST['level'] === 'sss') ? 'selected' : ''; ?>>Senior Secondary (SSS)</option>
                            </select>
                        </div>

                        <!-- JSS Fields -->
                        <div id="jss-fields">
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Class *</label>
                            <select name="class_jss" class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                <option value="JSS1" <?php echo (isset($_POST['class_jss']) && $_POST['class_jss'] === 'JSS1') ? 'selected' : ''; ?>>JSS 1</option>
                                <option value="JSS2" <?php echo (isset($_POST['class_jss']) && $_POST['class_jss'] === 'JSS2') ? 'selected' : ''; ?>>JSS 2</option>
                                <option value="JSS3" <?php echo (isset($_POST['class_jss']) && $_POST['class_jss'] === 'JSS3') ? 'selected' : ''; ?>>JSS 3</option>
                            </select>
                        </div>

                        <!-- SSS Fields -->
                        <div id="sss-fields" class="hidden space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Class *</label>
                                <select name="class_sss" class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                    <option value="SSS1" <?php echo (isset($_POST['class_sss']) && $_POST['class_sss'] === 'SSS1') ? 'selected' : ''; ?>>SSS 1</option>
                                    <option value="SSS2" <?php echo (isset($_POST['class_sss']) && $_POST['class_sss'] === 'SSS2') ? 'selected' : ''; ?>>SSS 2</option>
                                    <option value="SSS3" <?php echo (isset($_POST['class_sss']) && $_POST['class_sss'] === 'SSS3') ? 'selected' : ''; ?>>SSS 3</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Department *</label>
                                <select name="department" class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                    <option value="science" <?php echo (isset($_POST['department']) && $_POST['department'] === 'science') ? 'selected' : ''; ?>>Science</option>
                                    <option value="art" <?php echo (isset($_POST['department']) && $_POST['department'] === 'art') ? 'selected' : ''; ?>>Art</option>
                                    <option value="commercial" <?php echo (isset($_POST['department']) && $_POST['department'] === 'commercial') ? 'selected' : ''; ?>>Commercial</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guardian Information -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Name *</label>
                        <input type="text" name="guardian_name" required
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Parent/Guardian Name" value="<?php echo isset($_POST['guardian_name']) ? htmlspecialchars($_POST['guardian_name']) : ''; ?>">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Phone *</label>
                        <input type="tel" name="guardian_phone" required
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="+234 XXX XXX XXXX" value="<?php echo isset($_POST['guardian_phone']) ? htmlspecialchars($_POST['guardian_phone']) : ''; ?>">
                    </div>
                </div>

                <!-- Password -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Password *</label>
                        <input type="password" name="password" required
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="••••••••">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Confirm Password *</label>
                        <input type="password" name="confirm_password" required
                               class="w-full h-14 rounded-lg border border-[#e7deda] bg-white p-4 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="••••••••">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submitBtn" class="w-full h-14 rounded-lg bg-[#ff6933] text-white text-base font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Create Account
                </button>
            </form>

            <!-- Login Link -->
            <div class="mt-8 text-center">
                <p class="text-sm text-[#8d6a5e]">
                    Already have an account? 
                    <a href="login.php" class="font-bold text-[#ff6933] hover:underline">Log In</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sectionRadios = document.querySelectorAll('input[name="section"]');
            const primaryFields = document.getElementById('primary-fields');
            const secondaryFields = document.getElementById('secondary-fields');
            const levelSelect = document.querySelector('select[name="level"]');
            const jssFields = document.getElementById('jss-fields');
            const sssFields = document.getElementById('sss-fields');
            const form = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');

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

            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = '#ef4444';
                    return false;
                } else {
                    confirmPassword.style.borderColor = '#e7deda';
                    return true;
                }
            }

            sectionRadios.forEach(radio => {
                radio.addEventListener('change', updateFormFields);
            });

            if (levelSelect) {
                levelSelect.addEventListener('change', updateSecondaryFields);
            }

            password.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);

            form.addEventListener('submit', function(e) {
                if (!validatePassword()) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating Account...';
                submitBtn.classList.add('opacity-50');

                return true;
            });

            updateFormFields();
        });
    </script>
</body>
</html>