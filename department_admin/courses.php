<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Access Control (Both Faculty Admin and Department Admin)
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

$message = "";
$type = "";

/* ===========================================
   CRUD OPERATIONS (Role-Based Scoping)
=========================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 🟢 ADD SUBJECT - Only for faculty_admin
    if ($_POST['action'] === 'add' && $role === 'faculty_admin') {
        try {
            $department = $_POST['department_id'] ?? null;
            $program = $_POST['program_id'] ?? null;
            $section = $_POST['section_id'] ?? null;
            $semester = $_POST['semester_id'] ?? null;
            $name = trim($_POST['subject_name'] ?? '');
            $code = trim($_POST['subject_code'] ?? '');
            $credit = $_POST['credit_hours'] ?? null;
            $desc = trim($_POST['description'] ?? '');
            $status = $_POST['status'] ?? 'active';

            // Validation
            if (empty($department) || empty($program) || empty($section) || empty($semester) || empty($name) || empty($code) || empty($credit)) {
                throw new Exception("All required fields must be filled!");
            }

            if (strlen($name) < 2 || strlen($name) > 255) {
                throw new Exception("Subject name must be between 2 and 255 characters!");
            }

            if (!preg_match('/^[A-Z0-9]{2,20}$/', $code)) {
                throw new Exception("Subject code must be 2-20 characters containing only uppercase letters and numbers!");
            }

            if ($credit < 0.5 || $credit > 10) {
                throw new Exception("Credit hours must be between 0.5 and 10!");
            }

            // Check if department belongs to faculty
            $checkDept = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_id = ? AND faculty_id = ?");
            $checkDept->execute([$department, $faculty_id]);
            if ($checkDept->fetchColumn() == 0) {
                throw new Exception("Invalid department selected!");
            }

            // Check if program belongs to department
            $checkProg = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE program_id = ? AND department_id = ?");
            $checkProg->execute([$program, $department]);
            if ($checkProg->fetchColumn() == 0) {
                throw new Exception("Invalid program selected!");
            }

            // Check if section belongs to program
            $checkSec = $pdo->prepare("SELECT COUNT(*) FROM class_section WHERE section_id = ? AND program_id = ?");
            $checkSec->execute([$section, $program]);
            if ($checkSec->fetchColumn() == 0) {
                throw new Exception("Invalid section selected!");
            }

            // Check duplicate subject code in faculty
            $checkCode = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_code = ? AND faculty_id = ?");
            $checkCode->execute([$code, $faculty_id]);
            if ($checkCode->fetchColumn() > 0) {
                throw new Exception("Subject code already exists in this faculty!");
            }

            // Check duplicate subject name in same program
            $checkName = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_name = ? AND program_id = ? AND faculty_id = ?");
            $checkName->execute([$name, $program, $faculty_id]);
            if ($checkName->fetchColumn() > 0) {
                throw new Exception("Subject name already exists in this program!");
            }

            $stmt = $pdo->prepare("INSERT INTO subject 
                (faculty_id, department_id, program_id, section_id, semester_id, subject_name, subject_code, credit_hours, description, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?, NOW())");
            $stmt->execute([$faculty_id, $department, $program, $section, $semester, $name, $code, $credit, $desc, $status]);

            $message = "✅ Subject added successfully!";
            $type = "success";
        } catch (PDOException $e) {
            $message = "❌ Database Error: " . $e->getMessage();
            $type = "error";
        } catch (Exception $e) {
            $message = "❌ " . $e->getMessage();
            $type = "error";
        }
    }

    // 🟡 UPDATE SUBJECT - Both roles can update with restrictions
    if ($_POST['action'] === 'update') {
        try {
            $id = $_POST['subject_id'];
            $name = trim($_POST['subject_name']);
            $code = trim($_POST['subject_code']);
            $credit = $_POST['credit_hours'];
            $desc = trim($_POST['description']);
            $status = $_POST['status'];

            // Validation
            if (empty($name) || empty($code) || empty($credit)) {
                throw new Exception("All required fields must be filled!");
            }

            if (strlen($name) < 2 || strlen($name) > 255) {
                throw new Exception("Subject name must be between 2 and 255 characters!");
            }

            if (!preg_match('/^[A-Z0-9]{2,20}$/', $code)) {
                throw new Exception("Subject code must be 2-20 characters containing only uppercase letters and numbers!");
            }

            if ($credit < 0.5 || $credit > 10) {
                throw new Exception("Credit hours must be between 0.5 and 10!");
            }

            // Build query based on role
            if ($role === 'faculty_admin') {
                $department = $_POST['department_id'];
                $program = $_POST['program_id'];
                $section = $_POST['section_id'];
                $semester = $_POST['semester_id'];

                if (empty($department) || empty($program) || empty($section) || empty($semester)) {
                    throw new Exception("All required fields must be filled!");
                }

                // Check if subject exists and belongs to faculty
                $checkSubject = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_id = ? AND faculty_id = ?");
                $checkSubject->execute([$id, $faculty_id]);
                if ($checkSubject->fetchColumn() == 0) {
                    throw new Exception("Subject not found or access denied!");
                }

                // Check if department belongs to faculty
                $checkDept = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE department_id = ? AND faculty_id = ?");
                $checkDept->execute([$department, $faculty_id]);
                if ($checkDept->fetchColumn() == 0) {
                    throw new Exception("Invalid department selected!");
                }

                // Check duplicate subject code (excluding current)
                $checkCode = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_code = ? AND faculty_id = ? AND subject_id != ?");
                $checkCode->execute([$code, $faculty_id, $id]);
                if ($checkCode->fetchColumn() > 0) {
                    throw new Exception("Subject code already exists in this faculty!");
                }

                // Check duplicate subject name in same program (excluding current)
                $checkName = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_name = ? AND program_id = ? AND faculty_id = ? AND subject_id != ?");
                $checkName->execute([$name, $program, $faculty_id, $id]);
                if ($checkName->fetchColumn() > 0) {
                    throw new Exception("Subject name already exists in this program!");
                }

                $stmt = $pdo->prepare("UPDATE subject 
                    SET department_id=?, program_id=?, section_id=?, semester_id=?, subject_name=?, subject_code=?, credit_hours=?, description=?, status=?, updated_at=NOW()
                    WHERE subject_id=? AND faculty_id=?");
                $params = [$department, $program, $section, $semester, $name, $code, $credit, $desc, $status, $id, $faculty_id];
            } else { // department_admin
                // Check if subject exists and belongs to department
                $checkSubject = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_id = ? AND department_id = ?");
                $checkSubject->execute([$id, $department_id]);
                if ($checkSubject->fetchColumn() == 0) {
                    throw new Exception("Subject not found or access denied!");
                }

                // Check duplicate subject code in department (excluding current)
                $checkCode = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_code = ? AND department_id = ? AND subject_id != ?");
                $checkCode->execute([$code, $department_id, $id]);
                if ($checkCode->fetchColumn() > 0) {
                    throw new Exception("Subject code already exists in your department!");
                }

                // Check duplicate subject name in department (excluding current)
                $checkName = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_name = ? AND department_id = ? AND subject_id != ?");
                $checkName->execute([$name, $department_id, $id]);
                if ($checkName->fetchColumn() > 0) {
                    throw new Exception("Subject name already exists in your department!");
                }

                $stmt = $pdo->prepare("UPDATE subject 
                    SET subject_name=?, subject_code=?, credit_hours=?, description=?, status=?, updated_at=NOW()
                    WHERE subject_id=? AND department_id=?");
                $params = [$name, $code, $credit, $desc, $status, $id, $department_id];
            }

            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                throw new Exception("No changes made or subject not found!");
            }

            $message = "✅ Subject updated successfully!";
            $type = "success";
        } catch (PDOException $e) {
            $message = "❌ Database Error: " . $e->getMessage();
            $type = "error";
        } catch (Exception $e) {
            $message = "❌ " . $e->getMessage();
            $type = "error";
        }
    }

    // 🔴 DELETE SUBJECT - Only for faculty_admin
    if ($_POST['action'] === 'delete' && $role === 'faculty_admin') {
        try {
            $id = $_POST['subject_id'];

            // Check if subject exists and belongs to faculty
            $checkSubject = $pdo->prepare("SELECT COUNT(*) FROM subject WHERE subject_id = ? AND faculty_id = ?");
            $checkSubject->execute([$id, $faculty_id]);
            if ($checkSubject->fetchColumn() == 0) {
                throw new Exception("Subject not found or access denied!");
            }

            // Check if subject has any enrollments (safety check)
            $checkEnrollments = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE subject_id = ?");
            $checkEnrollments->execute([$id]);
            if ($checkEnrollments->fetchColumn() > 0) {
                throw new Exception("Cannot delete subject with student enrollments!");
            }

            $stmt = $pdo->prepare("DELETE FROM subject WHERE subject_id=? AND faculty_id=?");
            $stmt->execute([$id, $faculty_id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Failed to delete subject!");
            }

            $message = "🗑️ Subject deleted successfully!";
            $type = "success";
        } catch (PDOException $e) {
            $message = "❌ Database Error: " . $e->getMessage();
            $type = "error";
        } catch (Exception $e) {
            $message = "❌ " . $e->getMessage();
            $type = "error";
        }
    }
}

/* ===========================================
   FETCH ROLE-BASED DATA
=========================================== */

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

