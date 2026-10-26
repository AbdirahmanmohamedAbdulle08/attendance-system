<?php
/*******************************************************************************************
 * BULK STUDENT ENROLLMENT — Hormuud University
 * Enroll Multiple Students into Program / Semester / Section
 * Handles subject_id foreign key correctly (adds all subjects automatically)
 * Super Admin Panel | PHP 8.2 | PDO Secure Queries
 * Author: ChatGPT 2025
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

$role = strtolower($_SESSION['user']['role'] ?? '');
if (!in_array($role, [ 'super_admin'])) {
  header("Location: ../login.php");
  exit;
}

$user = $_SESSION['user'] ?? [];
$campus_id = $user['linked_id'] ?? null;


date_default_timezone_set('Africa/Nairobi');
$message = "";
$type = "";

/* =============== ACTIVE TERM =============== */
$term = $pdo->query("SELECT academic_term_id FROM academic_term WHERE status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$academic_term_id = $term['academic_term_id'] ?? null;

/* =============== BULK ENROLL ACTION =============== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_selected'])) {
  try {
    $pdo->beginTransaction();

    $program_id  = $_POST['program_id'];
    $semester_id = $_POST['semester_id'];
    $section_id  = $_POST['section_id'];
    $students    = $_POST['student_ids'] ?? [];

    if (empty($students)) throw new Exception("No students selected!");
    if (!$academic_term_id) throw new Exception("No active academic term found!");

    // ✅ Get all subjects under this program + semester
    $subjects = $pdo->prepare("SELECT subject_id FROM subject WHERE program_id=? AND semester_id=?");
    $subjects->execute([$program_id, $semester_id]);
    $subjectList = $subjects->fetchAll(PDO::FETCH_COLUMN);

    if (empty($subjectList)) {
      throw new Exception("No subjects found for the selected program and semester!");
    }

    // ✅ Prepare insert statement (includes subject_id)
    $stmt = $pdo->prepare("
      INSERT INTO student_enroll 
      (student_id, program_id, semester_id, section_id, subject_id, academic_term_id, status, created_at)
      VALUES (?,?,?,?,?,?,'active',NOW())
    ");

    foreach ($students as $sid) {
      foreach ($subjectList as $subj_id) {
        // Avoid duplicate enrollment for same subject in same term
        $check = $pdo->prepare("
          SELECT enroll_id FROM student_enroll 
          WHERE student_id=? AND academic_term_id=? AND subject_id=?
        ");
        $check->execute([$sid, $academic_term_id, $subj_id]);
        if ($check->rowCount() == 0) {
          $stmt->execute([$sid, $program_id, $semester_id, $section_id, $subj_id, $academic_term_id]);
        }
      }
    }

    $pdo->commit();
    $message = "✅ Selected students enrolled successfully into all subjects!";
    $type = "success";
  } catch (Exception $e) {
    $pdo->rollBack();
    $message = "❌ Error: " . $e->getMessage();
    $type = "error";
  }
}

/* =============== FETCH DATA =============== */
/* =============== FETCH DATA =============== */
if (in_array($role, ['campus_admin', 'super_admin'])) {

  // ✅ 1. CAMPUS SELECTION (super_admin only)
  if ($role === 'super_admin') {
    $campus = $pdo->query("
      SELECT campus_id, campus_name
      FROM campus
      ORDER BY campus_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    $selectedCampus = $_GET['campus_id'] ?? null;
  } else {
    // campus_admin
    $selectedCampus = $campus_id;
    $campus = $pdo->prepare("
      SELECT campus_id, campus_name
      FROM campus
      WHERE campus_id = ?
    ");
    $campus->execute([$campus_id]);
    $campus = $campus->fetchAll(PDO::FETCH_ASSOC);
  }

  // ✅ 2. Faculties ee campus la doortay
  if (!empty($selectedCampus)) {
    $faculties = $pdo->prepare("
      SELECT faculty_id, faculty_name
      FROM faculties
      WHERE campus_id = ?
      ORDER BY faculty_name ASC
    ");
    $faculties->execute([$selectedCampus]);
    $faculties = $faculties->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $faculties = [];
  }

  // ✅ 3. Departments ee faculty la doortay
  $selectedFaculty = $_GET['faculty_id'] ?? null;
  if (!empty($selectedFaculty)) {
    $departments = $pdo->prepare("
      SELECT department_id, department_name
      FROM departments
      WHERE faculty_id = ?
      ORDER BY department_name ASC
    ");
    $departments->execute([$selectedFaculty]);
    $departments = $departments->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $departments = [];
  }

  // ✅ 4. Programs ee department la doortay
  $selectedDepartment = $_GET['department_id'] ?? null;
  if (!empty($selectedDepartment)) {
    $programs = $pdo->prepare("
      SELECT program_id, program_name
      FROM programs
      WHERE department_id = ?
      ORDER BY program_name ASC
    ");
    $programs->execute([$selectedDepartment]);
    $programs = $programs->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $programs = [];
  }

  // ✅ 5. Sections ee program la doortay
  $selectedProgram = $_GET['program_id'] ?? null;
  if (!empty($selectedProgram)) {
    $sections = $pdo->prepare("
      SELECT section_id, section_name
      FROM class_section
      WHERE program_id = ?
      ORDER BY section_name ASC
    ");
    $sections->execute([$selectedProgram]);
    $sections = $sections->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $sections = [];
  }

  // ✅ 6. Semesters guud (global)
  $semesters = $pdo->query("
    SELECT semester_id, semester_name
    FROM semester
    ORDER BY semester_id ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
}

include('../includes/header.php');
?>
<style>
  .sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}
</style>

<div class="main-content">
  <div class="page-header">
    <h1>Bulk Student Enrollment</h1>
  </div>

  <!-- SELECTION FORM -->
<div class="filter-box">
  <form method="GET">
    <div class="grid">

      <!-- CAMPUS (Only for Super Admin) -->
      <?php if ($role === 'super_admin'): ?>
      <div>
        <label>Campus</label>
        <select name="campus_id" required onchange="this.form.submit()">
          <option value="">Select Campus</option>
          <?php foreach($campus as $c): ?>
            <option value="<?= $c['campus_id'] ?>" <?= ($_GET['campus_id']??'')==$c['campus_id']?'selected':'' ?>>
              <?= htmlspecialchars($c['campus_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>

      <!-- FACULTY -->
      <div>
        <label>Faculty</label>
        <select name="faculty_id" required <?= empty($faculties)?'disabled':'' ?> onchange="this.form.submit()">
          <option value="">Select Faculty</option>
          <?php foreach($faculties as $f): ?>
            <option value="<?= $f['faculty_id'] ?>" <?= ($_GET['faculty_id']??'')==$f['faculty_id']?'selected':'' ?>>
              <?= htmlspecialchars($f['faculty_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- DEPARTMENT -->
      <div>
        <label>Department</label>
        <select name="department_id" <?= empty($departments)?'disabled':'' ?> onchange="this.form.submit()">
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
        <label>Program</label>
        <select name="program_id" <?= empty($programs)?'disabled':'' ?> onchange="this.form.submit()">
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
        <label>Section</label>
        <select name="section_id" <?= empty($sections)?'disabled':'' ?>>
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
        <label>Semester</label>
        <select name="semester_id" required>
          <option value="">Select Semester</option>
          <?php foreach($semesters as $s): ?>
            <option value="<?= $s['semester_id'] ?>" <?= ($_GET['semester_id']??'')==$s['semester_id']?'selected':'' ?>>
              <?= htmlspecialchars($s['semester_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="align-self:end;">
        <button type="submit" class="btn blue"><i class="fa fa-search"></i> Load Students</button>
      </div>

    </div>
  </form>
</div>



  <!-- STUDENTS LIST -->
  <?php if (isset($_GET['program_id'], $_GET['semester_id'], $_GET['section_id'])): 
  $program_id  = $_GET['program_id'];
  $semester_id = $_GET['semester_id'];
  $section_id  = $_GET['section_id'];

  if ($role === 'campus_admin' && $campus_id) {
    // ✅ Ardayda ka tirsan campus-kiisa oo aan weli la diiwaangelin term-kan
    $students = $pdo->prepare("
      SELECT s.student_id, s.full_name, s.reg_no
      FROM students s
      JOIN programs p ON s.program_id = p.program_id
      JOIN departments d ON p.department_id = d.department_id
      JOIN faculties f ON d.faculty_id = f.faculty_id
      WHERE s.program_id = ?
        AND f.campus_id = ?
        AND s.student_id NOT IN (
          SELECT student_id FROM student_enroll WHERE academic_term_id = ?
        )
      ORDER BY s.full_name ASC
    ");
    $students->execute([$program_id, $campus_id, $academic_term_id]);
  } else {
    // ✅ Super admin: ardayda program-kaas oo aan weli la diiwaangelin
    $students = $pdo->prepare("
      SELECT s.student_id, s.full_name, s.reg_no 
      FROM students s
      WHERE s.program_id = ? 
        AND s.student_id NOT IN (
          SELECT student_id FROM student_enroll WHERE academic_term_id = ?
        )
      ORDER BY s.full_name ASC
    ");
    $students->execute([$program_id, $academic_term_id]);
  }

  $students = $students->fetchAll(PDO::FETCH_ASSOC);
?>

  <div class="table-wrapper">
    <form method="POST">
      <input type="hidden" name="program_id" value="<?= htmlspecialchars($program_id) ?>">
      <input type="hidden" name="semester_id" value="<?= htmlspecialchars($semester_id) ?>">
      <input type="hidden" name="section_id" value="<?= htmlspecialchars($section_id) ?>">

      <h3 style="color:#0072CE;margin:10px;">Select Students to Enroll</h3>
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" id="checkAll"></th>
            <th>#</th>
            <th>Full Name</th>
            <th>Reg No</th>
          </tr>
        </thead>
        <tbody>
          <?php if($students): $i=1; foreach($students as $s): ?>
            <tr>
              <td><input type="checkbox" name="student_ids[]" value="<?= $s['student_id'] ?>"></td>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($s['full_name']) ?></td>
              <td><?= htmlspecialchars($s['reg_no']) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;color:#777;">No students found for this program or all are enrolled.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if($students): ?>
      <div style="padding:10px;text-align:right;">
        <button type="submit" name="enroll_selected" class="btn green"><i class="fa fa-user-plus"></i> Enroll Selected Students</button>
      </div>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php if($message): ?>
<div class="alert <?= $type ?>"><strong><?= $message ?></strong></div>
<script>setTimeout(()=>document.querySelector('.alert').remove(),5000);</script>
<?php endif; ?>

<!-- STYLE -->
<style>
body{font-family:'Poppins',sans-serif;background:#f7f9fb;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.page-header h1{color:#0072CE;margin-bottom:15px;}
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:15px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;}
label{font-weight:600;color:#0072CE;font-size:13px;}
select{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}
.btn{border:none;padding:9px 15px;border-radius:6px;font-weight:600;cursor:pointer;}
.btn.green{background:#00843D;color:#fff;}
.btn.blue{background:#0072CE;color:#fff;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
.alert{position:fixed;top:15px;right:15px;background:#00843D;color:#fff;padding:10px 20px;border-radius:6px;font-weight:600;z-index:9999;}
.alert.error{background:#C62828;}
@media(max-width:768px){.main-content{margin-left:0;padding:15px;}.grid{grid-template-columns:1fr;}}
</style>

<script>
document.addEventListener("DOMContentLoaded", ()=>{
  const checkAll=document.getElementById('checkAll');
  if(checkAll){
    checkAll.addEventListener('change', ()=>{
      document.querySelectorAll('input[name="student_ids[]"]').forEach(ch=>{
        ch.checked = checkAll.checked;
      });
    });
  }
});
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>
