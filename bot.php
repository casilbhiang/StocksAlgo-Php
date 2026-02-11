<?php

require __DIR__ . '/vendor/autoload.php';

use StocksAlgo\Data\TwelveDataDataProvider;
use StocksAlgo\Strategy\PinBarStrategy;
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
$strategy = new PinBarStrategy();
$executor = new PaperTradingExecutor(); // Defaults to $10,000

$lastProcessedTime = 0;

while (true) {
    try {
        $now = new DateTimeImmutable('now');
        echo "[" . $now->format('H:i:s') . "] Checking market...\n";

        // Fetch just enough data for the strategy
        // PinBar only needs the current candle, but let's fetch a few to be safe/consistent
        $start = $now->modify('-2 hours');
        $bars = $provider->getBars($symbol, $timeframe, $start, $now);

        if (empty($bars)) {
            echo "No data received.\n";
            sleep(60);
            continue;
        }

        // Get the most recent completed bar? 
        // Twelve Data returns closed bars usually, or realtime. 
        // If we are trading '5min', we usually wait for the bar to close.
        // Let's assume the last bar in the array is the most recent one.
        $lastBar = end($bars);
        $barTime = $lastBar->timestamp->getTimestamp();

        if ($barTime > $lastProcessedTime) {
            // New bar detected
            echo "Processing bar: " . $lastBar->timestamp->format('Y-m-d H:i') . " (Close: {$lastBar->close})\n";
            
            // Check Strategy
            // We pass 'null' for position if we just want entry signals
            // Or we check our current position from executor
            $currentPosition = $executor->getPosition($symbol);
            
            // Allow strategy to know if we are long/short? 
            // The current PinBarStrategy interface `onBar` takes `Position $position`.
            // We might need to construct a Position object or update the interface.
            // For now, let's just pass null to get raw signals, 
            // and handle "don't buy if already long" here in the bot logic.
            
            $signal = $strategy->onBar($lastBar, null);

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
