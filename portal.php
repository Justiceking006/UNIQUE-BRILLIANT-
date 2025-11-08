<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get user details based on user type
$db = getDBConnection();

// Define menu items
$menu_items = [
    'dashboard' => [
        'title' => 'Dashboard',
        'icon' => 'dashboard'
    ],
    'academic' => [
        'title' => 'Academic',
        'icon' => 'school',
        'sub' => [
            ['title' => 'Assignments', 'icon' => 'assignment', 'link' => '?page=assignments'],
            ['title' => 'Lectures', 'icon' => 'video_library', 'link' => '?page=lectures'],
            ['title' => 'Courses Enrolled', 'icon' => 'book', 'link' => '?page=courses-enrolled']
        ]
    ],
    'fees' => [
        'title' => 'Fee Management', 
        'icon' => 'payments'
    ],
    'results' => [
        'title' => 'Results', 
        'icon' => 'grade'
    ],
    
  
    'notifications' => [
        'title' => 'Notifications', 
        'icon' => 'notifications'
    ],
    'profile' => [
        'title' => 'Profile', 
        'icon' => 'person'
    ]
];

if ($_SESSION['user_type'] === 'student') {
    // Get student details
    $stmt = $db->prepare("
        SELECT s.*, u.email 
        FROM students s 
        JOIN users u ON s.id = u.student_id 
        WHERE s.id = ?
    ");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch();

    if (!$student) {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Get dashboard statistics
    $fees_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'unpaid' THEN 1 ELSE 0 END) as pending_payments,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'paid' THEN amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN transaction_type = 'payment' AND status = 'unpaid' THEN amount ELSE 0 END) as total_pending,
            SUM(CASE WHEN transaction_type = 'fee_issued' THEN amount ELSE 0 END) as total_issued_fees
        FROM fee_transactions 
        WHERE student_id = ?
    ");
    $fees_stmt->execute([$_SESSION['student_id']]);
    $fee_stats = $fees_stmt->fetch();

    // Recent fee transactions
    $recent_fees_stmt = $db->prepare("
        SELECT * FROM fee_transactions 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_fees_stmt->execute([$_SESSION['student_id']]);
    $recent_transactions = $recent_fees_stmt->fetchAll();

    // Upcoming events count
    $events_stmt = $db->prepare("
        SELECT COUNT(*) as events_count 
        FROM events 
        WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        AND status = 'active'
    ");
    $events_stmt->execute();
    $upcoming_events = $events_stmt->fetch()['events_count'];

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
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    $password_error = null;
    $password_success = null;
    
    if ($user && password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($update_stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                    $password_success = "Password changed successfully!";
                } else {
                    $password_error = "Failed to update password. Please try again.";
                }
            } else {
                $password_error = "Password must be at least 6 characters long.";
            }
        } else {
            $password_error = "New passwords do not match.";
        }
    } else {
        $password_error = "Current password is incorrect.";
    }
}

// Set default page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - Unique Brilliant Schools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Lexend', sans-serif; background-color: #f8f6f5; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .submenu { transition: all 0.3s ease-in-out; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
        }
    </style>
