<?php
declare(strict_types=1);
require_once __DIR__ . '/DBC.php';

function ensureTable(PDO $dbh, string $table): void {
    if ($table === 'H_page_config') {
        $sql = "CREATE TABLE IF NOT EXISTS H_page_config (
            id INT(11) NOT NULL AUTO_INCREMENT,
            conf_name CHAR(50) DEFAULT NULL,
            status INT(11) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
        $dbh->exec($sql);
    }
}

function upsertConfig(PDO $dbh, string $table, string $name, int $value): void {
    $select = $dbh->prepare("SELECT id FROM {$table} WHERE conf_name = :name LIMIT 1");
    $select->execute([':name' => $name]);
    $id = $select->fetchColumn();
    if ($id) {
        $upd = $dbh->prepare("UPDATE {$table} SET status = :val WHERE id = :id");
        $upd->execute([':val' => $value, ':id' => (int)$id]);
    } else {
        $ins = $dbh->prepare("INSERT INTO {$table} (conf_name, status) VALUES (:name, :val)");
        $ins->execute([':name' => $name, ':val' => $value]);
    }
}

try {
    ensureTable($dbh, 'H_page_config');

    // 太宰府（通常）
    upsertConfig($dbh, 'page_config', 'loan_days', 7);
    upsertConfig($dbh, 'page_config', 'max_reservations', 5);
    upsertConfig($dbh, 'page_config', 'start_offset_min', 0);
    upsertConfig($dbh, 'page_config', 'start_offset_max', 14);

    // 博多
    upsertConfig($dbh, 'H_page_config', 'loan_days', 7);
    upsertConfig($dbh, 'H_page_config', 'max_loans', 5);
    upsertConfig($dbh, 'H_page_config', 'start_offset_min', 0);
    upsertConfig($dbh, 'H_page_config', 'start_offset_max', 14);

    echo "Config initialized.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Init error: ' . $e->getMessage();
}


