<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

$role = strtolower($_SESSION['user']['role'] ?? '');
if (!in_array($role, ['campus_admin'])) {
  header("Location: ../login.php");
  exit;
}


$message = "";
$type = "";

/* ===========================================
   CRUD OPERATIONS
=========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // 🟢 ADD SUBJECT
  if ($_POST['action'] === 'add') {
    try {
      $campus = $_POST['campus_id'] ?? null;
      $faculty = $_POST['faculty_id'] ?? null;
      $department = $_POST['department_id'] ?? null;
      $program = $_POST['program_id'] ?? null;
      $section = $_POST['section_id'] ?? null;
      $semester = $_POST['semester_id'] ?? null;
      $name = trim($_POST['subject_name'] ?? '');
      $code = trim($_POST['subject_code'] ?? '');
      $credit = $_POST['credit_hours'] ?? null;
      $desc = trim($_POST['description'] ?? '');
      $status = $_POST['status'] ?? 'active';

      if (!$campus || !$faculty || !$department || !$program || !$section || !$semester || !$name || !$code || !$credit)
        throw new Exception("❌ All fields are required!");

      $stmt = $pdo->prepare("INSERT INTO subject
        (campus_id, faculty_id, department_id, program_id, section_id, semester_id, subject_name, subject_code, credit_hours, description, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->execute([$campus, $faculty, $department, $program, $section, $semester, $name, $code, $credit, $desc, $status]);

      $message = "✅ Subject added successfully!";
      $type = "success";
    } catch (Exception $e) {
      $message = $e->getMessage();
      $type = "error";
    }
  }

  // 🟡 UPDATE SUBJECT
  if ($_POST['action'] === 'update') {
    try {
      $id = $_POST['subject_id'];
      $campus = $_POST['campus_id'];
      $faculty = $_POST['faculty_id'];
      $department = $_POST['department_id'];
      $program = $_POST['program_id'];
      $section = $_POST['section_id'];
      $semester = $_POST['semester_id'];
      $name = trim($_POST['subject_name']);
      $code = trim($_POST['subject_code']);
      $credit = $_POST['credit_hours'];
      $desc = trim($_POST['description']);
      $status = $_POST['status'];

      if (!$campus || !$faculty || !$department || !$program || !$section || !$semester || !$name || !$code || !$credit)
        throw new Exception("❌ All fields are required!");

      $stmt = $pdo->prepare("UPDATE subject SET campus_id=?, faculty_id=?, department_id=?, program_id=?, section_id=?, semester_id=?, subject_name=?, subject_code=?, credit_hours=?, description=?, status=?, updated_at=NOW() WHERE subject_id=?");
      $stmt->execute([$campus, $faculty, $department, $program, $section, $semester, $name, $code, $credit, $desc, $status, $id]);

      $message = "✅ Subject updated successfully!";
      $type = "success";
    } catch (Exception $e) {
      $message = $e->getMessage();
      $type = "error";
    }
  }


}

/* ===========================================
   FETCH DATA
=========================================== */
$user = $_SESSION['user'] ?? null;
$role = strtolower($user['role'] ?? '');
$campus_id = $user['linked_id'] ?? null;

if ($role === 'super_admin') {
  $campuses = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
  $faculties = $pdo->query("SELECT * FROM faculties")->fetchAll(PDO::FETCH_ASSOC);
  $departments = $pdo->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);
  $programs = $pdo->query("SELECT * FROM programs")->fetchAll(PDO::FETCH_ASSOC);
  $sections = $pdo->query("SELECT * FROM class_section")->fetchAll(PDO::FETCH_ASSOC);
  $semesters = $pdo->query("SELECT * FROM semester ORDER BY semester_id DESC")->fetchAll(PDO::FETCH_ASSOC);

  $subjects = $pdo->query("
    SELECT s.*, c.campus_name, f.faculty_name, d.department_name, p.program_name, sec.section_name, se.semester_name
    FROM subject s
    JOIN campus c ON s.campus_id=c.campus_id
    JOIN faculties f ON s.faculty_id=f.faculty_id
    JOIN departments d ON s.department_id=d.department_id
    JOIN programs p ON s.program_id=p.program_id
    JOIN class_section sec ON s.section_id=sec.section_id
    JOIN semester se ON s.semester_id=se.semester_id
    ORDER BY s.subject_id DESC
  ")->fetchAll(PDO::FETCH_ASSOC);
}

