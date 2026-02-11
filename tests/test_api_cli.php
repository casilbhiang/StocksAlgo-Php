<?php
// Simulate GET request
$_GET['symbol'] = 'IBM';
$_GET['timeframe'] = '5min'; // Correct format

// Capture output
ob_start();
require __DIR__ . '/public/api.php';
$output = ob_get_clean();

echo "API Output Length: " . strlen($output) . "\n";
echo "First 100 chars: " . substr($output, 0, 100) . "\n";

$json = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "JSON Decode Error: " . json_last_error_msg() . "\n";
    echo "Full Output:\n$output\n";
} else {
    echo "JSON Valid.\n";
    if (isset($json['error'])) {
        echo "API returned error: " . $json['error'] . "\n";
    } else {
        echo "API returned success. Bars: " . count($json['bars']) . "\n";
    }
}
