<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

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
// 🟢 ADD DEPARTMENT
if ($_POST['action'] === 'add') {
  try {
    // ✅ 1. Verify unique department code
    $code = trim($_POST['department_code']);
    $check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_code = ?");
    $check->execute([$code]);

    if ($check->fetchColumn() > 0) {
      $message = "⚠️ Department code already exists!";
      $type = "error";
    } else {

      // ✅ 2. Check if user email already exists
      $email = trim($_POST['email'] ?? '');
      $existing_user = null;
      if ($email) {
        $emailCheck = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $emailCheck->execute([$email]);
        $existing_user = $emailCheck->fetch(PDO::FETCH_ASSOC);
      }

      // ✅ 3. Get campus_id from logged-in campus admin
      $campus_id = $_SESSION['user']['linked_id'] ?? null;
      if (!$campus_id) {
        throw new Exception("Campus ID missing! Please log in again.");
      }

      // ✅ 4. Verify faculty belongs to this campus
      $faculty_id = $_POST['faculty_id'];
      $checkFaculty = $pdo->prepare("SELECT COUNT(*) FROM faculties WHERE faculty_id = ? AND campus_id = ?");
      $checkFaculty->execute([$faculty_id, $campus_id]);
      if ($checkFaculty->fetchColumn() == 0) {
        throw new Exception("You cannot add a department under a faculty from another campus!");
      }

      // ✅ 5. Upload profile photo (if provided)
      $photo_path = null;
      if (!empty($_FILES['profile_photo']['name'])) {
        $upload_dir = __DIR__ . '/../upload/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid('dept_') . '.' . strtolower($ext);
        $photo_path = 'upload/profiles/' . $new_name;
        move_uploaded_file($_FILES['profile_photo']['tmp_name'], __DIR__ . '/../' . $photo_path);
      }

      // ✅ 6. Insert department WITH campus_id
      $stmt = $pdo->prepare("
        INSERT INTO departments 
        (department_name, department_code, head_of_department, faculty_id, campus_id, phone_number, email, office_location, profile_photo_path, status)
        VALUES (?,?,?,?,?,?,?,?,?, 'active')
      ");
      $stmt->execute([
        $_POST['department_name'],
        $_POST['department_code'],
        $_POST['head_of_department'],
        $_POST['faculty_id'],
        $campus_id,
        $_POST['phone_number'],
        $_POST['email'],
        $_POST['office_location'],
        $photo_path
      ]);

      $department_id = $pdo->lastInsertId();

      // ✅ 7. Handle user link / create new user
      if ($existing_user) {
        // Update user to link this department
        $updateUser = $pdo->prepare("
          UPDATE users 
          SET linked_id = ?, linked_table = 'department' 
          WHERE email = ?
        ");
        $updateUser->execute([$department_id, $email]);
      } else {
        // Create new department admin account
        $uuid = bin2hex(random_bytes(16));
        $plain_pass = "123"; // default password
        $hashed = password_hash($plain_pass, PASSWORD_BCRYPT);

        $user = $pdo->prepare("
          INSERT INTO users 
          (user_uuid, username, email, phone_number, profile_photo_path, password, password_plain, role, linked_id, linked_table, status)
          VALUES (?,?,?,?,?,?,?,?,?,?,'active')
        ");
        $user->execute([
          $uuid,
          $_POST['department_name'],
          $_POST['email'],
          $_POST['phone_number'],
          $photo_path,
          $hashed,
          $plain_pass,
          'department_admin',
          $department_id,
          'department'
        ]);
      }

      // ✅ 8. Success message
      $message = "✅ Department added successfully!";
      $type = "success";
    }

  } catch (PDOException $e) {
    $message = "❌ Database Error: " . $e->getMessage();
    $type = "error";
  } catch (Exception $ex) {
    $message = "❌ " . $ex->getMessage();
    $type = "error";
  }
}


}

// ✅ Fetch campuses & faculties
$campuses = $pdo->query("SELECT campus_id, campus_name FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$campus_id = $_SESSION['user']['linked_id'];
$stmt = $pdo->prepare("SELECT f.*, c.campus_id 
  FROM faculties f 
  JOIN campus c ON f.campus_id = c.campus_id 
  WHERE c.campus_id = ?");
$stmt->execute([$campus_id]);
$faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch departments with faculty and campus
$campus_id = $_SESSION['user']['linked_id'];
$stmt = $pdo->prepare("SELECT d.*, f.faculty_name, c.campus_name, c.campus_id
  FROM departments d
  JOIN faculties f ON d.faculty_id = f.faculty_id
  JOIN campus c ON f.campus_id = c.campus_id
  WHERE c.campus_id = ?
  ORDER BY d.department_id DESC");
$stmt->execute([$campus_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Management | Hormuud University</title>
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--red:#C62828;--light-green:#00A651;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;color:#333;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s ease;}
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
th,td{padding:14px 16px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap;}
tr:hover{background:#eef8f0;}
img.profile-photo{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid var(--green);}
.action-btns{display:flex;justify-content:center;gap:8px;}
.edit-btn,.del-btn{border:none;border-radius:6px;padding:8px 10px;color:#fff;cursor:pointer;transition:.3s;}
.edit-btn{background:var(--blue);} .edit-btn:hover{background:#2196f3;}
.del-btn{background:var(--red);} .del-btn:hover{background:#e53935;}
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:3000;}
.modal.show{display:flex;}
.modal-content{background:#fff;border-radius:10px;width:90%;max-width:700px;padding:25px;position:relative;box-shadow:0 8px 25px rgba(0,0,0,.25);}
.close-modal{position:absolute;top:10px;right:15px;font-size:24px;cursor:pointer;color:#444;}
form{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
label{font-weight:600;color:#0072CE;}
input,select{width:100%;padding:10px;border:1.5px solid #ccc;border-radius:6px;font-family:'Poppins';}
.save-btn{grid-column:span 2;background:var(--green);color:#fff;border:none;padding:10px;border-radius:6px;font-weight:600;cursor:pointer;transition:.3s;}
.save-btn:hover{background:var(--light-green);}
.alert-popup{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;border-radius:10px;padding:16px 24px;text-align:center;z-index:4000;box-shadow:0 6px 20px rgba(0,0,0,.25);}
.alert-popup.show{display:block;}
.alert-popup.success{border-top:5px solid var(--green);} .alert-popup.error{border-top:5px solid var(--red);}
</style>
</head>
<body>

<?php require_once('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>Department Management</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Department</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Photo</th><th>Department</th><th>Code</th><th>Head</th><th>Faculty</th><th>Campus</th>
          <th>Phone</th><th>Email</th><th>Password</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($departments): foreach($departments as $i=>$d): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><img src="../<?= $d['profile_photo_path'] ?: 'assets/img/default.png' ?>" class="profile-photo"></td>
          <td><?= htmlspecialchars($d['department_name']) ?></td>
          <td><?= htmlspecialchars($d['department_code']) ?></td>
          <td><?= htmlspecialchars($d['head_of_department']) ?></td>
          <td><?= htmlspecialchars($d['faculty_name']) ?></td>
          <td><?= htmlspecialchars($d['campus_name']) ?></td>
          <td><?= htmlspecialchars($d['phone_number']) ?></td>
          <td><?= htmlspecialchars($d['email']) ?></td>
          <td>123</td>
          <td><?= ucfirst($d['status']) ?></td>
          <td class="action-btns">
            <button class="edit-btn" onclick="openEditModal(<?= $d['department_id'] ?>,
              '<?= htmlspecialchars($d['department_name']) ?>',
              '<?= htmlspecialchars($d['department_code']) ?>',
              '<?= htmlspecialchars($d['head_of_department']) ?>',
              '<?= $d['campus_id'] ?>',
              '<?= $d['faculty_id'] ?>',
              '<?= htmlspecialchars($d['phone_number']) ?>',
              '<?= htmlspecialchars($d['email']) ?>',
              '<?= htmlspecialchars($d['office_location']) ?>',
              '<?= $d['status'] ?>')"><i class="fa-solid fa-pen-to-square"></i></button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="12" style="text-align:center;color:#777;">No departments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2>Add Department</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div><label>Department Name</label><input name="department_name" required></div>
      <div><label>Code</label><input name="department_code" required></div>
      <div><label>Head of Department</label><input name="head_of_department"></div>
      <input type="hidden" id="add_campus" value="<?= $_SESSION['user']['linked_id'] ?>">
      <div>
        <label>Faculty</label>
        <select id="add_faculty" name="faculty_id" required>
          <option value="">Select Faculty</option>
        </select>
      </div>
      <div><label>Phone</label><input name="phone_number"></div>
      <div><label>Email</label><input name="email" type="email"></div>
      <div><label>Office Location</label><input name="office_location"></div>
      <div><label>Photo</label><input type="file" name="profile_photo" accept="image/*"></div>
      <button class="save-btn" type="submit">Save</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2>Edit Department</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="department_id">
      <div><label>Department Name</label><input id="edit_name" name="department_name" required></div>
      <div><label>Code</label><input id="edit_code" name="department_code" disabled></div>
      <div><label>Head of Department</label><input id="edit_head" name="head_of_department"></div>
      <input type="hidden" id="edit_campus" value="<?= $_SESSION['user']['linked_id'] ?>">
      <div>
        <label>Faculty</label>
        <select id="edit_faculty" name="faculty_id" required>
          <option value="">Select Faculty</option>
        </select>
      </div>
      <div><label>Phone</label><input id="edit_phone" name="phone_number"></div>
      <div><label>Email</label><input id="edit_email" name="email" type="email"></div>
      <div><label>Office Location</label><input id="edit_office" name="office_location"></div>
      <div><label>Status</label>
        <select id="edit_status" name="status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div><label>New Photo</label><input type="file" name="profile_photo" accept="image/*"></div>
      <button class="save-btn" type="submit">Update</button>
    </form>
  </div>
</div>



<!-- ALERT POPUP -->
<div id="popup" class="alert-popup <?= $type ?>">
  <i><?= $type==='success'?'✔️':'❌' ?></i>
  <h3><?= $message ?></h3>
</div>

<script>
const faculties = <?= json_encode($faculties) ?>;

function filterFaculties(prefix) {
  const campusId = document.getElementById(prefix + '_campus').value;
  const facultySelect = document.getElementById(prefix + '_faculty');
  facultySelect.innerHTML = '<option value="">Select Faculty</option>';
  faculties.filter(f => f.campus_id == campusId).forEach(f => {
    const opt = document.createElement('option');
    opt.value = f.faculty_id;
    opt.textContent = f.faculty_name;
    facultySelect.appendChild(opt);
  });
}

function openModal(id){document.getElementById(id).classList.add('show');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('show');document.body.style.overflow='auto';}

function openEditModal(id,name,code,head,campus,faculty,phone,email,office,status){
  openModal('editModal');
  edit_id.value=id;edit_name.value=name;edit_code.value=code;edit_head.value=head;
  edit_campus.value=campus;filterFaculties('edit');edit_faculty.value=faculty;
  edit_phone.value=phone;edit_email.value=email;edit_office.value=office;edit_status.value=status;
}
function openDeleteModal(id){openModal('deleteModal');delete_id.value=id;}
if("<?= $message ?>"){const p=document.getElementById('popup');p.classList.add('show');setTimeout(()=>p.classList.remove('show'),3500);}
document.addEventListener('DOMContentLoaded', () => {
  const campusId = document.getElementById('add_campus').value;
  const facultySelect = document.getElementById('add_faculty');
  facultySelect.innerHTML = '<option value="">Select Faculty</option>';
  faculties.filter(f => f.campus_id == campusId).forEach(f => {
    const opt = document.createElement('option');
    opt.value = f.faculty_id;
    opt.textContent = f.faculty_name;
    facultySelect.appendChild(opt);
  });
});

</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>
