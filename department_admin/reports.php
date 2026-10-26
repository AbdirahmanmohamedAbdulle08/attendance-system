<?php
/*******************************************************************************************
 * DEPARTMENT-SPECIFIC UNIVERSITY REPORT — Hormuud University
 * Department Admin Version - Shows Only Department Data
 * Fully Working & Error-Free
 * Author: ChatGPT 2025
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

$role = strtolower($_SESSION['user']['role'] ?? '');
$user_linked_id = $_SESSION['user']['linked_id'] ?? null;

if ($role !== 'department_admin') {
  header("Location: ../login.php");
  exit;
}

// ✅ Auto set department_id from session
$department_id = $user_linked_id;
if (!$department_id) {
  die("Department ID not found in session. Please login again.");
}

date_default_timezone_set('Africa/Nairobi');

// Get department and faculty info
$dept_stmt = $pdo->prepare("
  SELECT d.department_name, f.faculty_name 
  FROM departments d 
  JOIN faculties f ON d.faculty_id = f.faculty_id 
  WHERE d.department_id = ?
");
$dept_stmt->execute([$department_id]);
$department_info = $dept_stmt->fetch(PDO::FETCH_ASSOC);

if (!$department_info) {
  die("Department not found.");
}

$department_name = $department_info['department_name'];
$faculty_name = $department_info['faculty_name'];

/* ================= FETCH DEPARTMENT-SPECIFIC SUMMARY DATA ================= */
$stats = [];

// ✅ Academic Info
$stats['academic_year'] = $pdo->query("SELECT COUNT(*) FROM academic_year")->fetchColumn();
$stats['academic_term'] = $pdo->query("SELECT COUNT(*) FROM academic_term")->fetchColumn();
$stats['semester']      = $pdo->query("SELECT COUNT(*) FROM semester")->fetchColumn();

// ✅ Structure Info (Department-specific)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE department_id = ?");
$stmt->execute([$department_id]);
$stats['programs'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*) FROM class_section cs 
  JOIN programs p ON p.program_id = cs.program_id 
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['sections'] = $stmt->fetchColumn();

// ✅ People Info (Department-specific)
$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT t.teacher_id)
  FROM teachers t
  JOIN timetable tb ON tb.teacher_id = t.teacher_id
  JOIN class_section cs ON cs.section_id = tb.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['teachers'] = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT s.student_id)
  FROM students s
  JOIN class_section cs ON cs.section_id = s.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['students'] = $stmt->fetchColumn() ?: 0;

