@echo off
echo Starting BioAttend Fingerprint API Server...
echo.

REM Check if Python is installed
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.8+ from https://python.org
    pause
    exit /b 1
)

REM Check if pip is available
pip --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: pip is not available
    echo Please ensure pip is installed with Python
    pause
    exit /b 1
)

echo Installing/updating required packages...
pip install -r requirements.txt

echo.
echo Starting Flask server on http://127.0.0.1:5000
echo Press Ctrl+C to stop the server
echo.

python finger_clean.py

pause
