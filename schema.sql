-- Create table for storing trades
CREATE TABLE IF NOT EXISTS trades (
    id SERIAL PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    side VARCHAR(4) NOT NULL, -- BUY or SELL
    price DECIMAL(10, 4) NOT NULL,
    quantity DECIMAL(10, 4) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pnl DECIMAL(10, 4) DEFAULT NULL, -- Only for SELL trades
    strategy VARCHAR(50) DEFAULT NULL
);

-- Index for faster queries
CREATE INDEX IF NOT EXISTS idx_trades_symbol ON trades(symbol);
CREATE INDEX IF NOT EXISTS idx_trades_timestamp ON trades(timestamp);
