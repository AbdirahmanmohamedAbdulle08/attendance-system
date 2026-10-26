<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control
if (strtolower($_SESSION['user']['role'] ?? '') !== 'super_admin') {
  header("Location: ../login.php");
  exit;
}

$message = "";
$type = "";

/* ===========================================
   CRUD OPERATIONS
=========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if ($_POST['action'] === 'add') {
      $campus = $_POST['campus_id'] ?? null;
      $faculty = $_POST['faculty_id'] ?? null;
      $dept = $_POST['department_id'] ?? null;
      $program = $_POST['program_id'] ?? null;
      $name = trim($_POST['section_name'] ?? '');
      $capacity = $_POST['capacity'] ?? null;
      $status = $_POST['status'] ?? 'active';
      if (!$campus || !$faculty || !$dept || !$program || !$name || !$capacity)
      throw new Exception("All fields are required!");
    if (!is_numeric($capacity) || $capacity <= 0)
      throw new Exception("Capacity must be a positive number!");
    if (!in_array($status, ['active','inactive']))
      throw new Exception("Invalid status value!");
          $stmt = $pdo->prepare("INSERT INTO class_section (campus_id, faculty_id, department_id, program_id, section_name, capacity, status)
      VALUES (?,?,?,?,?,?,?)");
      $stmt->execute([$_POST['campus_id'], $faculty, $dept, $program, $name, $capacity, $status]);
      
      $message = "✅ Section added successfully!";
      $type = "success";
    }

    if ($_POST['action'] === 'update') {
      $id = $_POST['section_id'] ?? null;
      $faculty = $_POST['faculty_id'] ?? null;
      $dept = $_POST['department_id'] ?? null;
      $program = $_POST['program_id'] ?? null;
      $name = trim($_POST['section_name'] ?? '');
      $capacity = $_POST['capacity'] ?? null;
      $status = $_POST['status'] ?? 'active';
      if (!$id || !$faculty || !$dept || !$program || !$name || !$capacity) throw new Exception("All fields are required!");
      $stmt = $pdo->prepare("UPDATE class_section 
      SET campus_id=?, faculty_id=?, department_id=?, program_id=?, section_name=?, capacity=?, status=?, updated_at=NOW() 
      WHERE section_id=?");
      $stmt->execute([$_POST['campus_id'], $faculty, $dept, $program, $name, $capacity, $status, $id]);
      
      $message = "✅ Section updated successfully!";
      $type = "success";
    }

    if ($_POST['action'] === 'delete') {
      $id = $_POST['section_id'];
      $pdo->prepare("DELETE FROM class_section WHERE section_id=?")->execute([$id]);
      $message = "✅ Section deleted successfully!";
      $type = "success";
    }
  } catch (Exception $e) {
    $message = "❌ " . $e->getMessage();
    $type = "error";
  }
}

/* ===========================================
   FETCH DATA
=========================================== */
$campuses = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$programs = $pdo->query("SELECT * FROM programs ORDER BY program_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sections = $pdo->query("
  SELECT 
    s.section_id, s.section_name, s.capacity, s.status, s.campus_id,
    f.faculty_name, f.faculty_id,
    d.department_name, d.department_id,
    p.program_name, p.program_id
  FROM class_section s
  JOIN faculties f ON s.faculty_id=f.faculty_id
  JOIN departments d ON s.department_id=d.department_id
  JOIN programs p ON s.program_id=p.program_id
  ORDER BY s.section_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Class Sections | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --green: #00843D;
  --light-green: #00A651;
  --blue: #0072CE;
  --red: #C62828;
  --bg: #F5F9F7;
}
body {
  font-family: 'Poppins', sans-serif;
  background: var(--bg);
  margin: 0;
  color: #333;
}
.main-content {
  padding: 20px;
  margin-top: 90px;
  margin-left: 250px;
  transition: all .3s ease;
}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.page-header h1 { color: var(--blue); font-size: 22px; font-weight: 700; }
.add-btn {
  background: var(--green);
  color: #fff;
  border: none;
  padding: 10px 18px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: .3s;
}
.add-btn:hover { background: var(--light-green); }

