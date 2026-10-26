<!DOCTYPE html>
<html lang="so">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hormuud University - Attendance Management System</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="images.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-green: #00843D;
            --primary-dark: #00602c;
            --blue-accent: #0072CE;
            --light-green-accent: #00A651;
            --light-green-bg: #e6f7ed;
            --dark-gray: #1a1f24;
            --medium-gray: #4a5568;
            --light-gray: #F5F9F7;
            --white: #FFFFFF;
            --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            background-color: var(--light-gray);
            color: var(--medium-gray);
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Outfit', sans-serif;
            color: var(--dark-gray);
            font-weight: 700;
        }

        /* Navbar */
        nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }

        nav.scrolled {
            padding: 0.8rem 5%;
            box-shadow: var(--shadow-md);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-green);
            text-decoration: none;
            font-family: 'Outfit', sans-serif;
            letter-spacing: -0.5px;
        }

        .logo img {
            height: 45px;
            width: auto;
            object-fit: contain;
            transition: var(--transition);
        }
        
        .logo:hover img {
            transform: scale(1.05);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 2.5rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark-gray);
            font-weight: 500;
            font-size: 1rem;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a:not(.btn-login-nav)::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: var(--primary-green);
            transition: var(--transition);
        }

        .nav-links a:not(.btn-login-nav):hover::after {
            width: 100%;
        }

        .nav-links a:hover {
            color: var(--primary-green);
        }

        .btn-login-nav {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green-accent) 100%);
            color: var(--white) !important;
            padding: 0.6rem 1.8rem;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0, 132, 61, 0.2);
            transition: var(--transition);
        }

        .btn-login-nav:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 132, 61, 0.3);
        }

        /* Hero Section */
        .hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8rem 5% 5rem;
            min-height: 100vh;
            background: radial-gradient(circle at top right, #e8f5ed 0%, var(--light-gray) 60%);
            position: relative;
            overflow: hidden;
        }

        /* Abstract Background Shapes */
        .shape {
            position: absolute;
            z-index: 0;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.5;
        }
        .shape-1 {
            width: 400px;
            height: 400px;
            background: rgba(0, 132, 61, 0.15);
            top: -100px;
            right: -100px;
            animation: float 8s ease-in-out infinite;
        }
        .shape-2 {
            width: 300px;
            height: 300px;
            background: rgba(0, 114, 206, 0.1);
            bottom: -50px;
            left: -50px;
            animation: float 10s ease-in-out infinite reverse;
        }

        .hero-content {
            flex: 1.2;
            max-width: 650px;
            position: relative;
            z-index: 1;
            animation: slideUpFade 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--light-green-bg);
            color: var(--primary-green);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(0, 132, 61, 0.1);
        }

        .badge i {
            font-size: 1rem;
        }

        .hero h1 {
            font-size: 4rem;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: var(--dark-gray);
            letter-spacing: -1px;
        }

        .hero h1 span {
            color: var(--primary-green);
            display: block;
        }

        .hero p {
            font-size: 1.15rem;
            margin-bottom: 2.5rem;
            max-width: 550px;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--light-green-accent) 100%);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(0, 132, 61, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 8px 25px rgba(0, 132, 61, 0.4);
            color: var(--white);
        }

        .btn-outline {
            background: var(--white);
            color: var(--dark-gray);
            border: 1px solid #e2e8f0;
            box-shadow: var(--shadow-sm);
        }

        .btn-outline:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .hero-image {
            flex: 1;
            position: relative;
            z-index: 1;
            display: flex;
            justify-content: flex-end;
            animation: slideLeftFade 1.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        /* Abstract Dashboard Illustration */
        .dashboard-mockup {
            width: 100%;
            max-width: 550px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255,255,255,0.5);
            overflow: hidden;
            position: relative;
            transform: perspective(1000px) rotateY(-5deg) rotateX(5deg);
            transition: transform 0.5s ease;
        }

        .dashboard-mockup:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
        }

        .mockup-header {
            background: #f8fafc;
            padding: 15px 20px;
            border-bottom: 1px solid #edf2f7;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .mockup-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #cbd5e1;
        }
        .mockup-dot:nth-child(1) { background: #ef4444; }
        .mockup-dot:nth-child(2) { background: #eab308; }
        .mockup-dot:nth-child(3) { background: #22c55e; }

        .mockup-body {
            padding: 25px;
        }

        .mockup-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .mockup-stat-card {
            background: var(--light-green-bg);
            padding: 15px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .mockup-stat-card.blue { background: #eff6ff; }
        
        .mockup-line {
            height: 8px;
            background: rgba(0,0,0,0.05);
            border-radius: 4px;
        }
        .mockup-line.short { width: 40%; }
        .mockup-line.green { background: var(--primary-green); height: 24px; border-radius: 6px; width: 80%; }
        .mockup-line.blue { background: var(--blue-accent); height: 24px; border-radius: 6px; width: 60%; }

        .mockup-chart {
            height: 120px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex;
            align-items: flex-end;
            padding: 15px;
            gap: 10px;
        }

        .mockup-bar {
            flex: 1;
            background: #e2e8f0;
            border-radius: 4px 4px 0 0;
            animation: growUp 2s ease-out forwards;
            transform-origin: bottom;
        }
        .mockup-bar:nth-child(2) { background: var(--light-green-accent); height: 60%; animation-delay: 0.2s; }
        .mockup-bar:nth-child(3) { background: var(--blue-accent); height: 40%; animation-delay: 0.4s; }
        .mockup-bar:nth-child(4) { background: var(--primary-green); height: 90%; animation-delay: 0.6s; }

        /* Stats Section */
        .stats {
            padding: 4rem 5%;
            background: var(--white);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            text-align: center;
            border-bottom: 1px solid #edf2f7;
        }

        .stat-item h3 {
            font-size: 3rem;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            font-weight: 500;
            color: var(--medium-gray);
            font-size: 1.1rem;
        }

        /* Features Section */
        .features {
            padding: 8rem 5%;
            background-color: var(--white);
            position: relative;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 4rem;
        }

        .section-header h2 {
            font-size: 2.8rem;
            color: var(--dark-gray);
            margin-bottom: 1rem;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 2.5rem;
        }

        .feature-card {
            background: var(--white);
            padding: 3rem 2rem;
            border-radius: 20px;
            transition: var(--transition);
            border: 1px solid #edf2f7;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--light-green-bg) 0%, transparent 100%);
            opacity: 0;
            z-index: -1;
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
            border-color: rgba(0, 132, 61, 0.2);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon-wrapper {
            width: 70px;
            height: 70px;
            background: var(--light-green-bg);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon-wrapper {
            background: var(--primary-green);
            transform: scale(1.1) rotate(5deg);
        }

        .feature-icon {
            font-size: 2rem;
            color: var(--primary-green);
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon {
            color: var(--white);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Footer */
        footer {
            background-color: var(--dark-gray);
            color: var(--white);
            padding: 4rem 5% 2rem;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 3rem;
            text-align: center;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--white);
            font-family: 'Outfit', sans-serif;
        }
        
        .footer-logo img {
            height: 40px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
        }

        /* Animations */
        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideLeftFade {
            from { opacity: 0; transform: translateX(40px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes float {
            0% { transform: translate(0, 0); }
            50% { transform: translate(-20px, 20px); }
            100% { transform: translate(0, 0); }
        }

        @keyframes growUp {
            from { transform: scaleY(0); }
            to { transform: scaleY(1); }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .hero {
                flex-direction: column;
                text-align: center;
                padding-top: 10rem;
            }
            
            .hero-content {
                margin-bottom: 4rem;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .hero h1 { font-size: 3rem; }
            
            .dashboard-mockup { transform: none; }
            .dashboard-mockup:hover { transform: translateY(-10px); }
        }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero h1 { font-size: 2.5rem; }
            .hero-buttons { flex-direction: column; width: 100%; max-width: 300px; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

    <!-- Navigation -->
    <nav id="navbar">
        <a href="index.php" class="logo">
            <!-- Using images.png as the logo -->
            <img src="images.png" alt="HU Logo" onerror="this.style.display='none'">
            Hormuud University
        </a>
        <div class="nav-links">
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="login.php" class="btn btn-login-nav">
                <i class="fas fa-sign-in-alt"></i> Login Portal
            </a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        
        <div class="hero-content">
            <div class="badge">
                <i class="fas fa-check-circle"></i> Smart Campus Technology
            </div>
            <h1>Next-Gen <span>Attendance System</span></h1>
            <p>Streamline campus administration with our automated, secure, and real-time attendance management solution designed exclusively for Hormuud University.</p>
            <div class="hero-buttons">
                <a href="login.php" class="btn btn-primary">
                    Get Started <i class="fas fa-arrow-right"></i>
                </a>
                <a href="#features" class="btn btn-outline">
                    Explore Features
                </a>
            </div>
        </div>
        
        <div class="hero-image">
            <div class="dashboard-mockup">
                <div class="mockup-header">
                    <div class="mockup-dot"></div>
                    <div class="mockup-dot"></div>
                    <div class="mockup-dot"></div>
                </div>
                <div class="mockup-body">
                    <div class="mockup-stats">
                        <div class="mockup-stat-card">
                            <div class="mockup-line short"></div>
                            <div class="mockup-line green"></div>
                        </div>
                        <div class="mockup-stat-card blue">
                            <div class="mockup-line short"></div>
                            <div class="mockup-line blue"></div>
                        </div>
                    </div>
                    <div class="mockup-chart">
                        <div class="mockup-bar" style="height: 30%"></div>
                        <div class="mockup-bar"></div>
                        <div class="mockup-bar"></div>
                        <div class="mockup-bar"></div>
                        <div class="mockup-bar" style="height: 70%"></div>
                        <div class="mockup-bar" style="height: 50%"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Stats -->
    <section class="stats">
        <div class="stat-item">
            <h3>100%</h3>
            <p>Digital Tracking</p>
        </div>
        <div class="stat-item">
            <h3>24/7</h3>
            <p>System Uptime</p>
        </div>
        <div class="stat-item">
            <h3>Real-time</h3>
            <p>Data Synchronization</p>
        </div>
        <div class="stat-item">
            <h3>Secure</h3>
            <p>Role-based Access</p>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header">
            <h2>Why Choose Our System?</h2>
            <p>Built to handle the dynamic needs of a modern educational institution with precision and ease.</p>
        </div>
        
        <div class="feature-grid">
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-bolt feature-icon"></i>
                </div>
                <h3>Real-Time Tracking</h3>
                <p>Monitor attendance data instantly. View active classes, absent students, and late arrivals with our live dashboard updates.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-chart-pie feature-icon"></i>
                </div>
                <h3>Advanced Analytics</h3>
                <p>Generate comprehensive reports with visual charts. Export data seamlessly for administrative review and official auditing.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-shield-alt feature-icon"></i>
                </div>
                <h3>Enterprise Security</h3>
                <p>Your data is protected with industry-standard encryption and strict role-based access controls for different staff levels.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-mobile-alt feature-icon"></i>
                </div>
                <h3>Mobile Optimized</h3>
                <p>Access the system anywhere, anytime. The responsive design ensures a flawless experience on smartphones and tablets.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-user-clock feature-icon"></i>
                </div>
                <h3>Automated Alerts</h3>
                <p>System automatically flags excessive absences and notifies relevant faculty members to take appropriate action.</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon-wrapper">
                    <i class="fas fa-sync feature-icon"></i>
                </div>
                <h3>Seamless Integration</h3>
                <p>Designed to work in harmony with Hormuud University's existing technological infrastructure and databases.</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <img src="images.png" alt="HU Logo" onerror="this.style.display='none'">
                Hormuud University
            </div>
            <p>Empowering Education through Innovation</p>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> Hormuud University Attendance Management System. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Scroll Effect Script -->
    <script>
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
