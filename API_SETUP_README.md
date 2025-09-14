BioAttend Fingerprint Verification API Setup
Overview
This system uses a Flask API server to handle fingerprint verification requests from the PHP web application.

Prerequisites
- Python 3.8 or higher
- pip (Python package installer)
- MySQL server running with the `bioattend_db_system` database
- XAMPP or similar web server for PHP

Quick Start

Option 1: Windows Batch File (Recommended)
1. Double-click `start_api_server.bat`
2. The script will automatically:
   - Check Python installation
   - Install required packages
   - Start the Flask server

Option 2: PowerShell Script
1. Right-click `start_api_server.ps1`
2. Select "Run with PowerShell"
3. Follow the prompts

Option 3: Manual Setup
1. Open Command Prompt or PowerShell
2. Navigate to the project directory
3. Run: `pip install -r requirements.txt`
4. Run: `python finger.py`

Server Information
- URL: http://127.0.0.1:5000
- Health Check: http://127.0.0.1:5000/health
- Verify Endpoint: http://127.0.0.1:5000/verify

Troubleshooting

Common Issues

1. "API request failed with code: 0"
- Cause: Flask server is not running
- Solution: Start the API server using one of the methods above

2. "Module not found" errors
- Cause: Python packages not installed
- Solution: Run `pip install -r requirements.txt`

3. "Database connection failed"
- Cause: MySQL server not running or wrong credentials
- Solution: 
  - Start MySQL in XAMPP
  - Check database credentials in `connection/config.php`

4. Port 5000 already in use
- Cause: Another application using port 5000
- Solution: 
  - Change port in `finger.py` (line 312)
  - Update PHP code in `identify.php` (line 18)

Verification Steps
1. Start the Flask server
2. Open http://127.0.0.1:5000/health in browser
3. Should see: `{"status": "healthy", ...}`
4. Try fingerprint verification in the web app

File Structure
- `finger.py` - Main Flask API server
- `requirements.txt` - Python dependencies
- `start_api_server.bat` - Windows startup script
- `start_api_server.ps1` - PowerShell startup script
- `connection/config.php` - Database configuration

API Endpoints

POST /verify
Verifies a fingerprint against enrolled students.

Request: Multipart form with fingerprint image
Response: JSON with match results

GET /health
Server health check and configuration info.

POST/GET /clear_temp
Clears temporary fingerprint files.


