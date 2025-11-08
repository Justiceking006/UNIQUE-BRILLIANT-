<?php
// collect_fees_content.php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$db = getDBConnection();
$success_message = '';
$error_message = '';

// Handle fee collection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_fee'])) {
    $student_id = intval($_POST['student_id']);
    $term = sanitize($_POST['term']);
    $amount = floatval($_POST['amount']);
    $description = sanitize($_POST['description']);
    
    try {
        // Create unpaid fee transaction
        $stmt = $db->prepare("
            INSERT INTO fee_transactions 
            (student_id, term, fee_type, description, amount, transaction_type, status, due_date, created_at)
            VALUES (?, ?, 'tuition', ?, ?, 'fee_issued', 'unpaid', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ");
        $stmt->execute([$student_id, $term, $description, $amount]);
        
        $success_message = "Fee of ₦" . number_format($amount, 2) . " issued successfully for selected term!";
        
    } catch (PDOException $e) {
        $error_message = "Failed to issue fee: " . $e->getMessage();
    }
}

// Handle setting total fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_total_fee'])) {
    $student_id = intval($_POST['student_id']);
    $term = sanitize($_POST['term']);
    $total_fee = floatval($_POST['total_fee']);
    
    try {
        // Check if fee record already exists
        $check_stmt = $db->prepare("SELECT id FROM student_fees WHERE student_id = ? AND term = ?");
        $check_stmt->execute([$student_id, $term]);
        
        if ($check_stmt->fetch()) {
            // Update existing
            $stmt = $db->prepare("UPDATE student_fees SET total_fee = ? WHERE student_id = ? AND term = ?");
            $stmt->execute([$total_fee, $student_id, $term]);
        } else {
            // Insert new
            $stmt = $db->prepare("
                INSERT INTO student_fees (student_id, term, total_fee, academic_year) 
                VALUES (?, ?, ?, '2024/2025')
            ");
            $stmt->execute([$student_id, $term, $total_fee]);
        }
        
        $success_message = "Total fee for term set to ₦" . number_format($total_fee, 2) . " successfully!";
        
    } catch (PDOException $e) {
        $error_message = "Failed to set total fee: " . $e->getMessage();
    }
}

// Handle recording past payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_past_payment'])) {
    $student_id = intval($_POST['student_id']);
    $term = sanitize($_POST['term']);
    $amount = floatval($_POST['payment_amount']);
    $payment_date = sanitize($_POST['payment_date']);
    $payment_method = sanitize($_POST['payment_method']);
    $reference = sanitize($_POST['reference']);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO fee_transactions 
            (student_id, term, fee_type, description, amount, transaction_type, status, created_at)
            VALUES (?, ?, 'tuition', ?, ?, 'payment', 'paid', ?)
        ");
        $description = "Past payment - " . $payment_method . ($reference ? " (Ref: $reference)" : "");
        $stmt->execute([$student_id, $term, $description, $amount, $payment_date]);
        
        $success_message = "Past payment of ₦" . number_format($amount, 2) . " recorded successfully!";
        
    } catch (PDOException $e) {
        $error_message = "Failed to record past payment: " . $e->getMessage();
    }
}
?>

<!-- Headline Text -->
<h1 class="text-[32px] font-bold leading-tight tracking-tight text-[#181210]">Fee Collection</h1>
<p class="text-base font-normal leading-normal text-[#8d6a5e] pt-1">Manage student fees and payments by term</p>

<!-- Success/Error Messages -->
<?php if (!empty($success_message)): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-green-100 p-4 border border-green-300">
    <span class="material-symbols-outlined text-green-600 text-2xl">check_circle</span>
    <p class="text-sm font-medium text-green-700"><?php echo $success_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-green-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="mt-6 flex items-center gap-3 rounded-lg bg-red-100 p-4 border border-red-300">
    <span class="material-symbols-outlined text-red-600 text-2xl">error</span>
    <p class="text-sm font-medium text-red-700"><?php echo $error_message; ?></p>
    <button class="ml-auto" onclick="this.parentElement.remove()">
        <span class="material-symbols-outlined text-red-600/70">close</span>
    </button>
