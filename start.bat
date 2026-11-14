@echo off
rem Jalankan e-DEMA dengan PHP portabel (mode demo SQLite).
cd /d "%~dp0"

if not exist ".runtime\php\php.exe" (
  echo PHP portabel tidak ditemukan di .runtime\php
  echo Gunakan XAMPP/Laragon lalu buka folder ini melalui Apache.
  pause
  exit /b 1
)

echo e-DEMA - http://127.0.0.1:8080/
echo Akses dari HP / komputer lain di Wi-Fi yang sama: http://IP-komputer-ini:8080/
start "" http://127.0.0.1:8080/
".runtime\php\php.exe" -S 0.0.0.0:8080 -t "%~dp0" "%~dp0router.php"
