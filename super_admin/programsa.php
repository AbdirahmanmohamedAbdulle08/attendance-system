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
      // Check duplicate program name in same faculty
      $check = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE program_name = ? AND faculty_id = ?");
      $check->execute([$_POST['program_name'], $_POST['faculty_id']]);
      if ($check->fetchColumn() > 0) {
        $message = "⚠️ Program already exists under this faculty!";
        $type = "error";
      } else {
        $stmt = $pdo->prepare("INSERT INTO programs 
          (campus_id, faculty_id, department_id, program_name, program_code, duration_years, description, status)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
          $_POST['campus_id'],
          $_POST['faculty_id'] ?: null,
          $_POST['department_id'] ?: null,
          $_POST['program_name'],
          $_POST['program_code'],
          $_POST['duration_years'],
          $_POST['description'],
          $_POST['status']
        ]);
        $message = "✅ Program added successfully!";
        $type = "success";
      }
    }
    

    if ($_POST['action'] === 'update') {
      $stmt = $pdo->prepare("UPDATE programs 
      SET campus_id=?, faculty_id=?, department_id=?, program_name=?, program_code=?, duration_years=?, description=?, status=?, updated_at=NOW()
      WHERE program_id=?");
    $stmt->execute([
      $_POST['campus_id'],
      $_POST['faculty_id'] ?: null,
      $_POST['department_id'] ?: null,
      $_POST['program_name'],
      $_POST['program_code'],
      $_POST['duration_years'],
      $_POST['description'],
      $_POST['status'],
      $_POST['program_id']
    ]);
    
      $message = "✅ Program updated successfully!";
      $type = "success";
    }

    if ($_POST['action'] === 'delete') {
      $stmt = $pdo->prepare("DELETE FROM programs WHERE program_id=?");
      $stmt->execute([$_POST['program_id']]);
      $message = "✅ Program deleted successfully!";
      $type = "success";
    }

  } catch (PDOException $e) {
    $message = "❌ " . $e->getMessage();
    $type = "error";
  }
}

/* ===========================================
   FETCH DATA
=========================================== */
$programs = $pdo->query("SELECT p.*, f.faculty_name, d.department_name 
                         FROM programs p
                         LEFT JOIN faculties f ON p.faculty_id = f.faculty_id
                         LEFT JOIN departments d ON p.department_id = d.department_id
                         ORDER BY p.program_id DESC")->fetchAll(PDO::FETCH_ASSOC);

$campuses = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Programs | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
  --green:#00843D;
  --light-green:#00A651;
  --blue:#0072CE;
  --red:#C62828;
  --bg:#F5F9F7;
}
body {
  font-family:'Poppins',sans-serif;
  background:var(--bg);
  margin:0;
  color:#333;
}
.main-content {
  padding:20px;
  margin-top:90px;
  margin-left:250px;
  transition:all .3s;
}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

.page-header h1 {
  color:var(--blue);
  font-size:24px;
  font-weight:700;
}
.add-btn {
  background:var(--green);
  color:#fff;
  border:none;
  padding:10px 18px;
  border-radius:6px;
  font-weight:600;
  cursor:pointer;
}
.add-btn:hover { background:var(--light-green); }

