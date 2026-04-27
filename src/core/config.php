<?php
// Definir la zona horaria predeterminada
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Definimos las variables de conexión capacitacion
define("DB_HOST", "localhost");
define("DB_PORT", "5432");
define("DB_NAME", "bd_name");
define("DB_USER", "user_name");
define("DB_PASSWORD", "password");

//FHIR SERVER ENDPOINT
define("APP_FHIR_SERVER", "https://fhir-conectaton.mspbs.gov.py/fhir");
