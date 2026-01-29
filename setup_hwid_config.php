<?php

/**
 * Setup HWID Validation Configuration
 * 
 * This script initializes the max_hwids_per_ip setting in database.
 * Run once after deploying HWID validation feature.
 * 
 * Usage: php setup_hwid_config.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Launcher\ServerSetting;

echo "Setting up HWID validation configuration...\n\n";

// Set max HWIDs per IP (default: 3)
$setting = ServerSetting::updateOrCreate(
    ['key' => 'max_hwids_per_ip'],
    [
        'value' => '3',
        'description' => 'Maximum unique hardware IDs (devices) allowed per IP address in 24 hours'
    ]
);

echo "✅ Created/Updated: max_hwids_per_ip = 3\n";
echo "   This allows up to 3 different devices per IP address.\n\n";

echo "Configuration options:\n";
echo "  1 = Very strict (single PC per IP)\n";
echo "  3 = Moderate (family with 2-3 PCs) ← RECOMMENDED\n";
echo "  5 = Lenient (internet cafe friendly)\n";
echo " 10 = Very lenient (almost no restriction)\n\n";

echo "To change the limit, update server_settings table:\n";
echo "UPDATE server_settings SET value = '5' WHERE key = 'max_hwids_per_ip';\n\n";

echo "✅ HWID validation is now active!\n";
