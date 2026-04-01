<?php
require_once 'conn.php';
require_once 'Classes.php';

/* -----------------------------------------------
   Helper: fetch all rows from a sqlsrv result
----------------------------------------------- */
function sqlsrv_fetch_all($result) {
    $rows = array();
    if ($result) {
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/* -----------------------------------------------
   Get all table names in the database
----------------------------------------------- */
function get_all_tables() {
    $conn   = get_connection();
    $tables = array();

    // Whitelist only your actual tables
    $allowed = array('DEPT', 'EMP', 'BONUS', 'SALGRADE', 'Project', 'ProjAssign');

    $result = sqlsrv_query($conn,
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'"
    );
    if ($result) {
        while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC)) {
            if (in_array($row[0], $allowed)) {
                $tables[] = $row[0];
            }
        }
    }
    sqlsrv_close($conn);
    return $tables;
}

/* -----------------------------------------------
   Fetch all rows from a given table
----------------------------------------------- */
function get_table_data($table_name) {
    $conn       = get_connection();
    $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
    $result     = sqlsrv_query($conn, "SELECT * FROM [$table_name]");
    $rows       = sqlsrv_fetch_all($result);
    sqlsrv_close($conn);
    return $rows;
}

/* -----------------------------------------------
   Fetch column headers for a given table
----------------------------------------------- */
function get_table_columns($table_name) {
    $conn       = get_connection();
    $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
    $result     = sqlsrv_query($conn,
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?",
        array($table_name)
    );
    $columns = array();
    if ($result) {
        while ($col = sqlsrv_fetch_array($result, SQLSRV_FETCH_NUMERIC)) {
            $columns[] = $col[0];
        }
    }
    sqlsrv_close($conn);
    return $columns;
}

/* -----------------------------------------------
   Full DB view: join EMP, DEPT, SALGRADE, Project, ProjAssign
----------------------------------------------- */
function get_full_join_data() {
    $conn = get_connection();
    $sql  = "SELECT e.EMPNO, e.ENAME, e.JOB, e.MGR, e.HIREDATE, e.SAL, e.COMM,
                    d.DEPTNO, d.DNAME, d.LOC, s.GRADE,
                    p.Projno, p.ProjName, pa.ProjPeriod, pa.NoOfHrs
             FROM EMP e
             LEFT JOIN DEPT d        ON e.DEPTNO = d.DEPTNO
             LEFT JOIN SALGRADE s    ON e.SAL BETWEEN s.LOSAL AND s.HISAL
             LEFT JOIN ProjAssign pa ON e.EMPNO = pa.Empno
             LEFT JOIN Project p     ON pa.Projno = p.Projno
             ORDER BY e.EMPNO";
    $result = sqlsrv_query($conn, $sql);
    $rows   = sqlsrv_fetch_all($result);
    sqlsrv_close($conn);
    return $rows;
}

/* -----------------------------------------------
   Search employee by EMPNO
----------------------------------------------- */
function search_employee_by_id($empno, $table = 'all') {
    $conn  = get_connection();
    $empno = (int) $empno;
    $rows  = array();

    if ($table == 'EMP' || $table == 'all') {
        $r = sqlsrv_query($conn, "SELECT * FROM EMP WHERE EMPNO = ?", array($empno));
        if ($r) while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) $rows['EMP'][] = $row;
    }
    if ($table == 'DEPT' || $table == 'all') {
        $r = sqlsrv_query($conn,
            "SELECT d.* FROM DEPT d JOIN EMP e ON e.DEPTNO = d.DEPTNO WHERE e.EMPNO = ?",
            array($empno));
        if ($r) while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) $rows['DEPT'][] = $row;
    }
    if ($table == 'SALGRADE' || $table == 'all') {
        $r = sqlsrv_query($conn,
            "SELECT s.* FROM SALGRADE s JOIN EMP e ON e.SAL BETWEEN s.LOSAL AND s.HISAL WHERE e.EMPNO = ?",
            array($empno));
        if ($r) while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) $rows['SALGRADE'][] = $row;
    }
    if ($table == 'Project' || $table == 'all') {
        $r = sqlsrv_query($conn,
            "SELECT p.* FROM Project p JOIN ProjAssign pa ON pa.Projno = p.Projno WHERE pa.Empno = ?",
            array($empno));
        if ($r) while ($row = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC)) $rows['Project'][] = $row;
    }

    sqlsrv_close($conn);
    return $rows;
}

