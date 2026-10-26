<?php
/*******************************************************************************************
 * PROMOTION REPORT — Hormuud University
 * Extended Print Version | Official Layout
 * Super Admin Panel | PHP 8.2 | PDO Secure Queries
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

if (strtolower($_SESSION['user']['role'] ?? '') !== 'super_admin') {
  header("Location: ../login.php");
  exit;
}

date_default_timezone_set('Africa/Nairobi');
$message = ""; $type = "";

/* ================= FETCH HIERARCHY DATA ================= */
$campuses    = $pdo->query("SELECT * FROM campus ORDER BY campus_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$faculties   = $pdo->query("SELECT * FROM faculties ORDER BY faculty_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT * FROM departments ORDER BY department_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$programs    = $pdo->query("SELECT * FROM programs ORDER BY program_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$sections    = $pdo->query("SELECT * FROM class_section ORDER BY section_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$semesters   = $pdo->query("SELECT * FROM semester ORDER BY semester_id ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ================= FILTERS ================= */
$where = "1=1";
$params = [];

if (!empty($_GET['campus_id']))      { $where .= " AND c.campus_id=?";     $params[] = $_GET['campus_id']; }
if (!empty($_GET['faculty_id']))     { $where .= " AND f.faculty_id=?";    $params[] = $_GET['faculty_id']; }
if (!empty($_GET['department_id']))  { $where .= " AND d.department_id=?"; $params[] = $_GET['department_id']; }
if (!empty($_GET['program_id']))     { $where .= " AND p.program_id=?";    $params[] = $_GET['program_id']; }
if (!empty($_GET['section_id']))     { $where .= " AND cs.section_id=?";   $params[] = $_GET['section_id']; }
if (!empty($_GET['semester_id']))    { $where .= " AND se.semester_id=?";  $params[] = $_GET['semester_id']; }

if (!empty($_GET['from_date']) && !empty($_GET['to_date'])) {
  $where .= " AND DATE(ph.promotion_date) BETWEEN ? AND ?";
  $params[] = $_GET['from_date'];
  $params[] = $_GET['to_date'];
}

/* ================= GET PROMOTION DATA ================= */
$query = "
  SELECT ph.*, s.full_name, s.reg_no, se.semester_name AS new_sem,
         oldse.semester_name AS old_sem, u.username AS promoted_by,
         c.campus_name, f.faculty_name, d.department_name, p.program_name, cs.section_name
  FROM promotion_history ph
  JOIN students s ON s.student_id = ph.student_id
  LEFT JOIN users u ON u.user_id = ph.promoted_by
  LEFT JOIN semester se ON se.semester_id = ph.new_semester_id
  LEFT JOIN semester oldse ON oldse.semester_id = ph.old_semester_id
  LEFT JOIN campus c ON s.campus_id = c.campus_id
  LEFT JOIN faculties f ON s.faculty_id = f.faculty_id
  LEFT JOIN departments d ON s.department_id = d.department_id
  LEFT JOIN programs p ON s.program_id = p.program_id
  LEFT JOIN class_section cs ON s.section_id = cs.section_id
  WHERE $where
  ORDER BY ph.promotion_date DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);

include('../includes/header.php');
?>
<style>
  .sidebar.collapsed ~ .main-content {
  margin-left: 70px;
}

</style>
<div class="main-content">
  <div class="page-header">
    <h1>Promotion Report</h1>
  </div>

  <!-- FILTER FORM -->
  <div class="filter-box">
    <form method="GET">
      <div class="grid">
        <div><label>Campus</label>
          <select name="campus_id" id="campus" onchange="filterFaculties()">
            <option value="">All</option>
            <?php foreach($campuses as $c): ?>
              <option value="<?= $c['campus_id'] ?>" <?= ($_GET['campus_id']??'')==$c['campus_id']?'selected':'' ?>>
                <?= htmlspecialchars($c['campus_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>Faculty</label>
          <select name="faculty_id" id="faculty" onchange="filterDepartments()">
            <option value="">All</option>
            <?php foreach($faculties as $f): ?>
              <option value="<?= $f['faculty_id'] ?>" <?= ($_GET['faculty_id']??'')==$f['faculty_id']?'selected':'' ?>>
                <?= htmlspecialchars($f['faculty_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>Department</label>
          <select name="department_id" id="department" onchange="filterPrograms()">
            <option value="">All</option>
            <?php foreach($departments as $d): ?>
              <option value="<?= $d['department_id'] ?>" <?= ($_GET['department_id']??'')==$d['department_id']?'selected':'' ?>>
                <?= htmlspecialchars($d['department_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>Program</label>
          <select name="program_id" id="program" onchange="filterSections()">
            <option value="">All</option>
            <?php foreach($programs as $p): ?>
              <option value="<?= $p['program_id'] ?>" <?= ($_GET['program_id']??'')==$p['program_id']?'selected':'' ?>>
                <?= htmlspecialchars($p['program_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>Section</label>
          <select name="section_id" id="section">
            <option value="">All</option>
            <?php foreach($sections as $sec): ?>
              <option value="<?= $sec['section_id'] ?>" <?= ($_GET['section_id']??'')==$sec['section_id']?'selected':'' ?>>
                <?= htmlspecialchars($sec['section_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>Semester</label>
          <select name="semester_id">
            <option value="">All</option>
            <?php foreach($semesters as $s): ?>
              <option value="<?= $s['semester_id'] ?>" <?= ($_GET['semester_id']??'')==$s['semester_id']?'selected':'' ?>>
                <?= htmlspecialchars($s['semester_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div><label>From</label><input type="date" name="from_date" value="<?= $_GET['from_date']??'' ?>"></div>
        <div><label>To</label><input type="date" name="to_date" value="<?= $_GET['to_date']??'' ?>"></div>

        <div style="align-self:end;">
          <button type="submit" class="btn blue"><i class="fa fa-search"></i> Filter</button>
        </div>
      </div>
    </form>
  </div>

  <!-- REPORT TABLE -->
  <div class="table-wrapper" id="reportArea">
    <div class="print-header">
      <img src="../assets/logo.png" alt="Logo">
      <div>
        <h2>HORMUUD UNIVERSITY</h2>
        <p>Promotion Report</p>
        <p><strong>Date:</strong> <?= date('d M Y') ?></p>
      </div>
    </div>

    <div style="padding:10px 15px;display:flex;justify-content:space-between;align-items:center;" class="noprint">
      <h3 style="color:#0072CE;">Promotion Report</h3>
      <div>
        <!-- <button onclick="printReport()" class="btn green"><i class="fa fa-print"></i> Print</button> -->
        <button onclick="exportTableToCSV('promotion_report.csv')" class="btn blue"><i class="fa fa-file-excel"></i> Excel</button>
      </div>
    </div>

    <table id="reportTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Student</th>
          <th>Reg No</th>
          <th>Campus</th>
          <th>Faculty</th>
          <th>Department</th>
          <th>Program</th>
          <th>Section</th>
          <th>Old Semester</th>
          <th>New Semester</th>
          <th>Promoted By</th>
          <th>Date</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if($promotions): $i=1; foreach($promotions as $r): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td><?= htmlspecialchars($r['full_name']) ?></td>
          <td><?= htmlspecialchars($r['reg_no']) ?></td>
          <td><?= htmlspecialchars($r['campus_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['faculty_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['department_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['program_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['section_name'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['old_sem'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['new_sem'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['promoted_by'] ?? '-') ?></td>
          <td><?= htmlspecialchars($r['promotion_date']) ?></td>
          <td><?= htmlspecialchars($r['remarks'] ?? '') ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr><td colspan="13" style="text-align:center;color:#777;">No promotion records found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="signature">
      <p>__________________________</p>
      <p><strong>Registrar / Admin</strong></p>
      <p>Date: <?= date('d-m-Y') ?></p>
    </div>
  </div>
</div>

<style>
body{font-family:'Poppins',sans-serif;background:#f5f9f7;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:15px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;}
label{font-weight:600;color:#0072CE;font-size:13px;}
select,input{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}
.btn{border:none;padding:9px 15px;border-radius:6px;font-weight:600;cursor:pointer;}
.btn.blue{background:#0072CE;color:#fff;}
.btn.green{background:#00843D;color:#fff;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);padding-bottom:20px;}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border:1px solid #ddd;text-align:left;font-size:13px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
.print-header{text-align:center;display:none;}
.print-header img{width:80px;margin-top:10px;}
.print-header h2{margin:5px 0;color:#0072CE;}
.signature{text-align:center;margin-top:30px;}
@media print{
  body{background:#fff;}
  .noprint,.filter-box{display:none!important;}
  .print-header{display:block;}
  .main-content{margin:0;padding:0;}
  table{font-size:12px;border-collapse:collapse;width:100%;}
  th,td{border:1px solid #000;padding:6px;}
}
</style>

<script>
function printReport(){
  window.print();
}
function exportTableToCSV(filename){
  const csv=[];const rows=document.querySelectorAll("#reportTable tr");
  for(let i=0;i<rows.length;i++){
    const cols=rows[i].querySelectorAll("td, th");const data=[];
    for(let j=0;j<cols.length;j++){data.push('"' + cols[j].innerText.replace(/"/g,'""') + '"');}
    csv.push(data.join(","));
  }
  const csvFile=new Blob([csv.join("\n")],{type:"text/csv"});
  const downloadLink=document.createElement("a");
  downloadLink.download=filename;
  downloadLink.href=window.URL.createObjectURL(csvFile);
  downloadLink.style.display="none";
  document.body.appendChild(downloadLink);
  downloadLink.click();
}
const faculties = <?= json_encode($faculties) ?>;
const departments = <?= json_encode($departments) ?>;
const programs = <?= json_encode($programs) ?>;
const sections = <?= json_encode($sections) ?>;
function filterFaculties(){
  const c=document.getElementById('campus').value;
  const f=document.getElementById('faculty');
  f.innerHTML='<option value="">All</option>';
  faculties.filter(x=>x.campus_id==c).forEach(x=>{
    f.innerHTML+=`<option value="${x.faculty_id}">${x.faculty_name}</option>`;
  });
}
function filterDepartments(){
  const f=document.getElementById('faculty').value;
  const d=document.getElementById('department');
  d.innerHTML='<option value="">All</option>';
  departments.filter(x=>x.faculty_id==f).forEach(x=>{
    d.innerHTML+=`<option value="${x.department_id}">${x.department_name}</option>`;
  });
}
function filterPrograms(){
  const d=document.getElementById('department').value;
  const p=document.getElementById('program');
  p.innerHTML='<option value="">All</option>';
  programs.filter(x=>x.department_id==d).forEach(x=>{
    p.innerHTML+=`<option value="${x.program_id}">${x.program_name}</option>`;
  });
}
function filterSections(){
  const p=document.getElementById('program').value;
  const s=document.getElementById('section');
  s.innerHTML='<option value="">All</option>';
  sections.filter(x=>x.program_id==p).forEach(x=>{
    s.innerHTML+=`<option value="${x.section_id}">${x.section_name}</option>`;
  });
}
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>
