<?php

namespace StocksAlgo\Data;

interface MarketDataProvider {
    /**
     * @return Bar[]
     */
    public function getBars(string $symbol, string $timeframe, \DateTimeImmutable $start, \DateTimeImmutable $end): array;
}
