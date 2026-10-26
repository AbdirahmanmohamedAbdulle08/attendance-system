<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');
$campus_id = $_SESSION['user']['linked_id']; // campus admin's campus id
// ✅ Access Control
if (strtolower($_SESSION['user']['role'] ?? '') !== 'campus_admin') {
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
      $term_id = $_POST['academic_term_id'];
      $start_date = $_POST['start_date'];
      $end_date = $_POST['end_date'];

      $term = $pdo->prepare("SELECT start_date, end_date FROM academic_term WHERE academic_term_id=?");
      $term->execute([$term_id]);
      $term_dates = $term->fetch(PDO::FETCH_ASSOC);
      if (!$term_dates) throw new Exception("Invalid academic term selected!");

      if ($start_date < $term_dates['start_date'] || $end_date > $term_dates['end_date'])
        throw new Exception("Allocation dates must be within the term period (" . $term_dates['start_date'] . " → " . $term_dates['end_date'] . ").");

        $stmt = $pdo->prepare("INSERT INTO room_allocation 
        (campus_id, room_id, academic_term_id, department_id, allocated_to, start_date, end_date, remarks)
        VALUES (?,?,?,?,?,?,?,?)");
      $stmt->execute([
        $campus_id, $_POST['room_id'], $term_id, $_POST['department_id'],
        $_POST['allocated_to'], $start_date, $end_date, $_POST['remarks']
      ]);
      

      if ($_POST['allocated_to'] === 'Maintenance')
        $pdo->prepare("UPDATE rooms SET status='maintenance' WHERE room_id=?")->execute([$_POST['room_id']]);
      else
        $pdo->prepare("UPDATE rooms SET status='occupied' WHERE room_id=?")->execute([$_POST['room_id']]);

      $message = "✅ Room allocated successfully!";
      $type = "success";
    } catch (Exception $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }

  // 🟡 UPDATE
  if ($_POST['action'] === 'update') {
    try {
      $term_id = $_POST['academic_term_id'];
      $start_date = $_POST['start_date'];
      $end_date = $_POST['end_date'];

      $term = $pdo->prepare("SELECT start_date, end_date FROM academic_term WHERE academic_term_id=?");
      $term->execute([$term_id]);
      $term_dates = $term->fetch(PDO::FETCH_ASSOC);

      if (!$term_dates) throw new Exception("Invalid academic term selected!");
      if ($start_date < $term_dates['start_date'] || $end_date > $term_dates['end_date'])
        throw new Exception("Allocation dates must be within (" . $term_dates['start_date'] . " → " . $term_dates['end_date'] . ").");

        $stmt = $pdo->prepare("UPDATE room_allocation 
        SET room_id=?, academic_term_id=?, department_id=?, allocated_to=?, start_date=?, end_date=?, remarks=? 
        WHERE allocation_id=? AND campus_id=?");
      $stmt->execute([
        $_POST['room_id'], $term_id, $_POST['department_id'],
        $_POST['allocated_to'], $start_date, $end_date, $_POST['remarks'],
        $_POST['allocation_id'], $campus_id
      ]);
      

      $message = "✅ Allocation updated successfully!";
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
$campuses = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties = $pdo->query("SELECT * FROM faculties")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments")->fetchAll(PDO::FETCH_ASSOC);
$rooms = $pdo->query("SELECT * FROM rooms WHERE status!='inactive'")->fetchAll(PDO::FETCH_ASSOC);
$terms = $pdo->query("SELECT * FROM academic_term ORDER BY academic_term_id DESC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT 
    a.*, 
    r.room_name, r.room_code, r.department_id,
    d.department_name, d.faculty_id,  /* ✅ ADD faculty_id here */
    f.faculty_name, c.campus_name, 
    t.term_name
  FROM room_allocation a
  JOIN rooms r ON a.room_id = r.room_id
  JOIN departments d ON a.department_id = d.department_id
  JOIN faculties f ON d.faculty_id = f.faculty_id
  JOIN campus c ON f.campus_id = c.campus_id
  JOIN academic_term t ON a.academic_term_id = t.academic_term_id
  WHERE a.campus_id = ?
  ORDER BY a.allocation_id DESC");

$stmt->execute([$campus_id]);
$allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Room Allocations | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
  --green:#00843D; --blue:#0072CE; --red:#C62828;
  --light-green:#00A651; --bg:#F5F9F7;
}
body {
  font-family:'Poppins',sans-serif; background:var(--bg);
  margin:0; color:#333;
}
.main-content {
  padding:20px; margin-top:90px; margin-left:250px; transition:all .3s ease;
}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

.page-header {
  display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;
}
.page-header h1 {
  color:var(--blue); font-size:24px; margin:0; font-weight:700;
}
.add-btn {
  background:var(--green); color:#fff; border:none;
  padding:10px 18px; border-radius:6px; font-weight:600; cursor:pointer; transition:.3s;
}
.add-btn:hover { background:var(--light-green); }

/* Table */
.table-wrapper { overflow:auto; max-height:500px; border-radius:10px; }
table {
  width:100%; border-collapse:collapse; background:#fff;
  border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
thead th {
  position:sticky; top:0; background:var(--blue); color:#fff; z-index:2;
}
th, td {
  padding:12px 14px; border-bottom:1px solid #eee;
  text-align:left; white-space:nowrap;
}
tr:hover { background:#eef8f0; }

.action-btns { display:flex; justify-content:center; gap:8px; }
.edit-btn, .del-btn {
  border:none; border-radius:6px; padding:8px 10px; color:#fff;
  cursor:pointer; transition:.3s;
}
.edit-btn { background:var(--blue); } .edit-btn:hover { background:#2196f3; }
.del-btn { background:var(--red); } .del-btn:hover { background:#e53935; }

/* Modal */
.modal {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,0.5); justify-content:center; align-items:center;
  z-index:3000;
}
.modal.show { display:flex; }

.modal-content {
  background:#fff; border-radius:10px; width:90%; max-width:700px;
  padding:25px 35px; position:relative;
  box-shadow:0 8px 25px rgba(0,0,0,0.25);
  border-top:5px solid var(--blue);
  animation:fadeIn .3s ease;
}
@keyframes fadeIn {
  from { opacity:0; transform:scale(0.9) translateY(-20px); }
  to { opacity:1; transform:scale(1) translateY(0); }
}
.close-modal {
  position:absolute; top:10px; right:15px; font-size:24px;
  cursor:pointer; color:var(--red); transition:.2s;
}
.close-modal:hover { transform:scale(1.2); }

/* Form (2x2 Grid) */
#allocationForm {
  display:grid; grid-template-columns:1fr 1fr;
  gap:16px 24px; align-items:start;
}
#allocationForm label {
  font-weight:600; color:var(--blue); font-size:13.5px; margin-bottom:4px; display:block;
}
#allocationForm input, #allocationForm select, #allocationForm textarea {
  width:100%; padding:8px 10px; border:1.5px solid #ccc; border-radius:6px;
  font-size:13.5px; background:#f9f9f9; transition:0.2s;
}
#allocationForm input:focus, #allocationForm select:focus, #allocationForm textarea:focus {
  border-color:var(--blue);
  box-shadow:0 0 0 3px rgba(0,114,206,0.12);
  outline:none;
}
#allocationForm textarea {
  resize:none; min-height:60px; font-family:inherit;
}
#allocationForm textarea[name="remarks"], .save-btn { grid-column:span 2; }

/* Button */
.save-btn {
  background:var(--green); color:#fff; border:none; padding:10px;
  border-radius:6px; font-weight:600; cursor:pointer; transition:.3s; font-size:14px;
}
.save-btn:hover { background:var(--light-green); }

/* Divider between cols */
#allocationForm > *:nth-child(odd) { padding-right:10px; }
#allocationForm > *:nth-child(even) {
  padding-left:10px; border-left:1px solid #e3e3e3;
}

/* Responsive */
@media (max-width:600px){
  .modal-content { width:96%; padding:20px; }
  #allocationForm { grid-template-columns:repeat(2,1fr); gap:12px 14px; }
  #allocationForm label { font-size:12.5px; }
  #allocationForm input, #allocationForm select, #allocationForm textarea {
    font-size:12.5px; padding:7px;
  }
  .save-btn { font-size:13px; padding:9px; }
}

