<?php

namespace StocksAlgo\Backtest;

use StocksAlgo\Data\MarketDataProvider;
use StocksAlgo\Strategy\Strategy;
use StocksAlgo\Data\Bar;

class Backtester {
    private float $capital;
    private array $trades = [];
    private ?Position $currentPosition = null;

    public function __construct(
        private MarketDataProvider $dataProvider,
        private Strategy $strategy,
        float $initialCapital = 10000.0
    ) {
        $this->capital = $initialCapital;
    }

    public function run(string $symbol, string $timeframe, \DateTimeImmutable $start, \DateTimeImmutable $end): array {
        $bars = $this->dataProvider->getBars($symbol, $timeframe, $start, $end);
        
        foreach ($bars as $bar) {
            $this->processBar($bar);
        }

        return [
            'trades' => $this->trades,
            'finalCapital' => $this->capital,
            'totalTrades' => count($this->trades),
            // Add more metrics like Win Rate, Drawdown etc.
        ];
    }

    private function processBar(Bar $bar) {
        // 1. Check for exit rules on existing position (SL/TP) - Simplified for now
        if ($this->currentPosition) {
            // Logic to check if low hit SL or high hit TP could go here
        }

        // 2. Get signal from strategy
        $signal = $this->strategy->onBar($bar, $this->currentPosition);

        // 3. Execute Signal
        if ($signal === 'BUY') {
            if ($this->currentPosition && $this->currentPosition->type === 'SHORT') {
                $this->closePosition($bar->close, $bar->timestamp);
            }
            if (!$this->currentPosition) {
                $this->openPosition($bar->close, 'LONG', $bar->timestamp);
            }
        } elseif ($signal === 'SELL') {
            if ($this->currentPosition && $this->currentPosition->type === 'LONG') {
                $this->closePosition($bar->close, $bar->timestamp);
            }
            if (!$this->currentPosition) {
                $this->openPosition($bar->close, 'SHORT', $bar->timestamp);
            }
        }
    }

    private function openPosition(float $price, string $type, \DateTimeImmutable $time) {
        $quantity = floor($this->capital / $price); // Simple all-in for now
        if ($quantity <= 0) return;

        $this->currentPosition = new Position(
            symbol: 'TEST', // Should come from somewhere
            type: $type,
            entryPrice: $price,
            quantity: $quantity,
            entryTime: $time,
            stopLoss: 0, // Not implemented yet
            takeProfit: 0 // Not implemented yet
        );
        
        // Deduct capital (simplified, assuming margin/cash usage)
        // For accurate P&L tracking, we just track the trade result.
    }

    private function closePosition(float $price, \DateTimeImmutable $time) {
        if (!$this->currentPosition) return;

        $pnl = $this->currentPosition->getCurrentPnl($price);
        $this->capital += $pnl;

        $this->trades[] = new Trade(
            symbol: $this->currentPosition->symbol,
            type: $this->currentPosition->type,
            entryPrice: $this->currentPosition->entryPrice,
            exitPrice: $price,
            quantity: $this->currentPosition->quantity,
            entryTime: $this->currentPosition->entryTime,
            exitTime: $time,
            pnl: $pnl
        );

        $this->currentPosition = null;
    }
}
