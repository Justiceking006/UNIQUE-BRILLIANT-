<?php
// Check if assignment_id is provided
if (!isset($_GET['assignment_id']) || empty($_GET['assignment_id'])) {
    header('Location: ?page=assignments');
    exit;
}

$assignment_id = intval($_GET['assignment_id']);

// Get assignment details with subject info and submission status
$assignment_stmt = $db->prepare("
    SELECT a.*, s.subject_name, s.subject_code,
           asub.id as submission_id, asub.submission_text, asub.file_path, 
           asub.submitted_at, asub.marks_awarded, asub.feedback, asub.status as submission_status
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN student_course_enrollments sce ON s.id = sce.subject_id AND sce.student_id = ?
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
    WHERE a.id = ? AND sce.status = 'active'
");
$assignment_stmt->execute([$_SESSION['student_id'], $_SESSION['student_id'], $assignment_id]);
$assignment = $assignment_stmt->fetch();

if (!$assignment) {
    header('Location: ?page=assignments');
    exit;
}

// Handle file upload
$upload_error = null;
$upload_success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assignment'])) {
    $submission_text = $_POST['submission_text'] ?? '';
    
    // Check if already submitted
    if ($assignment['submission_id']) {
        $upload_error = "You have already submitted this assignment.";
    } else {
        // Handle file upload
        $uploaded_files = [];
        
        if (!empty($_FILES['answer_files']['name'][0])) {
            $file_count = count($_FILES['answer_files']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['answer_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = $_FILES['answer_files']['name'][$i];
                    $file_tmp = $_FILES['answer_files']['tmp_name'][$i];
                    $file_size = $_FILES['answer_files']['size'][$i];
                    $file_type = $_FILES['answer_files']['type'][$i];
                    
                    // Validate file type (images only)
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($file_type, $allowed_types)) {
                        $upload_error = "Only image files (JPEG, PNG, GIF, WebP) are allowed.";
                        break;
                    }
                    
                    // Validate file size (max 5MB per file)
                    if ($file_size > 5 * 1024 * 1024) {
                        $upload_error = "Each file must be less than 5MB.";
                        break;
                    }
                    
                    // Generate unique filename
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_filename = 'assignment_' . $assignment_id . '_student_' . $_SESSION['student_id'] . '_' . time() . '_' . $i . '.' . $file_extension;
                    $upload_path = 'uploads/receipts/' . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $uploaded_files[] = [
                            'path' => $upload_path,
                            'name' => $new_filename,
                            'original_name' => $file_name,
                            'size' => $file_size,
                            'type' => $file_type
                        ];
                    } else {
                        $upload_error = "Failed to upload file: " . $file_name;
                        break;
                    }
                }
            }
        }
        
        if (!$upload_error) {
            if (empty($uploaded_files)) {
                $upload_error = "Please upload at least one image file of your answers.";
            } else {
                // Convert uploaded files array to JSON for storage
                $files_json = json_encode($uploaded_files);
                
                // Insert submission into database
                $insert_stmt = $db->prepare("
                    INSERT INTO assignment_submissions (assignment_id, student_id, submission_text, file_path, submitted_at, status) 
                    VALUES (?, ?, ?, ?, NOW(), 'submitted')
                ");
                
                if ($insert_stmt->execute([$assignment_id, $_SESSION['student_id'], $submission_text, $files_json])) {
                    $upload_success = "Assignment submitted successfully!";
                    
                    // Refresh assignment data to show submission
                    $assignment_stmt->execute([$_SESSION['student_id'], $_SESSION['student_id'], $assignment_id]);
                    $assignment = $assignment_stmt->fetch();
                } else {
                    $upload_error = "Failed to save submission. Please try again.";
                }
            }
        }
    }
}

