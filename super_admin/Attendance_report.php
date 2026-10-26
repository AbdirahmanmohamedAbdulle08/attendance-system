<?php
/*******************************************************************************************
 * ATTENDANCE REPORT — Hormuud University
 * View attendance by Day, Week, Month, Year + Full Hierarchy (Campus → Faculty → Dept → Program → Section)
 * Includes PDF + Excel Export & Low Attendance Filter
 * Author: ChatGPT 2025
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

if (strtolower($_SESSION['user']['role'] ?? '') !== 'super_admin') {
  header("Location: ../login.php");
  exit;
}

date_default_timezone_set('Africa/Nairobi');

/* ================= FETCH FILTER DATA ================= */
$campuses    = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties   = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$programs    = $pdo->query("SELECT * FROM programs ORDER BY program_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sections    = $pdo->query("SELECT * FROM class_section ORDER BY section_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$report = [];
$params = [];
$filter_sql = "";

/* ================= HANDLE FILTER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])) {
  $campus_id  = $_POST['campus_id'] ?? '';
  $faculty_id = $_POST['faculty_id'] ?? '';
  $dept_id    = $_POST['department_id'] ?? '';
  $prog_id    = $_POST['program_id'] ?? '';
  $section_id = $_POST['section_id'] ?? '';
  $period     = $_POST['period'] ?? 'day';
  $date       = $_POST['date'] ?? date('Y-m-d');
  $low_only   = isset($_POST['low_only']);

  // ====== Period filter ======
  switch ($period) {
    case 'week':
      $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
      $end   = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
      $filter_sql .= " AND a.attendance_date BETWEEN ? AND ?";
      $params[] = $start; $params[] = $end;
      break;
    case 'month':
      $start = date('Y-m-01', strtotime($date));
      $end   = date('Y-m-t', strtotime($date));
      $filter_sql .= " AND a.attendance_date BETWEEN ? AND ?";
      $params[] = $start; $params[] = $end;
      break;
    case 'year':
      $year = date('Y', strtotime($date));
      $filter_sql .= " AND YEAR(a.attendance_date)=?";
      $params[] = $year;
      break;
    default:
      $filter_sql .= " AND a.attendance_date=?";
      $params[] = $date;
  }

  // ====== Hierarchy filter ======
  if ($section_id) { $filter_sql .= " AND c.section_id=?"; $params[] = $section_id; }
  elseif ($prog_id){ $filter_sql .= " AND p.program_id=?"; $params[] = $prog_id; }
  elseif ($dept_id){ $filter_sql .= " AND d.department_id=?"; $params[] = $dept_id; }
  elseif ($faculty_id){ $filter_sql .= " AND f.faculty_id=?"; $params[] = $faculty_id; }
  elseif ($campus_id){ $filter_sql .= " AND f.campus_id=?"; $params[] = $campus_id; }

  // ====== Main query ======
  if ($low_only) {
    // show only students with 5 or more absences in the range
    $stmt = $pdo->prepare("
      SELECT s.full_name, s.reg_no, c.section_name, p.program_name, d.department_name, 
             f.faculty_name, ca.campus_name,
             COUNT(CASE WHEN a.status='absent' THEN 1 END) AS absences
      FROM attendance a
      JOIN students s ON s.student_id=a.student_id
      JOIN class_section c ON c.section_id=a.section_id
      JOIN programs p ON p.program_id=c.program_id
      JOIN departments d ON d.department_id=p.department_id
      JOIN faculties f ON f.faculty_id=d.faculty_id
      JOIN campus ca ON ca.campus_id=f.campus_id
      WHERE 1=1 $filter_sql
      GROUP BY s.student_id
      HAVING absences >= 5
      ORDER BY absences DESC
    ");
  } else {
    $stmt = $pdo->prepare("
      SELECT a.attendance_date, s.full_name, s.reg_no, c.section_name, p.program_name, 
             d.department_name, f.faculty_name, ca.campus_name, a.status, t.teacher_name
      FROM attendance a
      JOIN students s ON s.student_id=a.student_id
      JOIN class_section c ON c.section_id=a.section_id
      JOIN programs p ON p.program_id=c.program_id
      JOIN departments d ON d.department_id=p.department_id
      JOIN faculties f ON f.faculty_id=d.faculty_id
      JOIN campus ca ON ca.campus_id=f.campus_id
      JOIN teachers t ON t.teacher_id=a.teacher_id
      WHERE 1=1 $filter_sql
      ORDER BY a.attendance_date DESC
    ");
  }
  $stmt->execute($params);
  $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= EXPORT (CSV/PDF) ================= */
if (isset($_GET['export']) && isset($_GET['type'])) {
  $type = $_GET['type'];
  $data = $_SESSION['report_data'] ?? [];
  if (!$data) exit("⚠️ No report data to export.");

  if ($type === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=attendance_report_" . date('Y-m-d') . ".csv");
    $out = fopen("php://output", "w");
    fputcsv($out, array_keys($data[0]));
    foreach($data as $r) fputcsv($out, $r);
    fclose($out);
    exit;
  } elseif ($type === 'pdf') {
    require_once(__DIR__ . '/../libs/fpdf/fpdf.php');
    $pdf = new FPDF('L','mm','A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0,10,'Hormuud University - Attendance Report',0,1,'C');
    $pdf->SetFont('Arial','',10);
    foreach ($data as $r) {
      $line = implode(' | ', $r);
      $pdf->MultiCell(0,6,$line,0,'L');
    }
    $pdf->Output();
    exit;
  }
}

$_SESSION['report_data'] = $report;

include('../includes/header.php');
?>
<style>
  .sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

</style>
<div class="main-content">
  <div class="page-header"><h1><i class="fa fa-chart-bar"></i> Attendance Report</h1></div>

  <!-- FILTER FORM -->
  <form method="POST" class="filter-box">
    <div class="grid">
      <div>
        <label>Campus</label>
        <select name="campus_id" id="campus" onchange="filterFaculties()">
          <option value="">All Campuses</option>
          <?php foreach($campuses as $c): ?>
            <option value="<?= $c['campus_id'] ?>"><?= htmlspecialchars($c['campus_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label>Faculty</label><select name="faculty_id" id="faculty" onchange="filterDepartments()"><option value="">All Faculties</option></select></div>
      <div><label>Department</label><select name="department_id" id="department" onchange="filterPrograms()"><option value="">All Departments</option></select></div>
      <div><label>Program</label><select name="program_id" id="program" onchange="filterSections()"><option value="">All Programs</option></select></div>
      <div><label>Section</label><select name="section_id" id="section"><option value="">All Sections</option></select></div>

      <div>
        <label>Period Type</label>
        <select name="period">
          <option value="day">By Day</option>
          <option value="week">By Week</option>
          <option value="month">By Month</option>
          <option value="year">By Year</option>
        </select>
      </div>
      <div><label>Date</label><input type="date" name="date" value="<?= date('Y-m-d') ?>"></div>
      <div><label><input type="checkbox" name="low_only"> Only Students with ≥5 Absences</label></div>
      <div style="align-self:end;">
        <button name="filter" class="btn green"><i class="fa fa-search"></i> Generate</button>
      </div>
    </div>
  </form>

  <!-- EXPORT BUTTONS -->
  <?php if($report): ?>
  <div class="export-box">
    <a href="?export=1&type=csv" class="btn blue"><i class="fa fa-file-excel"></i> Download Excel</a>
  </div>
  <?php endif; ?>

  <!-- REPORT TABLE -->
  <div class="table-wrapper">
    <h3 style="color:#0072CE;margin:10px;">Filtered Attendance Results</h3>
    <table>
      <thead>
        <tr>
          <?php if(isset($_POST['low_only'])): ?>
            <th>Student</th><th>Reg No</th><th>Section</th><th>Program</th><th>Department</th><th>Faculty</th><th>Campus</th><th>Absences</th>
          <?php else: ?>
            <th>Date</th><th>Student</th><th>Reg No</th><th>Section</th><th>Program</th><th>Department</th><th>Faculty</th><th>Campus</th><th>Teacher</th><th>Status</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if(!$report): ?>
          <tr><td colspan="10" style="text-align:center;color:#777;">No attendance found for selected filters.</td></tr>
        <?php else: foreach($report as $r): ?>
          <tr>
            <?php if(isset($_POST['low_only'])): ?>
              <td><?= htmlspecialchars($r['full_name']) ?></td>
              <td><?= htmlspecialchars($r['reg_no']) ?></td>
              <td><?= htmlspecialchars($r['section_name']) ?></td>
              <td><?= htmlspecialchars($r['program_name']) ?></td>
              <td><?= htmlspecialchars($r['department_name']) ?></td>
              <td><?= htmlspecialchars($r['faculty_name']) ?></td>
              <td><?= htmlspecialchars($r['campus_name']) ?></td>
              <td style="color:#C62828;font-weight:600;"><?= htmlspecialchars($r['absences']) ?></td>
            <?php else: ?>
              <td><?= htmlspecialchars($r['attendance_date']) ?></td>
              <td><?= htmlspecialchars($r['full_name']) ?></td>
              <td><?= htmlspecialchars($r['reg_no']) ?></td>
              <td><?= htmlspecialchars($r['section_name']) ?></td>
              <td><?= htmlspecialchars($r['program_name']) ?></td>
              <td><?= htmlspecialchars($r['department_name']) ?></td>
              <td><?= htmlspecialchars($r['faculty_name']) ?></td>
              <td><?= htmlspecialchars($r['campus_name']) ?></td>
              <td><?= htmlspecialchars($r['teacher_name']) ?></td>
              <td><?= htmlspecialchars($r['status']) ?></td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ✅ STYLE -->
<style>
body{font-family:'Poppins',sans-serif;background:#f5f9f7;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.page-header h1{color:#0072CE;margin-bottom:15px;}
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:20px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;}
label{font-weight:600;color:#0072CE;font-size:13px;}
select,input[type=date]{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}
.btn{border:none;padding:8px 15px;border-radius:6px;font-weight:600;cursor:pointer;}
.btn.green{background:#00843D;color:#fff;}
.btn.blue{background:#0072CE;color:#fff;}
.btn.red{background:#C62828;color:#fff;}
.export-box{margin:10px 0;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
@media(max-width:768px){.main-content{margin-left:0;padding:15px;}.grid{grid-template-columns:1fr;}}
</style>

<!-- ✅ SCRIPT -->
<script>
const faculties = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;
const programs = <?= json_encode($programs) ?>;
const sections = <?= json_encode($sections) ?>;

function filterFaculties(){
  const c = document.getElementById('campus').value;
  const f = document.getElementById('faculty');
  f.innerHTML = '<option value="">All Faculties</option>';
  departmentsReset(); programsReset(); sectionsReset();
  faculties.filter(x=>x.campus_id==c).forEach(x=>{
    f.innerHTML += `<option value="${x.faculty_id}">${x.faculty_name}</option>`;
  });
}
function filterDepartments(){
  const fId = document.getElementById('faculty').value;
  const d = document.getElementById('department');
  d.innerHTML = '<option value="">All Departments</option>';
  programsReset(); sectionsReset();
  departments.filter(x=>x.faculty_id==fId).forEach(x=>{
    d.innerHTML += `<option value="${x.department_id}">${x.department_name}</option>`;
  });
}
function filterPrograms(){
  const dId = document.getElementById('department').value;
  const p = document.getElementById('program');
  p.innerHTML = '<option value="">All Programs</option>';
  sectionsReset();
  programs.filter(x=>x.department_id==dId).forEach(x=>{
    p.innerHTML += `<option value="${x.program_id}">${x.program_name}</option>`;
  });
}
function filterSections(){
  const pId = document.getElementById('program').value;
  const s = document.getElementById('section');
  s.innerHTML = '<option value="">All Sections</option>';
  sections.filter(x=>x.program_id==pId).forEach(x=>{
    s.innerHTML += `<option value="${x.section_id}">${x.section_name}</option>`;
  });
}
function departmentsReset(){document.getElementById('department').innerHTML='<option value="">All Departments</option>';}
function programsReset(){document.getElementById('program').innerHTML='<option value="">All Programs</option>';}
function sectionsReset(){document.getElementById('section').innerHTML='<option value="">All Sections</option>';}
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>
