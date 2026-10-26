<?php
/*******************************************************************************************
 * HORMUUD UNIVERSITY — CAMPUS ADMIN DASHBOARD (Final Clean Version, No Campus Fields)
 * Compatible with NEW header.php layout
 * Author: ChatGPT 2025 (Do Not Remove)
 *******************************************************************************************/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Redirect if not logged in or not campus admin
if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'campus_admin') {
  header("Location: ../login.php");
  exit;
}

// ✅ Load user info
$name = $_SESSION['user']['username'] ?? 'User';
$role = $_SESSION['user']['role'] ?? '';
$campus_id = $_SESSION['user']['linked_id'] ?? 0;

// ✅ Include header (topbar + sidebar)
require_once('../includes/header.php');

/* ============================================================
   SAFE COUNT FUNCTION (Campus Isolation)
   ============================================================ */
function getCounts(PDO $pdo, string $table, int $campus_id = null): array {
  try {
    $allowed = [
      'faculties','departments','programs',
      'class_section','teachers','students','parents','announcement'
    ];
    if (!in_array($table, $allowed)) return [0,0];

    if ($campus_id > 0) {
      $stmtA = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE status='active' AND campus_id=:cid");
      $stmtI = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE status='inactive' AND campus_id=:cid");
      $stmtA->execute(['cid'=>$campus_id]);
      $stmtI->execute(['cid'=>$campus_id]);
      return [$stmtA->fetchColumn() ?: 0, $stmtI->fetchColumn() ?: 0];
    }

    $a = $pdo->query("SELECT COUNT(*) FROM $table WHERE status='active'")->fetchColumn();
    $i = $pdo->query("SELECT COUNT(*) FROM $table WHERE status='inactive'")->fetchColumn();
    return [$a ?: 0, $i ?: 0];
  } catch (Exception $e) {
    return [0,0];
  }
}

/* ============================================================
   FETCH STATS (Campus Filtered)
   ============================================================ */
[$aFac,$iFac]   = getCounts($pdo,'faculties',$campus_id);
[$aDep,$iDep]   = getCounts($pdo,'departments',$campus_id);
[$aProg,$iProg] = getCounts($pdo,'programs',$campus_id);
[$aSec,$iSec]   = getCounts($pdo,'class_section',$campus_id);
[$aTea,$iTea]   = getCounts($pdo,'teachers',$campus_id);
[$aStu,$iStu]   = getCounts($pdo,'students',$campus_id);
[$aPar,$iPar]   = getCounts($pdo,'parents',$campus_id);
[$aAnn,$iAnn]   = getCounts($pdo,'announcement',$campus_id);

/* ============================================================
   CAMPUS NAME (Display only)
   ============================================================ */
