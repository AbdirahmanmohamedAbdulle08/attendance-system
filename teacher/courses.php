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
$teacher_id = $user['linked_id'] ?? null;
$teacher_name = $user['username'] ?? 'Teacher';

// ✅ Fetch subjects uu macalinku dhigayo
try {
  $stmt = $pdo->prepare("
    SELECT DISTINCT
      subj.subject_id,
      subj.subject_name,
      subj.subject_code,
      subj.credit_hours,
      subj.description,
      subj.status,
      se.semester_name,
      p.program_name,
      d.department_name,
      f.faculty_name,
      c.campus_name
    FROM timetable t
    JOIN subject subj ON t.subject_id = subj.subject_id
    JOIN class_section s ON t.section_id = s.section_id
    JOIN programs p ON subj.program_id = p.program_id
    JOIN departments d ON p.department_id = d.department_id
    JOIN faculties f ON d.faculty_id = f.faculty_id
    JOIN campus c ON f.campus_id = c.campus_id
    JOIN semester se ON subj.semester_id = se.semester_id
    WHERE t.teacher_id = ?
    ORDER BY subj.subject_name ASC
  ");
  $stmt->execute([$teacher_id]);
  $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $subjects = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Subjects | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s ease;}
.sidebar.collapsed ~ .main-content{margin-left:70px;}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;}
.page-header h1{color:var(--blue);}
.page-header p{color:#444;font-size:14px;}
.table-wrapper{background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.08);overflow:auto;max-height:550px;}
table{width:100%;border-collapse:collapse;min-width:900px;}
thead th{background:var(--blue);color:#fff;position:sticky;top:0;}
th,td{padding:12px 14px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap;}
tr:hover{background:#eef8f0;}
.status-active{color:green;font-weight:600;}
.status-inactive{color:red;font-weight:600;}
.no-data{text-align:center;color:#777;padding:20px;font-style:italic;}
@media(max-width:700px){.main-content{margin-left:0;padding:80px 10px;}}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
  <div class="page-header">
    <h1>📘 My Subjects</h1>
    <p>Welcome, <strong><?= htmlspecialchars($teacher_name) ?></strong></p>
  </div>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Subject Name</th>
          <th>Code</th>
          <th>Credit Hours</th>
          <th>Semester</th>
          <th>Program</th>
          <th>Department</th>
          <th>Faculty</th>
          <th>Campus</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if($subjects): foreach($subjects as $i=>$s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($s['subject_name']) ?></td>
          <td><?= htmlspecialchars($s['subject_code']) ?></td>
          <td><?= htmlspecialchars($s['credit_hours']) ?></td>
          <td><?= htmlspecialchars($s['semester_name']) ?></td>
          <td><?= htmlspecialchars($s['program_name']) ?></td>
          <td><?= htmlspecialchars($s['department_name']) ?></td>
          <td><?= htmlspecialchars($s['faculty_name']) ?></td>
          <td><?= htmlspecialchars($s['campus_name']) ?></td>
          <td class="status-<?= strtolower($s['status']) ?>"><?= ucfirst($s['status']) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="10" class="no-data">No subjects assigned to you yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
