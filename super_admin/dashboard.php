<?php
/*******************************************************************************************
 * HORMUUD UNIVERSITY — ADMIN DASHBOARD (Extended Version)
 * Compatible with the NEW header.php layout
 * Author: ChatGPT 2025 (No Remove)
 *******************************************************************************************/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../config/db_connect.php');

// ✅ Redirect if not logged in
if (empty($_SESSION['user'])) {
  header("Location: ../login.php");
  exit;
}

// ✅ Include header (which loads topbar + sidebar)
require_once('../includes/header.php');

// ✅ Count function
function getCounts($pdo, $table) {
  try {
    $active = $pdo->query("SELECT COUNT(*) FROM $table WHERE status='active'")->fetchColumn();
    $inactive = $pdo->query("SELECT COUNT(*) FROM $table WHERE status='inactive'")->fetchColumn();
    return [$active ?: 0, $inactive ?: 0];
  } catch (Exception $e) {
    return [0, 0];
  }
}

// ✅ Stats
[$aCampus,$iCampus] = getCounts($pdo,'campus');
[$aFac,$iFac] = getCounts($pdo,'faculties');
[$aDep,$iDep] = getCounts($pdo,'departments');
[$aProg,$iProg] = getCounts($pdo,'programs');
[$aSec,$iSec] = getCounts($pdo,'class_section');
[$aTea,$iTea] = getCounts($pdo,'teachers');
[$aStu,$iStu] = getCounts($pdo,'students');
[$aPar,$iPar] = getCounts($pdo,'parents');
[$aAnn,$iAnn] = getCounts($pdo,'announcement');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | Hormuud University</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
.dashboard-wrapper {
  margin-left: 250px;
  padding: 100px 30px 60px;
  transition: .3s;
}
.sidebar.collapsed ~ .dashboard-wrapper {
  margin-left: 70px;
}
.welcome-banner {
  background: linear-gradient(135deg, #0072CE, #009639);
  color: white;
  text-align: center;
  border-radius: 12px;
  padding: 35px 20px;
  margin-bottom: 40px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
.welcome-banner h1 {
  font-size: 28px;
  font-weight: 700;
}
.welcome-banner p {
  font-size: 15px;
  opacity: .9;
}

/* CARDS */
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
.card:hover { transform: translateY(-6px); }
.counter { font-size: 34px; font-weight: bold; color: #0072CE; }
.card p { font-weight: 600; }
.card small { color: gray; }

/* CHARTS */
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
.chart-box h3 {
  color: #0072CE;
  font-size: 17px;
  font-weight: 700;
  margin-bottom: 14px;
}
canvas { width: 100%!important; height: 320px!important; }

@media(max-width:768px) {
  .dashboard-wrapper { margin-left: 0; padding: 90px 15px; }
}
</style>
</head>
<body>

<!-- ✅ DASHBOARD CONTENT -->
<div class="dashboard-wrapper">
  <div class="welcome-banner">
    <h1>Welcome, <?= ucfirst($name) ?> 👋</h1>
    <p>Role: <strong><?= ucfirst(str_replace('_', ' ', $role)) ?></strong> | Date: <?= date("d M, Y") ?></p>
  </div>

  <div class="stats-grid">
    <div class="card"><div class="counter" data-target="<?= $aCampus ?>"></div><p>Active Campuses</p><small>Inactive: <?= $iCampus ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aFac ?>"></div><p>Active Faculties</p><small>Inactive: <?= $iFac ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aDep ?>"></div><p>Active Departments</p><small>Inactive: <?= $iDep ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aProg ?>"></div><p>Active Programs</p><small>Inactive: <?= $iProg ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aSec ?>"></div><p>Active Sections</p><small>Inactive: <?= $iSec ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aTea ?>"></div><p>Active Teachers</p><small>Inactive: <?= $iTea ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aStu ?>"></div><p>Active Students</p><small>Inactive: <?= $iStu ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aPar ?>"></div><p>Active Parents</p><small>Inactive: <?= $iPar ?></small></div>
    <div class="card"><div class="counter" data-target="<?= $aAnn ?>"></div><p>Active Announcements</p><small>Inactive: <?= $iAnn ?></small></div>
  </div>

  <!-- CHARTS -->
  <div class="charts-container">
    <div class="chart-box"><h3>Active vs Inactive Overview</h3><canvas id="barChart"></canvas></div>
    <div class="chart-box"><h3>Active Distribution</h3><canvas id="pieChart"></canvas></div>
    <div class="chart-box"><h3>Growth Trend</h3><canvas id="lineChart"></canvas></div>
  </div>
</div>

<!-- ✅ FOOTER -->
<?php include('../includes/footer.php'); ?>

<script>
/* COUNTER ANIMATION */
document.querySelectorAll('.counter').forEach(counter=>{
  counter.innerText='0';
  const update=()=>{
    const target=+counter.dataset.target;
    const c=+counter.innerText;
    const inc=target/50;
    if(c<target){counter.innerText=Math.ceil(c+inc);setTimeout(update,20);}else counter.innerText=target;
  };
  update();
});

/* CHARTS */
const labels=['Campus','Faculties','Departments','Programs','Sections','Teachers','Students','Parents','Announcements'];
const activeData=[<?= "$aCampus,$aFac,$aDep,$aProg,$aSec,$aTea,$aStu,$aPar,$aAnn" ?>];
const inactiveData=[<?= "$iCampus,$iFac,$iDep,$iProg,$iSec,$iTea,$iStu,$iPar,$iAnn" ?>];
const colors=['#0072CE','#009639','#FFB400','#1E90FF','#F39C12','#C62828','#00C853','#0057A0','#FF7043'];

new Chart(document.getElementById('barChart'),{
  type:'bar',
  data:{labels,datasets:[
    {label:'Active',data:activeData,backgroundColor:'#0072CE'},
    {label:'Inactive',data:inactiveData,backgroundColor:'#C62828'}
  ]},
  options:{responsive:true,scales:{y:{beginAtZero:true}}}
});
new Chart(document.getElementById('pieChart'),{
  type:'pie',
  data:{labels,datasets:[{data:activeData,backgroundColor:colors}]}
});
new Chart(document.getElementById('lineChart'),{
  type:'line',
  data:{labels,datasets:[
    {label:'Active',data:activeData,borderColor:'#0072CE',backgroundColor:'rgba(0,114,206,0.2)',fill:true,tension:0.4},
    {label:'Inactive',data:inactiveData,borderColor:'#C62828',backgroundColor:'rgba(198,40,40,0.15)',fill:true,tension:0.4}
  ]}
});

/* FADE-IN CHARTS */
const boxes=document.querySelectorAll('.chart-box');
const obs=new IntersectionObserver(entries=>{
  entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');obs.unobserve(e.target);}});
},{threshold:0.2});
boxes.forEach(b=>obs.observe(b));
</script>
</body>
</html>
