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
        (room_id, academic_term_id, department_id, allocated_to, start_date, end_date, remarks)
        VALUES (?,?,?,?,?,?,?)");
      $stmt->execute([
        $_POST['room_id'], $term_id, $_POST['department_id'],
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
        WHERE allocation_id=?");
      $stmt->execute([
        $_POST['room_id'], $term_id, $_POST['department_id'],
        $_POST['allocated_to'], $start_date, $end_date, $_POST['remarks'], $_POST['allocation_id']
      ]);

      $message = "✅ Allocation updated successfully!";
      $type = "success";
    } catch (Exception $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }

  // 🔴 DELETE
  if ($_POST['action'] === 'delete') {
    try {
      $id = $_POST['allocation_id'];
      $room = $pdo->prepare("SELECT room_id FROM room_allocation WHERE allocation_id=?");
      $room->execute([$id]);
      $room_id = $room->fetchColumn();

      $pdo->prepare("DELETE FROM room_allocation WHERE allocation_id=?")->execute([$id]);
      $pdo->prepare("UPDATE rooms SET status='available' WHERE room_id=?")->execute([$room_id]);

      $message = "✅ Allocation deleted!";
      $type = "success";
    } catch (PDOException $e) {
      $message = "❌ ".$e->getMessage();
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
$rooms = $pdo->query("SELECT room_id, room_name, room_code, room_type, status 
  FROM rooms WHERE status!='inactive'")->fetchAll(PDO::FETCH_ASSOC);
$terms = $pdo->query("SELECT * FROM academic_term WHERE status = 'active' ORDER BY academic_term_id DESC")->fetchAll(PDO::FETCH_ASSOC);

$allocations = $pdo->query("SELECT 
    a.*, 
    r.room_name, r.room_code, r.room_id,
    d.department_name, d.department_id, d.faculty_id,
    f.faculty_name, f.faculty_id, f.campus_id,
    c.campus_name, c.campus_id,
    t.term_name, t.academic_term_id
  FROM room_allocation a
  JOIN rooms r ON a.room_id = r.room_id
  JOIN departments d ON a.department_id = d.department_id
  JOIN faculties f ON d.faculty_id = f.faculty_id
  JOIN campus c ON f.campus_id = c.campus_id
  JOIN academic_term t ON a.academic_term_id = t.academic_term_id
  ORDER BY a.allocation_id DESC
")->fetchAll(PDO::FETCH_ASSOC);

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
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this allocation?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="allocation_id" value="<?= $a['allocation_id'] ?>">
              <button class="del-btn"><i class="fa-solid fa-trash"></i></button>
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

      <!-- Campus & Faculty -->
      <div>
        <label>Campus</label>
        <select name="campus_id" id="campus_id" required>
          <option value="">Select Campus</option>
          <?php foreach($campuses as $c): ?>
          <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Faculty</label>
        <select name="faculty_id" id="faculty_id" required>
          <option value="">Select Faculty</option>
          <?php foreach($faculties as $f): ?>
          <option value="<?= $f['faculty_id'] ?>"><?= htmlspecialchars($f['faculty_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Department & Room -->
      <div>
        <label>Department</label>
        <select name="department_id" id="department_id" required>
          <option value="">Select Department</option>
          <?php foreach($departments as $d): ?>
          <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Room</label>
        <select name="room_id" id="room_id" required>
          <option value="">Select Room</option>
          <?php foreach($rooms as $r): ?>
  <option value="<?= $r['room_id'] ?>" 
          data-room-type="<?= htmlspecialchars($r['room_type'] ?? '') ?>">
    <?= htmlspecialchars($r['room_name'].' ('.$r['room_code'].')') ?>
  </option>
<?php endforeach; ?>

        </select>
      </div>

      <!-- Term & Allocated To -->
      <div>
        <label>Academic Term</label>
        <select name="academic_term_id" id="academic_term_id" required>
          <option value="">Select Term</option>
          <?php foreach($terms as $t): ?>
          <option value="<?= $t['academic_term_id'] ?>"><?= htmlspecialchars($t['term_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

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
function openModal(){
  const m=document.getElementById('allocationModal');
  m.classList.add('show');
  document.getElementById('modalTitle').innerText="Add Allocation";
  document.getElementById('formAction').value="add";
  document.getElementById('allocationForm').reset();
  document.getElementById('allocation_id').value="";
}
function closeModal(){
  document.getElementById('allocationModal').classList.remove('show');
}
function editAllocation(data){
  const m=document.getElementById('allocationModal');
  m.classList.add('show');
  document.getElementById('modalTitle').innerText="Edit Allocation";
  document.getElementById('formAction').value="update";
  document.getElementById('allocation_id').value=data.allocation_id;
  if(document.getElementById('campus_id'))document.getElementById('campus_id').value=data.campus_id;
  if(document.getElementById('faculty_id'))document.getElementById('faculty_id').value=data.faculty_id;
  document.getElementById('department_id').value=data.department_id;
  document.getElementById('room_id').value=data.room_id;
  document.getElementById('academic_term_id').value=data.academic_term_id;
  document.getElementById('allocated_to').value=data.allocated_to;
  document.getElementById('start_date').value=data.start_date;
  document.getElementById('end_date').value=data.end_date;
  document.getElementById('remarks').value=data.remarks;
}
window.onclick=function(e){
  const m=document.getElementById('allocationModal');
  if(e.target===m)closeModal();
}
</script>
<script>
/* Helper to populate a <select> */
function populateSelect(selectEl, items, placeholderText = 'Select') {
  selectEl.innerHTML = '';
  const ph = document.createElement('option');
  ph.value = '';
  ph.textContent = placeholderText;
  selectEl.appendChild(ph);
  items.forEach(it => {
    const opt = document.createElement('option');
    // it may have id fields like faculty_id, department_id, room_id
    const idKey = it.faculty_id ? 'faculty_id' : (it.department_id ? 'department_id' : (it.room_id ? 'room_id' : 'id'));
    const name = it.faculty_name ?? it.department_name ?? (it.room_name ? (it.room_name + (it.room_code ? ' ('+it.room_code+')' : '')) : '');
    opt.value = it[idKey] ?? it.id ?? '';
    opt.textContent = name;
    selectEl.appendChild(opt);
  });
}

/* AJAX GET JSON */
function ajaxGet(url, cb) {
  fetch(url, { credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => cb(null, data))
    .catch(err => cb(err));
}

/* Elements */
const campusSelect = document.getElementById('campus_id');
const facultySelect = document.getElementById('faculty_id');
const departmentSelect = document.getElementById('department_id');
const roomSelect = document.getElementById('room_id');
const modal = document.getElementById('allocationModal');

/* When campus changes -> load faculties */
if (campusSelect) {
  campusSelect.addEventListener('change', function(){
    const campusId = this.value;
    if (!campusId) {
      populateSelect(facultySelect, [], 'Select Faculty');
      populateSelect(departmentSelect, [], 'Select Department');
      return;
    }
    ajaxGet('get_options.php?action=faculties&campus_id=' + encodeURIComponent(campusId), function(err, res){
      if (err || !res || res.status !== 'ok') {
        populateSelect(facultySelect, [], 'Select Faculty');
        return;
      }
      populateSelect(facultySelect, res.data, 'Select Faculty');

      // Clear downstreams
      populateSelect(departmentSelect, [], 'Select Department');
      // Optionally clear rooms
      populateSelect(roomSelect, [], 'Select Room');
    });
  });
}

/* When faculty changes -> load departments and optionally rooms */
if (facultySelect) {
  facultySelect.addEventListener('change', function(){
    const facultyId = this.value;
    if (!facultyId) {
      populateSelect(departmentSelect, [], 'Select Department');
      populateSelect(roomSelect, [], 'Select Room');
      return;
    }
    // departments
    ajaxGet('get_options.php?action=departments&faculty_id=' + encodeURIComponent(facultyId), function(err, res){
      if (err || !res || res.status !== 'ok') {
        populateSelect(departmentSelect, [], 'Select Department');
        return;
      }
      populateSelect(departmentSelect, res.data, 'Select Department');
    });

    // rooms by faculty (optional — comment out if not needed or change endpoint)
    ajaxGet('get_options.php?action=rooms_by_faculty&faculty_id=' + encodeURIComponent(facultyId), function(err, res){
      if (err || !res || res.status !== 'ok') {
        populateSelect(roomSelect, [], 'Select Room');
        return;
      }
      populateSelect(roomSelect, res.data, 'Select Room');
    });
  });
}

/* When editing allocation: ensure dependent selects are populated before setting values.
   We update editAllocation() function to trigger change events. */

function editAllocation(data){
  const m=document.getElementById('allocationModal');
  m.classList.add('show');
  document.getElementById('modalTitle').innerText="Edit Allocation";
  document.getElementById('formAction').value="update";
  document.getElementById('allocation_id').value=data.allocation_id;

  // Set campus first (if available) and trigger change to load faculties
  if(document.getElementById('campus_id')){
    document.getElementById('campus_id').value = data.campus_id || '';
    // trigger change programmatically
    const ev = new Event('change');
    document.getElementById('campus_id').dispatchEvent(ev);

    // Wait for faculties to be loaded then set faculty & trigger faculty change
    // We'll poll for faculty options and set when available (simple approach)
    const setFacultyWhenReady = setInterval(function(){
      if (document.getElementById('faculty_id').options.length > 1) {
        document.getElementById('faculty_id').value = data.faculty_id || '';
        document.getElementById('faculty_id').dispatchEvent(ev);

        // Wait for departments to load and then set department
        const setDeptWhenReady = setInterval(function(){
          if (document.getElementById('department_id').options.length > 1) {
            document.getElementById('department_id').value = data.department_id || '';
            clearInterval(setDeptWhenReady);
          }
        }, 100);
        clearInterval(setFacultyWhenReady);
      }
    }, 100);
  } else {
    // fallback: set the selects directly
    document.getElementById('faculty_id').value = data.faculty_id;
    document.getElementById('department_id').value = data.department_id;
  }

  // Rooms (if rooms are not dynamically loaded, set directly)
  if(document.getElementById('room_id')) {
    // If rooms are loaded by faculty change above, wait similarly; otherwise set directly:
    setTimeout(function(){
      document.getElementById('room_id').value = data.room_id || '';
    }, 400);
  }

  document.getElementById('academic_term_id').value=data.academic_term_id;
  document.getElementById('allocated_to').value=data.allocated_to;
  document.getElementById('start_date').value=data.start_date;
  document.getElementById('end_date').value=data.end_date;
  document.getElementById('remarks').value=data.remarks;
}
</script>
<script>
// Marka room la doorto, si toos ah u dejiso "Allocated To" iyadoo ku saleysan room_type
document.getElementById('room_id').addEventListener('change', function() {
  const selected = this.options[this.selectedIndex];
  const roomType = selected.dataset.roomType ? selected.dataset.roomType.toLowerCase() : '';
  const allocatedTo = document.getElementById('allocated_to');

  if (!roomType) return; // haddii room_type maqan yahay, ha samayn wax

  if (roomType.includes('office')) {
    allocatedTo.value = 'Maintenance'; // ama "Office" haddii aad rabto
  } 
  else if (roomType.includes('class')) {
    allocatedTo.value = 'Class';
  } 
  else if (roomType.includes('exam')) {
    allocatedTo.value = 'Exam';
  } 
  else {
    allocatedTo.value = ''; // default
  }
});
</script>
<!-- script-yo kale kadib -->
<script>
document.getElementById('room_id').addEventListener('change', function() {
  const selected = this.options[this.selectedIndex];
  const roomType = selected.dataset.roomType ? selected.dataset.roomType.toLowerCase() : '';
  const allocatedTo = document.getElementById('allocated_to');

  if (!roomType) return;

  if (roomType.includes('office')) {
    allocatedTo.value = 'Maintenance';
  } 
  else if (roomType.includes('class')) {
    allocatedTo.value = 'Class';
  } 
  else if (roomType.includes('exam')) {
    allocatedTo.value = 'Exam';
  } 
  else {
    allocatedTo.value = '';
  }
});
</script>

</body>
</html>
<?php ob_end_flush(); ?>

</body>
</html>
<?php ob_end_flush(); ?>
