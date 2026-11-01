@echo off
setlocal EnableExtensions
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
  echo [ERROR] PHP tidak ditemukan di PATH. Lihat README.md.
  pause
  exit /b 1
)
if not exist "vendor\autoload.php" (
  echo [ERROR] Dependency belum terpasang. Jalankan setup.bat terlebih dahulu.
  pause
  exit /b 1
)
if not exist ".env" (
  echo [ERROR] File .env belum ada. Jalankan setup.bat terlebih dahulu.
  pause
  exit /b 1
)

echo ==================================================
echo   PawCare berjalan. Buka di browser:
echo   http://127.0.0.1:8000
echo   Tekan Ctrl+C untuk menghentikan server.
echo ==================================================
php artisan serve --host=127.0.0.1 --port=8000
if errorlevel 1 (
  echo.
  echo [ERROR] Server gagal berjalan. Mungkin port 8000 dipakai; coba: php artisan serve --port=8080
  pause
  exit /b 1
)
