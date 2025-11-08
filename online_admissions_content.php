<?php
// online_admissions_content.php - Redesigned with your layout
session_start();
require_once 'connect.php';

// Check if user is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_student'])) {
        $student_id = intval($_POST['student_id']);
        
        // Generate unique 6-digit PIN
        function generateUniquePIN($db) {
            do {
                $pin = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
                $stmt = $db->prepare("SELECT COUNT(*) FROM students WHERE student_pin = ?");
                $stmt->execute([$pin]);
            } while ($stmt->fetchColumn() > 0);
            return $pin;
        }
        
        $student_pin = generateUniquePIN($db);
        
        // Update student status and set PIN
        $stmt = $db->prepare("UPDATE students SET status = 'approved', student_pin = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
        $stmt->execute([$student_pin, $_SESSION['user_id'], $student_id]);
        
        $success_message = "Student approved successfully! PIN: " . $student_pin;
        
    } elseif (isset($_POST['reject_student'])) {
        $student_id = intval($_POST['student_id']);
        
        $stmt = $db->prepare("UPDATE students SET status = 'rejected', approved_at = NOW(), approved_by = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $student_id]);
        
        $success_message = "Student application rejected.";
    }
}

// Get ONLY pending applications - no filters
$stmt = $db->prepare("SELECT * FROM students WHERE status = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$applications = $stmt->fetchAll();

// Get counts
$pending_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'pending'")->fetchColumn();
$approved_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'approved'")->fetchColumn();
$paid_count = $db->query("SELECT COUNT(*) FROM students WHERE status = 'pending' AND admission_fee_paid = 1")->fetchColumn();
?>

<!-- Headline Text -->
<h1 class="text-[32px] font-bold leading-tight tracking-tight text-[#181210]">Admissions Dashboard</h1>

<!-- Body Text -->
<p class="text-base font-normal leading-normal text-[#8d6a5e] pt-1">Review Pending Applications & Payment Receipts</p>

<!-- Success Alert -->
<?php if (isset($success_message)): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo $success_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="flex flex-col gap-2 rounded-xl p-6 bg-white border border-[#e7deda]">
        <p class="text-base font-medium text-orange-500 flex items-center gap-2">
            <span class="material-symbols-outlined text-xl">hourglass_top</span>
            Pending Review
        </p>
        <p class="tracking-light text-3xl font-bold text-[#181210]"><?php echo $pending_count; ?></p>
    </div>
    <div class="flex flex-col gap-2 rounded-xl p-6 bg-white border border-[#e7deda]">
        <p class="text-base font-medium text-green-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-xl">verified</span>
            Approved
        </p>
        <p class="tracking-light text-3xl font-bold text-[#181210]"><?php echo $approved_count; ?></p>
    </div>
    <div class="flex flex-col gap-2 rounded-xl p-6 bg-white border border-[#e7deda]">
        <p class="text-base font-medium text-purple-600 flex items-center gap-2">
            <span class="material-symbols-outlined text-xl">payments</span>
            Paid Fees
        </p>
        <p class="tracking-light text-3xl font-bold text-[#181210]"><?php echo $paid_count; ?></p>
    </div>
</div>

<!-- Section Header -->
<h2 class="text-[22px] font-bold leading-tight tracking-[-0.015em] pt-8 pb-3 text-[#181210]">Pending Applications</h2>

<!-- Applications List -->
<div class="space-y-3">
    <?php if (empty($applications)): ?>
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-[#e7deda] bg-white p-12 text-center">
            <span class="material-symbols-outlined text-5xl text-[#8d6a5e]">inbox</span>
            <p class="mt-4 text-lg font-bold text-[#181210]">All caught up!</p>
            <p class="mt-1 text-[#8d6a5e]">There are no pending applications to review.</p>
        </div>
    <?php else: ?>
        <?php foreach ($applications as $application): ?>
        <div class="flex items-center gap-4 rounded-xl p-4 bg-white border border-[#e7deda]">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ff6933]/10">
                <span class="text-[#ff6933] text-xl font-bold">
                    <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
                </span>
            </div>
            <div class="flex-1">
                <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                <p class="text-sm text-[#8d6a5e]">
                    ID: <?php echo htmlspecialchars($application['student_code']); ?> | 
                    Submitted: <?php echo date('d/m/Y', strtotime($application['created_at'])); ?>
                </p>
                <p class="text-xs text-[#8d6a5e] mt-1">
                    Class: <?php echo htmlspecialchars($application['class']); ?> | 
                    Section: <?php echo ucfirst($application['section']); ?>
                    <?php if ($application['admission_fee_paid']): ?>
                        | <span class="text-green-600 font-medium">Fee Paid</span>
                    <?php else: ?>
                        | <span class="text-orange-600 font-medium">Fee Pending</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex gap-2">
                <?php if ($application['admission_receipt_filename']): ?>
                <button onclick="viewReceipt('<?php echo htmlspecialchars($application['admission_receipt_filename']); ?>')" 
                        class="flex items-center justify-center rounded-lg bg-purple-100 px-3 py-2 text-sm font-bold text-purple-600"
                        title="View Receipt">
                    <span class="material-symbols-outlined text-base mr-1">receipt</span>
                    Receipt
                </button>
                <?php endif; ?>
                <button onclick="openApplicationModal(<?php echo htmlspecialchars(json_encode($application)); ?>)"
                        class="flex items-center justify-center rounded-lg bg-[#ff6933]/10 px-4 py-2 text-sm font-bold text-[#ff6933]">
                    View Details
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Application Details Modal -->
<div id="applicationModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center hidden">
    <div class="flex h-full max-h-[90%] w-full flex-col rounded-t-xl bg-white sm:max-w-lg sm:rounded-xl">
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-[#e7deda] p-4">
            <h3 class="text-lg font-bold text-[#181210]" id="modalTitle">Application Details</h3>
            <button onclick="closeApplicationModal()" class="flex h-8 w-8 items-center justify-center rounded-full bg-white hover:bg-[#f8f6f5]">
                <span class="material-symbols-outlined text-[#8d6a5e]">close</span>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6">
            <div class="space-y-6" id="modalContent">
                <!-- Content loaded by JavaScript -->
            </div>
        </div>
        
        <!-- Modal Footer Actions -->
        <div class="flex gap-3 border-t border-[#e7deda] p-4 bg-white">
            <form method="POST" class="flex-1" onsubmit="return confirm('Reject this application?')" id="rejectForm">
                <input type="hidden" name="student_id" id="rejectStudentId">
                <button type="submit" name="reject_student" 
                        class="flex h-12 w-full items-center justify-center rounded-lg bg-red-100 text-sm font-bold text-red-600 hover:bg-red-200">
                    Reject Application
                </button>
            </form>
            <form method="POST" class="flex-1" onsubmit="return confirm('Approve this application?')" id="approveForm">
                <input type="hidden" name="student_id" id="approveStudentId">
                <button type="submit" name="approve_student" 
                        class="flex h-12 w-full items-center justify-center rounded-lg bg-[#ff6933] text-sm font-bold text-white hover:bg-[#ff6933]/90">
                    Approve Application
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" id="receiptModal">
    <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col mx-4">
        <div class="p-4 border-b border-[#e7deda] bg-white flex-shrink-0">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Admission Fee Receipt</h3>
                <div class="flex gap-2">
                    <button onclick="printReceiptImage()" 
                            class="inline-flex items-center px-3 py-1 text-sm bg-[#ff6933] text-white rounded-lg hover:bg-[#ff6933]/90 transition-colors">
                        <span class="material-symbols-outlined mr-1 text-base">print</span>
                        Print
                    </button>
                    <button onclick="closeReceiptModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-4 flex items-center justify-center">
            <img id="receiptImage" src="" alt="Admission Fee Receipt" class="max-w-full max-h-full object-contain">
        </div>
    </div>
</div>

<script>
    // Application Details Modal
    function openApplicationModal(application) {
        const modal = document.getElementById('applicationModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalContent = document.getElementById('modalContent');
        const rejectForm = document.getElementById('rejectForm');
        const approveForm = document.getElementById('approveForm');
        const rejectStudentId = document.getElementById('rejectStudentId');
        const approveStudentId = document.getElementById('approveStudentId');
        
        // Set form values
        rejectStudentId.value = application.id;
        approveStudentId.value = application.id;
        
        // Set modal title
        modalTitle.textContent = `Application - ${application.first_name} ${application.last_name}`;
        
        // Format the level display
        let levelDisplay = '';
        if (application.level) {
            levelDisplay = application.level.toUpperCase();
        }
        
        // Format the department display
        let departmentDisplay = '';
        if (application.department) {
            departmentDisplay = application.department.charAt(0).toUpperCase() + application.department.slice(1);
        }

        // Payment status
        const paymentStatus = application.admission_fee_paid ? 
            '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">Fee Paid - ₦' + (application.admission_fee_amount || '3000.00') + '</span>' :
            '<span class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">Fee Pending</span>';
        
        // Receipt section
        const receiptSection = application.admission_receipt_filename ? `
            <div>
                <h4 class="mb-3 text-base font-bold text-[#ff6933]">Payment Receipt</h4>
                <div class="space-y-2 text-sm">
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Receipt:</strong> 
                        <button onclick="viewReceipt('${application.admission_receipt_filename}')" 
                                class="inline-flex items-center px-3 py-1 rounded-lg bg-purple-100 text-purple-600 text-sm font-medium hover:bg-purple-200">
                            <span class="material-symbols-outlined mr-1 text-base">receipt</span>
                            View Receipt
                        </button>
                    </p>
                </div>
            </div>
        ` : `
            <div>
                <h4 class="mb-3 text-base font-bold text-[#ff6933]">Payment Information</h4>
                <div class="space-y-2 text-sm">
                    <p><strong class="font-medium text-[#8d6a5e]">Payment Instructions:</strong></p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm">
                        <p class="font-medium text-yellow-800">Amount: ₦3,000 per term</p>
                        <p class="text-yellow-700">Bank: OPay</p>
                        <p class="text-yellow-700">Account: 8165790445</p>
                        <p class="text-yellow-700">Name: Justice King</p>
                        <p class="text-yellow-700 mt-2">Student should upload receipt after payment</p>
                    </div>
                </div>
            </div>
        `;
        
        modalContent.innerHTML = `
            <!-- Student Information -->
            <div>
                <h4 class="mb-3 text-base font-bold text-[#ff6933]">Student Information</h4>
                <div class="space-y-2 text-sm">
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Full Name:</strong> ${application.first_name} ${application.last_name}</p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Email:</strong> ${application.email}</p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Student Code:</strong> ${application.student_code}</p>
                </div>
            </div>
            
            <!-- Academic Information -->
            <div>
                <h4 class="mb-3 text-base font-bold text-[#ff6933]">Academic Information</h4>
                <div class="space-y-2 text-sm">
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Section:</strong> ${application.section.charAt(0).toUpperCase() + application.section.slice(1)}</p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Class:</strong> ${application.class}</p>
                    ${application.level ? `
                        <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Level:</strong> ${levelDisplay}</p>
                    ` : ''}
                    ${application.department ? `
                        <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Department:</strong> ${departmentDisplay}</p>
                    ` : ''}
                </div>
            </div>
            
            <!-- Guardian Information -->
            <div>
                <h4 class="mb-3 text-base font-bold text-[#ff6933]">Guardian Information</h4>
                <div class="space-y-2 text-sm">
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Full Name:</strong> ${application.guardian_name}</p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Phone:</strong> ${application.guardian_phone}</p>
                </div>
            </div>
            
            <!-- Payment Information -->
            ${receiptSection}
            
            <!-- Application Details -->
            <div>
                <h4 class="mb-3 text-base font-bold text-[#ff6933]">Application Details</h4>
                <div class="space-y-2 text-sm">
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Applied On:</strong> ${new Date(application.created_at).toLocaleDateString()}</p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Applied At:</strong> ${new Date(application.created_at).toLocaleTimeString()}</p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Status:</strong> 
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending Review</span>
                    </p>
                    <p><strong class="font-medium text-[#8d6a5e] w-24 inline-block">Payment:</strong> 
                        ${paymentStatus}
                    </p>
                </div>
            </div>
        `;
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeApplicationModal() {
        const modal = document.getElementById('applicationModal');
        modal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    // Receipt Modal Functions
    function viewReceipt(receiptFilename) {
        const receiptPath = `uploads/receipts/${receiptFilename}`;
        document.getElementById('receiptImage').src = receiptPath;
        document.getElementById('receiptModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }

    function printReceiptImage() {
        const receiptImg = document.getElementById('receiptImage');
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Print Receipt</title>
                <style>
                    body { margin: 0; padding: 20px; text-align: center; }
                    img { max-width: 100%; height: auto; }
                    @media print {
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <img src="${receiptImg.src}" alt="Admission Fee Receipt">
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() {
                            window.close();
                        }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    // Close modal when clicking outside (for sm screens)
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('applicationModal');
        const receiptModal = document.getElementById('receiptModal');
        if (e.target === modal) {
            closeApplicationModal();
        }
        if (e.target === receiptModal) {
            closeReceiptModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeApplicationModal();
            closeReceiptModal();
        }
    });
</script>