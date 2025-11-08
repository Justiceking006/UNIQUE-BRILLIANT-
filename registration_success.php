<?php
session_start();
if (!isset($_SESSION['student_code'])) {
    header('Location: register.php');
    exit;
}

$student_code = $_SESSION['student_code'];

// Handle receipt upload
$upload_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['receipt'])) {
    require_once 'connect.php';
    $db = getDBConnection();
    
    $upload_dir = 'uploads/receipts/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['receipt'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    if (in_array($file_extension, $allowed_extensions)) {
        if ($file['size'] <= 5 * 1024 * 1024) { // 5MB max
            $new_filename = 'receipt_' . $student_code . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Update database
                $stmt = $db->prepare("UPDATE students SET admission_receipt_image = ?, admission_receipt_filename = ?, admission_fee_paid = 1 WHERE student_code = ?");
                $stmt->execute([$file_path, $new_filename, $student_code]);
                
                $upload_message = '<div class="mb-4 p-3 bg-green-100 border border-green-300 text-green-700 rounded-lg">Receipt uploaded successfully!</div>';
            } else {
                $upload_message = '<div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg">Error uploading file. Please try again.</div>';
            }
        } else {
            $upload_message = '<div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg">File size too large. Maximum 5MB allowed.</div>';
        }
    } else {
        $upload_message = '<div class="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg">Invalid file type. Allowed: JPG, PNG, GIF, PDF</div>';
    }
}

// Keep student_code in session for potential additional uploads
// unset($_SESSION['student_code']); // Don't unset yet if you want to allow multiple upload attempts
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Unique Brilliant Schools</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Lexend', sans-serif; background-color: #f8f6f5; }
        .file-input-hidden { opacity: 0; position: absolute; width: 0.1px; height: 0.1px; }
    </style>
