# EmpDB Manager

A full-stack web application built with **PHP** and **Microsoft SQL Server** to manage employee records, departments, projects, salary grades, and registered users — with a complete authentication system, role-based access control, activity tracking, and admin user suspension controls. [code_file:0]

---

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (procedural + OOP) [code_file:0] |
| Database | Microsoft SQL Server (via `sqlsrv` driver) [code_file:0] |
| Frontend | HTML5, CSS3, Vanilla JavaScript [code_file:0] |
| Charts | Chart.js 4.4 [code_file:0] |
| Server | Apache via XAMPP [code_file:0] |
| Version Control | Git + GitHub [code_file:0] |

---

## ✅ Features

### 🔐 Authentication System
- **Role selector landing page** — users pick Admin or Regular User before logging in, each routed to a contextually labelled login page. [code_file:0]
- **Secure login** using `password_hash()` and `password_verify()` — passwords are never stored in plain text. [code_file:0]
- **Remember Me** — generates a cryptographically secure token stored in the database and a 30-day `httponly` cookie. [code_file:0]
- **Blocked-user protection** — suspended users cannot log in with either normal credentials or an old remember-me cookie. [code_file:0]
- **Automatic token cleanup for blocked users** — if a blocked user still has a saved remember token, it is cleared from both the database and browser cookie. [code_file:0]
- **Session security** — `session_regenerate_id()` on login and timed regeneration for stronger protection against session fixation. [code_file:0]
- **Autofill protection** on signup — `autocomplete="new-password"` and hidden dummy fields prevent the browser from leaking saved credentials. [code_file:0]
- **Auth guard (`auth.php`)** — every protected page includes this file; unauthenticated users are immediately redirected. [code_file:0]

### 👤 User Dashboard
- **Home tab** — shows a personalized greeting and logged-in user info cards. [code_file:0]
- **Profile tab** — update full name, city, and university. [code_file:0]
- **Password tab** — change password with current password verification; enforces minimum 8 characters, 1 uppercase, 1 number. [code_file:0]
- **Activity Log tab** — users can view their own recorded actions inside the dashboard. [code_file:0]
- **Quick access buttons** — open DB Manager or jump directly to Activity Log from the dashboard home tab. [code_file:0]

### 🛡️ Admin Dashboard (Admin only)
- **Stats tab** — live counts of total employees, departments, projects, registered users, and average salary — all queried directly from SQL Server — plus **4 interactive Chart.js charts**. [code_file:0]
- **Employees per Department** bar chart. [code_file:0]
- **Job Role Distribution** doughnut chart. [code_file:0]
- **Average Salary by Department** bar chart. [code_file:0]
- **Top 6 Highest Earning Employees** horizontal bar chart. [code_file:0]
- **Users tab** — full list of all registered users with admin actions. [code_file:0]
- **Promote to Admin** button. [code_file:0]
- **Demote to User** button. [code_file:0]
- **Block user** button. [code_file:0]
- **Unblock user** button. [code_file:0]
- **Delete user** button with confirmation dialog. [code_file:0]
- **Self-protection** — admins cannot block, demote, promote, or delete their own account. [code_file:0]
- **Blocked status badge** — blocked accounts are clearly marked in the users table. [code_file:0]
- **Activity Log management** — admins can view all users' actions from a central log panel. [code_file:0]
- **Activity Log filters** — filter logs by action type or user name directly from the dashboard. [code_file:0]
- **Stable tab navigation** — activity log tab state is preserved correctly after filtering and actions. [code_file:0]

### 🗄️ Database Manager
- **View all tables** — EMP, DEPT, SALGRADE, Project, ProjAssign. [code_file:0]
- **Full join view** — one unified table joining all 5 tables via LEFT JOINs. [code_file:0]
- **Search employee by ID** — returns all linked data across tables. [code_file:0]
- **Insert employee** — form-based with full validation. [code_file:0]
- **Insert department** — add new department records. [code_file:0]
- **Update employee** — edit existing employee fields. [code_file:0]
- **Delete employee** — with safety check (employees assigned to projects cannot be deleted). [code_file:0]
- **Export to CSV** — export any table's data as a dated `.csv` file with one click. [code_file:0]
- **← Dashboard button** — quick navigation back to dashboard from any DB Manager tab. [code_file:0]

### 📜 Activity Logging
- **Automatic action history** for important system events. [code_file:0]
- Logged actions include login-related protected access flow, profile updates, password changes, employee insert / update / delete operations, department insert operations, user role changes, user block / unblock actions, and user deletion actions. [code_file:0]
- **User-level visibility** — normal users see only their own actions. [code_file:0]
- **Admin-level visibility** — admins can review all recorded activity across the system. [code_file:0]

