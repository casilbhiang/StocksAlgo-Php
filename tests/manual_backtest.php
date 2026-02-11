<?php

require __DIR__ . '/../vendor/autoload.php';

use StocksAlgo\Data\MockDataProvider;
use StocksAlgo\Strategy\PinBarStrategy;
use StocksAlgo\Backtest\Backtester;

echo "Starting Backtest...\n";

// Setup
$dataProvider = new MockDataProvider();
$strategy = new PinBarStrategy(wickRatio: 2.0);
$backtester = new Backtester($dataProvider, $strategy, initialCapital: 10000);

// Run
$results = $backtester->run('TEST', '5m', new DateTimeImmutable(), new DateTimeImmutable());

// Output Results
echo "Initial Capital: 10000\n";
echo "Final Capital: " . $results['finalCapital'] . "\n";
echo "Total Trades: " . $results['totalTrades'] . "\n";

foreach ($results['trades'] as $trade) {
    echo "--------------------------------------------------\n";
    echo "Trade: {$trade->type} {$trade->quantity} shares @ {$trade->entryPrice}\n";
    echo "Entry: {$trade->entryTime->format('Y-m-d H:i')}\n";
    echo "Exit: {$trade->exitTime->format('Y-m-d H:i')} @ {$trade->exitPrice}\n";
    echo "PnL: {$trade->pnl}\n";
}
