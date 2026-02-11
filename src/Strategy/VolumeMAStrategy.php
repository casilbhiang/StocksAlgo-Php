<?php

namespace StocksAlgo\Strategy;

use StocksAlgo\Data\Bar;
use StocksAlgo\Backtest\Position;

class VolumeMAStrategy implements Strategy
{
    private int $smaPeriod;
    private float $volumeMultiplier;

    public function __construct(int $smaPeriod = 20, float $volumeMultiplier = 2.0)
    {
        $this->smaPeriod = $smaPeriod;
        $this->volumeMultiplier = $volumeMultiplier;
    }

    public function onBar(Bar $bar, ?Position $currentPosition, array $previousBars = []): ?string
    {
        // Need enough history for SMA + Volume Avg
        if (count($previousBars) < $this->smaPeriod) {
            return null;
        }

        // 1. Calculate Average Volume
        $sumVol = 0;
        $sumClose = 0;

        $subset = array_slice($previousBars, -$this->smaPeriod);
        foreach ($subset as $b) {
            $sumVol += $b->volume;
            $sumClose += $b->close;
        }

        $avgVol = $sumVol / count($subset);
        $sma = $sumClose / count($subset);

        // 2. Volume Check: "Big Volume"
        if ($bar->volume < ($avgVol * $this->volumeMultiplier)) {
            return null;
        }

        // 3. Logic: Breaking the Moving Average

        // SHORT: Red Candle AND Price Breaks Below SMA (Open > SMA and Close < SMA) or just Close < SMA
        // User said: "if got big volume sell, can start to short too if it is breaking the moving average"
        // "Big volume sell" usually means it's a Down candle (Red) with high volume.

        $isRedCandle = $bar->close < $bar->open;
        $isGreenCandle = $bar->close > $bar->open;

        // SHORT Signal
        // If it's a big sell-off (Red) AND it crosses below SMA (or is below SMA significantly?)
        // Strict "Break": Open was above, Close is below.
        if ($isRedCandle) {
            if ($bar->open > $sma && $bar->close < $sma) {
                return 'SELL';
            }
            // Also consider if it's just a huge drop ALREADY below SMA?
            // "Breaking" usually implies the cross. Let's stick to the cross for now.
        }

        // LONG Signal (Symmetric)
        // Big Green Candle + Breaks Above SMA
        if ($isGreenCandle) {
            if ($bar->open < $sma && $bar->close > $sma) {
                return 'BUY';
            }
        }

        return null;
    }
}
