@echo off

set "FEATURE=%1"
set "NAMESPACE=%2"

if "%FEATURE%"=="" (
    echo Feature name argument missing.
    exit /b 1
)

if "%NAMESPACE%"=="" (
    echo Namespace argument missing.
    exit /b 1
)

if exist "app\Features\%FEATURE%" (
    echo Directory app\Features\%FEATURE% already exists. Aborting.
    exit /b 1
)

composer create-project mirror-and-mountain/meros-feature "app/Features/%FEATURE%" --no-install
if errorlevel 1 exit /b 1

cd "app\Features\%FEATURE%"
if errorlevel 1 exit /b 1

rem Resolve stub path relative to this script
set SCRIPT_DIR=%~dp0
set STUB_PATH=%SCRIPT_DIR%..\stubs\Feature.stub

rem Remove trailing slash from SCRIPT_DIR if it exists
if "%STUB_PATH:~-1%"=="\" set STUB_PATH=%STUB_PATH:~0,-1%

powershell -Command "(Get-Content '%STUB_PATH%') -replace '{{NewFeature}}', '%FEATURE%' -replace '{{namespace}}', '%NAMESPACE%' | Set-Content '%FEATURE%.php'"

echo Feature created: app\Features\%FEATURE%\%FEATURE%.php
