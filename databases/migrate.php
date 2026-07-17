<?php

$host = "127.0.0.1";
$db   = "dynamic_db";
$user = "root";
$pass = "@masomoA";

$pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass
);

$migrationDir = __DIR__ . "/migrations";

$files = glob($migrationDir . "/*.sql");

sort($files);

foreach ($files as $file) {

    $migration = basename($file);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM migrations WHERE migration=?"
    );

    $stmt->execute([$migration]);

    if ($stmt->fetchColumn() > 0) {

        echo "Skipped: $migration\n";

        continue;
    }

    $sql = file_get_contents($file);

    $pdo->exec($sql);

    $stmt = $pdo->prepare(
        "INSERT INTO migrations(migration) VALUES(?)"
    );

    $stmt->execute([$migration]);

    echo "Migrated: $migration\n";
}

echo "Done.\n";