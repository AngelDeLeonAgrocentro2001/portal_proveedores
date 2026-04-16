<?php
// database/DatabaseSAP.php

class DatabaseSAP {

    public function CONEXION_HANA($db_name = 'GT_AGROCENTRO_2016') {
        $driver = "HDBODBC";
        $servername = "192.168.1.9:30015";
        $username = "SAPDBA";
        $password = "B1Adminh";

        $conn = odbc_connect(
            "Driver=$driver;ServerNode=$servername;Database=$db_name;", 
            $username, 
            $password, 
            SQL_CUR_USE_ODBC
        );

        if (!$conn) {
            $error = odbc_errormsg();
            error_log("Error al conectar a SAP HANA: " . $error);
            throw new Exception("Error al conectar a SAP HANA: " . $error);
        }

        return $conn;
    }
}