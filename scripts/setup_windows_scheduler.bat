@echo off
echo === LEAVE SYNC WINDOWS TASK SCHEDULER SETUP ===
echo.

REM Get current directory
set "CURRENT_DIR=%~dp0"
set "PROJECT_ROOT=%CURRENT_DIR%.."
set "CRON_SCRIPT=%CURRENT_DIR%cron_leave_sync.php"
set "LOG_FILE=%PROJECT_ROOT%\logs\cron_sync.log"

echo Project Root: %PROJECT_ROOT%
echo Cron Script: %CRON_SCRIPT%
echo Log File: %LOG_FILE%
echo.

REM Check if cron script exists
if not exist "%CRON_SCRIPT%" (
    echo ERROR: Cron script not found at %CRON_SCRIPT%
    pause
    exit /b 1
)

echo ✅ Cron script found
echo.

REM Create log directory if it doesn't exist
if not exist "%PROJECT_ROOT%\logs" (
    mkdir "%PROJECT_ROOT%\logs"
    echo ✅ Created log directory
) else (
    echo ✅ Log directory exists
)

echo.
echo === TESTING CRON SCRIPT ===
echo Running: php "%CRON_SCRIPT%"
echo.

REM Test the script
php "%CRON_SCRIPT%"
if %ERRORLEVEL% neq 0 (
    echo ❌ Cron script test failed with error code: %ERRORLEVEL%
    pause
    exit /b 1
)

echo.
echo ✅ Cron script test successful!
echo.

echo === WINDOWS TASK SCHEDULER SETUP ===
echo.
echo To set up automatic sync using Windows Task Scheduler:
echo.
echo 1. Open Task Scheduler (taskschd.msc)
echo 2. Click "Create Basic Task"
echo 3. Name: "Leave Sync Automatic"
echo 4. Description: "Automatic sync of leave requests from Google Sheets"
echo 5. Trigger: Daily (or as needed)
echo 6. Action: Start a program
echo 7. Program: php
echo 8. Arguments: "%CRON_SCRIPT%"
echo 9. Start in: "%PROJECT_ROOT%"
echo 10. Check "Run whether user is logged on or not"
echo.
echo === ALTERNATIVE: MANUAL TASK CREATION ===
echo.
echo You can also create the task using this command:
echo.
echo schtasks /create /tn "Leave Sync Automatic" /tr "php \"%CRON_SCRIPT%\"" /sc minute /mo 15 /ru SYSTEM
echo.
echo This will run every 15 minutes.
echo.

echo === VERIFICATION ===
echo After setting up the task:
echo 1. Wait for the scheduled time
echo 2. Check log file: %LOG_FILE%
echo 3. Look for 'CRON:' entries in the log
echo.

echo === MANUAL TEST ===
echo To test manually, run:
echo php "%CRON_SCRIPT%"
echo.

echo === SETUP COMPLETE ===
pause