.table-wrapper {
  overflow-x:auto;
  background:#fff;
  border-radius:10px;
  box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
table { width:100%; border-collapse:collapse; }
thead th {
  position:sticky; top:0;
  background:var(--blue); color:#fff;
}
th,td { padding:12px 14px; border-bottom:1px solid #eee; text-align:left; }
tr:hover { background:#eef8f0; }

.action-buttons { display:flex; gap:8px; justify-content:center; }
.btn-edit, .btn-delete {
  border:none; border-radius:6px; padding:8px 12px; color:#fff; cursor:pointer;
}
.btn-edit { background:var(--blue); }
.btn-delete { background:var(--red); }
.btn-edit:hover { background:#2196f3; }
.btn-delete:hover { background:#e53935; }

.modal {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
  justify-content:center; align-items:center; z-index:3000; padding:10px;
}
.modal.show { display:flex; }
.modal-content {
  background:#fff; border-radius:10px; width:95%; max-width:650px;
  padding:25px; position:relative; border-top:5px solid var(--blue);
}
.close-modal {
  position:absolute; top:10px; right:15px; font-size:22px;
  color:var(--red); cursor:pointer;
}
form { display:grid; grid-template-columns:repeat(2,1fr); gap:14px 18px; }
label { font-weight:600; color:var(--blue); font-size:13px; margin-bottom:3px; display:block; }
input, select, textarea {
  width:100%; padding:9px 10px; border:1.5px solid #ccc; border-radius:6px;
  background:#f9f9f9; transition:.2s; font-size:13px;
}
textarea { resize:none; height:60px; }
.save-btn {
  grid-column:span 2; background:var(--green); color:#fff; border:none;
  padding:10px; border-radius:6px; font-weight:600; cursor:pointer;
}
.save-btn:hover { background:var(--light-green); }

.alert-popup {
  display:none; position:fixed; top:20px; right:20px; background:#fff;
  padding:16px 24px; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.1);
  z-index:4000;
}
.alert-popup.show { display:block; animation:slideIn .5s ease; }
.alert-popup.success { border-left:5px solid var(--green); }
.alert-popup.error { border-left:5px solid var(--red); }
@keyframes slideIn { from{opacity:0;transform:translateY(-15px);} to{opacity:1;transform:translateY(0);} }
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Programs</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Program</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Program Name</th>
          <th>Code</th>
          <th>Faculty</th>
          <th>Department</th>
          <th>Years</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($programs): foreach ($programs as $i=>$p): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($p['program_name']) ?></td>
          <td><?= htmlspecialchars($p['program_code']) ?></td>
          <td><?= htmlspecialchars($p['faculty_name']) ?></td>
          <td><?= htmlspecialchars($p['department_name']) ?></td>
          <td><?= htmlspecialchars($p['duration_years']) ?></td>
          <td style="color:<?= $p['status']=='active'?'#00843D':'#C62828' ?>;font-weight:600;">
            <?= ucfirst($p['status']) ?>
          </td>
          <td>
            <div class="action-buttons">
              <button class="btn-edit" onclick="openEditModal(
                <?= $p['program_id'] ?>,
                '<?= htmlspecialchars($p['program_name'],ENT_QUOTES) ?>',
                '<?= htmlspecialchars($p['program_code'],ENT_QUOTES) ?>',
                <?= (int)$p['duration_years'] ?>,
                '<?= htmlspecialchars($p['description'],ENT_QUOTES) ?>',
                '<?= $p['status'] ?>',
                '<?= $p['faculty_id'] ?>',
                '<?= $p['department_id'] ?>'
              )"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="btn-delete" onclick="openDeleteModal(<?= $p['program_id'] ?>)">
                <i class="fa-solid fa-trash"></i>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" style="text-align:center;color:#777;">No programs found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 🔹 Add Modal -->
 
<!-- 🔹 Add Modal -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue);margin-bottom:10px;">Add Program</h2>

    <form method="POST">
      <input type="hidden" name="action" value="add">

      <div>
        <label>Program Name</label>
        <input type="text" name="program_name" required>
      </div>

      <div>
        <label>Program Code</label>
        <input type="text" name="program_code" required>
      </div>

      <!-- ✅ Campus Dropdown -->
      <div>
        <label>Campus</label>
        <select id="add_campus" name="campus_id" required onchange="filterFaculties(this.value, 'add')">
          <option value="">Select Campus</option>
          <?php foreach($campuses as $c): ?>
            <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ✅ Faculty Dropdown -->
      <div>
        <label>Faculty</label>
        <select id="add_faculty" name="faculty_id" required onchange="filterDepartments(this.value, 'add')">
          <option value="">Select Faculty</option>
        </select>
      </div>

      <!-- ✅ Department Dropdown -->
      <div>
        <label>Department</label>
        <select id="add_department" name="department_id">
          <option value="">Select Department</option>
        </select>
      </div>

      <div>
        <label>Duration (Years)</label>
        <input type="number" name="duration_years" value="4" min="1" required>
      </div>

      <div>
        <label>Status</label>
        <select name="status" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div style="grid-column:span 2;">
        <label>Description</label>
        <textarea name="description"></textarea>
      </div>

      <button class="save-btn" type="submit">Save Program</button>
    </form>
  </div>
</div>



<!-- 🔹 Edit Modal -->

<!-- 🔹 Edit Modal -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue);margin-bottom:10px;">Edit Program</h2>

    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="program_id">

      <div>
        <label>Program Name</label>
        <input type="text" id="edit_name" name="program_name" required>
      </div>

      <div>
        <label>Program Code</label>
        <input type="text" id="edit_code" name="program_code" required>
      </div>

      <!-- ✅ Campus Dropdown -->
      <div>
        <label>Campus</label>
        <select id="edit_campus" name="campus_id" required onchange="filterFaculties(this.value, 'edit')">
          <option value="">Select Campus</option>
          <?php foreach($campuses as $c): ?>
            <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ✅ Faculty Dropdown -->
      <div>
        <label>Faculty</label>
        <select id="edit_faculty" name="faculty_id" required onchange="filterDepartments(this.value, 'edit')">
          <option value="">Select Faculty</option>
        </select>
      </div>

      <!-- ✅ Department Dropdown -->
      <div>
        <label>Department</label>
        <select id="edit_department" name="department_id">
          <option value="">Select Department</option>
        </select>
      </div>

      <div>
        <label>Duration (Years)</label>
        <input type="number" id="edit_duration" name="duration_years" min="1" required>
      </div>

      <div>
        <label>Status</label>
        <select id="edit_status" name="status" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <div style="grid-column:span 2;">
        <label>Description</label>
        <textarea id="edit_desc" name="description"></textarea>
      </div>

      <button class="save-btn" type="submit">Update Program</button>
    </form>
  </div>
</div>



<!-- 🔹 Delete Modal -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
    <h2 style="color:#C62828;text-align:center;">Confirm Delete</h2>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" id="delete_id" name="program_id">
      <p style="text-align:center;">Are you sure you want to delete this program?</p>
      <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ✅ Alert Popup -->
<div id="popup" class="alert-popup <?= $type ?>"><strong><?= $message ?></strong></div>

<script>
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}