/* Table wrapper */
.table-wrapper {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 500px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
table { width: 100%; border-collapse: collapse; }
thead th {
  background: var(--blue);
  color: #fff;
  position: sticky; top: 0; z-index: 2;
}
th, td { padding: 12px 14px; border-bottom: 1px solid #eee; white-space: nowrap; text-align: left; }
tr:hover { background: #eef8f0; }
.action-buttons { display: flex; justify-content: center; gap: 6px; }
.btn-edit, .btn-delete {
  border: none; color: #fff; border-radius: 6px; padding: 8px 10px; cursor: pointer; transition: .2s;
}
.btn-edit { background: var(--blue); }
.btn-edit:hover { background: #2196f3; }
.btn-delete { background: var(--red); }
.btn-delete:hover { background: #e53935; }

/* Modal */
.modal {
  display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45);
  justify-content: center; align-items: center; z-index: 3000;
}
.modal.show { display: flex; }
.modal-content {
  background: #fff; border-radius: 10px; width: 95%; max-width: 720px;
  padding: 25px 30px; box-shadow: 0 8px 25px rgba(0,0,0,0.25);
  border-top: 5px solid var(--blue); position: relative; overflow-y: auto; max-height: 90vh;
}
.close-modal {
  position: absolute; top: 10px; right: 15px; font-size: 22px; cursor: pointer;
  color: var(--red); transition: .2s;
}
.close-modal:hover { transform: scale(1.2); }

/* Form */
form { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px 20px; }
form div { display: flex; flex-direction: column; }
label { font-weight: 600; color: var(--blue); font-size: 13px; margin-bottom: 3px; }
input, select {
  padding: 10px; border: 1.5px solid #ccc; border-radius: 6px;
  background: #f9f9f9; transition: .2s; font-size: 13px;
}
input:focus, select:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(0,114,206,0.12); outline: none; }
.save-btn {
  grid-column: span 2; background: var(--green); color: #fff; border: none;
  padding: 11px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: .3s;
}
.save-btn:hover { background: var(--light-green); }

/* Responsive */
@media(max-width:600px){ form{grid-template-columns:1fr;} .table-wrapper{max-height:400px;} }
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Sections</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Section</button>
  </div>
  <?php if($message): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      showMessageModal("<?= addslashes($message) ?>", "<?= $type ?>");
    });
  </script>
<?php endif; ?>


<div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Campus</th>
        <th>Faculty</th>
        <th>Department</th>
        <th>Program</th>
        <th>Section</th>
        <th>Capacity</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if($sections): foreach($sections as $i=>$s): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td>
          <?php 
            // ✅ Hel magaca campus-ka iyadoo la adeegsanayo campus_id
            $campusName = '';
            foreach($campuses as $c) {
              if($c['campus_id'] == $s['campus_id']) {
                $campusName = htmlspecialchars($c['campus_name']);
                break;
              }
            }
            echo $campusName ?: '-';
          ?>
        </td>
        <td><?= htmlspecialchars($s['faculty_name']) ?></td>
        <td><?= htmlspecialchars($s['department_name']) ?></td>
        <td><?= htmlspecialchars($s['program_name']) ?></td>
        <td><?= htmlspecialchars($s['section_name']) ?></td>
        <td><?= htmlspecialchars($s['capacity']) ?></td>
        <td style="color:<?= $s['status']=='active'?'#00843D':'#C62828' ?>;font-weight:600;">
          <?= ucfirst($s['status']) ?>
        </td>
        <td>
          <div class="action-buttons">
            <button class="btn-edit" 
              onclick="openEditModal(
                '<?= $s['section_id'] ?>',
                '<?= htmlspecialchars($s['faculty_id'], ENT_QUOTES) ?>',
                '<?= htmlspecialchars($s['department_id'], ENT_QUOTES) ?>',
                '<?= htmlspecialchars($s['program_id'], ENT_QUOTES) ?>',
                '<?= htmlspecialchars($s['section_name'], ENT_QUOTES) ?>',
                '<?= htmlspecialchars($s['capacity'], ENT_QUOTES) ?>',
                '<?= htmlspecialchars($s['status'], ENT_QUOTES) ?>'
              )">
              <i class="fa-solid fa-pen-to-square"></i>
            </button>

            <button class="btn-delete" onclick="openDeleteModal('<?= $s['section_id'] ?>')">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr><td colspan="9" style="text-align:center;color:#777;">No sections found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>


<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue)">Add Section</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div><label>Campus</label>
  <select id="add_campus" name="campus_id" onchange="filterFaculties('add')" required>
    <option value="">Select Campus</option>
    <?php
$campuses = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach($campuses as $c): ?>
      <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>

      <div><label>Faculty</label>
        <select id="add_faculty" name="faculty_id" onchange="filterDepartments('add')" required>
          <option value="">Select Faculty</option>
          <?php foreach($faculties as $f): ?><option value="<?= $f['faculty_id'] ?>"><?= $f['faculty_name'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Department</label>
        <select id="add_department" name="department_id" onchange="filterPrograms('add')" required>
          <option value="">Select Department</option>
        </select>
      </div>
      <div><label>Program</label>
        <select id="add_program" name="program_id" required>
          <option value="">Select Program</option>
        </select>
      </div>
      <div><label>Section Name</label><input type="text" name="section_name" required></div>
      <div><label>Capacity</label><input type="number" name="capacity" required></div>
      <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <button class="save-btn" type="submit">Save Section</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue)">Edit Section</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="section_id">
      <div><label>Campus</label>
  <select id="edit_campus" name="campus_id" onchange="filterFaculties('edit')" required>
    <?php foreach($campuses as $c): ?>
      <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
    <?php endforeach; ?>
  </select>
