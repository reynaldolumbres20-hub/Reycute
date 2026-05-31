@echo off
REM Pumunta sa folder ng ngrok
cd /d D:\OneDrive\Documents\Desktop\bosing\ngrok-v3-stable-windows-386

REM Start ngrok sa port 80
start ngrok.exe http 80

REM Optional: Open default browser sa login page
start "" "https://stereotyped-kingsley-unwrongfully.ngrok-free.dev/payrollsystem/login.php"