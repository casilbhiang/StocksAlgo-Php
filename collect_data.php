<?php

require __DIR__ . '/vendor/autoload.php';

use StocksAlgo\Data\TwelveDataDataProvider;
use Dotenv\Dotenv;

// Load .env
$dotenvPath = __DIR__ . '/.env';
if (file_exists($dotenvPath)) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

$apiKey = $_ENV['TWELVE_DATA_API_KEY'] ?? getenv('TWELVE_DATA_API_KEY');
if (!$apiKey)
    die("API Key missing.\n");
$symbol = $argv[1] ?? 'PLTR'; // Default to PLTR
$timeframe = $argv[2] ?? '1h'; // Default to 1h for training (more stable patterns)

echo "Fetching training data for $symbol ($timeframe)...\n";

$provider = new TwelveDataDataProvider($apiKey);

// Fetch extensive history (TwelveData free tier supports up to 5000 usually)
// Let's try to get last 2000 bars.
// TwelveDataDataProvider needs Start/End.
$end = new DateTimeImmutable('now');

// Estimate: 2000 hours ~ 83 days (if 24/7) but market is open ~6.5h/day.
// 2000 bars * 1h / 6.5h/day ~ 300 days.
$start = $end->modify('-1 year');

try {
    $bars = $provider->getBars($symbol, $timeframe, $start, $end);

    if (empty($bars)) {
        die("No data returned. Check API key or Limits.\n");
    }

    echo "Received " . count($bars) . " bars.\n";

    $csvFile = __DIR__ . '/ml/data/' . $symbol . '_' . $timeframe . '.csv';
    $fp = fopen($csvFile, 'w');

    // Header
    fputcsv($fp, ['timestamp', 'open', 'high', 'low', 'close', 'volume']);

    foreach ($bars as $bar) {
        fputcsv($fp, [
            $bar->timestamp->getTimestamp(),
            $bar->open,
            $bar->high,
            $bar->low,
            $bar->close,
            $bar->volume
        ]);
    }

    fclose($fp);
    echo "Saved to $csvFile\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
