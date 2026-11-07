<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$sql = file_get_contents(__DIR__ . '/../database/migrate_phase_6.sql');
get_db()->exec($sql);
echo "Phase 6 migration applied successfully.\n";