/* ===============================
   CENTER ALERT OVERLAY
   =============================== */
.alert-overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.45);
  display:flex;
  justify-content:center;
  align-items:center;
  z-index:4000;
}
.alert-box{
  min-width:260px;
  max-width:420px;
  background:#ffffff;
  border-radius:10px;
  padding:18px 20px 16px;
  box-shadow:0 10px 30px rgba(0,0,0,0.25);
  border-left:5px solid #cccccc;
  text-align:center;
  position:relative;
  animation:fadeIn .2s ease-out;
}
.alert-box p{
  margin:0;
  font-size:14px;
  color:#333;
}
.alert-box.success{
  border-left-color:var(--green);
}
.alert-box.error{
  border-left-color:var(--red);
}
.alert-close{
  position:absolute;
  top:6px;
  right:10px;
  font-size:18px;
  cursor:pointer;
  color:#777;
  transition:0.2s;
}
.alert-close:hover{
  color:#000;
  transform:scale(1.1);
}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<?php if (!empty($message)): ?>
  <!-- ✅ Center Alert Overlay -->
  <div class="alert-overlay" id="alertOverlay">
    <div class="alert-box <?= htmlspecialchars($type) ?>">
      <span class="alert-close" onclick="document.getElementById('alertOverlay').style.display='none'">&times;</span>
      <p><?= htmlspecialchars($message) ?></p>
    </div>
  </div>
