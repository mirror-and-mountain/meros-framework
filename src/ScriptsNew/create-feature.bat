@echo off

REM Use first argument as feature name
set FEATURE=%1

if "%FEATURE%"=="" (
    echo Feature name argument missing.
    exit /b 1
)

composer create-project mirror-and-mountain/meros-feature "app/Features/%FEATURE%" --no-install
if errorlevel 1 exit /b 1

cd "app/Features/%FEATURE%"
if errorlevel 1 exit /b 1

set FEATURE_CLASS=%FEATURE%
powershell -Command "(Get-Content 'Feature.stub') -replace '{{NewFeature}}', '%FEATURE_CLASS%' | Set-Content '%FEATURE_CLASS%.php'"

echo Feature created: app/Features/%FEATURE%/%FEATURE_CLASS%.php
