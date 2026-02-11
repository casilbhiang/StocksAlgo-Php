<?php

namespace StocksAlgo\Strategy;

use StocksAlgo\Data\Bar;
use StocksAlgo\Backtest\Position;

interface Strategy {
    /**
     * Process a new bar and return a signal or null.
     * Use a simple string for now: 'BUY', 'SELL', or null.
     * In a real app, this might return a Signal object.
     */
    public function onBar(Bar $bar, ?Position $currentPosition): ?string;
}
