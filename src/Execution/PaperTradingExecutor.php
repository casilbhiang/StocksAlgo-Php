<?php

namespace StocksAlgo\Execution;

class PaperTradingExecutor implements OrderExecutor
{
    private string $stateFile;
    private array $state;

    public function __construct(float $initialBalance = 10000.0, string $stateFile = 'data/paper_trading_state.json')
    {
        $this->stateFile = __DIR__ . '/../../' . $stateFile;
        $this->loadState($initialBalance);
    }

    private function loadState(float $initialBalance): void
    {
        if (file_exists($this->stateFile)) {
            $json = file_get_contents($this->stateFile);
            $this->state = json_decode($json, true) ?? [];
        }

        if (!isset($this->state['balance'])) {
            $this->state['balance'] = $initialBalance;
        }

        if (!isset($this->state['trades'])) {
            $this->state['trades'] = [];
        }

        if (!isset($this->state['positions'])) {
            $this->state['positions'] = []; // Track currently held shares per symbol
        }
    }

    private function saveState(): void
    {
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }

    public function executeOrder(string $symbol, string $side, float $quantity, float $price): array
    {
        $totalCost = $quantity * $price;
        $pnl = null;

        // Normalize position state (handle old int format)
        if (isset($this->state['positions'][$symbol]) && is_numeric($this->state['positions'][$symbol])) {
            $this->state['positions'][$symbol] = [
                'quantity' => (int) $this->state['positions'][$symbol],
                'avg_price' => $price // Assume current price if unknown? Or 0. Let's start fresh or assume current.
            ];
        }

        if ($side === 'BUY') {
            if ($this->state['balance'] < $totalCost) {
                if ($this->state['balance'] < $totalCost) {
                    return ['status' => 'failed', 'reason' => 'Insufficient funds'];
                }
            }
            $this->state['balance'] -= $totalCost;

            if (!isset($this->state['positions'][$symbol])) {
                $this->state['positions'][$symbol] = ['quantity' => 0, 'avg_price' => 0];
            }

            $pos = &$this->state['positions'][$symbol];

            // Weighted Average Price
            if (($pos['quantity'] + $quantity) > 0) {
                $pos['avg_price'] = (($pos['quantity'] * $pos['avg_price']) + ($quantity * $price)) / ($pos['quantity'] + $quantity);
            }
            $pos['quantity'] += $quantity;

        } elseif ($side === 'SELL') {
            $currentPos = $this->state['positions'][$symbol] ?? ['quantity' => 0, 'avg_price' => 0];
            // Handle numeric legacy state just in case
            if (is_numeric($currentPos))
                $currentPos = ['quantity' => $currentPos, 'avg_price' => 0];

            if ($currentPos['quantity'] < $quantity) {
                return ['status' => 'failed', 'reason' => 'Insufficient shares to sell'];
            }

            $this->state['balance'] += $totalCost;

            // Calculate PnL
            $avgEntry = $currentPos['avg_price'];
            $pnl = ($price - $avgEntry) * $quantity;

            $this->state['positions'][$symbol]['quantity'] -= $quantity;

            // If closed completely, remove or reset?
            if ($this->state['positions'][$symbol]['quantity'] <= 0) {
                unset($this->state['positions'][$symbol]);
            }
        }

        $trade = [
            'id' => uniqid(),
            'timestamp' => date('Y-m-d H:i:s'),
            'symbol' => $symbol,
            'side' => $side,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $totalCost,
            'pnl' => $pnl, // Add PnL
            'balance_after' => $this->state['balance']
        ];

        $this->state['trades'][] = $trade;
        $this->saveState();

        echo "[PaperTrade] Executed $side $quantity shares of $symbol @ $$price. PnL: " . ($pnl ? number_format($pnl, 2) : '-') . "\n";

        return ['status' => 'filled', 'trade' => $trade];
    }

    public function getBalance(): float
    {
        return $this->state['balance'];
    }

    public function getPosition(string $symbol): int
    {
        $pos = $this->state['positions'][$symbol] ?? 0;
        if (is_array($pos))
            return $pos['quantity'];
        return (int) $pos;
    }
}
