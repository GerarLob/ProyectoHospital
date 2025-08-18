<?php
// Configuración principal de la aplicación

// Datos de conexión a MySQL (XAMPP)
const DB_HOST = 'localhost';
const DB_NAME = 'hospital_db';
const DB_USER = 'root';
const DB_PASS = 'hospital2025';

// URL base (ajústala si instalas en subcarpeta)
const APP_BASE_PATH = '/';

// Zona horaria
date_default_timezone_set('America/Guatemala');

// Mostrar errores en desarrollo (desactiva en producción)
ini_set('display_errors', '1');
error_reporting(E_ALL);

?>


