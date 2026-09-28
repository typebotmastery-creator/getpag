@echo off
REM ==========================================================
REM  GatewayPro - iniciar servidor local de DESENVOLVIMENTO
REM ==========================================================
REM  Nao precisa do Docker nem do modulo PHP do Apache.
REM  O MySQL precisa estar rodando (XAMPP > MySQL > Start).
REM ==========================================================

echo.
echo  Iniciando o GatewayPro...
echo.

REM Modo master local: sem isso o login.php:69 manda para /ativacao
REM em vez de entrar no painel. Desligado pela constante
REM LICENCA_DESATIVADA em config/config.php (helpers/master_helper.php).
REM Nao precisa de nenhuma variavel de ambiente.
cd /d "%~dp0"

echo  URL:      http://localhost:8088
echo  Login:    admin@gmail.com
echo  Senha:    admin123
echo.
echo  Para PARAR, feche esta janela ou pressione CTRL+C.
echo  ------------------------------------------------------------
echo.

REM Usa caminho relativo porque %~dp0 termina em "\". Escrito entre aspas
REM viraria "F:\checkout\" e o PHP leria a barra como escape, juntando os
REM dois argumentos num so ("Directory ... does not exist").
REM O cd acima ja deixou o .bat dentro da pasta do app.
C:\xampp\php\php.exe -S localhost:8088 -t . router.local.php

echo.
echo  Servidor encerrado.
pause
