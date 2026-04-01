<?php
function get_connection() {
    $serverName = "DESKTOP-7J4COFK\SQLEXPRESS";
    $connectionOptions = array(
        "Database"               => "EmpDeptDB",
        "Uid"                    => "",
        "PWD"                    => "",
        "TrustServerCertificate" => true
    );
    $conn = sqlsrv_connect($serverName, $connectionOptions);
    if ($conn === false) {
        die("Connection failed: " . print_r(sqlsrv_errors(), true));
    }
    return $conn;
}
?>