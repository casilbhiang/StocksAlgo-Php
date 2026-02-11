<?php

require __DIR__ . '/vendor/autoload.php';

use StocksAlgo\Data\TwelveDataDataProvider;
use StocksAlgo\Strategy\PinBarStrategy;
use StocksAlgo\Strategy\VolumeMAStrategy;
use StocksAlgo\Execution\PaperTradingExecutor;
use Dotenv\Dotenv;

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Configuration
$symbol = $argv[1] ?? 'AAPL';
$timeframe = $argv[2] ?? '5min';
$apiKey = $_ENV['TWELVE_DATA_API_KEY'] ?? die("API Key missing.\n");

echo "Starting Paper Trading Bot for $symbol ($timeframe)...\n";
echo "Press Ctrl+C to stop.\n";

$provider = new TwelveDataDataProvider($apiKey);
// $strategy = new VolumeMAStrategy(20, 2.0);
echo "Strategy: Machine Learning (LSTM)\n";
$strategy = new StocksAlgo\Strategy\MLStrategy();
$executor = new PaperTradingExecutor(); // Defaults to $10,000

$lastProcessedTime = 0;

while (true) {
    try {
        $now = new DateTimeImmutable('now');
        echo "[" . $now->format('H:i:s') . "] Checking market...\n";

        // Fetch just enough data for the strategy
        // Quant Upgrade: Need more history for indicators (RSI/MACD require warm-up)
        // Fetch 24 hours of 5min bars (~288 bars)
        $start = $now->modify('-24 hours');
        $bars = $provider->getBars($symbol, $timeframe, $start, $now);

        if (empty($bars)) {
            echo "No data received.\n";
            sleep(60);
            continue;
        }

        // Get the most recent completed bar
        $lastBar = end($bars);
        $barTime = $lastBar->timestamp->getTimestamp();

        if ($barTime > $lastProcessedTime) {
            // New bar detected
            echo "Processing bar: " . $lastBar->timestamp->format('Y-m-d H:i') . " (Close: {$lastBar->close})\n";

            // Check Strategy
            $currentPosition = $executor->getPosition($symbol);

            // FIX: Pass $bars (history) to the strategy!
            $signal = $strategy->onBar($lastBar, null, $bars);

            if ($signal) {
                echo "SIGNAL DETECTED: $signal\n";

                // Simple Position Sizing: Trade 10 shares fixed
                $quantity = 10;

                // Logic: 
                // If BUY signal and we have 0 shares -> Buy
                // If SELL signal and we have > 0 shares -> Sell (Exit)
                // (Or if Strategy is Shorting, that's different. PinBarStrategy returns BUY/SELL).

                if ($signal === 'BUY') {
                    if ($currentPosition == 0) {
                        $executor->executeOrder($symbol, 'BUY', $quantity, $lastBar->close);
                    } else {
                        echo "Signal BUY ignored: Already holding $currentPosition shares.\n";
                    }
                } elseif ($signal === 'SELL') {
                    if ($currentPosition > 0) {
                        $executor->executeOrder($symbol, 'SELL', $currentPosition, $lastBar->close); // Sell all
                    } else {
                        echo "Signal SELL ignored: No position to sell.\n";
                    }
                }
            } else {
                echo "No signal.\n";
            }

            $lastProcessedTime = $barTime;
        } else {
            echo "No new bar yet.\n";
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // Wait before next check
    // 5min timeframe -> check every 60s is fine
    echo "Sleeping 60s...\n";
    sleep(60);
}
