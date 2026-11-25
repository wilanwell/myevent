<?php

namespace MyEvent\Tests\Support;

use mysqli;
use RuntimeException;

/** Creates the throwaway test database from database/schema.sql. */
final class TestDatabase
{
    /** Drop and recreate $name, then load the schema. */
    public static function rebuild(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+_test$/', $name)) {
            throw new RuntimeException("Refusing to rebuild '$name': test databases must end in _test.");
        }
        self::loadDbSettingsFromDotEnv();

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli(
            (string) (getenv('DB_HOST') ?: 'localhost'),
            (string) (getenv('DB_USERNAME') ?: 'root'),
            (string) (getenv('DB_PASSWORD') ?: ''),
            '',
            (int) (getenv('DB_PORT') ?: 3306)
        );
        $conn->set_charset('utf8mb4');
        $conn->query("DROP DATABASE IF EXISTS `$name`");
        $conn->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $conn->select_db($name);

        $schema = file_get_contents(__DIR__ . '/../../database/schema.sql');
        $conn->multi_query($schema);
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        $conn->close();
    }

    /** Reuse the database host and credentials from .env when they are not set in the environment. */
    private static function loadDbSettingsFromDotEnv(): void
    {
        $file = __DIR__ . '/../../.env';
        if (!is_readable($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (in_array($key, ['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'], true) && getenv($key) === false) {
                putenv($key . '=' . trim($value, "\"'"));
            }
        }
    }
}
