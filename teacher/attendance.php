<?php
/*******************************************************************************************
 * ATTENDANCE MANAGEMENT — Hormuud University
 * Teacher Panel (Real-Time Timetable Filter)
 * PHP 8.2 | PDO Secure Queries | UI Integrated Version
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'teacher') {
  session_destroy();
  header("Location: ../login.php?error=unauthorized");
  exit;
}

$teacher_id = $_SESSION['user']['linked_id'] ?? null;
$teacher_name = $_SESSION['user']['username'] ?? 'Teacher';
date_default_timezone_set('Africa/Nairobi');

$message = "";
$type = "";

/* ================= ACTIVE TERM ================= */
$term = $pdo->query("SELECT academic_term_id FROM academic_term WHERE status='active' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$academic_term_id = $term['academic_term_id'] ?? null;
if (!$academic_term_id) {
  $message = "❌ Error: No active academic term found!";
  $type = "error";
}

/* ================= HANDLE STUDENT ATTENDANCE ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  try {
    $pdo->beginTransaction();
    $action = $_POST['action'];
    $section_id = intval($_POST['section_id'] ?? 0);
    $date = trim($_POST['date'] ?? '');

    if (!$teacher_id || !$section_id || !$date)
      throw new Exception("Missing required fields!");

    $day = date('D', strtotime($date));
    $attendance = $_POST['attendance'] ?? [];

    // ✅ Find timetable
    $q = $pdo->prepare("SELECT subject_id, start_time, end_time 
                        FROM timetable 
                        WHERE section_id=? AND teacher_id=? AND day_of_week=? LIMIT 1");
    $q->execute([$section_id, $teacher_id, $day]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception("No timetable found for this section on $day");
    $subject_id = $row['subject_id'];

    // ✅ Time Check
    $start_time = strtotime($row['start_time']);
    $end_time   = strtotime($row['end_time']);
    $now = time();

    // ✅ Ensure Time IN before save/unlock
    $checkTeacherIn = $pdo->prepare("SELECT time_in FROM teacher_attendance WHERE teacher_id=? AND date=?");
    $checkTeacherIn->execute([$teacher_id, $date]);
    $teacherAttendance = $checkTeacherIn->fetch(PDO::FETCH_ASSOC);
    if (!$teacherAttendance || empty($teacherAttendance['time_in'])) {
      throw new Exception("⛔ You must record your Time IN before marking or unlocking attendance!");
    }

    if ($now < $start_time || $now > $end_time)
      throw new Exception("You can only record attendance during class time ({$row['start_time']} - {$row['end_time']}).");

    /* 🔐 Unlock */
    if ($action === 'student_unlock') {
      $check = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE section_id=? AND teacher_id=? AND subject_id=? AND attendance_date=?");
      $check->execute([$section_id, $teacher_id, $subject_id, $date]);
      if ($check->fetchColumn() > 0) {
        throw new Exception("🚫 Attendance already submitted — cannot unlock after saving.");
      }

      $pdo->prepare("DELETE FROM attendance WHERE section_id=? AND teacher_id=? AND subject_id=? AND attendance_date=?")
          ->execute([$section_id, $teacher_id, $subject_id, $date]);
      $message = "🔓 Attendance unlocked successfully!";
    }

    /* 💾 Save */
    elseif ($action === 'student_save') {
      foreach ($attendance as $student_id => $status) {
        $check = $pdo->prepare("SELECT attendance_id FROM attendance WHERE student_id=? AND attendance_date=? AND subject_id=?");
        $check->execute([$student_id, $date, $subject_id]);
        if ($check->fetch()) {
          throw new Exception("Attendance already exists! Unlock before editing.");
        }

        $insert = $pdo->prepare("INSERT INTO attendance 
          (student_id, section_id, teacher_id, subject_id, academic_term_id, attendance_date, status, created_at)
          VALUES (?,?,?,?,?,?,?,NOW())");
        $insert->execute([$student_id, $section_id, $teacher_id, $subject_id, $academic_term_id, $date, $status]);
      }
      $message = "✅ Attendance saved successfully!";
    }

    $pdo->commit();
    $type = "success";

  } catch (Exception $e) {
    $pdo->rollBack();
    $message = "❌ Error: " . $e->getMessage();
    $type = "error";
  }
}

/* ================= FETCH ACTIVE SECTIONS ================= */
$current_day = date('D');
$current_time = date('H:i:s');

