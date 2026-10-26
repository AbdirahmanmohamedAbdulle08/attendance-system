<?php
/*******************************************************************************************
 * STUDENT PROMOTION + FULL ACADEMIC HIERARCHY — Hormuud University
 * Campus → Faculty → Department → Program → Section → Semester → Subject
 * Super Admin Panel | PHP 8.2 | PDO Secure Queries
 * Author: ChatGPT 2025
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

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

// ==================== GET FACULTY & CAMPUS INFO ====================
$faculty_info = $pdo->prepare("
    SELECT f.*, c.campus_name 
    FROM faculties f 
    LEFT JOIN campus c ON f.campus_id = c.campus_id 
    WHERE f.faculty_id = ?
");
$faculty_info->execute([$faculty_id]);
$faculty_data = $faculty_info->fetch(PDO::FETCH_ASSOC);

if (!$faculty_data) {
    header("Location: ../login.php");
    exit;
}

$user_campus_id = $faculty_data['campus_id'];
$user_faculty_id = $faculty_data['faculty_id'];
$user_campus_name = $faculty_data['campus_name'];
$user_faculty_name = $faculty_data['faculty_name'];

date_default_timezone_set('Africa/Nairobi');
$message = ""; 
$type = "";

/* ================= HANDLE PROMOTION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote'])) {
  try {
    $pdo->beginTransaction();

    $student_ids     = $_POST['student_ids']  ?? [];
    $new_semester_id = $_POST['new_semester_id'] ?? null;
    $subject_ids     = $_POST['subject_ids']  ?? [];
    $remarks         = trim($_POST['remarks'] ?? '');
    $admin_id        = $_SESSION['user']['id'] ?? null;

    // ➕ cusub: xogta "To" hierarchy
    $new_campus_id   = $_POST['to_campus'] ?? null;
    $new_faculty_id  = $_POST['to_faculty'] ?? null;
    $new_department_id = $_POST['to_department'] ?? null;
    $new_program_id  = $_POST['to_program'] ?? null;
    $new_section_id  = $_POST['to_section'] ?? null;

    if (empty($student_ids))      throw new Exception("Please select at least one student!");
    if (empty($new_semester_id))  throw new Exception("Please select a new semester!");
    if (empty($new_campus_id))    throw new Exception("Please select a destination campus!");

    foreach ($student_ids as $student_id) {

      // hel xogtii hore ee student_enroll
      $old = $pdo->prepare("SELECT * FROM student_enroll WHERE student_id=? LIMIT 1");
      $old->execute([$student_id]);
      $old_data = $old->fetch(PDO::FETCH_ASSOC);
      if (!$old_data) continue;

      // kaydi taariikhda wareejinta
      $insert = $pdo->prepare("
        INSERT INTO promotion_history
        (student_id,
         old_faculty_id, old_department_id, old_program_id, old_semester_id,
         new_faculty_id, new_department_id, new_program_id, new_semester_id,
         old_campus_id, new_campus_id,
         promoted_by, remarks)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
      ");
      $insert->execute([
        $student_id,
        $old_data['faculty_id']    ?? null,
        $old_data['department_id'] ?? null,
        $old_data['program_id']    ?? null,
        $old_data['semester_id']   ?? null,
        $new_faculty_id,
        $new_department_id,
        $new_program_id,
        $new_semester_id,
        $old_data['campus_id']     ?? null,
        $new_campus_id,
        $admin_id,
        $remarks
      ]);

      // ✅ update student_enroll oo leh xogta cusub
      $update = $pdo->prepare("
        UPDATE student_enroll 
        SET campus_id=?, faculty_id=?, department_id=?, program_id=?, section_id=?, semester_id=?, updated_at=NOW() 
        WHERE student_id=?
      ");
      $update->execute([
        $new_campus_id,
        $new_faculty_id,
        $new_department_id,
        $new_program_id,
        $new_section_id,
        $new_semester_id,
        $student_id
      ]);

      // link subjects
      foreach ($subject_ids as $sub_id) {
        $pdo->prepare("
          REPLACE INTO student_subject (student_id, subject_id, assigned_at)
          VALUES (?,?,NOW())
        ")->execute([$student_id, $sub_id]);
      }
    }

    $pdo->commit();
    $message = "✅ Selected students promoted / transferred successfully!";
    $type = "success";

  } catch (Exception $e) {
    $pdo->rollBack();
    $message = "❌ Error: " . $e->getMessage();
    $type = "error";
  }
}


/* ================= FETCH DATA ================= */


