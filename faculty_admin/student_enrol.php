<?php
/*******************************************************************************************
 * BULK STUDENT ENROLLMENT — Hormuud University
 * Faculty Admin Panel | PHP 8.2 | PDO Secure Queries
 * Author: ChatGPT 2025
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ==================== ROLE CHECK ====================
$role = strtolower($_SESSION['user']['role'] ?? '');
if ($role !== 'faculty_admin') {
    header("Location: ../login.php");
    exit;
}

// ==================== USER INFO ====================
$user = $_SESSION['user'] ?? [];
$faculty_id = $user['linked_id'] ?? null;
if (!$faculty_id) {
    header("Location: ../login.php");
    exit;
}

// ==================== TIMEZONE ====================
date_default_timezone_set('Africa/Nairobi');

// ==================== ACTIVE TERM ====================
$term = $pdo->query("SELECT academic_term_id, term_name FROM academic_term WHERE status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$academic_term_id = $term['academic_term_id'] ?? null;
$active_term_name = $term['term_name'] ?? 'No Active Term';

// ==================== ALERTS ====================
$message = "";
$type = "";

// ==================== FACULTY FETCH ====================
// Faculty admin can only access their own faculty
$facultiesStmt = $pdo->prepare("SELECT faculty_id, faculty_name FROM faculties WHERE faculty_id = ?");
$facultiesStmt->execute([$faculty_id]);
$faculties = $facultiesStmt->fetchAll(PDO::FETCH_ASSOC);
$current_faculty = $faculties[0] ?? [];

// ==================== DEPARTMENTS FETCH ====================
$selectedFaculty = $faculty_id; // auto-select admin's faculty
$departmentsStmt = $pdo->prepare("SELECT department_id, department_name FROM departments WHERE faculty_id = ? AND status='active' ORDER BY department_name ASC");
$departmentsStmt->execute([$selectedFaculty]);
$departments = $departmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// ==================== PROGRAMS FETCH ====================
$selectedDepartment = $_GET['department_id'] ?? null;
if ($selectedDepartment) {
    // Verify department belongs to faculty
    $checkDept = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_id = ? AND faculty_id = ?");
    $checkDept->execute([$selectedDepartment, $faculty_id]);
    if ($checkDept->fetchColumn() > 0) {
        $programsStmt = $pdo->prepare("SELECT program_id, program_name FROM programs WHERE department_id = ? AND status='active' ORDER BY program_name ASC");
        $programsStmt->execute([$selectedDepartment]);
        $programs = $programsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $programs = [];
        $selectedDepartment = null;
    }
} else {
    $programs = [];
}

// ==================== SECTIONS FETCH ====================
$selectedProgram = $_GET['program_id'] ?? null;
if ($selectedProgram) {
    // Verify program belongs to faculty through department
    $checkProg = $pdo->prepare("
        SELECT COUNT(*) FROM programs p 
        JOIN departments d ON p.department_id = d.department_id 
        WHERE p.program_id = ? AND d.faculty_id = ?
    ");
    $checkProg->execute([$selectedProgram, $faculty_id]);
    if ($checkProg->fetchColumn() > 0) {
        $sectionsStmt = $pdo->prepare("SELECT section_id, section_name FROM class_section WHERE program_id = ? AND status='active' ORDER BY section_name ASC");
        $sectionsStmt->execute([$selectedProgram]);
        $sections = $sectionsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sections = [];
        $selectedProgram = null;
    }
} else {
    $sections = [];
}

// ==================== SEMESTERS FETCH ====================
$semesters = $pdo->query("SELECT semester_id, semester_name FROM semester WHERE status='active' ORDER BY semester_id ASC")->fetchAll(PDO::FETCH_ASSOC);

// ==================== BULK ENROLLMENT ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_selected'])) {
    try {
        $program_id  = $_POST['program_id'] ?? null;
        $semester_id = $_POST['semester_id'] ?? null;
        $section_id  = $_POST['section_id'] ?? null;
        $students    = $_POST['student_ids'] ?? [];

        // Validation
        if (empty($students)) {
            throw new Exception("No students selected for enrollment!");
        }
        if (!$program_id || !$semester_id || !$section_id) {
            throw new Exception("Missing required program information!");
        }
        if (!$academic_term_id) {
            throw new Exception("No active academic term found. Please contact administrator!");
        }

        // Verify program belongs to faculty
        $checkProgramAccess = $pdo->prepare("
            SELECT COUNT(*) FROM programs p 
            JOIN departments d ON p.department_id = d.department_id 
            WHERE p.program_id = ? AND d.faculty_id = ?
        ");
        $checkProgramAccess->execute([$program_id, $faculty_id]);
        if ($checkProgramAccess->fetchColumn() == 0) {
            throw new Exception("Access denied for this program!");
        }

        $pdo->beginTransaction();

        // Get all active subjects for program + semester
        $subjectsStmt = $pdo->prepare("
            SELECT subject_id, subject_name, subject_code 
            FROM subject 
            WHERE program_id = ? AND semester_id = ? AND status='active'
        ");
        $subjectsStmt->execute([$program_id, $semester_id]);
        $subjectList = $subjectsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($subjectList)) {
            throw new Exception("No active subjects found for this program & semester combination.");
        }

        // Prepare insert statement
        $insertStmt = $pdo->prepare("
            INSERT INTO student_enroll 
            (student_id, program_id, semester_id, section_id, subject_id, academic_term_id, status, created_at)
            VALUES (?,?,?,?,?,?,'active',NOW())
        ");

        $enrollment_count = 0;
        $subject_count = count($subjectList);

        foreach ($students as $student_id) {
            // Verify student belongs to the selected program and section
            $checkStudent = $pdo->prepare("
                SELECT COUNT(*) FROM students 
                WHERE student_id = ? AND program_id = ? AND section_id = ?
            ");
            $checkStudent->execute([$student_id, $program_id, $section_id]);
            
            if ($checkStudent->fetchColumn() > 0) {
                foreach ($subjectList as $subject) {
                    // Prevent duplicates: same student, same subject, same term, same section
                    $checkStmt = $pdo->prepare("
                        SELECT enroll_id 
                        FROM student_enroll 
                        WHERE student_id = ? AND academic_term_id = ? AND subject_id = ? AND section_id = ?
                    ");
                    $checkStmt->execute([$student_id, $academic_term_id, $subject['subject_id'], $section_id]);

                    if ($checkStmt->rowCount() == 0) {
                        $insertStmt->execute([$student_id, $program_id, $semester_id, $section_id, $subject['subject_id'], $academic_term_id]);
                        $enrollment_count++;
                    }
                }
            }
        }

        $pdo->commit();
        
        if ($enrollment_count > 0) {
            $message = "✅ Successfully enrolled {$enrollment_count} student-subject combinations!";
            $type = "success";
        } else {
            $message = "ℹ️ No new enrollments were created. Students may already be enrolled in all subjects.";
            $type = "info";
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $type = "error";
    }
}

// ==================== FETCH STUDENTS FOR DISPLAY ====================
$students = [];
$subject_info = [];
if ($selectedProgram && ($_GET['semester_id'] ?? null) && ($_GET['section_id'] ?? null)) {

    $semester_id = $_GET['semester_id'];
    $section_id  = $_GET['section_id'];  

    // Get subject information for display
    $subjectInfoStmt = $pdo->prepare("
        SELECT subject_id, subject_name, subject_code, credit_hours 
        FROM subject 
        WHERE program_id = ? AND semester_id = ? AND status='active'
        ORDER BY subject_code ASC
    ");
    $subjectInfoStmt->execute([$selectedProgram, $semester_id]);
    $subject_info = $subjectInfoStmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ CORRECTED: Fetch students for the selected program and section
    $studentsStmt = $pdo->prepare("
        SELECT s.student_id, s.full_name, s.reg_no, s.email, s.phone_number
        FROM students s
        WHERE s.program_id = ?
          AND s.section_id = ?
          AND s.status = 'active'
        ORDER BY s.full_name ASC
    ");

    $studentsStmt->execute([$selectedProgram, $section_id]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
}

include('../includes/header.php');
?>

<div class="main-content">
    <!-- <div class="page-header">
        <h1>Bulk Student Enrollment</h1>
        <div class="faculty-badge">Faculty: <?= htmlspecialchars($current_faculty['faculty_name'] ?? 'N/A') ?></div>
    </div> -->

    <!-- SELECTION FORM -->
    <div class="filter-box">
        <form method="GET">
            <div class="grid">

                <!-- FACULTY (Auto-selected) -->
                <div>
                    <label>Faculty</label>
                    <select name="faculty_id" required disabled style="background: #e8f5e8;">
                        <option value="<?= $faculty_id ?>"><?= htmlspecialchars($current_faculty['faculty_name'] ?? 'Your Faculty') ?></option>
                    </select>
                    <input type="hidden" name="faculty_id" value="<?= $faculty_id ?>">
                </div>

                <!-- DEPARTMENT -->
                <div>
                    <label>Department *</label>
                    <select name="department_id" required onchange="this.form.submit()">
                        <option value="">Select Department</option>
                        <?php foreach($departments as $d): ?>
                            <option value="<?= $d['department_id'] ?>" <?= ($_GET['department_id']??'')==$d['department_id']?'selected':'' ?>>
                                <?= htmlspecialchars($d['department_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- PROGRAM -->
                <div>
                    <label>Program *</label>
                    <select name="program_id" required onchange="this.form.submit()" <?= empty($programs)?'disabled':'' ?>>
                        <option value="">Select Program</option>
                        <?php foreach($programs as $p): ?>
                            <option value="<?= $p['program_id'] ?>" <?= ($_GET['program_id']??'')==$p['program_id']?'selected':'' ?>>
                                <?= htmlspecialchars($p['program_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- SECTION -->
                <div>
                    <label>Section *</label>
                    <select name="section_id" required onchange="this.form.submit()" <?= empty($sections)?'disabled':'' ?>>
                        <option value="">Select Section</option>
                        <?php foreach($sections as $sec): ?>
                            <option value="<?= $sec['section_id'] ?>" <?= ($_GET['section_id']??'')==$sec['section_id']?'selected':'' ?>>
                                <?= htmlspecialchars($sec['section_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- SEMESTER -->
                <div>
                    <label>Semester *</label>
                    <select name="semester_id" required onchange="this.form.submit()">
                        <option value="">Select Semester</option>
                        <?php foreach($semesters as $s): ?>
                            <option value="<?= $s['semester_id'] ?>" <?= ($_GET['semester_id']??'')==$s['semester_id']?'selected':'' ?>>
                                <?= htmlspecialchars($s['semester_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="align-self:end;">
                    <button type="submit" class="btn blue" <?= !$academic_term_id ? 'disabled' : '' ?>>
                        <i class="fas fa-search"></i> Load Students
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- SUBJECT INFORMATION -->
    <?php if (!empty($subject_info) && isset($_GET['program_id'], $_GET['semester_id'])): ?>
    <div class="subject-info">
        <h3><i class="fas fa-book"></i> Available Subjects for Enrollment</h3>
        <div class="subject-grid">
            <?php foreach($subject_info as $subject): ?>
                <div class="subject-card">
                    <strong><?= htmlspecialchars($subject['subject_code']) ?></strong>
                    <div><?= htmlspecialchars($subject['subject_name']) ?></div>
                    <small><?= $subject['credit_hours'] ?> Credits</small>
                </div>
            <?php endforeach; ?>
        </div>
        <p><small><strong>Total:</strong> <?= count($subject_info) ?> subjects will be enrolled for each selected student</small></p>
    </div>
    <?php endif; ?>

    <!-- STUDENTS LIST -->
    <?php if (isset($_GET['program_id'], $_GET['semester_id'], $_GET['section_id'])): ?>
    <div class="table-wrapper">
        <form method="POST" id="enrollmentForm">
            <input type="hidden" name="program_id" value="<?= htmlspecialchars($_GET['program_id']) ?>">
            <input type="hidden" name="semester_id" value="<?= htmlspecialchars($_GET['semester_id']) ?>">
            <input type="hidden" name="section_id" value="<?= htmlspecialchars($_GET['section_id']) ?>">

            <div class="table-header">
                <h3><i class="fas fa-users"></i> Select Students to Enroll</h3>
                <div class="selection-info">
                    <span id="selectedCount">0</span> students selected
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" id="checkAll">
                        </th>
                        <th width="60">#</th>
                        <th>Full Name</th>
                        <th>Registration No</th>
                        <th>Contact</th>
                        <th>Enrollment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($students): ?>
                        <?php $i=1; foreach($students as $s): 
                            // Check if student is already enrolled in any subject for this term
                            $enrollmentCheck = $pdo->prepare("
                                SELECT COUNT(*) FROM student_enroll 
                                WHERE student_id = ? AND academic_term_id = ? AND section_id = ?
                            ");
                            $enrollmentCheck->execute([$s['student_id'], $academic_term_id, $_GET['section_id']]);
                            $isEnrolled = $enrollmentCheck->fetchColumn() > 0;
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="student_ids[]" value="<?= $s['student_id'] ?>" class="student-checkbox" <?= $isEnrolled ? 'disabled' : '' ?>>
                                </td>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($s['full_name']) ?></td>
                                <td><code><?= htmlspecialchars($s['reg_no']) ?></code></td>
                                <td>
                                    <small><?= htmlspecialchars($s['email']) ?><br>
                                    <?= htmlspecialchars($s['phone_number']) ?></small>
                                </td>
                                <td>
                                    <?php if($isEnrolled): ?>
                                        <span class="status-badge status-enrolled">Already Enrolled</span>
                                    <?php else: ?>
                                        <span class="status-badge status-available">Available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;color:#777;padding:30px;">
                                <i class="fas fa-user-check" style="font-size:48px;color:#ccc;margin-bottom:10px;display:block;"></i>
                                No students found for this selection.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if($students): ?>
            <div class="form-actions">
                <button type="submit" name="enroll_selected" class="btn green" id="enrollBtn" disabled>
                    <i class="fas fa-user-plus"></i> 
                    Enroll Selected Students 
                    <span id="enrollCount">(0)</span>
                </button>
                <small style="color:#666;margin-left:15px;">
                    Each student will be enrolled in <?= count($subject_info) ?> subjects
                </small>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- ALERT MESSAGE -->
<?php if($message): ?>
<div class="alert-popup <?= $type ?>">
    <div class="alert-content">
        <i class="fas <?= $type === 'success' ? 'fa-check-circle' : ($type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>"></i>
        <span><?= $message ?></span>
    </div>
</div>
<script>
    setTimeout(() => {
        const alert = document.querySelector('.alert-popup');
        if (alert) alert.remove();
    }, 5000);
</script>
<?php endif; ?>

<!-- STYLES -->
<style>
:root {
    --green: #00843D;
    --light-green: #00A651;
    --blue: #0072CE;
    --red: #C62828;
    --orange: #FF9800;
    --bg: #F5F9F7;
}

body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg);
    margin: 0;
}

.main-content {
    padding: 25px;
    margin-left: 250px;
    margin-top: 90px;
    transition: all 0.3s;
}

.sidebar.collapsed ~ .main-content {
    margin-left: 70px;
}

.page-header h1 {
    color: var(--blue);
    margin-bottom: 15px;
    font-size: 24px;
}

.faculty-badge {
    background: var(--blue);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 15px;
}

.filter-box {
    background: #fff;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    align-items: end;
}

label {
    font-weight: 600;
    color: var(--blue);
    font-size: 13px;
    margin-bottom: 8px;
    display: block;
}

select, input[type="text"], input[type="number"] {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    font-family: inherit;
    background: #fff;
    transition: border-color 0.3s;
}

select:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 114, 206, 0.1);
}

.btn {
    border: none;
    padding: 12px 20px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 14px;
}

.btn.green {
    background: var(--green);
    color: #fff;
}

.btn.blue {
    background: var(--blue);
    color: #fff;
}

.btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.table-wrapper {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    overflow: hidden;
    margin-top: 20px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 1px solid #eee;
    background: #f8fafc;
}

.table-header h3 {
    color: var(--blue);
    margin: 0;
}

.selection-info {
    background: var(--blue);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 14px 16px;
    border-bottom: 1px solid #eee;
    text-align: left;
    font-size: 14px;
}

thead th {
    background: var(--blue);
    color: #fff;
    position: sticky;
    top: 0;
    font-weight: 600;
}

tr:hover {
    background: #f8fafc;
}

.subject-info {
    background: #e8f5e8;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid var(--green);
}

.subject-info h3 {
    color: var(--green);
    margin-top: 0;
    margin-bottom: 15px;
}

.subject-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
    margin-bottom: 15px;
}

.subject-card {
    background: white;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ddd;
    font-size: 13px;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-available {
    background: #e8f5e8;
    color: #2e7d32;
}

.status-enrolled {
    background: #fff3e0;
    color: #ef6c00;
}

.form-actions {
    padding: 20px 25px;
    border-top: 1px solid #eee;
    background: #f8fafc;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.alert-popup {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 0;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    z-index: 9999;
    border-left: 4px solid var(--green);
    max-width: 400px;
}

.alert-popup.success {
    border-left-color: var(--green);
}

.alert-popup.error {
    border-left-color: var(--red);
}

.alert-popup.info {
    border-left-color: var(--blue);
}

.alert-content {
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.alert-content i {
    font-size: 20px;
}

.alert-popup.success .alert-content i {
    color: var(--green);
}

.alert-popup.error .alert-content i {
    color: var(--red);
}

.alert-popup.info .alert-content i {
    color: var(--blue);
}

input[type="checkbox"] {
    transform: scale(1.2);
    cursor: pointer;
}

input[type="checkbox"]:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    
    .grid {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .subject-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- JAVASCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const checkAll = document.getElementById('checkAll');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox');
    const selectedCount = document.getElementById('selectedCount');
    const enrollCount = document.getElementById('enrollCount');
    const enrollBtn = document.getElementById('enrollBtn');
    const subjectCount = <?= count($subject_info) ?>;

    // Select all functionality (only enabled checkboxes)
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            studentCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = checkAll.checked;
                }
            });
            updateSelectionCount();
        });
    }

    // Update selection count
    function updateSelectionCount() {
        const selected = document.querySelectorAll('.student-checkbox:checked').length;
        if (selectedCount) selectedCount.textContent = selected;
        if (enrollCount) enrollCount.textContent = `(${selected})`;
        if (enrollBtn) enrollBtn.disabled = selected === 0;
        
        // Update button text with subject count information
        if (selected > 0 && subjectCount > 0) {
            enrollBtn.innerHTML = `<i class="fas fa-user-plus"></i> Enroll Selected Students (${selected})`;
        }
    }

    // Add event listeners to individual checkboxes
    studentCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionCount);
    });

    // Initial count update
    updateSelectionCount();

    // Form submission confirmation
    const enrollmentForm = document.getElementById('enrollmentForm');
    if (enrollmentForm) {
        enrollmentForm.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.student-checkbox:checked').length;
            if (selected === 0) {
                e.preventDefault();
                alert('Please select at least one student to enroll.');
                return false;
            }
            
            const totalEnrollments = selected * subjectCount;
            return confirm(`You are about to create ${totalEnrollments} student-subject enrollments. Continue?`);
        });
    }
});
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>