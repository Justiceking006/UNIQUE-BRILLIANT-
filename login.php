<?php
session_start();
require_once 'connect.php';

$status_message = '';
$status_type = '';

// Check admission status (students only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_status'])) {
    $student_code = sanitize($_POST['student_code']);
    
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT status, student_pin FROM students WHERE student_code = ?");
    $stmt->execute([$student_code]);
    $student = $stmt->fetch();
    
    if ($student) {
        switch ($student['status']) {
            case 'approved':
                $status_message = "Approved! Your Student PIN: <strong>" . $student['student_pin'] . "</strong>";
                $status_type = 'success';
                break;
            case 'pending':
                $status_message = "Your application is under review. Please check back later.";
                $status_type = 'info';
                break;
            case 'rejected':
                $status_message = "Application not approved. Please contact administration.";
                $status_type = 'error';
                break;
            default:
                $status_message = "Application status: " . $student['status'];
                $status_type = 'info';
        }
    } else {
        $status_message = "Invalid student code. Please check and try again.";
        $status_type = 'error';
    }
}

// Handle login for all user types (auto-detect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $student_pin = isset($_POST['student_pin']) ? sanitize($_POST['student_pin']) : '';
    
    $db = getDBConnection();
    
    // Auto-detect user type by checking all tables
    $stmt = $db->prepare("
        SELECT u.*, 
               s.student_pin, s.status as student_status, s.first_name as student_first_name, s.last_name as student_last_name,
               st.first_name as staff_first_name, st.last_name as staff_last_name, st.position as staff_position,
               CASE 
                   WHEN s.id IS NOT NULL THEN 'student'
                   WHEN st.id IS NOT NULL THEN 'staff' 
                   WHEN u.user_type = 'admin' THEN 'admin'
                   ELSE 'unknown'
               END as detected_type
        FROM users u 
        LEFT JOIN students s ON u.student_id = s.id 
        LEFT JOIN staff st ON u.staff_id = st.id 
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $detected_type = $user['detected_type'];
        
        // Handle different user types
        if ($detected_type === 'student') {
            // Student login - check status and PIN
            if ($user['student_status'] !== 'approved') {
                $status_message = "Your account is not yet approved. Status: " . $user['student_status'];
                $status_type = 'error';
            } elseif (empty($user['student_pin'])) {
                $status_message = "PIN not generated. Please contact administration.";
                $status_type = 'error';
            } elseif ($user['student_pin'] === $student_pin) {
                // Student login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['first_name'] = $user['student_first_name'];
                $_SESSION['last_name'] = $user['student_last_name'];
                $_SESSION['user_type'] = 'student';
                $_SESSION['logged_in'] = true;
                
                header('Location: portal.php');
                exit;
            } else {
                $status_message = "Invalid Student PIN.";
                $status_type = 'error';
            }
            
        } elseif ($detected_type === 'staff') {
            // Staff login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['staff_id'] = $user['staff_id'];
            $_SESSION['first_name'] = $user['staff_first_name'];
            $_SESSION['last_name'] = $user['staff_last_name'];
            $_SESSION['user_type'] = 'staff';
            $_SESSION['position'] = $user['staff_position'];
            $_SESSION['logged_in'] = true;
            
            header('Location: staff_portal.php');
            exit;
            
        } elseif ($detected_type === 'admin') {
            // Admin login successful
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['first_name'] = $user['staff_first_name'] ?? 'Administrator';
            $_SESSION['last_name'] = $user['staff_last_name'] ?? '';
            $_SESSION['user_type'] = 'admin';
            $_SESSION['logged_in'] = true;
            
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $status_message = "Unable to determine user type. Please contact administration.";
            $status_type = 'error';
        }
        
    } else {
        $status_message = "Invalid email or password.";
        $status_type = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Unique Brilliant Schools</title>
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
            <h1 class="text-center text-3xl font-bold leading-tight text-[#181210]">School Portal</h1>
            <p class="text-center text-[#8d6a5e] mt-2">Secure access to your educational resources</p>

            <!-- Status Check Section (Students Only) -->
            <div class="mt-8 bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                <h2 class="text-xl font-bold text-[#181210] mb-4">Check Admission Status</h2>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student Code</label>
                        <input type="text" name="student_code" required
                               class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Enter your student code">
                    </div>
                    
                    <button type="submit" name="check_status" 
                            class="w-full h-12 rounded-lg bg-[#ff6933] text-white text-base font-bold hover:bg-[#ff6933]/90 transition-colors">
                        Check Status
                    </button>
                </form>

                <!-- Status Message -->
                <?php if ($status_message && isset($_POST['check_status'])): ?>
                    <div class="mt-4 p-3 rounded-lg 
                        <?php echo $status_type === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : ''; ?>
                        <?php echo $status_type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : ''; ?>
                        <?php echo $status_type === 'info' ? 'bg-blue-100 border border-blue-400 text-blue-700' : ''; ?>">
                        <?php echo $status_message; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Login Form for All Users -->
            <div class="mt-6 bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                <h2 class="text-xl font-bold text-[#181210] mb-6">Sign In to Your Account</h2>
                
                <!-- Login Error Message -->
                <?php if ($status_message && isset($_POST['login'])): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                        <?php echo $status_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Email Address</label>
                        <input type="email" name="email" required
                               class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="your.email@school.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Password</label>
                        <input type="password" name="password" required
                               class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Enter your password">
                    </div>
                    
                    <!-- Student PIN Field (Shown by default, system will detect if needed) -->
                    <div id="student-pin-field">
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student PIN (If Applicable)</label>
                        <input type="text" name="student_pin"
                               class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Enter 6-digit PIN" maxlength="6">
                        <p class="text-xs text-[#8d6a5e] mt-2">
                            <span class="material-symbols-outlined align-middle text-sm">info</span>
                            Required for students only. Get your PIN after approval.
                        </p>
                    </div>
                    
                    <button type="submit" name="login" 
                            class="w-full h-12 rounded-lg bg-[#ff6933] text-white text-base font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                        <span class="material-symbols-outlined mr-2 text-lg">login</span>
                        Sign In
                    </button>
                </form>
            </div>

            <!-- Registration Link (Students Only) -->
            <div class="mt-6 text-center">
                <p class="text-sm text-[#8d6a5e]">
                    Student without an account? 
                    <a href="register.php" class="font-bold text-[#ff6933] hover:underline">Apply for Admission</a>
                </p>
            </div>

            <!-- Support Info -->
            <div class="mt-4 text-center">
                <p class="text-xs text-[#8d6a5e]">
                    Need help? Contact administration at 
                    <a href="mailto:support@brilliantschools.edu" class="text-[#ff6933] hover:underline">support@brilliantschools.edu</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const pinInput = document.querySelector('input[name="student_pin"]');
            
            // Auto-format student PIN input to numbers only
            pinInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 6) {
                    this.value = this.value.slice(0, 6);
                }
            });

            // Add loading state to login button
            const loginForm = document.querySelector('form[method="POST"]');
            const loginBtn = loginForm.querySelector('button[name="login"]');
            
            loginForm.addEventListener('submit', function() {
                loginBtn.disabled = true;
                loginBtn.innerHTML = '<span class="material-symbols-outlined mr-2 text-lg">lock_clock</span>Signing In...';
                loginBtn.classList.add('opacity-50');
            });
        });
    </script>
</body>
</html>