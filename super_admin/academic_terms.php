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
    // 🟢 ADD TERM
    if ($_POST['action'] === 'add') {
      $academic_year_id = $_POST['academic_year_id'];
      $term_name = $_POST['term_name'];
      $start_date = $_POST['start_date'];
      $end_date = $_POST['end_date'];
      $status = $_POST['status'];

      // ✅ Hubi in academic_year laftiisa la heli karo oo xad leh
      $yearStmt = $pdo->prepare("SELECT start_date, end_date FROM academic_year WHERE academic_year_id=?");
      $yearStmt->execute([$academic_year_id]);
      $year_dates = $yearStmt->fetch(PDO::FETCH_ASSOC);

      if (!$year_dates) {
        throw new Exception("❌ Invalid academic year selected!");
      }

      // ✅ Term-ka ha ka bixin xadka academic_year
      if ($start_date < $year_dates['start_date'] || $end_date > $year_dates['end_date']) {
        throw new Exception(
          "❌ Term dates must be within the academic year period (" .
          $year_dates['start_date'] . " → " . $year_dates['end_date'] . ")."
        );
      }

      // ✅ Prevent overlapping terms (within the same academic year)
      $check = $pdo->prepare("
        SELECT * FROM academic_term 
        WHERE academic_year_id = ? 
          AND (
            (start_date <= ? AND end_date >= ?) OR
            (start_date <= ? AND end_date >= ?) OR
            (? <= start_date AND ? >= end_date)
          )
      ");
      $check->execute([
        $academic_year_id,
        $start_date, $start_date,
        $end_date, $end_date,
        $start_date, $end_date
      ]);
      if ($check->rowCount() > 0) {
        throw new Exception("❌ The selected term dates overlap with an existing term in this academic year!");
      }

      // ✅ Haddii la doorto 'active' laakiin term-ku mustaqbalka bilaabanayo → ha noqdo INACTIVE
      $today = date('Y-m-d');
      if ($status === 'active') {
        if ($start_date > $today) {
          // mustaqbal → ha noqon active hadda
          $status = 'inactive';
        } else {
          // Term-kan ayaa noqda active, kuwa kale ee isla academic_year ah ha noqdaan inactive
          $deactivate = $pdo->prepare("UPDATE academic_term SET status='inactive' WHERE academic_year_id=?");
          $deactivate->execute([$academic_year_id]);
        }
      }

      $stmt = $pdo->prepare("INSERT INTO academic_term (academic_year_id, term_name, start_date, end_date, status)
                             VALUES (?, ?, ?, ?, ?)");
      $stmt->execute([$academic_year_id, $term_name, $start_date, $end_date, $status]);
      $message = "✅ Academic Term added successfully!";
      $type = "success";
    }

    // 🟡 UPDATE TERM
    if ($_POST['action'] === 'update') {
      $academic_term_id = $_POST['academic_term_id'];
      $academic_year_id = $_POST['academic_year_id'];
      $term_name = $_POST['term_name'];
      $start_date = $_POST['start_date'];
      $end_date = $_POST['end_date'];
      $status = $_POST['status'];

      // ✅ Hubi in academic_year sax yahay oo xad leh
      $yearStmt = $pdo->prepare("SELECT start_date, end_date FROM academic_year WHERE academic_year_id=?");
      $yearStmt->execute([$academic_year_id]);
      $year_dates = $yearStmt->fetch(PDO::FETCH_ASSOC);

      if (!$year_dates) {
        throw new Exception("❌ Invalid academic year selected!");
      }

      // ✅ Term-ka ha ka bixin xadka academic_year
      if ($start_date < $year_dates['start_date'] || $end_date > $year_dates['end_date']) {
        throw new Exception(
          "❌ Term dates must be within the academic year period (" .
          $year_dates['start_date'] . " → " . $year_dates['end_date'] . ")."
        );
      }

      // ✅ Prevent overlapping with OTHER terms in same academic year
      $check = $pdo->prepare("
        SELECT * FROM academic_term 
        WHERE academic_year_id = ? 
          AND academic_term_id != ?
          AND (
            (start_date <= ? AND end_date >= ?) OR
            (start_date <= ? AND end_date >= ?) OR
            (? <= start_date AND ? >= end_date)
          )
      ");
      $check->execute([
        $academic_year_id,
        $academic_term_id,
        $start_date, $start_date,
        $end_date, $end_date,
        $start_date, $end_date
      ]);
      if ($check->rowCount() > 0) {
        throw new Exception("❌ The selected term dates overlap with another term in this academic year!");
      }

      // ✅ Haddii la doorto 'active' laakiin term-ku mustaqbalka bilaabanayo → ha noqdo INACTIVE
      $today = date('Y-m-d');
      if ($status === 'active') {
        if ($start_date > $today) {
          $status = 'inactive';
        } else {
          // Term-kan oo keliya ha noqdo active, kuwa kale ee isla sanadka ah ha noqdaan inactive
          $deactivate = $pdo->prepare("UPDATE academic_term SET status='inactive' WHERE academic_year_id=? AND academic_term_id != ?");
          $deactivate->execute([$academic_year_id, $academic_term_id]);
        }
      }

      $stmt = $pdo->prepare("UPDATE academic_term 
        SET academic_year_id=?, term_name=?, start_date=?, end_date=?, status=?, updated_at=NOW()
        WHERE academic_term_id=?");
      $stmt->execute([$academic_year_id, $term_name, $start_date, $end_date, $status, $academic_term_id]);
      $message = "✅ Academic Term updated successfully!";
      $type = "success";
    }

    // 🔴 DELETE TERM
    if ($_POST['action'] === 'delete') {
      $stmt = $pdo->prepare("DELETE FROM academic_term WHERE academic_term_id=?");
      $stmt->execute([$_POST['academic_term_id']]);
      $message = "✅ Academic Term deleted successfully!";
      $type = "success";
    }
  } catch (Exception $e) {
    $message = $e->getMessage();
    $type = "error";
  }
}

/* ===========================================
   FETCH DATA
=========================================== */
$terms = $pdo->query("SELECT t.*, y.year_name 
                      FROM academic_term t
                      JOIN academic_year y ON y.academic_year_id = t.academic_year_id
                      ORDER BY t.academic_term_id DESC")->fetchAll(PDO::FETCH_ASSOC);
$years = $pdo->query("SELECT academic_year_id, year_name FROM academic_year ORDER BY year_name DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Academic Terms | Hormuud University</title>
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
.sidebar.collapsed ~ .main-content {
  margin-left:70px;
}

/* Page Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}
.page-header h1 {
  color: var(--blue);
  font-size: 24px;
  font-weight: 700;
  margin: 0;
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
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
table {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
}
thead th {
  position: sticky;
  top: 0;
  background: var(--blue);
  color: #fff;
}
th, td {
  padding: 12px 14px;
  border-bottom: 1px solid #eee;
  white-space: nowrap;
}
tr:hover { background: #eef8f0; }

/* Buttons */
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

/* ✅ Modal */
.modal {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  justify-content: center;
  align-items: center;
  z-index: 3000;
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

/* ✅ 2x2 GRID FORM */
form {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 15px 20px;
}
form div {
  display: flex;
  flex-direction: column;
}
label {
  font-weight: 600;
  color: var(--blue);
  margin-bottom: 3px;
}
input, select {
  width: 100%;
  padding: 9px 10px;
  border: 1.5px solid #ccc;
  border-radius: 6px;
  background: #f9f9f9;
  transition: .2s;
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
}
.save-btn:hover { background: var(--light-green); }

/* Alert */
.alert-popup {
  display: none;
  position: fixed;
  top: 20px;
  right: 20px;
  background: #fff;
  padding: 16px 24px;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  z-index: 4000;
}
.alert-popup.show { display: block; animation: slideIn .5s ease; }
.alert-popup.success { border-left: 5px solid var(--green); }
.alert-popup.error { border-left: 5px solid var(--red); }
@keyframes slideIn {
  from {opacity:0;transform:translateY(-15px);}
  to {opacity:1;transform:translateY(0);}
}

/* Responsive */
@media (max-width:480px){
  .modal-content{width:96%;padding:20px;}
  form{grid-template-columns:repeat(2,1fr);gap:10px;}
}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Academic Terms</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Term</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Academic Year</th>
          <th>Term</th>
          <th>Start</th>
          <th>End</th>
          <th>Status</th>
          <th>Created</th>
          <th style="text-align:center;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($terms): foreach ($terms as $i => $t): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($t['year_name']) ?></td>
          <td><?= htmlspecialchars($t['term_name']) ?></td>
          <td><?= htmlspecialchars($t['start_date']) ?></td>
          <td><?= htmlspecialchars($t['end_date']) ?></td>
          <td style="color:<?= $t['status']=='active'?'#00843D':'#C62828' ?>;font-weight:600;"><?= ucfirst($t['status']) ?></td>
          <td><?= htmlspecialchars($t['created_at']) ?></td>
          <td>
            <div class="action-buttons">
              <button class="btn-edit" onclick="openEditModal(<?= $t['academic_term_id'] ?>,<?= $t['academic_year_id'] ?>,'<?= $t['term_name'] ?>','<?= $t['start_date'] ?>','<?= $t['end_date'] ?>','<?= $t['status'] ?>')"><i class="fa-solid fa-pen-to-square"></i></button>
              <button class="btn-delete" onclick="openDeleteModal(<?= $t['academic_term_id'] ?>)"><i class="fa-solid fa-trash"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="8" style="text-align:center;color:#777;">No academic terms found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue);margin-bottom:10px;">Add Academic Term</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div><label>Academic Year</label>
        <select name="academic_year_id" required>
          <option value="">Select Year</option>
          <?php foreach($years as $y): ?>
          <option value="<?= $y['academic_year_id'] ?>"><?= $y['year_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Term Name</label>
        <select name="term_name" required>
          <option value="A">Term A</option>
          <option value="B">Term B</option>
        </select>
      </div>
      <div><label>Start Date</label><input type="date" name="start_date" required></div>
      <div><label>End Date</label><input type="date" name="end_date" required></div>
      <div><label>Status</label>
        <select name="status" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <button class="save-btn" type="submit">Save Term</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2 style="text-align:center;color:var(--blue);margin-bottom:10px;">Edit Academic Term</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="academic_term_id">
      <div><label>Academic Year</label>
        <select id="edit_year" name="academic_year_id" required>
          <?php foreach($years as $y): ?>
          <option value="<?= $y['academic_year_id'] ?>"><?= $y['year_name'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Term Name</label>
        <select id="edit_term" name="term_name" required>
          <option value="A">Term A</option>
          <option value="B">Term B</option>
        </select>
      </div>
      <div><label>Start Date</label><input type="date" id="edit_start" name="start_date" required></div>
      <div><label>End Date</label><input type="date" id="edit_end" name="end_date" required></div>
      <div><label>Status</label>
        <select id="edit_status" name="status" required>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <button class="save-btn" type="submit">Update Term</button>
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
      <input type="hidden" id="delete_id" name="academic_term_id">
      <p style="text-align:center;">Are you sure you want to delete this academic term?</p>
      <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ALERT -->
<div id="popup" class="alert-popup <?= $type ?>"><strong><?= $message ?></strong></div>

<script>
function openModal(id){document.getElementById(id).classList.add('show');}
function closeModal(id){document.getElementById(id).classList.remove('show');}
function openEditModal(id,year,term,start,end,status){
  openModal('editModal');
  document.getElementById('edit_id').value=id;
  document.getElementById('edit_year').value=year;
  document.getElementById('edit_term').value=term;
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

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>