$stmt = $pdo->prepare("
  SELECT COUNT(DISTINCT pr.parent_id)
  FROM parents pr
  JOIN students s ON s.parent_id = pr.parent_id
  JOIN class_section cs ON cs.section_id = s.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['parents'] = $stmt->fetchColumn() ?: 0;

// ✅ Attendance & Other Data (Department-specific)
$stmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM attendance a
  JOIN students s ON s.student_id = a.student_id
  JOIN class_section cs ON cs.section_id = s.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['student_att'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM teacher_attendance ta
  JOIN teachers t ON t.teacher_id = ta.teacher_id
  JOIN timetable tb ON tb.teacher_id = t.teacher_id
  JOIN class_section cs ON cs.section_id = tb.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['teacher_att'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("
  SELECT COUNT(*) 
  FROM promotion_history ph
  JOIN students s ON s.student_id = ph.student_id
  JOIN class_section cs ON cs.section_id = s.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$stats['promotion'] = $stmt->fetchColumn();

/* ================= DETAILED REPORTS ================= */

// 👨‍🎓 Students (Department-specific)
$stmt = $pdo->prepare("
  SELECT s.reg_no, s.full_name, p.program_name, cs.section_name, d.department_name, f.faculty_name
  FROM students s
  JOIN class_section cs ON cs.section_id = s.section_id
  JOIN programs p ON p.program_id = cs.program_id
  JOIN departments d ON d.department_id = p.department_id
  JOIN faculties f ON f.faculty_id = d.faculty_id
  WHERE d.department_id = ?
  ORDER BY s.full_name ASC
  LIMIT 50
");
$stmt->execute([$department_id]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 👨‍🏫 Teachers (Department-specific)
$stmt = $pdo->prepare("
  SELECT DISTINCT t.teacher_name, t.email, t.phone_number, t.qualification, d.department_name, f.faculty_name
  FROM teachers t
  JOIN timetable tb ON tb.teacher_id = t.teacher_id
  JOIN class_section cs ON cs.section_id = tb.section_id
  JOIN programs p ON p.program_id = cs.program_id
  JOIN departments d ON d.department_id = p.department_id
  JOIN faculties f ON f.faculty_id = d.faculty_id
  WHERE d.department_id = ?
  ORDER BY t.teacher_name ASC
  LIMIT 50
");
$stmt->execute([$department_id]);
$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 📊 Attendance Summary (Department-specific)
$stmt = $pdo->prepare("
  SELECT COUNT(*) AS total,
         SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present,
         SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) AS absent
  FROM attendance a
  JOIN students s ON s.student_id = a.student_id
  JOIN class_section cs ON cs.section_id = s.section_id
  JOIN programs p ON p.program_id = cs.program_id
  WHERE p.department_id = ?
");
$stmt->execute([$department_id]);
$attendance_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// 🎓 Program Summary (Department-specific)
$stmt = $pdo->prepare("
  SELECT p.program_name, COUNT(s.student_id) AS total_students, d.department_name, f.faculty_name
  FROM programs p
  LEFT JOIN class_section cs ON cs.program_id = p.program_id
  LEFT JOIN students s ON s.section_id = cs.section_id
  LEFT JOIN departments d ON d.department_id = p.department_id
  LEFT JOIN faculties f ON f.faculty_id = d.faculty_id
  WHERE d.department_id = ?
  GROUP BY p.program_id
  ORDER BY p.program_name ASC
");
$stmt->execute([$department_id]);
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 📚 Sections Summary (Department-specific)
$stmt = $pdo->prepare("
  SELECT cs.section_name, p.program_name, COUNT(s.student_id) AS total_students
  FROM class_section cs
  JOIN programs p ON p.program_id = cs.program_id
  LEFT JOIN students s ON s.section_id = cs.section_id
  WHERE p.department_id = ?
  GROUP BY cs.section_id
  ORDER BY cs.section_name ASC
");
$stmt->execute([$department_id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$active_term = $pdo->query("SELECT term_name, status FROM academic_term WHERE status='active'")->fetch(PDO::FETCH_ASSOC);

/* ================= EXPORT FUNCTIONALITY ================= */
if (isset($_GET['export']) && isset($_GET['type'])) {
  $type = $_GET['type'];
  $filename = "Department_Report_" . str_replace(' ', '_', $department_name) . "_" . date('Y-m-d');
  
  // Prepare export data
  $export_data = [
    "Department Information" => [
      "Department Name" => $department_name,
      "Faculty" => $faculty_name,
      "Department ID" => $department_id,
      "Report Date" => date('Y-m-d H:i:s')
    ],
    "Active Term" => $active_term ?: "No active term",
    "Summary Statistics" => $stats,
    "Attendance Summary" => $attendance_summary,
    "Programs" => $programs,
    "Sections" => $sections
  ];

  if ($type === 'csv') {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename={$filename}.csv");
    
    $output = fopen("php://output", "w");
    
    // Add BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Write headers
    fputcsv($output, ["CATEGORY", "ITEM", "VALUE"]);
    
    // Write data
    foreach ($export_data as $category => $items) {
      if (is_array($items)) {
        foreach ($items as $key => $value) {
          if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
          }
          fputcsv($output, [$category, $key, $value]);
        }
      } else {
        fputcsv($output, [$category, $items]);
      }
    }
    
    fclose($output);
    exit;
  }
}

include('../includes/header.php');
?>

<div class="main-content">
  <div class="page-header">
    <h1>Department Report </h1>
    <div class="department-info">
     
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="summary-grid">
    <?php 
    $card_labels = [
      'programs' => 'Programs',
      'sections' => 'Sections',
      'teachers' => 'Teachers',
      'students' => 'Students',
      'parents' => 'Parents',
      'student_att' => 'Student Attendance',
      'teacher_att' => 'Teacher Attendance',
      'promotion' => 'Promotions'
    ];
    
    foreach($card_labels as $key => $label): 
      if(isset($stats[$key])):
    ?>
      <div class="card">
        <h3><?= htmlspecialchars($label) ?></h3>
        <p><?= number_format($stats[$key]) ?></p>
      </div>
    <?php 
      endif;
    endforeach; 
    ?>
  </div>

  <!-- Export Buttons -->
  <div class="export-box">
    <a href="?export=1&type=csv" class="btn blue" onclick="return confirm('Download CSV report?')">
      <i class="fa fa-file-excel"></i> Download CSV
    </a>
    <div class="report-info">
      <strong>Report Scope:</strong> <?= htmlspecialchars($department_name) ?> Department Only
    </div>
  </div>

  <!-- Active Term -->
  <div class="section">
    <h2>📚 Active Academic Term</h2>
    <?php if($active_term): ?>
      <div class="info-box">
        <p><strong>Term:</strong> <?= htmlspecialchars($active_term['term_name']) ?></p>
        <p><strong>Status:</strong> <span class="status-active"><?= htmlspecialchars($active_term['status']) ?></span></p>
      </div>
    <?php else: ?>
      <p class="no-data">No active term found.</p>
    <?php endif; ?>
  </div>

  <!-- Programs Summary -->
  <div class="section">
    <h2>🎓 Programs Summary</h2>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Program Name</th>
            <th>Department</th>
            <th>Faculty</th>
            <th>Total Students</th>
          </tr>
        </thead>
        <tbody>
          <?php if($programs): ?>
            <?php foreach($programs as $prog): ?>
              <tr>
                <td><?= htmlspecialchars($prog['program_name']) ?></td>
                <td><?= htmlspecialchars($prog['department_name']) ?></td>
                <td><?= htmlspecialchars($prog['faculty_name']) ?></td>
                <td><?= htmlspecialchars($prog['total_students']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="4" style="text-align:center; color:#777; padding:20px;">
                No programs found in this department.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Sections Summary -->
  <div class="section">
    <h2>📚 Sections Summary</h2>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Section Name</th>
            <th>Program</th>
            <th>Total Students</th>
          </tr>
        </thead>
        <tbody>
          <?php if($sections): ?>
            <?php foreach($sections as $sec): ?>
              <tr>
                <td><?= htmlspecialchars($sec['section_name']) ?></td>
                <td><?= htmlspecialchars($sec['program_name']) ?></td>
                <td><?= htmlspecialchars($sec['total_students']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="3" style="text-align:center; color:#777; padding:20px;">
                No sections found in this department.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Students List -->
  <div class="section">
    <h2>👨‍🎓 Students (Top 50)</h2>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Reg No</th>
            <th>Name</th>
            <th>Program</th>
            <th>Section</th>
            <th>Department</th>
          </tr>
        </thead>
        <tbody>
          <?php if($students): ?>
            <?php foreach($students as $student): ?>
              <tr>
                <td><?= htmlspecialchars($student['reg_no']) ?></td>
                <td><?= htmlspecialchars($student['full_name']) ?></td>
                <td><?= htmlspecialchars($student['program_name']) ?></td>
                <td><?= htmlspecialchars($student['section_name']) ?></td>
                <td><?= htmlspecialchars($student['department_name']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align:center; color:#777; padding:20px;">
                No students found in this department.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Teachers List -->
  <div class="section">
    <h2>👨‍🏫 Teachers (Top 50)</h2>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Qualification</th>
            <th>Department</th>
          </tr>
        </thead>
        <tbody>
          <?php if($teachers): ?>
            <?php foreach($teachers as $teacher): ?>
              <tr>
                <td><?= htmlspecialchars($teacher['teacher_name']) ?></td>
                <td><?= htmlspecialchars($teacher['email']) ?></td>
                <td><?= htmlspecialchars($teacher['phone_number']) ?></td>
                <td><?= htmlspecialchars($teacher['qualification']) ?></td>
                <td><?= htmlspecialchars($teacher['department_name']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align:center; color:#777; padding:20px;">
                No teachers found in this department.
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Attendance Summary -->
  <div class="section">
    <h2>📈 Attendance Summary</h2>
    <div class="attendance-stats">
      <div class="stat-box">
        <h3>Total Records</h3>
        <p><?= number_format($attendance_summary['total'] ?? 0) ?></p>
      </div>
      <div class="stat-box present">
        <h3>Present</h3>
        <p><?= number_format($attendance_summary['present'] ?? 0) ?></p>
      </div>
      <div class="stat-box absent">
        <h3>Absent</h3>
        <p><?= number_format($attendance_summary['absent'] ?? 0) ?></p>
      </div>
    </div>
  </div>
</div>

<style>
body{font-family:'Poppins',sans-serif;background:#f7f9fb;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.page-header h1{color:#0072CE;margin-bottom:5px;}
/* .department-info {
  background: #e3f2fd;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #bbdefb;
  color: #1565c0;
  font-size: 14px;
  margin-top: 10px;
  display: inline-block;
} */
.summary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px;}
.card{background:#fff;border-radius:10px;padding:15px;text-align:center;box-shadow:0 2px 6px rgba(0,0,0,0.08);transition:transform 0.2s;}
.card:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.12);}
.card h3{font-size:13px;color:#0072CE;text-transform:uppercase;margin:0;}
.card p{font-size:24px;font-weight:700;color:#00843D;margin:8px 0 0;}
.export-box{margin:20px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.btn{border:none;padding:10px 20px;border-radius:6px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;transition:all 0.3s;}
.btn.blue{background:#0072CE;color:#fff;}
.btn.blue:hover{background:#005ba3;}
.report-info{margin-left:auto;color:#666;font-size:14px;}
.section{margin-top:30px;background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
.section h2{color:#00843D;margin-bottom:15px;padding-bottom:10px;border-bottom:2px solid #f0f0f0;}
.info-box{display:flex;gap:30px;flex-wrap:wrap;}
.info-box p{margin:5px 0;}
.status-active{color:#2E7D32;font-weight:600;}
.no-data{color:#777;font-style:italic;}
.table-wrapper{overflow:auto;border-radius:8px;border:1px solid #eee;}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px 15px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f8fafc;}
.attendance-stats{display:flex;gap:20px;flex-wrap:wrap;}
.stat-box{flex:1;min-width:120px;background:#f8fafc;padding:15px;border-radius:8px;text-align:center;}
.stat-box h3{font-size:14px;color:#666;margin:0 0 8px 0;}
.stat-box p{font-size:24px;font-weight:700;margin:0;}
.stat-box.present{background:#E8F5E8;border-left:4px solid #2E7D32;}
.stat-box.present p{color:#2E7D32;}
.stat-box.absent{background:#FFEBEE;border-left:4px solid #C62828;}
.stat-box.absent p{color:#C62828;}
@media(max-width:768px){
  .main-content{margin-left:0;padding:15px;}
  .summary-grid{grid-template-columns:1fr;}
  .export-box{flex-direction:column;align-items:flex-start;}
  .report-info{margin-left:0;margin-top:10px;}
  .info-box{flex-direction:column;gap:10px;}
  .attendance-stats{flex-direction:column;}
}
</style>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>