</head>
<body class="min-h-screen bg-[#f8f6f5]">
    <!-- Mobile Header -->
    <header class="bg-white shadow-sm lg:hidden">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <button id="menuToggle" class="text-[#8d6a5e] hover:text-[#ff6933]">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
                <div class="flex items-center space-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#ff6933]/20">
                        <span class="material-symbols-outlined text-xl text-[#ff6933]">school</span>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-[#181210]">Student Portal</h1>
                    </div>
                </div>
                <a href="logout.php" class="text-[#8d6a5e] hover:text-[#ff6933]">
                    <span class="material-symbols-outlined">logout</span>
                </a>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar Navigation -->
        <aside class="sidebar fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white shadow-lg lg:shadow-none border-r border-[#e7deda]">
            <!-- Logo Section -->
            <div class="p-6 border-b border-[#e7deda] hidden lg:block">
                <div class="flex items-center space-x-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ff6933]/20">
                        <span class="material-symbols-outlined text-2xl text-[#ff6933]">school</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-[#181210]">Student Portal</h1>
                        <p class="text-sm text-[#8d6a5e]">
                            Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!
                        </p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="p-4 space-y-1">
                <?php
                foreach ($menu_items as $key => $item) {
                    $isActive = $page === $key;
                    $hasSub = isset($item['sub']);
                    
                    if ($hasSub) {
                        // Menu item with dropdown
                        echo '
                        <div class="mb-1">
                            <button onclick="toggleSubmenu(\'' . $key . '\')" class="flex items-center justify-between w-full p-3 rounded-lg transition-colors ' . ($isActive ? 'bg-[#ff6933] text-white' : 'text-[#8d6a5e] hover:bg-[#ff6933]/10 hover:text-[#ff6933]') . '">
                                <div class="flex items-center space-x-3">
                                    <span class="material-symbols-outlined">' . $item['icon'] . '</span>
                                    <span class="font-medium">' . $item['title'] . '</span>
                                </div>
                                <span id="chevron-' . $key . '" class="material-symbols-outlined text-sm transform transition-transform">chevron_right</span>
                            </button>
                            <div id="submenu-' . $key . '" class="submenu mt-1 space-y-1 overflow-hidden ' . ($isActive ? 'max-h-96' : 'max-h-0') . '">
                        ';
                        
                        foreach ($item['sub'] as $subItem) {
                            $isSubActive = $_GET['page'] === str_replace('?page=', '', $subItem['link']);
                            echo '
                            <a href="' . $subItem['link'] . '" class="flex items-center space-x-3 p-3 pl-12 rounded-lg transition-colors ' . ($isSubActive ? 'bg-[#ff6933] text-white' : 'text-[#8d6a5e] hover:bg-[#ff6933]/10 hover:text-[#ff6933]') . '">
                                <span class="material-symbols-outlined text-sm">' . $subItem['icon'] . '</span>
                                <span class="font-medium">' . $subItem['title'] . '</span>
                            </a>';
                        }
                        
                        echo '
                            </div>
                        </div>';
                    } else {
                        // Simple menu item
                        echo '
                        <a href="?page=' . $key . '" class="flex items-center justify-between p-3 rounded-lg transition-colors ' . ($isActive ? 'bg-[#ff6933] text-white' : 'text-[#8d6a5e] hover:bg-[#ff6933]/10 hover:text-[#ff6933]') . '">
                            <div class="flex items-center space-x-3">
                                <span class="material-symbols-outlined">' . $item['icon'] . '</span>
                                <span class="font-medium">' . $item['title'] . '</span>
                            </div>
                        </a>';
                    }
                }
                ?>
            </nav>

            <!-- Logout Button -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-[#e7deda]">
                <a href="logout.php" class="flex items-center space-x-3 p-3 rounded-lg text-[#8d6a5e] hover:bg-red-50 hover:text-red-600 transition-colors">
                    <span class="material-symbols-outlined">logout</span>
                    <span class="font-medium">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 lg:ml-0 min-h-screen">
            <!-- Overlay for mobile -->
            <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden"></div>

            <div class="p-6">
                <!-- Page Content -->
                <div class="max-w-6xl mx-auto">
                    <?php if ($_SESSION['user_type'] !== 'student'): ?>
                        <!-- Admin/Staff Dashboard -->
                        <div class="mb-6">
                            <h1 class="text-2xl font-bold text-[#181210] mb-2">
                                <?php echo ucfirst($_SESSION['user_type']); ?> Dashboard
                            </h1>
                            <p class="text-[#8d6a5e]">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋</p>
                        </div>

                        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                            <h2 class="text-xl font-bold text-[#181210] mb-4">Administrative Panel</h2>
                            <p class="text-[#8d6a5e]">You are logged in as an administrator. Use the navigation to manage the system.</p>
                        </div>

                    <?php elseif ($page === 'dashboard'): ?>
                        <!-- Student Dashboard Overview -->
                        <?php include 'dashboard_content.php'; ?>

                    <?php elseif ($page === 'fees'): ?>
                        <!-- Include External Fee Management Content -->
                        <?php include 'fees_content.php'; ?>

                    <?php elseif ($page === 'academic' || $page === 'assignments' || $page === 'lectures' || $page === 'courses-enrolled'): ?>
                        <!-- Include Academic Content -->
                        <?php include 'academic_content.php'; ?>

                    <?php elseif ($page === 'results'): ?>
                        <!-- Results Page -->
                        <?php include 'results_content.php'; ?>

                    <?php elseif ($page === 'documents'): ?>
                        <!-- Documents Page -->
                        <?php include 'documents_content.php'; ?>

                    <?php elseif ($page === 'notifications'): ?>
                        <!-- Include External Notifications Content -->
                        <?php include 'notifications_content.php'; ?>
                        <?php elseif ($page === 'assignment-subject'): ?>
    <!-- Assignment Subject Page -->
    <?php include 'assignment_subject.php'; ?>

<?php elseif ($page === 'assignment-detail'): ?>
    <!-- Assignment Detail Page -->
    <?php include 'assignment_detail.php'; ?>

                    <?php elseif ($page === 'profile'): ?>
                        <!-- Include External Profile Content -->
                        <?php include 'profile_content.php'; ?>
                    
                    <?php else: ?>
                        <!-- Fallback for unknown pages -->
                        <div class="mb-6">
                            <h1 class="text-2xl font-bold text-[#181210]">
                                <?php echo isset($menu_items[$page]) ? $menu_items[$page]['title'] : 'Page'; ?>
                            </h1>
                        </div>
                        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                            <p class="text-[#8d6a5e] text-center py-8">Page content coming soon...</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('overlay');

            function toggleMenu() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('hidden');
            }

            menuToggle.addEventListener('click', toggleMenu);
            overlay.addEventListener('click', toggleMenu);

            // Close menu when clicking on a link (mobile)
            if (window.innerWidth < 768) {
                const navLinks = document.querySelectorAll('nav a');
                navLinks.forEach(link => {
                    link.addEventListener('click', toggleMenu);
                });
            }
        });

        // Submenu toggle function
        function toggleSubmenu(menuKey) {
            const submenu = document.getElementById('submenu-' + menuKey);
            const chevron = document.getElementById('chevron-' + menuKey);
            
            if (submenu.classList.contains('max-h-0')) {
                submenu.classList.remove('max-h-0');
                submenu.classList.add('max-h-96');
                chevron.classList.add('rotate-90');
            } else {
                submenu.classList.remove('max-h-96');
                submenu.classList.add('max-h-0');
                chevron.classList.remove('rotate-90');
            }
        }
    </script>
</body>
</html>