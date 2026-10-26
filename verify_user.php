<?php
require_once __DIR__ . '/config/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_no   = trim($_POST['reg_no'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($reg_no === '' || $password === '') {
        echo "<div class='error-message'>
                <div class='error-icon'>⚠️</div>
                <div class='error-text'>Please fill all fields.</div>
              </div>";
        exit;
    }

    // ✅ Fetch student + user info
    $stmt = $pdo->prepare("
        SELECT s.*, u.password AS user_password,
               f.faculty_name, d.department_name, p.program_name,
               cs.section_name, sem.semester_name, c.campus_name
        FROM students s
        JOIN users u ON u.linked_id = s.student_id
        LEFT JOIN faculties f ON f.faculty_id = s.faculty_id
        LEFT JOIN departments d ON d.department_id = s.department_id
        LEFT JOIN programs p ON p.program_id = s.program_id
        LEFT JOIN class_section cs ON cs.section_id = s.section_id
        LEFT JOIN semester sem ON sem.semester_id = s.semester_id
        LEFT JOIN campus c ON c.campus_id = s.campus_id
        WHERE s.reg_no = ? AND u.linked_table = 'student'
        LIMIT 1
    ");
    $stmt->execute([$reg_no]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "<div class='error-message'>
                <div class='error-icon'>❌</div>
                <div class='error-text'>No student found with this registration number!</div>
              </div>";
        exit;
    }

    if (!password_verify($password, $student['user_password'])) {
        echo "<div class='error-message'>
                <div class='error-icon'>❌</div>
                <div class='error-text'>Incorrect password!</div>
              </div>";
        exit;
    }

    $_SESSION['student_verified'] = $student['reg_no'];

    // ✅ Fetch parent/guardian info
    $parent_stmt = $pdo->prepare("
        SELECT ps.relation_type, p.full_name AS parent_name, p.phone AS parent_phone, p.email AS parent_email
        FROM parent_student ps
        LEFT JOIN parents p ON p.parent_id = ps.parent_id
        WHERE ps.student_id = ?
        LIMIT 1
    ");
    $parent_stmt->execute([$student['student_id']]);
    $parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);

    // ✅ Fetch timetable for today
    $today = date('D'); // Mon, Tue, Wed...
    $timetable_stmt = $pdo->prepare("
        SELECT tt.*, sub.subject_name, r.room_name, t.teacher_name AS teacher_name
        FROM timetable tt
        LEFT JOIN subject sub ON sub.subject_id = tt.subject_id
        LEFT JOIN rooms r ON r.room_id = tt.room_id
        LEFT JOIN teachers t ON t.teacher_id = tt.teacher_id
        WHERE tt.section_id = ? AND tt.day_of_week = ? AND tt.status = 'active'
        ORDER BY tt.start_time ASC
    ");
    $timetable_stmt->execute([$student['section_id'], $today]);
    $classes_today = $timetable_stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Display info (read status only)
    $photo   = htmlspecialchars($student['photo_path'] ?: 'assets/img/default_avatar.png', ENT_QUOTES, 'UTF-8');
    $name    = htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8');
    $email   = htmlspecialchars($student['email'] ?: 'N/A', ENT_QUOTES, 'UTF-8');
    $isActive = strtolower($student['status']) === 'active';
    $status  = $isActive ? '🟢 Active' : '🔴 Inactive / Dropped';
    $statusColor = $isActive ? '#00843D' : '#C62828';
    $todayInterest = count($classes_today) > 0 ? "Yes, has class today ✅" : "No class today ❌";

    $parentName  = $parent['parent_name']  ?? 'N/A';
    $parentPhone = $parent['parent_phone'] ?? 'N/A';
    $parentEmail = $parent['parent_email'] ?? 'N/A';
    $relation    = ucfirst($parent['relation_type'] ?? 'Guardian');

    echo "
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Student Verification | Hormuud University</title>
        <link rel='icon' type='image/png' href='images.png'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css'>
    </head>
    <body>
    <div class='verify-container'>
        <div class='verify-card'>
            <div class='card-header'>
                <h1><i class='fas fa-user-check'></i> Student Verification</h1>
                <div class='status-badge' style='background-color: {$statusColor};'>
                    <i class='fas " . ($isActive ? 'fa-check-circle' : 'fa-times-circle') . "'></i> {$status}
                </div>
            </div>
            
            <div class='profile-section'>
                <div class='profile-image-container'>
                    <img src='{$photo}' alt='Profile' class='profile-image'>
                    <div class='profile-status-indicator' style='background-color: {$statusColor};'>
                        <i class='fas " . ($isActive ? 'fa-check' : 'fa-times') . "'></i>
                    </div>
                </div>
                <div class='profile-info'>
                    <h2 class='student-name'><i class='fas fa-user-graduate'></i> {$name}</h2>
                    <p class='student-email'><i class='fas fa-envelope'></i> {$email}</p>
                    <p class='student-reg'><i class='fas fa-id-card'></i> {$student['reg_no']}</p>
                </div>
            </div>

            <div class='info-grid'>
                <div class='info-section'>
                    <h3 class='section-title'>
                        <i class='fas fa-graduation-cap'></i>
                        Academic Information
                    </h3>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-university'></i> Campus:</span>
                        <span class='info-value'>{$student['campus_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-school'></i> Faculty:</span>
                        <span class='info-value'>{$student['faculty_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-building'></i> Department:</span>
                        <span class='info-value'>{$student['department_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-book'></i> Program:</span>
                        <span class='info-value'>{$student['program_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-users'></i> Section:</span>
                        <span class='info-value'>{$student['section_name']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-calendar-alt'></i> Semester:</span>
                        <span class='info-value'>{$student['semester_name']}</span>
                    </div>
                </div>

                <div class='info-section'>
                    <h3 class='section-title'>
                        <i class='fas fa-user-circle'></i>
                        Personal Details
                    </h3>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-home'></i> Address:</span>
                        <span class='info-value'>{$student['address']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-phone'></i> Phone:</span>
                        <span class='info-value'>{$student['phone_number']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-venus-mars'></i> Gender:</span>
                        <span class='info-value'>{$student['gender']}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-birthday-cake'></i> Date of Birth:</span>
                        <span class='info-value'>{$student['dob']}</span>
                    </div>
                </div>

                <div class='info-section'>
                    <h3 class='section-title'>
                        <i class='fas fa-users'></i>
                        Guardian Information
                    </h3>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-user-tie'></i> Name:</span>
                        <span class='info-value'>{$parentName}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-handshake'></i> Relation:</span>
                        <span class='info-value'>{$relation}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-mobile-alt'></i> Phone:</span>
                        <span class='info-value'>{$parentPhone}</span>
                    </div>
                    <div class='info-item'>
                        <span class='info-label'><i class='fas fa-at'></i> Email:</span>
                        <span class='info-value'>{$parentEmail}</span>
                    </div>
                </div>
            </div>

            <div class='timetable-section'>
                <h3 class='section-title'>
                    <i class='fas fa-calendar-day'></i>
                    Today's Timetable (" . htmlspecialchars($today) . ")
                </h3>
                
                <div class='class-status-indicator'>
                    <span class='indicator-label'><i class='fas fa-clock'></i> Classes Today:</span>
                    <span class='indicator-value'>" . 
                    (count($classes_today) > 0 ? 
                    "<i class='fas fa-check-circle' style='color: #00843D;'></i> Yes, has class today" : 
                    "<i class='fas fa-times-circle' style='color: #C62828;'></i> No class today") . 
                    "</span>
                </div>";

    if (count($classes_today) > 0) {
        echo "<div class='table-container'>
                <table class='timetable-table'>
                    <thead>
                        <tr>
                            <th> Subject</th>
                            <th> Start Time</th>
                            <th> End Time</th>
                            <th> Room</th>
                            <th>Teacher</th>
                        </tr>
                    </thead>
                    <tbody>";
        foreach ($classes_today as $class) {
            echo "<tr>
                    <td>" . htmlspecialchars($class['subject_name'] ?? 'N/A') . "</td>
                    <td> " . htmlspecialchars(substr($class['start_time'], 0, 5)) . "</td>
                    <td>" . htmlspecialchars(substr($class['end_time'], 0, 5)) . "</td>
                    <td>" . htmlspecialchars($class['room_name'] ?? 'N/A') . "</td>
                    <td>" . htmlspecialchars($class['teacher_name'] ?? 'N/A') . "</td>
                </tr>";
        }
        echo "</tbody>
                </table>
              </div>";
    } else {
        echo "<div class='no-classes-message'>
                <div class='no-classes-icon'>
                    <i class='fas fa-calendar-times'></i>
                </div>
                <p>No classes scheduled for today. Enjoy your free time!</p>
              </div>";
    }

    echo "
            </div>

            <div class='action-buttons'>
                <button class='btn-primary' onclick='redirectLogin()'>
                    <i class='fas fa-sign-out-alt'></i> Return to Login
             
            </div>
        </div>
    </div>

    // <script>
    // function redirectLogin() {
    //     window.location.href = 'login.php';
    // }
    </script>

    <style>
    :root {
        --primary-green: #00843D;
        --blue-accent: #0072CE;
        --light-green: #00A651;
        --dark-gray: #333333;
        --light-gray-bg: #F5F9F7;
        --danger-red: #C62828;
        --warning-amber: #FFB400;
        --surface-white: #FFFFFF;
        --border-color: #E0E0E0;
        --shadow-light: rgba(0, 0, 0, 0.08);
        --shadow-medium: rgba(0, 0, 0, 0.12);
    }
    
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Poppins', 'Segoe UI', sans-serif;
        background: var(--light-gray-bg);
        color: var(--dark-gray);
        line-height: 1.6;
    }
    
    .verify-container {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        padding: 20px;
        min-height: 100vh;
        animation: fadeIn 0.5s ease-in-out;
    }
    
    .verify-card {
        background: var(--surface-white);
        border-radius: 16px;
        box-shadow: 0 8px 30px var(--shadow-medium);
        width: 100%;
        max-width: 1000px;
        overflow: hidden;
        color: var(--dark-gray);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 30px;
        background: linear-gradient(135deg, var(--primary-green), var(--light-green));
        color: white;
    }
    
    .card-header h1 {
        font-size: 24px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .status-badge {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        color: white;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .profile-section {
        display: flex;
        align-items: center;
        padding: 30px;
        border-bottom: 1px solid var(--border-color);
        background: var(--light-gray-bg);
    }
    
    .profile-image-container {
        position: relative;
        margin-right: 20px;
    }
    
    .profile-image {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid var(--light-green);
        object-fit: cover;
        box-shadow: 0 6px 15px var(--shadow-light);
    }
    
    .profile-status-indicator {
        position: absolute;
        bottom: 8px;
        right: 8px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 3px solid white;
        background: var(--primary-green);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 10px;
    }
    
    .profile-info {
        flex: 1;
    }
    
    .student-name {
        font-size: 28px;
        font-weight: 700;
        color: var(--primary-green);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .student-email, .student-reg {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 5px;
        font-size: 16px;
    }
    
    .student-email {
        color: var(--blue-accent);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        padding: 30px;
    }
    
    .info-section {
        background: var(--light-gray-bg);
        border-radius: 12px;
        padding: 20px;
        border-left: 4px solid var(--primary-green);
    }
    
    .section-title {
        display: flex;
        align-items: center;
        font-size: 18px;
        font-weight: 600;
        color: var(--primary-green);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
        gap: 10px;
    }
    
    .info-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px dashed var(--border-color);
    }
    
    .info-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    
    .info-label {
        font-weight: 600;
        color: var(--dark-gray);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-value {
        color: var(--dark-gray);
        text-align: right;
        max-width: 60%;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .timetable-section {
        padding: 0 30px 30px;
    }
    
    .class-status-indicator {
        display: flex;
        justify-content: space-between;
        background: var(--light-gray-bg);
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid var(--blue-accent);
    }
    
    .indicator-label, .indicator-value {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
    }
    
    .table-container {
        overflow-x: auto;
        border-radius: 10px;
        box-shadow: 0 4px 12px var(--shadow-light);
    }
    
    .timetable-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--surface-white);
    }
    
    .timetable-table th {
        background: var(--primary-green);
        color: white;
        padding: 14px 12px;
        text-align: left;
        font-weight: 600;
    }
    
    .timetable-table th i {
        margin-right: 8px;
    }
    
    .timetable-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .timetable-table td i {
        margin-right: 8px;
        color: var(--blue-accent);
    }
    
    .timetable-table tr:last-child td {
        border-bottom: none;
    }
    
    .timetable-table tr:hover {
        background: var(--light-gray-bg);
    }
    
    .no-classes-message {
        text-align: center;
        padding: 40px;
        background: var(--light-gray-bg);
        border-radius: 10px;
        border: 2px dashed var(--border-color);
    }
    
    .no-classes-icon {
        font-size: 48px;
        margin-bottom: 15px;
        color: var(--blue-accent);
    }
    
    .action-buttons {
        padding: 0 30px 30px;
        text-align: center;
        display: flex;
        gap: 15px;
        justify-content: center;
    }
    
    .btn-primary, .btn-secondary {
        border: none;
        padding: 12px 24px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 8px var(--shadow-light);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: var(--blue-accent);
        color: white;
    }
    
    .btn-secondary {
        background: var(--primary-green);
        color: white;
    }
    
    .btn-primary:hover {
        background: #005bb5;
        transform: translateY(-2px);
        box-shadow: 0 6px 12px var(--shadow-medium);
    }
    
    .btn-secondary:hover {
        background: var(--light-green);
        transform: translateY(-2px);
        box-shadow: 0 6px 12px var(--shadow-medium);
    }
    
    .error-message {
        display: flex;
        align-items: center;
        background: #FFEBEE;
        color: var(--danger-red);
        padding: 12px 16px;
        border-radius: 8px;
        margin: 10px 0;
        border-left: 4px solid var(--danger-red);
    }
    
    .error-icon {
        margin-right: 10px;
        font-size: 18px;
    }
    
    .error-text {
        font-weight: 600;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Print Styles */
    @media print {
        .action-buttons {
            display: none;
        }
        
        .verify-card {
            box-shadow: none;
            margin: 0;
        }
        
        body {
            background: white;
        }
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .verify-container {
            padding: 10px;
        }
        
        .card-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
        
        .profile-section {
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        
        .profile-image-container {
            margin-right: 0;
            margin-bottom: 15px;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
            padding: 20px;
        }
        
        .timetable-section {
            padding: 0 20px 20px;
        }
        
        .action-buttons {
            padding: 0 20px 20px;
            flex-direction: column;
        }
        
        .info-item {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .info-value {
            text-align: left;
            max-width: 100%;
            margin-top: 5px;
        }
        
        .student-name, .student-email, .student-reg {
            justify-content: center;
        }
    }
    </style>
    </body>
    </html>";
    exit;
}
?>