</div>
<?php endif; ?>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Student Selection Card -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">search</span>
                Select Student
            </h3>
            
            <form id="studentSelectForm" class="space-y-4">
                <!-- Section Selection -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Section</label>
                    <select name="section" required 
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Select Section</option>
                        <option value="primary">Primary</option>
                        <option value="secondary">Secondary</option>
                    </select>
                </div>
                
                <!-- Class Selection - Dynamic -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Class</label>
                    <select name="class" required 
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Select Class First</option>
                    </select>
                </div>
                
                <!-- Student Selection -->
                <div>
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Student</label>
                    <select name="student_id" required 
                            class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        <option value="">Select Student</option>
                    </select>
                </div>
                
                <button type="button" onclick="loadStudentFeeInfo()" 
                        class="w-full h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                    <span class="material-symbols-outlined mr-2">person_search</span>
                    Load Student Info
                </button>
            </form>
        </div>
    </div>

    <!-- Fee Management Card -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <h3 class="text-lg font-bold text-[#181210] mb-4 flex items-center">
                <span class="material-symbols-outlined mr-2 text-[#ff6933]">payments</span>
                Fee Management
            </h3>
            
            <div id="feeManagementSection" class="hidden">
                <!-- Term Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-[#8d6a5e] mb-3">Select Term</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button type="button" onclick="selectTerm('first_term')" 
                                class="term-btn h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] font-medium hover:border-[#ff6933] hover:text-[#ff6933] transition-colors"
                                data-term="first_term">
                            First Term
                        </button>
                        <button type="button" onclick="selectTerm('second_term')" 
                                class="term-btn h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] font-medium hover:border-[#ff6933] hover:text-[#ff6933] transition-colors"
                                data-term="second_term">
                            Second Term
                        </button>
                        <button type="button" onclick="selectTerm('third_term')" 
                                class="term-btn h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] font-medium hover:border-[#ff6933] hover:text-[#ff6933] transition-colors"
                                data-term="third_term">
                            Third Term
                        </button>
                    </div>
                </div>
                
                <!-- Student Info Display -->
                <div id="studentInfo" class="mb-6 p-4 bg-[#f8f6f5] rounded-lg hidden">
                    <!-- Loaded by JavaScript -->
                </div>
                
                <!-- Fee Setup Form -->
                <form id="feeSetupForm" method="POST" class="space-y-4 mb-6 hidden">
                    <input type="hidden" name="student_id" id="feeStudentId">
                    <input type="hidden" name="term" id="feeTerm">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Total Term Fee</label>
                            <input type="number" name="total_fee" id="totalFee" step="0.01" min="0" required
                                   class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        </div>
                        <div class="md:col-span-2 flex items-end">
                            <button type="submit" name="set_total_fee" 
                                    class="w-full h-12 rounded-lg bg-green-600 text-white font-bold hover:bg-green-700 transition-colors flex items-center justify-center">
                                <span class="material-symbols-outlined mr-2">save</span>
                                Set Total Fee
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Current Status -->
                <div id="currentStatus" class="mb-6 p-4 bg-[#f8f6f5] rounded-lg hidden">
                    <!-- Loaded by JavaScript -->
                </div>
                
                <!-- Collect New Fee -->
                <form id="collectFeeForm" method="POST" class="space-y-4 hidden">
                    <input type="hidden" name="student_id" id="collectStudentId">
                    <input type="hidden" name="term" id="collectTerm">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Amount to Collect</label>
                            <input type="number" name="amount" id="collectAmount" step="0.01" min="0.01" required
                                   class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Description</label>
                            <input type="text" name="description" value="Tuition Fee" required
                                   class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                        </div>
                    </div>
                    
                    <button type="submit" name="collect_fee" 
                            class="w-full h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors flex items-center justify-center">
                        <span class="material-symbols-outlined mr-2">payments</span>
                        Issue Fee
                    </button>
                </form>
                
                <!-- Record Past Payment Button -->
                <div class="text-center">
                    <button type="button" onclick="openPastPaymentModal()" 
                            class="inline-flex items-center h-12 px-6 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                        <span class="material-symbols-outlined mr-2">history</span>
                        Record Past Payment
                    </button>
                </div>
            </div>
            
            <!-- Initial State -->
            <div id="initialState" class="text-center py-12">
                <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">school</span>
                <h3 class="text-xl font-bold text-[#181210] mb-2">Select a Student</h3>
                <p class="text-[#8d6a5e] max-w-md mx-auto">
                    Choose a student from the left panel to view and manage their fee information.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Past Payment Modal -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4" id="pastPaymentModal">
    <div class="bg-white rounded-xl w-full max-w-md">
        <div class="p-4 border-b border-[#e7deda]">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-bold text-[#181210]">Record Past Payment</h3>
                <button onclick="closePastPaymentModal()" class="text-[#8d6a5e] hover:text-[#181210]">
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
        
        <form method="POST" class="p-4 space-y-4" onsubmit="return validatePastPayment()">
            <input type="hidden" name="student_id" id="pastPaymentStudentId">
            <input type="hidden" name="term" id="pastPaymentTerm">
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Amount</label>
                <input type="number" name="payment_amount" step="0.01" min="0.01" required
                       class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Date</label>
                <input type="date" name="payment_date" required
                       class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Payment Method</label>
                <select name="payment_method" required
                        class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
                    <option value="">Select Method</option>
                    <option value="cash">Cash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cheque">Cheque</option>
                    <option value="pos">POS</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Reference Number (Optional)</label>
                <input type="text" name="reference"
                       class="w-full h-12 rounded-lg border border-[#e7deda] bg-white p-3 text-[#181210] focus:outline-none focus:border-[#ff6933]">
            </div>
            
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closePastPaymentModal()" 
                        class="flex-1 h-12 rounded-lg border border-[#e7deda] text-[#8d6a5e] hover:bg-[#f8f6f5] transition-colors">
                    Cancel
                </button>
                <button type="submit" name="record_past_payment" 
                        class="flex-1 h-12 rounded-lg bg-[#ff6933] text-white font-bold hover:bg-[#ff6933]/90 transition-colors">
                    Record Payment
                </button>
            </div>
        </form>
    </div>
</div>
<script>
// Global variables
let currentStudentId = null;
let currentStudentData = null;
let currentTerm = null;
let currentSection = null;
let currentClass = null;

// Define class options based on your registration structure
const classOptions = {
    primary: [
        'Nursery 1', 'Nursery 2', 'Nursery 3',
        'Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'
    ],
    secondary: [
        'JSS1', 'JSS2', 'JSS3', 'SSS1', 'SSS2', 'SSS3'
    ]
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Populate classes when section changes
    document.querySelector('select[name="section"]').addEventListener('change', function() {
        currentSection = this.value;
        populateClasses(currentSection);
    });

    // Load students when class is selected
    document.querySelector('select[name="class"]').addEventListener('change', function() {
        currentClass = this.value;
        if (currentSection && currentClass) {
            loadStudents(currentSection, currentClass);
        }
    });

    // Auto-load student info when student is selected
    document.querySelector('select[name="student_id"]').addEventListener('change', function() {
        if (this.value) {
            loadStudentFeeInfo(this.value);
        }
    });

    // Try to restore state from URL parameters or form submissions
    restoreState();
});

function populateClasses(section) {
    const classSelect = document.querySelector('select[name="class"]');
    const studentSelect = document.querySelector('select[name="student_id"]');
    
    // Clear existing options
    classSelect.innerHTML = '<option value="">Select Class</option>';
    studentSelect.innerHTML = '<option value="">Select Student</option>';
    
    if (section && classOptions[section]) {
        classOptions[section].forEach(className => {
            const option = document.createElement('option');
            option.value = className;
            option.textContent = className;
            classSelect.appendChild(option);
        });
    }
}

function loadStudents(section, classValue) {
    // Show loading in student dropdown
    const studentSelect = document.querySelector('select[name="student_id"]');
    studentSelect.innerHTML = '<option value="">Loading students...</option>';
    
    fetch(`get_students_by_class.php?section=${section}&class=${classValue}`)
        .then(response => response.json())
        .then(students => {
            studentSelect.innerHTML = '<option value="">Select Student</option>';
            
            if (students.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No students found in this class';
                studentSelect.appendChild(option);
            } else {
                students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student.id;
                    let displayText = `${student.first_name} ${student.last_name}`;
                    if (student.student_code) {
                        displayText += ` (${student.student_code})`;
                    }
                    option.textContent = displayText;
                    studentSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading students:', error);
            studentSelect.innerHTML = '<option value="">Error loading students</option>';
        });
}

// Load student fee information
function loadStudentFeeInfo(studentId = null) {
    const selectedStudentId = studentId || document.querySelector('select[name="student_id"]').value;
    
    if (!selectedStudentId || selectedStudentId === '') {
        alert('Please select a student');
        return;
    }

    currentStudentId = selectedStudentId;
    
    // Show loading state
    document.getElementById('feeManagementSection').classList.remove('hidden');
    document.getElementById('initialState').classList.add('hidden');
    document.getElementById('studentInfo').innerHTML = `
        <div class="flex items-center justify-center py-4">
            <span class="material-symbols-outlined animate-spin text-[#ff6933] mr-2">refresh</span>
            Loading student information...
        </div>
    `;
    
    // Fetch student data
    fetch(`get_student_details.php?id=${currentStudentId}`)
        .then(response => response.json())
        .then(student => {
            currentStudentData = student;
            
            // Update student info display
            let studentDetails = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-[#ff6933]/10 mr-3">
                            <span class="text-[#ff6933] text-lg font-bold">
                                ${student.first_name.charAt(0)}${student.last_name.charAt(0)}
                            </span>
                        </div>
                        <div>
                            <p class="font-bold text-[#181210]">${student.first_name} ${student.last_name}</p>
                            <p class="text-sm text-[#8d6a5e]">${student.student_code} • ${student.class} • ${student.section}</p>
            `;
            
            // Add level and department for secondary students
            if (student.section === 'secondary') {
                if (student.level) {
                    studentDetails += `<p class="text-xs text-[#8d6a5e]">${student.level.toUpperCase()}`;
                    if (student.department) {
                        studentDetails += ` • ${student.department.charAt(0).toUpperCase() + student.department.slice(1)}`;
                    }
                    studentDetails += `</p>`;
                }
            }
            
            studentDetails += `
                        </div>
                    </div>
                    <span class="material-symbols-outlined text-green-500">verified</span>
                </div>
            `;
            
            document.getElementById('studentInfo').innerHTML = studentDetails;
            document.getElementById('studentInfo').classList.remove('hidden');
            
            // Set hidden fields
            document.getElementById('feeStudentId').value = currentStudentId;
            document.getElementById('collectStudentId').value = currentStudentId;
            document.getElementById('pastPaymentStudentId').value = currentStudentId;
            
            // Show fee setup form
            document.getElementById('feeSetupForm').classList.remove('hidden');
            
            // Auto-select first term if no term is selected
            if (!currentTerm) {
                selectTerm('first_term');
            } else {
                // Reload term data for current student
                loadTermFeeData(currentTerm);
            }
            
            // Update URL with current state (without page reload)
            updateURLState();
            
        })
        .catch(error => {
            console.error('Error loading student:', error);
            document.getElementById('studentInfo').innerHTML = `
                <div class="text-center text-red-600 py-4">
                    <span class="material-symbols-outlined mr-2">error</span>
                    Error loading student information
                </div>
            `;
        });
}

// Term selection
function selectTerm(term) {
    currentTerm = term;
    
    // Update UI
    document.querySelectorAll('.term-btn').forEach(btn => {
        if (btn.dataset.term === term) {
            btn.classList.add('bg-[#ff6933]', 'text-white', 'border-[#ff6933]');
            btn.classList.remove('text-[#8d6a5e]', 'border-[#e7deda]');
        } else {
            btn.classList.remove('bg-[#ff6933]', 'text-white', 'border-[#ff6933]');
            btn.classList.add('text-[#8d6a5e]', 'border-[#e7deda]');
        }
    });
    
    // Set term in forms
    document.getElementById('feeTerm').value = term;
    document.getElementById('collectTerm').value = term;
    document.getElementById('pastPaymentTerm').value = term;
    
    // Load term-specific data if student is selected
    if (currentStudentId) {
        loadTermFeeData(term);
    }
    
    // Update URL state
    updateURLState();
}

// Load term fee data
function loadTermFeeData(term) {
    if (!currentStudentId) return;
    
    // Show loading
    document.getElementById('currentStatus').innerHTML = `
        <div class="flex items-center justify-center py-4">
            <span class="material-symbols-outlined animate-spin text-[#ff6933] mr-2">refresh</span>
            Loading fee data...
        </div>
    `;
    document.getElementById('currentStatus').classList.remove('hidden');
    
    fetch(`get_student_fee_data.php?student_id=${currentStudentId}&term=${term}`)
        .then(response => response.json())
        .then(data => {
            // Update total fee if exists
            if (data.total_fee !== null) {
                document.getElementById('totalFee').value = data.total_fee;
            } else {
                document.getElementById('totalFee').value = '';
            }
            
            // Display current status
            const paidAmount = data.paid_amount || 0;
            const totalFee = data.total_fee || 0;
            const balance = totalFee - paidAmount;
            const balanceClass = balance > 0 ? 'text-red-600' : 'text-green-600';
            const balanceText = balance > 0 ? `₦${balance.toLocaleString()}` : 'Paid in Full';
            
            document.getElementById('currentStatus').innerHTML = `
                <h4 class="font-semibold text-[#181210] mb-3">Current Status - ${term.replace('_', ' ').toUpperCase()}</h4>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="p-3 bg-white rounded-lg border border-[#e7deda]">
                        <p class="text-sm text-[#8d6a5e]">Total Fee</p>
                        <p class="text-lg font-bold text-[#181210]">₦${totalFee.toLocaleString()}</p>
                    </div>
                    <div class="p-3 bg-white rounded-lg border border-[#e7deda]">
                        <p class="text-sm text-[#8d6a5e]">Paid</p>
                        <p class="text-lg font-bold text-green-600">₦${paidAmount.toLocaleString()}</p>
                    </div>
                    <div class="p-3 bg-white rounded-lg border border-[#e7deda]">
                        <p class="text-sm text-[#8d6a5e]">Balance</p>
                        <p class="text-lg font-bold ${balanceClass}">${balanceText}</p>
                    </div>
                </div>
            `;
            
            // Show collect fee form
            document.getElementById('collectFeeForm').classList.remove('hidden');
            
        })
        .catch(error => {
            console.error('Error loading fee data:', error);
            document.getElementById('currentStatus').innerHTML = `
                <div class="text-center text-red-600 py-4">
                    <span class="material-symbols-outlined mr-2">error</span>
                    Error loading fee data
                </div>
            `;
        });
}

// Update URL with current state
function updateURLState() {
    if (currentStudentId && currentTerm) {
        const url = new URL(window.location);
        url.searchParams.set('student_id', currentStudentId);
        url.searchParams.set('term', currentTerm);
        window.history.replaceState({}, '', url);
    }
}

// Restore state from URL parameters
function restoreState() {
    const urlParams = new URLSearchParams(window.location.search);
    const studentId = urlParams.get('student_id');
    const term = urlParams.get('term');
    
    if (studentId && term) {
        // Set the student and term from URL
        currentStudentId = studentId;
        currentTerm = term;
        
        // Load the student info directly
        loadStudentFeeInfo(studentId);
        
        // Select the term
        setTimeout(() => {
            selectTerm(term);
        }, 500);
    }
}

// Modify form submissions to preserve state
document.addEventListener('DOMContentLoaded', function() {
    // Intercept form submissions to preserve state
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            // Add current state to form as hidden fields
            if (currentStudentId) {
                let hiddenInput = form.querySelector('input[name="student_id"]');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'student_id';
                    form.appendChild(hiddenInput);
                }
                hiddenInput.value = currentStudentId;
            }
            
            if (currentTerm) {
                let hiddenInput = form.querySelector('input[name="term"]');
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'term';
                    form.appendChild(hiddenInput);
                }
                hiddenInput.value = currentTerm;
            }
            
            // The form will submit normally, but we'll restore state on page reload
        });
    });
});

// Past Payment Modal
function openPastPaymentModal() {
    if (!currentStudentId || !currentTerm) {
        alert('Please select a student and term first');
        return;
    }
    
    // Set today as default date
    document.querySelector('input[name="payment_date"]').valueAsDate = new Date();
    
    document.getElementById('pastPaymentModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closePastPaymentModal() {
    document.getElementById('pastPaymentModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function validatePastPayment() {
    const amount = document.querySelector('input[name="payment_amount"]').value;
    const date = document.querySelector('input[name="payment_date"]').value;
    const method = document.querySelector('select[name="payment_method"]').value;
    
    if (!amount || amount <= 0) {
        alert('Please enter a valid payment amount');
        return false;
    }
    
    if (!date) {
        alert('Please select a payment date');
        return false;
    }
    
    if (!method) {
        alert('Please select a payment method');
        return false;
    }
    
    return confirm('Are you sure you want to record this past payment?');
}

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.id === 'pastPaymentModal') {
        closePastPaymentModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePastPaymentModal();
    }
});

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