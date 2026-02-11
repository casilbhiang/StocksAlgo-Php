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

        if ($side === 'BUY') {
            if ($this->state['balance'] < $totalCost) {
                // In a real bot triggers an error, for now we just log warning and proceed (or return empty)
                // Let's enforce the limit
                // throw new \Exception("Insufficient funds.");
                // For robustness, let's just allow it but maybe go negative or return error?
                // Let's being strict.
                if ($this->state['balance'] < $totalCost) {
                    return ['status' => 'failed', 'reason' => 'Insufficient funds'];
                }
            }
            $this->state['balance'] -= $totalCost;
            
            if (!isset($this->state['positions'][$symbol])) {
                $this->state['positions'][$symbol] = 0;
            }
            $this->state['positions'][$symbol] += $quantity;
            
        } elseif ($side === 'SELL') {
            // Check if we have shares to sell?
            $currentQty = $this->state['positions'][$symbol] ?? 0;
             if ($currentQty < $quantity) {
                 return ['status' => 'failed', 'reason' => 'Insufficient shares to sell'];
             }
            
            $this->state['balance'] += $totalCost;
            $this->state['positions'][$symbol] -= $quantity;
        }

        $trade = [
            'id' => uniqid(),
            'timestamp' => date('Y-m-d H:i:s'),
            'symbol' => $symbol,
            'side' => $side,
            'quantity' => $quantity,
            'price' => $price,
            'total' => $totalCost,
            'balance_after' => $this->state['balance']
        ];

        $this->state['trades'][] = $trade;
        $this->saveState();

        echo "[PaperTrade] Executed $side $quantity shares of $symbol @ $$price. New Balance: $" . number_format($this->state['balance'], 2) . "\n";

        return ['status' => 'filled', 'trade' => $trade];
    }

    public function getBalance(): float
    {
        return $this->state['balance'];
    }
    
    public function getPosition(string $symbol): int 
    {
        return $this->state['positions'][$symbol] ?? 0;
    }
}
