<?php

namespace StocksAlgo\Strategy;

use StocksAlgo\Data\Bar;
use StocksAlgo\Backtest\Position;

class MLStrategy implements Strategy
{
    private string $pythonScriptPath;

    public function __construct()
    {
        // Use absolute path to be safe
        $this->pythonScriptPath = __DIR__ . '/../../ml/predict.py';
    }

    public function onBar(Bar $bar, ?Position $currentPosition, array $previousBars = []): ?string
    {
        // 1. We need history to form a sequence (e.g. 60 bars)
        // RSI+MACD needs ~100 bars safe warmup
        if (count($previousBars) < 100) {
            return null;
        }

        // 2. Prepare Data (Full OHLCV for Indicators)
        // Python needs a list of dicts: [{'open': 1, 'close': 2, ...}, ...]
        $data = [];
        // Take last 200 bars if available, to ensure python has enough to calc indicators
        $subset = array_slice($previousBars, -200);

        foreach ($subset as $b) {
            $data[] = [
                'open' => $b->open,
                'high' => $b->high,
                'low' => $b->low,
                'close' => $b->close,
                'volume' => $b->volume,
            ];
        }

        $jsonInput = json_encode($data);

        // 3. Call Python
        // Escape the JSON string for shell argument
        $cmd = 'python "' . $this->pythonScriptPath . '" ' . escapeshellarg($jsonInput);

        // Execute
        $output = shell_exec($cmd);

        if ($output === null) {
            // Error executing
            echo "[MLStrategy] Error executing python script.\n";
            return null;
        }

        $output = trim($output);

        // Python prints: "BUY (101.20)" or "HOLD ..."
        // Parse the output
        if (str_starts_with($output, 'BUY')) {
            return 'BUY';
        } elseif (str_starts_with($output, 'SELL')) {
            return 'SELL';
        }

        echo "[MLStrategy] Python says: $output\n";
        return null;
    }
}