</div>
      <div><label>Faculty</label>
        <select id="edit_faculty" name="faculty_id" onchange="filterDepartments('edit')" required>
          <?php foreach($faculties as $f): ?><option value="<?= $f['faculty_id'] ?>"><?= $f['faculty_name'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Department</label>
        <select id="edit_department" name="department_id" onchange="filterPrograms('edit')" required>
          <?php foreach($departments as $d): ?><option value="<?= $d['department_id'] ?>"><?= $d['department_name'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Program</label>
        <select id="edit_program" name="program_id" required>
          <?php foreach($programs as $p): ?><option value="<?= $p['program_id'] ?>"><?= $p['program_name'] ?></option><?php endforeach; ?>
        </select>
      </div>
      <div><label>Section Name</label><input type="text" id="edit_name" name="section_name" required></div>
      <div><label>Capacity</label><input type="number" id="edit_capacity" name="capacity" required></div>
      <div><label>Status</label><select id="edit_status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <button class="save-btn" type="submit">Update Section</button>
    </form>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
    <h2 style="color:#C62828;text-align:center;">Confirm Delete</h2>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" id="delete_id" name="section_id">
      <p style="text-align:center;">Are you sure you want to delete this section?</p>
      <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>
<script>
/* ===========================================================
   XOGTA PHP → JS
=========================================================== */
const faculties = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;
const programs = <?= json_encode($programs) ?>;

/* ===========================================================
   CAMPUS → FACULTY
=========================================================== */
function filterFaculties(prefix) {
  const campusId = document.getElementById(prefix + '_campus').value;
  const fSelect = document.getElementById(prefix + '_faculty');
  fSelect.innerHTML = '<option value="">Select Faculty</option>';

  faculties
    .filter(f => f.campus_id == campusId)
    .forEach(f => {
      fSelect.innerHTML += `<option value="${f.faculty_id}">${f.faculty_name}</option>`;
    });

  // marka campus la beddelo, nadiifi department & program
  document.getElementById(prefix + '_department').innerHTML = '<option value="">Select Department</option>';
  document.getElementById(prefix + '_program').innerHTML = '<option value="">Select Program</option>';
}

/* ===========================================================
   FACULTY → DEPARTMENT
=========================================================== */
function filterDepartments(prefix) {
  const facultyId = document.getElementById(prefix + '_faculty').value;
  const dSelect = document.getElementById(prefix + '_department');
  dSelect.innerHTML = '<option value="">Select Department</option>';

  departments
    .filter(d => d.faculty_id == facultyId)
    .forEach(d => {
      dSelect.innerHTML += `<option value="${d.department_id}">${d.department_name}</option>`;
    });

  // update program list
  filterPrograms(prefix);
}

/* ===========================================================
   DEPARTMENT → PROGRAM
=========================================================== */
function filterPrograms(prefix) {
  const deptId = document.getElementById(prefix + '_department').value;
  const pSelect = document.getElementById(prefix + '_program');
  pSelect.innerHTML = '<option value="">Select Program</option>';

  programs
    .filter(p => p.department_id == deptId)
    .forEach(p => {
      pSelect.innerHTML += `<option value="${p.program_id}">${p.program_name}</option>`;
    });
}

/* ===========================================================
   MODAL CONTROL FUNCTIONS
=========================================================== */
function openModal(id) {
  document.getElementById(id).classList.add('show');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('show');
}

/* ===========================================================
   EDIT MODAL → AUTO FILL DATA
=========================================================== */
function openEditModal(id, faculty, dept, program, name, capacity, status) {
  openModal('editModal');
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_capacity').value = capacity;
  document.getElementById('edit_status').value = status;

  const facultyObj = faculties.find(f => f.faculty_id == faculty);
  const campusId = facultyObj ? facultyObj.campus_id : '';
  document.getElementById('edit_campus').value = campusId;

  // Refresh dependent dropdowns
  filterFaculties('edit');
  setTimeout(() => {
    document.getElementById('edit_faculty').value = faculty;
    filterDepartments('edit');
    document.getElementById('edit_department').value = dept;
    filterPrograms('edit');
    document.getElementById('edit_program').value = program;
  }, 200);
}


/* ===========================================================
   DELETE MODAL
=========================================================== */
function openDeleteModal(id) {
  openModal('deleteModal');
  document.getElementById('delete_id').value = id;
}
/* ===========================================================
   MESSAGE ALERT (CENTER POPUP)
=========================================================== */
function showMessageModal(message, type) {
  const modal = document.getElementById('messageModal');
  const title = document.getElementById('msgTitle');
  const text = document.getElementById('msgText');
  
  title.textContent = type === 'success' ? '✅ Success' : '❌ Error';
  text.textContent = message;

  // Set color styles dynamically
  modal.querySelector('.modal-content').style.borderTop = 
    type === 'success' ? '5px solid #00843D' : '5px solid #C62828';
  
  openModal('messageModal');
}

</script>

<?php include('../includes/footer.php'); ?>
<!-- MESSAGE MODAL -->
<div class="modal" id="messageModal">
  <div class="modal-content" style="max-width:400px;text-align:center;">
    <h3 id="msgTitle" style="margin-bottom:10px;"></h3>
    <p id="msgText"></p>
    <button onclick="closeModal('messageModal')" 
            style="margin-top:15px;background:#0072CE;color:#fff;
                   border:none;padding:8px 16px;border-radius:5px;
                   cursor:pointer;font-weight:600;">
      OK
    </button>
  </div>
</div>

</body>
</html>
<?php ob_end_flush(); ?>
