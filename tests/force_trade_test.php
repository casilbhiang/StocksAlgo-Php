<?php

require __DIR__ . '/../vendor/autoload.php';

use StocksAlgo\Execution\PaperTradingExecutor;

echo "Forcing a test trade...\n";

$executor = new PaperTradingExecutor();
$symbol = 'PLTR';
$price = 135.50; // Arbitrary test price
$quantity = 5;

echo "Current Balance: $" . number_format($executor->getBalance(), 2) . "\n";

// Execute Order
$result = $executor->executeOrder($symbol, 'BUY', $quantity, $price);

print_r($result);

echo "New Balance: $" . number_format($executor->getBalance(), 2) . "\n";
echo "Done. Check your dashboard.\n";