// Fetch departments based on role
if ($role === 'faculty_admin') {
    $departments = $pdo->prepare("SELECT * FROM departments WHERE faculty_id=? AND status='active' ORDER BY department_name ASC");
    $departments->execute([$faculty_id]);
    $departments = $departments->fetchAll(PDO::FETCH_ASSOC);
} else {
    $departments = $pdo->prepare("SELECT * FROM departments WHERE department_id=? AND status='active' ORDER BY department_name ASC");
    $departments->execute([$department_id]);
    $departments = $departments->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch programs based on role
if ($role === 'faculty_admin') {
    $programs = $pdo->prepare("
        SELECT p.* FROM programs p
        JOIN departments d ON p.department_id = d.department_id
        WHERE d.faculty_id=? AND p.status='active'
        ORDER BY p.program_name ASC
    ");
    $programs->execute([$faculty_id]);
    $programs = $programs->fetchAll(PDO::FETCH_ASSOC);
} else {
    $programs = $pdo->prepare("
        SELECT p.* FROM programs p
        WHERE p.department_id=? AND p.status='active'
        ORDER BY p.program_name ASC
    ");
    $programs->execute([$department_id]);
    $programs = $programs->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch sections based on role
if ($role === 'faculty_admin') {
    $sections = $pdo->prepare("
        SELECT s.* FROM class_section s
        JOIN programs p ON s.program_id = p.program_id
        JOIN departments d ON p.department_id = d.department_id
        WHERE d.faculty_id=? AND s.status='active'
        ORDER BY s.section_name ASC
    ");
    $sections->execute([$faculty_id]);
    $sections = $sections->fetchAll(PDO::FETCH_ASSOC);
} else {
    $sections = $pdo->prepare("
        SELECT s.* FROM class_section s
        JOIN programs p ON s.program_id = p.program_id
        WHERE p.department_id=? AND s.status='active'
        ORDER BY s.section_name ASC
    ");
    $sections->execute([$department_id]);
    $sections = $sections->fetchAll(PDO::FETCH_ASSOC);
}

$semesters = $pdo->query("SELECT * FROM semester WHERE status='active' ORDER BY semester_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch subjects based on role
if ($role === 'faculty_admin') {
    $stmt = $pdo->prepare("
        SELECT s.*, f.faculty_name, d.department_name, p.program_name, sec.section_name, se.semester_name
        FROM subject s
        JOIN faculties f ON s.faculty_id = f.faculty_id
        JOIN departments d ON s.department_id = d.department_id
        JOIN programs p ON s.program_id = p.program_id
        JOIN class_section sec ON s.section_id = sec.section_id
        JOIN semester se ON s.semester_id = se.semester_id
        WHERE s.faculty_id = ?
        ORDER BY s.subject_id DESC
    ");
    $stmt->execute([$faculty_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("
        SELECT s.*, f.faculty_name, d.department_name, p.program_name, sec.section_name, se.semester_name
        FROM subject s
        JOIN faculties f ON s.faculty_id = f.faculty_id
        JOIN departments d ON s.department_id = d.department_id
        JOIN programs p ON s.program_id = p.program_id
        JOIN class_section sec ON s.section_id = sec.section_id
        JOIN semester se ON s.semester_id = se.semester_id
        WHERE s.department_id = ?
        ORDER BY s.subject_id DESC
    ");
    $stmt->execute([$department_id]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Subjects Management | <?= ucfirst(str_replace('_', ' ', $role)) ?> | Hormuud University</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="../images.png">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --green:#00843D;
    --blue:#0072CE;
    --red:#C62828;
    --light-green:#00A651;
    --bg:#F5F9F7;
}
body {
    font-family:'Poppins',sans-serif;
    background:var(--bg);
    margin:0;
    color:#333;
}
.main-content {
    padding:20px;
    margin-top:90px;
    margin-left:250px;
    transition:all .3s;
}
.sidebar.collapsed ~ .main-content { margin-left:70px; }

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.page-header h1 {
    color:var(--blue);
    font-size:24px;
    font-weight:700;
    margin:0;
}
.add-btn {
    background:var(--green);
    color:#fff;
    padding:10px 18px;
    border:none;
    border-radius:6px;
    font-weight:600;
    cursor:pointer;
    transition: background 0.3s;
}
.add-btn:hover { background:var(--light-green); }
.add-btn:disabled {
    background:#ccc;
    cursor:not-allowed;
}

/* Info Sections */
.department-info {
    background:#e8f5e9;
    padding:12px 16px;
    border-radius:8px;
    margin-bottom:20px;
    border-left:4px solid var(--green);
    font-weight:600;
}
.info-banner {
    background: var(--blue);
    color: white;
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-weight: 600;
}


.table-wrapper {
    background:#fff;
    border-radius:10px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    overflow:auto;
    max-height:500px;
    margin-top: 15px;
}
table {
    width:100%;
    border-collapse:collapse;
    min-width: 1000px;
}
thead th {
    background:var(--blue);
    color:#fff;
    position:sticky;
    top:0;
    padding: 14px 16px;
    font-weight: 600;
}
th,td {
    padding:12px 14px;
    border-bottom:1px solid #eee;
    white-space:nowrap;
    text-align:left;
}
tr:hover { background:#eef8f0; }

.action-buttons {
    display:flex;
    justify-content:center;
    gap:8px;
}
.btn-edit, .btn-delete {
    border:none;
    border-radius:6px;
    padding:8px 12px;
    color:#fff;
    cursor:pointer;
    transition: background 0.3s;
}
.btn-edit { background:var(--blue); }
.btn-delete { background:var(--red); }
.btn-edit:hover { background:#2196f3; }
.btn-delete:hover { background:#e53935; }
.btn-delete:disabled {
    background:#ccc;
    cursor:not-allowed;
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-active { background: #e8f5e8; color: #2e7d32; }
.status-inactive { background: #ffebee; color: #c62828; }

.modal {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.5);
    justify-content:center;
    align-items:center;
    z-index:3000;
    padding:10px;
}
.modal.show { display:flex; }
.modal-content {
    background:#fff;
    border-radius:10px;
    width:95%;
    max-width:750px;
    padding:25px;
    position:relative;
    overflow-y:auto;
    max-height:90vh;
    border-top: 5px solid var(--blue);
}
.close-modal {
    position:absolute;
    top:10px;
    right:15px;
    font-size:22px;
    cursor:pointer;
    background: none;
    border: none;
    color: var(--red);
}
form {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
}
label {
    font-weight:600;
    color:var(--blue);
    margin-bottom: 5px;
    display: block;
}
input, select, textarea {
    width:100%;
    padding:10px;
    border:1.5px solid #ddd;
    border-radius:6px;
    font-family: inherit;
    background: #fff;
}
input:focus, select:focus, textarea:focus {
    border-color: var(--blue);
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 114, 206, 0.1);
}
textarea {
    resize:vertical;
    height:80px;
    min-height: 80px;
}
.save-btn {
    grid-column:span 2;
    background:var(--green);
    color:#fff;
    border:none;
    padding:12px;
    border-radius:6px;
    font-weight:600;
    cursor:pointer;
    font-size: 16px;
    transition: background 0.3s;
}
.save-btn:hover { background:var(--light-green); }
.save-btn.delete { background: var(--red); }
.save-btn.delete:hover { background: #e53935; }

.alert-popup {
    display:none;
    position:fixed;
    top:20px;
    right:20px;
    background:#fff;
    padding:16px 24px;
    border-radius:10px;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
    z-index:4000;
    border-left: 5px solid var(--green);
    max-width: 400px;
}
.alert-popup.show { display:block; animation:slideIn 0.5s ease; }
.alert-popup.success { border-left-color: var(--green); }
.alert-popup.error { border-left-color: var(--red); }
@keyframes slideIn {
    from { opacity:0; transform:translateX(100px); }
    to { opacity:1; transform:translateX(0); }
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}
.empty-state i {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 15px;
}

.form-group {
    grid-column: span 2;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    form {
        grid-template-columns: 1fr;
    }
    .form-group {
        grid-column: span 1;
    }
}
</style>
</head>
<body>

<?php include('../includes/header.php'); ?>

<div class="main-content">
    <!-- Department/Faculty Info -->
    <?php if ($role === 'department_admin' && !empty($department_info)): ?>
   
    <?php elseif ($role === 'faculty_admin' && !empty($faculty_info)): ?>
    <div class="info-banner">
        <i class="fas fa-university"></i> 
        Faculty: <strong><?= htmlspecialchars($faculty_info['faculty_name']) ?></strong>
    </div>
    <?php endif; ?>

    <!-- View Only Notice for Department Admin -->
    <?php if ($role === 'department_admin'): ?>
    <div class="view-only-notice">
       
        <strong> </strong>
    </div>
    <?php endif; ?>
    
    <div class="page-header">
        <h1>Subjects Management
            <!-- <?php if ($role === 'department_admin' && !empty($department_info)): ?>
                <small style="font-size: 16px; color: #666;">- <?= htmlspecialchars($department_info['department_name']) ?></small>
            <?php endif; ?> -->
        </h1>
        <button class="add-btn" onclick="openModal('addModal')" <?= $role === 'department_admin' ? 'disabled' : '' ?>>
            <i class="fas fa-plus"></i> Add Subject
        </button>
    </div>

    <div class="table-wrapper">
        <?php if (empty($subjects)): ?>
        <div class="empty-state">
            <i class="fas fa-book"></i>
            <h3>No Subjects Found</h3>
            <p>
                <?php if ($role === 'department_admin'): ?>
                    No subjects found in your department.
                <?php else: ?>
                    Get started by adding your first subject to this faculty.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($role === 'faculty_admin'): ?>
                        <th>Department</th>
                    <?php endif; ?>
                    <th>Program</th>
                    <th>Section</th>
                    <th>Semester</th>
                    <th>Subject Name</th>
                    <th>Code</th>
                    <th>Credits</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($subjects as $i=>$s): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <?php if ($role === 'faculty_admin'): ?>
                        <td><?= htmlspecialchars($s['department_name']) ?></td>
                    <?php endif; ?>
                    <td><?= htmlspecialchars($s['program_name']) ?></td>
                    <td><?= htmlspecialchars($s['section_name']) ?></td>
                    <td><?= htmlspecialchars($s['semester_name']) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($s['subject_name']) ?></strong>
                        <?php if (!empty($s['description'])): ?>
                        <br><small style="color: #666;"><?= htmlspecialchars(substr($s['description'], 0, 50)) ?><?= strlen($s['description']) > 50 ? '...' : '' ?></small>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($s['subject_code']) ?></code></td>
                    <td><?= $s['credit_hours'] ?></td>
                    <td>
                        <span class="status-badge status-<?= $s['status'] ?>">
                            <?= ucfirst($s['status']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn-edit" onclick="openEditModal(
                                <?= $s['subject_id'] ?>,
                                '<?= $s['department_id'] ?>',
                                '<?= $s['program_id'] ?>',
                                '<?= $s['section_id'] ?>',
                                '<?= $s['semester_id'] ?>',
                                `<?= str_replace('`', '\`', $s['subject_name']) ?>`,
                                `<?= str_replace('`', '\`', $s['subject_code']) ?>`,
                                '<?= $s['credit_hours'] ?>',
                                `<?= str_replace('`', '\`', $s['description']) ?>`,
                                '<?= $s['status'] ?>'
                            )">
                                <i class='fa-solid fa-pen-to-square'></i>
                            </button>
                            <button class="btn-delete" onclick="openDeleteModal(<?= $s['subject_id'] ?>)" <?= $role === 'department_admin' ? 'disabled' : '' ?>>
                                <i class='fa-solid fa-trash'></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ADD MODAL - Only for faculty_admin -->
<?php if ($role === 'faculty_admin'): ?>
<div class="modal" id="addModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addModal')">&times;</span>
        <h2 style="color:var(--blue);margin-bottom:20px;">
           Add New Subject
        </h2>
        <form method="POST" id="addForm">
            <input type="hidden" name="action" value="add">

            <div>
                <label>Department *</label>
                <select id="add_department" name="department_id" onchange="filterPrograms('add')" required>
                    <option value="">Select Department</option>
                    <?php foreach($departments as $d): ?>
                        <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Program *</label>
                <select id="add_program" name="program_id" onchange="filterSections('add')" required>
                    <option value="">Select Program</option>
                </select>
            </div>

            <div>
                <label>Section *</label>
                <select id="add_section" name="section_id" required>
                    <option value="">Select Section</option>
                </select>
            </div>

            <div>
                <label>Semester *</label>
                <select name="semester_id" required>
                    <option value="">Select Semester</option>
                    <?php foreach($semesters as $s): ?>
                        <option value="<?= $s['semester_id'] ?>"><?= htmlspecialchars($s['semester_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Subject Name *</label>
                <input type="text" name="subject_name" required maxlength="255" placeholder="Enter subject name">
                <small style="color: #666;">Minimum 2 characters</small>
            </div>

            <div>
                <label>Subject Code *</label>
                <input type="text" name="subject_code" required maxlength="20" placeholder="e.g., MATH101" style="text-transform:uppercase">
                <small style="color: #666;">Uppercase letters and numbers only</small>
            </div>

            <div>
                <label>Credit Hours *</label>
                <input type="number" name="credit_hours" required min="0.5" max="10" step="0.5" placeholder="e.g., 3.0">
                <small style="color: #666;">Between 0.5 and 10 credits</small>
            </div>

            <div>
                <label>Status *</label>
                <select name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Optional subject description..." maxlength="500"></textarea>
                <small style="color: #666;">Maximum 500 characters</small>
            </div>

            <button class="save-btn" type="submit">
                <i class="fas fa-save"></i> Save Subject
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
        <h2 style="color:var(--blue);margin-bottom:20px;">
            <i class="fas fa-edit"></i> Edit Subject
        </h2>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_id" name="subject_id">

            <?php if ($role === 'faculty_admin'): ?>
            <div>
                <label>Department *</label>
                <select id="edit_department" name="department_id" onchange="filterPrograms('edit')" required>
                    <option value="">Select Department</option>
                    <?php foreach($departments as $d): ?>
                        <option value="<?= $d['department_id'] ?>"><?= htmlspecialchars($d['department_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Program *</label>
                <select id="edit_program" name="program_id" onchange="filterSections('edit')" required>
                    <option value="">Select Program</option>
                </select>
            </div>

            <div>
                <label>Section *</label>
                <select id="edit_section" name="section_id" required>
                    <option value="">Select Section</option>
                </select>
            </div>

            <div>
                <label>Semester *</label>
                <select id="edit_semester" name="semester_id" required>
                    <option value="">Select Semester</option>
                    <?php foreach($semesters as $s): ?>
                        <option value="<?= $s['semester_id'] ?>"><?= htmlspecialchars($s['semester_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="department_id" value="<?= $department_id ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Subject Name *</label>
                <input type="text" id="edit_name" name="subject_name" required maxlength="255">
            </div>

            <div>
                <label>Subject Code *</label>
                <input type="text" id="edit_code" name="subject_code" required maxlength="20" style="text-transform:uppercase">
            </div>

            <div>
                <label>Credit Hours *</label>
                <input type="number" id="edit_credit" name="credit_hours" required min="0.5" max="10" step="0.5">
            </div>

            <div>
                <label>Status *</label>
                <select id="edit_status" name="status" required>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="edit_desc" name="description" maxlength="500"></textarea>
            </div>

            <button class="save-btn" type="submit">
                <i class="fas fa-save"></i> Update Subject
            </button>
        </form>
    </div>
</div>

<!-- DELETE MODAL - Only for faculty_admin -->
<?php if ($role === 'faculty_admin'): ?>
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
        <h2 style="color:var(--red);text-align:center;margin-bottom:20px;">
            <i class="fas fa-exclamation-triangle"></i> Confirm Deletion
        </h2>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete_id" name="subject_id">
            
            <div style="text-align:center;margin-bottom:25px;">
                <i class="fas fa-trash-alt" style="font-size:48px;color:var(--red);margin-bottom:15px;"></i>
                <p style="font-size:16px;line-height:1.5;">
                    Are you sure you want to delete this subject?<br>
                    <strong style="color:var(--red);">This action cannot be undone!</strong>
                </p>
            </div>
            
            <div style="display:flex;gap:10px;grid-column:span2;">
                <button type="button" onclick="closeModal('deleteModal')" 
                        style="flex:1;padding:12px;border:1px solid #ddd;border-radius:6px;background:#f5f5f5;cursor:pointer;">
                    Cancel
                </button>
                <button class="save-btn delete" type="submit" style="flex:1;">
                    <i class="fas fa-trash"></i> Delete Subject
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ALERT POPUP -->
<div id="popup" class="alert-popup <?= $type ?>">
    <div style="display:flex;align-items:center;gap:10px;">
        <i class="fas <?= $type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>" 
           style="color:<?= $type === 'success' ? 'var(--green)' : 'var(--red)' ?>;font-size:18px;"></i>
        <div>
            <strong><?= $message ?></strong>
        </div>
    </div>
</div>

<script>
const programs = <?= json_encode($programs) ?>;
const sections = <?= json_encode($sections) ?>;

function filterPrograms(prefix) {
    const deptId = document.getElementById(prefix + '_department').value;
    const pSelect = document.getElementById(prefix + '_program');
    pSelect.innerHTML = '<option value="">Select Program</option>';
    
    programs.filter(p => p.department_id == deptId).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.program_id;
        opt.textContent = p.program_name;
        pSelect.appendChild(opt);
    });
    
    filterSections(prefix);
}

function filterSections(prefix) {
    const progId = document.getElementById(prefix + '_program').value;
    const sSelect = document.getElementById(prefix + '_section');
    sSelect.innerHTML = '<option value="">Select Section</option>';
    
    sections.filter(s => s.program_id == progId).forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.section_id;
        opt.textContent = s.section_name;
        sSelect.appendChild(opt);
    });
}

function openModal(id) {
    document.getElementById(id).classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
    document.body.style.overflow = 'auto';
}

function openEditModal(id, department, program, section, semester, name, code, credit, desc, status) {
    openModal('editModal');
    
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    document.getElementById('edit_credit').value = credit;
    document.getElementById('edit_desc').value = desc;
    document.getElementById('edit_status').value = status;
    
    <?php if ($role === 'faculty_admin'): ?>
    document.getElementById('edit_department').value = department;
    document.getElementById('edit_semester').value = semester;
    
    // Filter programs and sections
    filterPrograms('edit');
    setTimeout(() => {
        document.getElementById('edit_program').value = program;
        filterSections('edit');
        setTimeout(() => {
            document.getElementById('edit_section').value = section;
        }, 100);
    }, 100);
    <?php endif; ?>
}

function openDeleteModal(id) {
    openModal('deleteModal');
    document.getElementById('delete_id').value = id;
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Auto-uppercase subject codes
    document.querySelectorAll('input[name="subject_code"]').forEach(input => {
        input.addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    });

    // Client-side validation
    document.getElementById('addForm')?.addEventListener('submit', function(e) {
        const name = document.querySelector('#addForm input[name="subject_name"]').value.trim();
        const code = document.querySelector('#addForm input[name="subject_code"]').value.trim();
        const credit = document.querySelector('#addForm input[name="credit_hours"]').value;
        
        if (name.length < 2) {
            e.preventDefault();
            alert('Subject name must be at least 2 characters long!');
            return false;
        }
        
        if (!/^[A-Z0-9]{2,20}$/.test(code)) {
            e.preventDefault();
            alert('Subject code must be 2-20 characters containing only uppercase letters and numbers!');
            return false;
        }
        
        if (credit < 0.5 || credit > 10) {
            e.preventDefault();
            alert('Credit hours must be between 0.5 and 10!');
            return false;
        }
    });

    document.getElementById('editForm').addEventListener('submit', function(e) {
        const name = document.getElementById('edit_name').value.trim();
        const code = document.getElementById('edit_code').value.trim();
        const credit = document.getElementById('edit_credit').value;
        
        if (name.length < 2) {
            e.preventDefault();
            alert('Subject name must be at least 2 characters long!');
            return false;
        }
        
        if (!/^[A-Z0-9]{2,20}$/.test(code)) {
            e.preventDefault();
            alert('Subject code must be 2-20 characters containing only uppercase letters and numbers!');
            return false;
        }
        
        if (credit < 0.5 || credit > 10) {
            e.preventDefault();
            alert('Credit hours must be between 0.5 and 10!');
            return false;
        }
    });
});

// Close modal on outside click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
});

// Auto-hide alert popup
<?php if (!empty($message)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const popup = document.getElementById('popup');
    if (popup) {
        popup.classList.add('show');
        setTimeout(() => {
            popup.classList.remove('show');
        }, 5000);
    }
});
<?php endif; ?>
</script>

<script src="../assets/js/sidebar.js"></script>
<?php include('../includes/footer.php'); ?>
</body>
</html>
<?php ob_end_flush(); ?>