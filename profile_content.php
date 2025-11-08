<?php
// profile_content.php - Student Profile Management
session_start();
require_once 'connect.php';

// Check if user is student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$student_id = $_SESSION['student_id'];
$success_message = '';
$error_message = '';

// Get student details with user info
$stmt = $db->prepare("
    SELECT s.*, u.email 
    FROM students s 
    JOIN users u ON s.id = u.student_id 
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $error_message = "Student profile not found.";
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_password, $_SESSION['user_id']]);
                
                $success_message = "Password changed successfully!";
            } else {
                $error_message = "Password must be at least 6 characters long.";
            }
        } else {
            $error_message = "New passwords do not match.";
        }
    } else {
        $error_message = "Current password is incorrect.";
    }
}

// Calculate level display
$level_display = '';
if ($student['section'] === 'primary') {
    $level_display = $student['class'];
} else {
    if ($student['level'] === 'jss') {
        $level_display = 'JSS ' . substr($student['class'], -1);
    } else {
        $level_display = 'SSS ' . substr($student['class'], -1);
    }
}

// Calculate programme display
$programme_display = '';
if ($student['section'] === 'primary') {
    $programme_display = 'PRIMARY EDUCATION';
} else {
    if ($student['level'] === 'jss') {
        $programme_display = 'JUNIOR SECONDARY SCHOOL';
    } else {
        $programme_display = 'SENIOR SECONDARY SCHOOL - ' . strtoupper($student['department']) . ' DEPARTMENT';
    }
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210]">Student Profile</h1>
    <p class="text-[#8d6a5e]">Manage your personal information and account settings</p>
</div>

