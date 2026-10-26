<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control - Allow both faculty_admin and department_admin
$user_role = strtolower($_SESSION['user']['role'] ?? '');
if (!in_array($user_role, ['faculty_admin', 'department_admin'])) {
    header("Location: ../login.php");
    exit;
}

// ✅ Get linked_id based on role
$linked_id = $_SESSION['user']['linked_id'];
$faculty_id = null;
$department_id = null;

if ($user_role === 'faculty_admin') {
    $faculty_id = $linked_id;
} elseif ($user_role === 'department_admin') {
    $department_id = $linked_id;
    
    // Get faculty_id from department
    $stmt = $pdo->prepare("SELECT faculty_id FROM departments WHERE department_id = ?");
    $stmt->execute([$department_id]);
    $dept_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $faculty_id = $dept_info['faculty_id'] ?? null;
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
      $room_id = $_POST['room_id'];
      $allocated_to = $_POST['allocated_to'];

      // For department_admin, use their department_id automatically
      $dept_id = ($user_role === 'department_admin') ? $department_id : $_POST['department_id'];

      // Validate dates
      if ($start_date >= $end_date) {
        throw new Exception("End date must be after start date!");
      }

      $term = $pdo->prepare("SELECT start_date, end_date, status FROM academic_term WHERE academic_term_id=?");
      $term->execute([$term_id]);
      $term_dates = $term->fetch(PDO::FETCH_ASSOC);
      if (!$term_dates) throw new Exception("Invalid academic term selected!");

      // Check if term is active
      if ($term_dates['status'] !== 'active') {
        throw new Exception("Cannot allocate room to an inactive academic term!");
      }

      if ($start_date < $term_dates['start_date'] || $end_date > $term_dates['end_date']) {
        throw new Exception("Allocation dates must be within the term period (" . $term_dates['start_date'] . " → " . $term_dates['end_date'] . ").");
      }

      // Check if room is already allocated for the same period
      $checkAllocation = $pdo->prepare("
        SELECT COUNT(*) FROM room_allocation 
        WHERE room_id = ? 
        AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date))
        AND allocation_id != COALESCE(?, 0)
      ");
      $checkAllocation->execute([$room_id, $start_date, $end_date, $start_date, $end_date, $start_date, $_POST['allocation_id'] ?? 0]);
      
      if ($checkAllocation->fetchColumn() > 0) {
        throw new Exception("Room is already allocated for the selected period!");
      }

      $stmt = $pdo->prepare("INSERT INTO room_allocation 
        (room_id, academic_term_id, department_id, allocated_to, start_date, end_date, remarks)
        VALUES (?,?,?,?,?,?,?)");
      $stmt->execute([
        $room_id, $term_id, $dept_id,
        $allocated_to, $start_date, $end_date, $_POST['remarks']
      ]);

      // Update room status
      if ($allocated_to === 'Maintenance') {
        $pdo->prepare("UPDATE rooms SET status='maintenance' WHERE room_id=?")->execute([$room_id]);
      } else {
        $pdo->prepare("UPDATE rooms SET status='occupied' WHERE room_id=?")->execute([$room_id]);
      }

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
      $room_id = $_POST['room_id'];
      $allocated_to = $_POST['allocated_to'];
      $allocation_id = $_POST['allocation_id'];

      // For department_admin, use their department_id automatically
      $dept_id = ($user_role === 'department_admin') ? $department_id : $_POST['department_id'];

      // Validate dates
      if ($start_date >= $end_date) {
        throw new Exception("End date must be after start date!");
      }

      $term = $pdo->prepare("SELECT start_date, end_date, status FROM academic_term WHERE academic_term_id=?");
      $term->execute([$term_id]);
      $term_dates = $term->fetch(PDO::FETCH_ASSOC);

      if (!$term_dates) throw new Exception("Invalid academic term selected!");
      
      // Check if term is active (for new allocations, existing ones can be edited)
      if ($term_dates['status'] !== 'active') {
        throw new Exception("Cannot allocate room to an inactive academic term!");
      }

      if ($start_date < $term_dates['start_date'] || $end_date > $term_dates['end_date']) {
        throw new Exception("Allocation dates must be within (" . $term_dates['start_date'] . " → " . $term_dates['end_date'] . ").");
      }

      // Check if room is already allocated for the same period (excluding current allocation)
      $checkAllocation = $pdo->prepare("
        SELECT COUNT(*) FROM room_allocation 
        WHERE room_id = ? 
        AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date))
        AND allocation_id != ?
      ");
      $checkAllocation->execute([$room_id, $start_date, $end_date, $start_date, $end_date, $start_date, $allocation_id]);
      
      if ($checkAllocation->fetchColumn() > 0) {
        throw new Exception("Room is already allocated for the selected period!");
      }

      // Build query based on role
      if ($user_role === 'faculty_admin') {
        $stmt = $pdo->prepare("UPDATE room_allocation 
          SET room_id=?, academic_term_id=?, department_id=?, allocated_to=?, start_date=?, end_date=?, remarks=? 
          WHERE allocation_id=?");
        $params = [
          $room_id, $term_id, $dept_id,
          $allocated_to, $start_date, $end_date, $_POST['remarks'],
          $allocation_id
        ];
      } else { // department_admin
        $stmt = $pdo->prepare("UPDATE room_allocation 
          SET room_id=?, academic_term_id=?, allocated_to=?, start_date=?, end_date=?, remarks=? 
          WHERE allocation_id=? AND department_id=?");
        $params = [
          $room_id, $term_id,
          $allocated_to, $start_date, $end_date, $_POST['remarks'],
          $allocation_id, $department_id
        ];
      }

      $stmt->execute($params);

      // Update room status
      if ($allocated_to === 'Maintenance') {
        $pdo->prepare("UPDATE rooms SET status='maintenance' WHERE room_id=?")->execute([$room_id]);
      } else {
        $pdo->prepare("UPDATE rooms SET status='occupied' WHERE room_id=?")->execute([$room_id]);
      }

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

// ✅ Get current faculty info
if ($faculty_id) {
    $stmt = $pdo->prepare("SELECT * FROM faculties WHERE faculty_id=?");
    $stmt->execute([$faculty_id]);
    $current_faculty = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $current_faculty = null;
}

// ✅ Get departments based on role
if ($user_role === 'faculty_admin') {
    // Faculty admin sees all departments in their faculty
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE faculty_id=?");
    $stmt->execute([$faculty_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin only sees their own department
    $stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id=?");
    $stmt->execute([$department_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Get rooms based on role
if ($user_role === 'faculty_admin') {
    // Faculty admin sees all rooms in their faculty
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE faculty_id=? AND status != 'inactive'");
    $stmt->execute([$faculty_id]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin only sees rooms in their department
    $stmt = $pdo->prepare("SELECT * FROM rooms WHERE department_id=? AND status != 'inactive'");
    $stmt->execute([$department_id]);
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Get ONLY ACTIVE academic terms
$terms = $pdo->query("SELECT * FROM academic_term WHERE status = 'active' ORDER BY academic_term_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get allocations filtered by role
if ($user_role === 'faculty_admin') {
    // Faculty admin sees all allocations in their faculty
    $stmt = $pdo->prepare("SELECT 
        a.*, 
        r.room_name, r.room_code, r.status as room_status,
        d.department_name,
        f.faculty_name,
        t.term_name,
        t.status as term_status
      FROM room_allocation a
      JOIN rooms r ON a.room_id = r.room_id
      JOIN departments d ON a.department_id = d.department_id
      JOIN faculties f ON d.faculty_id = f.faculty_id
      JOIN academic_term t ON a.academic_term_id = t.academic_term_id
      WHERE f.faculty_id = ?
      ORDER BY a.allocation_id DESC");
    $stmt->execute([$faculty_id]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin only sees allocations in their department
    $stmt = $pdo->prepare("SELECT 
        a.*, 
        r.room_name, r.room_code, r.status as room_status,
        d.department_name,
        f.faculty_name,
        t.term_name,
        t.status as term_status
      FROM room_allocation a
      JOIN rooms r ON a.room_id = r.room_id
      JOIN departments d ON a.department_id = d.department_id
      JOIN faculties f ON d.faculty_id = f.faculty_id
      JOIN academic_term t ON a.academic_term_id = t.academic_term_id
      WHERE a.department_id = ?
      ORDER BY a.allocation_id DESC");
    $stmt->execute([$department_id]);
    $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Room Allocations | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {--green:#00843D; --blue:#0072CE; --red:#C62828; --light-green:#00A651; --bg:#F5F9F7;}
body {font-family:'Poppins',sans-serif; background:var(--bg); margin:0; color:#333;}
.main-content {padding:20px; margin-top:90px; margin-left:250px; transition:all .3s ease;}
.sidebar.collapsed ~ .main-content {margin-left:70px;}
.page-header {display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;}
.page-header h1 {color:var(--blue); font-size:24px; margin:0; font-weight:700;}
.add-btn {background:var(--green); color:#fff; border:none; padding:10px 18px; border-radius:6px; font-weight:600; cursor:pointer; transition:.3s;}
.add-btn:hover {background:var(--light-green);}
.table-wrapper {overflow:auto; max-height:500px; border-radius:10px;}
table {width:100%; border-collapse:collapse; background:#fff; border-radius:10px; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
thead th {position:sticky; top:0; background:var(--blue); color:#fff; z-index:2;}
th,td {padding:12px 14px; border-bottom:1px solid #eee; text-align:left; white-space:nowrap;}
tr:hover {background:#eef8f0;}
.action-btns {display:flex; justify-content:center; gap:8px;}
.edit-btn {background:var(--blue); border:none; border-radius:6px; padding:8px 10px; color:#fff; cursor:pointer; transition:.3s;}
.edit-btn:hover {background:#2196f3;}
.modal {display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index:3000;}
.modal.show {display:flex;}
.modal-content {background:#fff; border-radius:10px; width:90%; max-width:700px; padding:25px 35px; position:relative; box-shadow:0 8px 25px rgba(0,0,0,0.25);}
.close-modal {position:absolute; top:10px; right:15px; font-size:24px; cursor:pointer; color:var(--red);}
form {display:grid; grid-template-columns:1fr 1fr; gap:16px 24px;}
label {font-weight:600; color:var(--blue);}
input,select,textarea {width:100%; padding:8px 10px; border:1.5px solid #ccc; border-radius:6px; font-size:13.5px;}
textarea {resize:none; min-height:60px; font-family:inherit;}
.save-btn {grid-column:span 2; background:var(--green); color:#fff; border:none; padding:10px; border-radius:6px; font-weight:600; cursor:pointer; transition:.3s;}
.save-btn:hover {background:var(--light-green);}
.alert-popup {display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:10px; padding:16px 24px; text-align:center; z-index:4000; box-shadow:0 6px 20px rgba(0,0,0,.25);}
.alert-popup.show {display:block;}
.alert-popup.success {border-top:5px solid var(--green);} 
.alert-popup.error {border-top:5px solid var(--red);}
.info-banner {background:var(--blue); color:white; padding:10px 15px; border-radius:6px; margin-bottom:15px; font-weight:600;}
.status-badge {padding:4px 8px; border-radius:4px; font-size:12px; font-weight:600;}
.status-available {background:#e8f5e8; color:#2e7d32;}
.status-occupied {background:#ffeaa7; color:#e17055;}
.status-maintenance {background:#ffcdd2; color:#c62828;}
.status-active {background:#e8f5e8; color:#2e7d32;}
.status-inactive {background:#ffcdd2; color:#c62828;}
.term-status-info {background:#e3f2fd; padding:10px; border-radius:6px; margin-bottom:15px; border-left:4px solid var(--blue);}
.term-status-info p {margin:5px 0; font-size:14px;}
.no-active-terms {background:#fff3cd; border:1px solid #ffeaa7; padding:20px; border-radius:8px; text-align:center; margin-bottom:20px;}
.no-active-terms i {font-size:48px; color:#f39c12; margin-bottom:15px;}
.no-active-terms h3 {color:#856404; margin-bottom:10px;}
.department-info {background:#e8f5e9; padding:10px; border-radius:6px; margin-bottom:15px; border-left:4px solid var(--green);}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
    <?php if ($user_role === 'department_admin' && !empty($departments)): ?>
    <!-- <div class="department-info">
        <strong>Department:</strong> <?= htmlspecialchars($departments[0]['department_name']) ?>
    </div> -->
    <?php endif; ?>
    
    <?php if (empty($terms)): ?>
    <div class="no-active-terms">
        <i class="fas fa-calendar-times"></i>
        <h3>No Active Academic Terms Available</h3>
        <p>There are currently no active academic terms. Please contact the administrator to activate an academic term before allocating rooms.</p>
    </div>
    <?php endif; ?>
    
    <div class="page-header">
        <h1>Room Allocations 
            <?php if($user_role === 'department_admin' && !empty($departments)): ?>
                - <?= htmlspecialchars($departments[0]['department_name']) ?>
            <?php endif; ?>
        </h1>
        <button class="add-btn" onclick="openModal()" <?= empty($terms) ? 'disabled' : '' ?>>+ Add Allocation</button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if($user_role === 'faculty_admin'): ?>
                        <th>Faculty</th>
                    <?php endif; ?>
                    <th>Department</th>
                    <th>Room</th>
                    <th>Term</th>
                    <th>Term Status</th>
                    <th>Allocated To</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Remarks</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($allocations): foreach($allocations as $i=>$a): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <?php if($user_role === 'faculty_admin'): ?>
                        <td><?= htmlspecialchars($a['faculty_name']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($a['department_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($a['room_name'].' ('.$a['room_code'].')') ?>
                        <br><span class="status-badge status-<?= $a['room_status'] ?>"><?= ucfirst($a['room_status']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($a['term_name']) ?></td>
                    <td>
                        <span class="status-badge status-<?= $a['term_status'] ?>">
                            <?= ucfirst($a['term_status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($a['allocated_to']) ?></td>
                    <td><?= htmlspecialchars($a['start_date']) ?></td>
                    <td><?= htmlspecialchars($a['end_date']) ?></td>
                    <td><?= htmlspecialchars($a['remarks']) ?></td>
                    <td class="action-btns">
                        <button class="edit-btn" onclick='editAllocation(<?= json_encode($a) ?>)' 
                                <?= $a['term_status'] !== 'active' ? 'disabled title="Cannot edit allocations for inactive terms"' : '' ?>>
                            <i class="fa-solid fa-pen-to-square"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="<?= $user_role === 'faculty_admin' ? '11' : '10' ?>" style="text-align:center;color:#777;">
                        No allocations found <?= $user_role === 'department_admin' ? 'for your department' : 'for your faculty' ?>.
                    </td>
                </tr>
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

        <?php if($user_role === 'department_admin' && !empty($departments)): ?>
        <!-- <div class="department-info">
            <strong>Department:</strong> <?= htmlspecialchars($departments[0]['department_name']) ?>
        </div> -->
        <?php endif; ?>

        <form method="POST" id="allocationForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="allocation_id" id="allocation_id">

            <?php if($user_role === 'faculty_admin'): ?>
            <div>
                <label>Department</label>
                <select name="department_id" id="department_id" required>
                    <option value="">Select Department</option>
                    <?php foreach($departments as $d): ?>
                        <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="department_id" value="<?= $department_id ?>">
            <?php endif; ?>

            <div>
                <label>Room</label>
                <select name="room_id" id="room_id" required>
                    <option value="">Select Room</option>
                    <?php foreach($rooms as $r): ?>
                        <option value="<?= $r['room_id'] ?>" 
                                <?php if($user_role === 'faculty_admin'): ?>
                                    data-department="<?= $r['department_id'] ?>"
                                <?php endif; ?>>
                            <?= htmlspecialchars($r['room_name'].' ('.$r['room_code'].') - '.ucfirst($r['status'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Academic Term <small>(Active Only)</small></label>
                <select name="academic_term_id" id="academic_term_id" required>
                    <option value="">Select Term</option>
                    <?php foreach($terms as $t): ?>
                        <option value="<?= $t['academic_term_id'] ?>" data-status="<?= $t['status'] ?>">
                            <?= htmlspecialchars($t['term_name']) ?> 
                            (<?= date('M d, Y', strtotime($t['start_date'])) ?> - <?= date('M d, Y', strtotime($t['end_date'])) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($terms)): ?>
                <small style="color:var(--red);">No active academic terms available. Please contact administrator.</small>
                <?php endif; ?>
            </div>

            <div>
                <label>Allocated To</label>
                <select name="allocated_to" id="allocated_to" required>
                    <option value="">Select Type</option>
                    <option value="Class">Class</option>
                    <option value="Exam">Exam</option>
                    <option value="Maintenance">Maintenance</option>
                    <option value="Meeting">Meeting</option>
                    <option value="Event">Event</option>
                </select>
            </div>

            <div><label>Start Date</label><input type="date" name="start_date" id="start_date" required></div>
            <div><label>End Date</label><input type="date" name="end_date" id="end_date" required></div>

            <div style="grid-column:span 2;">
                <label>Remarks</label>
                <textarea name="remarks" id="remarks" placeholder="Optional notes about this allocation"></textarea>
            </div>

            <button type="submit" class="save-btn" <?= empty($terms) ? 'disabled' : '' ?>>
                <?= empty($terms) ? 'No Active Terms Available' : 'Save Allocation' ?>
            </button>
        </form>
    </div>
</div>

<!-- Alert Popup -->
<div id="popup" class="alert-popup <?= $type ?> <?= !empty($message) ? 'show' : '' ?>">
    <i><?= $type === 'success' ? '✔️' : '❌' ?></i>
    <h3><?= $message ?></h3>
</div>

<script>
function openModal(){
    document.getElementById('allocationModal').classList.add('show');
    document.getElementById('formAction').value = "add";
    document.getElementById('modalTitle').innerText = "Add Allocation";
    document.getElementById('allocationForm').reset();
    <?php if($user_role === 'faculty_admin'): ?>
    filterRoomsByDepartment();
    <?php endif; ?>
}

function closeModal(){
    document.getElementById('allocationModal').classList.remove('show');
}

<?php if($user_role === 'faculty_admin'): ?>
function filterRoomsByDepartment() {
    const deptId = document.getElementById('department_id').value;
    const roomSelect = document.getElementById('room_id');
    const allRoomOptions = roomSelect.querySelectorAll('option');
    
    // Show all options initially
    allRoomOptions.forEach(opt => {
        if (opt.value === "") {
            opt.style.display = 'block';
        } else {
            const roomDeptId = opt.getAttribute('data-department');
            if (roomDeptId === deptId) {
                opt.style.display = 'block';
            } else {
                opt.style.display = 'none';
            }
        }
    });
    
    // Reset selection if current selection is not in filtered list
    const currentRoomId = roomSelect.value;
    const currentOption = roomSelect.querySelector(`option[value="${currentRoomId}"]`);
    if (currentOption && currentOption.style.display === 'none') {
        roomSelect.value = "";
    }
}
<?php endif; ?>

function editAllocation(data){
    openModal();
    document.getElementById('formAction').value = "update";
    document.getElementById('modalTitle').innerText = "Edit Allocation";
    document.getElementById('allocation_id').value = data.allocation_id;
    <?php if($user_role === 'faculty_admin'): ?>
    document.getElementById('department_id').value = data.department_id;
    <?php endif; ?>
    document.getElementById('academic_term_id').value = data.academic_term_id;
    document.getElementById('allocated_to').value = data.allocated_to;
    document.getElementById('start_date').value = data.start_date;
    document.getElementById('end_date').value = data.end_date;
    document.getElementById('remarks').value = data.remarks;
    
    <?php if($user_role === 'faculty_admin'): ?>
    // Filter rooms and then set room value
    filterRoomsByDepartment();
    setTimeout(() => {
        document.getElementById('room_id').value = data.room_id;
    }, 100);
    <?php else: ?>
    document.getElementById('room_id').value = data.room_id;
    <?php endif; ?>
}

// Event listeners
<?php if($user_role === 'faculty_admin'): ?>
document.getElementById('department_id').addEventListener('change', filterRoomsByDepartment);
<?php endif; ?>

// Auto-hide alert popup
<?php if (!empty($message)): ?>
setTimeout(() => {
    const popup = document.getElementById('popup');
    if (popup) popup.classList.remove('show');
}, 3500);
<?php endif; ?>

// Form validation for dates
document.getElementById('allocationForm').addEventListener('submit', function(e) {
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    if (startDate >= endDate) {
        e.preventDefault();
        alert('End date must be after start date!');
        return false;
    }

    // Check if academic term is selected and active
    const termSelect = document.getElementById('academic_term_id');
    const selectedTerm = termSelect.options[termSelect.selectedIndex];
    if (selectedTerm && selectedTerm.getAttribute('data-status') !== 'active') {
        e.preventDefault();
        alert('You can only allocate rooms to active academic terms!');
        return false;
    }
});
</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>