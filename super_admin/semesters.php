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

  // 🟢 ADD
  if ($_POST['action'] === 'add') {
    try {
      $name = trim($_POST['semester_name']);
      $desc = trim($_POST['description']);
      $status = $_POST['status'] ?? 'active';

      if ($name == "") throw new Exception("Semester name is required!");

      $stmt = $pdo->prepare("INSERT INTO semester (semester_name, description, status) VALUES (?, ?, ?)");
      $stmt->execute([$name, $desc, $status]);

      $message = "✅ Semester added successfully!";
      $type = "success";

    } catch (Exception $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }

  // 🟡 UPDATE
  if ($_POST['action'] === 'update') {
    try {
      $id = $_POST['semester_id'];
      $name = trim($_POST['semester_name']);
      $desc = trim($_POST['description']);
      $status = $_POST['status'];

      if ($name == "") throw new Exception("Semester name cannot be empty!");

      $stmt = $pdo->prepare("UPDATE semester SET semester_name=?, description=?, status=?, updated_at=NOW() WHERE semester_id=?");
      $stmt->execute([$name, $desc, $status, $id]);

      $message = "✅ Semester updated successfully!";
      $type = "success";

    } catch (Exception $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }

  // 🔴 DELETE
  if ($_POST['action'] === 'delete') {
    try {
      $id = $_POST['semester_id'];
      $pdo->prepare("DELETE FROM semester WHERE semester_id=?")->execute([$id]);
      $message = "✅ Semester deleted successfully!";
      $type = "success";
    } catch (Exception $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }
}

/* ===========================================
   FETCH DATA
=========================================== */
$semesters = $pdo->query("SELECT * FROM semester ORDER BY semester_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Semester | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--red:#C62828;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s ease;}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}
.add-btn{background:var(--green);color:#fff;padding:9px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer;}
.add-btn:hover{background:#00A651;}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.page-header h1{color:var(--blue);}

/* ✅ Scrollable Table */
.table-wrapper{
  background:#fff;
  border-radius:10px;
  box-shadow:0 4px 12px rgba(0,0,0,0.08);
  overflow-x:auto;
  overflow-y:auto;
  max-height:480px;
}
table{width:100%;border-collapse:collapse;}
thead th{background:var(--blue);color:#fff;position:sticky;top:0;z-index:2;}
th,td{padding:12px 14px;border-bottom:1px solid #eee;white-space:nowrap;text-align:left;}
tr:hover{background:#eef8f0;}
.action-buttons{display:flex;justify-content:center;gap:6px;}
.btn-edit{background:#0072CE;color:white;padding:7px 9px;border:none;border-radius:6px;}
.btn-delete{background:#C62828;color:white;padding:7px 9px;border:none;border-radius:6px;}
.btn-edit:hover{background:#2196f3;}
.btn-delete:hover{background:#e53935;}

/* ✅ Modal */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:3000;}
.modal.show{display:flex;}
.modal-content{background:#fff;border-radius:10px;width:90%;max-width:600px;padding:25px;position:relative;overflow-y:auto;max-height:90vh;}
.close-modal{position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;}
form{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
label{font-weight:600;color:#0072CE;}
input,select,textarea{width:100%;padding:10px;border:1.5px solid #ccc;border-radius:6px;}
textarea{resize:none;height:60px;}
.save-btn{grid-column:span 2;background:var(--green);color:#fff;border:none;padding:10px;border-radius:6px;font-weight:600;cursor:pointer;}
.save-btn:hover{background:#00A651;}

/* ✅ Alert Box */
.alert-popup{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px 25px;border-radius:12px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,0.2);z-index:5000;width:280px;}
.alert-popup.show{display:block;}
.alert-popup.success{border-top:5px solid var(--green);}
.alert-popup.error{border-top:5px solid var(--red);}
.alert-popup h3{font-size:14px;color:#333;margin-bottom:15px;}
.alert-btn{background:var(--blue);color:white;padding:6px 16px;border:none;border-radius:5px;cursor:pointer;font-weight:600;}
.alert-btn:hover{background:#005bb5;}

@media(max-width:600px){
  form{grid-template-columns:1fr;}
  .table-wrapper{max-height:400px;}
}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Semester</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Semester</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Semester Name</th><th>Description</th><th>Status</th><th>Created</th><th>Updated</th><th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($semesters): foreach($semesters as $i=>$s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($s['semester_name']) ?></td>
          <td><?= htmlspecialchars($s['description']) ?></td>
          <td style="color:<?= $s['status']=='active'?'green':'red' ?>;font-weight:600;"><?= ucfirst($s['status']) ?></td>
          <td><?= $s['created_at'] ?></td>
          <td><?= $s['updated_at'] ?></td>
          <td>
            <div class="action-buttons">
              <button class="btn-edit" onclick="openEditModal(<?= $s['semester_id'] ?>,'<?= htmlspecialchars($s['semester_name']) ?>','<?= htmlspecialchars($s['description']) ?>','<?= $s['status'] ?>')"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="btn-delete" onclick="openDeleteModal(<?= $s['semester_id'] ?>)"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="7" style="text-align:center;color:#777;">No semesters found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2>Add Semester</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div><label>Semester Name</label><input type="text" name="semester_name" required></div>
      <div><label>Status</label><select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <div><label>Description</label><textarea name="description" placeholder="Optional"></textarea></div>
      <button class="save-btn" type="submit">Save Semester</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2>Edit Semester</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="semester_id">
      <div><label>Semester Name</label><input type="text" id="edit_name" name="semester_name" required></div>
      <div><label>Status</label><select id="edit_status" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      <div><label>Description</label><textarea id="edit_desc" name="description"></textarea></div>
      <button class="save-btn" type="submit">Update Semester</button>
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
      <input type="hidden" id="delete_id" name="semester_id">
      <p>Are you sure you want to delete this semester?</p>
      <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ALERT -->
<div id="popup" class="alert-popup <?= $type ?>">
  <h3><?= $message ?></h3>
  <button class="alert-btn" onclick="closeAlert()">OK</button>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('show');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('show');document.body.style.overflow='auto';}
function openEditModal(id,name,desc,status){
  openModal('editModal');
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_name').value=name;
  document.getElementById('edit_desc').value=desc;
  document.getElementById('edit_status').value=status;
}
function openDeleteModal(id){openModal('deleteModal');document.getElementById('delete_id').value=id;}
function closeAlert(){document.getElementById('popup').classList.remove('show');}
if("<?= $message ?>"){document.getElementById('popup').classList.add('show');}
</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>
