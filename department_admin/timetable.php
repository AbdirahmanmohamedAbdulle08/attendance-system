<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control
$role = strtolower($_SESSION['user']['role'] ?? '');
if (!in_array($role, ['faculty_admin', 'department_admin'])) {
    header("Location: ../login.php");
    exit;
}

// ✅ Get linked_id based on role
$linked_id = $_SESSION['user']['linked_id'];
$faculty_id = null;
$department_id = null;

if ($role === 'faculty_admin') {
    $faculty_id = $linked_id;
} elseif ($role === 'department_admin') {
    $department_id = $linked_id;
    
    // Get faculty_id from department
    $stmt = $pdo->prepare("SELECT faculty_id FROM departments WHERE department_id = ?");
    $stmt->execute([$department_id]);
    $dept_info = $stmt->fetch(PDO::FETCH_ASSOC);
    $faculty_id = $dept_info['faculty_id'] ?? null;
}

if (!$faculty_id) {
    header("Location: ../login.php");
    exit;
}

// Get department info for department_admin
$department_info = [];
if ($role === 'department_admin' && $department_id) {
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
    $stmt->execute([$department_id]);
    $department_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get faculty info
$faculty_info = [];
if ($faculty_id) {
    $stmt = $pdo->prepare("SELECT faculty_name FROM faculties WHERE faculty_id = ?");
    $stmt->execute([$faculty_id]);
    $faculty_info = $stmt->fetch(PDO::FETCH_ASSOC);
}

$message = "";
$type = "";

/* ===========================================
   CRUD OPERATIONS
=========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  try {
    function validateRequired($fields) {
      foreach ($fields as $label => $value) {
        if (empty($value)) throw new Exception("⚠️ '$label' field is required.");
      }
    }

    if (in_array($action, ['add', 'update'])) {
      $id = $_POST['timetable_id'] ?? null;
      $section_id = $_POST['section_id'] ?? '';
      $subject_id = $_POST['subject_id'] ?? '';
      $teacher_id = $_POST['teacher_id'] ?? null;
      $room_id = $_POST['room_id'] ?? null;
      $term_id = $_POST['academic_term_id'] ?? '';
      $day = $_POST['day_of_week'] ?? '';
      $start = $_POST['start_time'] ?? '';
      $end = $_POST['end_time'] ?? '';
      $status = $_POST['status'] ?? 'active';

      validateRequired([
        "Section" => $section_id,
        "Subject" => $subject_id,
        "Academic Term" => $term_id,
        "Day" => $day,
        "Start Time" => $start,
        "End Time" => $end
      ]);

      if ($end <= $start) throw new Exception("⛔ End time must be later than start time.");

      // ✅ Conflict Validation (Teacher or Room Only)
      $conflictQuery = "
      SELECT timetable_id FROM timetable
      WHERE academic_term_id = ?
        AND day_of_week = ?
        AND timetable_id != COALESCE(?, 0)
        AND faculty_id = ?
        AND (
          (teacher_id IS NOT NULL AND teacher_id = ?)
          OR (room_id IS NOT NULL AND room_id = ?)
        )
        AND (? < end_time AND ? > start_time)
      ";
      $stmt = $pdo->prepare($conflictQuery);
      $stmt->execute([
      $term_id,
      $day,
      $id,
      $faculty_id,
      $teacher_id,
      $room_id,
      $start,
      $end
      ]);

      if ($stmt->fetch()) {
      throw new Exception("❌ Time conflict! Section, teacher, or room already scheduled in that time.");
      }

      // ✅ Insert or Update
      if ($action === 'add') {
        // INSERT query with faculty_id
        $stmt = $pdo->prepare("
          INSERT INTO timetable 
            (faculty_id, section_id, subject_id, teacher_id, room_id, academic_term_id, day_of_week, start_time, end_time, status)
          VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
          $faculty_id,
          $section_id,
          $subject_id,
          $teacher_id,
          $room_id,
          $term_id,
          $day,
          $start,
          $end,
          $status
        ]);
      
        $message = "✅ Timetable entry added successfully!";
      }
      else {
        // Build query based on role
        if ($role === 'faculty_admin') {
          $stmt = $pdo->prepare("
            UPDATE timetable 
            SET faculty_id=?, section_id=?, subject_id=?, teacher_id=?, room_id=?, academic_term_id=?, day_of_week=?, start_time=?, end_time=?, status=?, updated_at=NOW()
            WHERE timetable_id=?
          ");
          $params = [
            $faculty_id,
            $section_id,
            $subject_id,
            $teacher_id,
            $room_id,
            $term_id,
            $day,
            $start,
            $end,
            $status,
            $id
          ];
        } else { // department_admin
          $stmt = $pdo->prepare("
            UPDATE timetable 
            SET subject_id=?, teacher_id=?, room_id=?, academic_term_id=?, day_of_week=?, start_time=?, end_time=?, status=?, updated_at=NOW()
            WHERE timetable_id=? AND faculty_id=?
          ");
          $params = [
            $subject_id,
            $teacher_id,
            $room_id,
            $term_id,
            $day,
            $start,
            $end,
            $status,
            $id,
            $faculty_id
          ];
        }
        
        $stmt->execute($params);
        $message = "✅ Timetable updated successfully!";
      }
      
      $type = "success";
    }      

    // 🔴 DELETE - Only for faculty_admin
    if ($action === 'delete' && $role === 'faculty_admin') {
        $id = $_POST['timetable_id'] ?? null;
        if (!$id) throw new Exception("Missing timetable ID!");

        $stmt = $pdo->prepare("DELETE FROM timetable WHERE timetable_id=? AND faculty_id=?");
        $stmt->execute([$id, $faculty_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to delete timetable entry!");
        }

        $message = "🗑️ Timetable entry deleted successfully!";
        $type = "success";
    }

  } catch (Exception $e) {
    $message = $e->getMessage();
    $type = "error";
  }
}

/* ===========================================
   FETCH DATA
=========================================== */
// ✅ Sections (Based on role)
if ($role === 'faculty_admin') {
    $sections = $pdo->prepare("
      SELECT s.section_id, s.section_name
      FROM class_section s
      JOIN departments d ON s.department_id = d.department_id
      JOIN faculties f ON d.faculty_id = f.faculty_id 
    WHERE d.faculty_id = ?
      ORDER BY s.section_name ASC
    ");
    $sections->execute([$faculty_id]);
    $sections = $sections->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin only sees sections in their department
    $sections = $pdo->prepare("
      SELECT s.section_id, s.section_name
      FROM class_section s
      WHERE s.department_id = ?
      ORDER BY s.section_name ASC
    ");
    $sections->execute([$department_id]);
    $sections = $sections->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Subjects (Based on role)
if ($role === 'faculty_admin') {
    $subjects = $pdo->prepare("
      SELECT sub.subject_id, sub.subject_name
      FROM subject sub
      JOIN programs p ON sub.program_id = p.program_id
      JOIN departments d ON p.department_id = d.department_id
      JOIN faculties f ON d.faculty_id = f.faculty_id
    WHERE d.faculty_id = ?
      ORDER BY sub.subject_name ASC
    ");
    $subjects->execute([$faculty_id]);
    $subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin only sees subjects in their department
    $subjects = $pdo->prepare("
      SELECT sub.subject_id, sub.subject_name
      FROM subject sub
      JOIN programs p ON sub.program_id = p.program_id
      WHERE p.department_id = ?
      ORDER BY sub.subject_name ASC
    ");
    $subjects->execute([$department_id]);
    $subjects = $subjects->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Teachers (All teachers available)
$teachers = $pdo->query("
  SELECT teacher_id, teacher_name
  FROM teachers
  ORDER BY teacher_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ✅ Rooms (Based on role)
if ($role === 'faculty_admin') {
    $rooms = $pdo->prepare("
      SELECT r.room_id, r.room_name
      FROM rooms r
    WHERE r.faculty_id = ?
      ORDER BY r.room_name ASC
    ");
    $rooms->execute([$faculty_id]);
    $rooms = $rooms->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin sees rooms in their faculty
    $rooms = $pdo->prepare("
      SELECT r.room_id, r.room_name
      FROM rooms r
    WHERE r.faculty_id = ?
      ORDER BY r.room_name ASC
    ");
    $rooms->execute([$faculty_id]);
    $rooms = $rooms->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Academic Terms (All campuses share same terms)
$terms = $pdo->prepare("
  SELECT t.academic_term_id, t.term_name
  FROM academic_term t
  JOIN academic_year y ON t.academic_year_id = y.academic_year_id
  WHERE t.status = 'active' 
    AND y.status = 'active'
  ORDER BY t.term_name DESC
");
$terms->execute();
$terms = $terms->fetchAll(PDO::FETCH_ASSOC);

// ✅ Timetable Query (Filtered by role)
if ($role === 'faculty_admin') {
    $timetables = $pdo->prepare("
      SELECT 
        t.*, 
        s.section_name, 
        subj.subject_name, 
        r.room_name, 
        d.department_name, 
        f.faculty_name, 
        c.campus_name,
        tr.teacher_name, 
        at.term_name
      FROM timetable t
      JOIN class_section s ON t.section_id = s.section_id
      JOIN departments d ON s.department_id = d.department_id
      JOIN faculties f ON d.faculty_id  = f.faculty_id 
      JOIN campus c ON f.campus_id = c.campus_id
      JOIN subject subj ON t.subject_id = subj.subject_id
      LEFT JOIN teachers tr ON t.teacher_id = tr.teacher_id
      LEFT JOIN rooms r ON t.room_id = r.room_id
      JOIN academic_term at ON t.academic_term_id = at.academic_term_id
    WHERE t.faculty_id = ?
      ORDER BY t.day_of_week, t.start_time
    ");
    $timetables->execute([$faculty_id]);
    $timetables = $timetables->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Department admin sees timetables for their department's sections
    $timetables = $pdo->prepare("
      SELECT 
        t.*, 
        s.section_name, 
        subj.subject_name, 
        r.room_name, 
        d.department_name, 
        f.faculty_name, 
        c.campus_name,
        tr.teacher_name, 
        at.term_name
      FROM timetable t
      JOIN class_section s ON t.section_id = s.section_id
      JOIN departments d ON s.department_id = d.department_id
      JOIN faculties f ON d.faculty_id  = f.faculty_id 
      JOIN campus c ON f.campus_id = c.campus_id
      JOIN subject subj ON t.subject_id = subj.subject_id
      LEFT JOIN teachers tr ON t.teacher_id = tr.teacher_id
      LEFT JOIN rooms r ON t.room_id = r.room_id
      JOIN academic_term at ON t.academic_term_id = at.academic_term_id
    WHERE s.department_id = ?
      ORDER BY t.day_of_week, t.start_time
    ");
    $timetables->execute([$department_id]);
    $timetables = $timetables->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $role === 'department_admin' ? 'Department' : 'Faculty' ?> Timetable | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--green:#00843D;--blue:#0072CE;--red:#C62828;--bg:#F5F9F7;}
body{font-family:'Poppins',sans-serif;background:var(--bg);margin:0;}
.main-content{padding:20px;margin-top:90px;margin-left:250px;transition:all .3s ease;}
.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

/* Info Sections */
/* .department-info {
    background:#e8f5e9;
    padding:12px 16px;
    border-radius:8px;
    margin-bottom:20px;
    border-left:4px solid var(--green);
    font-weight:600;
} */
.info-banner {
    background: var(--blue);
    color: white;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: 600;
}

.add-btn{background:var(--green);color:#fff;padding:8px 14px;border:none;border-radius:6px;font-weight:600;cursor:pointer;}
.add-btn:hover{background:#00A651;}

.table-wrapper{overflow-x:auto;overflow-y:auto;max-height:500px;background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;min-width:1000px;}
th,td{padding:12px 14px;border-bottom:1px solid #eee;text-align:left;white-space:nowrap;}
thead th{background:var(--blue);color:#fff;position:sticky;top:0;z-index:2;}
tr:hover{background:#eef8f0;}
.action-buttons{display:flex;justify-content:center;gap:6px;}
.btn-edit{background:#0072CE;color:white;padding:8px 10px;border:none;border-radius:6px;}
.btn-delete{background:#C62828;color:white;padding:8px 10px;border:none;border-radius:6px;}
.btn-delete:disabled {
    background:#ccc;
    cursor:not-allowed;
}

.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:3000;}
.modal.show{display:flex;animation:fadeIn .3s;}
@keyframes fadeIn{from{opacity:0;}to{opacity:1;}}
.modal-content{background:#fff;border-radius:10px;width:90%;max-width:700px;padding:25px;position:relative;max-height:90vh;overflow-y:auto;box-shadow:0 4px 20px rgba(0,0,0,0.2);}
.close-modal{position:absolute;top:10px;right:15px;font-size:22px;cursor:pointer;}
form{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;}
label{font-weight:600;color:#0072CE;}
input,select{width:100%;padding:10px;border:1.5px solid #ccc;border-radius:6px;}
.save-btn{grid-column:span 2;background:var(--green);color:#fff;border:none;padding:10px;border-radius:6px;font-weight:600;cursor:pointer;}
.save-btn:hover{background:#00994A;}
.alert-popup{display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px 25px;border-radius:12px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,0.2);z-index:5000;width:280px;}
.alert-popup.show{display:block;}
.alert-popup.success{border-top:5px solid var(--green);}
.alert-popup.error{border-top:5px solid var(--red);}
.alert-popup h3{font-size:14px;color:#333;margin-bottom:15px;}
.alert-btn{background:var(--blue);color:white;padding:6px 16px;border:none;border-radius:5px;cursor:pointer;font-weight:600;}
.alert-btn:hover{background:#005bb5;}
@media(max-width:700px){
  form{grid-template-columns:repeat(2,1fr);}
  .modal-content{max-width:95%;}
}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
    <!-- Department/Faculty Info -->
    <?php if ($role === 'department_admin' && !empty($department_info)): ?>
    <div class="department-info">
        <!-- <i class="fas fa-building"></i> 
        Department: <strong><?= htmlspecialchars($department_info['department_name']) ?></strong>
        <?php if (!empty($faculty_info)): ?>
            | Faculty: <strong><?= htmlspecialchars($faculty_info['faculty_name']) ?></strong> -->
        <?php endif; ?>
    </div>
    <?php elseif ($role === 'faculty_admin' && !empty($faculty_info)): ?>
    <div class="info-banner">
        <i class="fas fa-university"></i> 
        Faculty: <strong><?= htmlspecialchars($faculty_info['faculty_name']) ?></strong>
    </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;">
        <h1 style="color:var(--blue)">
            <?= $role === 'department_admin' ? 'Department' : 'Faculty' ?> Timetable
            <!-- <?php if ($role === 'department_admin' && !empty($department_info)): ?>
                <small style="font-size: 16px; color: #666;">- <?= htmlspecialchars($department_info['department_name']) ?></small>
            <?php endif; ?> -->
        </h1>
        <button class="add-btn" onclick="openModal('addModal')">
            + Add Timetable
        </button>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($role === 'faculty_admin'): ?>
                        <th>Campus</th>
                        <th>Faculty</th>
                        <th>Department</th>
                    <?php endif; ?>
                    <th>Section</th>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Room</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($timetables): foreach($timetables as $i=>$t): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <?php if ($role === 'faculty_admin'): ?>
                        <td><?= htmlspecialchars($t['campus_name']) ?></td>
                        <td><?= htmlspecialchars($t['faculty_name']) ?></td>
                        <td><?= htmlspecialchars($t['department_name']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($t['section_name']) ?></td>
                    <td><?= htmlspecialchars($t['subject_name']) ?></td>
                    <td><?= htmlspecialchars($t['teacher_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['room_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($t['day_of_week']) ?></td>
                    <td><?= htmlspecialchars($t['start_time'].' - '.$t['end_time']) ?></td>
                    <td style="color:<?= $t['status']=='active'?'green':'red' ?>;font-weight:600;"><?= ucfirst($t['status']) ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-edit" onclick="openEditModal(<?= $t['timetable_id'] ?>,'<?= $t['section_id'] ?>','<?= $t['subject_id'] ?>','<?= $t['teacher_id'] ?>','<?= $t['room_id'] ?>','<?= $t['academic_term_id'] ?>','<?= $t['day_of_week'] ?>','<?= $t['start_time'] ?>','<?= $t['end_time'] ?>','<?= $t['status'] ?>')">
                                <i class='fa-solid fa-pen-to-square'></i>
                            </button>
                            <button class="btn-delete" onclick="openDeleteModal(<?= $t['timetable_id'] ?>)" <?= $role === 'department_admin' ? 'disabled' : '' ?>>
                                <i class='fa-solid fa-trash'></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="<?= $role === 'faculty_admin' ? '12' : '9' ?>" style="text-align:center;color:#777;">
                        No timetable found <?= $role === 'department_admin' ? 'for your department' : '' ?>.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ ADD MODAL - For both faculty_admin and department_admin -->
<div class="modal" id="addModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
        <h2 style="text-align:center;color:var(--blue)">Add Timetable</h2>
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="add">
            <div><label>Section *</label>
                <select name="section_id" required>
                    <option value="">Select Section</option>
                    <?php foreach($sections as $s): ?>
                        <option value="<?= $s['section_id'] ?>"><?= $s['section_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Subject *</label>
                <select name="subject_id" required>
                    <option value="">Select Subject</option>
                    <?php foreach($subjects as $s): ?>
                        <option value="<?= $s['subject_id'] ?>"><?= $s['subject_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Teacher</label>
                <select name="teacher_id">
                    <option value="">Select Teacher</option>
                    <?php foreach($teachers as $t): ?>
                        <option value="<?= $t['teacher_id'] ?>"><?= $t['teacher_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Room</label>
                <select name="room_id">
                    <option value="">Select Room</option>
                    <?php foreach($rooms as $r): ?>
                        <option value="<?= $r['room_id'] ?>"><?= $r['room_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Term *</label>
                <select name="academic_term_id" required>
                    <option value="">Select Term</option>
                    <?php foreach($terms as $t): ?>
                        <option value="<?= $t['academic_term_id'] ?>"><?= $t['term_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Day *</label>
                <select name="day_of_week" required>
                    <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Start Time *</label><input type="time" name="start_time" required></div>
            <div><label>End Time *</label><input type="time" name="end_time" required></div>
            <div><label>Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button class="save-btn" type="submit">Save Timetable</button>
        </form>
    </div>
</div>

<!-- ✅ EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h2 style="text-align:center;color:var(--blue)">Edit Timetable</h2>
        <form method="POST" onsubmit="return validateForm(this)">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_id" name="timetable_id">
            
            <?php if ($role === 'faculty_admin'): ?>
            <div><label>Section *</label>
                <select id="edit_section" name="section_id" required>
                    <?php foreach($sections as $s): ?>
                        <option value="<?= $s['section_id'] ?>"><?= $s['section_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="section_id" id="edit_section">
            <?php endif; ?>
            
            <div><label>Subject *</label>
                <select id="edit_subject" name="subject_id" required>
                    <?php foreach($subjects as $s): ?>
                        <option value="<?= $s['subject_id'] ?>"><?= $s['subject_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Teacher</label>
                <select id="edit_teacher" name="teacher_id">
                    <option value="">Select Teacher</option>
                    <?php foreach($teachers as $t): ?>
                        <option value="<?= $t['teacher_id'] ?>"><?= $t['teacher_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Room</label>
                <select id="edit_room" name="room_id">
                    <option value="">Select Room</option>
                    <?php foreach($rooms as $r): ?>
                        <option value="<?= $r['room_id'] ?>"><?= $r['room_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Term *</label>
                <select id="edit_term" name="academic_term_id" required>
                    <?php foreach($terms as $t): ?>
                        <option value="<?= $t['academic_term_id'] ?>"><?= $t['term_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Day *</label>
                <select id="edit_day" name="day_of_week" required>
                    <?php foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><label>Start Time *</label><input type="time" id="edit_start" name="start_time" required></div>
            <div><label>End Time *</label><input type="time" id="edit_end" name="end_time" required></div>
            <div><label>Status</label>
                <select id="edit_status" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <button class="save-btn" type="submit">Update Timetable</button>
        </form>
    </div>
</div>

<!-- ✅ DELETE MODAL - Only for faculty_admin -->
<?php if ($role === 'faculty_admin'): ?>
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
        <h2 style="text-align:center;color:var(--red)">Confirm Delete</h2>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete_id" name="timetable_id">
            <p style="text-align:center;">Are you sure you want to delete this timetable entry?</p>
            <button class="save-btn" style="background:#C62828" type="submit">Yes, Delete</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ✅ ALERT -->
<div id="popup" class="alert-popup <?= $type ?>">
    <h3><?= $message ?></h3>
    <button class="alert-btn" onclick="closeAlert()">OK</button>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('show');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('show');document.body.style.overflow='auto';}
function openDeleteModal(id){openModal('deleteModal');document.getElementById('delete_id').value=id;}
function closeAlert(){document.getElementById('popup').classList.remove('show');}
if("<?= $message ?>"){document.getElementById('popup').classList.add('show');}

function openEditModal(id, section, subject, teacher, room, term, day, start, end, status){
    openModal('editModal');
    document.getElementById('edit_id').value=id;
    document.getElementById('edit_section').value=section;
    document.getElementById('edit_subject').value=subject;
    document.getElementById('edit_teacher').value=teacher;
    document.getElementById('edit_room').value=room;
    document.getElementById('edit_term').value=term;
    document.getElementById('edit_day').value=day;
    document.getElementById('edit_start').value=start;
    document.getElementById('edit_end').value=end;
    document.getElementById('edit_status').value=status;
}

function validateForm(form){
    const start=form.querySelector('[name="start_time"]').value;
    const end=form.querySelector('[name="end_time"]').value;
    if(end<=start){alert("⛔ End time must be later than start time.");return false;}
    return true;
}
</script>

</body>
</html>
<?php ob_end_flush(); ?>