$campus_name = 'Unknown';
try {
  $stmt = $pdo->prepare("SELECT campus_name FROM campus WHERE campus_id = ?");
  $stmt->execute([$campus_id]);
  $campus_name = $stmt->fetchColumn() ?: 'N/A';
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Hormuud University</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== LAYOUT ===== */
.dashboard-wrapper {
  margin-left: 250px;
  padding: 100px 30px 60px;
  transition: .3s;
}
.sidebar.collapsed ~ .dashboard-wrapper { margin-left: 70px; }

/* ===== BANNER ===== */
.welcome-banner {
  background: linear-gradient(135deg, #0072CE, #009639);
  color: white;
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
}
.card {
  background: white;
  border-radius: 12px;
  padding: 25px;
  text-align: center;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  transition: .3s;
}
.card:hover { transform: translateY(-6px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
.counter { font-size: 34px; font-weight: bold; color: #0072CE; margin-bottom: 5px; }
.card p { font-weight: 600; margin-bottom: 4px; }
.card small { color: gray; }

/* ===== CHARTS ===== */
.charts-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 25px;
  margin-top: 45px;
}
.chart-box {
  background: white;
  border-radius: 15px;
  padding: 25px 15px 30px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.1);
  text-align: center;
  border-top: 6px solid #009639;
  opacity: 0;
  transform: translateY(40px);
  transition: all .8s ease;
}
.chart-box.visible { opacity: 1; transform: translateY(0); }
.chart-box h3 { color: #0072CE; font-size: 17px; font-weight: 700; margin-bottom: 14px; }
canvas { width: 100%!important; height: 320px!important; }

@media(max-width:768px) {
  .dashboard-wrapper { margin-left: 0; padding: 90px 15px; }
}
</style>
</head>
<body>

<div class="dashboard-wrapper">
  <div class="welcome-banner">
    <h1>Welcome, <?= htmlspecialchars(ucfirst($name)) ?> 👋</h1>
    <p>
      Role: <strong><?= ucfirst(str_replace('_', ' ', $role)) ?></strong> |
      Campus: <strong><?= htmlspecialchars($campus_name) ?></strong> |
      Date: <?= date("d M, Y") ?>
    </p>
  </div>

  <!-- ✅ STATS (No Campus Cards) -->
  <div class="stats-grid">
    <div class="card"><div class="counter" data-target="<?= $aFac ?>"></div><p>Active Faculties</p><small>Inactive: <?= $iFac ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aDep ?>"></div><p>Active Departments</p><small>Inactive: <?= $iDep ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aProg ?>"></div><p>Active Programs</p><small>Inactive: <?= $iProg ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aSec ?>"></div><p>Active Sections</p><small>Inactive: <?= $iSec ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aStu ?>"></div><p>Active Students</p><small>Inactive: <?= $iStu ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aAnn ?>"></div><p>Active Announcements</p><small>Inactive: <?= $iAnn ?></small></div>
  </div>

  <!-- ✅ CHARTS -->
  <div class="charts-container">
    <div class="chart-box"><h3>Active vs Inactive Overview</h3><canvas id="barChart"></canvas></div>
    <div class="chart-box"><h3>Active Distribution</h3><canvas id="pieChart"></canvas></div>
    <div class="chart-box"><h3>Growth Trend</h3><canvas id="lineChart"></canvas></div>
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

/* CHART DATA (No Campus) */
const labels=['Faculties','Departments','Programs','Sections','Teachers','Students','Parents','Announcement'];
const activeData=[<?= "$aFac,$aDep,$aProg,$aSec,$aTea,$aStu,$aPar,$aAnn" ?>];
const inactiveData=[<?= "$iFac,$iDep,$iProg,$iSec,$iTea,$iStu,$iPar,$iAnn" ?>];
const colors=['#0072CE','#009639','#FFB400','#1E90FF','#F39C12','#C62828','#00C853','#FF7043'];

/* BAR CHART */
new Chart(document.getElementById('barChart'),{
  type:'bar',
  data:{labels,datasets:[
    {label:'Active',data:activeData,backgroundColor:'#0072CE'},
    {label:'Inactive',data:inactiveData,backgroundColor:'#C62828'}
  ]},
  options:{responsive:true,scales:{y:{beginAtZero:true}}}
});

/* PIE CHART */
new Chart(document.getElementById('pieChart'),{
  type:'pie',
  data:{labels,datasets:[{data:activeData,backgroundColor:colors}]},
  options:{responsive:true,plugins:{legend:{position:'bottom'}}}
});

/* LINE CHART */
new Chart(document.getElementById('lineChart'),{
  type:'line',
  data:{labels,datasets:[
    {label:'Active',data:activeData,borderColor:'#0072CE',backgroundColor:'rgba(0,114,206,0.25)',fill:true,tension:0.4},
    {label:'Inactive',data:inactiveData,borderColor:'#C62828',backgroundColor:'rgba(198,40,40,0.15)',fill:true,tension:0.4}
  ]},
  options:{responsive:true,plugins:{legend:{position:'bottom'}}}
});

/* FADE-IN EFFECT */
const boxes=document.querySelectorAll('.chart-box');
const observer=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}});
},{threshold:0.2});
boxes.forEach(b=>observer.observe(b));
</script>
</body>
</html>
