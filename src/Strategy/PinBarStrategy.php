<?php

namespace StocksAlgo\Strategy;

use StocksAlgo\Data\Bar;
use StocksAlgo\Backtest\Position;

class PinBarStrategy implements Strategy {
    private float $wickRatio;

    public function __construct(float $wickRatio = 2.0) {
        $this->wickRatio = $wickRatio;
    }

    public function onBar(Bar $bar, ?Position $currentPosition): ?string {
        // If we already have a position, we might implement exit logic here
        // For now, let's assume the Backtester handles simple TP/SL exits,
        // or we exit on the opposite signal.
        
        $bodySize = abs($bar->close - $bar->open);
        $totalRange = $bar->high - $bar->low;
        $upperWick = $bar->high - max($bar->open, $bar->close);
        $lowerWick = min($bar->open, $bar->close) - $bar->low;

        // Avoid division by zero
        if ($bodySize == 0) $bodySize = 0.0001;
        
        // Long Signal (Trailing Tail / Hammer)
        // Lower wick is significantly larger than body (and upper wick is small)
        if ($lowerWick > ($bodySize * $this->wickRatio) && $upperWick < $lowerWick * 0.5) {
            return 'BUY';
        }

        // Short Signal (Whicking Top / Shooting Star)
        // Upper wick is significantly larger than body (and lower wick is small)
        if ($upperWick > ($bodySize * $this->wickRatio) && $lowerWick < $upperWick * 0.5) {
            return 'SELL';
        }

        return null;
    }
}
