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
// use StocksAlgo\Data\MockDataProvider;

// ...

$apiKey = $_ENV['TWELVE_DATA_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo json_encode(['error' => 'API Key missing (TWELVE_DATA_API_KEY)']);
    exit;
}

try {
    $provider = new TwelveDataDataProvider($apiKey);
    // $provider = new MockDataProvider(); // Fallback to Mock Data to unblock user
    $strategy = new PinBarStrategy();

    // Fetch last 100 candles (approx) or define a range
    // For simplicity, let's fetch 'full' (which the provider does by default) but maybe limit the output?
    // AlphaVantage 'full' returns a lot. Let's start with defaults.

    // We'll ask for last 2 days of data for 5/15m charts
    $end = new DateTimeImmutable('now');
    $start = $end->modify('-2 days');

    $bars = $provider->getBars($symbol, $timeframe, $start, $end);

    $chartData = [];
    $signals = [];

    foreach ($bars as $bar) {
        $chartData[] = [
            'x' => $bar->timestamp->getTimestamp() * 1000, // JS timestamp
            'o' => $bar->open,
            'h' => $bar->high,
            'l' => $bar->low,
            'c' => $bar->close
        ];

        // Check for Signal
        // Passing null for position means we are checking for entry signals only
        $signal = $strategy->onBar($bar, null);

        if ($signal) {
            $signals[] = [
                'x' => $bar->timestamp->getTimestamp() * 1000,
                'type' => $signal,
                'price' => $bar->close, // Signal price (or close price of the bar)
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

            // Calculate Equity (Cash + Value of all positions)
            // Note: In a real app we need Realtime Price for ALL held symbols.
            // Here we only have price for $symbol. 
            // We will just calculate equity contribution from Current Symbol + Cash + (Value of other symbols at UNKNOWN price? - Let's ignore others for now or assume cost basis)

            $currentPrice = end($bars)->close;
            $quantity = $state['positions'][$symbol] ?? 0;
            $portfolio['position'] = $quantity;

            if ($quantity != 0) {
                $currentValue = $quantity * $currentPrice;
                // We don't have avg cost in simple state yet, so 'unrealized_pnl' is hard.
                // Let's just return current Value of this position.
                $portfolio['market_value'] = $currentValue;
            }

            // Approximate Equity = Balance + (Current Symbol Value)
            // (Ignoring other symbols for this simple demo)
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

} catch (Exception $e) {
    http_response_code(500);
    // Log error for debugging
    file_put_contents(__DIR__ . '/../api_error.log', date('[Y-m-d H:i:s] ') . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => $e->getMessage()]);
}
