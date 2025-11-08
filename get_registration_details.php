<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit;
}

$registration_id = intval($_GET['id']);
$db = getDBConnection();

// Get registration details
$stmt = $db->prepare("
    SELECT er.*, e.title as event_title, e.description, e.event_date, e.event_time, e.venue, e.event_type,
           s.first_name, s.last_name, s.email, s.class, s.guardian_name, s.guardian_phone,
           st.first_name as admin_first, st.last_name as admin_last
    FROM event_registrations er 
    JOIN events e ON er.event_id = e.id 
    JOIN students s ON er.student_id = s.id 
    LEFT JOIN staff st ON er.approved_by = st.id
    WHERE er.id = ?
");
$stmt->execute([$registration_id]);
$registration = $stmt->fetch();

if (!$registration) {
    echo '<p class="text-red-600">Registration not found</p>';
    exit;
}
?>

<div class="space-y-6">
    <!-- Student Information -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <h4 class="text-lg font-bold text-[#181210] mb-3">Student Information</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Name:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Class:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['class']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Email:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['email']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Guardian:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['guardian_name']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Guardian Phone:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['guardian_phone']); ?></span>
                </div>
            </div>
        </div>

        <!-- Event Information -->
        <div>
            <h4 class="text-lg font-bold text-[#181210] mb-3">Event Information</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Event:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['event_title']); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Date:</span>
                    <span class="font-medium text-[#181210]"><?php echo date('F j, Y', strtotime($registration['event_date'])); ?></span>
                </div>
                <?php if ($registration['event_time']): ?>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Time:</span>
                    <span class="font-medium text-[#181210]"><?php echo date('g:i A', strtotime($registration['event_time'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($registration['venue']): ?>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Venue:</span>
                    <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['venue']); ?></span>
                </div>
                <?php endif; ?>
                <div class="flex justify-between">
                    <span class="text-[#8d6a5e]">Type:</span>
                    <span class="font-medium text-[#181210]"><?php echo ucfirst($registration['event_type']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Information -->
    <div>
        <h4 class="text-lg font-bold text-[#181210] mb-3">Payment Information</h4>
        <div class="bg-[#f8f6f5] rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="flex justify-between mb-2">
                        <span class="text-[#8d6a5e]">Payment Amount:</span>
                        <span class="font-bold text-[#181210]">₦<?php echo number_format($registration['payment_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between mb-2">
                        <span class="text-[#8d6a5e]">Status:</span>
                        <span class="font-medium text-[#181210]">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                <?php echo $registration['payment_status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                         ($registration['payment_status'] === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800'); ?>">
                                <?php echo ucfirst($registration['payment_status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#8d6a5e]">Registration Date:</span>
                        <span class="font-medium text-[#181210]"><?php echo date('F j, Y g:i A', strtotime($registration['registration_date'])); ?></span>
                    </div>
                </div>
                
                <?php if ($registration['approved_at']): ?>
                <div>
                    <div class="flex justify-between mb-2">
                        <span class="text-[#8d6a5e]">Processed By:</span>
                        <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($registration['admin_first'] . ' ' . $registration['admin_last']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#8d6a5e]">Processed At:</span>
                        <span class="font-medium text-[#181210]"><?php echo date('F j, Y g:i A', strtotime($registration['approved_at'])); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Receipt Image -->
    <?php if ($registration['receipt_image']): ?>
    <div>
        <h4 class="text-lg font-bold text-[#181210] mb-3">Payment Receipt</h4>
        <div class="bg-[#f8f6f5] rounded-lg p-4 text-center">
            <img src="<?php echo htmlspecialchars($registration['receipt_image']); ?>" 
                 alt="Payment Receipt" 
                 class="max-w-full h-auto mx-auto rounded-lg max-h-64 object-contain">
            <p class="text-sm text-[#8d6a5e] mt-2">Uploaded payment receipt</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Admin Notes -->
    <?php if ($registration['admin_notes']): ?>
    <div>
        <h4 class="text-lg font-bold text-[#181210] mb-3">Admin Notes</h4>
        <div class="bg-[#f8f6f5] rounded-lg p-4">
            <p class="text-[#181210]"><?php echo nl2br(htmlspecialchars($registration['admin_notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>