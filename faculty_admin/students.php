<?php
/*******************************************************************************************
 * STUDENTS MANAGEMENT SYSTEM — Hormuud University
 * Faculty Admin Version - Restricted to faculty students only
 * Full CRUD + Parent Auto-detect + Parent_Student Relation + CSV Import/Export + Auto User
 * Layout compatible with header/sidebar/footer
 * Author: ChatGPT (2025)
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

$role = strtolower($_SESSION['user']['role'] ?? '');
if ($role !== 'faculty_admin') {
  header("Location: ../login.php");
  exit;
}

// Get faculty_id from session
$faculty_id = $_SESSION['user']['linked_id'] ?? null;
if (!$faculty_id) {
  header("Location: ../login.php");
  exit;
}

$message = "";
$type = "";

/* ============================================================
   AJAX HANDLERS
============================================================ */
if (isset($_GET['ajax'])) {
  header('Content-Type: application/json');
  
  // Get related data within faculty
  if ($_GET['ajax'] === 'get_related') {
    $type = $_GET['type'] ?? '';
    $id = intval($_GET['id']);
    
    switch ($type) {
      case 'department':
        $stmt = $pdo->prepare("SELECT department_id AS id, department_name AS name FROM departments WHERE faculty_id=?");
        $stmt->execute([$id]);
        break;
      case 'program':
        $stmt = $pdo->prepare("SELECT program_id AS id, program_name AS name FROM programs WHERE department_id=?");
        $stmt->execute([$id]);
        break;
      case 'section':
        $stmt = $pdo->prepare("SELECT section_id AS id, section_name AS name FROM class_section WHERE program_id=?");
        $stmt->execute([$id]);
        break;
      default:
        echo json_encode([]); exit;
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
  
  // Get parent info automatically by name
  } elseif ($_GET['ajax'] === 'get_parent') {
    $name = trim($_GET['name'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM parents WHERE full_name LIKE ? LIMIT 1");
    $stmt->execute(["%$name%"]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
    exit;
  }
}

/* ============================================================
   CSV IMPORT / EXPORT + CRUD (Students + Parents)
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

  /* ============================================================
     ✅ EXPORT CSV - Faculty Restricted
  ============================================================ */
  if ($_POST['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
  
    // ✅ Header row
    fputcsv($output, [
      'Reg No', 'Full Name', 'Gender', 'DOB', 'Email', 'Phone', 'Address',
      'Faculty', 'Department', 'Program', 'Section', 'Semester',
      'Status', 'Guardian Name', 'Guardian Gender', 'Guardian Phone', 'Guardian Email',
      'Guardian Address', 'Guardian Occupation', 'Guardian Status', 'Relation'
    ]);
  
    // ✅ Fetch data with name joins - Faculty restricted
    $stmt = $pdo->prepare("
      SELECT 
        s.reg_no,
        s.full_name,
        s.gender,
        s.dob,
        s.email,
        s.phone_number,
        s.address,
        COALESCE(f.faculty_name, 'N/A') AS faculty_name,
        COALESCE(d.department_name, 'N/A') AS department_name,
        COALESCE(pr.program_name, 'N/A') AS program_name,
        COALESCE(sec.section_name, 'N/A') AS section_name,
        COALESCE(se.semester_name, 'N/A') AS semester_name,
        s.status,
        COALESCE(p.full_name, 'N/A') AS guardian_name,
        COALESCE(p.gender, 'N/A') AS guardian_gender,
        COALESCE(p.phone, 'N/A') AS guardian_phone,
        COALESCE(p.email, 'N/A') AS guardian_email,
        COALESCE(p.address, 'N/A') AS guardian_address,
        COALESCE(p.occupation, 'N/A') AS guardian_occupation,
        COALESCE(p.status, 'N/A') AS guardian_status,
        COALESCE(ps.relation_type, 'N/A') AS relation_type
      FROM students s
      LEFT JOIN parents p ON s.parent_id = p.parent_id
      LEFT JOIN parent_student ps ON s.student_id = ps.student_id
      LEFT JOIN faculties f ON s.faculty_id = f.faculty_id
      LEFT JOIN departments d ON s.department_id = d.department_id
      LEFT JOIN programs pr ON s.program_id = pr.program_id
      LEFT JOIN class_section sec ON s.section_id = sec.section_id
      LEFT JOIN semester se ON s.semester_id = se.semester_id
      WHERE s.faculty_id = ?
      ORDER BY s.student_id DESC
    ");
    $stmt->execute([$faculty_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
    // ✅ Write each row
    foreach ($rows as $r) {
      // ensure no null values break CSV
      foreach ($r as &$v) {
        if (is_null($v) || $v === '') $v = 'N/A';
      }
      fputcsv($output, $r);
    }
  
    fclose($output);
    exit;
  }

  /* ============================================================
     ✅ IMPORT CSV - Faculty Restricted
  ============================================================ */
  if ($_POST['action'] === 'import_csv' && !empty($_FILES['csv_file']['tmp_name'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
  
    if ($handle !== false) {
      $header = fgetcsv($handle);
      $inserted = 0;
  
      while (($row = fgetcsv($handle)) !== false) {
        $row = array_pad($row, 21, '');
        list(
          $reg_no, $full_name, $gender, $dob, $email, $phone, $address,
          $faculty_name, $department_name, $program_name, $section_name, $semester_name,
          $status_csv, $g_name, $g_gender, $g_phone, $g_email, $g_address,
          $g_occupation, $g_status_csv, $relation
        ) = array_map('trim', $row);
  
        if (empty($reg_no) || empty($full_name)) continue;
  
        $status = 'active';
        $g_status = 'active';

        /* ================= DEPARTMENT ================= */
        $department_id = null;
        if (!empty($department_name) && strtolower($department_name) !== 'select') {
          $q = $pdo->prepare("SELECT department_id FROM departments WHERE department_name=? AND faculty_id=? LIMIT 1");
          $q->execute([$department_name, $faculty_id]);
          $department_id = $q->fetchColumn();
          if (!$department_id) {
            // Only create department if it belongs to current faculty
            $pdo->prepare("INSERT INTO departments (department_name, faculty_id) VALUES (?, ?)")->execute([$department_name, $faculty_id]);
            $department_id = $pdo->lastInsertId();
          }
        }

        /* ================= PROGRAM ================= */
        $program_id = null;
        if (!empty($program_name) && strtolower($program_name) !== 'select') {
          $q = $pdo->prepare("SELECT program_id FROM programs WHERE program_name=? AND department_id IN (SELECT department_id FROM departments WHERE faculty_id=?) LIMIT 1");
          $q->execute([$program_name, $faculty_id]);
          $program_id = $q->fetchColumn();
          if (!$program_id && $department_id) {
            $pdo->prepare("INSERT INTO programs (program_name, department_id) VALUES (?, ?)")->execute([$program_name, $department_id]);
            $program_id = $pdo->lastInsertId();
          }
        }

        /* ================= SECTION ================= */
        $section_id = null;
        if (!empty($section_name) && strtolower($section_name) !== 'select') {
          $q = $pdo->prepare("SELECT section_id FROM class_section WHERE section_name=? AND program_id IN (SELECT program_id FROM programs WHERE department_id IN (SELECT department_id FROM departments WHERE faculty_id=?)) LIMIT 1");
          $q->execute([$section_name, $faculty_id]);
          $section_id = $q->fetchColumn();
          if (!$section_id && $program_id) {
            $pdo->prepare("INSERT INTO class_section (section_name, program_id) VALUES (?, ?)")->execute([$section_name, $program_id]);
            $section_id = $pdo->lastInsertId();
          }
        }

        /* ================= SEMESTER ================= */
        $semester_id = null;
        if (!empty($semester_name) && strtolower($semester_name) !== 'select') {
          $q = $pdo->prepare("SELECT semester_id FROM semester WHERE semester_name=? LIMIT 1");
          $q->execute([$semester_name]);
          $semester_id = $q->fetchColumn();
          if (!$semester_id) {
            $pdo->prepare("INSERT INTO semester (semester_name) VALUES (?)")->execute([$semester_name]);
            $semester_id = $pdo->lastInsertId();
          }
        }

        /* ================= PARENT ================= */
        $parent_id = null;
        $g_phone_clean = trim($g_phone);

        // ✅ 1. Check by phone (priority)
        if (!empty($g_phone_clean)) {
          $q = $pdo->prepare("SELECT parent_id FROM parents WHERE phone=? LIMIT 1");
          $q->execute([$g_phone_clean]);
          $parent_id = $q->fetchColumn();
        }

        // ✅ 2. Check by name if phone not found
        if (!$parent_id && !empty($g_name)) {
          $q = $pdo->prepare("SELECT parent_id FROM parents WHERE full_name=? LIMIT 1");
          $q->execute([$g_name]);
          $parent_id = $q->fetchColumn();
        }

        // ✅ 3. Create new parent if not found
        if (!$parent_id) {
          $phone_for_db = !empty($g_phone_clean) ? $g_phone_clean : null;
          $email_for_parent = !empty($g_email) ? $g_email : strtolower(str_replace(' ', '', $g_name ?: 'parent')) . '@example.com';

          // handle duplicate parent email
          $chkP = $pdo->prepare("SELECT COUNT(*) FROM parents WHERE email=?");
          $chkP->execute([$email_for_parent]);
          if ($chkP->fetchColumn()) {
            $email_for_parent = uniqid('parent_') . '@example.com';
          }

          $pdo->prepare("
            INSERT INTO parents (full_name, gender, phone, email, address, occupation, status, created_at)
            VALUES (?,?,?,?,?,?,?,NOW())
          ")->execute([
            $g_name ?: 'Unknown Parent',
            $g_gender ?: 'N/A',
            $phone_for_db,
            $email_for_parent,
            $g_address ?: '',
            $g_occupation ?: '',
            $g_status
          ]);
          $parent_id = $pdo->lastInsertId();
        }

        /* ================= STUDENT ================= */
        $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE reg_no=?");
        $chk->execute([$reg_no]);

        if (!$chk->fetchColumn()) {
          // ✅ Safe email
          $email_clean = trim($email);
          if (empty($email_clean)) {
            $email_clean = null;
          } else {
            $chkEmail = $pdo->prepare("SELECT COUNT(*) FROM students WHERE email=?");
            $chkEmail->execute([$email_clean]);
            if ($chkEmail->fetchColumn()) {
              $email_clean = uniqid('student_') . '@example.com';
            }
          }

          $pdo->prepare("
            INSERT INTO students
            (student_uuid, parent_id, faculty_id, department_id, program_id,
             reg_no, full_name, gender, dob, phone_number, email, address,
             section_id, semester_id, status, created_at)
            VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
          ")->execute([
            $parent_id,
            $faculty_id, $department_id, $program_id,
            $reg_no, $full_name, $gender ?: 'N/A',
            $dob ?: '2000-01-01',
            $phone ?: '',
            $email_clean,
            $address ?: '',
            $section_id, $semester_id, $status
          ]);

          $sid = $pdo->lastInsertId();

          // ✅ Parent-student link
          $pdo->prepare("
            INSERT INTO parent_student (parent_id, student_id, relation_type, created_at)
            VALUES (?,?,?,NOW())
          ")->execute([$parent_id, $sid, $relation ?: 'guardian']);

          $inserted++;
        }
      }

      fclose($handle);
      $message = "✅ Imported $inserted student(s) successfully!";
      $type = "success";
    } else {
      $message = "❌ Unable to read CSV file!";
      $type = "error";
    }
  }
}

/* ============================================================
   ✅ CRUD OPERATIONS (Add / Update / Delete) - Faculty Restricted
============================================================ */
if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  isset($_POST['action']) &&
  !in_array($_POST['action'], ['export_csv', 'import_csv'])
) {
  try {
    $pdo->beginTransaction();

    /* =====================================================
       DELETE STUDENT - Faculty Restricted
    ===================================================== */
    if ($_POST['action'] === 'delete') {
      $student_id = $_POST['student_id'];
      
      // Verify student belongs to faculty before deletion
      $check = $pdo->prepare("SELECT faculty_id FROM students WHERE student_id=?");
      $check->execute([$student_id]);
      $student_faculty = $check->fetchColumn();
      
      if ($student_faculty != $faculty_id) {
        throw new Exception("❌ You can only delete students from your faculty!");
      }
      
      $pdo->prepare("DELETE FROM users WHERE linked_id=? AND linked_table='student'")
          ->execute([$student_id]);
      $pdo->prepare("DELETE FROM students WHERE student_id=?")
          ->execute([$student_id]);
      $message = "🗑️ Student deleted successfully!";
    }

    /* =====================================================
       ADD / UPDATE STUDENT - Faculty Restricted
    ===================================================== */
    else {
      if (empty($_POST['reg_no']) || empty($_POST['full_name']) || empty($_POST['guardian_name'])) {
        throw new Exception("Missing required fields!");
      }

      // ✅ Prevent duplicate registration number
      if ($_POST['action'] === 'add') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM students WHERE reg_no=?");
        $check->execute([$_POST['reg_no']]);
        if ($check->fetchColumn() > 0) {
          throw new Exception("❌ Registration number already exists!");
        }
      } else {
        // For update, verify student belongs to faculty
        $check = $pdo->prepare("SELECT faculty_id FROM students WHERE student_id=?");
        $check->execute([$_POST['student_id']]);
        $student_faculty = $check->fetchColumn();
        
        if ($student_faculty != $faculty_id) {
          throw new Exception("❌ You can only update students from your faculty!");
        }
      }

      // ✅ Parent handling
      $q = $pdo->prepare("SELECT * FROM parents WHERE full_name=? OR phone=? LIMIT 1");
      $q->execute([$_POST['guardian_name'], $_POST['guardian_phone']]);
      $p = $q->fetch(PDO::FETCH_ASSOC);
      $pid = $p['parent_id'] ?? null;

      if (!$pid) {
        $pdo->prepare("
          INSERT INTO parents (full_name, gender, phone, email, address, occupation, status, created_at)
          VALUES (?,?,?,?,?,?,?,NOW())
        ")->execute([
          $_POST['guardian_name'],
          $_POST['guardian_gender'],
          $_POST['guardian_phone'],
          $_POST['guardian_email'] ?: strtolower(str_replace(' ', '', $_POST['guardian_name'])) . '@example.com',
          $_POST['guardian_address'],
          $_POST['guardian_occupation'],
          $_POST['guardian_status'] ?? 'active'
        ]);
        $pid = $pdo->lastInsertId();
      }

      // ✅ Auto-create parent user if not exists
      $checkParent = $pdo->prepare("SELECT user_id FROM users WHERE linked_id=? AND linked_table='parent'");
      $checkParent->execute([$pid]);
      if (!$checkParent->fetch()) {
        $plainPass = '123';
        $pdo->prepare("
          INSERT INTO users 
          (user_uuid, username, email, phone_number, profile_photo_path, password, password_plain, role, linked_id, linked_table, status, created_at)
          VALUES (UUID(),?,?,?,?,?,?,?,?,?,'active',NOW())
        ")->execute([
          $_POST['guardian_name'],
          $_POST['guardian_email'] ?: strtolower(str_replace(' ', '', $_POST['guardian_name'])) . '@example.com',
          $_POST['guardian_phone'],
          'upload/profiles/default.png',
          password_hash($plainPass, PASSWORD_BCRYPT),
          $plainPass,
          'parent',
          $pid,
          'parent'
        ]);
      }

      // ✅ Handle student photo upload
      $photo = $_POST['existing_photo'] ?? null;
      if (!empty($_FILES['photo']['name'])) {
        $dir = __DIR__ . '/../upload/profiles/';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $name = uniqid('stu_') . ".$ext";
        $photo = "upload/profiles/$name";
        move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $photo);
      }

      $rel = $_POST['guardian_relation'] ?? 'guardian';
      $status = $_POST['status'] ?? 'active';

      /* =====================================================
         ADD NEW STUDENT - Auto-assign faculty_id
      ===================================================== */
      if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("
          INSERT INTO students 
          (student_uuid, parent_id, faculty_id, department_id, program_id,
           reg_no, full_name, gender, dob, phone_number, email, address,
           photo_path, section_id, semester_id, status, created_at)
          VALUES (UUID(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");

        $stmt->execute([
          $pid,
          $faculty_id, // Auto-assign current faculty
          $_POST['department_id'],
          $_POST['program_id'],
          $_POST['reg_no'],
          $_POST['full_name'],
          $_POST['gender'],
          $_POST['dob'],
          $_POST['phone_number'],
          $_POST['email'],
          $_POST['address'],
          $photo,
          $_POST['section_id'],
          $_POST['semester_id'],
          $status
        ]);

        $sid = $pdo->lastInsertId();

        // ✅ Parent-student link
        $pdo->prepare("
          INSERT INTO parent_student (parent_id, student_id, relation_type, created_at)
          VALUES (?,?,?,NOW())
        ")->execute([$pid, $sid, $rel]);

        // ✅ Auto-create student user
        $plainPass = '123';
        $pdo->prepare("
          INSERT INTO users 
          (user_uuid, username, email, phone_number, profile_photo_path, password, password_plain, role, linked_id, linked_table, status, created_at)
          VALUES (UUID(),?,?,?,?,?,?,?,?,?,'active',NOW())
        ")->execute([
          $_POST['reg_no'], // username
          $_POST['email'] ?: strtolower(str_replace(' ', '', $_POST['full_name'])) . '@example.com',
          $_POST['phone_number'],
          $photo ?: 'upload/profiles/default.png',
          password_hash($plainPass, PASSWORD_BCRYPT),
          $plainPass,
          'student',
          $sid,
          'student'
        ]);

        $message = "✅ Student Added Successfully!";
      }

      /* =====================================================
         UPDATE EXISTING STUDENT
      ===================================================== */
      elseif ($_POST['action'] === 'update') {
        $stmt = $pdo->prepare("
          UPDATE students SET 
            parent_id=?, department_id=?, program_id=?, 
            reg_no=?, full_name=?, gender=?, dob=?, phone_number=?, email=?, address=?, 
            photo_path=?, section_id=?, semester_id=?, status=? 
          WHERE student_id=?
        ");

        $stmt->execute([
          $pid,
          $_POST['department_id'],
          $_POST['program_id'],
          $_POST['reg_no'],
          $_POST['full_name'],
          $_POST['gender'],
          $_POST['dob'],
          $_POST['phone_number'],
          $_POST['email'],
          $_POST['address'],
          $photo,
          $_POST['section_id'],
          $_POST['semester_id'],
          $status,
          $_POST['student_id']
        ]);

        // ✅ Replace or update parent-student relation
        $pdo->prepare("
          REPLACE INTO parent_student (parent_id, student_id, relation_type, created_at)
          VALUES (?,?,?,NOW())
        ")->execute([$pid, $_POST['student_id'], $rel]);

        // ✅ Ensure student user exists
        $chkStu = $pdo->prepare("SELECT user_id FROM users WHERE linked_id=? AND linked_table='student'");
        $chkStu->execute([$_POST['student_id']]);
        if (!$chkStu->fetch()) {
          $plainPass = '123';
          $pdo->prepare("
            INSERT INTO users 
            (user_uuid, username, email, phone_number, profile_photo_path, password, password_plain, role, linked_id, linked_table, status, created_at)
            VALUES (UUID(),?,?,?,?,?,?,?,?,?,'active',NOW())
          ")->execute([
            $_POST['reg_no'],
            $_POST['email'] ?: strtolower(str_replace(' ', '', $_POST['full_name'])) . '@example.com',
            $_POST['phone_number'],
            $photo ?: 'upload/profiles/default.png',
            password_hash($plainPass, PASSWORD_BCRYPT),
            $plainPass,
            'student',
            $_POST['student_id'],
            'student'
          ]);
        }

        // ✅ Sync status with users table
        $pdo->prepare("UPDATE users SET status=? WHERE linked_id=? AND linked_table='student'")
             ->execute([$status, $_POST['student_id']]);

        $message = "✏️ Student Updated Successfully!";
      }
    }

    $pdo->commit();
    $type = "success";
  } catch (Exception $e) {
    $pdo->rollBack();
    $message = "❌ " . $e->getMessage();
    $type = "error";
  }
}

/* ============================================================
   FETCH DATA - Faculty Restricted
============================================================ */
// Get faculty name for display
$faculty_stmt = $pdo->prepare("SELECT faculty_name FROM faculties WHERE faculty_id = ?");
$faculty_stmt->execute([$faculty_id]);
$faculty_name = $faculty_stmt->fetchColumn();

// Get departments for current faculty
$departments = $pdo->prepare("SELECT * FROM departments WHERE faculty_id = ? ORDER BY department_name ASC");
$departments->execute([$faculty_id]);
$departments = $departments->fetchAll(PDO::FETCH_ASSOC);

// Get programs for current faculty
$programs = $pdo->prepare("
  SELECT p.* FROM programs p 
  JOIN departments d ON p.department_id = d.department_id 
  WHERE d.faculty_id = ? 
  ORDER BY p.program_name ASC
");
$programs->execute([$faculty_id]);
$programs = $programs->fetchAll(PDO::FETCH_ASSOC);

// Get sections for current faculty
$sections = $pdo->prepare("
  SELECT cs.* FROM class_section cs
  JOIN programs p ON cs.program_id = p.program_id
  JOIN departments d ON p.department_id = d.department_id
  WHERE d.faculty_id = ?
  ORDER BY cs.section_name ASC
");
$sections->execute([$faculty_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

$semesters = $pdo->query("SELECT * FROM semester ORDER BY semester_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get students for current faculty only
$search = trim($_GET['q'] ?? '');
$sql = "SELECT s.*,p.full_name AS parent_name,p.phone AS parent_phone,ps.relation_type,
  p.gender AS guardian_gender,p.email AS guardian_email,p.address AS guardian_address,
  p.occupation AS guardian_occupation,p.status AS guardian_status,
  u.password AS student_password
  FROM students s
  LEFT JOIN parents p ON s.parent_id=p.parent_id
  LEFT JOIN parent_student ps ON s.student_id=ps.student_id
  LEFT JOIN users u ON s.student_id=u.linked_id AND u.linked_table='student'
  WHERE s.faculty_id = ?";

if ($search !== '') {
  $sql .= " AND (s.full_name LIKE ? OR s.reg_no LIKE ?)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$faculty_id, "%$search%", "%$search%"]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $sql .= " ORDER BY s.student_id DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$faculty_id]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

include('../includes/header.php');
?>

<div class="main-content">
  <?php if($message): ?>
  <div class="alert <?= $type=='success'?'alert-success':'alert-error' ?>"><?= htmlspecialchars($message) ?></div>
  <script>setTimeout(()=>document.querySelector('.alert').classList.add('hide'),3000);</script>
  <?php endif; ?>

  <div class="top-bar">
    <h2>Students Management - <?= htmlspecialchars($faculty_name) ?></h2>
    <div>
      <form method="POST" enctype="multipart/form-data" style="display:inline;">
        <input type="file" name="csv_file" accept=".csv" required>
        <input type="hidden" name="action" value="import_csv">
        <button class="btn blue">Import CSV</button>
      </form>
      
      <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="export_csv">
        <button class="btn green">Export CSV</button>
      </form>
      <button class="btn green" onclick="openModal('addModal')">+ Add Student</button>
    </div>
  </div>

  <form method="GET" style="
  margin-top:10px;
  display:flex;
  align-items:center;
  gap:8px;
  justify-content:flex-start;
  padding:5px 0;
">
  <input 
    type="text" 
    name="q" 
    value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
    placeholder="🔍 Search by Reg No or Name..."
    style="
      width:230px;
      padding:6px 10px;
      border-radius:6px;
      border:1px solid #ccc;
      font-size:13px;
      outline:none;
      transition:all 0.3s ease;
    "
    onfocus="this.style.borderColor='#0072CE';"
    onblur="this.style.borderColor='#ccc';"
  >
  <button 
    class="btn blue" 
    style="
      padding:6px 12px;
      font-size:13px;
      border-radius:6px;
      cursor:pointer;
      transition:background 0.3s ease;
    "
  >Search</button>
</form>

<div class="table-responsive">
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Reg No</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Parent</th>
        <th>Relation</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if(empty($students)): ?>
        <tr>
          <td colspan="9" style="text-align:center;padding:20px;">No students found in your faculty.</td>
        </tr>
      <?php else: ?>
        <?php foreach($students as $i=>$s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td><?= htmlspecialchars($s['reg_no']) ?></td>
          <td><?= htmlspecialchars($s['full_name']) ?></td>
          <td><?= htmlspecialchars($s['email']) ?></td>
          <td><?= htmlspecialchars($s['phone_number']) ?></td>
          <td><?= htmlspecialchars($s['parent_name']) ?></td>
          <td><?= htmlspecialchars($s['relation_type']?:'-') ?></td>
          <td style="color:<?= $s['status']=='active'?'green':'red' ?>"><?= ucfirst($s['status']) ?></td>
          <td>
            <button class="btn blue" onclick='editStudent(<?= json_encode($s) ?>)'><i class="fa fa-edit"></i></button>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="student_id" value="<?= $s['student_id'] ?>">
              <input type="hidden" name="action" value="delete">
              <button class="btn red" onclick="return confirm('Delete this student?')"><i class="fa fa-trash"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- ================================
     ADD / EDIT STUDENT MODAL FORM
================================ -->
<div class="modal" id="addModal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
    <h2 id="formTitle">Add Student & Parent</h2>

    <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm(this)">
      <input type="hidden" name="action" id="formAction" value="add">
      <input type="hidden" name="student_id" id="student_id">
      <input type="hidden" name="existing_photo" id="existing_photo">
      <input type="hidden" name="faculty_id" value="<?= $faculty_id ?>">

      <div><label>Reg No*</label><input name="reg_no" id="reg_no" required></div>
      <div><label>Full Name*</label><input name="full_name" id="full_name" required></div>
      <div><label>Gender*</label>
        <select name="gender" id="gender" required>
          <option value="">Select</option>
          <option>male</option>
          <option>female</option>
        </select>
      </div>
      <div><label>DOB</label><input type="date" name="dob" id="dob"></div>
      <div><label>Email</label><input type="email" name="email" id="email"></div>
      <div><label>Phone</label><input name="phone_number" id="phone_number"></div>
      <div><label>Address</label><input name="address" id="address"></div>

      <div>
        <label>Faculty</label>
        <div style="padding:8px; background:#f0f0f0; border-radius:6px; border:1px solid #ccc;">
          <strong><?= htmlspecialchars($faculty_name) ?></strong>
        </div>
      </div>

      <div>
        <label>Department*</label>
        <select name="department_id" id="department" required onchange="loadRelated(this.value,'program')">
          <option value="">Select</option>
          <?php foreach($departments as $d): ?>
            <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>Program*</label>
        <select name="program_id" id="program" required onchange="loadRelated(this.value,'section')">
          <option value="">Select</option>
        </select>
      </div>

      <div>
        <label>Section*</label>
        <select name="section_id" id="section" required>
          <option value="">Select</option>
        </select>
      </div>

      <div><label>Semester*</label>
        <select name="semester_id" id="semester" required>
          <option value="">Select</option>
          <?php foreach($semesters as $sm): ?>
            <option value="<?= $sm['semester_id'] ?>"><?= htmlspecialchars($sm['semester_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div><label>Status</label>
        <select name="status" id="status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <!-- Guardian Section -->
      <div><label>Guardian Name*</label><input name="guardian_name" id="guardian_name" required onkeyup="getParentInfo(this.value)"></div>
      <div><label>Gender*</label>
        <select name="guardian_gender" id="guardian_gender" required>
          <option value="">Select</option>
          <option>male</option>
          <option>female</option>
        </select>
      </div>
      <div><label>Phone</label><input name="guardian_phone" id="guardian_phone"></div>
      <div><label>Email</label><input type="email" name="guardian_email" id="guardian_email"></div>
      <div><label>Address</label><input name="guardian_address" id="guardian_address"></div>
      <div><label>Occupation</label><input name="guardian_occupation" id="guardian_occupation"></div>
      <div><label>Status</label>
        <select name="guardian_status" id="guardian_status">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div><label>Relation*</label>
        <select name="guardian_relation" id="guardian_relation" required>
          <option value="">Select</option>
          <option value="father">Father</option>
          <option value="mother">Mother</option>
          <option value="sister">Sister</option>
          <option value="brother">Brother</option>
          <option value="guardian">Guardian</option>
          <option value="other">Other</option>
        </select>
      </div>

      <input type="hidden" name="guardian_parent_id" id="guardian_parent_id">

      <div><label>Photo</label><input type="file" name="photo" id="photo" accept="image/*"></div>

      <button class="btn green save-btn">Save Record</button>
    </form>
  </div>
</div>

<!-- ================================
     CSS DESIGN
================================ -->
<style>
body {
  font-family:'Poppins',sans-serif;
  background:#f7f9fb;
  margin:0;
}
.main-content {
  padding:25px;
  margin-left:250px;
  margin-top:90px;
  transition:margin-left 0.3s ease;
}

.sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

.top-bar {
  display:flex;
  justify-content:space-between;
  align-items:center;
  background:#fff;
  padding:15px 20px;
  border-radius:10px;
  box-shadow:0 2px 6px rgba(0,0,0,0.08);
}
.top-bar h2 { color:#0072CE; margin:0; }
.btn {
  border:none;
  padding:8px 14px;
  border-radius:6px;
  font-weight:600;
  cursor:pointer;
  margin:2px;
  font-size:13px;
}
.btn.blue{background:#0072CE;color:#fff;}
.btn.green{background:#00843D;color:#fff;}
.btn.red{background:#C62828;color:#fff;}
.alert {
  position:fixed;
  top:50%;
  left:50%;
  transform:translate(-50%,-50%);
  padding:20px 25px;
  border-radius:10px;
  color:#fff;
  font-weight:600;
  z-index:9999;
  animation:fadeIn .4s ease;
}
.alert.hide{opacity:0;transition:opacity .5s ease;}
.alert-success{background:#00843D;}
.alert-error{background:#C62828;}
@keyframes fadeIn {
  from{opacity:0;transform:translate(-50%,-60%);}
  to{opacity:1;transform:translate(-50%,-50%);}
}

.table-responsive {
  width: 100%;
  overflow-x: auto;
  overflow-y: auto;
  max-height: 450px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  margin-top: 10px;
}

thead th {
  position: sticky;
  top: 0;
  background: #0072CE;
  color: #fff;
  z-index: 2;
}

table {
  width:100%;
  border-collapse:collapse;
  margin-top:15px;
  background:#fff;
  box-shadow:0 2px 6px rgba(0,0,0,.08);
}
th,td {
  padding:10px 12px;
  text-align:left;
  border-bottom:1px solid #eee;
  font-size:14px;
}
thead th { background:#0072CE; color:#fff; }
.modal {
  display:none;
  position:fixed;
  top:0; left:0;
  width:100%; height:100%;
  background:rgba(0,0,0,0.4);
  justify-content:center;
  align-items:center;
  z-index:9999;
}
.modal.show{display:flex;}
.modal-content {
  background:#fff;
  width:95%;
  max-width:1100px;
  border-radius:12px;
  padding:25px;
  position:relative;
  overflow-y:auto;
  max-height:90vh;
}
.close-modal {
  position:absolute;
  top:10px; right:15px;
  font-size:22px;
  cursor:pointer;
}
form {
  display:grid;
  grid-template-columns:repeat(4,1fr);
  gap:15px;
}
form label {
  font-weight:600;
  color:#0072CE;
  font-size:13px;
}
form input, form select {
  width:100%;
  padding:8px;
  border:1px solid #ccc;
  border-radius:6px;
  font-size:13px;
}
.save-btn {
  grid-column:span 4;
  width:100%;
}
</style>

<!-- ================================
     JAVASCRIPT SECTION
================================ -->
<script>
function openModal(id){
  const m=document.getElementById(id);
  m.classList.add('show');
  document.getElementById('formAction').value='add';
  document.getElementById('formTitle').innerText='Add Student';
  m.querySelector('form').reset();
  document.getElementById('student_id').value='';
  
  // Reset dynamic dropdowns
  document.getElementById('program').innerHTML = '<option value="">Select</option>';
  document.getElementById('section').innerHTML = '<option value="">Select</option>';
}

function closeModal(id){
  document.getElementById(id).classList.remove('show');
}

function validateForm(f){
  for(let i of f.querySelectorAll('[required]')){
    if(!i.value.trim()){
      alert('⚠️ Please fill all required fields!');
      i.focus();
      return false;
    }
  }
  return true;
}

// AUTO-FILL PARENT DETAILS
function getParentInfo(n) {
  if (n.length < 3) return;

  fetch('?ajax=get_parent&name=' + encodeURIComponent(n))
    .then(r => r.json())
    .then(p => {
      if (p.parent_id) {
        guardian_gender.value = p.gender || '';
        guardian_phone.value = p.phone || '';
        guardian_email.value = p.email || '';
        guardian_address.value = p.address || '';
        guardian_occupation.value = p.occupation || '';
        guardian_status.value = p.status || 'active';
        guardian_parent_id.value = p.parent_id;
        
        guardian_name.style.border = '2px solid green';
        guardian_name.title = 'Existing parent found (auto-filled)';
        guardian_name.dataset.parentId = p.parent_id;
      } else {
        guardian_gender.value = '';
        guardian_phone.value = '';
        guardian_email.value = '';
        guardian_address.value = '';
        guardian_occupation.value = '';
        guardian_status.value = 'active';
        guardian_name.style.border = '1px solid #ccc';
        guardian_name.title = '';
        delete guardian_name.dataset.parentId;
        guardian_parent_id.value = '';
      }
    });
}

// CASCADING DROPDOWNS
async function loadRelated(id, type, value='') {
  if (!id) return;
  const res = await fetch(`?ajax=get_related&type=${type}&id=${id}`);
  const data = await res.json();

  const sel = document.getElementById(type);
  sel.innerHTML = '<option value="">Select</option>';

  data.forEach(x => {
    const opt = document.createElement('option');
    opt.value = x.id;
    opt.textContent = x.name;
    sel.appendChild(opt);
  });

  if (value) sel.value = value;

  // Reset only lower levels
  if (type === 'department') {
    ['program','section'].forEach(k => document.getElementById(k).innerHTML = '<option value="">Select</option>');
  } else if (type === 'program') {
    document.getElementById('section').innerHTML = '<option value="">Select</option>';
  }
}

// EDIT STUDENT
async function editStudent(s){
  openModal('addModal');
  document.getElementById('formTitle').innerText='Edit Student';
  document.getElementById('formAction').value='update';
  student_id.value=s.student_id;
  existing_photo.value=s.photo_path||'';
  reg_no.value=s.reg_no||'';
  full_name.value=s.full_name||'';
  gender.value=s.gender||'';
  dob.value=s.dob||'';
  email.value=s.email||'';
  phone_number.value=s.phone_number||'';
  address.value=s.address||'';
  status.value=s.status||'active';

  guardian_name.value=s.parent_name||'';
  guardian_gender.value=s.guardian_gender||'';
  guardian_phone.value=s.parent_phone||'';
  guardian_email.value=s.guardian_email||'';
  guardian_address.value=s.guardian_address||'';
  guardian_occupation.value=s.guardian_occupation||'';
  guardian_status.value=s.guardian_status||'active';
  guardian_relation.value=s.relation_type||'guardian';

  department.value=s.department_id||'';
  await loadRelated(s.department_id,'program',s.program_id);
  await loadRelated(s.program_id,'section',s.section_id);
  semester.value=s.semester_id||'';
}
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>