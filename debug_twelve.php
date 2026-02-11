<?php

require __DIR__ . '/vendor/autoload.php';

use StocksAlgo\Data\TwelveDataDataProvider;
use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['TWELVE_DATA_API_KEY'] ?? die("API Key not found in .env\n");
$symbol = 'IBM';
$timeframe = '5min';

echo "Debug: Fetching Twelve Data for $symbol ($timeframe)...\n";

try {
    $provider = new TwelveDataDataProvider($apiKey);
    
    // Test fetch
    $end = new DateTimeImmutable('now');
    $start = $end->modify('-1 day');

    echo "Requesting bars...\n";
    $bars = $provider->getBars($symbol, $timeframe, $start, $end);

    echo "Bars count: " . count($bars) . "\n";
    
    if (count($bars) > 0) {
        $first = $bars[0];
        echo "First Bar: Time: {$first->timestamp->format('Y-m-d H:i')}, Close: {$first->close}\n";
    } else {
        echo "No bars found.\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
