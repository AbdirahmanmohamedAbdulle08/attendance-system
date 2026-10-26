<?php
/*******************************************************************************************
 * STUDENTS MANAGEMENT SYSTEM — Hormuud University
 * Department Admin Version - View Only Access
 * No CRUD operations allowed - Only view department students
 * Layout compatible with header/sidebar/footer
 *******************************************************************************************/
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

$role = strtolower($_SESSION['user']['role'] ?? '');
if ($role !== 'department_admin') {
  header("Location: ../login.php");
  exit;
}

// Get department_id from session
$department_id = $_SESSION['user']['linked_id'] ?? null;
if (!$department_id) {
  header("Location: ../login.php");
  exit;
}

$message = "";
$type = "";

/* ============================================================
   ACCESS CONTROL - NO CRUD OPERATIONS ALLOWED
============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $message = "❌ Permission denied! You have view-only access to students.";
  $type = "error";
}

/* ============================================================
   FETCH DATA - Department Restricted
============================================================ */
// Get department name for display
$dept_stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
$dept_stmt->execute([$department_id]);
$department_name = $dept_stmt->fetchColumn();

// Get faculty name for the department
$faculty_stmt = $pdo->prepare("
  SELECT f.faculty_name 
  FROM faculties f 
  JOIN departments d ON f.faculty_id = d.faculty_id 
  WHERE d.department_id = ?
");
$faculty_stmt->execute([$department_id]);
$faculty_name = $faculty_stmt->fetchColumn();

// Get programs for current department
$programs = $pdo->prepare("SELECT * FROM programs WHERE department_id = ? ORDER BY program_name ASC");
$programs->execute([$department_id]);
$programs = $programs->fetchAll(PDO::FETCH_ASSOC);

// Get sections for current department
$sections = $pdo->prepare("
  SELECT cs.* FROM class_section cs
  JOIN programs p ON cs.program_id = p.program_id
  WHERE p.department_id = ?
  ORDER BY cs.section_name ASC
");
$sections->execute([$department_id]);
$sections = $sections->fetchAll(PDO::FETCH_ASSOC);

$semesters = $pdo->query("SELECT * FROM semester ORDER BY semester_name ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ============================================================
   SEARCH AND FILTER FUNCTIONALITY
============================================================ */
$search = trim($_GET['q'] ?? '');
$program_filter = $_GET['program'] ?? 'all';
$section_filter = $_GET['section'] ?? 'all';
$semester_filter = $_GET['semester'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build the base SQL query
$sql = "SELECT s.*, p.full_name AS parent_name, p.phone AS parent_phone, ps.relation_type,
  p.gender AS guardian_gender, p.email AS guardian_email, p.address AS guardian_address,
  p.occupation AS guardian_occupation, p.status AS guardian_status,
  u.password AS student_password,
  d.department_name,
  pr.program_name,
  cs.section_name,
  sem.semester_name,
  f.faculty_name
  FROM students s
  LEFT JOIN parents p ON s.parent_id = p.parent_id
  LEFT JOIN parent_student ps ON s.student_id = ps.student_id
  LEFT JOIN users u ON s.student_id = u.linked_id AND u.linked_table = 'student'
  LEFT JOIN departments d ON s.department_id = d.department_id
  LEFT JOIN programs pr ON s.program_id = pr.program_id
  LEFT JOIN class_section cs ON s.section_id = cs.section_id
  LEFT JOIN semester sem ON s.semester_id = sem.semester_id
  LEFT JOIN faculties f ON s.faculty_id = f.faculty_id
  WHERE s.department_id = ?";

$params = [$department_id];

// Add search filter
if ($search !== '') {
  $sql .= " AND (s.full_name LIKE ? OR s.reg_no LIKE ? OR s.email LIKE ? OR s.phone_number LIKE ?)";
  $search_param = "%$search%";
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
  $params[] = $search_param;
}

// Add program filter
if ($program_filter !== 'all') {
  $sql .= " AND s.program_id = ?";
  $params[] = $program_filter;
}

// Add section filter
if ($section_filter !== 'all') {
  $sql .= " AND s.section_id = ?";
  $params[] = $section_filter;
}

// Add semester filter
if ($semester_filter !== 'all') {
  $sql .= " AND s.semester_id = ?";
  $params[] = $semester_filter;
}

// Add status filter
if ($status_filter !== 'all') {
  $sql .= " AND s.status = ?";
  $params[] = $status_filter;
}

$sql .= " ORDER BY s.student_id DESC";

// Execute the query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

include('../includes/header.php');
?>

<div class="main-content">
  <?php if($message): ?>
  <div class="alert <?= $type=='success'?'alert-success':'alert-error' ?>"><?= htmlspecialchars($message) ?></div>
  <script>setTimeout(()=>document.querySelector('.alert').classList.add('hide'),3000);</script>
  <?php endif; ?>

  <div class="top-bar">
    <h2>Students Management </h2>
    <!-- <div class="department-info">
      <strong>Faculty:</strong> <?= htmlspecialchars($faculty_name) ?>
    </div> -->
  </div>

  <!-- Search and Filter Form -->
  <form method="GET" class="search-filter-form">
    <div class="filter-row">
      <input 
        type="text" 
        name="q" 
        value="<?= htmlspecialchars($search) ?>" 
        placeholder="🔍 Search by Reg No, Name, Email or Phone..."
        class="search-input"
      >
      
      <select name="program" class="filter-select">
        <option value="all">All Programs</option>
        <?php foreach($programs as $program): ?>
          <option value="<?= $program['program_id'] ?>" <?= $program_filter == $program['program_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($program['program_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <select name="section" class="filter-select">
        <option value="all">All Sections</option>
        <?php foreach($sections as $section): ?>
          <option value="<?= $section['section_id'] ?>" <?= $section_filter == $section['section_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($section['section_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <select name="semester" class="filter-select">
        <option value="all">All Semesters</option>
        <?php foreach($semesters as $semester): ?>
          <option value="<?= $semester['semester_id'] ?>" <?= $semester_filter == $semester['semester_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($semester['semester_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      
      <select name="status" class="filter-select">
        <option value="all">All Status</option>
        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
      
      <button type="submit" class="btn blue filter-btn">Apply Filters</button>
      <a href="?" class="btn red">Clear</a>
    </div>
  </form>

  <!-- Results Count -->
  <div class="results-count">
    Found <?= count($students) ?> student(s)
    <?php if($search || $program_filter !== 'all' || $section_filter !== 'all' || $semester_filter !== 'all' || $status_filter !== 'all'): ?>
      with current filters
    <?php endif; ?>
  </div>

  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Reg No</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Program</th>
          <th>Section</th>
          <th>Semester</th>
          <th>Parent</th>
          <th>Relation</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($students)): ?>
          <tr>
            <td colspan="11" style="text-align:center;padding:20px;">
              <?php if($search || $program_filter !== 'all' || $section_filter !== 'all' || $semester_filter !== 'all' || $status_filter !== 'all'): ?>
                No students found matching your search criteria. <a href="?" style="color:#0072CE;">Clear filters</a>
              <?php else: ?>
                No students found in your department.
              <?php endif; ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach($students as $i=>$s): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= htmlspecialchars($s['reg_no']) ?></strong></td>
            <td><?= htmlspecialchars($s['full_name']) ?></td>
            <td><?= htmlspecialchars($s['email']) ?></td>
            <td><?= htmlspecialchars($s['phone_number']) ?></td>
            <td><?= htmlspecialchars($s['program_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['section_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['semester_name'] ?? '-') ?></td>
            <td><?= htmlspecialchars($s['parent_name']) ?></td>
            <td><?= htmlspecialchars($s['relation_type'] ?: '-') ?></td>
            <td style="color:<?= $s['status']=='active'?'green':'red' ?>;font-weight:600;">
              <?= ucfirst($s['status']) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
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
  /* box-shadow:0 2px 6px rgba(0,0,0,0.08); */
  flex-wrap: wrap;
  gap: 15px;
}
.top-bar h2 { 
  color:#0072CE; 
  margin:0; 
}
/* .department-info {
  background: #e3f2fd;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid #bbdefb;
  color: #1565c0;
  font-size: 14px;
} */

/* Search and Filter Styles */
.search-filter-form {
  background: #fff;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin: 15px 0;
}
.filter-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.search-input {
  flex: 2;
  min-width: 200px;
  padding: 8px 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
}
.filter-select {
  flex: 1;
  min-width: 150px;
  padding: 8px 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 14px;
  background: white;
}
.filter-btn {
  white-space: nowrap;
}
.results-count {
  background: #f8f9fa;
  padding: 8px 12px;
  border-radius: 4px;
  margin: 10px 0;
  font-size: 14px;
  color: #495057;
  border-left: 4px solid #0072CE;
}

.btn {
  border:none;
  padding:8px 14px;
  border-radius:6px;
  font-weight:600;
  cursor:pointer;
  margin:2px;
  font-size:13px;
  text-decoration: none;
  display: inline-block;
  text-align: center;
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

/* Responsive Design */
@media (max-width: 768px) {
  .filter-row {
    flex-direction: column;
    align-items: stretch;
  }
  .search-input, .filter-select {
    min-width: auto;
  }
}
</style>

<!-- ================================
     JAVASCRIPT SECTION
================================ -->
<script>
// Enable form submissions for search and filter
document.addEventListener('DOMContentLoaded', function() {
  // Search form is now enabled for GET requests
  const searchForm = document.querySelector('.search-filter-form');
  if (searchForm) {
    searchForm.addEventListener('submit', function(e) {
      // Allow the form to submit normally for GET requests
      // No need to prevent default
    });
  }
});

// Show permission alert for any POST actions (if any forms remain)
function showPermissionAlert() {
  alert("Permission denied! As a Department Admin, you have view-only access to students. Adding, editing, or deleting students is restricted.");
}
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>