<!-- Success/Error Messages -->
<?php if (!empty($success_message)): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo $success_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="mb-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <p class="text-sm font-medium text-red-700"><?php echo $error_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-red-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Personal Information -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">person</span>
                Personal Information
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Student Details -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Full Name</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Email Address</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($student['email']); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student Code</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($student['student_code']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($student['student_pin']): ?>
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student PIN</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($student['student_pin']); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Academic Details -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Academic Level</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($level_display); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Programme</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($programme_display); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Section</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210] capitalize"><?php echo htmlspecialchars($student['section']); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Account Status</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium capitalize 
                                <?php echo $student['status'] === 'approved' ? 'text-green-600' : 'text-orange-600'; ?>">
                                <?php echo htmlspecialchars($student['status']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Guardian Information -->
            <div class="mt-6 pt-6 border-t border-[#e7deda]">
                <h4 class="text-md font-bold text-[#181210] mb-4 flex items-center">
                    <span class="material-symbols-outlined mr-2 text-blue-500">family_restroom</span>
                    Guardian Information
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Name</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($student['guardian_name']); ?></p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Guardian Phone</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]"><?php echo htmlspecialchars($student['guardian_phone']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Admission Information -->
            <div class="mt-6 pt-6 border-t border-[#e7deda]">
                <h4 class="text-md font-bold text-[#181210] mb-4 flex items-center">
                    <span class="material-symbols-outlined mr-2 text-purple-500">school</span>
                    Admission Information
                </h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Admission Date</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]">
                                <?php echo date('F j, Y', strtotime($student['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Admission Fee</label>
                        <div class="p-3 bg-[#f8f6f5] rounded-lg border border-[#e7deda]">
                            <p class="font-medium text-[#181210]">
                                <?php echo $student['admission_fee_paid'] ? 
                                    '<span class="text-green-600">Paid - ₦' . number_format($student['admission_fee_amount'], 2) . '</span>' : 
                                    '<span class="text-orange-600">Pending - ₦' . number_format($student['admission_fee_amount'], 2) . '</span>'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <?php if ($student['admission_receipt_image']): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Admission Receipt</label>
                    <a href="<?php echo htmlspecialchars($student['admission_receipt_image']); ?>" 
                       target="_blank" 
                       class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <span class="material-symbols-outlined text-sm">receipt</span>
                        View Admission Receipt
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Account Security -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">lock</span>
                Account Security
            </h3>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Current Password</label>
                    <input type="password" name="current_password" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter current password">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">New Password</label>
                    <input type="password" name="new_password" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Enter new password"
                           minlength="6">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" required
                           class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]"
                           placeholder="Confirm new password"
                           minlength="6">
                </div>
                
                <button type="submit" name="change_password" 
                        class="w-full h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">lock_reset</span>
                    Change Password
                </button>
            </form>
            
            <!-- Security Tips -->
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h4 class="font-bold text-blue-900 mb-2 flex items-center">
                    <span class="material-symbols-outlined mr-2 text-blue-600">security</span>
                    Security Tips
                </h4>
                <ul class="text-sm text-blue-800 space-y-1">
                    <li>• Use a strong, unique password</li>
                    <li>• Don't share your login details</li>
                    <li>• Log out after each session</li>
                    <li>• Keep your student PIN confidential</li>
                </ul>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mt-6">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">quick_reference</span>
                Quick Actions
            </h3>
            
            <div class="space-y-3">
                <a href="?page=fees" 
                   class="flex items-center justify-between p-3 rounded-lg border border-[#e7deda] hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <span class="material-symbols-outlined text-[#8d6a5e] group-hover:text-[#ff6933]">payments</span>
                        <span class="font-medium text-[#181210]">Manage Fees</span>
                    </div>
                    <span class="material-symbols-outlined text-[#8d6a5e] group-hover:text-[#ff6933]">chevron_right</span>
                </a>
                
                <a href="?page=academic" 
                   class="flex items-center justify-between p-3 rounded-lg border border-[#e7deda] hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <span class="material-symbols-outlined text-[#8d6a5e] group-hover:text-[#ff6933]">school</span>
                        <span class="font-medium text-[#181210]">Academic Info</span>
                    </div>
                    <span class="material-symbols-outlined text-[#8d6a5e] group-hover:text-[#ff6933]">chevron_right</span>
                </a>
                
                <a href="?page=results" 
                   class="flex items-center justify-between p-3 rounded-lg border border-[#e7deda] hover:border-[#ff6933] hover:bg-[#ff6933]/5 transition-colors group">
                    <div class="flex items-center space-x-3">
                        <span class="material-symbols-outlined text-[#8d6a5e] group-hover:text-[#ff6933]">grade</span>
                        <span class="font-medium text-[#181210]">View Results</span>
                    </div>
                    <span class="material-symbols-outlined text-[#8d6a5e] group-hover:text-[#ff6933]">chevron_right</span>
                </a>
            </div>
        </div>
        
        <!-- System Information -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mt-6">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">info</span>
                System Information
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Last Login</span>
                    <span class="font-medium text-[#181210]">
                        <?php echo isset($_SESSION['last_login']) ? 
                            date('M j, Y g:i A', strtotime($_SESSION['last_login'])) : 'First login'; ?>
                    </span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Account Created</span>
                    <span class="font-medium text-[#181210]">
                        <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                    </span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">User Type</span>
                    <span class="font-medium text-[#181210] capitalize">Student</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Session</span>
                    <span class="font-medium text-[#181210]">2024/2025</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-hide success messages after 5 seconds
setTimeout(() => {
    const successMessages = document.querySelectorAll('.bg-green-100');
    successMessages.forEach(msg => {
        msg.style.opacity = '0';
        msg.style.transition = 'opacity 0.5s ease';
        setTimeout(() => msg.remove(), 500);
    });
}, 5000);

// Password strength indicator (optional enhancement)
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.querySelector('input[name="new_password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    function checkPasswordMatch() {
        if (newPassword.value && confirmPassword.value) {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.style.borderColor = '#ef4444';
            } else {
                confirmPassword.style.borderColor = '#10b981';
            }
        }
    }
    
    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
    }
});
</script>