<?php
require_once __DIR__ . '/config.php';

class DatabaseConnection {
    private static ?PDO $connection = null;

    public static function getConnection(): PDO {
        if (self::$connection === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            try {
                self::$connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Fallback común en XAMPP: usuario root sin contraseña
                $isAuthError = (string)$e->getCode() === '1045' || stripos($e->getMessage(), 'Access denied') !== false;
                if ($isAuthError && DB_USER === 'root' && DB_PASS !== '') {
                    self::$connection = new PDO($dsn, 'root', '', $options);
                } else {
                    throw $e;
                }
            }
        }
        return self::$connection;
    }
}

?>