elseif ($role === 'campus_admin' && $campus_id) {
  // ❗ Campus-admin kaliya campus-kiisa
  $campuses = $pdo->prepare("SELECT * FROM campus WHERE campus_id=? ORDER BY campus_name ASC");
  $campuses->execute([$campus_id]);
  $campuses = $campuses->fetchAll(PDO::FETCH_ASSOC);

  $faculties = $pdo->prepare("SELECT * FROM faculties WHERE campus_id=?");
  $faculties->execute([$campus_id]);
  $faculties = $faculties->fetchAll(PDO::FETCH_ASSOC);

  $departments = $pdo->prepare("
    SELECT d.* FROM departments d
    JOIN faculties f ON d.faculty_id=f.faculty_id
    WHERE f.campus_id=?");
  $departments->execute([$campus_id]);
  $departments = $departments->fetchAll(PDO::FETCH_ASSOC);

  $programs = $pdo->prepare("
    SELECT p.* FROM programs p
    JOIN departments d ON p.department_id=d.department_id
    JOIN faculties f ON d.faculty_id=f.faculty_id
    WHERE f.campus_id=?");
  $programs->execute([$campus_id]);
  $programs = $programs->fetchAll(PDO::FETCH_ASSOC);

  $sections = $pdo->prepare("
    SELECT s.* FROM class_section s
    JOIN programs p ON s.program_id=p.program_id
    JOIN departments d ON p.department_id=d.department_id
    JOIN faculties f ON d.faculty_id=f.faculty_id
    WHERE f.campus_id=?");
  $sections->execute([$campus_id]);
  $sections = $sections->fetchAll(PDO::FETCH_ASSOC);

  $semesters = $pdo->query("SELECT * FROM semester ORDER BY semester_id DESC")->fetchAll(PDO::FETCH_ASSOC);

  $subjects = $pdo->prepare("
    SELECT s.*, c.campus_name, f.faculty_name, d.department_name, p.program_name, sec.section_name, se.semester_name
    FROM subject s
    JOIN campus c ON s.campus_id=c.campus_id
    JOIN faculties f ON s.faculty_id=f.faculty_id
    JOIN departments d ON s.department_id=d.department_id
    JOIN programs p ON s.program_id=p.program_id
    JOIN class_section sec ON s.section_id=sec.section_id
    JOIN semester se ON s.semester_id=se.semester_id
    WHERE c.campus_id=?
    ORDER BY s.subject_id DESC
  ");
  $subjects->execute([$campus_id]);
  $subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Subjects | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--red:#C62828;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s;}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}
.add-btn{background:var(--green);color:#fff;padding:9px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer;}
.add-btn:hover{background:#00A651;}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.page-header h1{color:var(--blue);}
.table-wrapper{background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.08);overflow:auto;max-height:500px;}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--blue);color:#fff;position:sticky;top:0;}
th,td{padding:12px 14px;border-bottom:1px solid #eee;white-space:nowrap;text-align:left;}
tr:hover{background:#eef8f0;}
.action-buttons{display:flex;justify-content:center;gap:6px;}
.btn-edit{background:#0072CE;color:white;padding:7px 9px;border:none;border-radius:6px;}
.btn-delete{background:#C62828;color:white;padding:7px 9px;border:none;border-radius:6px;}
.btn-edit:hover{background:#2196f3;}
.btn-delete:hover{background:#e53935;}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:3000;}
.modal.show{display:flex;}
.modal-content{background:#fff;border-radius:10px;width:90%;max-width:750px;padding:25px;position:relative;overflow-y:auto;max-height:90vh;}
.close-modal{position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;}
form{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
label{font-weight:600;color:#0072CE;}
input,select,textarea{width:100%;padding:10px;border:1.5px solid #ccc;border-radius:6px;}
textarea{resize:none;height:60px;}
.save-btn{grid-column:span 2;background:var(--green);color:#fff;border:none;padding:10px;border-radius:6px;font-weight:600;cursor:pointer;}
.save-btn:hover{background:#00A651;}
.alert-popup{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px 25px;border-radius:12px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,0.2);z-index:5000;width:280px;}
.alert-popup.show{display:block;}
.alert-popup.success{border-top:5px solid var(--green);}
.alert-popup.error{border-top:5px solid var(--red);}
.alert-popup h3{font-size:14px;color:#333;margin-bottom:15px;}
.alert-btn{background:var(--blue);color:white;padding:6px 16px;border:none;border-radius:5px;cursor:pointer;font-weight:600;}
.alert-btn:hover{background:#005bb5;}
@media(max-width:600px){form{grid-template-columns:1fr;}.table-wrapper{max-height:400px;}}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Subjects</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Subject</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Campus</th><th>Faculty</th><th>Department</th><th>Program</th><th>Section</th><th>Semester</th><th>Subject</th><th>Code</th><th>Credit</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($subjects): foreach($subjects as $i=>$s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= $s['campus_name'] ?></td>
          <td><?= $s['faculty_name'] ?></td>
          <td><?= $s['department_name'] ?></td>
          <td><?= $s['program_name'] ?></td>
          <td><?= $s['section_name'] ?></td>
          <td><?= $s['semester_name'] ?></td>
          <td><?= $s['subject_name'] ?></td>
          <td><?= $s['subject_code'] ?></td>
          <td><?= $s['credit_hours'] ?></td>
          <td style="color:<?= $s['status']=='active'?'green':'red' ?>;font-weight:600;"><?= ucfirst($s['status']) ?></td>
          <td>
            <div class="action-buttons">
              <button class="btn-edit" onclick="openEditModal(<?= $s['subject_id'] ?>,'<?= $s['campus_id'] ?>','<?= $s['faculty_id'] ?>','<?= $s['department_id'] ?>','<?= $s['program_id'] ?>','<?= $s['section_id'] ?>','<?= $s['semester_id'] ?>','<?= htmlspecialchars($s['subject_name']) ?>','<?= htmlspecialchars($s['subject_code']) ?>','<?= $s['credit_hours'] ?>','<?= htmlspecialchars($s['description']) ?>','<?= $s['status'] ?>')"><i class='fa-solid fa-pen-to-square'></i></button>
              <button class="btn-delete" onclick="openDeleteModal(<?= $s['subject_id'] ?>)"><i class='fa-solid fa-trash'></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="12" style="text-align:center;color:#777;">No subjects found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2>Add Subject</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div><label>Campus</label>
        <select id="add_campus" name="campus_id" onchange="filterFaculties('add')" required>
          <option value="">Select Campus</option>
          <?php foreach($campuses as $c): ?><option value="<?= $c['campus_id'] ?>"><?= $c['campus_name'] ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Faculty</label>
        <select id="add_faculty" name="faculty_id" onchange="filterDepartments('add')" required><option value="">Select Faculty</option></select></div>
      <div><label>Department</label>
        <select id="add_department" name="department_id" onchange="filterPrograms('add')" required><option value="">Select Department</option></select></div>
      <div><label>Program</label>
        <select id="add_program" name="program_id" onchange="filterSections('add')" required><option value="">Select Program</option></select></div>
      <div><label>Section</label>
        <select id="add_section" name="section_id" required><option value="">Select Section</option></select></div>
      <div><label>Semester</label>
        <select name="semester_id" required>
          <option value="">Select Semester</option>
          <?php foreach($semesters as $s): ?><option value="<?= $s['semester_id'] ?>"><?= $s['semester_name'] ?></option><?php endforeach; ?>
        </select></div>
      <div><label>Subject Name</label><input type="text" name="subject_name" required></div>
      <div><label>Subject Code</label><input type="text" name="subject_code" required></div>
      <div><label>Credit Hours</label><input type="number" name="credit_hours" required min="1" max="10"></div>
      <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <div><label>Description</label><textarea name="description"></textarea></div>
      <button class="save-btn" type="submit">Save Subject</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2>Edit Subject</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="subject_id">
      <div><label>Campus</label><select id="edit_campus" name="campus_id"><?php foreach($campuses as $c): ?><option value="<?= $c['campus_id'] ?>"><?= $c['campus_name'] ?></option><?php endforeach; ?></select></div>
      <div><label>Faculty</label><select id="edit_faculty" name="faculty_id"><?php foreach($faculties as $f): ?><option value="<?= $f['faculty_id'] ?>"><?= $f['faculty_name'] ?></option><?php endforeach; ?></select></div>
      <div><label>Department</label><select id="edit_dept" name="department_id"><?php foreach($departments as $d): ?><option value="<?= $d['department_id'] ?>"><?= $d['department_name'] ?></option><?php endforeach; ?></select></div>
      <div><label>Program</label><select id="edit_program" name="program_id"><?php foreach($programs as $p): ?><option value="<?= $p['program_id'] ?>"><?= $p['program_name'] ?></option><?php endforeach; ?></select></div>
      <div><label>Section</label><select id="edit_section" name="section_id"><?php foreach($sections as $sec): ?><option value="<?= $sec['section_id'] ?>"><?= $sec['section_name'] ?></option><?php endforeach; ?></select></div>
      <div><label>Semester</label><select id="edit_sem" name="semester_id"><?php foreach($semesters as $s): ?><option value="<?= $s['semester_id'] ?>"><?= $s['semester_name'] ?></option><?php endforeach; ?></select></div>
      <div><label>Subject Name</label><input type="text" id="edit_name" name="subject_name" required></div>
      <div><label>Subject Code</label><input type="text" id="edit_code" name="subject_code" required></div>
      <div><label>Credit Hours</label><input type="number" id="edit_credit" name="credit_hours" required></div>
      <div><label>Status</label><select id="edit_status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <div><label>Description</label><textarea id="edit_desc" name="description"></textarea></div>
      <button class="save-btn" type="submit">Update Subject</button>
    </form>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
    <h2 style="color:#C62828">Confirm Delete</h2>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" id="delete_id" name="subject_id">
      <p>Are you sure you want to delete this subject?</p>
      <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ALERT -->
<div id="popup" class="alert-popup <?= $type ?>"><h3><?= $message ?></h3><button class="alert-btn" onclick="closeAlert()">OK</button></div>

<script>
const faculties = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;
const programs = <?= json_encode($programs) ?>;
const sections = <?= json_encode($sections) ?>;

function filterFaculties(prefix){
  const campusId=document.getElementById(prefix+'_campus').value;
  const fSelect=document.getElementById(prefix+'_faculty');
  fSelect.innerHTML='<option value="">Select Faculty</option>';
  faculties.filter(f=>f.campus_id==campusId).forEach(f=>fSelect.innerHTML+=`<option value="${f.faculty_id}">${f.faculty_name}</option>`);
  filterDepartments(prefix);
}
function filterDepartments(prefix){
  const facultyId=document.getElementById(prefix+'_faculty').value;
  const dSelect=document.getElementById(prefix+'_department');
  dSelect.innerHTML='<option value="">Select Department</option>';
  departments.filter(d=>d.faculty_id==facultyId).forEach(d=>dSelect.innerHTML+=`<option value="${d.department_id}">${d.department_name}</option>`);
  filterPrograms(prefix);
}
function filterPrograms(prefix){
  const deptId=document.getElementById(prefix+'_department').value;
  const pSelect=document.getElementById(prefix+'_program');
  pSelect.innerHTML='<option value="">Select Program</option>';
  programs.filter(p=>p.department_id==deptId).forEach(p=>pSelect.innerHTML+=`<option value="${p.program_id}">${p.program_name}</option>`);
  filterSections(prefix);
}
function filterSections(prefix){
  const progId=document.getElementById(prefix+'_program').value;
  const sSelect=document.getElementById(prefix+'_section');
  sSelect.innerHTML='<option value="">Select Section</option>';
  sections.filter(s=>s.program_id==progId).forEach(s=>sSelect.innerHTML+=`<option value="${s.section_id}">${s.section_name}</option>`);
}

function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
function openEditModal(id,campus,faculty,dept,prog,sec,sem,name,code,credit,desc,status){
  openModal('editModal');
  edit_id.value=id;edit_campus.value=campus;edit_faculty.value=faculty;edit_dept.value=dept;edit_program.value=prog;edit_section.value=sec;
  edit_sem.value=sem;edit_name.value=name;edit_code.value=code;edit_credit.value=credit;edit_desc.value=desc;edit_status.value=status;
}
function openDeleteModal(id){openModal('deleteModal');delete_id.value=id;}
function closeAlert(){document.getElementById('popup').classList.remove('show');}
if("<?= $message ?>"){document.getElementById('popup').classList.add('show');}
</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>
