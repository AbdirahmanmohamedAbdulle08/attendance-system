<?php
/*******************************************************************************************
 * HORMUUD UNIVERSITY - SUPER ADMIN REGISTRATION (FINAL LOGO FIXED VERSION)
 * Author: ChatGPT 2025 (No Remove)
 *******************************************************************************************/
session_start();
require_once __DIR__ . '/config/db_connect.php';
require_once __DIR__ . '/config/constants.php';

$message = '';
$success = false;

// ✅ Check if Super Admin exists
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='super_admin'");
$hasUser = $stmt->fetchColumn() > 0;

// ✅ Handle registration
if (!$hasUser && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = $_POST['password'];
    $photoPath = null;

    // ✅ Password validation
    $passwordValid = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,20}$/', $password);
    if (!$passwordValid) {
        $message = "❌ Password must be 8–20 characters and include uppercase, lowercase, number, and symbol.";
    } elseif (empty($_FILES['profile_photo']['name'])) {
        $message = "⚠️ Please upload a profile photo.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $role = 'super_admin';

        // ✅ Upload Profile Photo
        $uploadDir = __DIR__ . '/upload/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $photoName = uniqid('profile_') . '_' . basename($_FILES['profile_photo']['name']);
        $targetPath = $uploadDir . $photoName;

        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetPath)) {
            $photoPath = 'upload/profiles/' . $photoName;
        } else {
            $message = "⚠️ Failed to upload profile photo.";
        }

        // ✅ Continue if no upload error
        if (empty($message)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? OR email=?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                $message = "❌ Username or email already exists!";
            } else {
                // ✅ Insert Super Admin
                $user_uuid = uniqid('user_', true);
                $sql = "INSERT INTO users (
                            user_uuid, username, email, phone_number, profile_photo_path,
                            password, password_plain, role, linked_id, linked_table,
                            last_login, status, created_at, updated_at
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, 'active', NOW(), NOW()
                        )";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([
                    $user_uuid,
                    $username,
                    $email,
                    $phone,
                    $photoPath,
                    $hashedPassword,
                    $password,
                    $role
                ])){
                  // ✅ Ku dar session si profile uu u muuqdo isla markiiba
                  $_SESSION['user'] = [
                      'user_id' => $pdo->lastInsertId(),
                      'user_uuid' => $user_uuid,
                      'username' => $username,
                      'email' => $email,
                      'phone_number' => $phone,
                      'profile_photo_path' => $photoPath,
                      'role' => $role,
                      'status' => 'active'
                  ];
                  $success = true;
              }else {
                    $message = "⚠️ Error registering Super Admin.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Super Admin Registration | Hormuud University</title>

<!-- ✅ Favicon -->
<link rel="icon" type="image/png" href="images.png">

<!-- ✅ Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --hu-green: #00843D;
  --hu-blue: #0072CE;
  --hu-light-green: #00A651;
  --hu-light-gray: #F5F9F7;
  --hu-dark-gray: #333333;
  --hu-danger: #C62828;
  --hu-warning: #FFB400;
  --hu-white: #FFFFFF;
}

*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{
  background:var(--hu-light-gray);
  color:var(--hu-dark-gray);
  display:flex;align-items:center;justify-content:center;
  height:100vh;
}
.container{
  display:flex;width:950px;max-width:95%;
  background:var(--hu-white);
  border-radius:12px;
  box-shadow:0 6px 20px rgba(0,0,0,0.15);
  overflow:hidden;
}
.left-panel{
  background:linear-gradient(135deg,var(--hu-blue),var(--hu-green));
  color:var(--hu-white);
  flex:1;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  padding:40px;
}
.left-panel img{
  width:130px;
  height:auto;
  margin-bottom:20px;
  background:#fff;
  padding:8px;
  border-radius:12px;
  box-shadow:0 0 15px rgba(255,255,255,0.4);
}
.left-panel h1{font-size:26px;font-weight:700;text-align:center;}
.left-panel p{margin-top:10px;opacity:0.9;text-align:center;}
.right-panel{flex:1.2;padding:40px 35px;background:var(--hu-white);}
.right-panel h2{font-size:24px;color:var(--hu-blue);margin-bottom:8px;}
.right-panel h3{font-size:16px;margin-bottom:25px;color:gray;}
.error-msg{
  background:#ffeaea;color:var(--hu-danger);
  padding:10px;border-radius:6px;margin-bottom:15px;
  text-align:center;font-weight:500;
}
form{display:flex;flex-direction:column;gap:15px;}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
input[type=text],input[type=email],input[type=password],input[type=file]{
  padding:10px;border:1px solid #ccc;border-radius:6px;width:100%;
}
button{
  background:var(--hu-blue);color:var(--hu-white);
  border:none;padding:12px;border-radius:6px;
  font-size:16px;font-weight:600;cursor:pointer;transition:.3s;
}
button:hover{background:var(--hu-green);}
.upload-section{margin:10px 0;}
.upload-section label{font-weight:600;color:var(--hu-dark-gray);}
.password-meter{
  margin-top:-5px;margin-bottom:10px;display:flex;align-items:center;gap:10px;
}
.password-strength{
  height:8px;flex:1;border-radius:4px;background:#ddd;overflow:hidden;
}
.password-strength div{height:100%;transition:width .3s;}
#strengthLabel{font-size:13px;font-weight:600;}
.overlay{position:fixed;inset:0;background:rgba(0,0,0,0.4);}
.alert-center{
  position:fixed;top:50%;left:50%;
  transform:translate(-50%,-50%);
  background:var(--hu-white);
  padding:30px;border-radius:12px;
  box-shadow:0 4px 12px rgba(0,0,0,0.3);
  text-align:center;width:300px;
}
.circle{
  width:80px;height:80px;border-radius:50%;
  border:4px solid var(--hu-green);margin:0 auto;position:relative;
}
.checkmark{
  width:25px;height:50px;
  border-right:4px solid var(--hu-green);
  border-bottom:4px solid var(--hu-green);
  position:absolute;left:26px;top:10px;
  transform:rotate(45deg) scale(0);
  transition:transform .6s ease;
}
.circle.animate .checkmark{transform:rotate(45deg) scale(1);}
@media(max-width:768px){
  .container{flex-direction:column;height:auto;margin:20px;}
  .left-panel{padding:20px;}
}
</style>
</head>
<body>