</head>
<body class="min-h-screen bg-[#f8f6f5]">
    <div class="flex flex-col min-h-screen justify-between p-4">
        <div class="flex flex-col items-center">
            <!-- Checkmark Icon -->
            <div class="flex h-20 w-20 items-center justify-center rounded-full bg-[#ff6933]/20 mb-6">
                <span class="material-symbols-outlined text-5xl text-[#ff6933]">check</span>
            </div>
            
            <!-- Headline -->
            <h1 class="text-[#181210] text-[32px] font-bold text-center pb-2">Registration Successful!</h1>
            
            <!-- Body Text -->
            <p class="text-[#8d6a5e] text-base font-normal text-center max-w-sm pb-8">
                Your application has been submitted for review. Complete your admission fee payment to proceed.
            </p>
            
            <!-- Upload Message -->
            <?php echo $upload_message; ?>
            
            <!-- Student Code Card -->
            <div class="flex flex-col items-stretch rounded-xl w-full bg-white p-6 shadow-sm mb-4">
                <p class="text-[#181210] text-lg font-bold text-center mb-4">Your Student Code</p>
                <div class="flex items-center gap-4 justify-between rounded-lg bg-[#f8f6f5] p-4">
                    <p class="text-[#ff6933] text-2xl font-bold tracking-wider"><?php echo $student_code; ?></p>
                    <button onclick="copyToClipboard('<?php echo $student_code; ?>')" 
                            class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-lg h-10 px-4 bg-[#ff6933] text-white text-sm font-medium gap-2">
                        <span class="material-symbols-outlined text-base">content_copy</span>
                        <span>Copy</span>
                    </button>
                </div>
                <p class="text-[#8d6a5e] text-sm font-normal text-center pt-4">
                    You will need this code to check your application status and upload payment receipt.
                </p>
            </div>

   
            <!-- Payment Instructions Card -->
            <div class="flex flex-col items-stretch rounded-xl w-full bg-white p-6 shadow-sm border border-[#e7deda]">
                <p class="text-[#181210] text-lg font-bold text-center mb-4">Admission Fee Payment</p>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between items-center">
                        <span class="text-[#8d6a5e]">Amount:</span>
                        <span class="font-bold text-[#181210]">₦3,000 per term</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[#8d6a5e]">Bank:</span>
                        <span class="font-medium text-[#181210]">Union Bank</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[#8d6a5e]">Account Number:</span>
                        <span class="font-medium text-[#181210]">0060368349</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[#8d6a5e]">Account Name:</span>
                        <span class="font-medium text-[#181210]">Unique Brilliant Schools</span>
                    </div>
                </div>
                <p class="text-[#8d6a5e] text-sm font-normal text-center pt-4">
                    Make payment first, then upload your receipt above.
                </p>
            </div>
        </div>
        
        
                 <!-- Receipt Upload Card -->
            <div class="flex flex-col items-stretch rounded-xl w-full bg-white p-6 shadow-sm border border-[#e7deda] mb-4">
                <p class="text-[#181210] text-lg font-bold text-center mb-4">Upload Payment Receipt</p>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="flex flex-col items-center justify-center border-2 border-dashed border-[#e7deda] rounded-lg p-6 bg-[#f8f6f5] hover:bg-[#f0ecea] transition-colors cursor-pointer"
                         onclick="document.getElementById('receiptFile').click()">
                        <span class="material-symbols-outlined text-3xl text-[#8d6a5e] mb-2">cloud_upload</span>
                        <p class="text-[#181210] font-medium text-center">Click to upload receipt</p>
                        <p class="text-[#8d6a5e] text-sm text-center mt-1">JPG, PNG, GIF, or PDF (Max 5MB)</p>
                        <input type="file" id="receiptFile" name="receipt" accept=".jpg,.jpeg,.png,.gif,.pdf" 
                               class="file-input-hidden" onchange="updateFileName(this)">
                    </div>
                    
                    <div id="fileNameDisplay" class="text-center text-sm text-[#8d6a5e] hidden">
                        Selected file: <span id="fileName" class="font-medium text-[#ff6933]"></span>
                    </div>
                    
                    <button type="submit" 
                            class="flex w-full cursor-pointer items-center justify-center rounded-lg h-12 bg-[#ff6933] text-white text-base font-bold gap-2">
                        <span class="material-symbols-outlined text-xl">upload</span>
                        <span>Upload Receipt</span>
                    </button>
                </form>
                
                <p class="text-[#8d6a5e] text-sm font-normal text-center pt-4">
                    After uploading, your application will be marked as paid and ready for review.
                </p>
            </div>

        
        <!-- Buttons -->
        <div class="flex flex-col gap-3 px-4 py-3 mt-8">
            <a href="login.php" class="flex w-full cursor-pointer items-center justify-center rounded-lg h-12 bg-[#ff6933] text-white text-base font-bold">
                <span>Check Application Status</span>
            </a>
            <a href="register.php" class="flex w-full cursor-pointer items-center justify-center rounded-lg h-12 border border-[#ff6933] text-[#ff6933] text-base font-bold">
                <span>Register Another Student</span>
            </a>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Student code copied to clipboard!');
            });
        }

        function updateFileName(input) {
            const fileNameDisplay = document.getElementById('fileNameDisplay');
            const fileName = document.getElementById('fileName');
            
            if (input.files.length > 0) {
                fileName.textContent = input.files[0].name;
                fileNameDisplay.classList.remove('hidden');
            } else {
                fileNameDisplay.classList.add('hidden');
            }
        }

        // Optional: Drag and drop functionality
        const dropArea = document.querySelector('form .border-dashed');
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight() {
            dropArea.classList.add('bg-[#ff6933]/10', 'border-[#ff6933]');
        }

        function unhighlight() {
            dropArea.classList.remove('bg-[#ff6933]/10', 'border-[#ff6933]');
        }

        dropArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const input = document.getElementById('receiptFile');
            input.files = files;
            updateFileName(input);
        }
    </script>
</body>
</html>