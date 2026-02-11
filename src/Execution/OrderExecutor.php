<?php

namespace StocksAlgo\Execution;

interface OrderExecutor
{
    /**
     * Execute a market order.
     *
     * @param string $symbol The stock symbol (e.g., 'IBM').
     * @param string $side 'BUY' or 'SELL'.
     * @param float $quantity Number of shares.
     * @param float $price Estimated execution price (for paper trading logs).
     * @return array The details of the executed trade.
     */
    public function executeOrder(string $symbol, string $side, float $quantity, float $price): array;

    /**
     * Get the current account balance.
     *
     * @return float
     */
    public function getBalance(): float;
}