$stmt = $pdo->prepare("
  SELECT DISTINCT cs.section_id, cs.section_name, subj.subject_name, t.start_time, t.end_time
  FROM timetable t
  JOIN class_section cs ON t.section_id = cs.section_id
  JOIN subject subj ON t.subject_id = subj.subject_id
  WHERE t.teacher_id = ?
    AND t.day_of_week = ?
    AND ? BETWEEN t.start_time AND t.end_time
  ORDER BY cs.section_name
");
$stmt->execute([$teacher_id, $current_day, $current_time]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= LOAD STUDENTS ================= */
$students = [];
if (isset($_POST['load_students'])) {
  $section_id = intval($_POST['section_id'] ?? 0);
  $date = $_POST['date'] ?? date('Y-m-d');
  $day = date('D', strtotime($date));

  $chk = $pdo->prepare("SELECT subject_id FROM timetable WHERE section_id=? AND teacher_id=? AND day_of_week=? LIMIT 1");
  $chk->execute([$section_id, $teacher_id, $day]);
  $hasClass = $chk->fetch(PDO::FETCH_ASSOC);
  if (!$hasClass) {
    $message = "❌ You have no class for this section on $day.";
    $type = "error";
  } else {
    $stmt = $pdo->prepare("SELECT student_id AS id, full_name, reg_no FROM students WHERE section_id=? ORDER BY full_name ASC");
    $stmt->execute([$section_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}

/* ================= VIEW LAST 50 RECORDS ================= */
$stmt = $pdo->prepare("
  SELECT a.attendance_date, s.full_name, s.reg_no, cs.section_name, sub.subject_name, a.status
  FROM attendance a
  JOIN students s ON s.student_id=a.student_id
  JOIN class_section cs ON cs.section_id=a.section_id
  JOIN subject sub ON sub.subject_id=a.subject_id
  WHERE a.teacher_id=?
  ORDER BY a.attendance_date DESC
  LIMIT 50
");
$stmt->execute([$teacher_id]);
$attendance_view = $stmt->fetchAll(PDO::FETCH_ASSOC);

include('../includes/header.php');
?>
<style>
:root {
  --green:#00843D;
  --blue:#0072CE;
  --light:#F5F9F7;
  --red:#C62828;
  --amber:#FFB400;
  --white:#FFFFFF;
}
body{font-family:'Poppins',sans-serif;background:var(--light);margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.sidebar.collapsed ~ .main-content{margin-left:70px;}
.tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;}
.tab-btn{background:var(--blue);color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-weight:600;}
.tab-btn.active{background:var(--green);}
.tab-content{display:none;} .tab-content.active{display:block;animation:fadeIn .4s;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);margin-bottom:15px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
label{font-weight:600;color:var(--blue);font-size:13px;}
select,input{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}
.btn{border:none;padding:8px 15px;border-radius:6px;font-weight:600;cursor:pointer;}
.btn.green{background:var(--green);color:#fff;}
.btn.red{background:var(--red);color:#fff;}
.btn.blue{background:var(--blue);color:#fff;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:var(--blue);color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
.actions{text-align:center;padding:15px;}
.alert{position:fixed;top:15px;right:15px;background:var(--green);color:#fff;padding:10px 20px;border-radius:6px;font-weight:600;z-index:9999;}
.alert.error{background:var(--red);}
@media(max-width:768px){.main-content{margin-left:0;padding:15px;}.grid{grid-template-columns:1fr;}}
</style>

<div class="main-content">
  <div class="page-header"><h1>📋 Student Attendance</h1></div>

  <div class="tabs">
    <button class="tab-btn active" data-tab="tab1"><i class="fa fa-user-check"></i> Record Attendance</button>
    <button class="tab-btn" data-tab="tab2"><i class="fa fa-list"></i> View Attendance</button>
  </div>

  <!-- TAB 1 -->
  <div id="tab1" class="tab-content active">
    <div class="filter-box">
      <form method="POST">
        <div class="grid">
          <div>
            <label>Section (Now Active)</label>
            <select name="section_id" required>
              <option value="">Select Section</option>
              <?php foreach($sections as $s): ?>
                <option value="<?= $s['section_id'] ?>" <?= (!empty($_POST['section_id']) && $_POST['section_id']==$s['section_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($s['section_name'] . ' — ' . $s['subject_name'] . " ({$s['start_time']} - {$s['end_time']})") ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" required>
          </div>
          <div style="align-self:end;">
            <button name="load_students" class="btn blue"><i class="fa fa-list"></i> Load Students</button>
          </div>
        </div>
      </form>
      <?php if(empty($sections)): ?>
        <p style="color:var(--red);margin-top:10px;"><i class="fa fa-clock"></i> No active class right now (<?= date('D H:i A') ?>)</p>
      <?php endif; ?>
    </div>

    <?php if(!empty($students)): ?>
    <form method="POST">
      <input type="hidden" name="section_id" value="<?= $_POST['section_id'] ?>">
      <input type="hidden" name="date" value="<?= $_POST['date'] ?>">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Student</th><th>Reg No</th><th>Status</th></tr></thead>
          <tbody>
            <?php $i=1; foreach($students as $st): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($st['full_name']) ?></td>
              <td><?= htmlspecialchars($st['reg_no']) ?></td>
              <td style="text-align:center;">
                <select name="attendance[<?= $st['id'] ?>]" required>
                  <option value="present">Present</option>
                  <option value="absent">Absent</option>
                  <option value="late">Late</option>
                  <option value="excused">Excused</option>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="actions">
        <button name="action" value="student_save" class="btn green"><i class="fa fa-save"></i> Save</button>
        <button name="action" value="student_unlock" class="btn red"><i class="fa fa-unlock"></i> Unlock</button>
      </div>
    </form>
    <?php endif; ?>
  </div>

  <!-- TAB 2 -->
  <div id="tab2" class="tab-content">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr><th>Date</th><th>Student</th><th>Reg No</th><th>Section</th><th>Subject</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (!empty($attendance_view)): foreach($attendance_view as $a): ?>
          <tr>
            <td><?= htmlspecialchars($a['attendance_date']) ?></td>
            <td><?= htmlspecialchars($a['full_name']) ?></td>
            <td><?= htmlspecialchars($a['reg_no']) ?></td>
            <td><?= htmlspecialchars($a['section_name']) ?></td>
            <td><?= htmlspecialchars($a['subject_name']) ?></td>
            <td>
              <?php
                $color = match($a['status']) {
                  'present' => 'var(--green)',
                  'absent' => 'var(--red)',
                  'late' => 'var(--amber)',
                  default => 'var(--blue)'
                };
                echo "<span style='color:$color;font-weight:bold;'>".ucfirst($a['status'])."</span>";
              ?>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="6" style="text-align:center;">No attendance records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if($message): ?>
  <div class="alert <?= $type ?>"><strong><?= $message ?></strong></div>
  <script>setTimeout(()=>document.querySelector('.alert').remove(),5000);</script>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.tab-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c=>c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});
</script>
<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>