// Parse uploaded files if submission exists
$uploaded_files_data = [];
if ($assignment['file_path']) {
    $uploaded_files_data = json_decode($assignment['file_path'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignment Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="min-h-screen bg-[#f8f6f5]">
<div class="p-6">
    <div class="max-w-6xl mx-auto">
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-[#181210]"><?php echo htmlspecialchars($assignment['title']); ?></h1>
                    <p class="text-[#8d6a5e]">
                        Subject: <?php echo htmlspecialchars($assignment['subject_name']); ?> 
                        (<?php echo htmlspecialchars($assignment['subject_code']); ?>)
                    </p>
                </div>
                <a href="?page=assignment-subject&subject_id=<?php echo $assignment['subject_id']; ?>" 
                   class="text-[#ff6933] hover:underline flex items-center text-sm font-medium">
                    ← Back to assignments
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Assignment Details -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Assignment Information -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                    <h2 class="text-xl font-bold text-[#181210] mb-4">Assignment Details</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Description</label>
                            <p class="text-[#181210]"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Due Date</label>
                                <p class="text-[#181210] font-medium">
                                    <?php echo date('F j, Y g:i A', strtotime($assignment['due_date'])); ?>
                                </p>
                                <?php if (strtotime($assignment['due_date']) < time() && !$assignment['submission_id']): ?>
                                    <p class="text-red-600 text-sm mt-1">This assignment is overdue</p>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Maximum Marks</label>
                                <p class="text-[#181210] font-medium"><?php echo $assignment['max_marks']; ?> points</p>
                            </div>
                        </div>
                        
                        <?php if ($assignment['submission_id']): ?>
                            <div class="border-t border-[#e7deda] pt-4">
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Submission Status</label>
                                <div class="flex items-center space-x-4">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        <span class="material-symbols-outlined text-base mr-1">check_circle</span>
                                        Submitted on <?php echo date('M j, Y g:i A', strtotime($assignment['submitted_at'])); ?>
                                    </span>
                                    
                                    <?php if ($assignment['marks_awarded'] !== null): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <span class="material-symbols-outlined text-base mr-1">grade</span>
                                            Graded: <?php echo $assignment['marks_awarded']; ?>/<?php echo $assignment['max_marks']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                            <span class="material-symbols-outlined text-base mr-1">pending</span>
                                            Awaiting Grading
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($assignment['feedback']): ?>
                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-[#8d6a5e] mb-1">Teacher Feedback</label>
                                        <p class="text-[#181210] bg-[#f8f6f5] p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($assignment['feedback'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Submission Form / View -->
                <?php if ($assignment['submission_id']): ?>
                    <!-- View Submission -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                        <h2 class="text-xl font-bold text-[#181210] mb-4">Your Submission</h2>
                        
                        <?php if ($assignment['submission_text']): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Submission Notes</label>
                                <p class="text-[#181210] bg-[#f8f6f5] p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($assignment['submission_text'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($uploaded_files_data)): ?>
                            <div>
                                <label class="block text-sm font-medium text-[#8d6a5e] mb-2">Uploaded Answer Images</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($uploaded_files_data as $file): ?>
                                        <div class="border border-[#e7deda] rounded-lg p-3">
                                            <div class="flex items-center justify-between mb-2">
                                                <span class="text-sm font-medium text-[#181210] truncate">
                                                    <?php echo htmlspecialchars($file['original_name']); ?>
                                                </span>
                                                <span class="text-xs text-[#8d6a5e]">
                                                    <?php echo round($file['size'] / 1024, 1); ?> KB
                                                </span>
                                            </div>
                                            <img src="<?php echo $file['path']; ?>" 
                                                 alt="Assignment answer" 
                                                 class="w-full h-32 object-cover rounded border border-[#e7deda] cursor-pointer"
                                                 onclick="openImageModal('<?php echo $file['path']; ?>')">
                                            <div class="mt-2 flex justify-between items-center">
                                                <span class="text-xs text-[#8d6a5e]">
                                                    <?php echo strtoupper(pathinfo($file['name'], PATHINFO_EXTENSION)); ?>
                                                </span>
                                                <a href="<?php echo $file['path']; ?>" 
                                                   download="<?php echo $file['original_name']; ?>"
                                                   class="text-[#ff6933] hover:text-[#e55a2b] text-sm font-medium">
                                                    Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Submission Form -->
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                        <h2 class="text-xl font-bold text-[#181210] mb-4">Submit Assignment</h2>
                        
                        <?php if ($upload_success): ?>
                            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                                <?php echo $upload_success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($upload_error): ?>
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                                <?php echo $upload_error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="space-y-4">
                                <div>
                                    <label for="submission_text" class="block text-sm font-medium text-[#8d6a5e] mb-2">
                                        Additional Notes (Optional)
                                    </label>
                                    <textarea name="submission_text" id="submission_text" rows="4" 
                                              class="w-full px-3 py-2 border border-[#e7deda] rounded-lg focus:outline-none focus:ring-2 focus:ring-[#ff6933] focus:border-transparent"
                                              placeholder="Add any comments or explanations about your submission..."><?php echo htmlspecialchars($_POST['submission_text'] ?? ''); ?></textarea>
                                </div>
                                
                                <div>
                                    <label for="answer_files" class="block text-sm font-medium text-[#8d6a5e] mb-2">
                                        Upload Answer Images *
                                    </label>
                                    <div class="border-2 border-dashed border-[#e7deda] rounded-lg p-6 text-center hover:border-[#ff6933] transition-colors">
                                        <input type="file" name="answer_files[]" id="answer_files" 
                                               multiple accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                               class="hidden" onchange="previewFiles()">
                                        
                                        <div id="file-upload-area" class="cursor-pointer">
                                            <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">cloud_upload</span>
                                            <p class="text-[#181210] font-medium mb-1">Click to upload images</p>
                                            <p class="text-sm text-[#8d6a5e]">Upload images of your handwritten or typed answers</p>
                                            <p class="text-xs text-[#8d6a5e] mt-2">Supported formats: JPEG, PNG, GIF, WebP (Max 5MB each)</p>
                                        </div>
                                        
                                        <div id="file-preview" class="mt-4 hidden">
                                            <p class="text-sm font-medium text-[#181210] mb-2">Selected files:</p>
                                            <div id="file-list" class="space-y-2"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex justify-end">
                                    <button type="submit" name="submit_assignment" 
                                            class="bg-[#ff6933] hover:bg-[#e55a2b] text-white px-6 py-3 rounded-lg font-medium transition-colors flex items-center">
                                        <span class="material-symbols-outlined text-lg mr-2">upload</span>
                                        Submit Assignment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Status Card -->
                <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
                    <h3 class="font-bold text-[#181210] mb-3">Assignment Status</h3>
                    
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-[#8d6a5e]">Status:</span>
                            <?php if ($assignment['submission_id']): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                    Submitted
                                </span>
                            <?php elseif (strtotime($assignment['due_date']) < time()): ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">
                                    Overdue
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-orange-100 text-orange-800">
                                    Pending
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-[#8d6a5e]">Due Date:</span>
                            <span class="font-medium text-[#181210]"><?php echo date('M j, Y', strtotime($assignment['due_date'])); ?></span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-[#8d6a5e]">Time Left:</span>
                            <span class="font-medium <?php echo (strtotime($assignment['due_date']) - time() < 24 * 3600) ? 'text-red-600' : 'text-[#181210]'; ?>">
                                <?php
                                $time_left = strtotime($assignment['due_date']) - time();
                                if ($time_left > 0) {
                                    $days = floor($time_left / (60 * 60 * 24));
                                    $hours = floor(($time_left % (60 * 60 * 24)) / (60 * 60));
                                    echo $days . 'd ' . $hours . 'h';
                                } else {
                                    echo 'Overdue';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
                    <h3 class="font-bold text-blue-900 mb-3">Submission Instructions</h3>
                    <ul class="text-blue-800 text-sm space-y-2">
                        <li class="flex items-start">
                            <span class="material-symbols-outlined text-base mr-2 text-blue-600">photo_camera</span>
                            Take clear photos of your handwritten answers
                        </li>
                        <li class="flex items-start">
                            <span class="material-symbols-outlined text-base mr-2 text-blue-600">upload</span>
                            Upload multiple images if needed
                        </li>
                        <li class="flex items-start">
                            <span class="material-symbols-outlined text-base mr-2 text-blue-600">warning</span>
                            Ensure images are readable and well-lit
                        </li>
                        <li class="flex items-start">
                            <span class="material-symbols-outlined text-base mr-2 text-blue-600">schedule</span>
                            Submit before the due date
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
    <div class="max-w-4xl max-h-full">
        <img id="modalImage" src="" alt="Enlarged view" class="max-w-full max-h-full object-contain">
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white hover:text-gray-300">
            <span class="material-symbols-outlined text-3xl">close</span>
        </button>
    </div>
</div>

<script>
function previewFiles() {
    const fileInput = document.getElementById('answer_files');
    const filePreview = document.getElementById('file-preview');
    const fileList = document.getElementById('file-list');
    const fileUploadArea = document.getElementById('file-upload-area');
    
    fileList.innerHTML = '';
    
    if (fileInput.files.length > 0) {
        fileUploadArea.classList.add('hidden');
        filePreview.classList.remove('hidden');
        
        for (let i = 0; i < fileInput.files.length; i++) {
            const file = fileInput.files[i];
            const fileItem = document.createElement('div');
            fileItem.className = 'flex items-center justify-between bg-[#f8f6f5] p-2 rounded text-sm';
            fileItem.innerHTML = `
                <div class="flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[#8d6a5e]">image</span>
                    <span class="text-[#181210] truncate">${file.name}</span>
                </div>
                <span class="text-[#8d6a5e] text-xs">${(file.size / 1024).toFixed(1)} KB</span>
            `;
            fileList.appendChild(fileItem);
        }
    } else {
        fileUploadArea.classList.remove('hidden');
        filePreview.classList.add('hidden');
    }
}

function openImageModal(imageSrc) {
    document.getElementById('modalImage').src = imageSrc;
    document.getElementById('imageModal').classList.remove('hidden');
}

function closeImageModal() {
    document.getElementById('imageModal').classList.add('hidden');
}

// Make the file upload area clickable
document.getElementById('file-upload-area').addEventListener('click', function() {
    document.getElementById('answer_files').click();
});

// Close modal on background click
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target.id === 'imageModal') {
        closeImageModal();
    }
});
</script>
</body>
</html>