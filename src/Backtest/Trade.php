<?php

namespace StocksAlgo\Backtest;

class Trade {
    public function __construct(
        public readonly string $symbol,
        public readonly string $type, // 'LONG' or 'SHORT'
        public readonly float $entryPrice,
        public readonly float $exitPrice,
        public readonly int $quantity,
        public readonly \DateTimeImmutable $entryTime,
        public readonly \DateTimeImmutable $exitTime,
        public readonly float $pnl
    ) {}
}
