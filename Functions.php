<?php
require_once 'conn.php';

/* -----------------------------------------------
   Activity Log Helpers
----------------------------------------------- */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

function write_activity_log($action, $details) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Guest';
    return write_activity_log_as($username, $action, $details);
}

function write_activity_log_as($username, $action, $details) {
    $conn = get_connection();
    $ip   = get_client_ip();

    $sql = "INSERT INTO ActivityLog (UserName, Action, Details, IPAddress, LogTime)
            VALUES (?, ?, ?, ?, GETDATE())";

    $ok = sqlsrv_query($conn, $sql, array($username, $action, $details, $ip));
    sqlsrv_close($conn);

    return $ok ? true : false;
}

/* -----------------------------------------------
   Get all table names from database
----------------------------------------------- */
function get_all_tables() {
    $conn = get_connection();
    $tables = array();

    $sql = "
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ";

    $result = sqlsrv_query($conn, $sql);

    if ($result) {
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $tables[] = $row['TABLE_NAME'];
        }
    }

    sqlsrv_close($conn);
    return $tables;
}

/* -----------------------------------------------
   Fetch all rows from a selected table
----------------------------------------------- */
function get_table_data($table_name) {
    $conn = get_connection();
    $rows = array();

    $allowed = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
    if ($allowed === '') {
        sqlsrv_close($conn);
        return $rows;
    }

    $sql = "SELECT * FROM [$allowed]";
    $result = sqlsrv_query($conn, $sql);

    if ($result) {
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    }

    sqlsrv_close($conn);
    return $rows;
}

/* -----------------------------------------------
   Full database join view
----------------------------------------------- */
function get_full_join_data() {
    $conn = get_connection();
    $rows = array();

    $sql = "
        SELECT
            e.EMPNO,
            e.ENAME,
            e.JOB,
            e.SAL,
            e.COMM,
            e.HIREDATE,
            d.DEPTNO,
            d.DNAME,
            d.LOC,
            s.GRADE,
            s.LOSAL,
            s.HISAL,
            pa.ProjectID,
            p.ProjectName,
            p.Budget
        FROM EMP e
        LEFT JOIN DEPT d
            ON e.DEPTNO = d.DEPTNO
        LEFT JOIN SALGRADE s
            ON e.SAL BETWEEN s.LOSAL AND s.HISAL
        LEFT JOIN ProjAssign pa
            ON e.EMPNO = pa.EMPNO
        LEFT JOIN Project p
            ON pa.ProjectID = p.ProjectID
        ORDER BY e.EMPNO
    ";

    $result = sqlsrv_query($conn, $sql);

    if ($result) {
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    }

    sqlsrv_close($conn);
    return $rows;
}

