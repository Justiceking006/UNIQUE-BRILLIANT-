<?php
// Get current academic subpage
$academic_page = isset($_GET['page']) ? $_GET['page'] : 'academic';

// Handle assignments functionality
if ($academic_page === 'assignments') {
    // Get all subjects that the student is enrolled in and have assignments
    $enrolled_subjects_stmt = $db->prepare("
        SELECT s.id, s.subject_code, s.subject_name, 
               COUNT(a.id) as assignment_count,
               COUNT(CASE WHEN asub.id IS NOT NULL THEN 1 END) as submitted_count
        FROM subjects s 
        JOIN student_course_enrollments sce ON s.id = sce.subject_id 
        LEFT JOIN assignments a ON s.id = a.subject_id 
        LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = ?
        WHERE sce.student_id = ? AND sce.status = 'active'
        GROUP BY s.id
        HAVING assignment_count > 0
        ORDER BY s.subject_name
    ");
    $enrolled_subjects_stmt->execute([$_SESSION['student_id'], $_SESSION['student_id']]);
    $enrolled_subjects = $enrolled_subjects_stmt->fetchAll();
}

// Handle lectures functionality
if ($academic_page === 'lectures') {
    // Get all subjects that the student is enrolled in with lectures and notes
    $enrolled_subjects_stmt = $db->prepare("
        SELECT s.id, s.subject_code, s.subject_name,
               COUNT(DISTINCT ll.id) as lecture_count,
               COUNT(DISTINCT cn.id) as notes_count
        FROM subjects s 
        JOIN student_course_enrollments sce ON s.id = sce.subject_id 
        LEFT JOIN lecture_links ll ON s.id = ll.subject_id 
        LEFT JOIN class_notes cn ON s.id = cn.subject_id AND cn.status = 'active'
        WHERE sce.student_id = ? AND sce.status = 'active'
        GROUP BY s.id
        HAVING lecture_count > 0 OR notes_count > 0
        ORDER BY s.subject_name
    ");
    $enrolled_subjects_stmt->execute([$_SESSION['student_id']]);
    $enrolled_subjects = $enrolled_subjects_stmt->fetchAll();
    
    // If specific subject is selected, get its lectures and notes
    if (isset($_GET['subject_id'])) {
        $subject_id = intval($_GET['subject_id']);
        
        // Verify student is enrolled in this subject
        $check_enrollment_stmt = $db->prepare("
            SELECT s.id, s.subject_name, s.subject_code 
            FROM subjects s 
            JOIN student_course_enrollments sce ON s.id = sce.subject_id 
            WHERE s.id = ? AND sce.student_id = ? AND sce.status = 'active'
        ");
        $check_enrollment_stmt->execute([$subject_id, $_SESSION['student_id']]);
        $current_subject = $check_enrollment_stmt->fetch();
        
        if ($current_subject) {
            // Get lectures for this subject
            $lectures_stmt = $db->prepare("
                SELECT * FROM lecture_links 
                WHERE subject_id = ? 
                ORDER BY posted_at DESC
            ");
            $lectures_stmt->execute([$subject_id]);
            $lectures = $lectures_stmt->fetchAll();
            
            // Get class notes for this subject
            $notes_stmt = $db->prepare("
                SELECT * FROM class_notes 
                WHERE subject_id = ? AND status = 'active'
                ORDER BY uploaded_at DESC
            ");
            $notes_stmt->execute([$subject_id]);
            $class_notes = $notes_stmt->fetchAll();
        }
    }
}


// Handle courses enrolled functionality
if ($academic_page === 'courses-enrolled') {
    // Get all subjects that the student is enrolled in with detailed info
    $enrolled_courses_stmt = $db->prepare("
        SELECT 
            s.id, s.subject_code, s.subject_name, s.description, s.category, s.credits,
            sce.class_name, sce.term, sce.academic_year, sce.enrolled_at,
            ct.staff_id, st.first_name as teacher_first_name, st.last_name as teacher_last_name
        FROM subjects s 
        JOIN student_course_enrollments sce ON s.id = sce.subject_id 
        LEFT JOIN class_teachers ct ON sce.class_name = ct.class_name AND ct.academic_year = sce.academic_year AND ct.status = 'active'
        LEFT JOIN staff st ON ct.staff_id = st.id
        WHERE sce.student_id = ? AND sce.status = 'active'
        ORDER BY s.subject_name
    ");
    $enrolled_courses_stmt->execute([$_SESSION['student_id']]);
    $enrolled_courses = $enrolled_courses_stmt->fetchAll();
    
    // Get student's class information for schedule
    $student_info_stmt = $db->prepare("
        SELECT section, level, class, department 
        FROM students 
        WHERE id = ?
    ");
    $student_info_stmt->execute([$_SESSION['student_id']]);
    $student_info = $student_info_stmt->fetch();
}

?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-[#181210]">
        <?php 
        switch($academic_page) {
            case 'assignments': echo 'Assignments'; break;
            case 'lectures': 
                if (isset($current_subject)) {
                    echo htmlspecialchars($current_subject['subject_name']) . ' - Lectures & Notes';
                } else {
                    echo 'Lectures & Notes';
                }
                break;
            case 'courses-enrolled': echo 'Courses Enrolled'; break;
            default: echo 'Academic';
        }
        ?>
    </h1>
    <p class="text-[#8d6a5e]">
        <?php 
        switch($academic_page) {
            case 'assignments': echo 'View and submit your assignments'; break;
            case 'lectures': 
                if (isset($current_subject)) {
                    echo 'Video lectures and study materials for ' . htmlspecialchars($current_subject['subject_name']);
                } else {
                    echo 'Access lecture videos and study materials';
                }
                break;
            case 'courses-enrolled': echo 'View your enrolled courses and schedules'; break;
            default: echo 'Manage your academic activities';
        }
        ?>
    </p>
</div>

<div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
    <?php if ($academic_page === 'assignments'): ?>
        
        <?php if (empty($enrolled_subjects)): ?>
            <div class="text-center py-8">
                <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">assignment</span>
                <h3 class="text-xl font-bold text-[#181210] mb-2">No Assignments Available</h3>
                <p class="text-[#8d6a5e]">You don't have any assignments for your enrolled subjects yet.</p>
            </div>
        
        <?php else: ?>
            <!-- Subjects with Assignments -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($enrolled_subjects as $subject): ?>
                    <a href="?page=assignment-subject&subject_id=<?php echo $subject['id']; ?>" 
                       class="bg-[#f8f6f5] rounded-lg p-6 hover:bg-[#ff6933]/10 transition-colors border border-transparent hover:border-[#ff6933]/20">
                        <div class="flex items-center justify-between mb-3">
                            <span class="material-symbols-outlined text-3xl text-[#ff6933]">book</span>
                            <span class="bg-[#ff6933] text-white text-sm font-medium px-2 py-1 rounded-full">
                                <?php echo $subject['assignment_count']; ?> assignments
                            </span>
                        </div>
                        <h3 class="font-bold text-[#181210] text-lg mb-2"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                        <p class="text-sm text-[#8d6a5e] mb-3">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                        <div class="flex justify-between items-center text-xs">
                            <span class="text-green-600 font-medium">
                                <?php echo $subject['submitted_count']; ?> submitted
                            </span>
                            <span class="text-[#8d6a5e]">
                                <?php echo $subject['assignment_count'] - $subject['submitted_count']; ?> pending
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    
    <?php elseif ($academic_page === 'lectures'): ?>
        
        <?php if (isset($current_subject)): ?>
            <!-- Back button -->
            <div class="mb-6">
                <a href="?page=lectures" class="text-[#ff6933] hover:underline flex items-center text-sm font-medium mb-4">
                    ← Back to all subjects
                </a>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- YouTube Lectures Section -->
                <div>
                    <h2 class="text-xl font-bold text-[#181210] mb-4 flex items-center">
                        <span class="material-symbols-outlined text-[#ff6933] mr-2">video_library</span>
                        Video Lectures
                    </h2>
                    
                    <?php if (empty($lectures)): ?>
                        <div class="text-center py-8 bg-[#f8f6f5] rounded-lg">
                            <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">play_disabled</span>
                            <p class="text-[#8d6a5e]">No video lectures available for this subject yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($lectures as $lecture): 
                                // Extract YouTube video ID
                                $video_id = '';
                                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $lecture['youtube_url'], $matches)) {
                                    $video_id = $matches[1];
                                }
                            ?>
                                <div class="border border-[#e7deda] rounded-lg overflow-hidden">
                                    <div class="bg-black">
                                        <?php if ($video_id): ?>
                                            <iframe 
                                                class="w-full h-48" 
                                                src="https://www.youtube.com/embed/<?php echo $video_id; ?>" 
                                                frameborder="0" 
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                allowfullscreen>
                                            </iframe>
                                        <?php else: ?>
                                            <div class="w-full h-48 flex items-center justify-center bg-gray-800 text-white">
                                                <span class="material-symbols-outlined text-4xl mr-2">link</span>
                                                Invalid YouTube URL
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="p-4">
                                        <h3 class="font-bold text-[#181210] mb-2"><?php echo htmlspecialchars($lecture['title']); ?></h3>
                                        <p class="text-sm text-[#8d6a5e] mb-3"><?php echo htmlspecialchars($lecture['description']); ?></p>
                                        <div class="flex justify-between items-center text-xs text-[#8d6a5e]">
                                            <span>Posted: <?php echo date('M j, Y', strtotime($lecture['posted_at'])); ?></span>
                                            <?php if ($video_id): ?>
                                                <a href="<?php echo htmlspecialchars($lecture['youtube_url']); ?>" 
                                                   target="_blank" 
                                                   class="text-[#ff6933] hover:underline flex items-center">
                                                    Watch on YouTube
                                                    <span class="material-symbols-outlined text-sm ml-1">open_in_new</span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Class Notes Section -->
                <div>
                    <h2 class="text-xl font-bold text-[#181210] mb-4 flex items-center">
                        <span class="material-symbols-outlined text-[#ff6933] mr-2">description</span>
                        Study Materials & Notes
                    </h2>
                    
                    <?php if (empty($class_notes)): ?>
                        <div class="text-center py-8 bg-[#f8f6f5] rounded-lg">
                            <span class="material-symbols-outlined text-4xl text-[#8d6a5e] mb-2">note_stack</span>
                            <p class="text-[#8d6a5e]">No study materials available for this subject yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($class_notes as $note): ?>
                                <div class="border border-[#e7deda] rounded-lg p-4 hover:border-[#ff6933]/30 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <span class="material-symbols-outlined text-[#ff6933] mr-2">description</span>
                                                <h3 class="font-bold text-[#181210]"><?php echo htmlspecialchars($note['title']); ?></h3>
                                            </div>
                                            <p class="text-sm text-[#8d6a5e] mb-2"><?php echo htmlspecialchars($note['description']); ?></p>
                                            <div class="flex items-center space-x-4 text-xs text-[#8d6a5e]">
                                                <span class="flex items-center">
                                                    <span class="material-symbols-outlined text-xs mr-1">description</span>
                                                    PDF Document
                                                </span>
                                                <span class="flex items-center">
                                                    <span class="material-symbols-outlined text-xs mr-1">database</span>
                                                    <?php echo round($note['file_size'] / 1024 / 1024, 1); ?> MB
                                                </span>
                                                <span class="flex items-center">
                                                    <span class="material-symbols-outlined text-xs mr-1">schedule</span>
                                                    <?php echo date('M j, Y', strtotime($note['uploaded_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <?php if ($note['file_path'] && file_exists($note['file_path'])): ?>
                                                <a href="<?php echo $note['file_path']; ?>" 
                                                   download="<?php echo $note['file_name']; ?>"
                                                   class="bg-[#ff6933] hover:bg-[#e55a2b] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center">
                                                    <span class="material-symbols-outlined text-sm mr-1">download</span>
                                                    Download
                                                </a>
                                            <?php else: ?>
                                                <span class="bg-gray-300 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium flex items-center cursor-not-allowed">
                                                    <span class="material-symbols-outlined text-sm mr-1">error</span>
                                                    Unavailable
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Subject Selection View -->
            <?php if (empty($enrolled_subjects)): ?>
                <div class="text-center py-8">
                    <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">video_library</span>
                    <h3 class="text-xl font-bold text-[#181210] mb-2">No Lectures Available</h3>
                    <p class="text-[#8d6a5e]">You don't have any lectures or study materials for your enrolled subjects yet.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($enrolled_subjects as $subject): ?>
                        <a href="?page=lectures&subject_id=<?php echo $subject['id']; ?>" 
                           class="bg-[#f8f6f5] rounded-lg p-6 hover:bg-[#ff6933]/10 transition-colors border border-transparent hover:border-[#ff6933]/20">
                            <div class="flex items-center justify-between mb-3">
                                <span class="material-symbols-outlined text-3xl text-[#ff6933]">school</span>
                                <div class="flex space-x-2">
                                    <?php if ($subject['lecture_count'] > 0): ?>
                                        <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded-full">
                                            <?php echo $subject['lecture_count']; ?> videos
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($subject['notes_count'] > 0): ?>
                                        <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded-full">
                                            <?php echo $subject['notes_count']; ?> notes
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3 class="font-bold text-[#181210] text-lg mb-2"><?php echo htmlspecialchars($subject['subject_name']); ?></h3>
                            <p class="text-sm text-[#8d6a5e] mb-3">Code: <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-blue-600 font-medium">
                                    <?php echo $subject['lecture_count']; ?> lectures
                                </span>
                                <span class="text-green-600 font-medium">
                                    <?php echo $subject['notes_count']; ?> materials
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
<?php elseif ($academic_page === 'courses-enrolled'): ?>
    
    <?php if (empty($enrolled_courses)): ?>
        <div class="text-center py-8">
            <span class="material-symbols-outlined text-6xl text-[#8d6a5e] mb-4">book</span>
            <h3 class="text-xl font-bold text-[#181210] mb-2">No Courses Enrolled</h3>
            <p class="text-[#8d6a5e]">You are not currently enrolled in any courses.</p>
        </div>
    
    <?php else: ?>
        <!-- Student Info Card -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda] mb-6">
            <h2 class="text-xl font-bold text-[#181210] mb-4">Student Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="space-y-1">
                    <p class="text-sm text-[#8d6a5e]">Class</p>
                    <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($student_info['class']); ?></p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-[#8d6a5e]">Section</p>
                    <p class="font-bold text-[#181210] capitalize"><?php echo htmlspecialchars($student_info['section']); ?></p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-[#8d6a5e]">Level</p>
                    <p class="font-bold text-[#181210] capitalize"><?php echo htmlspecialchars($student_info['level'] ?? 'N/A'); ?></p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-[#8d6a5e]">Academic Year</p>
                    <p class="font-bold text-[#181210]"><?php echo htmlspecialchars($enrolled_courses[0]['academic_year'] ?? '2024/2025'); ?></p>
                </div>
            </div>
        </div>

        <!-- Enrolled Courses -->
        <div class="bg-white rounded-xl p-6 shadow-sm border border-[#e7deda]">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-xl font-bold text-[#181210]">Enrolled Courses (<?php echo count($enrolled_courses); ?>)</h2>
                <div class="text-sm text-[#8d6a5e]">
                    Term: <span class="font-medium text-[#181210] capitalize"><?php echo str_replace('_', ' ', $enrolled_courses[0]['term'] ?? 'first_term'); ?></span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($enrolled_courses as $course): ?>
                    <div class="border border-[#e7deda] rounded-lg p-5 hover:border-[#ff6933]/30 transition-colors">
                        <div class="flex items-start justify-between mb-3">
                            <span class="material-symbols-outlined text-3xl text-[#ff6933]">school</span>
                            <span class="bg-[#ff6933] text-white text-xs font-medium px-2 py-1 rounded-full capitalize">
                                <?php echo $course['category']; ?>
                            </span>
                        </div>
                        
                        <h3 class="font-bold text-lg text-[#181210] mb-2"><?php echo htmlspecialchars($course['subject_name']); ?></h3>
                        <p class="text-sm text-[#8d6a5e] mb-3">Code: <?php echo htmlspecialchars($course['subject_code']); ?></p>
                        
                        <?php if ($course['description']): ?>
                            <p class="text-sm text-[#181210] mb-4"><?php echo htmlspecialchars($course['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between items-center">
                                <span class="text-[#8d6a5e]">Credits:</span>
                                <span class="font-medium text-[#181210]"><?php echo $course['credits']; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-[#8d6a5e]">Class:</span>
                                <span class="font-medium text-[#181210]"><?php echo htmlspecialchars($course['class_name']); ?></span>
                            </div>
                            
                            <?php if ($course['teacher_first_name']): ?>
                                <div class="flex justify-between items-center">
                                    <span class="text-[#8d6a5e]">Teacher:</span>
                                    <span class="font-medium text-[#181210]">
                                        <?php echo htmlspecialchars($course['teacher_first_name'] . ' ' . $course['teacher_last_name']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-[#8d6a5e]">Enrolled:</span>
                                <span class="font-medium text-[#181210]">
                                    <?php echo date('M j, Y', strtotime($course['enrolled_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-[#e7deda]">
                            <div class="flex space-x-2">
                                <a href="?page=lectures&subject_id=<?php echo $course['id']; ?>" 
                                   class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-3 rounded text-sm font-medium transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-sm mr-1">video_library</span>
                                    Lectures
                                </a>
                                <a href="?page=assignment-subject&subject_id=<?php echo $course['id']; ?>" 
                                   class="flex-1 bg-green-600 hover:bg-green-700 text-white text-center py-2 px-3 rounded text-sm font-medium transition-colors flex items-center justify-center">
                                    <span class="material-symbols-outlined text-sm mr-1">assignment</span>
                                    Assignments
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Summary Card -->
            <div class="mt-8 bg-[#f8f6f5] rounded-lg p-6">
                <h3 class="font-bold text-[#181210] mb-4">Academic Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-center">
                    <div>
                        <p class="text-2xl font-bold text-[#181210]"><?php echo count($enrolled_courses); ?></p>
                        <p class="text-sm text-[#8d6a5e]">Total Courses</p>
                    </div>
                    <div>
                        <?php
                        $core_count = array_filter($enrolled_courses, function($course) {
                            return $course['category'] === 'core';
                        });
                        ?>
                        <p class="text-2xl font-bold text-[#181210]"><?php echo count($core_count); ?></p>
                        <p class="text-sm text-[#8d6a5e]">Core Courses</p>
                    </div>
                    <div>
                        <?php
                        $elective_count = array_filter($enrolled_courses, function($course) {
                            return $course['category'] === 'elective';
                        });
                        ?>
                        <p class="text-2xl font-bold text-[#181210]"><?php echo count($elective_count); ?></p>
                        <p class="text-sm text-[#8d6a5e]">Elective Courses</p>
                    </div>
                    <div>
                        <?php
                        $total_credits = array_sum(array_column($enrolled_courses, 'credits'));
                        ?>
                        <p class="text-2xl font-bold text-[#181210]"><?php echo $total_credits; ?></p>
                        <p class="text-sm text-[#8d6a5e]">Total Credits</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php else: ?>
        <!-- Academic Overview -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="?page=assignments" class="bg-[#f8f6f5] rounded-lg p-6 text-center hover:bg-[#ff6933]/10 transition-colors">
                <span class="material-symbols-outlined text-4xl text-[#ff6933] mb-3">assignment</span>
                <h3 class="font-bold text-[#181210] mb-2">Assignments</h3>
                <p class="text-sm text-[#8d6a5e]">View and submit assignments</p>
            </a>
            
            <a href="?page=lectures" class="bg-[#f8f6f5] rounded-lg p-6 text-center hover:bg-[#ff6933]/10 transition-colors">
                <span class="material-symbols-outlined text-4xl text-[#ff6933] mb-3">video_library</span>
                <h3 class="font-bold text-[#181210] mb-2">Lectures & Notes</h3>
                <p class="text-sm text-[#8d6a5e]">Access video lectures and study materials</p>
            </a>
            
            <a href="?page=courses-enrolled" class="bg-[#f8f6f5] rounded-lg p-6 text-center hover:bg-[#ff6933]/10 transition-colors">
                <span class="material-symbols-outlined text-4xl text-[#ff6933] mb-3">book</span>
                <h3 class="font-bold text-[#181210] mb-2">Courses</h3>
                <p class="text-sm text-[#8d6a5e]">View enrolled courses</p>
            </a>
        </div>
    <?php endif; ?>
</div>