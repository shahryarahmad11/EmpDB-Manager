# EmpDB Manager

A full-stack web application built with **PHP** and **Microsoft SQL Server** to manage employee records, departments, projects, salary grades, and registered users — with a complete authentication system, role-based access control, activity tracking, and admin user suspension controls.

***

## 🛠 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (procedural + OOP) |
| Database | Microsoft SQL Server (via `sqlsrv` driver) |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Server | Apache via XAMPP |
| Version Control | Git + GitHub |

***

## ✅ Features

### 🔐 Authentication System
- **Role selector landing page** — users pick Admin or Regular User before logging in, each routed to a contextually labelled login page
- **Secure login** using `password_hash()` and `password_verify()` — passwords are never stored in plain text
- **Remember Me** — generates a cryptographically secure token stored in the database and a 30-day `httponly` cookie
- **Blocked-user protection** — suspended users cannot log in with either normal credentials or an old remember-me cookie
- **Automatic token cleanup for blocked users** — if a blocked user still has a saved remember token, it is cleared from both the database and browser cookie
- **Session security** — `session_regenerate_id()` on login and timed regeneration for stronger protection against session fixation
- **Autofill protection** on signup — `autocomplete="new-password"` and hidden dummy fields prevent the browser from leaking saved credentials
- **Auth guard (`auth.php`)** — every protected page includes this file; unauthenticated users are immediately redirected

### 👤 User Dashboard
- **Home tab** — shows a personalized greeting and logged-in user info cards
- **Profile tab** — update full name, city, and university
- **Password tab** — change password with current password verification; enforces minimum 8 characters, 1 uppercase, 1 number
- **Activity Log tab** — users can view their own recorded actions inside the dashboard
- **Quick access buttons** — open DB Manager or jump directly to Activity Log from the dashboard home tab

### 🛡️ Admin Dashboard (Admin only)
- **Stats tab** — live counts of total employees, departments, projects, registered users, and average salary — all queried directly from SQL Server
- **Users tab** — full list of all registered users with:
  - **▲ Promote to Admin** button
  - **▼ Demote to User** button
  - **Block user** button
  - **Unblock user** button
  - **Delete user** button with confirmation dialog
  - **Self-protection** — admins cannot block, demote, promote, or delete their own account
- **Blocked status badge** — blocked accounts are clearly marked in the users table
- **Activity Log management** — admins can view all users' actions from a central log panel
- **Activity Log filters** — filter logs by action type or user name directly from the dashboard
- **Stable tab navigation** — activity log tab state is preserved correctly after filtering and actions

### 🗄️ Database Manager
- **View all tables** — EMP, DEPT, SALGRADE, Project, ProjAssign
- **Full join view** — one unified table joining all 5 tables via LEFT JOINs
- **Search employee by ID** — returns all linked data across tables
- **Insert employee** — form-based with full validation
- **Insert department** — add new department records
- **Update employee** — edit existing employee fields
- **Delete employee** — with safety check (employees assigned to projects cannot be deleted)
- **Export to CSV** — export any table's data as a dated `.csv` file with one click
- **← Dashboard button** — quick navigation back to dashboard from any DB Manager tab

### 📜 Activity Logging
- **Automatic action history** for important system events
- Logged actions include:
  - login-related protected access flow
  - profile updates
  - password changes
  - employee insert / update / delete operations
  - department insert operations
  - user role changes
  - user block / unblock actions
  - user deletion actions
- **User-level visibility** — normal users see only their own actions
- **Admin-level visibility** — admins can review all recorded activity across the system

### 🎨 UI & Design
- Dark theme throughout with a consistent modern color palette
- Styled dashboard with tabbed navigation, cards, alerts, tables, and admin action badges
- Personalized greeting based on current time of day
- Improved dashboard flow with direct access to DB Manager and Activity Log
- Fully responsive layout

***

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

2. **Set up the database** — Open SSMS and run `demobld.sql` to create and populate all core tables

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
       UserID      INT NOT NULL,
       Action      VARCHAR(50) NOT NULL,
       Details     VARCHAR(255) NULL,
       IPAddress   VARCHAR(45) NULL,
       CreatedAt   DATETIME DEFAULT GETDATE(),
       FOREIGN KEY (UserID) REFERENCES Users(UserID)
   );
   ```

5. **Configure connection** — open `conn.php` and set your SQL Server instance name

6. **Create your admin account** — generate a bcrypt password hash and insert the first admin user manually through SSMS

7. **Start Apache** in XAMPP and visit:**
   ```
   http://localhost/EmpDB-Manager/
   ```

***

## 🧪 Test Cases

### Authentication
- Register a new user and log in successfully
- Try logging in with an incorrect password and verify the error message
- Log in with **Remember Me** checked, close the browser, and confirm auto-login works

### Block / Suspend User
- Log in as admin and block a normal user from the **Users** tab
- Try logging in as that blocked user with the correct password — access should be denied
- If that user had a remember-me token, confirm auto-login also fails
- Unblock the user and verify manual login works again

### Activity Log
- Update profile, change password, or perform CRUD actions
- Confirm the actions appear in the **Activity Log** tab
- As admin, filter logs by action type and by user name
- Verify the tab stays on **Activity Log** after filtering

### Admin Controls
- Promote a user to admin and verify the role updates correctly
- Demote an admin back to user
- Confirm admins cannot modify or delete their own account from the users table

### Database Manager
- View tables, run employee search, and test insert / update / delete functions
- Export a table to CSV and verify the downloaded file content

***

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
├── functions.php
├── classes.php
├── logout.php
├── README.md
└── demobld.sql
```

***

## 💬 Feedback & Suggestions

This project is actively being developed and improved. If you have any suggestions, ideas, or spot any issues, feel free to reach out:

📧 **[shahryar.professional@gmail.com](mailto:shahryar.professional@gmail.com)**

All feedback is welcome and appreciated.

***

*Built by Shahryar Ahmad*