# EmpDB Manager

A full-stack web application built with **PHP** and **Microsoft SQL Server** to manage employee records, departments, projects, and salary grades — with a complete authentication system and role-based access control.

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (procedural + OOP) |
| Database | Microsoft SQL Server (via `sqlsrv` driver) |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Server | Apache via XAMPP |
| Version Control | Git + GitHub |


---

## ✅ Features

### 🔐 Authentication System
- **Role selector landing page** — users pick Admin or Regular User before logging in, each routed to a contextually labelled login page
- **Secure login** using `password_hash()` and `password_verify()` — passwords are never stored in plain text
- **Remember Me** — generates a cryptographically secure 64-character token stored in the database and a 30-day `httponly` cookie; token rotates on every use to prevent replay attacks
- **Session security** — `session_regenerate_id()` on every login and every 5 minutes to prevent session fixation attacks; sessions die when the browser closes unless Remember Me is checked
- **Autofill protection** on signup — `autocomplete="new-password"` and hidden dummy fields prevent the browser from leaking other users' saved credentials
- **Auth guard (`auth.php`)** — every protected page includes this file; unauthenticated users are immediately redirected to the landing page

### 👤 User Dashboard
- **Profile tab** — update full name, city, and university
- **Password tab** — change password with current password verification; enforces minimum 8 characters, 1 uppercase, 1 number
- **Home tab** — shows logged-in user info cards

### 🛡️ Admin Dashboard (Admin only)
- **Stats tab** — live counts of total employees, departments, projects, registered users, and average salary — all queried directly from SQL Server
- **Users tab** — full list of all registered users with:
  - **▲ Promote to Admin** button
  - **▼ Demote to User** button
  - **Delete user** button with confirmation dialog
  - Self-protection: admins cannot promote, demote, or delete their own account

### 🗄️ Database Manager
- **View all tables** — EMP, DEPT, SALGRADE, Project, ProjAssign
- **Full join view** — one unified table joining all 5 tables via LEFT JOINs
- **Search employee by ID** — returns all linked data across tables
- **Insert employee** — form-based with full validation
- **Insert department** — add new department records
- **Update employee** — edit existing employee fields
- **Delete employee** — with safety check (employees assigned to projects cannot be deleted)

### 🎨 UI & Design
- Dark theme throughout with a consistent `#0f1117` base
- Server room background image on landing and login pages with a dark overlay and `backdrop-filter: blur()` for a polished look
- Cards with hover lift animations and glowing top-border effects on the role selector
- Fully responsive layout

---

## ⚙️ Setup & Installation

### Prerequisites
- XAMPP (Apache + PHP 8.2)
- Microsoft SQL Server + SSMS
- PHP `sqlsrv` and `pdo_sqlsrv` extensions enabled in `php.ini`

### Steps

1. **Clone the repo**
   ```bash
   git clone https://github.com/YOUR_USERNAME/EmpDB-Manager.git
   cd EmpDB-Manager
   ```

2. **Set up the database** — Open SSMS and run `demobld.sql` to create and populate all tables

3. **Create the Users table**
   ```sql
   USE EmpDeptDB;
   CREATE TABLE Users (
       UserID       INT IDENTITY(1,1) PRIMARY KEY,
       FullName     VARCHAR(100) NOT NULL,
       Email        VARCHAR(100) NOT NULL UNIQUE,
       Password     VARCHAR(255) NOT NULL,
       City         VARCHAR(50)  NOT NULL,
       University   VARCHAR(100) NOT NULL,
       Role         VARCHAR(10)  NOT NULL DEFAULT 'user',
       RememberToken VARCHAR(64) NULL,
       CreatedAt    DATETIME     DEFAULT GETDATE()
   );
   ```

4. **Configure connection** — open `conn.php` and set your SQL Server instance name

5. **Create your admin account** — use `hash.php` to generate a bcrypt hash, insert via SSMS

6. **Start Apache** in XAMPP and visit:

http://localhost/EmpDB-Manager/


---

## 💬 Feedback & Suggestions

This project is actively being developed and improved. If you have any suggestions, ideas, or spot any issues, I'd genuinely love to hear from you — feel free to reach out:

📧 **[shahryar.professional@gmail.com](mailto:shahryar.professional@gmail.com)**

All feedback is welcome and appreciated!

---

*Built by Shahryar Ahmad*