<?php
/**
 * Launcher API Key Generator
 * Generates daily rotating API keys for testing
 */

$secret = 'o6LDOB3E2Nv4mYPM'; // Must match server_settings.launcher_secret

// Get dates
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Generate keys
$key_today = hash_hmac('sha256', $today, $secret);
$key_yesterday = hash_hmac('sha256', $yesterday, $secret);
$key_tomorrow = hash_hmac('sha256', $tomorrow, $secret);

echo "===========================================\n";
echo "Launcher API Key Generator\n";
echo "===========================================\n\n";

echo "SECRET: $secret\n\n";

echo "-------------------------------------------\n";
echo "YESTERDAY ($yesterday):\n";
echo "Key: $key_yesterday\n\n";

echo "-------------------------------------------\n";
echo "TODAY ($today): ✅ USE THIS\n";
echo "Key: $key_today\n\n";

echo "-------------------------------------------\n";
echo "TOMORROW ($tomorrow):\n";
echo "Key: $key_tomorrow\n\n";

echo "===========================================\n";
echo "USAGE:\n";
echo "===========================================\n\n";

echo "curl -X POST http://emulsis-realm.my.id/api/launcher/status \\\n";
echo "  -H \"X-API-Key: $key_today\"\n\n";

echo "curl -X POST http://emulsis-realm.my.id/api/launcher/request-launch \\\n";
echo "  -H \"X-API-Key: $key_today\" \\\n";
echo "  -H \"Content-Type: application/json\" \\\n";
echo "  -d '{\"hwid\":\"TEST123\",\"client_hash\":\"ABC456\"}'\n\n";
