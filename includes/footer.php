<!-- ==============================
   FOOTER (Hormuud University)
   ============================== -->
   <footer class="hu-footer">
  <p><strong>Hormuud University</strong> © <?= date("Y") ?> — All Rights Reserved.</p>
</footer>

<style>
/* ==============================
   HORMUUD FOOTER - Sidebar Responsive
   ============================== */

.hu-footer {
  position: fixed;
  bottom: 0;
  left: 250px; /* Default when sidebar open */
  right: 0;
  height: 40px;
  background: linear-gradient(135deg, #00843D, #0072CE); /* Hormuud Green to Blue gradient */
  color: #ffffff;
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  font-size: 14px;
  font-weight: 500;
  letter-spacing: 0.3px;
  border-top: 2px solid rgba(255, 255, 255, 0.2);
  box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.15);
  z-index: 998;
  transition: all 0.3s ease-in-out;
  animation: footerFadeIn 0.6s ease-in-out;
}

.hu-footer p {
  margin: 0;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}
.hu-footer strong {
  color: #fff;
  font-weight: 700;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

/* ✅ When sidebar collapsed */
.sidebar.collapsed ~ .hu-footer {
  left: 70px;
  width: calc(100% - 70px);
}

/* ✅ When sidebar open */
.sidebar:not(.collapsed) ~ .hu-footer {
  left: 250px;
  width: calc(100% - 250px);
}

/* ✅ Mobile view (full width) */
@media (max-width: 768px) {
  .hu-footer {
    left: 0 !important;
    width: 100% !important;
    height: auto;
    padding: 12px 5px;
    font-size: 13px;
    background: linear-gradient(135deg, #00843D, #0072CE); /* Same gradient for mobile */
  }
}

/* ✅ Animation */
@keyframes footerFadeIn {
  from { 
    opacity: 0; 
    transform: translateY(20px); 
  }
  to { 
    opacity: 1; 
    transform: translateY(0); 
  }
}

/* ✅ Hover effect for footer */
.hu-footer:hover {
  background: linear-gradient(135deg, #0072CE, #00843D); /* Reverse gradient on hover */
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.2);
}

/* ✅ Print styles - hide footer when printing */
@media print {
  .hu-footer {
    display: none;
  }
}
</style>

<script>
/* ✅ Sidebar toggle sync with footer */
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("sidebarToggle");
const toggleIcon = document.getElementById("toggleIcon");

if (toggleBtn && sidebar && toggleIcon) {
  toggleBtn.addEventListener("click", () => {
    sidebar.classList.toggle("collapsed");
    // icon-ka beddel marka sidebar la xiro / furo
    if (sidebar.classList.contains("collapsed")) {
      toggleIcon.classList.replace("fa-bars", "fa-xmark");
    } else {
      toggleIcon.classList.replace("fa-xmark", "fa-bars");
    }
  });
}

/* ✅ Footer auto-update year */
document.addEventListener('DOMContentLoaded', function() {
  const yearElement = document.querySelector('.hu-footer p');
  if (yearElement) {
    const currentYear = new Date().getFullYear();
    yearElement.innerHTML = yearElement.innerHTML.replace(/© \d{4}/, `© ${currentYear}`);
  }
});
</script>