/* -----------------------------------------------
   INSERT a new employee record
----------------------------------------------- */
function insert_employee($empno, $ename, $job, $mgr, $hiredate, $sal, $comm, $deptno) {
    $conn   = get_connection();
    $empno  = (int) $empno;
    $mgr    = ($mgr    == '') ? null : (int) $mgr;
    $comm   = ($comm   == '') ? null : (float) $comm;
    $deptno = ($deptno == '') ? null : (int) $deptno;
    $sal    = (float) $sal;

    $sql    = "INSERT INTO EMP (EMPNO,ENAME,JOB,MGR,HIREDATE,SAL,COMM,DEPTNO)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $ok  = sqlsrv_query($conn, $sql, array($empno,$ename,$job,$mgr,$hiredate,$sal,$comm,$deptno));
    $msg = $ok ? "Employee inserted successfully." : "Error: " . print_r(sqlsrv_errors(), true);
    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   INSERT a new department record
----------------------------------------------- */
function insert_department($deptno, $dname, $loc) {
    $conn   = get_connection();
    $deptno = (int) $deptno;
    $ok     = sqlsrv_query($conn,
        "INSERT INTO DEPT (DEPTNO, DNAME, LOC) VALUES (?, ?, ?)",
        array($deptno, $dname, $loc)
    );
    $msg = $ok ? "Department inserted successfully." : "Error: " . print_r(sqlsrv_errors(), true);
    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   UPDATE an existing employee record
----------------------------------------------- */
function update_employee($empno, $ename, $job, $sal, $comm, $deptno) {
    $conn   = get_connection();
    $empno  = (int) $empno;
    $sal    = (float) $sal;
    $comm   = ($comm   == '') ? null : (float) $comm;
    $deptno = ($deptno == '') ? null : (int) $deptno;

    $ok  = sqlsrv_query($conn,
        "UPDATE EMP SET ENAME=?, JOB=?, SAL=?, COMM=?, DEPTNO=? WHERE EMPNO=?",
        array($ename, $job, $sal, $comm, $deptno, $empno)
    );
    $msg = $ok ? "Employee updated successfully." : "Error: " . print_r(sqlsrv_errors(), true);
    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   DELETE an employee by EMPNO
----------------------------------------------- */
function delete_employee($empno) {
    $conn  = get_connection();
    $empno = (int) $empno;
    $ok    = sqlsrv_query($conn, "DELETE FROM EMP WHERE EMPNO = ?", array($empno));
    $msg   = $ok ? "Employee deleted successfully." : "Error: " . print_r(sqlsrv_errors(), true);
    sqlsrv_close($conn);
    return $msg;
}

/* -----------------------------------------------
   Fetch a single employee for the update form
----------------------------------------------- */
function get_employee_by_id($empno) {
    $conn   = get_connection();
    $empno  = (int) $empno;
    $result = sqlsrv_query($conn, "SELECT * FROM EMP WHERE EMPNO = ?", array($empno));
    $row    = null;
    if ($result) {
        $row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    }
    sqlsrv_close($conn);
    return $row;
}

/* -----------------------------------------------
   Render an HTML table — NULL shown as dash
----------------------------------------------- */
function render_table($rows, $title = '') {
    if (empty($rows)) {
        echo "<p><em>No records found.</em></p>";
        return;
    }
    if ($title) echo "<h3>" . htmlspecialchars($title) . "</h3>";
    $columns = array_keys($rows[0]);
    echo "<table border='1' cellpadding='6' cellspacing='0'><thead><tr>";
    foreach ($columns as $col) echo "<th>" . htmlspecialchars($col) . "</th>";
    echo "</tr></thead><tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($columns as $col) {
            if ($row[$col] instanceof DateTime) {
                $val = $row[$col]->format('Y-m-d');
            } else {
                $val = ($row[$col] === null) ? "&mdash;" : htmlspecialchars($row[$col]);
            }
            echo "<td>$val</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
}
?>