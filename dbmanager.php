<?php
/*
 * dbmanager.php
 * Main entry point for the Employee-Department Management System.
 * Table Viewer, Full Join View, Employee Search, CRUD + Activity Logging.
 *
 * Project by Shahryar Ahmad
 */
require_once 'auth.php';
require_once 'Functions.php';
require_once 'Classes.php';

/* -----------------------------------------------
   CSV Export — must run before any HTML output
----------------------------------------------- */
if (
    isset($_GET['export_csv']) && $_GET['export_csv'] == '1' &&
    isset($_GET['view_table']) && $_GET['view_table'] != ''
) {
    $export_table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['view_table']);
    $conn         = get_connection();
    $res          = sqlsrv_query($conn, "SELECT * FROM [$export_table]");

    if ($res) {
        write_activity_log('EXPORT_CSV', "Exported table '$export_table' as CSV.");

        $filename = $export_table . '_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output    = fopen('php://output', 'w');
        $first_row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

        if ($first_row) {
            fputcsv($output, array_keys($first_row));
            $row_data = array();
            foreach ($first_row as $val) {
                $row_data[] = $val instanceof DateTime ? $val->format('Y-m-d') : ($val === null ? '' : $val);
            }
            fputcsv($output, $row_data);

            while ($row = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC)) {
                $row_data = array();
                foreach ($row as $val) {
                    $row_data[] = $val instanceof DateTime ? $val->format('Y-m-d') : ($val === null ? '' : $val);
                }
                fputcsv($output, $row_data);
            }
        }

        fclose($output);
    }

    sqlsrv_close($conn);
    exit();
}

$action_msg = '';

/* -----------------------------------------------
   Handle POST CRUD actions
----------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'insert_emp') {
        $action_msg = insert_employee(
            $_POST['empno']    ?? '', $_POST['ename'] ?? '', $_POST['job']  ?? '',
            $_POST['mgr']      ?? '', $_POST['hiredate'] ?? '', $_POST['sal'] ?? '',
            $_POST['comm']     ?? '', $_POST['deptno'] ?? ''
        );

    } elseif ($action === 'insert_dept') {
        $action_msg = insert_department(
            $_POST['deptno'] ?? '', $_POST['dname'] ?? '', $_POST['loc'] ?? ''
        );

    } elseif ($action === 'update_emp') {
        $action_msg = update_employee(
            $_POST['empno']  ?? '', $_POST['ename'] ?? '', $_POST['job']    ?? '',
            $_POST['sal']    ?? '', $_POST['comm']  ?? '', $_POST['deptno'] ?? ''
        );

    } elseif ($action === 'delete_emp') {
        $action_msg = delete_employee($_POST['del_empno'] ?? '');
    }
}

/* -----------------------------------------------
   Page state & read-only action logging
----------------------------------------------- */
$all_tables = get_all_tables();

$selected_table = isset($_GET['view_table']) ? $_GET['view_table'] : '';
$table_rows     = array();
if ($selected_table !== '') {
    $table_rows = get_table_data($selected_table);
    write_activity_log('VIEW_TABLE', "Viewed table '$selected_table'.");
}

$show_full_join = isset($_GET['full_join']) && $_GET['full_join'] == '1';
$full_join_rows = array();
if ($show_full_join) {
    $full_join_rows = get_full_join_data();
    write_activity_log('FULL_JOIN_VIEW', 'Viewed full database joined data.');
}

$search_result = array();
$search_empno  = isset($_GET['search_id']) ? trim($_GET['search_id']) : '';
$search_table  = isset($_GET['search_table']) ? $_GET['search_table'] : 'all';
if ($search_empno !== '') {
    $search_result = search_employee_by_id($search_empno, $search_table);
    write_activity_log('SEARCH_EMP', "Searched for employee EMPNO=$search_empno in table=$search_table.");
}

