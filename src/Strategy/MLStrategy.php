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

        // Use a temp file to pass data (avoid shell escaping issues on Windows)
        $tempFile = sys_get_temp_dir() . '/ml_input_' . uniqid() . '.json';
        file_put_contents($tempFile, $jsonInput);

        // 3. Call Python
        $cmd = 'python "' . $this->pythonScriptPath . '" "' . $tempFile . '"';

        // Execute
        $output = shell_exec($cmd);

        // Clean up
        @unlink($tempFile);

        if ($output === null) {
            // Error executing
            echo "[MLStrategy] Error executing python script.\n";
            return null;
        }

        $output = trim($output);

        // Python prints: "BUY (101.20)" or "HOLD ..."
        // Parse the output
        $result = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "[MLStrategy] Invalid JSON from Python: $output\n";
            return null;
        }

        // Save "Brain Dump" for Dashboard
        $memoryFile = __DIR__ . '/../../data/ai_memory.json';
        file_put_contents($memoryFile, json_encode($result));

        // Return Signal
        $signal = $result['signal'];
        echo "[MLStrategy] Brain: $signal ({$result['confidence']}%) | RSI: {$result['rsi']}\n";

        if ($signal === 'BUY' || $signal === 'SELL') {
            return $signal;
        }
        return null;
    }
}
