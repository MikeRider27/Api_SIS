<?php
include('config.php');

function getConnection(){
	$dbconn = null;
	try {
	    $conn_string = "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME;
		$dbconn = new PDO($conn_string, DB_USER, DB_PASSWORD);
		$dbconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	} catch (PDOException $e) {
	    error_log('No se ha podido conectar: ' . $e->getMessage());
	}

    return $dbconn;
}

function closeConnection($dbconn){
	$dbconn = null; // Esto cierra la conexión PDO
}

function getConnectionRVE(){
	$dbconn = null;
	try {
	    $conn_string = "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME_RVE;
		$dbconn = new PDO($conn_string, DB_USER, DB_PASSWORD);
		$dbconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	} catch (PDOException $e) {
	    error_log('No se ha podido conectar: ' . $e->getMessage());
	}

    return $dbconn;
}

function closeConnectionRVE($dbconn){
	$dbconn = null; // Esto cierra la conexión PDO
}

function getConnectionFHIR(){
	$dbconn = null;
	try {
	    $conn_string = "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME_FHIR;
		$dbconn = new PDO($conn_string, DB_USER, DB_PASSWORD);
		$dbconn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);	
	} catch (PDOException $e) {
	    error_log('No se ha podido conectar: ' . $e->getMessage());
	}

    return $dbconn;
}

function closeConnectionFHIR($dbconn){
	$dbconn = null; // Esto cierra la conexión PDO
}

?>
