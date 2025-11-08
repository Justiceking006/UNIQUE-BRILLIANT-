<?php
// notifications_content.php - Student Notifications/Events
require_once 'connect.php';

// Check if user is student
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$student_id = $_SESSION['student_id'];

// Get student details to check class and section
$student_stmt = $db->prepare("SELECT class, section FROM students WHERE id = ?");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch();

if (!$student) {
    die("Student not found.");
}

// DEBUG: Let's see what events exist and what class the student has
echo "<!-- DEBUG: Student Class: " . htmlspecialchars($student['class']) . " -->";

// First, let's get ALL events to see what's in the database
$all_events_stmt = $db->prepare("SELECT * FROM events WHERE status = 'active' AND event_date >= CURDATE() ORDER BY event_date ASC");
$all_events_stmt->execute();
$all_events = $all_events_stmt->fetchAll();

echo "<!-- DEBUG: Total events found: " . count($all_events) . " -->";
foreach ($all_events as $event) {
    echo "<!-- DEBUG Event: " . htmlspecialchars($event['title']) . " | Audience: " . $event['audience'] . " | Classes: " . htmlspecialchars($event['specific_classes'] ?? 'NULL') . " -->";
}

// Build query to get events relevant to the student - FIXED VERSION
$query = "
    SELECT * FROM events 
    WHERE status = 'active' 
    AND event_date >= CURDATE()
    AND (
        audience = 'all' 
        OR audience = 'students'
        OR (audience = 'specific_classes' AND (specific_classes IS NULL OR specific_classes = '' OR specific_classes LIKE ?))
    )
    ORDER BY event_date ASC, event_time ASC
";

// Create search pattern for student's class
$class_pattern = '%' . $student['class'] . '%';
$events_stmt = $db->prepare($query);
$events_stmt->execute([$class_pattern]);
$events = $events_stmt->fetchAll();

echo "<!-- DEBUG: Filtered events for student: " . count($events) . " -->";

// Alternative approach - let's also try manual filtering
$manually_filtered_events = [];
foreach ($all_events as $event) {
    $should_show = false;
    
    if ($event['audience'] === 'all' || $event['audience'] === 'students') {
        $should_show = true;
    } 
    elseif ($event['audience'] === 'specific_classes') {
        $specific_classes = $event['specific_classes'];
        if (empty($specific_classes) || $specific_classes === 'null') {
            $should_show = true; // No specific classes means all classes
        } else {
            // Try to decode JSON and check if student's class is included
            $classes_array = json_decode($specific_classes, true);
            if (is_array($classes_array) && in_array($student['class'], $classes_array)) {
                $should_show = true;
            }
            // Also try simple string match as fallback
            elseif (strpos($specific_classes, $student['class']) !== false) {
                $should_show = true;
            }
        }
    }
    
    if ($should_show) {
        $manually_filtered_events[] = $event;
    }
}

// Use manually filtered events if SQL query didn't work well
if (count($events) === 0 && count($manually_filtered_events) > 0) {
    $events = $manually_filtered_events;
    echo "<!-- DEBUG: Using manually filtered events: " . count($events) . " -->";
}

