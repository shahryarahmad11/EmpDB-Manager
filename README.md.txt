# Employee Database Management System

A web-based database management system built with PHP and Microsoft SQL Server.

## Features
- Browse all database tables dynamically
- Full CRUD — Add, Update, Delete employees and departments
- Relational view joining EMP, DEPT, SALGRADE, Project & ProjAssign
- Search employee by ID with complete linked data

## Tech Stack
- **Backend:** PHP (XAMPP)
- **Database:** Microsoft SQL Server Express
- **Driver:** sqlsrv (PHP SQL Server extension)

## Setup
1. Install XAMPP and SQL Server Express
2. Enable `php_sqlsrv` extension in `php.ini`
3. Create database `EmpDeptDB` and import your schema
4. Clone this repo into `htdocs/`
5. Create your own `conn.php` with your server details