/* ✅ Campuses */
$campuses = $pdo->query("
  SELECT 
    campus_id, 
    campus_name, 
    campus_code, 
    address, 
    city, 
    country, 
    status
  FROM campus 
  WHERE status = 'active'
  ORDER BY campus_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Faculties (each linked to campus) */
$faculties = $pdo->query("
  SELECT 
    faculty_id, 
    faculty_name, 
    faculty_code, 
    campus_id, 
    dean_name, 
    status
  FROM faculties
  WHERE status = 'active'
  ORDER BY faculty_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Departments (linked to faculty + campus) */
$departments = $pdo->query("
  SELECT 
    department_id, 
    department_name, 
    faculty_id, 
    campus_id, 
    status
  FROM departments
  WHERE status = 'active'
  ORDER BY department_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Programs (linked to department + campus) */
$programs = $pdo->query("
  SELECT 
    program_id, 
    program_name, 
    department_id, 
    campus_id, 
    status
  FROM programs
  WHERE status = 'active'
  ORDER BY program_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Sections (linked to program + campus) */
$sections = $pdo->query("
  SELECT 
    section_id, 
    section_name, 
    program_id, 
    campus_id, 
    status
  FROM class_section
  WHERE status = 'active'
  ORDER BY section_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Semesters (standard academic levels) */
$semesters = $pdo->query("
  SELECT 
    semester_id, 
    semester_name, 
    status
  FROM semester 
  WHERE status = 'active'
  ORDER BY semester_id ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Subjects (linked to semester + campus) */
$subjects = $pdo->query("
  SELECT 
    subject_id, 
    subject_name, 
    semester_id, 
    campus_id, 
    status
  FROM subject
  WHERE status = 'active'
  ORDER BY subject_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Students (with ALL enrollment data for filtering) - ONLY FROM USER'S FACULTY - DISTINCT */
$students = $pdo->prepare("
  SELECT DISTINCT
    s.student_id, 
    s.full_name, 
    s.reg_no, 
    e.semester_id, 
    e.section_id,
    e.campus_id,
    e.faculty_id, 
    e.department_id,
    e.program_id,
    se.semester_name,
    cs.section_name,
    p.program_name,
    d.department_name
  FROM students s
  JOIN student_enroll e ON e.student_id = s.student_id
  LEFT JOIN semester se ON se.semester_id = e.semester_id
  LEFT JOIN class_section cs ON cs.section_id = e.section_id
  LEFT JOIN programs p ON p.program_id = e.program_id
  LEFT JOIN departments d ON d.department_id = e.department_id
  WHERE e.faculty_id = ? AND e.campus_id = ?
  ORDER BY s.full_name ASC
");
$students->execute([$user_faculty_id, $user_campus_id]);
$students = $students->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Promotion History (recent 50 promotions) */
$history = $pdo->query("
  SELECT 
    ph.*, 
    s.full_name, 
    se.semester_name AS new_sem
  FROM promotion_history ph
  JOIN students s 
    ON s.student_id = ph.student_id
  LEFT JOIN semester se 
    ON se.semester_id = ph.new_semester_id
  ORDER BY ph.promotion_date DESC
  LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

include('../includes/header.php');
?>
<style>
  .sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

</style>
<div class="main-content">
  <div class="page-header"><h1>Student Promotion</h1></div>

<!-- PROMOTION FORM -->
<div class="filter-box">
  <form method="POST">
    <div class="grid">

      <!-- ========== FROM HIERARCHY ========== -->
      <fieldset style="border:1px solid #ccc;border-radius:8px;padding:10px;">
        <legend style="color:#0072CE;font-weight:600;">From</legend>
        
        <div>
          <label>From Campus</label>
          <input type="text" value="<?= htmlspecialchars($user_campus_name) ?>" readonly style="background:#f0f0f0;">
          <input type="hidden" id="from_campus" value="<?= $user_campus_id ?>">
        </div>

        <div>
          <label>From Faculty</label>
          <input type="text" value="<?= htmlspecialchars($user_faculty_name) ?>" readonly style="background:#f0f0f0;">
          <input type="hidden" id="from_faculty" value="<?= $user_faculty_id ?>">
        </div>

        <div>
          <label>From Department</label>
          <select id="from_department" onchange="filterFromPrograms()" required>
            <option value="">Select Department</option>
            <?php 
// Kaliya active departments-ka faculty-ga
$user_departments = array_filter($departments, function($dept) use ($user_faculty_id, $user_campus_id) {
    return $dept['faculty_id'] == $user_faculty_id && $dept['campus_id'] == $user_campus_id;
});
              
foreach($user_departments as $d): ?>
  <option value="<?= $d['department_id'] ?>">
    <?= htmlspecialchars($d['department_name']) ?>
  </option>
<?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>From Program</label>
          <select id="from_program" onchange="filterFromSections()" required>
            <option value="">Select Program</option>
          </select>
        </div>

        <div>
          <label>From Section</label>
          <select id="from_section" onchange="filterFromStudents()" required>
            <option value="">Select Section</option>
          </select>
        </div>

        <div class="checkbox-group">
          <label>Students (by Section)</label>
          <div class="checkbox-list" id="from_student_list">
            <small style="color:#777;">Select section to list students.</small>
          </div>
        </div>
      </fieldset>

      <!-- ========== TO HIERARCHY ========== -->
      <fieldset style="border:1px solid #ccc;border-radius:8px;padding:10px;">
        <legend style="color:#0072CE;font-weight:600;">To</legend>
        
        <div>
          <label>To Campus</label>
          <select id="to_campus" name="to_campus" onchange="filterToFaculties()" required>
            <option value="">Select Campus</option>
            <?php foreach($campuses as $c): ?>
              <option value="<?= $c['campus_id'] ?>">
                <?= htmlspecialchars($c['campus_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label>To Faculty</label>
          <select id="to_faculty" name="to_faculty" onchange="filterToDepartments()" required>
            <option value="">Select Faculty</option>
          </select>
        </div>

        <div>
          <label>To Department</label>
          <select id="to_department" name="to_department" onchange="filterToPrograms()" required>
            <option value="">Select Department</option>
          </select>
        </div>

        <div>
          <label>To Program</label>
          <select id="to_program" name="to_program" onchange="filterToSections()" required>
            <option value="">Select Program</option>
          </select>
        </div>

        <div>
          <label>To Section</label>
          <select id="to_section" name="to_section" required>
            <option value="">Select Section</option>
          </select>
        </div>

        <div>
          <label>New Semester</label>
          <select name="new_semester_id" onchange="filterToSubjects()" required>
            <option value="">Select Semester</option>
            <?php foreach($semesters as $sem): ?>
              <option value="<?= $sem['semester_id'] ?>">
                <?= htmlspecialchars($sem['semester_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="checkbox-group">
          <label>Subjects (by Semester)</label>
          <div class="checkbox-list" id="to_subject_list">
            <small style="color:#777;">Select semester to list subjects.</small>
          </div>
        </div>

        <div>
          <label>Remarks</label>
          <input type="text" name="remarks" placeholder="Optional comment...">
        </div>

        <div style="align-self:end;">
          <button type="submit" name="promote" class="btn green">
            <i class="fa fa-arrow-up"></i> Promote Selected Students
          </button>
        </div>
      </fieldset>

    </div>
  </form>
</div>

  <!-- PROMOTION HISTORY -->
  <div class="table-wrapper">
    <h3 style="color:#0072CE;margin:10px;">Recent Promotion History</h3>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Student</th>
          <th>Old Semester</th>
          <th>New Semester</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($history as $h): ?>
        <tr>
          <td><?= htmlspecialchars($h['promotion_date']) ?></td>
          <td><?= htmlspecialchars($h['full_name']) ?></td>
          <td><?= htmlspecialchars($h['old_semester_id']) ?></td>
          <td><?= htmlspecialchars($h['new_sem']) ?></td>
          <td><?= htmlspecialchars($h['remarks']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if($message): ?>
<div class="alert <?= $type ?>"><strong><?= $message ?></strong></div>
<script>
  setTimeout(()=>document.querySelector('.alert').remove(),5000);
</script>
<?php endif; ?>

<!-- STYLE -->
<style>
body{font-family:'Poppins',sans-serif;background:#f5f9f7;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.page-header h1{color:#0072CE;margin-bottom:15px;}
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:15px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;}
label{font-weight:600;color:#0072CE;font-size:13px;}
select,input{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}
.btn{border:none;padding:9px 15px;border-radius:6px;font-weight:600;cursor:pointer;}
.btn.green{background:#00843D;color:#fff;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
.alert{position:fixed;top:15px;right:15px;background:#00843D;color:#fff;padding:10px 20px;border-radius:6px;font-weight:600;z-index:9999;}
.alert.error{background:#C62828;}
.checkbox-list{
  max-height:220px;
  overflow-y:auto;
  border:1px solid #ddd;
  border-radius:6px;
  padding:6px;
  background:#f9f9f9;
}
.checkbox-list label{
  display:block;
  padding:3px 2px;
  font-size:13px;
  cursor:pointer;
}
.checkbox-list input{margin-right:6px;}
</style>

<!-- FILTER SCRIPT -->
<script>
const faculties   = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;
const programs    = <?= json_encode($programs) ?>;
const sections    = <?= json_encode($sections) ?>;
const subjects    = <?= json_encode($subjects) ?>;
const students    = <?= json_encode($students) ?>;

// Auto-populate FROM programs and sections on page load
document.addEventListener('DOMContentLoaded', function() {
  // Don't show students initially - wait for section selection
  document.getElementById('from_student_list').innerHTML = '<small style="color:#777;">Select section to list students.</small>';
});

/* ---------------- FROM FILTERS ---------------- */
function filterFromPrograms(){
  const dId = document.getElementById('from_department').value;
  const campusId = document.getElementById('from_campus').value;
  const p = document.getElementById('from_program');
  p.innerHTML = '<option value="">Select Program</option>';
  resetCascade('from', ['section','student_list']);

  if (!dId) return;

  programs.forEach(x=>{
    if (String(x.department_id) === String(dId) && String(x.campus_id) === String(campusId)) {
      p.innerHTML += `<option value="${x.program_id}">${x.program_name}</option>`;
    }
  });
}

function filterFromSections(){
  const pId = document.getElementById('from_program').value;
  const campusId = document.getElementById('from_campus').value;
  const s = document.getElementById('from_section');
  s.innerHTML = '<option value="">Select Section</option>';
  resetCascade('from', ['student_list']);

  if (!pId) return;

  sections.forEach(x=>{
    if (String(x.program_id) === String(pId) && String(x.campus_id) === String(campusId)) {
      s.innerHTML += `<option value="${x.section_id}">${x.section_name}</option>`;
    }
  });
}

function filterFromStudents(){
  const secId = document.getElementById('from_section').value;
  const deptId = document.getElementById('from_department').value;
  const progId = document.getElementById('from_program').value;
  const campusId = document.getElementById('from_campus').value;
  const facultyId = document.getElementById('from_faculty').value;
  
  const box = document.getElementById('from_student_list');
  
  // Only show students if section is selected
  if (!secId) {
    box.innerHTML = '<small style="color:#777;">Select section to list students.</small>';
    return;
  }

  box.innerHTML = '';

  let filteredStudents = students.filter(st => {
    // Filter by faculty and campus (required)
    if (String(st.faculty_id) !== String(facultyId)) return false;
    if (String(st.campus_id) !== String(campusId)) return false;
    
    // Optional filters
    if (deptId && String(st.department_id) !== String(deptId)) return false;
    if (progId && String(st.program_id) !== String(progId)) return false;
    if (secId && String(st.section_id) !== String(secId)) return false;
    
    return true;
  });

  // Remove duplicates by student_id
  const uniqueStudents = [];
  const seenStudentIds = new Set();
  
  filteredStudents.forEach(st => {
    if (!seenStudentIds.has(st.student_id)) {
      seenStudentIds.add(st.student_id);
      uniqueStudents.push(st);
    }
  });

  if (uniqueStudents.length > 0) {
    uniqueStudents.forEach(st => {
      box.innerHTML += `
        <label>
          <input type="checkbox" name="student_ids[]" value="${st.student_id}">
          ${st.full_name} (${st.reg_no}) - ${st.semester_name}<br>
          <small style="color:#666;">${st.department_name} / ${st.program_name} / ${st.section_name}</small>
        </label>`;
    });
  } else {
    box.innerHTML = '<small style="color:#777;">No students found for this section.</small>';
  }
}

/* ---------------- TO FILTERS ---------------- */
function filterToFaculties(){
  const campusId = document.getElementById('to_campus').value;
  const f = document.getElementById('to_faculty');
  f.innerHTML = '<option value="">Select Faculty</option>';
  resetCascade('to', ['department','program','section','subject_list']);

  if (!campusId) return;

  faculties.forEach(x=>{
    if (String(x.campus_id) === String(campusId)) {
      f.innerHTML += `<option value="${x.faculty_id}">${x.faculty_name}</option>`;
    }
  });
}

function filterToDepartments(){
  const fId = document.getElementById('to_faculty').value;
  const campusId = document.getElementById('to_campus').value;
  const d = document.getElementById('to_department');
  d.innerHTML = '<option value="">Select Department</option>';
  resetCascade('to', ['program','section','subject_list']);

  if (!fId || !campusId) return;

  departments.forEach(x=>{
    if (String(x.faculty_id) === String(fId) && String(x.campus_id) === String(campusId)) {
      d.innerHTML += `<option value="${x.department_id}">${x.department_name}</option>`;
    }
  });
}

function filterToPrograms(){
  const dId = document.getElementById('to_department').value;
  const campusId = document.getElementById('to_campus').value;
  const p = document.getElementById('to_program');
  p.innerHTML = '<option value="">Select Program</option>';
  resetCascade('to', ['section','subject_list']);

  if (!dId || !campusId) return;

  programs.forEach(x=>{
    if (String(x.department_id) === String(dId) && String(x.campus_id) === String(campusId)) {
      p.innerHTML += `<option value="${x.program_id}">${x.program_name}</option>`;
    }
  });
}

function filterToSections(){
  const pId = document.getElementById('to_program').value;
  const campusId = document.getElementById('to_campus').value;
  const s = document.getElementById('to_section');
  s.innerHTML = '<option value="">Select Section</option>';
  resetCascade('to', ['subject_list']);

  if (!pId || !campusId) return;

  sections.forEach(x=>{
    if (String(x.program_id) === String(pId) && String(x.campus_id) === String(campusId)) {
      s.innerHTML += `<option value="${x.section_id}">${x.section_name}</option>`;
    }
  });
}

function filterToSubjects(){
  const semId = document.querySelector('select[name="new_semester_id"]').value;
  const campusId = document.getElementById('to_campus').value;
  const box = document.getElementById('to_subject_list');
  box.innerHTML = '';

  if (!semId || !campusId) {
    box.innerHTML = '<small style="color:#777;">Select semester to list subjects.</small>';
    return;
  }

  subjects.forEach(sb=>{
    if (String(sb.semester_id) === String(semId) && (!sb.campus_id || String(sb.campus_id) === String(campusId))) {
      box.innerHTML += `
        <label>
          <input type="checkbox" name="subject_ids[]" value="${sb.subject_id}">
          ${sb.subject_name}
        </label>`;
    }
  });

  if (!box.innerHTML)
    box.innerHTML = '<small style="color:#777;">No subjects found for this semester.</small>';
}

/* ---------------- HELPERS ---------------- */
function resetCascade(prefix, arr){
  arr.forEach(x=>{
    const el = document.getElementById(prefix+'_'+x);
    if (!el) return;
    if (x.includes('list')) {
      el.innerHTML = `<small style="color:#777;">Select ${x.replace('_',' ')}.</small>`;
    } else {
      el.innerHTML = `<option value="">Select ${x.charAt(0).toUpperCase()+x.slice(1)}</option>`;
    }
  });
}
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>