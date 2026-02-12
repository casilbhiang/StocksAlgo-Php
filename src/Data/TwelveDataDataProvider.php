<?php

namespace StocksAlgo\Data;

use GuzzleHttp\Client;
use DateTimeImmutable;

class TwelveDataDataProvider implements MarketDataProvider
{
    private Client $client;
    private string $apiKey;
    private string $cacheDir;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new Client([
            'base_uri' => 'https://api.twelvedata.com/',
            'timeout' => 10.0,
            'verify' => __DIR__ . '/../../cacert.pem',
        ]);
        $this->cacheDir = __DIR__ . '/../../data/cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function getBars(string $symbol, string $timeframe, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        // Simple caching to avoid spamming the API
        // Cache key based on symbol and timeframe. We'll cache for 55 seconds (just under a minute).
        $safeSymbol = str_replace(['/', ':'], '_', $symbol);
        $cacheFile = $this->cacheDir . '/' . $safeSymbol . '_' . $timeframe . '.json';

        // Check cache (TTL 55s)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 55)) {
            $json = file_get_contents($cacheFile);
            $data = json_decode($json, true);
            if ($data && isset($data['values'])) {
                return $this->mapResponseToBars($data);
            }
        }

        try {
            $response = $this->client->get('time_series', [
                'query' => [
                    'symbol' => $symbol,
                    'interval' => $timeframe,
                    'apikey' => $this->apiKey,
                    'outputsize' => 100
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['code']) && $data['code'] >= 400) {
                // Forward the error message clearly
                throw new \Exception("Twelve Data API Error: " . ($data['message'] ?? 'Unknown error'));
            }

            if (!isset($data['values'])) {
                throw new \Exception("Twelve Data API Error: No values returned. " . json_encode($data));
            }

            // Save to cache
            file_put_contents($cacheFile, json_encode($data));

            return $this->mapResponseToBars($data);

        } catch (\Exception $e) {
            // If API fails (e.g. Rate Limit) but we have STALE cache, return that instead of crashing!
            if (file_exists($cacheFile)) {
                $data = json_decode(file_get_contents($cacheFile), true);
                if ($data)
                    return $this->mapResponseToBars($data);
            }
            throw $e;
        }
    }

    private function mapResponseToBars(array $data): array
    {
        $bars = [];
        $values = array_reverse($data['values']);

        foreach ($values as $candle) {
            $bars[] = new Bar(
                (float) $candle['open'],
                (float) $candle['high'],
                (float) $candle['low'],
                (float) $candle['close'],
                (float) ($candle['volume'] ?? 0), // Fix: Default to 0 if missing
                new DateTimeImmutable($candle['datetime'])
            );
        }
        return $bars;
    }
}
