<?php
/*******************************************************************************************
 * ATTENDANCE REPORT — Hormuud University
 * Department Admin Version
 * View attendance by Day, Week, Month, Year + Hierarchy (Program → Section)
 * Includes CSV Export & Print + Low Attendance Filter
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

// Initialize filter variables from session or defaults
$prog_id = $_SESSION['attendance_filters']['program_id'] ?? '';
$section_id = $_SESSION['attendance_filters']['section_id'] ?? '';
$period = $_SESSION['attendance_filters']['period'] ?? 'day';
$date = $_SESSION['attendance_filters']['date'] ?? date('Y-m-d');
$low_only = $_SESSION['attendance_filters']['low_only'] ?? false;
$report = [];

/* ================= FETCH DEPARTMENT INFO ================= */
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

/* ================= FETCH FILTER DATA ================= */
// Get programs for current department
$stmt = $pdo->prepare("SELECT * FROM programs WHERE department_id = ? ORDER BY program_name ASC");
$stmt->execute([$department_id]);
$programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sections based on selected program or all sections in department
if (!empty($prog_id)) {
    $stmt = $pdo->prepare("
        SELECT s.* FROM class_section s
        JOIN programs p ON p.program_id = s.program_id
        WHERE p.department_id = ? AND s.program_id = ? ORDER BY s.section_name ASC
    ");
    $stmt->execute([$department_id, $prog_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT s.* FROM class_section s
        JOIN programs p ON p.program_id = s.program_id
        WHERE p.department_id = ? ORDER BY s.section_name ASC
    ");
    $stmt->execute([$department_id]);
}
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

$semesters = $pdo->query("SELECT * FROM semester ORDER BY semester_name ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ================= HANDLE FILTER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle both filter submission and AJAX updates
    if (isset($_POST['program_id']) || isset($_POST['filter'])) {
        $prog_id    = $_POST['program_id'] ?? '';
        $section_id = $_POST['section_id'] ?? '';
        $period     = $_POST['period'] ?? 'day';
        $date       = $_POST['date'] ?? date('Y-m-d');
        $low_only   = isset($_POST['low_only']);

        // Store filters in session for CSV export
        $_SESSION['attendance_filters'] = [
            'program_id' => $prog_id,
            'section_id' => $section_id,
            'period' => $period,
            'date' => $date,
            'low_only' => $low_only,
            'department_id' => $department_id
        ];

        // Only run the main query if "Generate" button was clicked
        if (isset($_POST['filter'])) {
            $params = [];
            $filter_sql = "";

            // ====== Period filter ======
            switch ($period) {
                case 'week':
                    $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
                    $end   = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
                    $filter_sql .= " AND a.attendance_date BETWEEN ? AND ?";
                    $params[] = $start; 
                    $params[] = $end;
                    break;
                case 'month':
                    $start = date('Y-m-01', strtotime($date));
                    $end   = date('Y-m-t', strtotime($date));
                    $filter_sql .= " AND a.attendance_date BETWEEN ? AND ?";
                    $params[] = $start; 
                    $params[] = $end;
                    break;
                case 'year':
                    $year = date('Y', strtotime($date));
                    $filter_sql .= " AND YEAR(a.attendance_date)=?";
                    $params[] = $year;
                    break;
                default: // day
                    $filter_sql .= " AND a.attendance_date=?";
                    $params[] = $date;
            }

            // ====== Hierarchy filter ======
            if (!empty($section_id)) { 
                $filter_sql .= " AND c.section_id=?"; 
                $params[] = $section_id; 
            }
            elseif (!empty($prog_id)){ 
                $filter_sql .= " AND p.program_id=?"; 
                $params[] = $prog_id; 
            }
            
            // Department-specific filter
            $filter_sql .= " AND d.department_id=?";
            $params[] = $department_id;

            // ====== Main query ======
            if ($low_only) {
                // show only students with 5 or more absences in the range
                $sql = "
                SELECT s.full_name, s.reg_no, c.section_name, p.program_name, d.department_name, 
                       f.faculty_name,
                       COUNT(CASE WHEN a.status='absent' THEN 1 END) AS absences
                FROM attendance a
                JOIN students s ON s.student_id=a.student_id
                JOIN class_section c ON c.section_id=a.section_id
                JOIN programs p ON p.program_id=c.program_id
                JOIN departments d ON d.department_id=p.department_id
                JOIN faculties f ON f.faculty_id=d.faculty_id
                WHERE 1=1 $filter_sql
                GROUP BY s.student_id
                HAVING absences >= 5
                ORDER BY absences DESC
                ";
            } else {
                $sql = "
                SELECT a.attendance_date, s.full_name, s.reg_no, c.section_name, p.program_name, 
                       d.department_name, f.faculty_name, a.status, t.teacher_name
                FROM attendance a
                JOIN students s ON s.student_id=a.student_id
                JOIN class_section c ON c.section_id=a.section_id
                JOIN programs p ON p.program_id=c.program_id
                JOIN departments d ON d.department_id=p.department_id
                JOIN faculties f ON f.faculty_id=d.faculty_id
                LEFT JOIN teachers t ON t.teacher_id=a.teacher_id
                WHERE 1=1 $filter_sql
                ORDER BY a.attendance_date DESC, s.reg_no ASC
                ";
            }
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Attendance report error: " . $e->getMessage());
                $report = [];
            }
        }
    }
}

/* ================= EXPORT CSV ================= */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Get filter parameters from session
    $export_filters = $_SESSION['attendance_filters'] ?? [];
    
    if (empty($export_filters)) {
        die("⚠️ No filter data found. Please generate a report first.");
    }
    
    $export_prog_id = $export_filters['program_id'] ?? '';
    $export_section_id = $export_filters['section_id'] ?? '';
    $export_period = $export_filters['period'] ?? 'day';
    $export_date = $export_filters['date'] ?? date('Y-m-d');
    $export_low_only = $export_filters['low_only'] ?? false;
    $export_department_id = $export_filters['department_id'] ?? $department_id;

    $export_params = [];
    $export_filter_sql = "";

    // Rebuild period filter
    switch ($export_period) {
        case 'week':
            $start = date('Y-m-d', strtotime('monday this week', strtotime($export_date)));
            $end   = date('Y-m-d', strtotime('sunday this week', strtotime($export_date)));
            $export_filter_sql .= " AND a.attendance_date BETWEEN ? AND ?";
            $export_params[] = $start; 
            $export_params[] = $end;
            break;
        case 'month':
            $start = date('Y-m-01', strtotime($export_date));
            $end   = date('Y-m-t', strtotime($export_date));
            $export_filter_sql .= " AND a.attendance_date BETWEEN ? AND ?";
            $export_params[] = $start; 
            $export_params[] = $end;
            break;
        case 'year':
            $year = date('Y', strtotime($export_date));
            $export_filter_sql .= " AND YEAR(a.attendance_date)=?";
            $export_params[] = $year;
            break;
        default:
            $export_filter_sql .= " AND a.attendance_date=?";
            $export_params[] = $export_date;
    }

    // Rebuild hierarchy filter
    if (!empty($export_section_id)) { 
        $export_filter_sql .= " AND c.section_id=?"; 
        $export_params[] = $export_section_id; 
    }
    elseif (!empty($export_prog_id)){ 
        $export_filter_sql .= " AND p.program_id=?"; 
        $export_params[] = $export_prog_id; 
    }
    
    $export_filter_sql .= " AND d.department_id=?";
    $export_params[] = $export_department_id;

    // Build export query
    if ($export_low_only) {
        $export_sql = "
        SELECT s.full_name, s.reg_no, c.section_name, p.program_name, d.department_name, 
               f.faculty_name,
               COUNT(CASE WHEN a.status='absent' THEN 1 END) AS absences
        FROM attendance a
        JOIN students s ON s.student_id=a.student_id
        JOIN class_section c ON c.section_id=a.section_id
        JOIN programs p ON p.program_id=c.program_id
        JOIN departments d ON d.department_id=p.department_id
        JOIN faculties f ON f.faculty_id=d.faculty_id
        WHERE 1=1 $export_filter_sql
        GROUP BY s.student_id
        HAVING absences >= 5
        ORDER BY absences DESC
        ";
    } else {
        $export_sql = "
        SELECT a.attendance_date, s.full_name, s.reg_no, c.section_name, p.program_name, 
               d.department_name, f.faculty_name, a.status, t.teacher_name
        FROM attendance a
        JOIN students s ON s.student_id=a.student_id
        JOIN class_section c ON c.section_id=a.section_id
        JOIN programs p ON p.program_id=c.program_id
        JOIN departments d ON d.department_id=p.department_id
        JOIN faculties f ON f.faculty_id=d.faculty_id
        LEFT JOIN teachers t ON t.teacher_id=a.teacher_id
        WHERE 1=1 $export_filter_sql
        ORDER BY a.attendance_date DESC, s.reg_no ASC
        ";
    }
    
    try {
        $stmt = $pdo->prepare($export_sql);
        $stmt->execute($export_params);
        $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error generating export: " . $e->getMessage());
    }

    if (!$export_data) {
        die("⚠️ No report data to export.");
    }

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=attendance_report_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fputs($output, "\xEF\xBB\xBF");
    
    // Write headers
    if ($export_low_only) {
        fputcsv($output, ['Student Name', 'Registration No', 'Section', 'Program', 'Department', 'Faculty', 'Absences']);
    } else {
        fputcsv($output, ['Date', 'Student Name', 'Registration No', 'Section', 'Program', 'Department', 'Faculty', 'Teacher', 'Status']);
    }
    
    // Write data
    foreach ($export_data as $row) {
        if ($export_low_only) {
            fputcsv($output, [
                $row['full_name'],
                $row['reg_no'],
                $row['section_name'],
                $row['program_name'],
                $row['department_name'],
                $row['faculty_name'],
                $row['absences']
            ]);
        } else {
            fputcsv($output, [
                $row['attendance_date'],
                $row['full_name'],
                $row['reg_no'],
                $row['section_name'],
                $row['program_name'],
                $row['department_name'],
                $row['faculty_name'],
                $row['teacher_name'] ?? 'N/A',
                $row['status']
            ]);
        }
    }
    
    fclose($output);
    exit;
}

include('../includes/header.php');
?>

<div class="main-content">
<div class="page-header">
    <h1><i class="fa fa-chart-bar"></i> Attendance Report — <?= htmlspecialchars($department_name) ?></h1>
    <div class="department-info">
        <!-- <strong>Faculty:</strong> <?= htmlspecialchars($faculty_name) ?> -->
    </div>
</div>

<!-- FILTER FORM -->
<form method="POST" class="filter-box" id="reportForm">
    <div class="grid">
        <div><label>Program</label>
            <select name="program_id" id="program" onchange="this.form.submit()">
                <option value="">All Programs</option>
                <?php foreach($programs as $p): ?>
                    <option value="<?= $p['program_id'] ?>" <?= ($prog_id == $p['program_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['program_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div><label>Section</label>
            <select name="section_id" id="section">
                <option value="">All Sections</option>
                <?php foreach($sections as $s): ?>
                    <option value="<?= $s['section_id'] ?>" <?= ($section_id == $s['section_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['section_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Period Type</label>
            <select name="period" id="period">
                <option value="day" <?= ($period == 'day') ? 'selected' : '' ?>>By Day</option>
                <option value="week" <?= ($period == 'week') ? 'selected' : '' ?>>By Week</option>
                <option value="month" <?= ($period == 'month') ? 'selected' : '' ?>>By Month</option>
                <option value="year" <?= ($period == 'year') ? 'selected' : '' ?>>By Year</option>
            </select>
        </div>
        
        <div>
            <label>Date</label>
            <input type="date" name="date" id="date" value="<?= htmlspecialchars($date ?: date('Y-m-d')) ?>">
        </div>
        
        <div class="checkbox-container">
            <label class="checkbox-label">
                <input type="checkbox" name="low_only" <?= $low_only ? 'checked' : '' ?>>
                <span class="checkmark"></span>
                Only Students with ≥5 Absences
            </label>
        </div>
        
        <div style="align-self:end; display: flex; gap: 10px;">
            <button type="submit" name="filter" class="btn green"><i class="fa fa-search"></i> Generate</button>
            <button type="button" onclick="resetFilters()" class="btn gray"><i class="fa fa-refresh"></i> Reset</button>
        </div>
    </div>
</form>

<!-- EXPORT BUTTONS -->
<?php if($report): ?>
<div class="export-box">
    <a href="?export=csv" class="btn blue" onclick="return confirm('Download CSV report?')">
        <i class="fa fa-file-excel"></i> Download CSV
    </a>
    <div class="report-summary">
        <strong>Total Records: <?= count($report) ?></strong>
        <?php if($low_only): ?>
            | <span style="color:#C62828;">Showing students with ≥5 absences</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- REPORT TABLE -->
<div class="table-wrapper">
    <h3 style="color:#0072CE;margin:10px; padding: 10px;">
        Filtered Attendance Results
        <?php if(isset($period) && isset($date)): ?>
            <small style="color:#666; font-size:14px;">
                (Period: <?= htmlspecialchars($period) ?> - <?= htmlspecialchars($date) ?>)
            </small>
        <?php endif; ?>
        <?php if(!empty($prog_id)): ?>
            <br><small style="color:#666; font-size:14px;">
                Program: <?= htmlspecialchars($programs[array_search($prog_id, array_column($programs, 'program_id'))]['program_name'] ?? 'Unknown') ?>
            </small>
        <?php endif; ?>
    </h3>
    
    <table id="attendanceTable">
        <thead>
            <tr>
                <?php if($low_only): ?>
                    <th>#</th>
                    <th>Student Name</th>
                    <th>Reg No</th>
                    <th>Section</th>
                    <th>Program</th>
                    <th>Department</th>
                    <th>Faculty</th>
                    <th>Absences</th>
                <?php else: ?>
                    <th>#</th>
                    <th>Date</th>
                    <th>Student Name</th>
                    <th>Reg No</th>
                    <th>Section</th>
                    <th>Program</th>
                    <th>Department</th>
                    <th>Faculty</th>
                    <th>Teacher</th>
                    <th>Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if(!$report): ?>
                <tr>
                    <td colspan="<?= $low_only ? '8' : '10' ?>" style="text-align:center;color:#777; padding: 20px;">
                        <?php if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filter'])): ?>
                            No attendance records found for the selected filters.
                        <?php else: ?>
                            Use the filters above to generate an attendance report.
                        <?php endif; ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach($report as $index => $r): ?>
                    <tr>
                        <?php if($low_only): ?>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($r['full_name']) ?></td>
                            <td><?= htmlspecialchars($r['reg_no']) ?></td>
                            <td><?= htmlspecialchars($r['section_name']) ?></td>
                            <td><?= htmlspecialchars($r['program_name']) ?></td>
                            <td><?= htmlspecialchars($r['department_name']) ?></td>
                            <td><?= htmlspecialchars($r['faculty_name']) ?></td>
                            <td style="color:#C62828;font-weight:600;"><?= htmlspecialchars($r['absences']) ?></td>
                        <?php else: ?>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($r['attendance_date']) ?></td>
                            <td><?= htmlspecialchars($r['full_name']) ?></td>
                            <td><?= htmlspecialchars($r['reg_no']) ?></td>
                            <td><?= htmlspecialchars($r['section_name']) ?></td>
                            <td><?= htmlspecialchars($r['program_name']) ?></td>
                            <td><?= htmlspecialchars($r['department_name']) ?></td>
                            <td><?= htmlspecialchars($r['faculty_name']) ?></td>
                            <td><?= htmlspecialchars($r['teacher_name'] ?? 'N/A') ?></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($r['status']) ?>">
                                    <?= htmlspecialchars(ucfirst($r['status'])) ?>
                                </span>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- ✅ STYLE -->
<style>
body{font-family:'Poppins',sans-serif;background:#f5f9f7;margin:0;}
.main-content{padding:25px;margin-left:250px;margin-top:90px;}
.page-header h1{color:#0072CE;margin-bottom:15px;}
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
.filter-box{background:#fff;padding:20px;border-radius:10px;box-shadow:0 3px 8px rgba(0,0,0,0.08);margin-bottom:20px;}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:15px;}
label{font-weight:600;color:#0072CE;font-size:13px;}
select,input[type=date]{width:100%;padding:8px;border:1px solid #ccc;border-radius:6px;background:#fafafa;}
.btn{border:none;padding:8px 15px;border-radius:6px;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block;font-size:13px;}
.btn.green{background:#00843D;color:#fff;}
.btn.blue{background:#0072CE;color:#fff;}
.btn.gray{background:#666;color:#fff;}
.export-box{margin:20px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
.report-summary{margin-left:auto;color:#666;font-size:14px;}
.table-wrapper{overflow:auto;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,0.08);}
table{width:100%;border-collapse:collapse;}
th,td{padding:10px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px;}
thead th{background:#0072CE;color:#fff;position:sticky;top:0;}
tr:hover{background:#f3f8ff;}
.checkbox-container{margin-top:8px;}
.checkbox-label{display:flex;align-items:center;cursor:pointer;font-weight:normal;}
.checkbox-label input{margin-right:8px;}
.status-badge{padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;}
.status-present{background:#E8F5E8;color:#2E7D32;}
.status-absent{background:#FFEBEE;color:#C62828;}
.status-late{background:#FFF3E0;color:#EF6C00;}

/* Print Styles */
@media print {
    .main-content{margin-left:0!important;padding:0!important;}
    .filter-box, .export-box, .page-header{display:none!important;}
    body{background:white!important;}
    .table-wrapper{box-shadow:none!important;}
    table{width:100%;font-size:12px;}
    th{background:#0072CE!important;color:white!important;-webkit-print-color-adjust:exact;}
}

@media(max-width:768px){
    .main-content{margin-left:0;padding:15px;}
    .grid{grid-template-columns:1fr;}
    .export-box{flex-direction:column;align-items:flex-start;}
    .report-summary{margin-left:0;margin-top:10px;}
}
</style>

<script>
function resetFilters() {
    document.getElementById('program').value = '';
    document.getElementById('section').value = '';
    document.getElementById('period').value = 'day';
    document.getElementById('date').value = '<?= date('Y-m-d') ?>';
    document.querySelector('input[name="low_only"]').checked = false;
    document.getElementById('reportForm').submit();
}

// Update date input based on period selection
document.getElementById('period').addEventListener('change', function() {
    const period = this.value;
    const dateInput = document.getElementById('date');
    
    if (period === 'month') {
        dateInput.type = 'month';
        dateInput.value = '<?= date('Y-m') ?>';
    } else if (period === 'year') {
        dateInput.type = 'number';
        dateInput.min = '2000';
        dateInput.max = '2030';
        dateInput.value = '<?= date('Y') ?>';
    } else {
        dateInput.type = 'date';
        if (period === 'week') {
            // Set to current week's Monday
            const today = new Date();
            const day = today.getDay();
            const diff = today.getDate() - day + (day === 0 ? -6 : 1);
            const monday = new Date(today.setDate(diff));
            dateInput.value = monday.toISOString().split('T')[0];
        } else {
            dateInput.value = '<?= date('Y-m-d') ?>';
        }
    }
});

// Initialize date input based on current period selection
document.addEventListener('DOMContentLoaded', function() {
    const period = document.getElementById('period').value;
    const dateInput = document.getElementById('date');
    
    if (period === 'month' && dateInput.type !== 'month') {
        dateInput.type = 'month';
    } else if (period === 'year' && dateInput.type !== 'number') {
        dateInput.type = 'number';
    }
});
</script>

<?php include('../includes/footer.php'); ?>
<?php ob_end_flush(); ?>