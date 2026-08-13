<?php
if (!defined('FORUM_RUNNING')) { http_response_code(403); die('Forbidden'); }
// lib/db.php

class DB {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);

                // MySQL-Zeitzone an PHP angleichen, damit NOW() = PHP date()
                try {
                    $tz = date_default_timezone_get();
                    // MySQL akzeptiert benannte Zeitzonen (z.B. "Europe/Berlin") nur wenn
                    // die TZ-Tabellen geladen sind; ansonsten Offset (z.B. "+02:00")
                    $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P');
                    self::$instance->exec("SET time_zone = '$offset'");
                } catch (Exception $e) {
                    // Best-effort, kein Beinbruch
                }
            } catch (PDOException $e) {
                die('Datenbankverbindung fehlgeschlagen: ' . htmlspecialchars($e->getMessage()));
            }
        }
        return self::$instance;
    }
}

function db_fetch(string $sql, array $params = []) {
    $stmt = DB::get()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function db_fetchAll(string $sql, array $params = []): array {
    $stmt = DB::get()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_execute(string $sql, array $params = []): int {
    $stmt = DB::get()->prepare($sql);
    $stmt->execute($params);
    return (int) DB::get()->lastInsertId();
}

function db_count(string $sql, array $params = []): int {
    $stmt = DB::get()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}
