<?php

namespace StocksAlgo\Strategy;

use StocksAlgo\Data\Bar;
use StocksAlgo\Backtest\Position;

class PinBarStrategy implements Strategy
{
    private float $wickRatio;

    public function __construct(float $wickRatio = 2.0)
    {
        $this->wickRatio = $wickRatio;
    }

    public function onBar(Bar $bar, ?Position $currentPosition, array $previousBars = []): ?string
    {
        // Volume Filter: Needs > 1.5x Average of last 20 bars
        if (count($previousBars) >= 20) {
            $sumVol = 0;
            // Get last 20 bars
            $subset = array_slice($previousBars, -20);
            foreach ($subset as $b) {
                $sumVol += $b->volume;
            }
            $avgVol = $sumVol / count($subset);

            // If current volume is not significantly higher, ignore
            if ($bar->volume < ($avgVol * 1.5)) {
                return null;
            }
        }

        // ... existing pattern logic ...

        $bodySize = abs($bar->close - $bar->open);
        $totalRange = $bar->high - $bar->low;
        $upperWick = $bar->high - max($bar->open, $bar->close);
        $lowerWick = min($bar->open, $bar->close) - $bar->low;

        // Avoid division by zero
        if ($bodySize == 0)
            $bodySize = 0.0001;

        // Long Signal (Trailing Tail / Hammer)
        if ($lowerWick > ($bodySize * $this->wickRatio) && $upperWick < $lowerWick * 0.5) {
            return 'BUY';
        }

        // Short Signal (Whicking Top / Shooting Star)
        if ($upperWick > ($bodySize * $this->wickRatio) && $lowerWick < $upperWick * 0.5) {
            return 'SELL';
        }

        return null;
    }
}