/* -----------------------------------------------
   Search employee by ID from one or more tables
----------------------------------------------- */
function search_employee_by_id($empno, $table = 'all') {
    $conn = get_connection();
    $data = array();
    $empno = (int)$empno;

    if ($empno <= 0) {
        sqlsrv_close($conn);
        return $data;
    }

    if ($table === 'EMP' || $table === 'all') {
        $rows = array();
        $res = sqlsrv_query($conn, "SELECT * FROM EMP WHERE EMPNO = ?", array($empno));
        if ($res) {
            while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
        }
        if (!empty($rows)) {
            $data['EMP'] = $rows;
        }
    }

    if ($table === 'DEPT' || $table === 'all') {
        $rows = array();
        $res = sqlsrv_query($conn, "
            SELECT d.*
            FROM EMP e
            INNER JOIN DEPT d ON e.DEPTNO = d.DEPTNO
            WHERE e.EMPNO = ?
        ", array($empno));
        if ($res) {
            while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
        }
        if (!empty($rows)) {
            $data['DEPT'] = $rows;
        }
    }

    if ($table === 'SALGRADE' || $table === 'all') {
        $rows = array();
        $res = sqlsrv_query($conn, "
            SELECT s.*
            FROM EMP e
            INNER JOIN SALGRADE s
                ON e.SAL BETWEEN s.LOSAL AND s.HISAL
            WHERE e.EMPNO = ?
        ", array($empno));
        if ($res) {
            while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
        }
        if (!empty($rows)) {
            $data['SALGRADE'] = $rows;
        }
    }

    if ($table === 'Project' || $table === 'all') {
        $rows = array();
        $res = sqlsrv_query($conn, "
            SELECT p.*
            FROM ProjAssign pa
            INNER JOIN Project p
                ON pa.ProjectID = p.ProjectID
            WHERE pa.EMPNO = ?
        ", array($empno));
        if ($res) {
            while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $rows[] = $row;
            }
        }
        if (!empty($rows)) {
            $data['Project'] = $rows;
        }
    }

    sqlsrv_close($conn);
    return $data;
}

/* -----------------------------------------------
   Insert employee
----------------------------------------------- */
function insert_employee($empno, $ename, $job, $mgr, $hiredate, $sal, $comm, $deptno) {
    $conn = get_connection();

    $empno    = (int)$empno;
    $ename    = trim($ename);
    $job      = trim($job);
    $mgr      = ($mgr !== '') ? (int)$mgr : null;
    $hiredate = ($hiredate !== '') ? $hiredate : null;
    $sal      = (float)$sal;
    $comm     = ($comm !== '') ? (float)$comm : null;
    $deptno   = ($deptno !== '') ? (int)$deptno : null;

    if ($empno <= 0 || $ename === '' || $sal < 0) {
        sqlsrv_close($conn);
        return "Error: Invalid employee data.";
    }

    $sql = "
        INSERT INTO EMP (EMPNO, ENAME, JOB, MGR, HIREDATE, SAL, COMM, DEPTNO)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $ok = sqlsrv_query($conn, $sql, array(
        $empno, $ename, $job, $mgr, $hiredate, $sal, $comm, $deptno
    ));

    if ($ok) {
        write_activity_log(
            'INSERT_EMP',
            "Inserted employee EMPNO=$empno, Name=$ename, Job=$job, DeptNo=" . ($deptno ?? 'NULL')
        );
        $msg = "Employee inserted successfully.";
    } else {
        $msg = "Error: " . print_r(sqlsrv_errors(), true);
    }

    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   Insert department
----------------------------------------------- */
function insert_department($deptno, $dname, $loc) {
    $conn = get_connection();

    $deptno = (int)$deptno;
    $dname  = trim($dname);
    $loc    = trim($loc);

    if ($deptno <= 0 || $dname === '') {
        sqlsrv_close($conn);
        return "Error: Invalid department data.";
    }

    $sql = "INSERT INTO DEPT (DEPTNO, DNAME, LOC) VALUES (?, ?, ?)";
    $ok = sqlsrv_query($conn, $sql, array($deptno, $dname, $loc));

    if ($ok) {
        write_activity_log(
            'INSERT_DEPT',
            "Inserted department DEPTNO=$deptno, Name=$dname, Location=$loc"
        );
        $msg = "Department inserted successfully.";
    } else {
        $msg = "Error: " . print_r(sqlsrv_errors(), true);
    }

    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   Update employee
----------------------------------------------- */
function update_employee($empno, $ename, $job, $sal, $comm, $deptno) {
    $conn = get_connection();

    $empno  = (int)$empno;
    $ename  = trim($ename);
    $job    = trim($job);
    $sal    = (float)$sal;
    $comm   = ($comm !== '') ? (float)$comm : null;
    $deptno = ($deptno !== '') ? (int)$deptno : null;

    if ($empno <= 0 || $ename === '' || $sal < 0) {
        sqlsrv_close($conn);
        return "Error: Invalid update data.";
    }

    $sql = "
        UPDATE EMP
        SET ENAME = ?, JOB = ?, SAL = ?, COMM = ?, DEPTNO = ?
        WHERE EMPNO = ?
    ";

    $ok = sqlsrv_query($conn, $sql, array(
        $ename, $job, $sal, $comm, $deptno, $empno
    ));

    if ($ok) {
        write_activity_log(
            'UPDATE_EMP',
            "Updated employee EMPNO=$empno, Name=$ename, Job=$job, Salary=$sal, DeptNo=" . ($deptno ?? 'NULL')
        );
        $msg = "Employee updated successfully.";
    } else {
        $msg = "Error: " . print_r(sqlsrv_errors(), true);
    }

    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   Delete employee safely
----------------------------------------------- */
function delete_employee($empno) {
    $conn  = get_connection();
    $empno = (int)$empno;

    if ($empno <= 0) {
        sqlsrv_close($conn);
        return "Error: Invalid employee number.";
    }

    $check_emp = sqlsrv_query(
        $conn,
        "SELECT EMPNO, ENAME FROM EMP WHERE EMPNO = ?",
        array($empno)
    );
    $emp_row = $check_emp ? sqlsrv_fetch_array($check_emp, SQLSRV_FETCH_ASSOC) : null;

    if (!$emp_row) {
        sqlsrv_close($conn);
        return "Error: Employee not found.";
    }

    $check_proj = sqlsrv_query(
        $conn,
        "SELECT COUNT(*) AS total FROM ProjAssign WHERE EMPNO = ?",
        array($empno)
    );
    $proj_row = $check_proj ? sqlsrv_fetch_array($check_proj, SQLSRV_FETCH_ASSOC) : null;
    $assigned_count = $proj_row ? (int)$proj_row['total'] : 0;

    if ($assigned_count > 0) {
        sqlsrv_close($conn);
        return "Error: Cannot delete employee #$empno because they are assigned to $assigned_count project(s). Remove the project assignment(s) first.";
    }

    $ok = sqlsrv_query(
        $conn,
        "DELETE FROM EMP WHERE EMPNO = ?",
        array($empno)
    );

    if ($ok) {
        write_activity_log(
            'DELETE_EMP',
            "Deleted employee EMPNO=" . $emp_row['EMPNO'] . ", Name=" . $emp_row['ENAME']
        );
        $msg = "Employee deleted successfully.";
    } else {
        $msg = "Error: " . print_r(sqlsrv_errors(), true);
    }

    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   Fetch a single employee for the update form
----------------------------------------------- */
function get_employee_by_id($empno) {
    $conn = get_connection();
    $empno = (int)$empno;

    $result = sqlsrv_query($conn, "SELECT * FROM EMP WHERE EMPNO = ?", array($empno));
    $row = null;

    if ($result) {
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    }

    sqlsrv_close($conn);
    return $row;
}

/* -----------------------------------------------
   Render an HTML table
----------------------------------------------- */
function render_table($rows, $title = '') {
    if (empty($rows)) {
        echo "<p class='no-data'>No records found.</p>";
        return;
    }

    if ($title !== '') {
        echo "<div class='table-title'>" . htmlspecialchars($title) . "</div>";
    }

    echo "<div class='table-wrap'>";
    echo "<table>";
    echo "<thead><tr>";

    foreach (array_keys($rows[0]) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }

    echo "</tr></thead>";
    echo "<tbody>";

    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            if ($val instanceof DateTime) {
                $val = $val->format('Y-m-d');
            }
            if ($val === null || $val === '') {
                echo "<td><span class='null-val'>—</span></td>";
            } else {
                echo "<td>" . htmlspecialchars((string)$val) . "</td>";
            }
        }
        echo "</tr>";
    }

    echo "</tbody>";
    echo "</table>";
    echo "</div>";
}
?>