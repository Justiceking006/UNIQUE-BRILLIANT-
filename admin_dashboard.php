<?php
session_start();
require_once 'connect.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get admin stats for dashboard
$db = getDBConnection();

// Total students count
$students_stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE status = 'approved'");
$total_students = $students_stmt->fetch()['total'];

// Pending approvals count
$pending_stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE status = 'pending'");
$pending_approvals = $pending_stmt->fetch()['total'];

// Total staff count
$staff_stmt = $db->query("SELECT COUNT(*) as total FROM staff WHERE status = 'active'");
$total_staff = $staff_stmt->fetch()['total'];

// Recent payments
$payments_stmt = $db->query("SELECT COUNT(*) as total FROM payments WHERE status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$recent_payments = $payments_stmt->fetch()['total'];

// Set default page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Unique Brilliant Schools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Lexend', sans-serif; background-color: #f8f6f5; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); position: fixed; height: 100vh; z-index: 50; }
            .sidebar.active { transform: translateX(0); }
            main { margin-left: 0 !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-[#f8f6f5]">
    <!-- Mobile Header -->
    <header class="bg-white shadow-sm lg:hidden sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <button id="menuToggle" class="text-[#8d6a5e] hover:text-[#ff6933]">
                    <span class="material-symbols-outlined text-2xl">menu</span>
                </button>
                <div class="flex items-center space-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-[#ff6933]/20">
                        <span class="material-symbols-outlined text-xl text-[#ff6933]">admin_panel_settings</span>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-[#181210]">Admin Panel</h1>
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
        <aside class="sidebar fixed lg:static inset-y-0 left-0 z-50 w-64 bg-white shadow-lg lg:shadow-none border-r border-[#e7deda] lg:h-screen">
            <!-- Logo Section -->
            <div class="p-6 border-b border-[#e7deda] hidden lg:block">
                <div class="flex items-center space-x-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ff6933]/20">
                        <span class="material-symbols-outlined text-2xl text-[#ff6933]">admin_panel_settings</span>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-[#181210]">Admin Panel</h1>
                        <p class="text-sm text-[#8d6a5e]">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="p-4 space-y-1 overflow-y-auto h-[calc(100vh-200px)]">
                <?php
                // Define menu structure wit// Define menu structure with sublists
$menu_structure = [
    'dashboard' => [
        'title' => 'Dashboard Overview',
        'icon' => 'dashboard',
        'link' => '?page=dashboard'
    ],

    'admissions' => [
        'title' => 'Admissions Management',
        'icon' => 'how_to_reg',
        'sub' => [
            ['title' => 'Online Admissions', 'icon' => 'list_alt', 'link' => '?page=online-admissions', 'badge' => $pending_approvals],
            ['title' => 'Create Admission', 'icon' => 'person_add', 'link' => '?page=create-admission']
        ]
    ],

    'students' => [
        'title' => 'Student Management',
        'icon' => 'school',
        'sub' => [
            ['title' => 'Student Details', 'icon' => 'people', 'link' => '?page=student-details'],
            ['title' => 'Class Lists', 'icon' => 'groups', 'link' => '?page=class-lists']
        ]
    ],

    'staff' => [
        'title' => 'Staff Management',
        'icon' => 'badge',
        'sub' => [
            ['title' => 'Add Staff', 'icon' => 'person_add', 'link' => '?page=add-staff'],
            ['title' => 'Staff List', 'icon' => 'list', 'link' => '?page=staff-list']
        ]
    ],

    'hr' => [
        'title' => 'Human Resources',
        'icon' => 'work',
        'sub' => [
            ['title' => 'Pay Staff', 'icon' => 'payments', 'link' => '?page=pay-staff'],
            ['title' => 'Staff Payment History', 'icon' => 'history', 'link' => '?page=payment-history']
        ]
    ],

    'academic' => [
        'title' => 'Academic Management',
        'icon' => 'menu_book',
        'sub' => [
            ['title' => 'Class & Section', 'icon' => 'class', 'link' => '?page=class-section'],
            ['title' => 'Assign Class Teacher', 'icon' => 'assignment_ind', 'link' => '?page=assign-teacher'],
            ['title' => 'Subjects/Courses', 'icon' => 'subject', 'link' => '?page=subjects'],
            ['title' => 'Course Assignment', 'icon' => 'assignment', 'link' => '?page=course-assignment']
        ]
    ],

    'bookstore' => [
        'title' => 'Bookstore Management',
        'icon' => 'store',
        'sub' => [
            ['title' => 'Book Inventory', 'icon' => 'inventory', 'link' => '?page=inventory'],
            ['title' => 'Stationery Items', 'icon' => 'description', 'link' => '?page=stationery'],
            ['title' => 'Stationery Purchase', 'icon' => 'shopping_cart', 'link' => '?page=stationery-purchase']
        ]
    ],

    'events' => [
        'title' => 'Events & Announcements',
        'icon' => 'event',
        'sub' => [
            ['title' => 'Create Events', 'icon' => 'add_circle', 'link' => '?page=create-event'],
            ['title' => 'Event Registrations', 'icon' => 'how_to_reg', 'link' => '?page=event-registrations']
        ]
    ],

    'transactions' => [
        'title' => 'Transactions & Finance',
        'icon' => 'account_balance',
        'sub' => [
            ['title' => 'Collect Fees', 'icon' => 'payments', 'link' => '?page=collect-fees'],
            ['title' => 'Approve Payments', 'icon' => 'verified', 'link' => '?page=approve-payments'],
            ['title' => 'Fee Transactions', 'icon' => 'receipt_long', 'link' => '?page=fee-transactions'],
            ['title' => 'Financial Reports', 'icon' => 'analytics', 'link' => '?page=reports']
        ]
    ],

    'settings' => [
        'title' => 'Settings',
        'icon' => 'settings',
        'link' => '?page=settings'
    ]
];
                // Render menu
                foreach ($menu_structure as $key => $item) {
                    $hasSub = isset($item['sub']);
                    $isActive = $page === $key;

                    echo '<div class="menu-item">';
                    
                    // Main item
                    if ($hasSub) {
                        echo '
                        <button class="flex items-center justify-between w-full p-3 rounded-lg transition-colors text-[#8d6a5e] hover:bg-[#ff6933]/10 hover:text-[#ff6933]" 
                            onclick="toggleDropdown(\''.$key.'\')">
                            <div class="flex items-center space-x-3">
                                <span class="material-symbols-outlined text-lg">'.$item['icon'].'</span>
                                <span class="font-medium text-sm">'.$item['title'].'</span>
                            </div>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                        </button>';
                    } else {
                        echo '
                        <a href="'.$item['link'].'" class="flex items-center justify-between w-full p-3 rounded-lg transition-colors '.($isActive ? 'bg-[#ff6933] text-white' : 'text-[#8d6a5e] hover:bg-[#ff6933]/10 hover:text-[#ff6933]').'">
                            <div class="flex items-center space-x-3">
                                <span class="material-symbols-outlined text-lg">'.$item['icon'].'</span>
                                <span class="font-medium text-sm">'.$item['title'].'</span>
                            </div>
                        </a>';
                    }

                    // Submenu (hidden by default)
                    if ($hasSub) {
                        echo '<div id="'.$key.'-submenu" class="hidden pl-10 space-y-1 mt-1">';
                        foreach ($item['sub'] as $sub) {
                            $badge = isset($sub['badge']) ? '<span class="bg-[#ff6933] text-white text-xs px-2 py-1 rounded-full ml-2">'.$sub['badge'].'</span>' : '';
                            $isSubActive = $page === str_replace('?page=', '', parse_url($sub['link'], PHP_URL_QUERY));
                            
                            echo '
                            <a href="'.$sub['link'].'" class="flex items-center justify-between p-2 rounded-md text-sm '.($isSubActive ? 'text-[#ff6933] font-medium' : 'text-[#8d6a5e] hover:text-[#ff6933]').' hover:bg-[#ff6933]/10 transition-colors">
                                <div class="flex items-center space-x-2">
                                    <span class="material-symbols-outlined text-sm">'.$sub['icon'].'</span>
                                    <span>'.$sub['title'].'</span>
                                </div>
                                '.$badge.'
                            </a>';
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </nav>

            <!-- Logout Button -->
            <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-[#e7deda] bg-white">
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

            <div class="p-4 lg:p-6">
                <!-- Page Content -->
                <div class="max-w-7xl mx-auto">
                    <?php if ($page === 'dashboard'): ?>
                        <!-- Admin Dashboard Overview -->
                        <div class="mb-6">
                            <h1 class="text-2xl font-bold text-[#181210] mb-2">Admin Dashboard</h1>
                            <p class="text-[#8d6a5e]">Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>! 👋 System Overview</p>
                        </div>

                        <!-- Quick Stats -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-6 lg:mb-8">
                            <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-[#8d6a5e]">Total Students</p>
                                        <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_students; ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-2xl lg:text-3xl text-blue-500">school</span>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-[#8d6a5e]">Pending Approvals</p>
                                        <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $pending_approvals; ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-2xl lg:text-3xl text-orange-500">pending</span>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-[#8d6a5e]">Staff Members</p>
                                        <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $total_staff; ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-2xl lg:text-3xl text-green-500">badge</span>
                                </div>
                            </div>
                            
                            <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-[#8d6a5e]">Recent Payments</p>
                                        <p class="text-xl lg:text-2xl font-bold text-[#181210]"><?php echo $recent_payments; ?></p>
                                    </div>
                                    <span class="material-symbols-outlined text-2xl lg:text-3xl text-purple-500">payments</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6 mb-6 lg:mb-8">
                            <a href="?page=online-admissions" class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda] hover:border-[#ff6933] transition-colors">
                                <div class="flex items-center space-x-3">
                                    <span class="material-symbols-outlined text-xl lg:text-2xl text-[#ff6933]">how_to_reg</span>
                                    <div>
                                        <h3 class="font-bold text-[#181210] text-sm lg:text-base">Review Admissions</h3>
                                        <p class="text-xs lg:text-sm text-[#8d6a5e]">Approve pending applications</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?page=add-staff" class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda] hover:border-[#ff6933] transition-colors">
                                <div class="flex items-center space-x-3">
                                    <span class="material-symbols-outlined text-xl lg:text-2xl text-[#ff6933]">person_add</span>
                                    <div>
                                        <h3 class="font-bold text-[#181210] text-sm lg:text-base">Add Staff</h3>
                                        <p class="text-xs lg:text-sm text-[#8d6a5e]">Create new staff accounts</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="?page=collect-fees" class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda] hover:border-[#ff6933] transition-colors">
                                <div class="flex items-center space-x-3">
                                    <span class="material-symbols-outlined text-xl lg:text-2xl text-[#ff6933]">payments</span>
                                    <div>
                                        <h3 class="font-bold text-[#181210] text-sm lg:text-base">Collect Fees</h3>
                                        <p class="text-xs lg:text-sm text-[#8d6a5e]">Process student payments</p>
                                    </div>
                                </div>
                            </a>
                        </div>

                        <!-- Recent Activity -->
                        <div class="bg-white rounded-xl p-4 lg:p-6 shadow-sm border border-[#e7deda]">
                            <h3 class="text-lg font-bold text-[#181210] mb-4">Recent Activity</h3>
                            <div class="text-center py-6 lg:py-8">
                                <span class="material-symbols-outlined text-3xl lg:text-4xl text-[#8d6a5e] mb-2">activity</span>
                                <p class="text-[#8d6a5e] text-sm lg:text-base">Activity feed will appear here</p>
                            </div>
                        </div>
                        
                        
<?php elseif ($page === 'online-admissions'): ?>
    <!-- Include External Online Admissions CONTENT -->
    <?php include 'online_admissions_content.php'; ?>

<?php elseif ($page === 'create-admission'): ?>
    <!-- Include External Create Admission Content -->
    <?php include 'create_admission_content.php'; ?>

<?php elseif ($page === 'pay-staff'): ?>
    <!-- Include External Create Admission Content -->
    <?php include 'pay_staff_content.php'; ?>
    
<?php elseif ($page === 'subjects'): ?>
    <!-- Include External Create Admission Content -->
    <?php include 'subjects_content.php'; ?>
			  						
<?php elseif ($page === 'course-assignment'): ?>
    <!-- Include External Create Admission Content -->
    <?php include 'course_assignment_content.php'; ?>
			
<?php elseif ($page === 'class-section'): ?>
    <!-- Include External Create Admission Content -->
    <?php include 'class_section_content.php'; ?>

<?php elseif ($page === 'stationery'): ?>
    <!-- Include External Create Admission Content -->
    <?php include 'stationery_content.php'; ?>

<?php elseif ($page === 'stationery-purchase'): ?>
    <!-- Include Stationery Purchase Content -->
    <?php include 'stationery_purchase.php'; ?>

<?php elseif ($page === 'assign-teacher'): ?>
    <!-- Include External Staff List Content -->
    <?php include 'assign_teacher_content.php'; ?>
	
<?php elseif ($page === 'payroll'): ?>
    <!-- Include External Staff List Content -->
    <?php include 'payroll_content.php'; ?>
    
<?php elseif ($page === 'staff-list'): ?>
    <!-- Include External Staff List Content -->
    <?php include 'staff_list_content.php'; ?>
    
<?php elseif ($page === 'create-event'): ?>
    <!-- Include Create Event Content -->
    <?php include 'create_event_content.php'; ?>
    
    <?php elseif ($page === 'reports'): ?>
    <!-- Include Financial Reports Content -->
    <?php include 'reports_content.php'; ?>
    
<?php elseif ($page === 'inventory'): ?>
    <!-- Include External Staff List Content -->
    <?php include 'inventory_content.php'; ?>
    
<?php elseif ($page === 'payment-history'): ?>
    <!-- Include External Staff List Content -->
    <?php include 'payment_history_content.php'; ?>

<?php elseif ($page === 'add-staff'): ?>
    <!-- Include External Add Staff Content -->
    <?php include 'add_staff_content.php'; ?>
    
    <?php elseif ($page === 'approve-payments'): ?>
    <!-- Include Approve Payments Content -->
    <?php include 'approve_payments_content.php'; ?>
    
<?php elseif ($page === 'class-lists'): ?>
    <!-- Include External Add Staff Content -->
    <?php include 'class_lists_content.php'; ?>
    
    <?php elseif ($page === 'settings'): ?>
    <!-- Include Settings Content -->
    <?php include 'settings_content.php'; ?>
    
<?php elseif ($page === 'student-details'): ?>
    <!-- Include External Add Staff Content -->
    <?php include 'student_details_content.php'; ?>
    
    <?php elseif ($page === 'event-registrations'): ?>
    <!-- Include Event Registrations Content -->
    <?php include 'event_registrations_content.php'; ?>
	<?php elseif ($page === 'fee-transactions'): ?>
    <!-- Include Fee Transactions Content -->
    <?php include 'fee_transactions_content.php'; ?>
    
<?php elseif ($page === 'fee-transactions'): ?>
    <!-- Include Fee Transactions Content -->
    <?php include 'fee_transactions_content.php'; ?>
<?php elseif ($page === 'collect-fees'): ?>
    <!-- Include Collect Fees Content -->
    <?php include 'collect_fees_content.php'; ?>

<?php elseif ($page === 'payment-history'): ?>
    <!-- Include External Staff List Content -->
    <?php include 'payment_history_content.php'; ?>
<?php else: ?>
                        <!-- Other Pages Placeholder -->
                        <div class="mb-6">
                            <h1 class="text-2xl font-bold text-[#181210]">
                                <?php 
                                $page_title = 'Page';
                                foreach ($menu_structure as $key => $item) {
                                    if ($page === $key) {
                                        $page_title = $item['title'];
                                        break;
                                    }
                                    if (isset($item['sub'])) {
                                        foreach ($item['sub'] as $sub) {
                                            $sub_page = str_replace('?page=', '', parse_url($sub['link'], PHP_URL_QUERY));
                                            if ($page === $sub_page) {
                                                $page_title = $sub['title'];
                                                break 2;
                                            }
                                        }
                                    }
                                }
                                echo $page_title; 
                                ?>
                            </h1>
                            <p class="text-[#8d6a5e]">Admin Panel - <?php echo $page_title; ?></p>
                        </div>
                        
                        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                            <div class="text-center py-12">
                                <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">construction</span>
                                <h3 class="text-xl font-bold text-[#181210] mb-2">Page Under Development</h3>
                                <p class="text-[#8d6a5e] max-w-md mx-auto">
                                    The <?php echo $page_title; ?> section is currently being developed. 
                                    This page will be available in the next update.
                                </p>
                            </div>
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
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
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

        // Toggle Dropdown Script
        function toggleDropdown(id) {
            const submenu = document.getElementById(id + '-submenu');
            const isHidden = submenu.classList.contains('hidden');
            
            // Close all other submenus
            document.querySelectorAll('[id$="-submenu"]').forEach(menu => {
                if (menu.id !== id + '-submenu') {
                    menu.classList.add('hidden');
                }
            });
            
            // Toggle current submenu
            if (isHidden) {
                submenu.classList.remove('hidden');
            } else {
                submenu.classList.add('hidden');
            }
        }
    </script>
</body>
</html>