$update_emp = null;
if (isset($_GET['update_id']) && $_GET['update_id'] !== '') {
    $update_emp = get_employee_by_id($_GET['update_id']);
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'viewer';
if ($show_full_join)  $active_tab = 'join';
if ($search_empno)    $active_tab = 'search';
if ($update_emp)      $active_tab = 'crud';

/* ── Greeting ── */
$first_name = explode(' ', $_SESSION['user_name'])[0];
$hour       = (int)date('H');
if ($hour < 12)       $greeting = 'Good morning';
elseif ($hour < 17)   $greeting = 'Good afternoon';
else                  $greeting = 'Good evening';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EmpDept Management System</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;600;700&display=swap');
        :root {
            --bg:       #0f1117; --surface:  #1a1d27; --surface2: #222639;
            --border:   #2e3350; --accent:   #4f8ef7; --accent2:  #7c5cfc;
            --success:  #3ecf8e; --danger:   #f46060; --warning:  #f5a623;
            --text:     #e2e8f0; --text-dim: #8892a4;
            --mono: 'IBM Plex Mono', monospace;
            --sans: 'IBM Plex Sans', sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:var(--bg);color:var(--text);font-family:var(--sans);font-size:14px;line-height:1.6;min-height:100vh}
        .header{background:var(--surface);border-bottom:1px solid var(--border);padding:18px 32px;display:flex;align-items:center;justify-content:space-between;gap:16px}
        .header-title{font-size:20px;font-weight:700;color:var(--accent);letter-spacing:.5px;white-space:nowrap}
        .header-right{display:flex;align-items:center;gap:16px}
        .header-greeting{font-family:var(--sans);font-size:13px;color:var(--text-dim);white-space:nowrap}
        .header-greeting strong{color:var(--accent)}
        .btn-dashboard{display:inline-flex;align-items:center;gap:6px;background:var(--surface2);color:var(--accent);padding:7px 14px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:600;border:1px solid var(--border);transition:background .2s,border-color .2s;white-space:nowrap}
        .btn-dashboard:hover{background:var(--border);border-color:var(--accent)}
        .nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 32px;display:flex;gap:4px}
        .nav a{display:inline-block;padding:12px 20px;text-decoration:none;color:var(--text-dim);font-size:13px;font-weight:600;border-bottom:2px solid transparent;transition:color .2s,border-color .2s}
        .nav a:hover{color:var(--text)}.nav a.active{color:var(--accent);border-bottom-color:var(--accent)}
        .container{max-width:1300px;margin:0 auto;padding:32px}
        .card{background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:28px;margin-bottom:24px}
        .card-title{font-size:15px;font-weight:700;color:var(--accent);margin-bottom:20px;display:flex;align-items:center;gap:8px}
        .card-title::before{content:'';display:inline-block;width:3px;height:16px;background:var(--accent);border-radius:2px}
        .form-row{display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;margin-bottom:14px}
        .form-group{display:flex;flex-direction:column;gap:5px;flex:1;min-width:140px}
        .form-group label{font-size:11px;font-weight:600;color:var(--text-dim);text-transform:uppercase;letter-spacing:.6px;font-family:var(--mono)}
        input[type=text],input[type=number],input[type=date],select{background:var(--bg);border:1px solid var(--border);border-radius:5px;color:var(--text);font-family:var(--sans);font-size:13px;padding:8px 12px;outline:none;transition:border-color .2s}
        input:focus,select:focus{border-color:var(--accent)}
        .btn{padding:9px 20px;border:none;border-radius:5px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s,transform .1s;text-decoration:none;display:inline-block}
        .btn:hover{opacity:.85}.btn:active{transform:scale(.98)}
        .btn-primary{background:var(--accent);color:#fff}
        .btn-success{background:var(--success);color:#0f1117}
        .btn-danger{background:var(--danger);color:#fff}
        .btn-warning{background:var(--warning);color:#0f1117}
        .btn-secondary{background:var(--surface2);color:var(--text);border:1px solid var(--border)}
        .msg-banner{padding:12px 18px;border-radius:6px;margin-bottom:20px;font-size:13px;font-weight:600}
        .msg-success{background:rgba(62,207,142,.12);border:1px solid var(--success);color:var(--success)}
        .msg-error{background:rgba(244,96,96,.12);border:1px solid var(--danger);color:var(--danger)}
        .table-wrap{overflow-x:auto;border-radius:6px;border:1px solid var(--border)}
        table{width:100%;border-collapse:collapse;font-size:13px}
        thead tr{background:var(--surface2)}
        th{padding:10px 14px;text-align:left;font-family:var(--mono);font-size:11px;font-weight:600;color:var(--text-dim);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
        td{padding:9px 14px;border-bottom:1px solid var(--border);color:var(--text)}
        tbody tr:hover{background:var(--surface2)}
        tbody tr:last-child td{border-bottom:none}
        .null-val{color:var(--text-dim);font-family:var(--mono)}
        .table-title{font-size:13px;font-weight:700;color:var(--text-dim);margin:18px 0 8px 0;text-transform:uppercase;letter-spacing:.6px;font-family:var(--mono)}
        .no-data{color:var(--text-dim);font-style:italic;padding:12px 0}
        .error-msg{color:var(--danger)}
        .export-bar{display:flex;align-items:center;justify-content:flex-end;margin-top:12px}
        .sub-tabs{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
        .sub-tab{padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;color:var(--text-dim);background:var(--surface2);border:1px solid var(--border);transition:all .2s}
        .sub-tab.active,.sub-tab:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
        hr.divider{border:none;border-top:1px solid var(--border);margin:20px 0}
    </style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <div class="header-title">&#9670; EmpDept Management System</div>
    <div class="header-right">
        <span class="header-greeting">
            <?php echo htmlspecialchars($greeting); ?>, <strong><?php echo htmlspecialchars($first_name); ?></strong> &#128075;
        </span>
        <a href="dashboard.php" class="btn-dashboard">&#8592; Dashboard</a>
    </div>
</div>

<!-- NAV TABS -->
<nav class="nav">
    <a href="?tab=viewer"  class="<?php echo ($active_tab === 'viewer') ? 'active' : ''; ?>">Table Viewer</a>
    <a href="?full_join=1" class="<?php echo ($active_tab === 'join')   ? 'active' : ''; ?>">Full DB Join</a>
    <a href="?tab=search"  class="<?php echo ($active_tab === 'search') ? 'active' : ''; ?>">Employee Search</a>
    <a href="?tab=crud"    class="<?php echo ($active_tab === 'crud')   ? 'active' : ''; ?>">CRUD Operations</a>
</nav>

<div class="container">

<?php if ($action_msg !== ''): ?>
    <?php $is_error = (stripos($action_msg, 'error') !== false); ?>
    <div class="msg-banner <?php echo $is_error ? 'msg-error' : 'msg-success'; ?>">
        <?php echo htmlspecialchars($action_msg); ?>
    </div>
<?php endif; ?>

<!-- SECTION 1: TABLE VIEWER -->
<?php if ($active_tab === 'viewer'): ?>
<div class="card">
    <div class="card-title">Table Viewer</div>
    <form method="GET" action="">
        <input type="hidden" name="tab" value="viewer">
        <div class="form-row">
            <div class="form-group">
                <label for="view_table">Select a table</label>
                <select name="view_table" id="view_table">
                    <option value="">-- Choose Table --</option>
                    <?php foreach ($all_tables as $tbl): ?>
                        <option value="<?php echo htmlspecialchars($tbl); ?>"
                            <?php echo ($tbl === $selected_table) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">View Records</button>
        </div>
    </form>

    <?php if ($selected_table !== ''): ?>
        <?php if (!empty($table_rows)): ?>
            <?php render_table($table_rows, $selected_table); ?>
            <div class="export-bar">
                <a href="?tab=viewer&view_table=<?php echo urlencode($selected_table); ?>&export_csv=1"
                   class="btn btn-secondary" style="font-size:12px">
                    &#8595; Export as CSV
                </a>
            </div>
        <?php else: ?>
            <p class="no-data">No records in table <strong><?php echo htmlspecialchars($selected_table); ?></strong>.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- SECTION 2: FULL DB JOIN -->
<?php if ($active_tab === 'join'): ?>
<div class="card">
    <div class="card-title">Full Database View &mdash; Joined</div>
    <p style="color:var(--text-dim);margin-bottom:16px;font-size:13px">
        Joining EMP &rarr; DEPT &rarr; SALGRADE &rarr; ProjAssign &rarr; Project. NULL values shown as &mdash;
    </p>
    <?php render_table($full_join_rows); ?>
</div>
<?php endif; ?>

<!-- SECTION 3: EMPLOYEE SEARCH -->
<?php if ($active_tab === 'search'): ?>
<div class="card">
    <div class="card-title">Employee Search by ID</div>
    <form method="GET" action="">
        <input type="hidden" name="tab" value="search">
        <div class="form-row">
            <div class="form-group" style="max-width:160px">
                <label for="search_id">Employee ID</label>
                <input type="number" name="search_id" id="search_id"
                       value="<?php echo htmlspecialchars($search_empno); ?>"
                       placeholder="e.g. 7369">
            </div>
            <div class="form-group" style="max-width:180px">
                <label for="search_table">From table</label>
                <select name="search_table" id="search_table">
                    <option value="all"      <?php echo $search_table === 'all'      ? 'selected' : ''; ?>>All Related Tables</option>
                    <option value="EMP"      <?php echo $search_table === 'EMP'      ? 'selected' : ''; ?>>EMP</option>
                    <option value="DEPT"     <?php echo $search_table === 'DEPT'     ? 'selected' : ''; ?>>DEPT</option>
                    <option value="SALGRADE" <?php echo $search_table === 'SALGRADE' ? 'selected' : ''; ?>>SALGRADE</option>
                    <option value="Project"  <?php echo $search_table === 'Project'  ? 'selected' : ''; ?>>Project</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" onclick="return validate_search();">Search</button>
        </div>
    </form>

    <?php if (!empty($search_result)): ?>
        <?php foreach ($search_result as $tbl_name => $rows): ?>
            <?php render_table($rows, $tbl_name); ?>
        <?php endforeach; ?>
    <?php elseif ($search_empno !== ''): ?>
        <p class="no-data">No records found for Employee ID: <strong><?php echo htmlspecialchars($search_empno); ?></strong>.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- SECTION 4: CRUD OPERATIONS -->
<?php if ($active_tab === 'crud'): ?>
<div class="card">
    <div class="card-title">CRUD Operations</div>

    <div class="sub-tabs">
        <a href="?tab=crud&crud=insert_emp"
           class="sub-tab <?php echo (!isset($_GET['crud']) || $_GET['crud'] === 'insert_emp') ? 'active' : ''; ?>">
           Insert Employee</a>
        <a href="?tab=crud&crud=insert_dept"
           class="sub-tab <?php echo (isset($_GET['crud']) && $_GET['crud'] === 'insert_dept') ? 'active' : ''; ?>">
           Insert Department</a>
        <a href="?tab=crud&crud=update"
           class="sub-tab <?php echo (isset($_GET['crud']) && $_GET['crud'] === 'update') ? 'active' : ''; ?>">
           Update Employee</a>
        <a href="?tab=crud&crud=delete"
           class="sub-tab <?php echo (isset($_GET['crud']) && $_GET['crud'] === 'delete') ? 'active' : ''; ?>">
           Delete Employee</a>
        <a href="?tab=crud&crud=query"
           class="sub-tab <?php echo (isset($_GET['crud']) && $_GET['crud'] === 'query') ? 'active' : ''; ?>">
           Query / Search</a>
    </div>

    <?php $crud_section = isset($_GET['crud']) ? $_GET['crud'] : 'insert_emp'; ?>

    <!-- INSERT EMPLOYEE -->
    <?php if ($crud_section === 'insert_emp' || !isset($_GET['crud'])): ?>
    <form method="POST" action="?tab=crud&crud=insert_emp" onsubmit="return validate_emp_form();">
        <input type="hidden" name="action" value="insert_emp">
        <div class="form-row">
            <div class="form-group">
                <label>EmpNo *</label>
                <input type="number" name="empno" required placeholder="e.g. 9001">
            </div>
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="ename" required placeholder="JOHN">
            </div>
            <div class="form-group">
                <label>Job</label>
                <input type="text" name="job" placeholder="CLERK">
            </div>
            <div class="form-group">
                <label>Manager EmpNo</label>
                <input type="number" name="mgr" placeholder="leave blank if none">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Hire Date</label>
                <input type="date" name="hiredate">
            </div>
            <div class="form-group">
                <label>Salary *</label>
                <input type="number" step="0.01" name="sal" required placeholder="2000.00">
            </div>
            <div class="form-group">
                <label>Commission</label>
                <input type="number" step="0.01" name="comm" placeholder="leave blank if none">
            </div>
            <div class="form-group">
                <label>DeptNo</label>
                <input type="number" name="deptno" placeholder="10, 20, 30...">
            </div>
        </div>
        <button type="submit" class="btn btn-success">Insert Employee</button>
    </form>
    <?php endif; ?>

    <!-- INSERT DEPARTMENT -->
    <?php if ($crud_section === 'insert_dept'): ?>
    <form method="POST" action="?tab=crud&crud=insert_dept">
        <input type="hidden" name="action" value="insert_dept">
        <div class="form-row">
            <div class="form-group" style="max-width:160px">
                <label>DeptNo *</label>
                <input type="number" name="deptno" required placeholder="60">
            </div>
            <div class="form-group">
                <label>Department Name *</label>
                <input type="text" name="dname" required placeholder="FINANCE">
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" name="loc" placeholder="LONDON">
            </div>
        </div>
        <button type="submit" class="btn btn-success">Insert Department</button>
    </form>
    <?php endif; ?>

    <!-- UPDATE EMPLOYEE -->
    <?php if ($crud_section === 'update'): ?>
    <div>
        <form method="GET" action="">
            <input type="hidden" name="tab"  value="crud">
            <input type="hidden" name="crud" value="update">
            <div class="form-row">
                <div class="form-group" style="max-width:200px">
                    <label>Load Employee by ID</label>
                    <input type="number" name="update_id"
                           value="<?php echo $update_emp ? htmlspecialchars($update_emp['EMPNO']) : ''; ?>"
                           placeholder="Enter EMPNO">
                </div>
                <button type="submit" class="btn btn-warning">Load</button>
            </div>
        </form>

        <?php if ($update_emp): ?>
        <hr class="divider">
        <form method="POST" action="?tab=crud&crud=update" onsubmit="return validate_update_form();">
            <input type="hidden" name="action" value="update_emp">
            <input type="hidden" name="empno"  value="<?php echo htmlspecialchars($update_emp['EMPNO']); ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>EmpNo (read-only)</label>
                    <input type="text" value="<?php echo htmlspecialchars($update_emp['EMPNO']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="ename" required
                           value="<?php echo htmlspecialchars($update_emp['ENAME']); ?>">
                </div>
                <div class="form-group">
                    <label>Job</label>
                    <input type="text" name="job"
                           value="<?php echo htmlspecialchars($update_emp['JOB'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Salary *</label>
                    <input type="number" step="0.01" name="sal" required
                           value="<?php echo htmlspecialchars($update_emp['SAL']); ?>">
                </div>
                <div class="form-group">
                    <label>Commission</label>
                    <input type="number" step="0.01" name="comm"
                           value="<?php echo htmlspecialchars($update_emp['COMM'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>DeptNo</label>
                    <input type="number" name="deptno"
                           value="<?php echo htmlspecialchars($update_emp['DEPTNO'] ?? ''); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-warning">Update Employee</button>
        </form>
        <?php elseif (isset($_GET['update_id']) && $_GET['update_id'] !== ''): ?>
            <p class="no-data">No employee found with ID <?php echo htmlspecialchars($_GET['update_id']); ?>.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- DELETE EMPLOYEE -->
    <?php if ($crud_section === 'delete'): ?>
    <form method="POST" action="?tab=crud&crud=delete" onsubmit="return confirm_delete();">
        <input type="hidden" name="action" value="delete_emp">
        <div class="form-row">
            <div class="form-group" style="max-width:200px">
                <label>Employee ID to Delete</label>
                <input type="number" name="del_empno" required placeholder="Enter EMPNO">
            </div>
            <button type="submit" class="btn btn-danger">Delete Employee</button>
        </div>
        <p style="color:var(--text-dim);font-size:12px;margin-top:8px">
            &#9888; This action cannot be undone. Employees assigned to projects cannot be deleted.
        </p>
    </form>
    <?php endif; ?>

    <!-- QUERY / SEARCH -->
    <?php if ($crud_section === 'query'): ?>
    <form method="GET" action="">
        <input type="hidden" name="tab"  value="crud">
        <input type="hidden" name="crud" value="query">
        <div class="form-row">
            <div class="form-group" style="max-width:180px">
                <label>Search in Table</label>
                <select name="q_table">
                    <option value="EMP">EMP</option>
                    <option value="DEPT">DEPT</option>
                    <option value="SALGRADE">SALGRADE</option>
                    <option value="Project">Project</option>
                    <option value="ProjAssign">ProjAssign</option>
                </select>
            </div>
            <div class="form-group">
                <label>Column</label>
                <input type="text" name="q_col" placeholder="e.g. JOB"
                       value="<?php echo isset($_GET['q_col']) ? htmlspecialchars($_GET['q_col']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>Value</label>
                <input type="text" name="q_val" placeholder="e.g. CLERK"
                       value="<?php echo isset($_GET['q_val']) ? htmlspecialchars($_GET['q_val']) : ''; ?>">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </form>

    <?php
    if (
        isset($_GET['q_table'], $_GET['q_col'], $_GET['q_val']) &&
        $_GET['q_col'] !== '' && $_GET['q_val'] !== ''
    ) {
        $conn    = get_connection();
        $q_table = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['q_table']);
        $q_col   = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['q_col']);
        $q_val   = $conn->real_escape_string ?? $_GET['q_val']; // sqlsrv doesn't have real_escape_string

        $q_result = sqlsrv_query(
            $conn,
            "SELECT * FROM [$q_table] WHERE [$q_col] LIKE ?",
            array('%' . $_GET['q_val'] . '%')
        );
        $q_rows = array();

        if ($q_result) {
            while ($r = sqlsrv_fetch_array($q_result, SQLSRV_FETCH_ASSOC)) {
                $q_rows[] = $r;
            }
        }

        sqlsrv_close($conn);
        render_table($q_rows, "Results from $q_table where $q_col LIKE '{$_GET['q_val']}'");
    }
    ?>
    <?php endif; ?>

</div>
<?php endif; ?>

</div><!-- end .container -->

<script>
function validate_search() {
    var emp_id = document.getElementById('search_id').value;
    if (emp_id == '' || emp_id <= 0) {
        alert('Please enter a valid Employee ID before searching.');
        return false;
    }
    return true;
}

function validate_emp_form() {
    var sal = document.getElementsByName('sal')[0].value;
    var nm  = document.getElementsByName('ename')[0].value;
    if (nm.trim() == '') { alert('Employee name cannot be empty.'); return false; }
    if (parseFloat(sal) < 0) { alert('Salary must be a positive value.'); return false; }
    return true;
}

function validate_update_form() {
    var nm  = document.getElementsByName('ename')[0].value;
    var sal = document.getElementsByName('sal')[0].value;
    if (nm.trim() == '') { alert('Name cannot be empty for update.'); return false; }
    if (sal == '' || parseFloat(sal) < 0) { alert('Please enter a valid salary.'); return false; }
    return true;
}

function confirm_delete() {
    var id = document.getElementsByName('del_empno')[0].value;
    if (id == '') { alert('Please enter an Employee ID to delete.'); return false; }
    return confirm('Are you sure you want to delete Employee #' + id + '? This cannot be undone.');
}
</script>
</body>
</html>