// Get event registrations for this student
$registrations_stmt = $db->prepare("
    SELECT event_id, payment_status 
    FROM event_registrations 
    WHERE student_id = ?
");
$registrations_stmt->execute([$student_id]);
$registrations = $registrations_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Handle event registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = intval($_POST['event_id']);
    
    // Get event details
    $event_stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    $event_stmt->execute([$event_id]);
    $event = $event_stmt->fetch();
    
    if ($event) {
        try {
            // Check if already registered
            $check_stmt = $db->prepare("SELECT id FROM event_registrations WHERE event_id = ? AND student_id = ?");
            $check_stmt->execute([$event_id, $student_id]);
            
            if ($check_stmt->fetch()) {
                $error_message = "You are already registered for this event.";
            } else {
                $insert_stmt = $db->prepare("
                    INSERT INTO event_registrations 
                    (event_id, student_id, payment_amount, registration_date) 
                    VALUES (?, ?, ?, NOW())
                ");
                
                $payment_amount = $event['requires_payment'] ? $event['payment_amount'] : 0.00;
                
                if ($insert_stmt->execute([$event_id, $student_id, $payment_amount])) {
                    $success_message = "Successfully registered for '" . htmlspecialchars($event['title']) . "'!";
                    
                    // If payment required, show payment instructions
                    if ($event['requires_payment']) {
                        $success_message .= " Please make payment of ₦" . number_format($event['payment_amount'], 2) . " to complete your registration.";
                    }
                    
                    // Refresh registrations
                    $registrations_stmt->execute([$student_id]);
                    $registrations = $registrations_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    // Refresh events list
                    $events_stmt->execute([$class_pattern]);
                    $events = $events_stmt->fetchAll();
                } else {
                    $error_message = "Failed to register for event. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Registration error: " . $e->getMessage();
        }
    } else {
        $error_message = "Event not found.";
    }
}
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210]">Events & Notifications</h1>
    <p class="text-[#8d6a5e]">Stay updated with school events and announcements</p>
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

<!-- Temporary Debug Info (remove after testing) -->
<div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
    <h4 class="font-bold text-yellow-800 mb-2">Debug Information</h4>
    <p class="text-sm text-yellow-700">
        Student Class: <strong><?php echo htmlspecialchars($student['class']); ?></strong> | 
        Total Events in DB: <strong><?php echo count($all_events); ?></strong> | 
        Events Shown: <strong><?php echo count($events); ?></strong>
    </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Events List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-[#181210] flex items-center">
                    <span class="material-symbols-outlined mr-2 text-[#ff6933]">event</span>
                    Upcoming Events (<?php echo count($events); ?>)
                </h3>
                <div class="text-sm text-[#8d6a5e]">
                    Showing events for your class: <strong><?php echo htmlspecialchars($student['class']); ?></strong>
                </div>
            </div>
            
            <div class="space-y-4">
                <?php if (empty($events)): ?>
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">event_available</span>
                        <h3 class="text-xl font-bold text-[#181210] mb-2">No Upcoming Events</h3>
                        <p class="text-[#8d6a5e] max-w-md mx-auto">
                            There are no upcoming events scheduled for your class at the moment. 
                            Check back later for new announcements!
                        </p>
                        
                        <!-- Show all events for debugging -->
                        <?php if (count($all_events) > 0): ?>
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-bold text-gray-800 mb-2">All Events in System (Debug):</h4>
                            <?php foreach ($all_events as $event): ?>
                                <div class="text-left text-sm text-gray-600 mb-2 p-2 border-b">
                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong> - 
                                    Audience: <?php echo $event['audience']; ?> - 
                                    Classes: <?php echo htmlspecialchars($event['specific_classes'] ?? 'None'); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($events as $event): 
                        $is_registered = isset($registrations[$event['id']]);
                        $registration_status = $is_registered ? $registrations[$event['id']] : null;
                    ?>
                        <div class="border border-[#e7deda] rounded-lg p-4 hover:border-[#ff6933] transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="flex-1">
                                    <h4 class="font-bold text-[#181210] text-lg mb-1">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h4>
                                    
                                    <?php if ($event['description']): ?>
                                        <p class="text-[#8d6a5e] text-sm mb-2">
                                            <?php echo htmlspecialchars($event['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="flex flex-wrap gap-4 text-sm text-[#8d6a5e]">
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-base">calendar_today</span>
                                            <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                                        </div>
                                        
                                        <?php if ($event['event_time']): ?>
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-base">schedule</span>
                                            <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['venue']): ?>
                                        <div class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-base">location_on</span>
                                            <?php echo htmlspecialchars($event['venue']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-end gap-2 ml-4">
                                    <!-- Event Type Badge -->
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium 
                                        <?php 
                                        switch($event['event_type']) {
                                            case 'academic': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'sports': echo 'bg-green-100 text-green-800'; break;
                                            case 'cultural': echo 'bg-purple-100 text-purple-800'; break;
                                            case 'social': echo 'bg-orange-100 text-orange-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($event['event_type']); ?>
                                    </span>
                                    
                                    <!-- Payment Badge -->
                                    <?php if ($event['requires_payment']): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            ₦<?php echo number_format($event['payment_amount'], 2); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Registration Status & Action -->
                            <div class="flex justify-between items-center pt-3 border-t border-[#e7deda]">
                                <div class="text-sm text-[#8d6a5e]">
                                    <?php if ($is_registered): ?>
                                        <span class="flex items-center gap-1 font-medium 
                                            <?php echo $registration_status === 'paid' ? 'text-green-600' : 'text-orange-600'; ?>">
                                            <span class="material-symbols-outlined text-base">
                                                <?php echo $registration_status === 'paid' ? 'check_circle' : 'pending'; ?>
                                            </span>
                                            Registered 
                                            <?php if ($registration_status === 'paid'): ?>
                                                • Payment Approved
                                            <?php elseif ($registration_status === 'pending' && $event['requires_payment']): ?>
                                                • Payment Pending
                                            <?php endif; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[#8d6a5e]">
                                            <?php echo $event['requires_payment'] ? 'Registration requires payment' : 'Free event'; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!$is_registered): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" name="register_event" 
                                                class="inline-flex items-center h-10 px-4 rounded-lg bg-[#ff6933] text-white font-medium hover:bg-[#ff6933]/90 transition-colors text-sm">
                                            <span class="material-symbols-outlined mr-1 text-base">how_to_reg</span>
                                            Register Now
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="inline-flex items-center h-10 px-4 rounded-lg bg-green-100 text-green-800 font-medium text-sm">
                                        <span class="material-symbols-outlined mr-1 text-base">check</span>
                                        Registered
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- My Registrations -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">list_alt</span>
                My Registrations
            </h3>
            
            <div class="space-y-3">
                <?php 
                $my_events_stmt = $db->prepare("
                    SELECT e.title, e.event_date, er.payment_status, e.requires_payment
                    FROM event_registrations er
                    JOIN events e ON er.event_id = e.id
                    WHERE er.student_id = ?
                    ORDER BY e.event_date ASC
                ");
                $my_events_stmt->execute([$student_id]);
                $my_events = $my_events_stmt->fetchAll();
                ?>
                
                <?php if (empty($my_events)): ?>
                    <p class="text-[#8d6a5e] text-center py-4">No event registrations yet</p>
                <?php else: ?>
                    <?php foreach ($my_events as $my_event): ?>
                        <div class="border border-[#e7deda] rounded-lg p-3">
                            <h4 class="font-bold text-[#181210] text-sm"><?php echo htmlspecialchars($my_event['title']); ?></h4>
                            <p class="text-xs text-[#8d6a5e]">
                                <?php echo date('M j, Y', strtotime($my_event['event_date'])); ?>
                            </p>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium mt-1
                                <?php echo $my_event['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'; ?>">
                                <?php echo ucfirst($my_event['payment_status']); ?>
                                <?php if ($my_event['requires_payment'] && $my_event['payment_status'] === 'pending'): ?>
                                    (Payment Required)
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Event Statistics -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">analytics</span>
                Event Overview
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Events:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($events); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">My Registrations:</span>
                    <span class="font-medium text-[#181210]"><?php echo count($my_events); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Pending Payments:</span>
                    <span class="font-medium text-[#181210]">
                        <?php 
                        $pending_count = 0;
                        foreach ($my_events as $event) {
                            if ($event['payment_status'] === 'pending' && $event['requires_payment']) {
                                $pending_count++;
                            }
                        }
                        echo $pending_count;
                        ?>
                    </span>
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
</script>