@echo off
REM Build upload zip for https://2deal.my
set ROOT=%~dp0
for /f "delims=" %%I in ('powershell -NoProfile -Command "[Environment]::GetFolderPath('Desktop')"') do set DESK=%%I
set OUT=%DESK%\2deal-my-deploy.zip
set OUT2=c:\xampp\htdocs\2deal-my-deploy.zip
set TMPZIP=%TEMP%\2deal-my-deploy.zip

if exist "%OUT%" del /f /q "%OUT%"
if exist "%OUT2%" del /f /q "%OUT2%"
if exist "%TMPZIP%" del /f /q "%TMPZIP%"

cd /d "%ROOT%"

if not exist uploads mkdir uploads
if not exist application\cache mkdir application\cache
if not exist application\logs mkdir application\logs

copy /y application\config\database.php application\config\database.php.localbak >nul
copy /y application\config\database.php.server application\config\database.php >nul

tar.exe -a -cf "%TMPZIP%" --exclude=frontend/amercereactjs --exclude=frontend1 --exclude=.git --exclude=.claude --exclude=.github --exclude=deploy-keys --exclude=node_modules --exclude=*.zip --exclude=application/config/database.php.localbak application assets css database fonts frontend images js system webfonts postman scripts uploads .htaccess index.php web.config UPLOAD_TO_2DEAL.txt README.md SHOPKART_SETUP.md fix_images.php deploy-webhook.php package-lock.json .editorconfig .gitignore

copy /y application\config\database.php.localbak application\config\database.php >nul
del /f /q application\config\database.php.localbak >nul

copy /y "%TMPZIP%" "%OUT%"
copy /y "%TMPZIP%" "%OUT2%"
echo.
echo Created:
echo   %OUT%
echo   %OUT2%
dir "%OUT%"
