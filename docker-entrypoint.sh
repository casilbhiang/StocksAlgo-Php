#!/bin/bash
set -e

echo "🚀 Starting Stocks Algo (Render Mode)..."

# 1. Fetch Data (if we have an API key)
if [ -n "$TWELVE_DATA_API_KEY" ]; then
    echo "📊 Fetching latest market data for QQQ..."
    # Default to BTC/USD 5min for now, or use ENV vars
    SYMBOL=${SYMBOL:-BTC/USD}
    TIMEFRAME=${TIMEFRAME:-5min}
    
    # Run slightly longer initial data collection
    php collect_data.php $SYMBOL $TIMEFRAME
fi

# 2. Train Brain
echo "🧠 Training AI Model..."
# Check if data exists before training
if [ -f "ml/data/${SYMBOL}_${TIMEFRAME}.csv" ]; then
    # python3 ml/train.py "ml/data/${SYMBOL}_${TIMEFRAME}.csv"
    echo "Skipping training on startup to speed up boot time..."
else
    echo "⚠️ No training data found. Skipping training."
fi

# 3. Start Bot in Background
echo "🤖 Starting Trading Bot (Background) for $SYMBOL..."
# Remove redirection so we see logs in Render Dashboard
php bot.php "$SYMBOL" "$TIMEFRAME" &

# 4. Start Web Server (Foreground)
# Render requires a web server to bind to a port to keep the service "Healthy" on Free Tier.
PORT=${PORT:-8000}
echo "🌍 Starting Dashboard on port $PORT..."
php -S 0.0.0.0:$PORT -t public/
