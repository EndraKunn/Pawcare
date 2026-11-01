@echo off
setlocal EnableExtensions
cd /d "%~dp0"
echo ==================================================
echo   PawCare - Setup (Windows)
echo ==================================================

where php >nul 2>nul
if errorlevel 1 goto :nophp
where composer >nul 2>nul
if errorlevel 1 goto :nocomposer

php -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);"
if errorlevel 1 goto :oldphp

php -r "$m=[]; foreach(['pdo_sqlite','sqlite3','mbstring','openssl','fileinfo','ctype','tokenizer','xml','curl'] as $e){ if(!extension_loaded($e)) $m[]=$e; } if($m){ echo 'Ekstensi PHP belum aktif: '.implode(', ',$m).PHP_EOL; exit(1);} exit(0);"
if errorlevel 1 goto :noext

echo.
echo [1/6] Menginstal dependency dengan Composer...
call composer install --no-interaction --prefer-dist
if errorlevel 1 goto :fail

echo.
echo [2/6] Menyiapkan file .env...
if not exist ".env" copy ".env.example" ".env" >nul
if errorlevel 1 goto :fail

findstr /b /c:"APP_KEY=base64:" ".env" >nul
if errorlevel 1 (
  echo       Membuat APP_KEY...
  php artisan key:generate --force
  if errorlevel 1 goto :fail
)

echo.
echo [3/6] Menyiapkan database SQLite...
findstr /b /c:"DB_CONNECTION=sqlite" ".env" >nul
if errorlevel 1 goto :otherdb
if not exist "database\database.sqlite" type nul > "database\database.sqlite"
set "DBSIZE=0"
for %%A in ("database\database.sqlite") do set "DBSIZE=%%~zA"
goto :migrate

:otherdb
echo       DB_CONNECTION bukan sqlite - pastikan database MySQL sudah dibuat dan .env sudah benar.
set "DBSIZE=unknown"

:migrate
echo.
echo [4/6] Menjalankan migration...
php artisan migrate --force
if errorlevel 1 goto :fail

echo.
echo [5/6] Data demo...
if "%DBSIZE%"=="0" (
  php artisan db:seed --force
  if errorlevel 1 goto :fail
) else (
  echo       Database sudah berisi data - seeder dilewati agar data tidak terhapus.
  echo       Untuk mengisi ulang data demo dari nol, jalankan: php artisan migrate:fresh --seed
)

echo.
echo [6/6] Membuat storage link...
php artisan storage:link >nul 2>nul
if errorlevel 1 echo       (storage link sudah ada atau tidak dapat dibuat - dapat diabaikan)

echo.
echo ==================================================
echo   Setup selesai. Jalankan start-demo.bat
echo   Akun demo: lihat README.md (password: password)
echo   PERINGATAN: ganti akun/password demo sebelum dipublikasikan!
echo ==================================================
pause
exit /b 0

:nophp
echo [ERROR] PHP tidak ditemukan di PATH. Pasang PHP 8.2 atau lebih baru ^(lihat README bagian 4^).
pause
exit /b 1
:nocomposer
echo [ERROR] Composer tidak ditemukan di PATH. Pasang dari https://getcomposer.org/download/
pause
exit /b 1
:oldphp
echo [ERROR] Versi PHP harus 8.2 atau lebih baru. Versi Anda:
php -v
pause
exit /b 1
:noext
echo [ERROR] Aktifkan ekstensi di php.ini ^(hapus tanda ; di depan extension=...^), lalu jalankan ulang setup.bat.
pause
exit /b 1
:fail
echo.
echo [ERROR] Langkah terakhir gagal ^(kode %errorlevel%^). Baca pesan error di atas.
pause
exit /b 1