<?php endif; ?>

<div class="main-content">
  <div class="page-header">
    <h1>Room Allocations</h1>
    <button class="add-btn" onclick="openModal()">+ Add Allocation</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Campus</th><th>Faculty</th><th>Department</th>
          <th>Room</th><th>Term</th><th>Allocated To</th>
          <th>Start</th><th>End</th><th>Remarks</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($allocations): foreach($allocations as $i=>$a): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($a['campus_name']) ?></td>
          <td><?= htmlspecialchars($a['faculty_name']) ?></td>
          <td><?= htmlspecialchars($a['department_name']) ?></td>
          <td><?= htmlspecialchars($a['room_name'].' ('.$a['room_code'].')') ?></td>
          <td><?= htmlspecialchars($a['term_name']) ?></td>
          <td><?= htmlspecialchars($a['allocated_to']) ?></td>
          <td><?= htmlspecialchars($a['start_date']) ?></td>
          <td><?= htmlspecialchars($a['end_date']) ?></td>
          <td><?= htmlspecialchars($a['remarks']) ?></td>
          <td class="action-btns">
            <button class="edit-btn" onclick='editAllocation(<?= json_encode($a) ?>)'><i class="fa-solid fa-pen-to-square"></i></button>
              <input type="hidden" name="allocation_id" value="<?= $a['allocation_id'] ?>">
            </form>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11" style="text-align:center;color:#777;">No allocations found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 🔹 Add/Edit Modal -->
