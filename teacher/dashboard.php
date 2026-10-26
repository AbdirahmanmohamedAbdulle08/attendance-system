<?php
/*******************************************************************************************
 * TEACHER DASHBOARD — Hormuud University
 * Version: 2025 Final UI
 * Author: GPT-5
 * Role: Teacher
 *******************************************************************************************/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'teacher') {
  header("Location: ../login.php");
  exit;
}

$teacher_id = $_SESSION['user']['linked_id'];
$teacher_name = $_SESSION['user']['username'] ?? 'Teacher';
date_default_timezone_set('Africa/Nairobi');

/* =============================== DASHBOARD DATA =============================== */

// ✅ Quick Stats
$stats = [
  'My Subjects' => $pdo->query("SELECT COUNT(DISTINCT subject_id) FROM timetable WHERE teacher_id=$teacher_id")->fetchColumn(),
  'My Classes' => $pdo->query("SELECT COUNT(DISTINCT section_id) FROM timetable WHERE teacher_id=$teacher_id")->fetchColumn(),
  'Students Taught' => $pdo->query("
      SELECT COUNT(DISTINCT s.student_id)
      FROM students s
      JOIN class_section cs ON s.section_id=cs.section_id
      JOIN timetable t ON t.section_id=cs.section_id
      WHERE t.teacher_id=$teacher_id
  ")->fetchColumn(),
  'Attendance Logs' => $pdo->query("SELECT COUNT(*) FROM teacher_attendance WHERE teacher_id=$teacher_id")->fetchColumn()
];

// ✅ Teacher IN/OUT Summary
$today = date('Y-m-d');
$attendance_today = $pdo->prepare("
  SELECT * FROM teacher_attendance WHERE teacher_id=? AND date=? LIMIT 1
");
$attendance_today->execute([$teacher_id, $today]);
$attendance_status = $attendance_today->fetch(PDO::FETCH_ASSOC);

// ✅ Today’s Timetable
$current_day = date('D');
$timetable_today = $pdo->prepare("
  SELECT t.*, s.section_name, subj.subject_name, r.room_name
  FROM timetable t
  JOIN class_section s ON t.section_id=s.section_id
  JOIN subject subj ON subj.subject_id=t.subject_id
  LEFT JOIN rooms r ON r.room_id=t.room_id
  WHERE t.teacher_id=? AND t.day_of_week=?
  ORDER BY t.start_time
");
$timetable_today->execute([$teacher_id, $current_day]);
$timetable_today = $timetable_today->fetchAll(PDO::FETCH_ASSOC);

// ✅ Announcements (latest 5)
$announcements = $pdo->query("
  SELECT title, message, created_at, image_path
  FROM announcement
  WHERE target_role IN ('teacher','all_users') AND status='active'
  ORDER BY created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

include('../includes/header.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Dashboard | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
  --green:#00843D; --blue:#0072CE; --bg:#F5F9F7;
}
body { font-family:'Poppins',sans-serif; background:var(--bg); margin:0; }
.main-content { margin-left:250px; padding:25px; margin-top:90px; transition:all .3s ease; }
.sidebar.collapsed ~ .main-content { margin-left:70px; }

/* HEADER */
.page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.page-header h1 { color:var(--blue); }
.page-header p { color:#555; font-size:14px; }

/* STATS GRID */
.stats-grid {
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
  gap:15px; margin-bottom:25px;
}
.card {
  background:#fff;
  padding:20px;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  border-top:4px solid var(--green);
  transition:.3s;
}
.card:hover { transform:translateY(-3px); }
.card h3 { font-size:14px; color:var(--blue); margin:0; text-transform:uppercase; }
.card p { font-size:26px; color:var(--green); margin:5px 0 0; font-weight:700; }

/* SECTIONS */
.section { margin-top:30px; }
.section h2 { color:var(--green); margin-bottom:10px; }

/* TABLE */
.table-wrapper {
  overflow:auto;
  background:#fff;
  border-radius:10px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
}
table { width:100%; border-collapse:collapse; min-width:850px; }
th,td { padding:10px 12px; border-bottom:1px solid #eee; text-align:left; }
thead th { background:var(--blue); color:#fff; position:sticky; top:0; }
tr:hover { background:#f3f9f5; }

/* ANNOUNCEMENTS */
.announcements { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:15px; }
.ann-card {
  background:#fff; border-radius:10px; padding:15px;
  box-shadow:0 2px 6px rgba(0,0,0,0.08);
}
.ann-card img {
  width:100%; height:150px; object-fit:cover;
  border-radius:8px; margin-bottom:10px;
}
.ann-card h4 { color:var(--blue); margin:0 0 5px; font-size:15px; }
.ann-card p { color:#555; font-size:13px; margin:0 0 10px; }
.ann-card small { color:#999; font-size:12px; }

/* RESPONSIVE */
@media(max-width:768px){
  .main-content{margin-left:0;padding:15px;}
  .stats-grid{grid-template-columns:1fr 1fr;}
}
</style>
</head>
<body>

<div class="main-content">
  <div class="page-header">
    <h1>👨‍🏫 Teacher Dashboard</h1>
    <p>Welcome back, <strong><?= htmlspecialchars($teacher_name) ?></strong></p>
  </div>

  <!-- ✅ QUICK STATS -->
  <div class="stats-grid">
    <?php foreach($stats as $label=>$value): ?>
      <div class="card">
        <h3><?= htmlspecialchars($label) ?></h3>
        <p><?= number_format($value) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ✅ TODAY’S TIMETABLE -->
  <div class="section">
    <h2>📅 Today’s Classes (<?= date('l') ?>)</h2>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>#</th><th>Section</th><th>Subject</th><th>Room</th><th>Time</th><th>Status</th></tr></thead>
        <tbody>
        <?php if($timetable_today): foreach($timetable_today as $i=>$t): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= htmlspecialchars($t['section_name']) ?></td>
            <td><?= htmlspecialchars($t['subject_name']) ?></td>
            <td><?= htmlspecialchars($t['room_name'] ?? '-') ?></td>
            <td><?= date('h:i A', strtotime($t['start_time'])) . ' - ' . date('h:i A', strtotime($t['end_time'])) ?></td>
            <td><?= ucfirst($t['status']) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="6" style="text-align:center;color:#777;">No classes scheduled for today.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ✅ ATTENDANCE STATUS -->
  <div class="section">
    <h2>🕒 My Attendance (Today)</h2>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Date</th><th>Time IN</th><th>Time OUT</th><th>Status</th></tr></thead>
        <tbody>
          <?php if($attendance_status): ?>
          <tr>
            <td><?= htmlspecialchars($attendance_status['date']) ?></td>
            <td style="color:#00843D;"><?= htmlspecialchars($attendance_status['time_in'] ?? '-') ?></td>
            <td style="color:#0072CE;"><?= htmlspecialchars($attendance_status['time_out'] ?? '-') ?></td>
            <td>
              <?php
                if($attendance_status['time_in'] && $attendance_status['time_out']) echo "<span style='color:#00843D;font-weight:bold;'>Completed</span>";
                elseif($attendance_status['time_in'] && !$attendance_status['time_out']) echo "<span style='color:#FFB400;font-weight:bold;'>In Progress</span>";
                else echo "<span style='color:#C62828;font-weight:bold;'>Not Marked</span>";
              ?>
            </td>
          </tr>
          <?php else: ?>
          <tr><td colspan="4" style="text-align:center;color:#777;">No attendance recorded yet today.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ✅ ANNOUNCEMENTS -->
  <div class="section">
    <h2>📢 Latest Announcements</h2>
    <div class="announcements">
      <?php if($announcements): foreach($announcements as $a): ?>
      <div class="ann-card">
        <?php if($a['image_path']): ?>
          <img src="../<?= htmlspecialchars($a['image_path']) ?>" alt="Announcement">
        <?php endif; ?>
        <h4><?= htmlspecialchars($a['title']) ?></h4>
        <p><?= htmlspecialchars(substr($a['message'],0,120)) ?><?= strlen($a['message'])>120?'...':'' ?></p>
        <small><i class="fa fa-calendar"></i> <?= date('M d, Y h:i A', strtotime($a['created_at'])) ?></small>
      </div>
      <?php endforeach; else: ?>
        <p style="color:#777;">No announcements at the moment.</p>
      <?php endif; ?>
    </div>
  </div>

</div>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
