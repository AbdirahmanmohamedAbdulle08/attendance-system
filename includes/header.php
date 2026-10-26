<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Redirect if not logged in
if (empty($_SESSION['user'])) {
  header("Location: ../login.php");
  exit;
}

// ✅ User Info
$user  = $_SESSION['user'];
$role  = strtolower(str_replace(' ', '_', $user['role'] ?? ''));
$name  = htmlspecialchars($user['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($user['phone_number'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$photo = !empty($user['profile_photo_path'])
  ? "../" . htmlspecialchars($user['profile_photo_path'], ENT_QUOTES, 'UTF-8')
  : "../upload/profiles/default.png";

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Hormuud University | <?= ucfirst(str_replace('.php', '', $currentPage)) ?></title>

<link rel="icon" type="image/png" href="../images.png">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
/* ---- CSS ORIGINAL (unchanged) ---- */
:root {
  --hu-green: #009639;
  --hu-blue: #0072CE;
  --hu-white: #ffffff;
  --hu-light: #f8fafc;
  --text-dark: #222;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Poppins',sans-serif;}
body{background:var(--hu-light);color:var(--text-dark);overflow-x:hidden;}
a{text-decoration:none;color:inherit;}
.hu-header{display:flex;align-items:center;justify-content:space-between;background:var(--hu-blue);padding:10px 20px;color:white;position:fixed;top:0;left:0;right:0;height:70px;z-index:1000;box-shadow:0 2px 5px rgba(0,0,0,0.1);}
.hu-left{display:flex;align-items:center;gap:15px;}
.menu-btn{background:none;border:none;color:white;font-size:22px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.logo-circle{background:white;border-radius:50%;padding:5px;display:flex;align-items:center;justify-content:center;box-shadow:0 0 5px rgba(0,0,0,0.1);}
.hu-logo{height:42px;width:42px;object-fit:contain;}
.hu-title{font-weight:600;font-size:20px;letter-spacing:0.5px;}
.hu-right{display:flex;align-items:center;gap:20px;}
.user-info{display:flex;align-items:center;gap:10px;cursor:pointer;}
.user-photo{width:42px;height:42px;border-radius:50%;border:2px solid white;object-fit:cover;}
.logout-btn{background:red;color:var(--hu-white);padding:7px 15px;border-radius:6px;font-weight:500;transition:0.3s;}
.logout-btn:hover{background:var(--hu-white);color:red;}
.sidebar{position:fixed;top:70px;left:0;width:250px;height:calc(100% - 70px);background:var(--hu-green);color:white;overflow-y:auto;transition:width 0.3s ease;padding:15px 0;}
.sidebar::-webkit-scrollbar{width:6px;}
.sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.4);border-radius:3px;}
.sidebar ul{list-style:none;}
.sidebar li{border-top:1px solid rgba(255,255,255,0.1);}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px 20px;color:white;font-weight:500;border-left:5px solid transparent;transition:all 0.3s ease;}
.sidebar a i{width:20px;text-align:center;}
.sidebar a:hover, .sidebar a.active{background:white;color:var(--hu-blue);border-left:5px solid var(--hu-blue);}
.sidebar .submenu{display:none;background:rgba(0,0,0,0.1);}
.sidebar .submenu a{padding-left:45px;font-size:14px;}
.sidebar .menu-toggle{cursor:pointer;display:flex;justify-content:space-between;align-items:center;padding:12px 20px;font-weight:500;}
.sidebar .menu-toggle:hover{background:rgba(255,255,255,0.1);}
.sidebar .menu-toggle i{transition:0.3s;}
.sidebar .open i{transform:rotate(90deg);}
.sidebar.collapsed{width:70px;}
.sidebar.collapsed a span,.sidebar.collapsed .menu-toggle span{display:none;}
.sidebar.collapsed .submenu{display:none !important;}
.sidebar.collapsed a{justify-content:center;}
.sidebar.collapsed .menu-toggle{justify-content:center;}
.profile-modal{display:none;justify-content:center;align-items:center;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;}
.profile-content{background:white;border-radius:10px;padding:25px;text-align:center;width:320px;box-shadow:0 0 15px rgba(0,0,0,0.2);position:relative;}
.profile-avatar{width:90px;height:90px;border-radius:50%;border:3px solid var(--hu-green);object-fit:cover;}
.close-profile{position:absolute;top:10px;right:15px;font-size:22px;color:var(--hu-blue);cursor:pointer;}
.profile-links{margin-top:20px;display:flex;flex-direction:column;gap:10px;}
.profile-link{background:var(--hu-blue);color:white;border:none;padding:10px;border-radius:6px;cursor:pointer;transition:0.3s;}
.profile-link:hover{background:var(--hu-green);}
.overlay{display:none;justify-content:center;align-items:center;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:3000;}
.overlay-content{background:white;border-radius:8px;width:90%;height:90%;position:relative;}
.close-overlay{position:absolute;top:10px;right:20px;font-size:26px;color:var(--hu-blue);cursor:pointer;}
.overlay-frame{width:100%;height:100%;border:none;border-radius:8px;}
@media(max-width:768px){.hu-title{display:none;}.sidebar{width:220px;}}
</style>
</head>
<body>

<header class="hu-header">
  <div class="hu-left">
    <button id="sidebarToggle" class="menu-btn" aria-label="Toggle Sidebar">
      <i class="fa-solid fa-bars" id="toggleIcon"></i>
    </button>
    <div class="logo-circle">
      <img src="https://hu.edu.so/static/img/public/hu_official_logo_without_text-and_bg_png.png" class="hu-logo" alt="HU Logo">
    </div>
    <span class="hu-title">Hormuud University</span>
  </div>
  <div class="hu-right">
    <div class="user-info" id="profileOpen">
      <img src="<?= $photo ?>" alt="Profile" class="user-photo">
      <div>
        <span style="font-weight:600;"><?= ucfirst($name) ?></span><br>
        <small><?= ucfirst(str_replace('_',' ',$role)) ?></small>
      </div>
    </div>
    <a href="../logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
  </div>
</header>

<!-- ✅ SIDEBAR NAVIGATION -->
<nav class="sidebar" id="sidebar">
  <ul>
<?php
/* ===========================================================
   SUPER ADMIN
   =========================================================== */
if ($role === 'super_admin') {
?>
    <li><a href="../super_admin/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>

    <!-- University -->
    <li>
      <div class="menu-toggle" data-target="uniSuper">
        <span><i class="fa-solid fa-university"></i> University</span>
        <i class="fa-solid fa-chevron-right"></i>
      </div>
      <ul class="submenu" id="uniSuper">
        <li><a href="../super_admin/campus.php"><i class="fa-solid fa-building-columns"></i> Campus</a></li>
        <li><a href="../super_admin/faculty.php"><i class="fa-solid fa-graduation-cap"></i> Faculty</a></li>
        <li><a href="../super_admin/department.php"><i class="fa-solid fa-building"></i> Department</a></li>
        <li><a href="../super_admin/rooms.php"><i class="fa-solid fa-door-open"></i> Rooms</a></li>
        <li><a href="../super_admin/room_allocations.php"><i class="fa-solid fa-clipboard-list"></i> Room Allocation</a></li>
      </ul>
    </li>

    <!-- Academic -->
    <li>
      <div class="menu-toggle" data-target="acadSuper">
        <span><i class="fa-solid fa-book-open"></i> Academic</span>
        <i class="fa-solid fa-chevron-right"></i>
      </div>
      <ul class="submenu" id="acadSuper">
        <li><a href="../super_admin/academic_years.php"><i class="fa-regular fa-calendar"></i> Academic Years</a></li>
        <li><a href="../super_admin/academic_terms.php"><i class="fa-solid fa-calendar-week"></i> Academic Terms</a></li>
        <li><a href="../super_admin/semesters.php"><i class="fa-solid fa-book"></i> Semesters</a></li>
        <li><a href="../super_admin/programsa.php"><i class="fa-solid fa-layer-group"></i> Programs</a></li>
        <li><a href="../super_admin/sections.php"><i class="fa-solid fa-diagram-project"></i> Sections</a></li>
        <li><a href="../super_admin/courses.php"><i class="fa-solid fa-bookmark"></i> Courses</a></li>
        <li><a href="../super_admin/student_enroll.php"><i class="fa-solid fa-user-plus"></i> Student Enroll</a></li>
        <li><a href="../super_admin/timetable.php"><i class="fa-solid fa-table"></i> Timetable</a></li>
        <li><a href="../super_admin/promotion_history.php"><i class="fa-solid fa-arrow-up"></i> Promotion</a></li>
        <li><a href="../super_admin/attendance.php"><i class="fa-solid fa-clock"></i> Attendance</a></li>
      </ul>
    </li>

    <!-- People -->
    <li>
      <div class="menu-toggle" data-target="peopleSuper">
        <span><i class="fa-solid fa-users"></i> People</span>
        <i class="fa-solid fa-chevron-right"></i>
      </div>
      <ul class="submenu" id="peopleSuper">
        <li><a href="../super_admin/teachers.php"><i class="fa-solid fa-user-tie"></i> Teachers</a></li>
        <li><a href="../super_admin/students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
        <li><a href="../super_admin/parents.php"><i class="fa-solid fa-user-group"></i> Parents</a></li>
        <li><a href="../super_admin/users.php"><i class="fa-solid fa-user-shield"></i> User Accounts</a></li>
      </ul>
    </li>

    <!-- Reports -->
    <li>
      <div class="menu-toggle" data-target="reportSuper">
        <span><i class="fa-solid fa-chart-line"></i> Reports</span>
        <i class="fa-solid fa-chevron-right"></i>
      </div>
      <ul class="submenu" id="reportSuper">
        <li><a href="../super_admin/attendance_report.php"><i class="fa-solid fa-clock"></i> Attendance Report</a></li>
        <li><a href="../super_admin/promotion_report.php"><i class="fa-solid fa-arrow-trend-up"></i> Promotions</a></li>
        <li><a href="../super_admin/reports.php"><i class="fa-solid fa-chart-pie"></i> Reports Overview</a></li>
      </ul>
    </li>

    <li><a href="../super_admin/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>

    <!-- Settings -->
    <li>
      <div class="menu-toggle" data-target="settingSuper">
        <span><i class="fa-solid fa-gear"></i> Settings</span>
        <i class="fa-solid fa-chevron-right"></i>
      </div>
      <ul class="submenu" id="settingSuper">
        <li><a href="../super_admin/notification.php"><i class="fa-solid fa-bell"></i> Notifications</a></li>
        <li><a href="../super_admin/audit_logs.php"><i class="fa-solid fa-file-invoice"></i> Audit Logs</a></li>
      </ul>
    </li>

<?php
/* ===========================================================
   CAMPUS ADMIN
   =========================================================== */
} elseif ($role === 'campus_admin') {
  ?>
  <li><a href="../campus_admin/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>

  <!-- University -->
  <li>
    <div class="menu-toggle" data-target="uniSuper">
      <span><i class="fa-solid fa-university"></i> University</span>
      <i class="fa-solid fa-chevron-right"></i>
    </div>
    <ul class="submenu" id="uniSuper">
      <li><a href="../campus_admin/faculty.php"><i class="fa-solid fa-graduation-cap"></i> Faculty</a></li>
      <li><a href="../campus_admin/department.php"><i class="fa-solid fa-building"></i> Department</a></li>
      <li><a href="../campus_admin/rooms.php"><i class="fa-solid fa-door-open"></i> Rooms</a></li>
      <li><a href="../campus_admin/room_allocations.php"><i class="fa-solid fa-clipboard-list"></i> Room Allocation</a></li>
    </ul>
  </li>

  <!-- Academic -->
  <li>
    <div class="menu-toggle" data-target="acadSuper">
      <span><i class="fa-solid fa-book-open"></i> Academic</span>
      <i class="fa-solid fa-chevron-right"></i>
    </div>
    <ul class="submenu" id="acadSuper">
      <li><a href="../campus_admin/academic_years.php"><i class="fa-regular fa-calendar"></i> Academic Years</a></li>
      <li><a href="../campus_admin/academic_terms.php"><i class="fa-solid fa-calendar-week"></i> Academic Terms</a></li>
      <li><a href="../campus_admin/semesters.php"><i class="fa-solid fa-book"></i> Semesters</a></li>
      <li><a href="../campus_admin/programsa.php"><i class="fa-solid fa-layer-group"></i> Programs</a></li>
      <li><a href="../campus_admin/sections.php"><i class="fa-solid fa-diagram-project"></i> Sections</a></li>
      <li><a href="../campus_admin/courses.php"><i class="fa-solid fa-bookmark"></i> Courses</a></li>
      <li><a href="../campus_admin/student_enroll.php"><i class="fa-solid fa-user-plus"></i> Student Enroll</a></li>
      <li><a href="../campus_admin/timetable.php"><i class="fa-solid fa-table"></i> Timetable</a></li>
      <li><a href="../campus_admin/promotion_history.php"><i class="fa-solid fa-arrow-up"></i> Promotion</a></li>
      <li><a href="../campus_admin/attendance.php"><i class="fa-solid fa-clock"></i> Attendance</a></li>
    </ul>
  </li>

  <!-- People -->
  <li>
    <div class="menu-toggle" data-target="peopleSuper">
      <span><i class="fa-solid fa-users"></i> People</span>
      <i class="fa-solid fa-chevron-right"></i>
    </div>
    <ul class="submenu" id="peopleSuper">
      <li><a href="../campus_admin/teachers.php"><i class="fa-solid fa-user-tie"></i> Teachers</a></li>
      <li><a href="../campus_admin/students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
      <li><a href="../campus_admin/parents.php"><i class="fa-solid fa-user-group"></i> Parents</a></li>
      <li><a href="../campus_admin/users.php"><i class="fa-solid fa-user-shield"></i> User Accounts</a></li>
    </ul>
  </li>

  <!-- Reports -->
  <li>
    <div class="menu-toggle" data-target="reportSuper">
      <span><i class="fa-solid fa-chart-line"></i> Reports</span>
      <i class="fa-solid fa-chevron-right"></i>
    </div>
    <ul class="submenu" id="reportSuper">
      <li><a href="../campus_admin/attendance_report.php"><i class="fa-solid fa-clock"></i> Attendance Report</a></li>
      <li><a href="../campus_admin/promotion_report.php"><i class="fa-solid fa-arrow-trend-up"></i> Promotions</a></li>
      <li><a href="../campus_admin/reports.php"><i class="fa-solid fa-chart-pie"></i> Reports Overview</a></li>
    </ul>
  </li>

  <li><a href="../campus_admin/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>

  <!-- Settings -->
  <li>
    <div class="menu-toggle" data-target="settingSuper">
      <span><i class="fa-solid fa-gear"></i> Settings</span>
      <i class="fa-solid fa-chevron-right"></i>
    </div>
    <ul class="submenu" id="settingSuper">
      <li><a href="../campus_admin/notification.php"><i class="fa-solid fa-bell"></i> Notifications</a></li>
      <li><a href="../campus_admin/audit_logs.php"><i class="fa-solid fa-file-invoice"></i> Audit Logs</a></li>
    </ul>
  </li>

<?php
/* ===========================================================
   FACULTY ADMIN
   =========================================================== */
} elseif ($role === 'faculty_admin') {
    ?>
        <li><a href="../faculty_admin/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
    
        <!-- University -->
        <li>
          <div class="menu-toggle" data-target="uniSuper">
            <span><i class="fa-solid fa-university"></i> University</span>
            <i class="fa-solid fa-chevron-right"></i>
          </div>
          <ul class="submenu" id="uniSuper">
            <li><a href="../faculty_admin/department.php"><i class="fa-solid fa-building"></i> Department</a></li>
            <li><a href="../faculty_admin/rooms.php"><i class="fa-solid fa-door-open"></i> Rooms</a></li>
            <li><a href="../faculty_admin/room_allocations.php"><i class="fa-solid fa-clipboard-list"></i> Room Allocation</a></li>
          </ul>
        </li>
    
        <!-- Academic -->
        <li>
          <div class="menu-toggle" data-target="acadSuper">
            <span><i class="fa-solid fa-book-open"></i> Academic</span>
            <i class="fa-solid fa-chevron-right"></i>
          </div>
          <ul class="submenu" id="acadSuper">
            <li><a href="../faculty_admin/academic_years.php"><i class="fa-regular fa-calendar"></i> Academic Years</a></li>
            <li><a href="../faculty_admin/academic_terms.php"><i class="fa-solid fa-calendar-week"></i> Academic Terms</a></li>
            <li><a href="../faculty_admin/semesters.php"><i class="fa-solid fa-book"></i> Semesters</a></li>
            <li><a href="../faculty_admin/programsa.php"><i class="fa-solid fa-layer-group"></i> Programs</a></li>
            <li><a href="../faculty_admin/sections.php"><i class="fa-solid fa-diagram-project"></i> Sections</a></li>
            <li><a href="../faculty_admin/courses.php"><i class="fa-solid fa-bookmark"></i> Courses</a></li>
            <li><a href="../faculty_admin/student_enrol.php"><i class="fa-solid fa-user-plus"></i> Student Enroll</a></li>
            <li><a href="../faculty_admin/timetable.php"><i class="fa-solid fa-table"></i> Timetable</a></li>
            <li><a href="../faculty_admin/promotion_history.php"><i class="fa-solid fa-arrow-up"></i> Promotion</a></li>
            <li><a href="../faculty_admin/attendance.php"><i class="fa-solid fa-clock"></i> Attendance</a></li>
          </ul>
        </li>
    
        <!-- People -->
        <li>
          <div class="menu-toggle" data-target="peopleSuper">
            <span><i class="fa-solid fa-users"></i> People</span>
            <i class="fa-solid fa-chevron-right"></i>
          </div>
          <ul class="submenu" id="peopleSuper">
            <li><a href="../faculty_admin/teachers.php"><i class="fa-solid fa-user-tie"></i> Teachers</a></li>
            <li><a href="../faculty_admin/students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
            <li><a href="../faculty_admin/parents.php"><i class="fa-solid fa-user-group"></i> Parents</a></li>
            <li><a href="../faculty_admin/users.php"><i class="fa-solid fa-user-shield"></i> User Accounts</a></li>
          </ul>
        </li>
    
        <!-- Reports -->
        <li>
          <div class="menu-toggle" data-target="reportSuper">
            <span><i class="fa-solid fa-chart-line"></i> Reports</span>
            <i class="fa-solid fa-chevron-right"></i>
          </div>
          <ul class="submenu" id="reportSuper">
            <li><a href="../faculty_admin/attendance_report.php"><i class="fa-solid fa-clock"></i> Attendance Report</a></li>
            <li><a href="../faculty_admin/promotion_report.php"><i class="fa-solid fa-arrow-trend-up"></i> Promotions</a></li>
            <li><a href="../faculty_admin/reports.php"><i class="fa-solid fa-chart-pie"></i> Reports Overview</a></li>
          </ul>
        </li>
    
        <li><a href="../faculty_admin/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>
    
        <!-- Settings -->
        <li>
          <div class="menu-toggle" data-target="settingSuper">
            <span><i class="fa-solid fa-gear"></i> Settings</span>
            <i class="fa-solid fa-chevron-right"></i>
          </div>
          <ul class="submenu" id="settingSuper">
            <li><a href="../faculty_admin/notification.php"><i class="fa-solid fa-bell"></i> Notifications</a></li>
          </ul>
        </li>
    
    <?php
/* ===========================================================
   DEPARTMENT ADMIN
   =========================================================== */
} elseif ($role === 'department_admin') {
  ?>
      <li><a href="../department_admin/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
  
      <!-- University -->
      <li>
        <div class="menu-toggle" data-target="uniSuper">
          <span><i class="fa-solid fa-university"></i> University</span>
          <i class="fa-solid fa-chevron-right"></i>
        </div>
        <ul class="submenu" id="uniSuper">
          <li><a href="../department_admin/rooms.php"><i class="fa-solid fa-door-open"></i> Rooms</a></li>
          <li><a href="../department_admin/room_allocations.php"><i class="fa-solid fa-clipboard-list"></i> Room Allocation</a></li>
        </ul>
      </li>
  
      <!-- Academic -->
      <li>
        <div class="menu-toggle" data-target="acadSuper">
          <span><i class="fa-solid fa-book-open"></i> Academic</span>
          <i class="fa-solid fa-chevron-right"></i>
        </div>
        <ul class="submenu" id="acadSuper">
          <li><a href="../department_admin/academic_year.php"><i class="fa-regular fa-calendar"></i> Academic Years</a></li>
          <li><a href="../department_admin/academic_term.php"><i class="fa-solid fa-calendar-week"></i> Academic Terms</a></li>
          <li><a href="../department_admin/semester.php"><i class="fa-solid fa-book"></i> Semesters</a></li>
          <li><a href="../department_admin/programsa.php"><i class="fa-solid fa-layer-group"></i> Programs</a></li>
          <li><a href="../department_admin/sections.php"><i class="fa-solid fa-diagram-project"></i> Sections</a></li>
          <li><a href="../department_admin/courses.php"><i class="fa-solid fa-bookmark"></i> Courses</a></li>
          <li><a href="../department_admin/student_enrol.php"><i class="fa-solid fa-user-plus"></i> Student Enroll</a></li>
          <li><a href="../department_admin/timetable.php"><i class="fa-solid fa-table"></i> Timetable</a></li>
          <li><a href="../department_admin/promotion_history.php"><i class="fa-solid fa-arrow-up"></i> Promotion</a></li>
          <li><a href="../department_admin/attendance.php"><i class="fa-solid fa-clock"></i> Attendance</a></li>
        </ul>
      </li>
  
      <!-- People -->
      <li>
        <div class="menu-toggle" data-target="peopleSuper">
          <span><i class="fa-solid fa-users"></i> People</span>
          <i class="fa-solid fa-chevron-right"></i>
        </div>
        <ul class="submenu" id="peopleSuper">
          <li><a href="../department_admin/teachers.php"><i class="fa-solid fa-user-tie"></i> Teachers</a></li>
          <li><a href="../department_admin/students.php"><i class="fa-solid fa-user-graduate"></i> Students</a></li>
          <li><a href="../department_admin/parents.php"><i class="fa-solid fa-user-group"></i> Parents</a></li>
          <li><a href="../department_admin/users.php"><i class="fa-solid fa-user-shield"></i> User Accounts</a></li>
        </ul>
      </li>
  
      <!-- Reports -->
      <li>
        <div class="menu-toggle" data-target="reportSuper">
          <span><i class="fa-solid fa-chart-line"></i> Reports</span>
          <i class="fa-solid fa-chevron-right"></i>
        </div>
        <ul class="submenu" id="reportSuper">
          <li><a href="../department_admin/attendance_report.php"><i class="fa-solid fa-clock"></i> Attendance Report</a></li>
          <li><a href="../department_admin/promotion_report.php"><i class="fa-solid fa-arrow-trend-up"></i> Promotions</a></li>
          <li><a href="../department_admin/reports.php"><i class="fa-solid fa-chart-pie"></i> Reports Overview</a></li>
        </ul>
      </li>
  
      <li><a href="../department_admin/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>
  
      <!-- Settings -->
      <li>
        <div class="menu-toggle" data-target="settingSuper">
          <span><i class="fa-solid fa-gear"></i> Settings</span>
          <i class="fa-solid fa-chevron-right"></i>
        </div>
        <ul class="submenu" id="settingSuper">
          <li><a href="../department_admin/notification.php"><i class="fa-solid fa-bell"></i> Notifications</a></li>
        </ul>
      </li>
  
  <?php
/* ===========================================================
   TEACHER
   =========================================================== */
} elseif ($role === 'teacher') {
?>
    <li><a href="../teacher/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
    <li><a href="../teacher/courses.php"><i class="fa-solid fa-bookmark"></i><span>Courses</span></a></li>
    <li><a href="../teacher/timetable.php"><i class="fa-solid fa-table"></i><span>Timetable</span></a></li>
    <li><a href="../teacher/attendance.php"><i class="fa-solid fa-clock"></i><span>Attendance</span></a></li>
    <li><a href="../teacher/reports.php"><i class="fa-solid fa-chart-pie"></i><span>Reports</span></a></li>
    <li><a href="../teacher/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>

<?php
/* ===========================================================
   STUDENT
   =========================================================== */
} elseif ($role === 'student') {
?>
    <li><a href="../student/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
    <li><a href="../student/courses.php"><i class="fa-solid fa-bookmark"></i><span>Courses</span></a></li>
    <li><a href="../student/timetable.php"><i class="fa-solid fa-table"></i><span>Timetable</span></a></li>
    <li><a href="../student/attendance.php"><i class="fa-solid fa-clock"></i><span>Attendance</span></a></li>
    <li><a href="../student/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>

<?php
/* ===========================================================
   PARENT
   =========================================================== */
} elseif ($role === 'parent') {
?>
    <li><a href="../parent/dashboard.php" class="<?= $currentPage=='dashboard.php'?'active':'' ?>"><i class="fa-solid fa-house"></i><span>Dashboard</span></a></li>
    <li><a href="../parent/student_progress.php"><i class="fa-solid fa-user-graduate"></i><span>Student Progress</span></a></li>
    <li><a href="../parent/attendance.php"><i class="fa-solid fa-clock"></i><span>Attendance</span></a></li>
    <li><a href="../parent/reports.php"><i class="fa-solid fa-chart-pie"></i><span>Reports</span></a></li>
    <li><a href="../parent/announcements.php"><i class="fa-solid fa-bullhorn"></i><span>Announcements</span></a></li>

<?php
/* ===========================================================
   UNKNOWN ROLE
   =========================================================== */
} else {
?>
    <li><a href="#"><i class="fa-solid fa-ban"></i><span>No Menu Assigned</span></a></li>
<?php } ?>
  </ul>
</nav>


<!-- ✅ PROFILE MODAL + JS same as original -->
<div class="profile-modal" id="profileModal">
  <div class="profile-content">
    <span class="close-profile" id="closeProfile">&times;</span>
    <img src="<?= $photo ?>" alt="Profile" class="profile-avatar">
    <h2><?= ucfirst($name) ?></h2>
    <p style="font-size:14px;color:gray;margin-top:5px;"><?= $email ?><br><?= $phone ?></p>
    <div class="profile-links">
      <button class="profile-link" id="viewProfileBtn"><i class="fa-solid fa-user"></i> View Profile</button>
      <button class="profile-link" id="changePasswordBtn"><i class="fa-solid fa-lock"></i> Change Password</button>
    </div>
  </div>
</div>

<div class="overlay" id="overlayView">
  <div class="overlay-content">
    <span class="close-overlay" data-close="overlayView">&times;</span>
    <iframe src="../config/view_profile.php" class="overlay-frame"></iframe>
  </div>
</div>

<div class="overlay" id="overlayPassword">
  <div class="overlay-content">
    <span class="close-overlay" data-close="overlayPassword">&times;</span>
    <iframe src="../config/change_password.php" class="overlay-frame"></iframe>
  </div>
</div>

<script>
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("sidebarToggle");
const toggleIcon = document.getElementById("toggleIcon");

toggleBtn.onclick = () => {
  sidebar.classList.toggle("collapsed");
  if (sidebar.classList.contains("collapsed")) {
    toggleIcon.classList.replace("fa-bars", "fa-xmark");
  } else {
    toggleIcon.classList.replace("fa-xmark", "fa-bars");
  }
};

document.querySelectorAll(".menu-toggle").forEach(toggle=>{
  toggle.onclick = ()=>{
    const submenu = document.getElementById(toggle.dataset.target);
    submenu.style.display = submenu.style.display==="block"?"none":"block";
    toggle.classList.toggle("open");
  };
});

const profileModal = document.getElementById("profileModal");
document.getElementById("profileOpen").onclick = ()=>{
  profileModal.style.display="flex";
  document.body.style.overflow="hidden";
};
document.getElementById("closeProfile").onclick = ()=>{
  profileModal.style.display="none";
  document.body.style.overflow="auto";
};

document.querySelectorAll(".close-overlay").forEach(btn=>{
  btn.onclick = e=>{
    const id=e.target.getAttribute("data-close");
    document.getElementById(id).style.display="none";
    document.body.style.overflow="auto";
  };
});

document.getElementById("viewProfileBtn").onclick=()=>{
  profileModal.style.display="none";
  document.getElementById("overlayView").style.display="flex";
  document.body.style.overflow="hidden";
};
document.getElementById("changePasswordBtn").onclick=()=>{
  profileModal.style.display="none";
  document.getElementById("overlayPassword").style.display="flex";
  document.body.style.overflow="hidden";
};
</script>

</body>
</html>
