<?php
// session_start(); // Remove this - session already active
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle event creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_event'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $venue = trim($_POST['venue']);
    $event_type = $_POST['event_type'];
    $audience = $_POST['audience'];
    $requires_payment = isset($_POST['requires_payment']) ? 1 : 0;
    $payment_amount = $requires_payment ? floatval($_POST['payment_amount']) : 0.00;
    $max_participants = !empty($_POST['max_participants']) ? intval($_POST['max_participants']) : NULL;
    
    // Handle specific classes
    $specific_classes = [];
    if ($audience === 'specific_classes' && isset($_POST['specific_classes'])) {
        $specific_classes = $_POST['specific_classes'];
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO events (title, description, event_date, event_time, venue, event_type, audience, specific_classes, requires_payment, payment_amount, max_participants, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $specific_classes_json = !empty($specific_classes) ? json_encode($specific_classes) : NULL;
        
        if ($stmt->execute([$title, $description, $event_date, $event_time, $venue, $event_type, $audience, $specific_classes_json, $requires_payment, $payment_amount, $max_participants, $_SESSION['user_id']])) {
            $success_message = "Event created successfully!";
            // Clear form
            $_POST = [];
        } else {
            $error_message = "Failed to create event. Please try again.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get upcoming events for preview
$upcoming_events = $db->query("
    SELECT * FROM events 
    WHERE event_date >= CURDATE() AND status = 'active'
    ORDER BY event_date, event_time 
    LIMIT 5
")->fetchAll();

// Get event statistics
$total_events = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
$upcoming_count = $db->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND status = 'active'")->fetchColumn();
$paid_events = $db->query("SELECT COUNT(*) FROM events WHERE requires_payment = 1")->fetchColumn();
?>

<!-- Create Event Content -->
<div class="mb-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210] mb-2">Create New Event</h1>
            <p class="text-[#8d6a5e]">Add events that will appear on student and staff dashboards</p>
        </div>
        <div class="mt-4 lg:mt-0">
            <a href="?page=event-registrations" 
               class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                <span class="material-symbols-outlined mr-2 text-lg">list_alt</span>
                View Registrations
            </a>
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Event Creation Form -->
    <div class="lg:col-span-2">
        <section class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-6 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">add_circle</span>
                Event Details
            </h3>
            
            <form method="POST" class="space-y-6">
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Event Title *</label>
                        <input type="text" name="title" required 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Enter event title">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Description</label>
                        <textarea name="description" rows="4"
                                  class="w-full px-4 py-3 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933] resize-none"
                                  placeholder="Describe the event..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Event Date *</label>
                        <input type="date" name="event_date" required 
                               value="<?php echo htmlspecialchars($_POST['event_date'] ?? ''); ?>"
                               min="<?php echo date('Y-m-d'); ?>"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Event Time</label>
                        <input type="time" name="event_time" 
                               value="<?php echo htmlspecialchars($_POST['event_time'] ?? ''); ?>"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Venue</label>
                        <input type="text" name="venue" 
                               value="<?php echo htmlspecialchars($_POST['venue'] ?? ''); ?>"
                               class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                               placeholder="Event location">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Event Type</label>
                        <select name="event_type" 
                                class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                            <option value="academic" <?php echo ($_POST['event_type'] ?? 'academic') === 'academic' ? 'selected' : ''; ?>>Academic</option>
                            <option value="social" <?php echo ($_POST['event_type'] ?? '') === 'social' ? 'selected' : ''; ?>>Social</option>
                            <option value="sports" <?php echo ($_POST['event_type'] ?? '') === 'sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="cultural" <?php echo ($_POST['event_type'] ?? '') === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                            <option value="other" <?php echo ($_POST['event_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <!-- Audience & Restrictions -->
                <div class="border-t border-[#e7deda] pt-6">
                    <h4 class="text-md font-bold text-[#181210] mb-4">Audience & Restrictions</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Target Audience</label>
                            <select name="audience" id="audienceSelect" 
                                    class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]">
                                <option value="all" <?php echo ($_POST['audience'] ?? 'all') === 'all' ? 'selected' : ''; ?>>All Users</option>
                                <option value="students" <?php echo ($_POST['audience'] ?? '') === 'students' ? 'selected' : ''; ?>>Students Only</option>
                                <option value="staff" <?php echo ($_POST['audience'] ?? '') === 'staff' ? 'selected' : ''; ?>>Staff Only</option>
                                <option value="parents" <?php echo ($_POST['audience'] ?? '') === 'parents' ? 'selected' : ''; ?>>Parents Only</option>
                                <option value="specific_classes" <?php echo ($_POST['audience'] ?? '') === 'specific_classes' ? 'selected' : ''; ?>>Specific Classes</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Max Participants (Optional)</label>
                            <input type="number" name="max_participants" 
                                   value="<?php echo htmlspecialchars($_POST['max_participants'] ?? ''); ?>"
                                   min="1"
                                   class="w-full h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                                   placeholder="Leave empty for unlimited">
                        </div>
                    </div>
                    
                    <!-- Specific Classes Selection (Hidden by default) -->
                    <div id="specificClassesSection" class="mt-4 hidden">
                        <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Select Classes</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            <?php
                            // Get all classes
                            $classes = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' ORDER BY class")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($classes as $class): ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="specific_classes[]" value="<?php echo htmlspecialchars($class); ?>" 
                                           class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                           <?php echo (in_array($class, $_POST['specific_classes'] ?? [])) ? 'checked' : ''; ?>>
                                    <span class="text-sm text-[#181210]"><?php echo htmlspecialchars($class); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Payment Settings -->
                <div class="border-t border-[#e7deda] pt-6">
                    <h4 class="text-md font-bold text-[#181210] mb-4">Payment Settings</h4>
                    
                    <div class="space-y-4">
                        <label class="flex items-center space-x-3">
                            <input type="checkbox" name="requires_payment" id="requiresPayment" 
                                   class="rounded border-[#e7deda] text-[#ff6933] focus:ring-[#ff6933]"
                                   <?php echo isset($_POST['requires_payment']) ? 'checked' : ''; ?>>
                            <span class="text-sm font-medium text-[#181210]">This event requires payment</span>
                        </label>
                        
                        <div id="paymentAmountSection" class="<?php echo isset($_POST['requires_payment']) ? '' : 'hidden'; ?>">
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Amount (₦)</label>
                            <input type="number" name="payment_amount" 
                                   value="<?php echo htmlspecialchars($_POST['payment_amount'] ?? '0.00'); ?>"
                                   step="0.01" min="0"
                                   class="w-full md:w-48 h-12 px-4 border border-[#e7deda] rounded-lg bg-white text-[#181210] focus:outline-none focus:border-[#ff6933]"
                                   placeholder="0.00">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4 border-t border-[#e7deda]">
                    <button type="submit" name="create_event"
                            class="inline-flex items-center h-12 px-8 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                        <span class="material-symbols-outlined mr-2">event_add</span>
                        Create Event
                    </button>
                </div>
            </form>
        </section>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Quick Stats -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">analytics</span>
                Event Statistics
            </h3>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Total Events:</span>
                    <span class="font-medium text-[#181210]"><?php echo $total_events; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Upcoming Events:</span>
                    <span class="font-medium text-[#181210]"><?php echo $upcoming_count; ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Paid Events:</span>
                    <span class="font-medium text-[#181210]"><?php echo $paid_events; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Events Preview -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">upcoming</span>
                Upcoming Events
            </h3>
            
            <div class="space-y-4">
                <?php if (empty($upcoming_events)): ?>
                    <p class="text-[#8d6a5e] text-center py-4">No upcoming events</p>
                <?php else: ?>
                    <?php foreach ($upcoming_events as $event): ?>
                    <div class="border border-[#e7deda] rounded-lg p-3">
                        <h4 class="font-bold text-[#181210] text-sm"><?php echo htmlspecialchars($event['title']); ?></h4>
                        <p class="text-xs text-[#8d6a5e]">
                            <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                            <?php if ($event['event_time']): ?>
                                • <?php echo date('g:i A', strtotime($event['event_time'])); ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($event['requires_payment']): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 mt-1">
                                ₦<?php echo number_format($event['payment_amount'], 2); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Tips -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">lightbulb</span>
                Quick Tips
            </h3>
            
            <div class="space-y-3 text-sm text-[#8d6a5e]">
                <p>• Use specific classes to target events to particular groups</p>
                <p>• Set payment amounts for events requiring fees</p>
                <p>• Add venue details for better event organization</p>
                <p>• Set participant limits for capacity management</p>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle payment amount field
    document.getElementById('requiresPayment').addEventListener('change', function() {
        const paymentSection = document.getElementById('paymentAmountSection');
        if (this.checked) {
            paymentSection.classList.remove('hidden');
        } else {
            paymentSection.classList.add('hidden');
        }
    });

    // Toggle specific classes selection
    document.getElementById('audienceSelect').addEventListener('change', function() {
        const classesSection = document.getElementById('specificClassesSection');
        if (this.value === 'specific_classes') {
            classesSection.classList.remove('hidden');
        } else {
            classesSection.classList.add('hidden');
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Check initial state of payment checkbox
        const paymentCheckbox = document.getElementById('requiresPayment');
        const paymentSection = document.getElementById('paymentAmountSection');
        if (paymentCheckbox.checked) {
            paymentSection.classList.remove('hidden');
        }

        // Check initial state of audience select
        const audienceSelect = document.getElementById('audienceSelect');
        const classesSection = document.getElementById('specificClassesSection');
        if (audienceSelect.value === 'specific_classes') {
            classesSection.classList.remove('hidden');
        }
    });
</script>