### 🎨 UI & Design
- Dark theme throughout with a consistent modern color palette. [code_file:0]
- Styled dashboard with tabbed navigation, cards, alerts, tables, admin action badges, and charts. [code_file:0]
- Personalized greeting based on current time of day. [code_file:0]
- Improved dashboard flow with direct access to DB Manager and Activity Log. [code_file:0]
- Fully responsive layout. [code_file:0]

---

## ⚙️ Setup & Installation

### Prerequisites
- XAMPP (Apache + PHP 8.2). [code_file:0]
- Microsoft SQL Server + SSMS. [code_file:0]
- PHP `sqlsrv` and `pdo_sqlsrv` extensions enabled in `php.ini`. [code_file:0]

### Steps

1. **Clone the repo**
   ```bash
   git clone https://github.com/YOUR_USERNAME/EmpDB-Manager.git
   cd EmpDB-Manager
   ```

2. **Set up the database** — Open SSMS and run `demobld.sql` to create and populate all core tables. [code_file:0]

3. **Create the Users table**
   ```sql
   USE EmpDeptDB;
   CREATE TABLE Users (
       UserID         INT IDENTITY(1,1) PRIMARY KEY,
       FullName       VARCHAR(100) NOT NULL,
       Email          VARCHAR(100) NOT NULL UNIQUE,
       Password       VARCHAR(255) NOT NULL,
       City           VARCHAR(50)  NOT NULL,
       University     VARCHAR(100) NOT NULL,
       Role           VARCHAR(10)  NOT NULL DEFAULT 'user',
       RememberToken  VARCHAR(64)  NULL,
       IsBlocked      BIT          NOT NULL DEFAULT 0,
       BlockReason    NVARCHAR(255) NULL,
       BlockedAt      DATETIME      NULL,
       BlockedBy      INT           NULL,
       CreatedAt      DATETIME      DEFAULT GETDATE()
   );
   ```

4. **Create the ActivityLog table**
   ```sql
   CREATE TABLE ActivityLog (
       LogID       INT IDENTITY(1,1) PRIMARY KEY,
       UserName    VARCHAR(100) NOT NULL,
       Action      VARCHAR(50)  NOT NULL,
       Details     VARCHAR(255) NULL,
       IPAddress   VARCHAR(45)  NULL,
       LogTime     DATETIME     DEFAULT GETDATE()
   );
   ```

5. **Configure connection** — open `conn.php` and set your SQL Server instance name. [code_file:0]

6. **Create your admin account** — generate a bcrypt password hash and insert the first admin user manually through SSMS. [code_file:0]

7. **Start Apache** in XAMPP and visit:
   ```text
   http://localhost/EmpDB-Manager/
   ```

---

## 🧪 Test Cases

### Authentication
- Register a new user and log in successfully. [code_file:0]
- Try logging in with an incorrect password and verify the error message. [code_file:0]
- Log in with **Remember Me** checked, close the browser, and confirm auto-login works. [code_file:0]

### Block / Suspend User
- Log in as admin and block a normal user from the **Users** tab. [code_file:0]
- Try logging in as that blocked user with the correct password — access should be denied. [code_file:0]
- If that user had a remember-me token, confirm auto-login also fails. [code_file:0]
- Unblock the user and verify manual login works again. [code_file:0]

### Activity Log
- Update profile, change password, or perform CRUD actions. [code_file:0]
- Confirm the actions appear in the **Activity Log** tab. [code_file:0]
- As admin, filter logs by action type and by user name. [code_file:0]
- Verify the tab stays on **Activity Log** after filtering. [code_file:0]

### Admin Controls
- Promote a user to admin and verify the role updates correctly. [code_file:0]
- Demote an admin back to user. [code_file:0]
- Confirm admins cannot modify or delete their own account from the users table. [code_file:0]

### Database Manager
- View tables, run employee search, and test insert / update / delete functions. [code_file:0]
- Export a table to CSV and verify the downloaded file content. [code_file:0]

---

## 📁 Project Structure

```bash
EmpDB-Manager/
│
├── index.php
├── login.php
├── signup.php
├── dashboard.php
├── dbmanager.php
├── auth.php
├── conn.php
├── Functions.php
├── Classes.php
├── logout.php
├── README.md
└── demobld.sql
```

---

## 💬 Feedback & Suggestions

This project is actively being developed and improved. If you have any suggestions, ideas, or spot any issues, feel free to reach out. [code_file:0]

📧 **[shahryar.professional@gmail.com](mailto:shahryar.professional@gmail.com)** [code_file:0]

All feedback is welcome and appreciated. [code_file:0]

---

*Built by Shahryar Ahmad* [code_file:0]