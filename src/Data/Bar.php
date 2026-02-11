<?php

namespace StocksAlgo\Data;

class Bar {
    public function __construct(
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly float $volume,
        public readonly \DateTimeImmutable $timestamp
    ) {}
}
