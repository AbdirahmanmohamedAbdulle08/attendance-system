<?php
/*******************************************************************************************
 * ALL-IN-ONE UNIVERSITY REPORT — Hormuud University
 * Gathers data from all major tables for management-level summary
 * Author: ChatGPT 2025 | PHP 8.2 | PDO Secure | Export to PDF & Excel
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

$role = strtolower($_SESSION['user']['role'] ?? '');
$user_linked_id = $_SESSION['user']['linked_id'] ?? null;

if (!in_array($role, ['super_admin', 'campus_admin'])) {
  header("Location: ../login.php");
  exit;
}

// Campus filter
if ($role === 'campus_admin') {
  $campus_id = $user_linked_id; // campus_admin wuxuu leeyahay hal campus oo uu xakamo
} else {
  $campus_id = null; // super_admin wuu arkaa dhammaan
}


date_default_timezone_set('Africa/Nairobi');

/* ================= FETCH SUMMARY DATA ================= */
$stats = [];

// ✅ Academic Info
$stats['academic_year']   = $pdo->query("SELECT COUNT(*) FROM academic_year")->fetchColumn();
$stats['academic_term']   = $pdo->query("SELECT COUNT(*) FROM academic_term")->fetchColumn();
$stats['semester']        = $pdo->query("SELECT COUNT(*) FROM semester")->fetchColumn();

// ✅ Structure Info
$stats['campus']          = $pdo->query("SELECT COUNT(*) FROM campus")->fetchColumn();
$stats['faculties']       = $pdo->query("SELECT COUNT(*) FROM faculties")->fetchColumn();
$stats['departments']     = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$stats['programs']        = $pdo->query("SELECT COUNT(*) FROM programs")->fetchColumn();
$stats['sections']        = $pdo->query("SELECT COUNT(*) FROM class_section")->fetchColumn();
$stats['rooms']           = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();