function openEditModal(id,name,code,duration,desc,status,faculty,department){
  openModal('editModal');
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_name').value=name;
  document.getElementById('edit_code').value=code;
  document.getElementById('edit_duration').value=duration;
  document.getElementById('edit_desc').value=desc;
  document.getElementById('edit_status').value=status;
  document.getElementById('edit_faculty').value=faculty;
  document.getElementById('edit_department').value=department;
}

function openDeleteModal(id){
  openModal('deleteModal');
  document.getElementById('delete_id').value=id;
}
const faculties = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;

function filterFaculties(campusId, prefix='add') {
  const facultySelect = document.getElementById(prefix + '_faculty');
  const deptSelect = document.getElementById(prefix + '_department');
  facultySelect.innerHTML = '<option value="">Select Faculty</option>';
  deptSelect.innerHTML = '<option value="">Select Department</option>';

  faculties
    .filter(f => f.campus_id == campusId)
    .forEach(f => {
      const opt = document.createElement('option');
      opt.value = f.faculty_id;
      opt.textContent = f.faculty_name;
      facultySelect.appendChild(opt);
    });
}

function filterDepartments(facultyId, prefix='add') {
  const deptSelect = document.getElementById(prefix + '_department');
  deptSelect.innerHTML = '<option value="">Select Department</option>';

  departments
    .filter(d => d.faculty_id == facultyId)
    .forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.department_id;
      opt.textContent = d.department_name;
      deptSelect.appendChild(opt);
    });
}


if("<?= $message ?>"){
  const popup=document.getElementById('popup');
  popup.classList.add('show');
  setTimeout(()=>popup.classList.remove('show'),3500);
}
</script>

<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>
