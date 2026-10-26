<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Stronger Access Control - CHANGE TO DEPARTMENT_ADMIN
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'department_admin') {
    session_destroy();
    header("Location: ../login.php?error=unauthorized");
    exit;
}

$department_id = $_SESSION['user']['linked_id'] ?? null;
if (!$department_id) {
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit;
}

// ✅ Get Department Info
$dept_info = $pdo->prepare("
    SELECT d.*, f.faculty_name, f.faculty_id, c.campus_name, c.campus_id
    FROM departments d 
    LEFT JOIN faculties f ON d.faculty_id = f.faculty_id 
    LEFT JOIN campus c ON d.campus_id = c.campus_id 
    WHERE d.department_id = ?
");
$dept_info->execute([$department_id]);
$department_data = $dept_info->fetch(PDO::FETCH_ASSOC);

if (!$department_data) {
    session_destroy();
    header("Location: ../login.php?error=invalid_department");
    exit;
}

$user_campus_id = $department_data['campus_id'];
$user_faculty_id = $department_data['faculty_id'];
$user_department_id = $department_data['department_id'];
$user_campus_name = $department_data['campus_name'];
$user_faculty_name = $department_data['faculty_name'];
$user_department_name = $department_data['department_name'];

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

/* ================= HANDLE ACTIONS ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        $action = $_POST['action'];

        // ✅ Input validation
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        $section_id = intval($_POST['section_id'] ?? 0);
        $date = trim($_POST['date'] ?? '');
        if (!$teacher_id || !$date) throw new Exception("Missing required input fields!");
        if (!$academic_term_id) throw new Exception("No active academic term found!");

        /* ============================================
           🧑‍🎓 STUDENT ATTENDANCE HANDLING
           ============================================ */
        if ($action === 'student_save' || $action === 'student_unlock') {
            $attendance = $_POST['attendance'] ?? [];
            $day = date('D', strtotime($date));

            // ✅ Fetch timetable record for validation - CHECK DEPARTMENT ACCESS
            $q = $pdo->prepare("
                SELECT tb.subject_id, tb.start_time, tb.end_time 
                FROM timetable tb
                JOIN class_section s ON s.section_id = tb.section_id
                JOIN programs p ON p.program_id = s.program_id
                JOIN departments d ON d.department_id = p.department_id
                WHERE tb.section_id=? AND tb.teacher_id=? AND tb.day_of_week=? AND d.department_id=?
                LIMIT 1
            ");
            $q->execute([$section_id, $teacher_id, $day, $department_id]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception("No timetable found for this teacher/section on $day");
            $subject_id = $row['subject_id'];

            // 🚫 Prevent past unlock/save
            if (strtotime($date) < strtotime(date('Y-m-d')))
                throw new Exception("You cannot unlock or re-record attendance for past dates.");

            // ✅ Require teacher to have Time IN before marking students
            $checkTeacherIn = $pdo->prepare("SELECT time_in FROM teacher_attendance WHERE teacher_id=? AND date=?");
            $checkTeacherIn->execute([$teacher_id, $date]);
            $teacherAttendance = $checkTeacherIn->fetch(PDO::FETCH_ASSOC);
            if (!$teacherAttendance || empty($teacherAttendance['time_in']))
                throw new Exception("Teacher must record Time IN before marking student attendance!");

            // ⚙️ Time check (only during class)
            $start_time = strtotime($row['start_time']);
            $end_time   = strtotime($row['end_time']);
            $now = time();
            if ($now < $start_time || $now > $end_time)
                throw new Exception("You can only perform attendance actions during class time ({$row['start_time']} - {$row['end_time']}).");

            /* ===========================
               🔓 UNLOCK STUDENT ATTENDANCE
               =========================== */
            if ($action === 'student_unlock') {
                // Check if attendance exists
                $checkAttendance = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE section_id=? AND teacher_id=? AND attendance_date=? AND subject_id=?");
                $checkAttendance->execute([$section_id, $teacher_id, $date, $subject_id]);
                $attendanceExists = $checkAttendance->fetch(PDO::FETCH_ASSOC);
                
                if ($attendanceExists['count'] == 0) {
                    throw new Exception("No attendance records found to unlock.");
                }
                
                $message = "🔓 Student attendance unlocked for editing. You can now modify and re-save.";
                $type = "success";
            }

            /* ===========================
               💾 SAVE OR UPDATE ATTENDANCE
               =========================== */
            else {
                foreach ($attendance as $student_id => $status) {
                    $student_id = intval($student_id);
                    if ($student_id <= 0) continue;

                    // Hubi haddii xog hore jirto
                    $check = $pdo->prepare("SELECT attendance_id FROM attendance WHERE student_id=? AND attendance_date=? AND subject_id=?");
                    $check->execute([$student_id, $date, $subject_id]);
                    $existing = $check->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        // ✅ Haddii hore loo diiwaangeliyey → UPDATE
                        $update = $pdo->prepare("UPDATE attendance SET status=?, updated_at=NOW() WHERE attendance_id=?");
                        $update->execute([$status, $existing['attendance_id']]);
                    } else {
                        // ✅ Haddii aan hore u jirin → INSERT cusub
                        $insert = $pdo->prepare("INSERT INTO attendance 
                          (student_id, section_id, teacher_id, subject_id, academic_term_id, attendance_date, status, created_at)
                          VALUES (?,?,?,?,?,?,?,NOW())");
                        $insert->execute([$student_id, $section_id, $teacher_id, $subject_id, $academic_term_id, $date, $status]);
                    }
                }

                $message = "✅ Student attendance saved/updated successfully!";
                $type = "success";
            }
        }

        /* ============================================
           👨‍🏫 TEACHER ATTENDANCE HANDLING
           ============================================ */
        if ($action === 'teacher_save' || $action === 'teacher_unlock') {
            $io_action = $_POST['io_action'] ?? '';
            $note = trim($_POST['notes'] ?? '');
            $day = date('D', strtotime($date));

            $tcheck = $pdo->prepare("
                SELECT MIN(tb.start_time) AS start_time, MAX(tb.end_time) AS end_time 
                FROM timetable tb
                JOIN class_section s ON s.section_id = tb.section_id
                JOIN programs p ON p.program_id = s.program_id
                JOIN departments d ON d.department_id = p.department_id
                WHERE tb.teacher_id=? AND tb.day_of_week=? AND d.department_id=?
            ");
            $tcheck->execute([$teacher_id, $day, $department_id]);
            $timetable = $tcheck->fetch(PDO::FETCH_ASSOC);
            if (!$timetable || !$timetable['start_time']) throw new Exception("No timetable found for this teacher today.");

            $start = strtotime($timetable['start_time']) - 600;
            $end = strtotime($timetable['end_time']);
            $now = time();
            if ($now < $start || $now > $end) throw new Exception("You can only record attendance between class hours.");
            if (strtotime($date) < strtotime(date('Y-m-d'))) throw new Exception("You cannot record or unlock attendance for past days.");

            $check = $pdo->prepare("SELECT id, time_in, time_out FROM teacher_attendance WHERE teacher_id=? AND date=?");
            $check->execute([$teacher_id, $date]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($action === 'teacher_unlock') {
                if (!$existing) throw new Exception("No attendance record found to unlock.");
                if ($io_action === 'In') {
                    $pdo->prepare("DELETE FROM teacher_attendance WHERE teacher_id=? AND date=?")->execute([$teacher_id, $date]);
                    $message = "🔓 All attendance data (IN/OUT) unlocked successfully!";
                } elseif ($io_action === 'Out') {
                    $pdo->prepare("UPDATE teacher_attendance SET time_out=NULL, minutes_worked=NULL, notes=?, updated_at=NOW() WHERE teacher_id=? AND date=?")
                        ->execute([$note, $teacher_id, $date]);
                    $message = "🔓 Time OUT unlocked successfully!";
                } else throw new Exception("Select valid unlock action (In/Out)!");
            } else {
                if ($io_action === 'In') {
                    if ($existing && $existing['time_in']) throw new Exception("Time IN already recorded!");
                    if ($existing) {
                        $pdo->prepare("UPDATE teacher_attendance SET time_in=?, notes=?, updated_at=NOW() WHERE teacher_id=? AND date=?")
                            ->execute([date('Y-m-d H:i:s'), $note, $teacher_id, $date]);
                    } else {
                        $pdo->prepare("INSERT INTO teacher_attendance (teacher_id, academic_term_id, date, time_in, notes, created_at)
                                       VALUES (?,?,?,?,?,NOW())")
                            ->execute([$teacher_id, $academic_term_id, $date, date('Y-m-d H:i:s'), $note]);
                    }
                    $message = "✅ Teacher Time IN recorded successfully!";
                } elseif ($io_action === 'Out') {
                    if (!$existing || !$existing['time_in']) throw new Exception("Time IN not found! Please record IN first.");
                    if ($existing['time_out']) throw new Exception("Time OUT already recorded!");
                    $time_in = strtotime($existing['time_in']);
                    $time_out = time();
                    $minutes = round(($time_out - $time_in) / 60);
                    $pdo->prepare("UPDATE teacher_attendance SET time_out=?, minutes_worked=?, notes=?, updated_at=NOW()
                                   WHERE teacher_id=? AND date=?")
                        ->execute([date('Y-m-d H:i:s'), $minutes, $note, $teacher_id, $date]);
                    $message = "✅ Teacher Time OUT recorded! Worked $minutes minutes.";
                } else throw new Exception("Select valid In/Out action!");
            }
            $type = "success";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ Error: " . $e->getMessage();
        $type = "error";
    }
}

/* ================= FETCH DATA ================= */

// ✅ Teachers (Only within this department)
$teachers = $pdo->prepare("
  SELECT DISTINCT t.teacher_id, t.teacher_name
  FROM teachers t
  JOIN timetable tb ON tb.teacher_id = t.teacher_id
  JOIN class_section s ON s.section_id = tb.section_id
  JOIN programs p ON p.program_id = s.program_id
  JOIN departments d ON d.department_id = p.department_id
  WHERE d.department_id = ?
  ORDER BY t.teacher_name ASC
");
$teachers->execute([$department_id]);
$teachers = $teachers->fetchAll(PDO::FETCH_ASSOC);

// ✅ Cascade Filters (department → program → section)
$programs = $sections = [];

// Programs under this department
$prog_stmt = $pdo->prepare("SELECT program_id, program_name FROM programs WHERE department_id=? ORDER BY program_name ASC");
$prog_stmt->execute([$department_id]);
$programs = $prog_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sections under program
if (!empty($_POST['program_id'])) {
    $sec_stmt = $pdo->prepare("SELECT section_id, section_name FROM class_section WHERE program_id=? ORDER BY section_name ASC");
    $sec_stmt->execute([$_POST['program_id']]);
    $sections = $sec_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* 👩‍🎓 Load Students if timetable exists */
$students = [];
if (isset($_POST['load_students'])) {
    $section_id = intval($_POST['section_id'] ?? 0);
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $day = date('D', strtotime($date));

    // ✅ Verify this teacher actually teaches this section on this day - CHECK DEPARTMENT ACCESS
    $chk = $pdo->prepare("
        SELECT tb.subject_id 
        FROM timetable tb
        JOIN class_section s ON s.section_id = tb.section_id
        JOIN programs p ON p.program_id = s.program_id
        JOIN departments d ON d.department_id = p.department_id
        WHERE tb.section_id=? AND tb.teacher_id=? AND tb.day_of_week=? AND d.department_id=?
        LIMIT 1
    ");
    $chk->execute([$section_id, $teacher_id, $day, $department_id]);
    $hasClass = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$hasClass) {
        $message = "❌ This teacher has no class for this section on $day.";
        $type = "error";
    } else {
        $stmt = $pdo->prepare("SELECT student_id AS id, full_name, reg_no FROM students WHERE section_id=? ORDER BY full_name ASC");
        $stmt->execute([$section_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Load existing attendance if any
        if (!empty($students)) {
            $student_ids = array_column($students, 'id');
            $placeholders = str_repeat('?,', count($student_ids) - 1) . '?';
            $attendance_stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE student_id IN ($placeholders) AND attendance_date=? AND subject_id=?");
            $attendance_stmt->execute(array_merge($student_ids, [$date, $hasClass['subject_id']]));
            $existing_attendance = $attendance_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
}

/* ✅ Attendance Views - FILTERED BY DEPARTMENT */
$stmt = $pdo->prepare("
  SELECT a.attendance_date, s.full_name, s.reg_no, c.section_name, t.teacher_name, a.status
  FROM attendance a
  JOIN students s ON s.student_id=a.student_id
  JOIN class_section c ON c.section_id=a.section_id
  JOIN programs p ON p.program_id=c.program_id
  JOIN departments d ON d.department_id=p.department_id
  JOIN teachers t ON t.teacher_id=a.teacher_id
  WHERE d.department_id=?
  ORDER BY a.attendance_date DESC
  LIMIT 50
");
$stmt->execute([$department_id]);
$attendance_view = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ✅ AUTO OUT - 10 minutes after timetable end_time */
$today = date('Y-m-d');
$dayName = date('D', strtotime($today));

$autoTeachers = $pdo->prepare("
  SELECT DISTINCT tb.teacher_id, MAX(tb.end_time) AS end_time
  FROM timetable tb
  JOIN teachers t ON t.teacher_id = tb.teacher_id
  JOIN class_section s ON s.section_id = tb.section_id
  JOIN programs p ON p.program_id = s.program_id
  JOIN departments d ON d.department_id = p.department_id
  WHERE tb.day_of_week = ? AND d.department_id = ?
  GROUP BY tb.teacher_id
");
$autoTeachers->execute([$dayName, $department_id]);
$autoTeachers = $autoTeachers->fetchAll(PDO::FETCH_ASSOC);

foreach ($autoTeachers as $tt) {
    $teacher_id = $tt['teacher_id'];
    $endTime = strtotime($today . ' ' . $tt['end_time']);
    $autoOutTime = $endTime + (10 * 60);

    if (time() >= $autoOutTime) {
        $check = $pdo->prepare("SELECT id, time_in, time_out FROM teacher_attendance WHERE teacher_id=? AND date=?");
        $check->execute([$teacher_id, $today]);
        $att = $check->fetch(PDO::FETCH_ASSOC);

        if ($att && $att['time_in'] && !$att['time_out']) {
            $time_in = strtotime($att['time_in']);
            $minutes = round(($autoOutTime - $time_in) / 60);
            $pdo->prepare("UPDATE teacher_attendance SET time_out=?, minutes_worked=?, notes=?, updated_at=NOW() WHERE teacher_id=? AND date=?")
                ->execute([date('Y-m-d H:i:s', $autoOutTime), $minutes, 'Auto-Out (10 mins after class end)', $teacher_id, $today]);
        }
    }
}

/* ✅ TEACHER VIEW - FILTERED BY DEPARTMENT */
$stmt = $pdo->prepare("
  SELECT ta.date, t.teacher_name, ta.time_in, ta.time_out, ta.minutes_worked, ta.notes
  FROM teacher_attendance ta
  JOIN teachers t ON t.teacher_id = ta.teacher_id
  WHERE ta.teacher_id IN (
    SELECT DISTINCT tb.teacher_id 
    FROM timetable tb 
    JOIN class_section s ON s.section_id = tb.section_id 
    JOIN programs p ON p.program_id = s.program_id 
    JOIN departments d ON d.department_id = p.department_id 
    WHERE d.department_id = ?
  )
  ORDER BY ta.date DESC
  LIMIT 50
");
$stmt->execute([$department_id]);
$teacher_view = $stmt->fetchAll(PDO::FETCH_ASSOC);

include('../includes/header.php');
?>


<style>
  .sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

</style>
<div class="main-content">
  <div class="page-header">
    <h1>Attendance Management</h1>
  </div>

  <!-- Department Information -->
  <!-- <div class="department-info" style="background: linear-gradient(135deg, #0072CE, #0056b3); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0, 114, 206, 0.3);">
    <h2 style="margin: 0 0 10px 0; font-size: 22px;"><?= htmlspecialchars($user_department_name) ?></h2>
    <p style="margin: 5px 0; opacity: 0.9;"><strong>Faculty:</strong> <?= htmlspecialchars($user_faculty_name) ?></p>
    <p style="margin: 5px 0; opacity: 0.9;"><strong>Campus:</strong> <?= htmlspecialchars($user_campus_name) ?></p>
  </div> -->

  <!-- ✅ Tabs -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="tab1"><i class="fa fa-user-check"></i> Student Record</button>
    <button class="tab-btn" data-tab="tab2"><i class="fa fa-list"></i> Student View</button>
    <button class="tab-btn" data-tab="tab3"><i class="fa fa-chalkboard-teacher"></i> Teacher Record</button>
    <button class="tab-btn" data-tab="tab4"><i class="fa fa-eye"></i> Teacher View</button>
  </div>

  <!-- ================= TAB 1: STUDENT RECORD ================= -->
  <div id="tab1" class="tab-content active">
    <div class="filter-box">
      <form method="POST">
        <div class="grid">

          <!-- 🎓 PROGRAM -->
          <div>
            <label>Program</label>
            <select name="program_id" onchange="this.form.submit()" required>
              <option value="">Select Program</option>
              <?php foreach($programs as $p): ?>
                <option value="<?= $p['program_id'] ?>" <?= (!empty($_POST['program_id']) && $_POST['program_id']==$p['program_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($p['program_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 🧑‍🎓 SECTION -->
          <div>
            <label>Section</label>
            <select name="section_id" onchange="this.form.submit()" required>
              <option value="">Select Section</option>
              <?php foreach($sections as $s): ?>
                <option value="<?= $s['section_id'] ?>" <?= (!empty($_POST['section_id']) && $_POST['section_id']==$s['section_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($s['section_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 👨‍🏫 TEACHER -->
          <div>
            <label>Teacher</label>
            <select name="teacher_id" required>
              <option value="">Select Teacher</option>
              <?php foreach($teachers as $t): ?>
                <option value="<?= $t['teacher_id'] ?>" <?= (!empty($_POST['teacher_id']) && $_POST['teacher_id']==$t['teacher_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($t['teacher_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 📅 DATE -->
          <div>
            <label>Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" required>
          </div>

          <div style="align-self:end;">
            <button name="load_students" class="btn blue"><i class="fa fa-list"></i> Load Students</button>
          </div>
        </div>
      </form>
    </div>

    <?php if(!empty($students)): ?>
    <form method="POST">
      <input type="hidden" name="teacher_id" value="<?= $_POST['teacher_id'] ?>">
      <input type="hidden" name="section_id" value="<?= $_POST['section_id'] ?>">
      <input type="hidden" name="date" value="<?= $_POST['date'] ?>">
      <div class="table-wrapper">
        <table>
          <thead><tr><th>#</th><th>Student</th><th>Reg No</th><th>Status</th></tr></thead>
          <tbody>
            <?php $i=1; foreach($students as $st): 
                $current_status = $existing_attendance[$st['id']] ?? 'present';
            ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($st['full_name']) ?></td>
              <td><?= htmlspecialchars($st['reg_no']) ?></td>
              <td style="text-align:center;">
                <select name="attendance[<?= $st['id'] ?>]" required>
                  <option value="present" <?= $current_status == 'present' ? 'selected' : '' ?>>Present</option>
                  <option value="absent" <?= $current_status == 'absent' ? 'selected' : '' ?>>Absent</option>
                  <option value="late" <?= $current_status == 'late' ? 'selected' : '' ?>>Late</option>
                  <option value="excused" <?= $current_status == 'excused' ? 'selected' : '' ?>>Excused</option>
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

  <!-- ================= TAB 2: STUDENT VIEW ================= -->
  <div id="tab2" class="tab-content">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Student</th>
            <th>Reg No</th>
            <th>Section</th>
            <th>Teacher</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($attendance_view)): ?>
            <?php foreach($attendance_view as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a['attendance_date']) ?></td>
                <td><?= htmlspecialchars($a['full_name']) ?></td>
                <td><?= htmlspecialchars($a['reg_no']) ?></td>
                <td><?= htmlspecialchars($a['section_name']) ?></td>
                <td><?= htmlspecialchars($a['teacher_name']) ?></td>
                <td>
                  <?php if ($a['status'] == 'present'): ?>
                    <span style="color:green;font-weight:bold;">Present</span>
                  <?php elseif ($a['status'] == 'absent'): ?>
                    <span style="color:red;font-weight:bold;">Absent</span>
                  <?php elseif ($a['status'] == 'late'): ?>
                    <span style="color:orange;font-weight:bold;">Late</span>
                  <?php else: ?>
                    <span style="color:blue;font-weight:bold;">Excused</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No student attendance records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ================= TAB 3: TEACHER RECORD ================= -->
  <div id="tab3" class="tab-content">
    <div class="filter-box">
      <form method="POST">
        <div class="grid">
          <!-- 👨‍🏫 TEACHER -->
          <div>
            <label>Teacher</label>
            <select name="teacher_id" required>
              <option value="">Select Teacher</option>
              <?php foreach($teachers as $t): ?>
                <option value="<?= $t['teacher_id'] ?>" <?= (!empty($_POST['teacher_id']) && $_POST['teacher_id']==$t['teacher_id'])?'selected':'' ?>>
                  <?= htmlspecialchars($t['teacher_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 📅 DATE -->
          <div>
            <label>Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d')) ?>" required>
          </div>

          <!-- ⚙️ ACTION -->
          <div>
            <label>Action</label>
            <select name="io_action" required>
              <option value="">Select</option>
              <option value="In">Time In</option>
              <option value="Out">Time Out</option>
            </select>
          </div>

          <!-- 📝 NOTES -->
          <div>
            <label>Notes</label>
            <input type="text" name="notes" placeholder="Optional notes..." value="<?= htmlspecialchars($_POST['notes'] ?? '') ?>">
          </div>

          <!-- 💾 BUTTONS -->
          <div style="align-self:end;">
            <button name="action" value="teacher_save" class="btn green"><i class="fa fa-save"></i> Save</button>
            <button name="action" value="teacher_unlock" class="btn red"><i class="fa fa-unlock"></i> Unlock</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ================= TAB 4: TEACHER VIEW ================= -->
  <div id="tab4" class="tab-content">
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Teacher</th>
            <th>Time In</th>
            <th>Time Out</th>
            <th>Minutes Worked</th>
            <th>Notes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($teacher_view)): ?>
            <?php foreach($teacher_view as $tv): ?>
              <tr>
                <td><?= htmlspecialchars($tv['date']) ?></td>
                <td><?= htmlspecialchars($tv['teacher_name']) ?></td>
                <td><?= htmlspecialchars($tv['time_in'] ?? '-') ?></td>
                <td><?= htmlspecialchars($tv['time_out'] ?? '-') ?></td>
                <td><?= htmlspecialchars($tv['minutes_worked'] ?? '-') ?></td>
                <td><?= htmlspecialchars($tv['notes'] ?? '-') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No teacher attendance records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<!-- ✅ Alert -->
<?php if($message): ?>
<div class="alert <?= htmlspecialchars($type) ?>"><strong><?= htmlspecialchars($message) ?></strong></div>
<?php endif; ?>


<!-- ✅ STYLE -->
<style>
body{font-family:'Poppins',sans-serif;background:#f7f9fb;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;}
.tab-btn{background:#0072CE;color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-weight:600;}
.tab-btn.active{background:#00843D;}
.tab-content{display:none;} .tab-content.active{display:block;animation:fadeIn .4s;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);margin-bottom:15px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
label{font-weight:600;color:#0072CE;font-size:13px;}
select,input{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}

.btn{border:none;padding:8px 15px;border-radius:6px;font-weight:600;cursor:pointer;}
.btn.green{background:#00843D;color:#fff;}
.btn.red{background:#C62828;color:#fff;}
.btn.blue{background:#0072CE;color:#fff;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
.actions{text-align:center;padding:15px;}
.alert{position:fixed;top:15px;right:15px;background:#00843D;color:#fff;padding:10px 20px;border-radius:6px;font-weight:600;z-index:9999;}
.alert.error{background:#C62828;}
@media(max-width:768px){.main-content{margin-left:0;padding:15px;}.grid{grid-template-columns:1fr;}}

select.locked {
  background: #eaeaea !important;
  color: #666 !important;
  cursor: not-allowed;
}
</style>

<!-- ✅ SCRIPT -->
<script>
// ✅ Tab Switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab).classList.add('active');
  });
});

// ✅ Required field validation before submit
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', e => {
    const required = form.querySelectorAll('[required]');
    for (const field of required) {
      if (!field.value.trim()) {
        e.preventDefault();
        alert('Please fill all required fields before submitting.');
        return;
      }
    }
  });
});

// ✅ Control attendance dropdowns (lock/unlock behavior)
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('form[action=""]') || document.querySelector('form[method="POST"]');
  if (!form) return;

  const saveBtn = form.querySelector('button[value="student_save"]');
  const unlockBtn = form.querySelector('button[value="student_unlock"]');
  const selects = form.querySelectorAll('select[name^="attendance["]');

  if (saveBtn && unlockBtn && selects.length > 0) {
    // Marka Save la riixo → xiro dropdowns
    saveBtn.addEventListener('click', () => {
      selects.forEach(sel => {
        sel.disabled = true;
        sel.classList.add('locked');
      });
    });

    // Marka Unlock la riixo → furo dropdowns
    unlockBtn.addEventListener('click', (e) => {
      e.preventDefault();
      selects.forEach(sel => {
        sel.disabled = false;
        sel.classList.remove('locked');
      });
      setTimeout(() => form.submit(), 200);
    });
  }
});

// Auto refresh every minute
setInterval(() => window.location.reload(), 60000);
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>