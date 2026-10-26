<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Check login
if (empty($_SESSION['user'])) {
  header("Location: ../login.php");
  exit;
}

// ✅ Access control
if (strtolower($_SESSION['user']['role'] ?? '') !== 'super_admin') {
  header("Location: ../login.php");
  exit;
}

$user  = $_SESSION['user'];
$role  = strtolower(str_replace(' ', '_', $user['role'] ?? ''));
$name  = htmlspecialchars($user['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($user['phone_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$photo = !empty($user['profile_photo_path'])
  ? "../" . htmlspecialchars($user['profile_photo_path'], ENT_QUOTES, 'UTF-8')
  : "../upload/profiles/default.png";

$currentPage = basename($_SERVER['PHP_SELF']);

$message = "";
$type = "";

/* =========================================================
   CRUD OPERATIONS
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // 🟢 ADD ROOM
  if ($action === 'add') {
    try {
      $campus_id = $_POST['campus_id'] ?? null;
      $room_code = trim($_POST['room_code'] ?? '');
      
  
      // ✅ Check same room_code *within the same campus only*
      $check = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_code=? AND campus_id=?");
      $check->execute([$room_code, $campus_id]);
  
      if ($check->fetchColumn() > 0) {
        $message = "⚠️ Room code already exists in this campus!";
        $type = "error";
      } else {
        $stmt = $pdo->prepare("INSERT INTO rooms 
          (campus_id, faculty_id, department_id, building_name, floor_no, room_name, room_code, capacity, room_type, description, status)
          VALUES (?,?,?,?,?,?,?,?,?,?, 'available')");
        $stmt->execute([
          $_POST['campus_id'], $_POST['faculty_id'], $_POST['department_id'],
          $_POST['building_name'], $_POST['floor_no'], $_POST['room_name'],
          $_POST['room_code'], $_POST['capacity'], $_POST['room_type'],
          $_POST['description']
        ]);
        $message = "✅ Room added successfully!";
        $type = "success";
      }
  
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }
  
  // 🟡 UPDATE ROOM
  if ($action === 'update') {
    try {
      $room_id   = $_POST['room_id'] ?? null;
      $campus_id = $_POST['campus_id'] ?? null;
      $room_code = trim($_POST['room_code'] ?? '');
      
  
      // ✅ Check duplication only if same code exists in same campus but different room
      $check = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_code=? AND campus_id=? AND room_id<>?");
      $check->execute([$room_code, $campus_id, $room_id]);
  
      if ($check->fetchColumn() > 0) {
        $message = "⚠️ Room code already exists in this campus!";
        $type = "error";
      } else {
        $stmt = $pdo->prepare("UPDATE rooms 
          SET campus_id=?, faculty_id=?, department_id=?, building_name=?, floor_no=?, room_name=?, room_code=?, capacity=?, room_type=?, description=?, status=? 
          WHERE room_id=?");
        $stmt->execute([
          $_POST['campus_id'], $_POST['faculty_id'], $_POST['department_id'],
          $_POST['building_name'], $_POST['floor_no'], $_POST['room_name'],
          $_POST['room_code'], $_POST['capacity'], $_POST['room_type'],
          $_POST['description'], $_POST['status'], $_POST['room_id']
        ]);
        $message = "✅ Room updated successfully!";
        $type = "success";
      }
  
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }
  

  // 🔴 DELETE ROOM
  if ($action === 'delete') {
    try {
      $pdo->prepare("DELETE FROM rooms WHERE room_id=?")->execute([$_POST['room_id']]);
      $message = "✅ Room deleted successfully!";
      $type = "success";
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }
}

/* =========================================================
   FETCH RELATED DATA
========================================================= */
$campuses = $pdo->query("SELECT campus_id, campus_name FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties = $pdo->query("SELECT faculty_id, faculty_name, campus_id FROM faculties")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT department_id, department_name, faculty_id FROM departments")->fetchAll(PDO::FETCH_ASSOC);

$rooms = $pdo->query("SELECT r.*, c.campus_name, f.faculty_name, d.department_name
  FROM rooms r
  LEFT JOIN campus c ON r.campus_id = c.campus_id
  LEFT JOIN faculties f ON r.faculty_id = f.faculty_id
  LEFT JOIN departments d ON r.department_id = d.department_id
  ORDER BY r.room_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Room Management | Hormuud University</title>
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--red:#C62828;--light-green:#00A651;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;color:#333;}
.main-content{
  padding:20px;
  margin-top:90px;
  margin-left:250px;
  transition:all .3s ease;
}
/* ✅ Marka sidebar la xiro (class=active), content-ka ha dhaqaaqo */
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.page-header h1{color:var(--blue);font-size:24px;margin:0;font-weight:700;}
.add-btn{background:var(--green);color:#fff;border:none;padding:10px 18px;border-radius:6px;font-weight:600;cursor:pointer;transition:.3s;}
.add-btn:hover{background:var(--light-green);}
.table-wrapper{overflow:auto;max-height:500px;border-radius:10px;}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
thead th{position:sticky;top:0;background:var(--blue);color:#fff;z-index:2;}
th,td{padding:12px 14px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap;}
tr:hover{background:#eef8f0;}
.action-btns{display:flex;justify-content:center;gap:8px;}
.edit-btn,.del-btn{border:none;border-radius:6px;padding:8px 10px;color:#fff;cursor:pointer;transition:.3s;}
.edit-btn{background:var(--blue);} .edit-btn:hover{background:#2196f3;}
.del-btn{background:var(--red);} .del-btn:hover{background:#e53935;}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:3000;}
.modal.show{display:flex;}
.modal-content{background:#fff;border-radius:10px;width:90%;max-width:800px;padding:25px;position:relative;box-shadow:0 8px 25px rgba(0,0,0,.25);}
.close-modal{position:absolute;top:10px;right:15px;font-size:24px;cursor:pointer;color:#444;}
form{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
label{font-weight:600;color:#0072CE;}
input,select,textarea{width:100%;padding:10px;border:1.5px solid #ccc;border-radius:6px;font-family:'Poppins';}
textarea{resize:none;height:60px;}
.save-btn{grid-column:span 2;background:var(--green);color:#fff;border:none;padding:10px;border-radius:6px;font-weight:600;cursor:pointer;transition:.3s;}
.save-btn:hover{background:var(--light-green);}
.alert-popup{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:16px 24px;text-align:center;z-index:4000;box-shadow:0 6px 20px rgba(0,0,0,.25);}
.alert-popup.show{display:block;}
.alert-popup.success{border-top:5px solid var(--green);} .alert-popup.error{border-top:5px solid var(--red);}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Room Management</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Room</button>
  </div>

  <div class="table-wrapper">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Building</th>
        <th>Floor</th>
        <th>Room Name</th>
        <th>Code</th>
        <th>Capacity</th>
        <th>Campus</th>
        <th>Faculty</th>
        <th>Department</th>
        <th>Type</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if($rooms): foreach($rooms as $i=>$r): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($r['building_name']) ?></td>
        <td><?= htmlspecialchars($r['floor_no']) ?></td>
        <td><?= htmlspecialchars($r['room_name']) ?></td>
        <td><?= htmlspecialchars($r['room_code']) ?></td>
        <td><?= htmlspecialchars($r['capacity']) ?></td>
        <td><?= htmlspecialchars($r['campus_name']) ?></td>
        <td><?= htmlspecialchars($r['faculty_name']) ?></td>
        <td><?= htmlspecialchars($r['department_name']) ?></td>
        <!-- ✅ Type hore -->
        <td><?= htmlspecialchars($r['room_type']) ?></td>
        <!-- ✅ Status kadib -->
        <td><?= ucfirst($r['status']) ?></td>
        <td class="action-btns">
          <button class="edit-btn" onclick="openEditModal(
            <?= $r['room_id'] ?>,
            '<?= $r['campus_id'] ?>',
            '<?= $r['faculty_id'] ?>',
            '<?= $r['department_id'] ?>',
            '<?= htmlspecialchars($r['building_name'], ENT_QUOTES) ?>',
            '<?= htmlspecialchars($r['floor_no'], ENT_QUOTES) ?>',
            '<?= htmlspecialchars($r['room_name'], ENT_QUOTES) ?>',
            '<?= htmlspecialchars($r['capacity'], ENT_QUOTES) ?>',
            '<?= htmlspecialchars($r['room_type'], ENT_QUOTES) ?>',
            '<?= htmlspecialchars($r['description'], ENT_QUOTES) ?>',
            '<?= $r['status'] ?>'
          )">
            <i class="fa-solid fa-pen-to-square"></i>
          </button>
          <button class="del-btn" onclick="openDeleteModal(<?= $r['room_id'] ?>)">
            <i class="fa-solid fa-trash"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; else: ?>
      <tr>
        <td colspan="12" style="text-align:center;color:#777;">No rooms found.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2>Add Room</h2>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div><label>Campus</label><select id="add_campus" name="campus_id" onchange="filterFaculties('add')" required>
        <option value="">Select Campus</option>
        <?php foreach($campuses as $c): ?><option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Faculty</label><select id="add_faculty" name="faculty_id" onchange="filterDepartments('add')" required><option value="">Select Faculty</option></select></div>
      <div><label>Department</label><select id="add_department" name="department_id" required><option value="">Select Department</option></select></div>
      <div><label>Building</label><input name="building_name" required></div>
      <div><label>Floor</label><input name="floor_no"></div>
      <div><label>Room Name</label><input name="room_name" required></div>
      <div><label>Room Code</label><input name="room_code" required></div>
      <div><label>Capacity</label><input name="capacity" type="number"></div>
      <div><label>Room Type</label>
        <select name="room_type" required>
          <option value="">Select Type</option>
          <option value="Lecture">Lecture</option>
          <option value="Lab">Lab</option>
          <option value="Seminar">Seminar</option>
          <option value="Office">Office</option>
          <option value="Online">Online</option>
        </select>
      </div>
      <div><label>Description</label><textarea name="description"></textarea></div>
      <button class="save-btn" type="submit">Save</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2>Edit Room</h2>
    <form method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="room_id">
      <div><label>Campus</label><select id="edit_campus" name="campus_id" onchange="filterFaculties('edit')" required>
        <option value="">Select Campus</option>
        <?php foreach($campuses as $c): ?><option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option><?php endforeach; ?>
      </select></div>
      <div><label>Faculty</label><select id="edit_faculty" name="faculty_id" onchange="filterDepartments('edit')" required><option value="">Select Faculty</option></select></div>
      <div><label>Department</label><select id="edit_department" name="department_id" required><option value="">Select Department</option></select></div>
      <div><label>Building</label><input id="edit_building" name="building_name" required></div>
      <div><label>Floor</label><input id="edit_floor" name="floor_no"></div>
      <div><label>Room Name</label><input id="edit_roomname" name="room_name" required></div>
      <div><label>Capacity</label><input id="edit_capacity" name="capacity" type="number"></div>
      <div><label>Room Type</label>
        <select id="edit_type" name="room_type" required>
          <option value="Lecture">Lecture</option>
          <option value="Lab">Lab</option>
          <option value="Seminar">Seminar</option>
          <option value="Office">Office</option>
          <option value="Online">Online</option>
        </select>
      </div>
      <div><label>Description</label><textarea id="edit_desc" name="description"></textarea></div>
      <div><label>Status</label>
        <select id="edit_status" name="status">
          <option value="available">Available</option>
          <option value="maintenance">Maintenance</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <button class="save-btn" type="submit">Update</button>
    </form>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
    <h2 style="color:var(--red)">Confirm Delete</h2>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="room_id" id="delete_id">
      <p>Are you sure you want to delete this room?</p>
      <button class="save-btn" style="background:var(--red)" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ALERT -->
<div id="popup" class="alert-popup <?= $type ?>">
  <h3><?= $message ?></h3>
</div>

<script>
const faculties = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;

function filterFaculties(prefix){
  const campus=document.getElementById(prefix+'_campus').value;
  const fSelect=document.getElementById(prefix+'_faculty');
  fSelect.innerHTML='<option value="">Select Faculty</option>';
  faculties.filter(f=>f.campus_id==campus).forEach(f=>{
    const opt=document.createElement('option');
    opt.value=f.faculty_id;
    opt.textContent=f.faculty_name;
    fSelect.appendChild(opt);
  });
  filterDepartments(prefix);
}

function filterDepartments(prefix){
  const faculty=document.getElementById(prefix+'_faculty').value;
  const dSelect=document.getElementById(prefix+'_department');
  dSelect.innerHTML='<option value="">Select Department</option>';
  departments.filter(d=>d.faculty_id==faculty).forEach(d=>{
    const opt=document.createElement('option');
    opt.value=d.department_id;
    opt.textContent=d.department_name;
    dSelect.appendChild(opt);
  });
}

function openModal(id){document.getElementById(id).classList.add('show');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('show');document.body.style.overflow='auto';}
function openEditModal(id,campus,faculty,dept,building,floor,name,capacity,type,desc,status){
  openModal('editModal');
  edit_id.value=id;
  edit_campus.value=campus;
  filterFaculties('edit');
  edit_faculty.value=faculty;
  filterDepartments('edit');
  edit_department.value=dept;
  edit_building.value=building;
  edit_floor.value=floor;
  edit_roomname.value=name;
  edit_capacity.value=capacity;
  edit_type.value=type;
  edit_desc.value=desc;
  edit_status.value=status;
}
function openDeleteModal(id){openModal('deleteModal');delete_id.value=id;}
if("<?= $message ?>"){const p=document.getElementById('popup');p.classList.add('show');setTimeout(()=>p.classList.remove('show'),3500);}
</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>

</body>
</html>
<?php ob_end_flush(); ?>
