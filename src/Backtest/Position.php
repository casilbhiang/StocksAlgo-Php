<?php

namespace StocksAlgo\Backtest;

use StocksAlgo\Data\Bar;

class Position {
    public function __construct(
        public readonly string $symbol,
        public readonly string $type, // 'LONG' or 'SHORT'
        public readonly float $entryPrice,
        public readonly int $quantity,
        public readonly \DateTimeImmutable $entryTime,
        public readonly float $stopLoss,
        public readonly float $takeProfit
    ) {}

    public function getCurrentPnl(float $currentPrice): float {
        $diff = $currentPrice - $this->entryPrice;
        if ($this->type === 'SHORT') {
            $diff = -$diff;
        }
        return $diff * $this->quantity;
    }
}
