<?php

namespace StocksAlgo\Data;

class MockDataProvider implements MarketDataProvider {
    public function getBars(string $symbol, string $timeframe, \DateTimeImmutable $start, \DateTimeImmutable $end): array {
        $bars = [];
        $currentPrice = 100.0;
        $currentTime = $end->modify('-500 minutes'); // Start 500 mins ago
        
        for ($i = 0; $i < 100; $i++) {
            $baseVol = rand(1000, 5000);
            
            // Random movement
            $open = $currentPrice;
            $close = $open + (rand(-50, 50) / 100);
            $high = max($open, $close) + (rand(0, 50) / 100);
            $low = min($open, $close) - (rand(0, 50) / 100);
            
            // Inject a Pin Bar (Long Signal) occasionally (e.g., at index 80)
            if ($i === 80) {
                $close = $open + 0.1;
                $low = $open - 2.0; // Long tail
                $high = $close + 0.1;
            }

            // Inject a Shooting Star (Short Signal) occasionally (e.g., at index 90)
            if ($i === 90) {
                $close = $open - 0.1;
                $high = $open + 2.0; // Long wick
                $low = $close - 0.1;
            }

            $bars[] = new Bar(
                (float)$open,
                (float)$high,
                (float)$low,
                (float)$close,
                (float)$baseVol,
                $currentTime
            );
            
            $currentPrice = $close;
            $currentTime = $currentTime->modify('+5 minutes');
        }

        return $bars;
    }
}
