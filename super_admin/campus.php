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

  /* 🟢 ADD CAMPUS */
  if ($_POST['action'] === 'add') {
    try {
      $code = trim($_POST['campus_code']);
      $check = $pdo->prepare("SELECT COUNT(*) FROM campus WHERE campus_code=?");
      $check->execute([$code]);

      if ($check->fetchColumn() > 0) {
        $message = "⚠️ Campus code already exists!";
        $type = "error";
      } else {
        $photo_path = null;
        if (!empty($_FILES['profile_photo']['name'])) {
          $upload_dir = __DIR__ . '/../upload/profiles/';
          if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
          $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
          $new_name = uniqid('campus_') . '.' . strtolower($ext);
          $photo_path = 'upload/profiles/' . $new_name;
          move_uploaded_file($_FILES['profile_photo']['tmp_name'], __DIR__ . '/../' . $photo_path);
        }

        // ✅ Insert into campus
        $stmt = $pdo->prepare("INSERT INTO campus 
          (campus_name, campus_code, address, city, country, phone_number, email, profile_photo_path, status)
          VALUES (?,?,?,?,?,?,?,?, 'active')");
        $stmt->execute([
          $_POST['campus_name'], $_POST['campus_code'], $_POST['address'],
          $_POST['city'], $_POST['country'], $_POST['phone_number'],
          $_POST['email'], $photo_path
        ]);

        // ✅ Create default user (campus_admin)
        $campus_id = $pdo->lastInsertId();
        $uuid = bin2hex(random_bytes(16));
        $plain_pass = "123";
        $hashed = password_hash($plain_pass, PASSWORD_BCRYPT);

        $user = $pdo->prepare("INSERT INTO users 
          (user_uuid, username, email, phone_number, profile_photo_path, password, password_plain, role, linked_id, linked_table, status)
          VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $user->execute([
          $uuid,
          $_POST['campus_name'],
          $_POST['email'],
          $_POST['phone_number'],
          $photo_path,
          $hashed,
          $plain_pass,
          'campus_admin',
          $campus_id,
          'campus',
          'active'
        ]);

        $message = "✅ Campus added successfully!";
        $type = "success";
      }
    } catch (PDOException $e) {
      $message = "❌ Error: " . $e->getMessage();
      $type = "error";
    }
  }

  /* 🟡 UPDATE CAMPUS */
  if ($_POST['action'] === 'update') {
    try {
      $id = $_POST['campus_id'];

      $photo_sql = "";
      $params = [
        $_POST['campus_name'], $_POST['address'], $_POST['city'],
        $_POST['country'], $_POST['phone_number'], $_POST['email'],
        $_POST['status'], $id
      ];

      if (!empty($_FILES['profile_photo']['name'])) {
        $upload_dir = __DIR__ . '/../upload/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
        $new_name = uniqid('campus_') . '.' . strtolower($ext);
        $photo_path = 'upload/profiles/' . $new_name;
        move_uploaded_file($_FILES['profile_photo']['tmp_name'], __DIR__ . '/../' . $photo_path);
        $photo_sql = ", profile_photo_path=?";
        array_splice($params, 7, 0, $photo_path);
      }

      $pdo->prepare("UPDATE campus 
        SET campus_name=?, address=?, city=?, country=?, phone_number=?, email=?, status=? $photo_sql 
        WHERE campus_id=?")->execute($params);

      $userStatus = ($_POST['status'] === 'inactive') ? 'inactive' : 'active';
      $updateUser = $pdo->prepare("UPDATE users 
        SET username=?, email=?, phone_number=?, status=? 
        WHERE linked_id=? AND linked_table='campus'");
      $updateUser->execute([
        $_POST['campus_name'],
        $_POST['email'],
        $_POST['phone_number'],
        $userStatus,
        $id
      ]);

      $message = "✅ Campus updated successfully!";
      $type = "success";
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }

  /* 🔴 DELETE CAMPUS */
  if ($_POST['action'] === 'delete') {
    try {
      $id = $_POST['campus_id'];
      $pdo->prepare("DELETE FROM users WHERE linked_id=? AND linked_table='campus'")->execute([$id]);
      $pdo->prepare("DELETE FROM campus WHERE campus_id=?")->execute([$id]);
      $message = "✅ Campus deleted successfully!";
      $type = "success";
    } catch (PDOException $e) {
      $message = "❌ " . $e->getMessage();
      $type = "error";
    }
  }
}

$campuses = $pdo->query("SELECT * FROM campus ORDER BY campus_id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Campus Management | Hormuud University</title>
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--red:#C62828;--light-green:#00A651;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;color:#333;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s ease;}
.sidebar.collapsed ~ .main-content {
  margin-left:70px;
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
    <h1>Campus Management</h1>
    <button class="add-btn" onclick="openModal('addModal')">+ Add Campus</button>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Photo</th><th>Name</th><th>Code</th><th>City</th><th>Country</th>
          <th>Phone</th><th>Email</th><th>Password</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if($campuses): foreach($campuses as $i=>$c): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><img src="../<?= $c['profile_photo_path'] ?: 'assets/img/default.png' ?>" class="profile-photo"></td>
          <td><?= htmlspecialchars($c['campus_name']) ?></td>
          <td><?= htmlspecialchars($c['campus_code']) ?></td>
          <td><?= htmlspecialchars($c['city']) ?></td>
          <td><?= htmlspecialchars($c['country']) ?></td>
          <td><?= htmlspecialchars($c['phone_number']) ?></td>
          <td><?= htmlspecialchars($c['email']) ?></td>
          <td>123</td>
          <td><?= ucfirst($c['status']) ?></td>
          <td class="action-btns">
            <button class="edit-btn" onclick="openEditModal(
              <?= $c['campus_id'] ?>,
              '<?= htmlspecialchars($c['campus_name']) ?>',
              '<?= htmlspecialchars($c['campus_code']) ?>',
              '<?= htmlspecialchars($c['address']) ?>',
              '<?= htmlspecialchars($c['city']) ?>',
              '<?= htmlspecialchars($c['country']) ?>',
              '<?= htmlspecialchars($c['phone_number']) ?>',
              '<?= htmlspecialchars($c['email']) ?>',
              '<?= $c['status'] ?>'
            )"><i class="fa-solid fa-pen-to-square"></i></button>
            <button class="del-btn" onclick="openDeleteModal(<?= $c['campus_id'] ?>)"><i class="fa-solid fa-trash"></i></button>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11" style="text-align:center;color:#777;">No campuses found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2>Add Campus</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add">
      <div><label>Name</label><input name="campus_name" required></div>
      <div><label>Code</label><input name="campus_code" required></div>
      <div><label>Address</label><input name="address"></div>
      <div>
        <label>Country</label>
        <input id="country_input" name="country" oninput="autoCityOptions()" required>
      </div>
      <div>
        <label>City</label>
        <select id="city_select" name="city" required>
          <option value="">-- Select City --</option>
        </select>
      </div>
      <div><label>Phone</label><input name="phone_number"></div>
      <div><label>Email</label><input type="email" name="email"></div>
      <div><label>Photo</label><input type="file" name="profile_photo" accept="image/*"></div>
      <button class="save-btn" type="submit">Save</button>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
    <h2>Edit Campus</h2>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update">
      <input type="hidden" id="edit_id" name="campus_id">
      <div><label>Name</label><input id="edit_name" name="campus_name" required></div>
      <div><label>Code</label><input id="edit_code" name="campus_code" disabled></div>
      <div><label>Address</label><input id="edit_address" name="address"></div>
      <div>
        <label>Country</label>
        <input id="edit_country" name="country" oninput="autoCityOptionsEdit()" required>
      </div>
      <div>
        <label>City</label>
        <select id="edit_city" name="city" required>
          <option value="">-- Select City --</option>
        </select>
      </div>
      <div><label>Phone</label><input id="edit_phone" name="phone_number"></div>
      <div><label>Email</label><input id="edit_email" name="email" type="email"></div>
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
      <input type="hidden" name="campus_id" id="delete_id">
      <p>Are you sure you want to delete this campus?</p>
      <button class="save-btn" style="background:var(--red)" type="submit">Yes, Delete</button>
    </form>
  </div>
</div>

<!-- ALERT POPUP -->
<div id="popup" class="alert-popup <?= $type ?>">
  <i><?= $type==='success'?'✔️':'❌' ?></i>
  <h3><?= $message ?></h3>
</div>

<script>
// ✅ MODAL HANDLERS
function openModal(id){document.getElementById(id).classList.add('show');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('show');document.body.style.overflow='auto';}

function openEditModal(id,name,code,address,city,country,phone,email,status){
  openModal('editModal');
  edit_id.value=id;
  edit_name.value=name;
  edit_code.value=code;
  edit_address.value=address;
  edit_country.value=country;
  edit_phone.value=phone;
  edit_email.value=email;
  edit_status.value=status;

  // marka edit la furayo, buuxi select-ka cities ee wadanka
  populateCitySelect(document.getElementById('edit_city'), country, city);
}

function openDeleteModal(id){openModal('deleteModal');delete_id.value=id;}

if("<?= $message ?>"){
  const p=document.getElementById('popup');
  p.classList.add('show');
  setTimeout(()=>p.classList.remove('show'),3500);
}

// ✅ COUNTRY → CITIES MAP (JSON aad siisay)
const countryCities = {
  "Somalia": ["Mogadishu", "Hargeisa", "Kismayo", "Bosaso", "Garowe", "Baidoa"],
  "Kenya": ["Nairobi", "Mombasa", "Kisumu", "Eldoret"],
  "Ethiopia": ["Addis Ababa", "Dire Dawa", "Mekelle"],
  "South Africa": ["Cape Town", "Johannesburg", "Durban", "Pretoria", "Port Elizabeth"],
  "Egypt": ["Cairo", "Alexandria", "Giza", "Luxor"],
  "United States": ["New York", "Los Angeles", "Chicago", "Houston", "Washington", "Boston", "San Francisco"],
  "United Kingdom": ["London", "Manchester", "Liverpool", "Birmingham"],
  "China": ["Beijing", "Shanghai", "Guangzhou", "Shenzhen"],
  "India": ["Delhi", "Mumbai", "Bangalore", "Chennai", "Kolkata"],
  "France": ["Paris", "Lyon", "Marseille", "Nice", "Toulouse"],
  "Germany": ["Berlin", "Munich", "Hamburg", "Frankfurt"],
  "Brazil": ["Brasilia", "Rio de Janeiro", "Sao Paulo"],
  "Australia": ["Sydney", "Melbourne", "Perth", "Brisbane", "Adelaide"],
  "Canada": ["Toronto", "Vancouver", "Ottawa", "Montreal"],
  "Japan": ["Tokyo", "Osaka", "Kyoto", "Hiroshima"],
  "Mexico": ["Mexico City", "Guadalajara", "Monterrey"],
  "Turkey": ["Ankara", "Istanbul", "Izmir"],
  "Saudi Arabia": ["Riyadh", "Jeddah", "Mecca", "Medina"],
  "Qatar": ["Doha"],
  "United Arab Emirates": ["Dubai", "Abu Dhabi", "Sharjah"],
  "Nigeria": ["Lagos", "Abuja", "Kano"],
  "South Korea": ["Seoul", "Busan"],
  "Italy": ["Rome", "Milan", "Naples"],
  "Spain": ["Madrid", "Barcelona", "Seville"],
  "Russia": ["Moscow", "Saint Petersburg"],
  "Argentina": ["Buenos Aires", "Cordoba"],
  "Chile": ["Santiago"],
  "Colombia": ["Bogota", "Medellin"],
  "Indonesia": ["Jakarta", "Bali"],
  "Thailand": ["Bangkok"],
  "Vietnam": ["Hanoi", "Ho Chi Minh City"],
  "Philippines": ["Manila", "Cebu"],
  "Pakistan": ["Karachi", "Lahore", "Islamabad"],
  "Bangladesh": ["Dhaka", "Chittagong"],
  "Iran": ["Tehran", "Mashhad"],
  "Iraq": ["Baghdad", "Basra"],
  "Israel": ["Jerusalem", "Tel Aviv"],
  "Palestine": ["Gaza", "Ramallah"],
  "Morocco": ["Rabat", "Casablanca"],
  "Tunisia": ["Tunis"],
  "Algeria": ["Algiers", "Oran"],
  "Ghana": ["Accra", "Kumasi"],
  "Uganda": ["Kampala"],
  "Tanzania": ["Dar es Salaam", "Arusha"],
  "Sudan": ["Khartoum"],
  "Djibouti": ["Djibouti"],
  "Eritrea": ["Asmara"],
  "Zambia": ["Lusaka"],
  "Zimbabwe": ["Harare"],
  "Namibia": ["Windhoek"],
  "Rwanda": ["Kigali"],
  "Burundi": ["Bujumbura"],
  "South Sudan": ["Juba"],
  "New Zealand": ["Auckland", "Wellington"],
  "Fiji": ["Suva"]
};

// helper: hel cities dal given
function getCitiesForCountry(country){
  if(!country) return [];
  let cNorm = country.trim().toLowerCase();
  for(const key in countryCities){
    if(key.toLowerCase() === cNorm){
      return countryCities[key];
    }
  }
  return [];
}

// helper: buuxi select-ka magaalooyinka
function populateCitySelect(selectEl, country, selectedCity){
  const cities = getCitiesForCountry(country);
  selectEl.innerHTML = "";
  if(!cities.length){
    const opt = document.createElement('option');
    opt.value = "";
    opt.textContent = "No cities found";
    selectEl.appendChild(opt);
    return;
  }
  const placeholder = document.createElement('option');
  placeholder.value = "";
  placeholder.textContent = "-- Select City --";
  selectEl.appendChild(placeholder);

  cities.forEach(city=>{
    const opt = document.createElement('option');
    opt.value = city;
    opt.textContent = city;
    if(selectedCity && selectedCity.toLowerCase() === city.toLowerCase()){
      opt.selected = true;
    }
    selectEl.appendChild(opt);
  });
}

// ✅ Add form: marka country la qoro
function autoCityOptions(){
  const country = document.getElementById('country_input').value;
  const citySelect = document.getElementById('city_select');
  populateCitySelect(citySelect, country, "");
}

// ✅ Edit form: marka country la qoro
function autoCityOptionsEdit(){
  const country = document.getElementById('edit_country').value;
  const citySelect = document.getElementById('edit_city');
  populateCitySelect(citySelect, country, "");
}
</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>
