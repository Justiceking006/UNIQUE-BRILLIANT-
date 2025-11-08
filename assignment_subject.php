<?php
// Check if subject_id is provided
if (!isset($_GET['subject_id']) || empty($_GET['subject_id'])) {
    header('Location: ?page=assignments');
    exit;
}

$subject_id = intval($_GET['subject_id']);

// Get subject details
$subject_stmt = $db->prepare("
    SELECT s.* 
    FROM subjects s 
    JOIN student_course_enrollments sce ON s.id = sce.subject_id 
    WHERE s.id = ? AND sce.student_id = ? AND sce.status = 'active'
");
$subject_stmt->execute([$subject_id, $_SESSION['student_id']]);
$subject = $subject_stmt->fetch();

if (!$subject) {
    header('Location: ?page=assignments');
    exit;
}

// Get assignments for this subject with submission status
$assignments_stmt = $db->prepare("
    SELECT a.*, 
           asub.id as submission_id,
           asub.submitted_at,
           asub.marks_awarded,
           asub.feedback,
           asub.status as submission_status,
           CASE 
               WHEN asub.id IS NOT NULL THEN 'submitted'
               WHEN a.due_date < NOW() THEN 'overdue'
               ELSE 'pending'
           END as status_display
    FROM assignments a
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
    WHERE a.subject_id = ?
    ORDER BY a.due_date ASC
");
$assignments_stmt->execute([$_SESSION['student_id'], $subject_id]);
$assignments = $assignments_stmt->fetchAll();
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#181210]"><?php echo htmlspecialchars($subject['subject_name']); ?> Assignments</h1>
            <p class="text-[#8d6a5e]">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
        </div>
        <a href="?page=assignments" class="text-[#ff6933] hover:underline flex items-center text-sm font-medium">
            ← Back to all subjects
        </a>
    </div>
</div>

<div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <?php if (empty($assignments)): ?>
        <div class="text-center py-8">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">assignment</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Assignments</h3>
            <p class="text-[#8d6a5e]">No assignments available for this subject yet.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($assignments as $assignment): ?>
                <div class="border border-[#e7deda] rounded-lg p-4 hover:border-[#ff6933]/30 transition-colors">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex-1">
                            <h3 class="font-bold text-lg text-[#181210] mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p class="text-[#8d6a5e] text-sm mb-2"><?php echo htmlspecialchars($assignment['description']); ?></p>
                            
                            <div class="flex items-center space-x-4 text-sm text-[#8d6a5e]">
                                <div class="flex items-center space-x-1">
                                    <span class="material-symbols-outlined text-base">event</span>
                                    <span>Due: <?php echo date('M j, Y g:i A', strtotime($assignment['due_date'])); ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <span class="material-symbols-outlined text-base">grade</span>
                                    <span>Max Marks: <?php echo $assignment['max_marks']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <?php if ($assignment['submission_id']): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <span class="material-symbols-outlined text-sm mr-1">check_circle</span>
                                    Submitted
                                </span>
                                <?php if ($assignment['marks_awarded'] !== null): ?>
                                    <div class="mt-1 text-sm font-bold text-[#181210]">
                                        Marks: <?php echo $assignment['marks_awarded']; ?>/<?php echo $assignment['max_marks']; ?>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($assignment['status_display'] === 'overdue'): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <span class="material-symbols-outlined text-sm mr-1">schedule</span>
                                    Overdue
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                    <span class="material-symbols-outlined text-sm mr-1">pending</span>
                                    Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <div>
                            <?php if ($assignment['marks_awarded'] !== null && $assignment['feedback']): ?>
                                <p class="text-sm text-[#8d6a5e]"><strong>Feedback:</strong> <?php echo htmlspecialchars($assignment['feedback']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex space-x-2">
                            <?php if ($assignment['submission_id']): ?>
                                <a href="?page=assignment-detail&assignment_id=<?php echo $assignment['id']; ?>" 
                                   class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                                    <span class="material-symbols-outlined text-sm mr-1">visibility</span>
                                    View Submission
                                </a>
                            <?php else: ?>
                                <a href="?page=assignment-detail&assignment_id=<?php echo $assignment['id']; ?>" 
                                   class="bg-[#ff6933] hover:bg-[#e55a2b] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                                    <span class="material-symbols-outlined text-sm mr-1">upload</span>
                                    Submit Assignment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>