// ✅ People Info
if ($role === 'campus_admin') {
    if ($role === 'campus_admin') {
        // campus_id = linked_id ee userka hadda log galay
        $campus_id = $_SESSION['user']['linked_id'];
      
        $stmt = $pdo->prepare("
          SELECT COUNT(*) 
          FROM teachers t
          JOIN users u ON u.linked_table = 'teacher' AND u.linked_id = t.teacher_id
          JOIN campus c ON c.campus_id = :campus_id
          WHERE c.campus_id = :campus_id
        ");
        $stmt->execute(['campus_id' => $campus_id]);
        $stats['teachers'] = $stmt->fetchColumn();
      
      } else {
        $stats['teachers'] = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
      }
      
  
    $stats['students'] = $pdo->prepare("SELECT COUNT(*) FROM students WHERE campus_id=?");
    $stats['students']->execute([$campus_id]);
    $stats['students'] = $stats['students']->fetchColumn();
  
    if ($role === 'campus_admin') {
        $campus_id = $_SESSION['user']['linked_id'];
    
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT p.parent_id)
            FROM parents p
            JOIN students s ON s.parent_id = p.parent_id
            WHERE s.campus_id = ?
        ");
        $stmt->execute([$campus_id]);
        $stats['parents'] = $stmt->fetchColumn();
    
    } else {
        $stats['parents'] = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
    }
    
  } else {
    // original global counts
    $stats['teachers'] = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $stats['students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $stats['parents'] = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
  }
  
$stats['parents']         = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
$stats['users']           = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// ✅ Attendance Info
$stats['student_att']     = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
$stats['teacher_att']     = $pdo->query("SELECT COUNT(*) FROM teacher_attendance")->fetchColumn();
$stats['promotion']       = $pdo->query("SELECT COUNT(*) FROM promotion_history")->fetchColumn();
$stats['audit_log']       = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();

/* ================= DETAILED REPORTS ================= */
if ($role === 'campus_admin') {
    $stmt = $pdo->prepare("
      SELECT s.reg_no, s.full_name, p.program_name, d.department_name, f.faculty_name
      FROM students s
      LEFT JOIN class_section cs ON cs.section_id = s.section_id
      LEFT JOIN programs p ON p.program_id = cs.program_id
      LEFT JOIN departments d ON d.department_id = p.department_id
      LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
      WHERE s.campus_id = :campus_id
      ORDER BY s.full_name ASC
      LIMIT 50
    ");
    $stmt->execute(['campus_id' => $campus_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $students = $pdo->query("
      SELECT s.reg_no, s.full_name, p.program_name, d.department_name, f.faculty_name
      FROM students s
      LEFT JOIN class_section cs ON cs.section_id = s.section_id
      LEFT JOIN programs p ON p.program_id = cs.program_id
      LEFT JOIN departments d ON d.department_id = p.department_id
      LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
      ORDER BY s.full_name ASC
      LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
  

  if ($role === 'campus_admin') {
    $campus_id = $_SESSION['user']['linked_id'];
  
    $stmt = $pdo->prepare("
      SELECT DISTINCT t.teacher_name, t.email, t.phone_number, t.qualification
      FROM teachers t
      JOIN users u ON u.linked_table = 'teacher' AND u.linked_id = t.teacher_id
      JOIN students s ON s.campus_id = :campus_id
      ORDER BY t.teacher_name ASC
      LIMIT 50
    ");
    $stmt->execute(['campus_id' => $campus_id]);
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  } else {
    $teachers = $pdo->query("
      SELECT teacher_name, email, phone_number, qualification
      FROM teachers
      ORDER BY teacher_name ASC
      LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
  
  
$attendance_summary = $pdo->query("
  SELECT COUNT(*) AS total, 
         SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS present,
         SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END) AS absent
  FROM attendance
")->fetch(PDO::FETCH_ASSOC);

if ($role === 'campus_admin') {
    $stmt = $pdo->prepare("
      SELECT p.program_name, COUNT(s.student_id) AS total_students
      FROM programs p
      LEFT JOIN class_section c ON c.program_id=p.program_id
      LEFT JOIN students s ON s.section_id=c.section_id
      WHERE s.campus_id = :campus_id
      GROUP BY p.program_id
      ORDER BY p.program_name ASC
    ");
    $stmt->execute(['campus_id' => $campus_id]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $programs = $pdo->query("
      SELECT p.program_name, COUNT(s.student_id) AS total_students
      FROM programs p
      LEFT JOIN class_section c ON c.program_id=p.program_id
      LEFT JOIN students s ON s.section_id=c.section_id
      GROUP BY p.program_id
      ORDER BY p.program_name ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
  }
  

$active_term = $pdo->query("SELECT term_name, status FROM academic_term WHERE status='active'")->fetch(PDO::FETCH_ASSOC);

/* ================= EXPORT ================= */
if (isset($_GET['export']) && isset($_GET['type'])) {
  $type = $_GET['type'];
  $filename = "University_Report_" . date('Y-m-d');
  $data = [
    "Active Term" => $active_term,
    "Stats" => $stats,
    "Attendance" => $attendance_summary,
    "Programs" => $programs
  ];

  if ($type === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename={$filename}.csv");
    $out = fopen("php://output", "w");
    fputcsv($out, ["CATEGORY", "DETAILS"]);
    foreach ($data as $k => $v) {
      fputcsv($out, [$k, json_encode($v)]);
    }
    fclose($out);
    exit;
  }
  elseif ($type === 'pdf') {
    require_once(__DIR__ . '/../libs/fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Hormuud University - All-in-One Report',0,1,'C');
    $pdf->SetFont('Arial','',12);
    $pdf->Ln(5);
    foreach ($data as $title => $arr) {
      $pdf->SetFont('Arial','B',12);
      $pdf->Cell(0,8,$title,0,1);
      $pdf->SetFont('Arial','',10);
      foreach ($arr as $k => $v) {
        if (is_array($v)) $v = json_encode($v);
        $pdf->MultiCell(0,6," - $k: $v",0,'L');
      }
      $pdf->Ln(3);
    }
    $pdf->Output();
    exit;
  }
}

include('../includes/header.php');
?>
<style>
  .sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

</style>
<div class="main-content">
  <div class="page-header">
    <h1><i class="fa fa-chart-pie"></i> University All-in-One Report</h1>
  </div>

  <div class="summary-grid">
    <?php foreach($stats as $label=>$value): ?>
      <div class="card">
        <h3><?= strtoupper(str_replace('_',' ',$label)) ?></h3>
        <p><?= number_format($value) ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="export-box">
    <a href="?export=1&type=csv" class="btn blue"><i class="fa fa-file-excel"></i> Download Excel</a>
    <a href="?export=1&type=pdf" class="btn red"><i class="fa fa-file-pdf"></i> Download PDF</a>
  </div>

  <div class="section">
    <h2>📚 Active Academic Term</h2>
    <?php if($active_term): ?>
      <p><strong>Term:</strong> <?= htmlspecialchars($active_term['term_name']) ?> |
         <strong>Status:</strong> <?= htmlspecialchars($active_term['status']) ?></p>
    <?php else: ?><p>No active term found.</p><?php endif; ?>
  </div>

  <div class="section">
    <h2>👨‍🎓 Students (Top 50)</h2>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Reg No</th><th>Name</th><th>Program</th><th>Department</th><th>Faculty</th></tr></thead>
        <tbody>
          <?php foreach($students as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['reg_no']) ?></td>
              <td><?= htmlspecialchars($s['full_name']) ?></td>
              <td><?= htmlspecialchars($s['program_name']) ?></td>
              <td><?= htmlspecialchars($s['department_name']) ?></td>
              <td><?= htmlspecialchars($s['faculty_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section">
    <h2>👨‍🏫 Teachers (Top 50)</h2>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Qualification</th></tr></thead>
        <tbody>
          <?php foreach($teachers as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['teacher_name']) ?></td>
              <td><?= htmlspecialchars($t['email']) ?></td>
              <td><?= htmlspecialchars($t['phone_number']) ?></td>
              <td><?= htmlspecialchars($t['qualification']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="section">
    <h2>📈 Attendance Summary</h2>
    <p><strong>Total:</strong> <?= $attendance_summary['total'] ?> |
       <strong>Present:</strong> <?= $attendance_summary['present'] ?> |
       <strong>Absent:</strong> <?= $attendance_summary['absent'] ?></p>
  </div>

  <div class="section">
    <h2>🎓 Programs Summary</h2>
    <div class="table-wrapper">
      <table>
        <thead><tr><th>Program</th><th>Total Students</th></tr></thead>
        <tbody>
          <?php foreach($programs as $p): ?>
            <tr>
              <td><?= htmlspecialchars($p['program_name']) ?></td>
              <td><?= htmlspecialchars($p['total_students']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ✅ STYLE -->
<style>
body{font-family:'Poppins',sans-serif;background:#f7f9fb;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.page-header h1{color:#0072CE;margin-bottom:15px;}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:25px;}
.card{background:#fff;border-radius:10px;padding:15px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.08);}
.card h3{font-size:13px;color:#0072CE;text-transform:uppercase;}
.card p{font-size:20px;font-weight:700;color:#00843D;margin:5px 0 0;}
.export-box{margin:15px 0;}
.btn{border:none;padding:8px 15px;border-radius:6px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;}
.btn.blue{background:#0072CE;color:#fff;}
.btn.red{background:#C62828;color:#fff;}
.section{margin-top:30px;}
.section h2{color:#00843D;margin-bottom:10px;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;}
tr:hover{background:#f3f8ff;}
@media(max-width:768px){.main-content{margin-left:0;padding:15px;}.summary-grid{grid-template-columns:1fr;}}
</style>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>