<div class="container">
  <div class="left-panel">
      <!-- ✅ Local Logo (store file at /assets/img/hu_logo.png) -->
      <img src="images.png" alt="Hormuud University Logo">
      <h1>Hormuud University</h1>
      <p>Attendance Management System<br><strong>Super Administrator Registration</strong></p>
  </div>

  <div class="right-panel">
      <h2>Register Super Admin</h2>
      <h3>First-Time System Setup</h3>

      <?php if (!empty($message)): ?>
          <p class="error-msg"><?= htmlspecialchars($message); ?></p>
      <?php endif; ?>

      <?php if (!$hasUser && !$success): ?>
      <form method="POST" enctype="multipart/form-data" novalidate>
          <div class="form-grid">
              <input type="text" name="username" placeholder="Username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
              <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
              <input type="text" name="phone" placeholder="Phone Number" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
              <input type="password" name="password" id="password" placeholder="Password (8–20 chars)" required>
          </div>

          <!-- ✅ Password strength meter -->
          <div class="password-meter">
              <div class="password-strength"><div id="strengthBar"></div></div>
              <span id="strengthLabel">Weak</span>
          </div>

          <div class="upload-section">
              <label for="profile_photo">Upload Profile Photo</label>
              <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required>
          </div>

          <button type="submit">Register Super Admin</button>
      </form>

      <?php elseif ($hasUser && !$success): ?>
          <p class="error-msg">⚠️ Super Admin already exists. Registration disabled.</p>
          <p style="text-align:center;margin-top:10px;"><a href="login.php" style="color:var(--hu-blue);font-weight:600;">Go to Login</a></p>
      <?php endif; ?>
  </div>
</div>

<?php if ($success): ?>
<div class="overlay"></div>
<div class="alert-center success">
  <div class="circle"><div class="checkmark"></div></div>
  <p id="success-text" style="opacity:0;">✅ Registration Successful!<br>Redirecting to Login...</p>
</div>
<script>
setTimeout(()=>document.querySelector('.circle').classList.add('animate'),600);
setTimeout(()=>document.getElementById('success-text').style.opacity=1,1200);
setTimeout(()=>window.location.href="login.php",3000);
</script>
<?php endif; ?>

<script>
// ✅ Password Strength Meter (Official Colors)
const pwd=document.getElementById('password');
const bar=document.getElementById('strengthBar');
const label=document.getElementById('strengthLabel');

pwd.addEventListener('input',()=>{
  const val=pwd.value;
  let strength=0;
  if(val.match(/[a-z]/))strength+=1;
  if(val.match(/[A-Z]/))strength+=1;
  if(val.match(/\d/))strength+=1;
  if(val.match(/[\W_]/))strength+=1;
  if(val.length>=8)strength+=1;

  const width=(strength/5)*100;
  bar.style.width=width+'%';

  if(width<=40){
    bar.style.background='#C62828'; // Red
    label.textContent='Weak';
    label.style.color='#C62828';
  } else if(width<=70){
    bar.style.background='#FFB400'; // Amber
    label.textContent='Medium';
    label.style.color='#FFB400';
  } else {
    bar.style.background='#00A651'; // Light Green
    label.textContent='Strong';
    label.style.color='#00A651';
  }
});
</script>

</body>
</html>
