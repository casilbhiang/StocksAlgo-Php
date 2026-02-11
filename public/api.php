<?php

use StocksAlgo\Data\AlphaVantageDataProvider;
use StocksAlgo\Strategy\PinBarStrategy;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

// Enable CORS for local development
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
ini_set('display_errors', 0); // Prevent PHP warnings from breaking JSON

// Load .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$symbol = $_GET['symbol'] ?? 'IBM';
$timeframe = $_GET['timeframe'] ?? '5min';

use StocksAlgo\Data\TwelveDataDataProvider;

$apiKey = $_ENV['TWELVE_DATA_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'API Key missing (TWELVE_DATA_API_KEY)']);
    exit;
}

use StocksAlgo\Strategy\VolumeMAStrategy;

// ...

try {
    $provider = new TwelveDataDataProvider($apiKey);
    // $strategy = new PinBarStrategy();
    $strategy = new VolumeMAStrategy(20, 2.0);

    // We'll ask for last 2 days of data for 5/15m charts
    $end = new DateTimeImmutable('now');
    $start = $end->modify('-2 days');

    $bars = $provider->getBars($symbol, $timeframe, $start, $end);

    $chartData = [];
    $signals = [];

    $history = [];
    foreach ($bars as $bar) {
        $chartData[] = [
            'x' => $bar->timestamp->getTimestamp() * 1000,
            'o' => $bar->open,
            'h' => $bar->high,
            'l' => $bar->low,
            'c' => $bar->close
        ];

        // Check for Signal with History
        $signal = $strategy->onBar($bar, null, $history);
        $history[] = $bar;

        if ($signal) {
            $signals[] = [
                'x' => $bar->timestamp->getTimestamp() * 1000,
                'type' => $signal,
                'price' => $bar->close,
                'description' => $signal === 'BUY' ? 'Long Entry (Pin Bar)' : 'Short Entry (Shooting Star)'
            ];
        }
    }


    // Read Paper Trading State
    $portfolio = [
        'balance' => 10000.0,
        'equity' => 10000.0,
        'position' => 0,
        'unrealized_pnl' => 0.0
    ];

    $stateFile = __DIR__ . '/../data/paper_trading_state.json';
    if (file_exists($stateFile)) {
        $state = json_decode(file_get_contents($stateFile), true);
        if ($state) {
            $portfolio['balance'] = $state['balance'] ?? 10000.0;
            $portfolio['positions'] = $state['positions'] ?? [];
            $portfolio['trades'] = $state['trades'] ?? [];

            // Calculate Equity (Cash + Value of all positions)

            // Verify we have bars before accessing end()
            $currentPrice = 0.0;
            if (!empty($bars)) {
                $currentPrice = end($bars)->close;
            } else {
                // Fallback if no bars returned (API issue)
                // Maybe use last known price from state if available? or 0.
                $currentPrice = 0.0;
            }

            $rawPos = $state['positions'][$symbol] ?? 0;
            $quantity = 0;
            if (is_array($rawPos)) {
                $quantity = $rawPos['quantity'];
                // Use avg_price as fallback if currentPrice is 0 (no data)
                if ($currentPrice == 0.0) {
                    $currentPrice = $rawPos['avg_price'];
                }
            } else {
                $quantity = (int) $rawPos;
            }

            $portfolio['position'] = $quantity;

            if ($quantity != 0) {
                $currentValue = $quantity * $currentPrice;
                $portfolio['market_value'] = $currentValue;
            }

            // Approximate Equity = Balance + (Current Symbol Value)
            $portfolio['equity'] = $portfolio['balance'] + ($quantity * $currentPrice);
        }
    }

    echo json_encode([
        'symbol' => $symbol,
        'timeframe' => $timeframe,
        'bars' => $chartData,
        'signals' => $signals,
        'portfolio' => $portfolio
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    // Log error for debugging
    file_put_contents(__DIR__ . '/../api_error.log', date('[Y-m-d H:i:s] ') . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => $e->getMessage()]);
}
