<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control
$role = strtolower($_SESSION['user']['role'] ?? '');
if ($role !== 'teacher') {
  header("Location: ../login.php");
  exit;
}

$user = $_SESSION['user'] ?? [];
$teacher_id = $user['linked_id'] ?? null; // teacher uu yahay
$teacher_name = $user['username'] ?? 'Teacher';

/* =======================================================
   FETCH TIMETABLES (for this teacher only)
========================================================= */
try {
  $timetables = $pdo->prepare("
    SELECT 
      t.*, 
      s.section_name, 
      subj.subject_name,
      r.room_name,
      d.department_name,
      f.faculty_name,
      c.campus_name,
      at.term_name
    FROM timetable t
    JOIN class_section s ON t.section_id = s.section_id
    JOIN departments d ON s.department_id = d.department_id
    JOIN faculties f ON d.faculty_id  = f.faculty_id 
    JOIN campus c ON f.campus_id = c.campus_id
    JOIN subject subj ON t.subject_id = subj.subject_id
    LEFT JOIN rooms r ON t.room_id = r.room_id
    JOIN academic_term at ON t.academic_term_id = at.academic_term_id
    WHERE t.teacher_id = ?
    ORDER BY FIELD(t.day_of_week,'Sun','Mon','Tue','Wed','Thu','Fri','Sat'), t.start_time
  ");
  $timetables->execute([$teacher_id]);
  $timetables = $timetables->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $timetables = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Timetable | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s ease;}
.sidebar.collapsed ~ .main-content{margin-left:70px;}
.table-wrapper{overflow-x:auto;overflow-y:auto;max-height:550px;background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;min-width:950px;}
th,td{padding:12px 14px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap;}
thead th{background:var(--blue);color:#fff;position:sticky;top:0;z-index:2;}
tr:hover{background:#eef8f0;}
.status-active{color:green;font-weight:600;}
.status-inactive{color:red;font-weight:600;}
.header-box{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.header-box h1{color:var(--blue);}
.header-box p{color:#444;font-size:14px;}
.no-data{text-align:center;color:#777;padding:20px;font-style:italic;}
@media(max-width:700px){.main-content{margin-left:0;padding:80px 10px;}}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="header-box">
    <h1>📅 My Teaching Timetable</h1>
    <p>Welcome, <strong><?= htmlspecialchars($teacher_name) ?></strong></p>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Campus</th>
          <th>Faculty</th>
          <th>Department</th>
          <th>Section</th>
          <th>Subject</th>
          <th>Room</th>
          <th>Term</th>
          <th>Day</th>
          <th>Time</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if($timetables): foreach($timetables as $i=>$t): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($t['campus_name']) ?></td>
          <td><?= htmlspecialchars($t['faculty_name']) ?></td>
          <td><?= htmlspecialchars($t['department_name']) ?></td>
          <td><?= htmlspecialchars($t['section_name']) ?></td>
          <td><?= htmlspecialchars($t['subject_name']) ?></td>
          <td><?= htmlspecialchars($t['room_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($t['term_name']) ?></td>
          <td><?= htmlspecialchars($t['day_of_week']) ?></td>
          <td><?= htmlspecialchars(date('h:i A', strtotime($t['start_time'])) . ' - ' . date('h:i A', strtotime($t['end_time']))) ?></td>
          <td class="status-<?= strtolower($t['status']) ?>"><?= ucfirst($t['status']) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="11" class="no-data">No timetable assigned to you yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
