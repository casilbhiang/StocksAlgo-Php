import torch
import torch.nn as nn
import numpy as np
import pandas as pd
import pandas_ta as ta
import sys
import os
import joblib
import json

# Configuration
base_dir = os.path.dirname(os.path.abspath(__file__))
model_file = os.path.join(base_dir, 'model.pth')
scaler_features_file = os.path.join(base_dir, 'scaler_features.save')
scaler_target_file = os.path.join(base_dir, 'scaler_target.save')

lookback = 60
hidden_size = 64
input_size = 4 # close, rsi, macd_h, volume

# Load Scalers
try:
    scaler = joblib.load(scaler_features_file)
    target_scaler = joblib.load(scaler_target_file)
except FileNotFoundError:
    print("Error: Scaler file not found. Train model first.")
    sys.exit(1)

# LSTM Model Definition (Must match train.py)
class QuantPredictor(nn.Module):
    def __init__(self, input_size, hidden_size, output_size=1):
        super(QuantPredictor, self).__init__()
        self.lstm = nn.LSTM(input_size, hidden_size, batch_first=True, num_layers=2, dropout=0.2)
        self.fc = nn.Linear(hidden_size, output_size)

    def forward(self, x):
        out, _ = self.lstm(x)
        out = self.fc(out[:, -1, :]) 
        return out

# Load Model
model = QuantPredictor(input_size=input_size, hidden_size=hidden_size)
try:
    model.load_state_dict(torch.load(model_file))
    model.eval()
except FileNotFoundError:
    print("Error: Model file not found. Train model first.")
    sys.exit(1)

# Read Input Data (JSON passed as argument)
# Input should be a JSON list of DICTS: [{'open':1, 'close':2...}, ...]
if len(sys.argv) < 2:
    print("Error: No input data provided.")
    sys.exit(1)

try:
    arg = sys.argv[1]
    
    # Check if argument is a file path
    if os.path.exists(arg) and os.path.isfile(arg):
        with open(arg, 'r') as f:
            raw_data = json.load(f)
    else:
        # Fallback to direct string
        raw_data = json.loads(arg)
    
    # Create DataFrame
    df = pd.DataFrame(raw_data)
    
    # Validations
    if len(df) < lookback + 20: # Need extra for indicators
        # print("HOLD (Not enough data for indicators)")
        # Just try anyway, indicators might be NaN at start
        pass

    # Feature Engineering (Must match train.py EXACTLY)
    df.ta.rsi(length=14, append=True)
    df.ta.macd(fast=12, slow=26, signal=9, append=True)
    
    # Feature Columns
    # 'MACDh_12_26_9' is the histogram
    feature_cols = ['close', 'RSI_14', 'MACDh_12_26_9', 'volume']
    
    # Drop rows with NaNs (created by indicators)
    # But we need the LAST 'lookback' rows to be valid.
    # If the last row is valid, we are good.
    
    # Check if last row has NaNs
    if df.iloc[-1][feature_cols].isnull().any():
         print("HOLD (Indicators not ready)")
         sys.exit(0)

    # Take the last 'lookback' rows
    recent_df = df.iloc[-lookback:]
    
    # Extract values
    data_values = recent_df[feature_cols].values.astype(float)
    
    if len(data_values) < lookback:
         print("HOLD (Not enough valid data)")
         sys.exit(0)

    # Normalize
    data_scaled = scaler.transform(data_values)
    
    # Convert to Tensor
    X_input = torch.from_numpy(data_scaled).float().unsqueeze(0) # (1, lookback, input_size)
    
    # Predict
    with torch.no_grad():
        prediction_scaled = model(X_input).numpy()
    
    # Denormalize Output (Target was 'close')
    predicted_price = target_scaler.inverse_transform(prediction_scaled)[0][0]
    
    current_price = raw_data[-1]['close']
    
    # Logic
    threshold_pct = 0.0005 # 0.05% change expectation
    
    change = (predicted_price - current_price) / current_price
    
    # Confidence Calculation
    # Cap confidence at 100% if change is 5x the threshold
    confidence = min(abs(change) / (threshold_pct * 5), 1.0) * 100
    
    signal = "HOLD"
    if change > threshold_pct:
        signal = "BUY"
    elif change < -threshold_pct:
        signal = "SELL"
        
    # Get Indicator Values (Last row)
    rsi_val = df['RSI_14'].iloc[-1]
    macd_val = df['MACDh_12_26_9'].iloc[-1]

    output = {
        "signal": signal,
        "confidence": round(float(confidence), 2),
        "predicted_price": round(float(predicted_price), 2),
        "current_price": round(float(current_price), 2),
        "rsi": round(float(rsi_val), 2) if not pd.isna(rsi_val) else 0,
        "macd": round(float(macd_val), 4) if not pd.isna(macd_val) else 0
    }
    
    print(json.dumps(output))

except Exception as e:
    error_msg = f"Error: {str(e)}"
    # print(e) # Debug
    
    # Fallback JSON so PHP doesn't crash
    fallback = {
        "signal": "HOLD",
        "confidence": 0,
        "predicted_price": 0,
        "current_price": 0,
        "rsi": 0,
        "macd": 0,
        "error": error_msg
    }
    print(json.dumps(fallback))
    sys.exit(1)
