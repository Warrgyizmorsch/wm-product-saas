<?php

// Paths to delete
$filesToDelete = [
    __DIR__ . '/app/Domains/HRMS/Models/RetroLopAdjustment.php',
    __DIR__ . '/database/migrations/2026_08_21_100004_create_retro_lop_adjustments_table.php',
    __DIR__ . '/app/Domains/HRMS/Models/PayrollRetroAdjustment.php',
    __DIR__ . '/database/migrations/2026_08_21_100004_create_payroll_retro_adjustments_table.php',
];

echo "Cleaning up old renamed files...\n";
foreach ($filesToDelete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "Deleted: " . basename($file) . "\n";
        } else {
            echo "Failed to delete: " . basename($file) . "\n";
        }
    } else {
        echo "File already deleted or does not exist: " . basename($file) . "\n";
    }
}
echo "Done! You can delete this script now.\n";
