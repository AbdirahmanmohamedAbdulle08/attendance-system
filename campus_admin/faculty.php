<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control - Changed to campus_admin
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

  // 🟢 ADD FACULTY
if ($_POST['action'] === 'add') {
  try {
    $photo_path = null;

    if (!empty($_FILES['profile_photo']['name'])) {
      $upload_dir = __DIR__ . '/../upload/profiles/';
      if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
      $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
      $new_name = uniqid('faculty_') . '.' . strtolower($ext);
      $photo_path = 'upload/profiles/' . $new_name;
      move_uploaded_file($_FILES['profile_photo']['tmp_name'], __DIR__ . '/../' . $photo_path);
    }

    // Faculty insert
    $stmt = $pdo->prepare("INSERT INTO faculties 
      (faculty_name, faculty_code, dean_name, campus_id, phone_number, email, office_address, profile_photo_path, status)
      VALUES (?,?,?,?,?,?,?,?, 'active')");
    $stmt->execute([
      $_POST['faculty_name'], $_POST['faculty_code'], $_POST['dean_name'],
      $_POST['campus_id'], $_POST['phone_number'], $_POST['email'],
      $_POST['office_address'], $photo_path
    ]);

    $faculty_id = $pdo->lastInsertId();
    $uuid = bin2hex(random_bytes(16));
    $plain = "123";
    $hashed = password_hash($plain, PASSWORD_BCRYPT);

    // Create user account linked to faculty
    $user = $pdo->prepare("INSERT INTO users 
      (user_uuid, username, email, phone_number, profile_photo_path, password, password_plain, role, linked_id, linked_table, status)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $user->execute([
      $uuid,
      $_POST['faculty_name'],
      $_POST['email'],
      $_POST['phone_number'],
      $photo_path,
      $hashed,
      $plain,
      'faculty_admin',
      $faculty_id,
      'faculty',
      'active'
    ]);

    $message = "✅ Faculty added successfully!";
    $type = "success";
  } catch (PDOException $e) {
    $message = "❌ Error: " . $e->getMessage();
    $type = "error";
  }
}


  // 🟡 UPDATE FACULTY
  if ($_POST['action'] === 'update') {
    try {
      $id = $_POST['faculty_id'];
      $photo_sql = "";
      $params = [
        $_POST['faculty_name'], $_POST['dean_name'], $_POST['campus_id'],
        $_POST['phone_number'], $_POST['email'], $_POST['office_address'],
        $_POST['status'], $id
      ];

      if (!empty($_FILES['profile_photo']['name'])) {
        $upload_dir = __DIR__ . '/../upload/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid('faculty_') . '.' . strtolower($ext);
        $photo_path = 'upload/profiles/' . $new_name;
        move_uploaded_file($_FILES['profile_photo']['tmp_name'], __DIR__ . '/../' . $photo_path);
        $photo_sql = ", profile_photo_path=?";
        array_splice($params, 7, 0, $photo_path);
      }

      $pdo->prepare("UPDATE faculties 
        SET faculty_name=?, dean_name=?, campus_id=?, phone_number=?, email=?, office_address=?, status=? $photo_sql 
        WHERE faculty_id=?")->execute($params);

      // ✅ Sync user account
      $userStatus = ($_POST['status'] === 'inactive') ? 'inactive' : 'active';
      $plain = "123";
$hashed = password_hash($plain, PASSWORD_BCRYPT);

$updateUser = $pdo->prepare("UPDATE users 
  SET username=?, email=?, phone_number=?, password=?, password_plain=?, profile_photo_path=?, status=? 
  WHERE linked_id=? AND linked_table='faculty'");
$updateUser->execute([
  $_POST['faculty_name'],
  $_POST['email'],
  $_POST['phone_number'],
  $hashed,
  $plain,
  $photo_path ?? null,
  $userStatus,
  $id
]);

      $message = "✅ Faculty updated successfully!";
      $type = "success";
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }

  // 🔴 DELETE FACULTY
  if ($_POST['action'] === 'delete') {
    try {
      $id = $_POST['faculty_id'];
      $pdo->prepare("DELETE FROM users WHERE linked_id=? AND linked_table='faculty'")->execute([$id]);
      $pdo->prepare("DELETE FROM faculties WHERE faculty_id=?")->execute([$id]);
      $message = "✅ Faculty deleted successfully!";
      $type = "success";
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }
}

// ✅ Fetch campuses for dropdown
$campuses = $pdo->query("SELECT campus_id, campus_name FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch faculties list
$faculties = $pdo->query("SELECT f.*, c.campus_name 
  FROM faculties f 
  LEFT JOIN campus c ON f.campus_id = c.campus_id 
  ORDER BY f.faculty_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faculty Management | Hormuud University</title>
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
    <h1>Faculty Management</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Faculty</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Photo</th><th>Faculty</th><th>Code</th><th>Dean</th><th>Campus</th>
          <th>Phone</th><th>Email</th><th>Password</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($faculties): foreach($faculties as $i=>$f): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><img src="../<?= $f['profile_photo_path'] ?: 'assets/img/default.png' ?>" class="profile-photo"></td>
          <td><?= htmlspecialchars($f['faculty_name']) ?></td>
          <td><?= htmlspecialchars($f['faculty_code']) ?></td>
          <td><?= htmlspecialchars($f['dean_name']) ?></td>
          <td><?= htmlspecialchars($f['campus_name']) ?></td>
          <td><?= htmlspecialchars($f['phone_number']) ?></td>
          <td><?= htmlspecialchars($f['email']) ?></td>
          <td>123</td>
          <td><?= ucfirst($f['status']) ?></td>
          <td class="action-btns">
            <button class="edit-btn" onclick="openEditModal(<?= $f['faculty_id'] ?>,
              '<?= htmlspecialchars($f['faculty_name']) ?>',
              '<?= htmlspecialchars($f['faculty_code']) ?>',
              '<?= htmlspecialchars($f['dean_name']) ?>',
              '<?= $f['campus_id'] ?>',
              '<?= htmlspecialchars($f['phone_number']) ?>',
              '<?= htmlspecialchars($f['email']) ?>',
              '<?= htmlspecialchars($f['office_address']) ?>',
              '<?= $f['status'] ?>')"><i class="fa-solid fa-pen-to-square"></i></button>
            <button class="del-btn" onclick="openDeleteModal(<?= $f['faculty_id'] ?>)"><i class="fa-solid fa-trash"></i></button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11" style="text-align:center;color:#777;">No faculties found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2>Add Faculty</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div><label>Faculty Name</label><input name="faculty_name" required></div>
      <div><label>Faculty Code</label><input name="faculty_code" required></div>
      <div><label>Dean Name</label><input name="dean_name"></div>
      <div>
        <label>Campus</label>
        <select name="campus_id" required>
          <option value="">Select Campus</option>
          <?php foreach($campuses as $c): ?>
            <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Phone</label><input name="phone_number"></div>
      <div><label>Email</label><input type="email" name="email"></div>
      <div><label>Office Address</label><input name="office_address"></div>
      <div><label>Photo</label><input type="file" name="profile_photo" accept="image/*"></div>
      <button class="save-btn" type="submit">Save</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2>Edit Faculty</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="faculty_id">
      <div><label>Faculty Name</label><input id="edit_name" name="faculty_name" required></div>
      <div><label>Code</label><input id="edit_code" name="faculty_code" disabled></div>
      <div><label>Dean Name</label><input id="edit_dean" name="dean_name"></div>
      <div>
        <label>Campus</label>
        <select id="edit_campus" name="campus_id" required>
          <?php foreach($campuses as $c): ?>
            <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Phone</label><input id="edit_phone" name="phone_number"></div>
      <div><label>Email</label><input id="edit_email" name="email" type="email"></div>
      <div><label>Office Address</label><input id="edit_office" name="office_address"></div>
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

<!-- DELETE MODAL -->
<div class="modal" id="deleteModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
    <h2 style="color:var(--red)">Confirm Delete</h2>
    <form method="POST">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="faculty_id" id="delete_id">
      <p>Are you sure you want to delete this faculty?</p>
      <button class="save-btn" style="background:var(--red)" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ✅ ALERT POPUP -->
<div id="popup" class="alert-popup <?= $type ?> <?= !empty($message)?'show':'' ?>">
  <i><?= $type==='success' ? '✔️' : '❌' ?></i>
  <h3><?= $message ?></h3>
</div>

<script>
function openModal(id){
  document.getElementById(id).classList.add('show');
  document.body.style.overflow='hidden';
}

function closeModal(id){
  document.getElementById(id).classList.remove('show');
  document.body.style.overflow='auto';
}

function openEditModal(id,name,code,dean,campus,phone,email,office,status){
  openModal('editModal');
  edit_id.value=id;
  edit_name.value=name;
  edit_code.value=code;
  edit_dean.value=dean;
  edit_campus.value=campus;
  edit_phone.value=phone;
  edit_email.value=email;
  edit_office.value=office;
  edit_status.value=status;
}

function openDeleteModal(id){
  openModal('deleteModal');
  delete_id.value=id;
}
</script>

<?php if (!empty($message)): ?>
<script>
// ✅ Show popup automatically for 3.5 seconds
const p = document.getElementById('popup');
if (p) {
  p.classList.add('show');
  setTimeout(() => p.classList.remove('show'), 3500);
}
</script>
<?php endif; ?>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>