<?php
// settings_content.php
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

// Handle password reset actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['reset_student_password'])) {
        $student_id = intval($_POST['student_id']);
        $new_password = $_POST['new_password'];
        
        // Validate password
        if (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE student_id = ?");
            if ($stmt->execute([$hashed_password, $student_id])) {
                $success_message = "Student password reset successfully!";
            } else {
                $error_message = "Error resetting student password.";
            }
        }
        
    } elseif (isset($_POST['reset_staff_password'])) {
        $staff_id = intval($_POST['staff_id']);
        $new_password = $_POST['new_password'];
        
        // Validate password
        if (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE staff_id = ?");
            if ($stmt->execute([$hashed_password, $staff_id])) {
                $success_message = "Staff password reset successfully!";
            } else {
                $error_message = "Error resetting staff password.";
            }
        }
        
    } elseif (isset($_POST['reset_admin_password'])) {
        $admin_id = intval($_POST['admin_id']);
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ? AND user_type = 'admin'");
            if ($stmt->execute([$hashed_password, $admin_id])) {
                $success_message = "Admin password reset successfully!";
            } else {
                $error_message = "Error resetting admin password.";
            }
        }
    }
}

// Get all students for dropdown
$students_stmt = $db->query("SELECT s.id, s.first_name, s.last_name, s.student_code, s.email FROM students s WHERE s.status = 'approved' ORDER BY s.first_name, s.last_name");
$students = $students_stmt->fetchAll();

// Get all staff for dropdown
$staff_stmt = $db->query("SELECT s.id, s.first_name, s.last_name, s.staff_id, s.email FROM staff s WHERE s.status = 'active' ORDER BY s.first_name, s.last_name");
$staff_members = $staff_stmt->fetchAll();

// Get all admins for dropdown
$admins_stmt = $db->query("SELECT u.id, u.email FROM users u WHERE u.user_type = 'admin' ORDER BY u.id");
$admins = $admins_stmt->fetchAll();
?>

<!-- Headline Text -->
<h1 class="text-[32px] font-bold leading-tight tracking-tight text-[#181210]">Password Management</h1>

<!-- Body Text -->
<p class="text-base font-normal leading-normal text-[#8d6a5e] pt-1">Reset passwords for students, staff, and admin accounts</p>

<!-- Success/Error Messages -->
<?php if ($success_message): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo $success_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<?php if ($error_message): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <p class="text-sm font-medium text-red-700"><?php echo $error_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-red-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<!-- Password Reset Sections -->
<div class="mt-6 space-y-6">

    <!-- Reset Student Password -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#ff6933]">school</span>
            Reset Student Password
        </h3>
        
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Student</label>
                    <select name="student_id" required class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]">
                        <option value="">Choose a student...</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['id']; ?>">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' - ' . $student['student_code']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">New Password</label>
                    <input type="password" name="new_password" required 
                           minlength="6"
                           class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]"
                           placeholder="Enter new password (min 6 characters)">
                </div>
            </div>
            
            <button type="submit" name="reset_student_password" 
                    class="flex items-center justify-center gap-2 px-6 py-3 bg-[#ff6933] text-white rounded-lg font-medium hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined">lock_reset</span>
                Reset Student Password
            </button>
        </form>
    </div>

    <!-- Reset Staff Password -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#ff6933]">badge</span>
            Reset Staff Password
        </h3>
        
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Staff</label>
                    <select name="staff_id" required class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]">
                        <option value="">Choose a staff member...</option>
                        <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>">
                                <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name'] . ' - ' . $staff['staff_id']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">New Password</label>
                    <input type="password" name="new_password" required 
                           minlength="6"
                           class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]"
                           placeholder="Enter new password (min 6 characters)">
                </div>
            </div>
            
            <button type="submit" name="reset_staff_password" 
                    class="flex items-center justify-center gap-2 px-6 py-3 bg-[#ff6933] text-white rounded-lg font-medium hover:bg-[#ff6933]/90 transition-colors">
                <span class="material-symbols-outlined">lock_reset</span>
                Reset Staff Password
            </button>
        </form>
    </div>

    <!-- Reset Admin Password -->
    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
        <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[#ff6933]">admin_panel_settings</span>
            Reset Admin Password
        </h3>
        
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Admin</label>
                    <select name="admin_id" required class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]">
                        <option value="">Choose an admin...</option>
                        <?php foreach ($admins as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>">
                                <?php echo htmlspecialchars($admin['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">New Password</label>
                    <input type="password" name="new_password" required 
                           minlength="6"
                           class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]"
                           placeholder="Enter new password">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" required 
                           minlength="6"
                           class="w-full p-3 rounded-lg border border-[#e7deda] bg-white text-[#181210] focus:border-[#ff6933] focus:ring-1 focus:ring-[#ff6933]"
                           placeholder="Confirm new password">
                </div>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-yellow-600 text-xl mt-0.5">warning</span>
                    <div>
                        <p class="text-sm font-medium text-yellow-800">Security Notice</p>
                        <p class="text-sm text-yellow-700 mt-1">Admin password changes require extra confirmation for security purposes.</p>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="reset_admin_password" 
                    class="flex items-center justify-center gap-2 px-6 py-3 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                Reset Admin Password
            </button>
        </form>
    </div>

    <!-- Password Guidelines -->
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h4 class="text-lg font-bold text-blue-900 mb-3 flex items-center gap-2">
            <span class="material-symbols-outlined">info</span>
            Password Guidelines
        </h4>
        <ul class="text-blue-800 space-y-2 text-sm">
            <li class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-blue-600">check_circle</span>
                Minimum 6 characters required
            </li>
            <li class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-blue-600">check_circle</span>
                Use a combination of letters and numbers for better security
            </li>
            <li class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-blue-600">check_circle</span>
                Avoid using easily guessable information
            </li>
            <li class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base text-blue-600">check_circle</span>
                Consider using a password manager for strong, unique passwords
            </li>
        </ul>
    </div>
</div>

<script>
// Password strength indicator (optional enhancement)
document.addEventListener('DOMContentLoaded', function() {
    const passwordInputs = document.querySelectorAll('input[type="password"]');
    
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = this.parentElement.querySelector('.password-strength') || createStrengthIndicator(this);
            
            if (password.length === 0) {
                strengthIndicator.innerHTML = '';
                return;
            }
            
            let strength = 'Weak';
            let color = 'text-red-600';
            
            if (password.length >= 8) {
                strength = 'Medium';
                color = 'text-orange-600';
            }
            
            if (password.length >= 10 && /[0-9]/.test(password) && /[a-zA-Z]/.test(password)) {
                strength = 'Strong';
                color = 'text-green-600';
            }
            
            strengthIndicator.innerHTML = `<span class="text-xs ${color} font-medium">Strength: ${strength}</span>`;
        });
    });
    
    function createStrengthIndicator(input) {
        const indicator = document.createElement('div');
        indicator.className = 'password-strength mt-1';
        input.parentElement.appendChild(indicator);
        return indicator;
    }
});
</script>