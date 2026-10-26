<?php
/*******************************************************************************************
 * HORMUUD UNIVERSITY — DEPARTMENT ADMIN DASHBOARD (Final Version with Official Colors)
 * Compatible with NEW header.php layout
 * Author: ChatGPT 2025 (Do Not Remove)
 *******************************************************************************************/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Redirect if not logged in or not department admin
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'department_admin') {
  header("Location: ../login.php");
  exit;
}

// ✅ Load user info
$name = $_SESSION['user']['username'] ?? 'User';
$role = $_SESSION['user']['role'] ?? '';
$department_id = $_SESSION['user']['linked_id'] ?? 0;

// ✅ Include header (topbar + sidebar)
require_once('../includes/header.php');

/* ============================================================
   SAFE COUNT FUNCTION (Department Isolation)
   ============================================================ */
function getDepartmentCounts(PDO $pdo, string $table, int $department_id = null): array {
  try {
    // Handle announcement table separately
    if ($table === 'announcement') {
      $stmtA = $pdo->query("SELECT COUNT(*) FROM announcement WHERE status='active'");
      $stmtI = $pdo->query("SELECT COUNT(*) FROM announcement WHERE status='inactive'");
      return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
    }
    
    // Handle programs table
    if ($table === 'programs') {
      if ($department_id > 0) {
        $stmtA = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE status='active' AND department_id=:did");
        $stmtI = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE status='inactive' AND department_id=:did");
        $stmtA->execute(['did'=>$department_id]);
        $stmtI->execute(['did'=>$department_id]);
        return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
      }
      $a = $pdo->query("SELECT COUNT(*) FROM programs WHERE status='active'")->fetchColumn();
      $i = $pdo->query("SELECT COUNT(*) FROM programs WHERE status='inactive'")->fetchColumn();
      return [$a ?: 0, $i ?: 0];
    }
    
    // Handle class_section table
    if ($table === 'class_section') {
      if ($department_id > 0) {
        $stmtA = $pdo->prepare("
          SELECT COUNT(*) FROM class_section cs 
          JOIN programs p ON p.program_id = cs.program_id 
          WHERE cs.status='active' AND p.department_id=:did
        ");
        $stmtI = $pdo->prepare("
          SELECT COUNT(*) FROM class_section cs 
          JOIN programs p ON p.program_id = cs.program_id 
          WHERE cs.status='inactive' AND p.department_id=:did
        ");
        $stmtA->execute(['did'=>$department_id]);
        $stmtI->execute(['did'=>$department_id]);
        return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
      }
      $a = $pdo->query("SELECT COUNT(*) FROM class_section WHERE status='active'")->fetchColumn();
      $i = $pdo->query("SELECT COUNT(*) FROM class_section WHERE status='inactive'")->fetchColumn();
      return [$a ?: 0, $i ?: 0];
    }
    
    // Handle teachers table
    if ($table === 'teachers') {
      if ($department_id > 0) {
        $stmtA = $pdo->prepare("
          SELECT COUNT(DISTINCT t.teacher_id) FROM teachers t
          JOIN timetable tb ON tb.teacher_id = t.teacher_id
          JOIN class_section cs ON cs.section_id = tb.section_id
          JOIN programs p ON p.program_id = cs.program_id
          WHERE t.status='active' AND p.department_id=:did
        ");
        $stmtI = $pdo->prepare("
          SELECT COUNT(DISTINCT t.teacher_id) FROM teachers t
          JOIN timetable tb ON tb.teacher_id = t.teacher_id
          JOIN class_section cs ON cs.section_id = tb.section_id
          JOIN programs p ON p.program_id = cs.program_id
          WHERE t.status='inactive' AND p.department_id=:did
        ");
        $stmtA->execute(['did'=>$department_id]);
        $stmtI->execute(['did'=>$department_id]);
        return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
      }
      $a = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='active'")->fetchColumn();
      $i = $pdo->query("SELECT COUNT(*) FROM teachers WHERE status='inactive'")->fetchColumn();
      return [$a ?: 0, $i ?: 0];
    }
    
    // Handle students table
    if ($table === 'students') {
      if ($department_id > 0) {
        $stmtA = $pdo->prepare("
          SELECT COUNT(*) FROM students s
          JOIN class_section cs ON cs.section_id = s.section_id
          JOIN programs p ON p.program_id = cs.program_id
          WHERE s.status='active' AND p.department_id=:did
        ");
        $stmtI = $pdo->prepare("
          SELECT COUNT(*) FROM students s
          JOIN class_section cs ON cs.section_id = s.section_id
          JOIN programs p ON p.program_id = cs.program_id
          WHERE s.status='inactive' AND p.department_id=:did
        ");
        $stmtA->execute(['did'=>$department_id]);
        $stmtI->execute(['did'=>$department_id]);
        return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
      }
      $a = $pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
      $i = $pdo->query("SELECT COUNT(*) FROM students WHERE status='inactive'")->fetchColumn();
      return [$a ?: 0, $i ?: 0];
    }
    
    // Handle parents table
    if ($table === 'parents') {
      if ($department_id > 0) {
        $stmtA = $pdo->prepare("
          SELECT COUNT(DISTINCT pr.parent_id) FROM parents pr
          JOIN students s ON s.parent_id = pr.parent_id
          JOIN class_section cs ON cs.section_id = s.section_id
          JOIN programs p ON p.program_id = cs.program_id
          WHERE pr.status='active' AND p.department_id=:did
        ");
        $stmtI = $pdo->prepare("
          SELECT COUNT(DISTINCT pr.parent_id) FROM parents pr
          JOIN students s ON s.parent_id = pr.parent_id
          JOIN class_section cs ON cs.section_id = s.section_id
          JOIN programs p ON p.program_id = cs.program_id
          WHERE pr.status='inactive' AND p.department_id=:did
        ");
        $stmtA->execute(['did'=>$department_id]);
        $stmtI->execute(['did'=>$department_id]);
        return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
      }
      $a = $pdo->query("SELECT COUNT(*) FROM parents WHERE status='active'")->fetchColumn();
      $i = $pdo->query("SELECT COUNT(*) FROM parents WHERE status='inactive'")->fetchColumn();
      return [$a ?: 0, $i ?: 0];
    }
    
    return [0,0];
  } catch (Exception $e) {
    error_log("Department count error for $table: " . $e->getMessage());
    return [0,0];
  }
}

/* ============================================================
   FETCH STATS (Department Filtered)
   ============================================================ */
[$aProg,$iProg] = getDepartmentCounts($pdo,'programs',$department_id);
[$aSec,$iSec]   = getDepartmentCounts($pdo,'class_section',$department_id);
[$aTea,$iTea]   = getDepartmentCounts($pdo,'teachers',$department_id);
[$aStu,$iStu]   = getDepartmentCounts($pdo,'students',$department_id);
[$aPar,$iPar]   = getDepartmentCounts($pdo,'parents',$department_id);
[$aAnn,$iAnn]   = getDepartmentCounts($pdo,'announcement',$department_id);

/* ============================================================
   DEPARTMENT NAME (Display only)
   ============================================================ */
$department_name = 'Unknown';
$faculty_name = 'Unknown';
try {
  $stmt = $pdo->prepare("
    SELECT d.department_name, f.faculty_name 
    FROM departments d 
    JOIN faculties f ON f.faculty_id = d.faculty_id 
    WHERE d.department_id = ?
  ");
  $stmt->execute([$department_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $department_name = $result['department_name'] ?? 'N/A';
  $faculty_name = $result['faculty_name'] ?? 'N/A';
} catch (Exception $e) {
  error_log("Department info error: " . $e->getMessage());
}

/* ============================================================
   RECENT ANNOUNCEMENTS
   ============================================================ */
$recent_announcements = [];
try {
  $stmt = $pdo->prepare("
    SELECT title, message, created_at, target_role, status 
    FROM announcement 
    ORDER BY created_at DESC 
    LIMIT 5
  ");
  $stmt->execute();
  $recent_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Recent announcements error: " . $e->getMessage());
  $recent_announcements = [];
}

/* ============================================================
   RECENT TEACHERS (For Department)
   ============================================================ */
$recent_teachers = [];
try {
  // Get teachers who are assigned to this department via timetable
  $stmt = $pdo->prepare("
    SELECT DISTINCT t.teacher_name, t.email, t.position_title, t.status, t.created_at
    FROM teachers t
    JOIN timetable tb ON tb.teacher_id = t.teacher_id
    JOIN class_section cs ON cs.section_id = tb.section_id
    JOIN programs p ON p.program_id = cs.program_id
    WHERE p.department_id = ?
    ORDER BY t.created_at DESC
    LIMIT 5
  ");
  $stmt->execute([$department_id]);
  $recent_teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Recent teachers error: " . $e->getMessage());
  $recent_teachers = [];
}

/* ============================================================
   RECENT STUDENTS (For Department)
   ============================================================ */
$recent_students = [];
try {
  $stmt = $pdo->prepare("
    SELECT s.full_name, s.reg_no, s.email, s.status, s.created_at, cs.section_name
    FROM students s
    JOIN class_section cs ON cs.section_id = s.section_id
    JOIN programs p ON p.program_id = cs.program_id
    WHERE p.department_id = ?
    ORDER BY s.created_at DESC
    LIMIT 5
  ");
  $stmt->execute([$department_id]);
  $recent_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Recent students error: " . $e->getMessage());
  $recent_students = [];
}

/* ============================================================
   RECENT PROGRAMS (For Department)
   ============================================================ */
$recent_programs = [];
try {
  $stmt = $pdo->prepare("
    SELECT program_name, program_code, status, created_at
    FROM programs 
    WHERE department_id = ?
    ORDER BY created_at DESC
    LIMIT 5
  ");
  $stmt->execute([$department_id]);
  $recent_programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Recent programs error: " . $e->getMessage());
  $recent_programs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Department Dashboard | Hormuud University</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== GLOBAL COLORS ===== */
:root {
  --primary-green: #00843D;
  --blue-accent: #0072CE;
  --light-green: #00A651;
  --dark-gray: #333333;
  --light-gray: #F5F9F7;
  --danger-red: #C62828;
  --warning-amber: #FFB400;
  --purple: #6A1B9A;
  --white: #FFFFFF;
}

/* ===== LAYOUT ===== */
body {
  background: var(--light-gray);
  color: var(--dark-gray);
  font-family: 'Segoe UI', sans-serif;
  margin: 0;
  padding: 0;
}
.dashboard-wrapper {
  margin-left: 250px;
  padding: 100px 30px 60px;
  transition: .3s;
  min-height: 100vh;
}
.sidebar.collapsed ~ .dashboard-wrapper { margin-left: 70px; }

/* ===== BANNER ===== */
.welcome-banner {
  background: linear-gradient(135deg, var(--primary-green), var(--blue-accent));
  color: var(--white);
  text-align: center;
  border-radius: 12px;
  padding: 35px 20px;
  margin-bottom: 40px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
.welcome-banner h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
.welcome-banner p { font-size: 15px; opacity: .9; margin: 0; }

/* ===== CARDS ===== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 20px;
  margin-bottom: 40px;
}
.card {
  background: var(--white);
  border-radius: 12px;
  padding: 25px;
  text-align: center;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transition: .3s;
  border-top: 4px solid var(--blue-accent);
}
.card:hover { transform: translateY(-6px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
.counter { font-size: 34px; font-weight: bold; color: var(--blue-accent); margin-bottom: 5px; }
.card p { font-weight: 600; margin-bottom: 4px; color: var(--dark-gray); font-size: 16px; }
.card small { color: #666; font-size: 13px; }

/* ===== DASHBOARD CONTENT ===== */
.dashboard-content {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 25px;
  margin-top: 20px;
}

.charts-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 25px;
}
.chart-box {
  background: var(--white);
  border-radius: 15px;
  padding: 25px 15px 30px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.1);
  text-align: center;
  border-top: 6px solid var(--primary-green);
  opacity: 0;
  transform: translateY(40px);
  transition: all .8s ease;
}
.chart-box.visible { opacity: 1; transform: translateY(0); }
.chart-box h3 { color: var(--blue-accent); font-size: 17px; font-weight: 700; margin-bottom: 14px; }
canvas { width: 100%!important; height: 320px!important; }

/* ===== SIDEBAR SECTIONS ===== */
.sidebar-sections {
  display: flex;
  flex-direction: column;
  gap: 25px;
}

.announcements-section,
.teachers-section,
.students-section,
.programs-section {
  background: var(--white);
  border-radius: 15px;
  padding: 25px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.1);
}

.announcements-section { border-top: 6px solid var(--warning-amber); }
.teachers-section { border-top: 6px solid var(--blue-accent); }
.students-section { border-top: 6px solid var(--primary-green); }
.programs-section { border-top: 6px solid var(--purple); }

.announcements-section h3,
.teachers-section h3,
.students-section h3,
.programs-section h3 {
  color: var(--blue-accent);
  font-size: 17px;
  font-weight: 700;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--light-gray);
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ===== ANNOUNCEMENTS STYLES ===== */
.announcement-item {
  background: var(--light-gray);
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 15px;
  border-left: 4px solid var(--warning-amber);
  transition: all 0.3s ease;
}
.announcement-item:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.announcement-title {
  font-weight: 600;
  color: var(--dark-gray);
  margin-bottom: 8px;
  font-size: 15px;
}
.announcement-message {
  color: #666;
  font-size: 13px;
  margin-bottom: 10px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.announcement-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #888;
}
.announcement-role {
  background: var(--blue-accent);
  color: white;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
}
.announcement-status {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

/* ===== TEACHERS STYLES ===== */
.teacher-item {
  background: var(--light-gray);
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 15px;
  border-left: 4px solid var(--blue-accent);
  transition: all 0.3s ease;
}
.teacher-item:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.teacher-name {
  font-weight: 600;
  color: var(--dark-gray);
  margin-bottom: 5px;
  font-size: 15px;
}
.teacher-position {
  color: var(--blue-accent);
  font-size: 13px;
  margin-bottom: 8px;
  font-weight: 500;
}
.teacher-email {
  color: #666;
  font-size: 12px;
  margin-bottom: 10px;
}
.teacher-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #888;
}
.teacher-status {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

/* ===== STUDENTS STYLES ===== */
.student-item {
  background: var(--light-gray);
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 15px;
  border-left: 4px solid var(--primary-green);
  transition: all 0.3s ease;
}
.student-item:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.student-name {
  font-weight: 600;
  color: var(--dark-gray);
  margin-bottom: 5px;
  font-size: 15px;
}
.student-reg {
  color: var(--blue-accent);
  font-size: 13px;
  margin-bottom: 8px;
  font-weight: 500;
}
.student-email {
  color: #666;
  font-size: 12px;
  margin-bottom: 8px;
}
.student-section {
  color: #888;
  font-size: 12px;
  margin-bottom: 10px;
}
.student-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #888;
}
.student-status {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

/* ===== PROGRAMS STYLES ===== */
.program-item {
  background: var(--light-gray);
  border-radius: 10px;
  padding: 15px;
  margin-bottom: 15px;
  border-left: 4px solid var(--purple);
  transition: all 0.3s ease;
}
.program-item:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.program-name {
  font-weight: 600;
  color: var(--dark-gray);
  margin-bottom: 5px;
  font-size: 15px;
}
.program-code {
  color: var(--purple);
  font-size: 13px;
  margin-bottom: 8px;
  font-weight: 500;
}
.program-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
  color: #888;
}
.program-status {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
}

/* ===== STATUS COLORS ===== */
.status-active { background: #E8F5E8; color: #2E7D32; }
.status-inactive { background: #FFEBEE; color: #C62828; }

.no-data {
  text-align: center;
  color: #666;
  font-style: italic;
  padding: 30px 0;
}

/* ===== RESPONSIVE DESIGN ===== */
@media(max-width: 1200px) {
  .dashboard-content {
    grid-template-columns: 1fr;
  }
}

@media(max-width: 768px) {
  .dashboard-wrapper { 
    margin-left: 0; 
    padding: 90px 15px 30px;
  }
  .charts-container {
    grid-template-columns: 1fr;
  }
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
  }
  .card {
    padding: 20px 15px;
  }
  .counter {
    font-size: 28px;
  }
}

@media(max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .welcome-banner {
    padding: 25px 15px;
  }
  .welcome-banner h1 {
    font-size: 24px;
  }
}
</style>
</head>
<body>

<div class="dashboard-wrapper">
  <div class="welcome-banner">
    <h1>Welcome, <?= htmlspecialchars(ucfirst($name)) ?> 👋</h1>
    <p>
      Role: <strong><?= ucfirst(str_replace('_', ' ', $role)) ?></strong> |
      Department: <strong><?= htmlspecialchars($department_name) ?></strong> |
      Faculty: <strong><?= htmlspecialchars($faculty_name) ?></strong> |
      Date: <?= date("d M, Y") ?>
    </p>
  </div>

  <!-- ✅ STATS -->
  <div class="stats-grid">
    <div class="card">
      <div class="counter" data-target="<?= $aProg ?>"></div>
      <p>Active Programs</p>
      <small>Inactive: <?= $iProg ?></small>
    </div>
    <div class="card">
      <div class="counter" data-target="<?= $aSec ?>"></div>
      <p>Active Sections</p>
      <small>Inactive: <?= $iSec ?></small>
    </div>
    <div class="card">
      <div class="counter" data-target="<?= $aTea ?>"></div>
      <p>Active Teachers</p>
      <small>Inactive: <?= $iTea ?></small>
    </div>
    <div class="card">
      <div class="counter" data-target="<?= $aStu ?>"></div>
      <p>Active Students</p>
      <small>Inactive: <?= $iStu ?></small>
    </div>
    <div class="card">
      <div class="counter" data-target="<?= $aPar ?>"></div>
      <p>Active Parents</p>
      <small>Inactive: <?= $iPar ?></small>
    </div>
    <div class="card">
      <div class="counter" data-target="<?= $aAnn ?>"></div>
      <p>Active Announcements</p>
      <small>Inactive: <?= $iAnn ?></small>
    </div>
  </div>

  <!-- ✅ CHARTS & SIDEBAR SECTIONS -->
  <div class="dashboard-content">
    <div class="charts-container">
      <div class="chart-box">
        <h3>Active vs Inactive Overview</h3>
        <canvas id="barChart"></canvas>
      </div>
      <div class="chart-box">
        <h3>Active Distribution</h3>
        <canvas id="pieChart"></canvas>
      </div>
      <div class="chart-box">
        <h3>Growth Trend</h3>
        <canvas id="lineChart"></canvas>
      </div>
    </div>

    <!-- ✅ SIDEBAR SECTIONS -->
    <div class="sidebar-sections">
      <!-- RECENT ANNOUNCEMENTS -->
      <div class="announcements-section">
        <h3>📢 Recent Announcements</h3>
        <?php if (!empty($recent_announcements)): ?>
          <?php foreach ($recent_announcements as $announcement): ?>
            <div class="announcement-item">
              <div class="announcement-title">
                <?= htmlspecialchars($announcement['title']) ?>
              </div>
              <div class="announcement-message">
                <?= htmlspecialchars(substr($announcement['message'], 0, 100)) ?>...
              </div>
              <div class="announcement-meta">
                <span><?= date('M d, Y', strtotime($announcement['created_at'])) ?></span>
                <span class="announcement-role"><?= htmlspecialchars($announcement['target_role']) ?></span>
                <span class="announcement-status status-<?= htmlspecialchars($announcement['status']) ?>">
                  <?= htmlspecialchars(ucfirst($announcement['status'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-data">
            No recent announcements found.
          </div>
        <?php endif; ?>
      </div>

      <!-- RECENT TEACHERS -->
      <div class="teachers-section">
        <h3>👨‍🏫 Recent Teachers</h3>
        <?php if (!empty($recent_teachers)): ?>
          <?php foreach ($recent_teachers as $teacher): ?>
            <div class="teacher-item">
              <div class="teacher-name">
                <?= htmlspecialchars($teacher['teacher_name']) ?>
              </div>
              <div class="teacher-position">
                <?= htmlspecialchars($teacher['position_title'] ?? 'Teacher') ?>
              </div>
              <div class="teacher-email">
                <?= htmlspecialchars($teacher['email']) ?>
              </div>
              <div class="teacher-meta">
                <span><?= date('M d, Y', strtotime($teacher['created_at'])) ?></span>
                <span class="teacher-status status-<?= htmlspecialchars($teacher['status']) ?>">
                  <?= htmlspecialchars(ucfirst($teacher['status'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-data">
            No teachers found in this department.
          </div>
        <?php endif; ?>
      </div>

      <!-- RECENT STUDENTS -->
      <div class="students-section">
        <h3>👨‍🎓 Recent Students</h3>
        <?php if (!empty($recent_students)): ?>
          <?php foreach ($recent_students as $student): ?>
            <div class="student-item">
              <div class="student-name">
                <?= htmlspecialchars($student['full_name']) ?>
              </div>
              <div class="student-reg">
                <?= htmlspecialchars($student['reg_no']) ?>
              </div>
              <div class="student-email">
                <?= htmlspecialchars($student['email']) ?>
              </div>
              <div class="student-section">
                Section: <?= htmlspecialchars($student['section_name']) ?>
              </div>
              <div class="student-meta">
                <span><?= date('M d, Y', strtotime($student['created_at'])) ?></span>
                <span class="student-status status-<?= htmlspecialchars($student['status']) ?>">
                  <?= htmlspecialchars(ucfirst($student['status'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-data">
            No students found in this department.
          </div>
        <?php endif; ?>
      </div>

      <!-- RECENT PROGRAMS -->
      <div class="programs-section">
        <h3>📚 Recent Programs</h3>
        <?php if (!empty($recent_programs)): ?>
          <?php foreach ($recent_programs as $program): ?>
            <div class="program-item">
              <div class="program-name">
                <?= htmlspecialchars($program['program_name']) ?>
              </div>
              <div class="program-code">
                <?= htmlspecialchars($program['program_code'] ?? 'N/A') ?>
              </div>
              <div class="program-meta">
                <span><?= date('M d, Y', strtotime($program['created_at'])) ?></span>
                <span class="program-status status-<?= htmlspecialchars($program['status']) ?>">
                  <?= htmlspecialchars(ucfirst($program['status'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="no-data">
            No programs found in this department.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include('../includes/footer.php'); ?>

<script>
/* COUNTER ANIMATION */
document.querySelectorAll('.counter').forEach(counter=>{
  counter.innerText='0';
  const update=()=>{
    const target=+counter.dataset.target;
    const c=+counter.innerText;
    const inc=target/60;
    if(c<target){counter.innerText=Math.ceil(c+inc);setTimeout(update,20);}else counter.innerText=target;
  };
  update();
});

/* CHART DATA */
const labels=['Programs','Sections','Teachers','Students','Parents','Announcement'];
const activeData=[<?= "$aProg,$aSec,$aTea,$aStu,$aPar,$aAnn" ?>];
const inactiveData=[<?= "$iProg,$iSec,$iTea,$iStu,$iPar,$iAnn" ?>];
const colors=['#0072CE','#00843D','#00A651','#FFB400','#C62828','#6A1B9A'];

/* BAR CHART */
new Chart(document.getElementById('barChart'),{
  type:'bar',
  data:{labels,datasets:[
    {label:'Active',data:activeData,backgroundColor:'#00843D'},
    {label:'Inactive',data:inactiveData,backgroundColor:'#C62828'}
  ]},
  options:{
    responsive:true,
    scales:{
      y:{beginAtZero:true}
    },
    plugins: {
      legend: {
        position: 'top',
      }
    }
  }
});

/* PIE CHART */
new Chart(document.getElementById('pieChart'),{
  type:'pie',
  data:{labels,datasets:[{data:activeData,backgroundColor:colors}]},
  options:{
    responsive:true,
    plugins:{
      legend:{
        position:'bottom'
      }
    }
  }
});

/* LINE CHART */
new Chart(document.getElementById('lineChart'),{
  type:'line',
  data:{labels,datasets:[
    {label:'Active',data:activeData,borderColor:'#00843D',backgroundColor:'rgba(0,132,61,0.25)',fill:true,tension:0.4},
    {label:'Inactive',data:inactiveData,borderColor:'#C62828',backgroundColor:'rgba(198,40,40,0.15)',fill:true,tension:0.4}
  ]},
  options:{
    responsive:true,
    plugins:{
      legend:{
        position:'bottom'
      }
    }
  }
});

/* FADE-IN EFFECT */
const boxes=document.querySelectorAll('.chart-box');
const observer=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}});},{threshold:0.2});
boxes.forEach(b=>observer.observe(b));
</script>
</body>
</html>