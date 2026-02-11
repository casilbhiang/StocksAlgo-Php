#!/bin/bash
set -e

echo "🚀 Starting Stocks Algo (Render Mode)..."

# 1. Fetch Data (if we have an API key)
if [ -n "$TWELVE_DATA_API_KEY" ]; then
    echo "📊 Fetching latest market data for QQQ..."
    # Default to QQQ 5min for now, or use END vars
    SYMBOL=${SYMBOL:-QQQ}
    TIMEFRAME=${TIMEFRAME:-5min}
    
    php collect_data.php $SYMBOL $TIMEFRAME
fi

# 2. Train Brain
echo "🧠 Training AI Model..."
# Check if data exists before training
if [ -f "ml/data/${SYMBOL}_${TIMEFRAME}.csv" ]; then
    python3 ml/train.py "ml/data/${SYMBOL}_${TIMEFRAME}.csv"
else
    echo "⚠️ No training data found. Skipping training."
fi

# 3. Start Bot
echo "🤖 Starting Trading Bot..."
php bot.php $SYMBOL $TIMEFRAME
