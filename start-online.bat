@echo off
rem Jalankan e-DEMA + tunnel publik Cloudflare (link bisa dibagikan).
rem Link hanya hidup selama laptop menyala dan jendela ini tidak ditutup.
cd /d "%~dp0"

if not exist ".runtime\php\php.exe" (
  echo PHP portabel tidak ditemukan di .runtime\php
  pause
  exit /b 1
)
if not exist "tools\bin\cloudflared.exe" (
  echo cloudflared.exe tidak ditemukan di tools\bin
  pause
  exit /b 1
)

echo Menjalankan server di http://127.0.0.1:8080 ...
start "e-DEMA server" /min ".runtime\php\php.exe" -S 0.0.0.0:8080 -t "%~dp0" "%~dp0router.php"

timeout /t 3 >nul
echo.
echo Membuat link publik... (salin URL trycloudflare.com yang muncul di bawah)
echo.
"tools\bin\cloudflared.exe" tunnel --url http://127.0.0.1:8080 --no-autoupdate
