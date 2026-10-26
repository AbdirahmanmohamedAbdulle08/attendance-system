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
    $action = $_POST['action'] ?? '';
    $year_name = trim($_POST['year_name'] ?? '');
    $start = $_POST['start_date'] ?? '';
    $end = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? '';

    // 🟡 Check date validity
    if ($end < $start) {
      throw new Exception("⚠️ End date cannot be earlier than start date!");
    }

    /* ================================
       ADD NEW ACADEMIC YEAR
    ================================ */
    if ($action === 'add') {

      // 🔍 Overlap check
      $check = $pdo->prepare("
        SELECT COUNT(*) FROM academic_year
        WHERE 
          (start_date <= ? AND end_date >= ?) 
          OR (start_date <= ? AND end_date >= ?)
          OR (? BETWEEN start_date AND end_date)
          OR (? BETWEEN start_date AND end_date)
      ");
      $check->execute([$start, $start, $end, $end, $start, $end]);

      if ($check->fetchColumn() > 0) {
        throw new Exception("⚠️ Another academic year overlaps with these dates!");
      }

      if ($status === 'active') {
        $pdo->exec("UPDATE academic_year SET status='inactive'");
      }

      $stmt = $pdo->prepare("INSERT INTO academic_year (year_name, start_date, end_date, status) VALUES (?,?,?,?)");
      $stmt->execute([$year_name, $start, $end, $status]);
      $message = "✅ Academic Year added successfully!";
      $type = "success";
    }

    /* ================================
       UPDATE EXISTING YEAR
    ================================ */
    if ($action === 'update') {
      $id = $_POST['academic_year_id'];

      // 🔍 Overlap check (excluding itself)
      $check = $pdo->prepare("
        SELECT COUNT(*) FROM academic_year
        WHERE academic_year_id <> ?
          AND (
            (start_date <= ? AND end_date >= ?)
            OR (start_date <= ? AND end_date >= ?)
            OR (? BETWEEN start_date AND end_date)
            OR (? BETWEEN start_date AND end_date)
          )
      ");
      $check->execute([$id, $start, $start, $end, $end, $start, $end]);

      if ($check->fetchColumn() > 0) {
        throw new Exception("⚠️ Another academic year overlaps with these dates!");
      }

      if ($status === 'active') {
        $pdo->exec("UPDATE academic_year SET status='inactive'");
      }

      $stmt = $pdo->prepare("UPDATE academic_year 
        SET year_name=?, start_date=?, end_date=?, status=?, updated_at=NOW()
        WHERE academic_year_id=?");
      $stmt->execute([$year_name, $start, $end, $status, $id]);
      $message = "✅ Academic Year updated successfully!";
      $type = "success";
    }

    /* ================================
       DELETE YEAR
    ================================ */
    if ($action === 'delete') {
      $stmt = $pdo->prepare("DELETE FROM academic_year WHERE academic_year_id=?");
      $stmt->execute([$_POST['academic_year_id']]);
      $message = "✅ Academic Year deleted successfully!";
      $type = "success";
    }

  } catch (Exception $e) {
    $message = $e->getMessage();
    $type = "error";
  } catch (PDOException $e) {
    $message = "❌ Database Error: " . $e->getMessage();
    $type = "error";
  }
}


/* ===========================================
   FETCH DATA
=========================================== */
$years = $pdo->query("SELECT * FROM academic_year ORDER BY academic_year_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Academic Years | Hormuud University</title>
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
  transition: all .3s;
}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

.page-header {
  display: flex;
  margin-left: -20px;

  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.page-header h1 {
  color: var(--blue);
  font-size: 24px;
  margin-left: -50px;
  margin: 0;
  font-weight: 700;
}
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

/* ✅ Table Scrollable */
.table-wrapper {
  overflow-x: auto;
  overflow-y: auto;
  max-height: 500px;
  margin-left: -20px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border-radius: 10px;
}
thead th {
  position: sticky;
  top: 0;
  background: var(--blue);
  color: #fff;
  z-index: 2;
}
th, td {
  padding: 12px 14px;
  border-bottom: 1px solid #eee;
  text-align: left;
  white-space: nowrap;
}
tr:hover { background: #eef8f0; }

.action-buttons {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
}
.btn-edit, .btn-delete {
  border: none;
  border-radius: 6px;
  padding: 8px 12px;
  color: #fff;
  cursor: pointer;
  transition: .3s;
}
.btn-edit { background: var(--blue); }
.btn-edit:hover { background: #2196f3; }
.btn-delete { background: var(--red); }
.btn-delete:hover { background: #e53935; }

/* ✅ Modal Base */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  justify-content: center;
  align-items: center;
  z-index: 3000;
  padding: 10px;
}
.modal.show { display: flex; }

.modal-content {
  background: #fff;
  border-radius: 10px;
  width: 95%;
  max-width: 640px;
  padding: 25px;
  position: relative;
  box-shadow: 0 8px 25px rgba(0,0,0,0.25);
  border-top: 5px solid var(--blue);
  animation: popUp .3s ease;
}
@keyframes popUp {
  from {opacity:0; transform:scale(.9) translateY(-10px);}
  to {opacity:1; transform:scale(1) translateY(0);}
}

.close-modal {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 22px;
  cursor: pointer;
  color: var(--red);
  transition: .2s;
}
.close-modal:hover { transform: scale(1.2); }

/* ✅ 2x2 FORM GRID — always 2 columns */
form {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px 18px;
  align-items: start;
}
label {
  font-weight: 600;
  color: var(--blue);
  font-size: 13px;
  margin-bottom: 3px;
  display: block;
}
input, select {
  width: 100%;
  padding: 9px 10px;
  border: 1.5px solid #ccc;
  border-radius: 6px;
  background: #f9f9f9;
  transition: 0.2s;
  font-size: 13px;
}
input:focus, select:focus {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(0,114,206,0.12);
  outline: none;
}
.save-btn {
  grid-column: span 2;
  background: var(--green);
  color: #fff;
  border: none;
  padding: 10px;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: .3s;
  font-size: 14px;
}
.save-btn:hover { background: var(--light-green); }

/* ✅ Alert Popup */
.alert-popup {
  display: none;
  position: fixed;
  top: 20px;
  right: 20px;
  background: #fff;
  padding: 16px 24px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 4000;
}
.alert-popup.show { display: block; animation: slideIn .5s ease; }
.alert-popup.success { border-left: 5px solid var(--green); }
.alert-popup.error { border-left: 5px solid var(--red); }
@keyframes slideIn {
  from {opacity:0; transform:translateY(-15px);}
  to {opacity:1; transform:translateY(0);}
}

/* ✅ Responsive — keep 2 columns even on mobile */
@media (max-width:480px){
  .modal-content{width:96%;padding:20px;}
  form{grid-template-columns:repeat(2,1fr);gap:10px;}
  label{font-size:12.5px;}
  input,select{font-size:12.5px;padding:8px;}
  .save-btn{font-size:13px;padding:9px;}
}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Academic Years</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Year</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Year Name</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Status</th>
          <th>Created At</th>
          <th>Updated At</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($years): foreach ($years as $i => $y): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($y['year_name']) ?></td>
          <td><?= htmlspecialchars($y['start_date']) ?></td>
          <td><?= htmlspecialchars($y['end_date']) ?></td>
          <td style="color:<?= $y['status']=='active'?'#00843D':'#C62828' ?>;font-weight:600;"><?= ucfirst($y['status']) ?></td>
          <td><?= htmlspecialchars($y['created_at']) ?></td>
          <td><?= htmlspecialchars($y['updated_at']) ?></td>
          <td>
            <div class="action-buttons">
              <button class="btn-edit" onclick="openEditModal(<?= $y['academic_year_id'] ?>,'<?= $y['year_name'] ?>','<?= $y['start_date'] ?>','<?= $y['end_date'] ?>','<?= $y['status'] ?>')"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="btn-delete" onclick="openDeleteModal(<?= $y['academic_year_id'] ?>)"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" style="text-align:center;color:#777;">No academic years found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 🔹 Add Modal -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue);margin-bottom:10px;">Add Academic Year</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div>
        <label>Year Name</label>
        <input type="text" name="year_name" required>
      </div>
      <div>
        <label>Status</label>
        <select name="status" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div>
        <label>Start Date</label>
        <input type="date" name="start_date" required>
      </div>
      <div>
        <label>End Date</label>
        <input type="date" name="end_date" required>
      </div>
      <button class="save-btn" type="submit">Save Year</button>
    </form>
  </div>
</div>

<!-- 🔹 Edit Modal -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue);margin-bottom:10px;">Edit Academic Year</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="academic_year_id">
      <div>
        <label>Year Name</label>
        <input type="text" id="edit_name" name="year_name" required>
      </div>
      <div>
        <label>Status</label>
        <select id="edit_status" name="status" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div>
        <label>Start Date</label>
        <input type="date" id="edit_start" name="start_date" required>
      </div>
      <div>
        <label>End Date</label>
        <input type="date" id="edit_end" name="end_date" required>
      </div>
      <button class="save-btn" type="submit">Update Year</button>
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
      <input type="hidden" id="delete_id" name="academic_year_id">
      <p style="text-align:center;">Are you sure you want to delete this academic year?</p>
      <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ✅ Alert Popup -->
<div id="popup" class="alert-popup <?= $type ?>"><strong><?= $message ?></strong></div>

<script>
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}

function openEditModal(id,name,start,end,status){
  openModal('editModal');
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_name').value=name;
  document.getElementById('edit_start').value=start;
  document.getElementById('edit_end').value=end;
  document.getElementById('edit_status').value=status;
}

function openDeleteModal(id){
  openModal('deleteModal');
  document.getElementById('delete_id').value=id;
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
