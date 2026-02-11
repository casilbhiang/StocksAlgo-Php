<?php

namespace StocksAlgo\Data;

use GuzzleHttp\Client;
use DateTimeImmutable;

class TwelveDataDataProvider implements MarketDataProvider {
    private Client $client;
    private string $apiKey;
    private string $cacheDir;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.twelvedata.com/',
            'timeout'  => 10.0,
            'verify'   => __DIR__ . '/../../cacert.pem',
        ]);
        $this->cacheDir = __DIR__ . '/../../data/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function getBars(string $symbol, string $timeframe, DateTimeImmutable $start, DateTimeImmutable $end): array {
        // Simple caching to avoid spamming the API
        // Cache key based on symbol and timeframe. We'll cache for 5 minutes.
        $cacheFile = $this->cacheDir . '/' . $symbol . '_' . $timeframe . '.json';
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 300)) { // 300s = 5m cache
            $data = json_decode(file_get_contents($cacheFile), true);
            return $this->mapResponseToBars($data);
        }

        $response = $this->client->get('time_series', [
            'query' => [
                'symbol' => $symbol,
                'interval' => $timeframe, // 1min, 5min, 15min
                'apikey' => $this->apiKey,
                'outputsize' => 100 // Limit to 100 to be safe
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        if (isset($data['code']) && $data['code'] >= 400) {
            throw new \Exception("Twelve Data API Error: " . ($data['message'] ?? 'Unknown error'));
        }

        if (!isset($data['values'])) {
             // If we have cache (even old), return it on error? No, fail loud for now.
             throw new \Exception("Twelve Data API Error: No values returned. " . json_encode($data));
        }

        // Save to cache
        file_put_contents($cacheFile, json_encode($data));

        return $this->mapResponseToBars($data);
    }

    private function mapResponseToBars(array $data): array {
        $bars = [];
        // Twelve Data returns oldest last, so we start from the end?
        // Actually values are usually sorted new -> old.
        // We need them old -> new for the algo.
        
        $values = array_reverse($data['values']); // Reverse to get chronological order

        foreach ($values as $candle) {
            $bars[] = new Bar(
                (float)$candle['open'],
                (float)$candle['high'],
                (float)$candle['low'],
                (float)$candle['close'],
                (float)$candle['volume'],
                new DateTimeImmutable($candle['datetime'])
            );
        }
        return $bars;
    }
}
