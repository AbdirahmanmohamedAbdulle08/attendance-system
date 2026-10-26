# Attendance Management System

An Attendance Management System built with **PHP**, **MySQL**, and **XAMPP**. This system allows administrators to manage employees/students, track attendance, handle leave requests, and generate reports.

---

## 📋 Features

- ✅ User management (Admin / User roles)
- ✅ Employee/Student management (Add / Edit / Delete)
- ✅ Daily attendance tracking (Check-in / Check-out)
- ✅ Leave management
- ✅ Attendance reports (Daily / Monthly)
- ✅ Dashboard with statistics
- ✅ Secure login (password hashing)
- ✅ Responsive design (Bootstrap)

---

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8.x |
| Database | MySQL / MariaDB |
| Server | Apache (XAMPP) |
| Frontend | HTML5, CSS3, Bootstrap 5, JavaScript |
| Version Control | Git & GitHub |

⚙️ Requirements
Before installing, make sure you have:
- [XAMPP](https://www.apachefriends.org/) (or WAMP/MAMP)
- PHP 8.0 or higher
- MySQL 5.7 or higher
- A modern browser (Chrome, Firefox, Edge)
- Git (optional, for cloning)
🚀Installation

1. Clone the repository

git clone https://github.com/AbdirahmanmohamedAbdulle08/attendance-system.git

Or download ZIP: GitHub → Code → Download ZIP

2. Copy the folder to XAMPP
Move the project folder to:

text
C:\xampp\htdocs\attendance_management_system
3. Start XAMPP
Open XAMPP Control Panel and start:

✅ Apache

✅ MySQL

4. Create the Database
Open your browser and go to: http://localhost/phpmyadmin

Create a new database named: attendance_db

Select the database → Import → upload the .sql file inside the database/ folder (e.g. attendance_db.sql)

Click Go

5. Configure the database connection
Open config/database.php (or includes/db.php) and verify:

php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";
6. Open the project
In your browser, go to:

📁 Folder Structure
text
attendance_management_system/
│
├── assets/              # CSS, JS, images
├── config/              # Database connection
├── database/            # SQL file
├── includes/            # Header, footer, functions
├── admin/               # Admin pages
├── user/                # User pages
├── index.php            # Login page
├── logout.php
└── README.md
📸 Screenshots
<img width="959" height="367" alt="image" src="https://github.com/user-attachments/assets/7edd967b-e175-4a16-88ed-c912b37da28b" />
<img width="958" height="377" alt="image" src="https://github.com/user-attachments/assets/7920f1ca-88ba-4de0-868d-c29719af1add" />
<img width="620" height="428" alt="image" src="https://github.com/user-attachments/assets/eb73838b-baa4-41bb-b4d8-f9278eda223c" />
<img width="959" height="339" alt="image" src="https://github.com/user-attachments/assets/cda9476c-a336-4197-88ce-060ff6b0f6c8" />
<img width="910" height="326" alt="image" src="https://github.com/user-attachments/assets/46b85353-de11-4553-a27b-5a62263520ba" />

🧪 Usage
Admin — login → Dashboard → Manage employees → View reports

User — login → Check-in / Check-out → View your attendance

Reports — Admin can generate daily or monthly reports

🤝 Contributing
Fork the repository

Create a new branch: git checkout -b feature/feature-name

Commit: git commit -m "Add feature"

Push: git push origin feature/feature-name

Open a Pull Request

📝 License
This project is licensed under the MIT License — you are free to use, modify, and distribute it.

👤 Author
Abdirahman Mohamed Abdulle

GitHub: @AbdirahmanmohamedAbdulle08
email:AbdirahmanmohamedAbdulle08@gmail.com
Repo: attendance-system

📞 Support
If you have any questions or issues, open a new Issue on GitHub:

👉 https://github.com/AbdirahmanmohamedAbdulle08/attendance-system/issues