<div class="modal" id="allocationModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal()">&times;</span>
    <h2 id="modalTitle">Add Allocation</h2>

    <form method="POST" id="allocationForm">
  <input type="hidden" name="action" id="formAction" value="add">
  <input type="hidden" name="allocation_id" id="allocation_id">
  <input type="hidden" name="campus_id" value="<?= $_SESSION['user']['linked_id'] ?>">

  <!-- Faculty -->
  <div>
    <label>Faculty</label>
    <select name="faculty_id" id="faculty_id" required onchange="filterDepartments()">
      <option value="">Select Faculty</option>
      <?php foreach($faculties as $f): ?>
        <?php if($f['campus_id'] == $campus_id): // ✅ filter by this campus ?>
          <option value="<?= $f['faculty_id'] ?>">
            <?= htmlspecialchars($f['faculty_name']) ?>
          </option>
        <?php endif; ?>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Department -->
  <div>
    <label>Department</label>
    <select name="department_id" id="department_id" required onchange="filterRooms()">
      <option value="">Select Department</option>
    </select>
  </div>

  <!-- Room -->
  <div>
    <label>Room</label>
    <select name="room_id" id="room_id" required>
      <option value="">Select Room</option>
    </select>
  </div>

  <!-- Academic Term -->
  <div>
    <label>Academic Term</label>
    <select name="academic_term_id" id="academic_term_id" required>
      <option value="">Select Term</option>
      <?php foreach($terms as $t): ?>
        <option value="<?= $t['academic_term_id'] ?>">
          <?= htmlspecialchars($t['term_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <!-- Allocated To -->
  <div>
    <label>Allocated To</label>
    <select name="allocated_to" id="allocated_to" required>
      <option value="">Select Type</option>
      <option value="Class">Class</option>
      <option value="Exam">Exam</option>
      <option value="Maintenance">Maintenance</option>
    </select>
  </div>

  <!-- Dates -->
  <div>
    <label>Start Date</label>
    <input type="date" name="start_date" id="start_date" required>
  </div>

  <div>
    <label>End Date</label>
    <input type="date" name="end_date" id="end_date" required>
  </div>

  <!-- Remarks -->
  <div style="grid-column:span 2;">
    <label>Remarks</label>
    <textarea name="remarks" id="remarks" placeholder="Optional"></textarea>
  </div>

  <button type="submit" class="save-btn">Save Allocation</button>
</form>

  </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
// ========= GLOBAL DATA FROM PHP =========
const allDepartments = <?= json_encode($departments) ?>;
const allRooms = <?= json_encode($rooms) ?>;

// ========= MODAL HANDLING =========
function openModal(){
  const m = document.getElementById('allocationModal');
  m.classList.add('show');
  document.getElementById('modalTitle').innerText = "Add Allocation";
  document.getElementById('formAction').value = "add";
  document.getElementById('allocationForm').reset();
  document.getElementById('allocation_id').value = "";

  // ✅ Reset dropdowns only
  document.getElementById('faculty_id').selectedIndex = 0;
  document.getElementById('department_id').innerHTML = '<option value="">Select Department</option>';
  document.getElementById('room_id').innerHTML = '<option value="">Select Room</option>';
}


function closeModal(){
  document.getElementById('allocationModal').classList.remove('show');
}

window.onclick = function(e){
  const m = document.getElementById('allocationModal');
  if(e.target === m) closeModal();
};

// ========= CASCADING SELECTS =========

// Faculty -> Departments
function filterDepartments(){
  const facultyId = document.getElementById('faculty_id').value;
  const deptSelect = document.getElementById('department_id');
  const roomSelect = document.getElementById('room_id');

  deptSelect.innerHTML = '<option value="">Select Department</option>';
  roomSelect.innerHTML = '<option value="">Select Room</option>';

  const filtered = allDepartments.filter(d => d.faculty_id == facultyId);

  if(filtered.length === 0){
    const opt = document.createElement('option');
    opt.textContent = 'No departments found';
    deptSelect.appendChild(opt);
  } else {
    filtered.forEach(d => {
      const opt = document.createElement('option');
      opt.value = d.department_id;
      opt.textContent = d.department_name;
      deptSelect.appendChild(opt);
    });
  }
}

// Department -> Rooms
function filterRooms(){
  const deptId = document.getElementById('department_id').value;
  const roomSelect = document.getElementById('room_id');

  roomSelect.innerHTML = '<option value="">Select Room</option>';

  const filtered = allRooms.filter(r => r.department_id == deptId);

  if(filtered.length === 0){
    const opt = document.createElement('option');
    opt.textContent = 'No rooms found';
    roomSelect.appendChild(opt);
  } else {
    filtered.forEach(r => {
      const opt = document.createElement('option');
      opt.value = r.room_id;
      opt.textContent = r.room_name + ' (' + r.room_code + ')';
      roomSelect.appendChild(opt);
    });
  }
}

// ========= EDIT ALLOCATION =========
function editAllocation(data){
  const m = document.getElementById('allocationModal');
  m.classList.add('show');
  document.getElementById('modalTitle').innerText = "Edit Allocation";
  document.getElementById('formAction').value = "update";
  document.getElementById('allocation_id').value = data.allocation_id;

  // Faculty
  const facultySelect = document.getElementById('faculty_id');
  facultySelect.value = data.faculty_id;

  // Refresh Departments after setting faculty
  filterDepartments();

  // Set Department
  setTimeout(() => {
    document.getElementById('department_id').value = data.department_id;
    filterRooms();

    // Set Room
    setTimeout(() => {
      document.getElementById('room_id').value = data.room_id;
    }, 100);
  }, 100);

  // Other fields
  document.getElementById('academic_term_id').value = data.academic_term_id;
  document.getElementById('allocated_to').value = data.allocated_to;
  document.getElementById('start_date').value = data.start_date;
  document.getElementById('end_date').value = data.end_date;
  document.getElementById('remarks').value = data.remarks;
}

</script>


</body>
</html>
<?php ob_end_flush(); ?>
