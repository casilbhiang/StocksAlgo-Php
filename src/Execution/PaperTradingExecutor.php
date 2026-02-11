<?php

namespace StocksAlgo\Execution;

class PaperTradingExecutor implements OrderExecutor
{
    private string $stateFile;
    private array $state;
    private $pdo;

    public function __construct(float $initialBalance = 10000.0, string $stateFile = 'data/paper_trading_state.json')
    {
        $this->stateFile = __DIR__ . '/../../' . $stateFile;
        // Connect to DB if available
        $this->connectDb();
        $this->loadState($initialBalance);
    }

    private function connectDb()
    {
        $dbUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
        if ($dbUrl) {
            try {
                $opts = parse_url($dbUrl);
                // Render DATABASE_URL format: postgres://user:pass@host:port/dbname
                $dsn = "pgsql:host={$opts['host']};port={$opts['port']};dbname=" . ltrim($opts['path'], '/');
                $user = $opts['user'];
                $pass = $opts['pass'];

                $this->pdo = new \PDO($dsn, $user, $pass);
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // Initialize tables if needed
                $sql = "CREATE TABLE IF NOT EXISTS trades (
                    id SERIAL PRIMARY KEY,
                    symbol VARCHAR(10) NOT NULL,
                    side VARCHAR(4) NOT NULL,
                    price DECIMAL(10, 4) NOT NULL,
                    quantity DECIMAL(10, 4) NOT NULL,
                    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    pnl DECIMAL(10, 4) DEFAULT NULL,
                    strategy VARCHAR(50) DEFAULT NULL
                )";
                $this->pdo->exec($sql);

            } catch (\Exception $e) {
                echo "DB Connection Failed: " . $e->getMessage() . "\n";
            }
        }
    }

    private function loadState(float $initialBalance): void
    {
        if (file_exists($this->stateFile)) {
            $json = file_get_contents($this->stateFile);
            $this->state = json_decode($json, true) ?? [];
        }

        // If no JSON (fresh restart), try to recover Balance from DB
        if (!isset($this->state['balance']) && $this->pdo) {
            echo "Restoring state from DB...\n";
            try {
                $stmt = $this->pdo->query("SELECT SUM(pnl) as total_pnl FROM trades WHERE pnl IS NOT NULL");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalPnl = $row['total_pnl'] ?? 0;
                $this->state['balance'] = $initialBalance + $totalPnl;
                echo "Restored Balance: " . $this->state['balance'] . "\n";
            } catch (\Exception $e) {
                $this->state['balance'] = $initialBalance;
            }
        } elseif (!isset($this->state['balance'])) {
            $this->state['balance'] = $initialBalance;
        }

        if (!isset($this->state['trades'])) {
            $this->state['trades'] = [];
        }

        if (!isset($this->state['positions'])) {
            $this->state['positions'] = [];
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

        // Normalize position state
        if (isset($this->state['positions'][$symbol]) && is_numeric($this->state['positions'][$symbol])) {
            $this->state['positions'][$symbol] = [
                'quantity' => (int) $this->state['positions'][$symbol],
                'avg_price' => $price
            ];
        }

        if ($side === 'BUY') {
            if ($this->state['balance'] < $totalCost) {
                return ['status' => 'failed', 'reason' => 'Insufficient funds'];
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
            'pnl' => $pnl,
            'balance_after' => $this->state['balance']
        ];

        $this->state['trades'][] = $trade;

        // Save to DB
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO trades (symbol, side, price, quantity, pnl, timestamp) VALUES (:sym, :side, :price, :qty, :pnl, :ts)");
                $stmt->execute([
                    ':sym' => $symbol,
                    ':side' => $side,
                    ':price' => $price,
                    ':qty' => $quantity,
                    ':pnl' => $pnl,
                    ':ts' => $trade['timestamp']
                ]);
            } catch (\Exception $e) {
                echo "DB Save Failed: " . $e->getMessage() . "\n";
            }
        }

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
