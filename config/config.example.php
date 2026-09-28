<?php
/**
 * GatewayPro - configuracao de BANCO DE DADOS (modelo para novos devs)
 *
 * Este arquivo NAO tem senha e pode ser versionado no GitHub.
 * O config/config.php (que tem a senha real) esta no .gitignore.
 *
 * COMO USAR:
 *   1. Copie este arquivo para config/config.php
 *   2. Ajuste as 4 constantes abaixo com os dados do seu banco
 *   3. Importe o Banco_de_Dados.sql
 *   4. Rode o iniciar-local.bat (ou o docker-start.bat se tiver Docker)
 *
 * Qual ambiente e o seu?
 *   - XAMPP/WAMP instalado .............. DB_HOST = 'localhost'
 *   - Docker (docker-start.bat) ......... DB_HOST = 'db'
 *   - Hospedagem com MySQL .............. DB_HOST = o host que o provedor deu
 */

// ------------------------------------------------------------------
// Ajuste estes 4 valores
// ------------------------------------------------------------------
define('DB_HOST', 'localhost');   // Docker usa 'db', nao 'localhost'
define('DB_USER', 'gatewaypro');
define('DB_PASS', 'troque_esta_senha');
define('DB_NAME', 'checkout');

// Desliga a checagem de licenca (helpers/master_helper.php -> isMasterPanel).
// Coloque false se este sistema for vendido como produto licenciado.
define('LICENCA_DESATIVADA', true);

// Chave usada para assinar o link de desinscricao dos e-mails (List-Unsubscribe).
// Gere a sua com:  openssl rand -base64 32
// OBRIGATORIO ser diferente em cada ambiente: e a mesma chave que valida o
// token do link, entao se vazar da para desinscrever e-mail de outra pessoa.
define('MAIL_UNSUBSCRIBE_SECRET', 'troque_esta_chave');

/* Valida o certificado TLS do servidor SMTP (helpers/mail_helper.php).
   Deixe true. Desligar isso permite que alguem no caminho da conexao leia os
   e-mails dos alunos. So mude se um dia der erro de certificado - e, nesse
   caso, o certo e corrigir o servidor SMTP.
   O guard evita warning: alguns arquivos carregam o mail_helper.php antes do
   config.php, e o helper ja define essa constante como padrao seguro. */
if (!defined('MAIL_VERIFICAR_CERTIFICADO')) {
    define('MAIL_VERIFICAR_CERTIFICADO', true);
}

date_default_timezone_set('America/Sao_Paulo');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '-03:00';");
} catch (PDOException $e) {
    die("ERRO: Nao foi possivel conectar ao banco de dados. " . $e->getMessage()
        . "<br><br>Verifique: (1) MySQL rodando? (2) banco '" . DB_NAME . "' existe? "
        . "(3) usuario '" . DB_USER . "' tem permissao? (4) config/config.php tem o DB_HOST certo?");
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getSystemSetting($chave, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ?");
        $stmt->execute([$chave]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['valor'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function setSystemSetting($chave, $valor) {
    global $pdo;
    if (!$pdo) return false;
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM configuracoes_sistema WHERE chave = ?");
        $stmt_check->execute([$chave]);
        $exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            $stmt = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?");
            $stmt->execute([$valor, $chave]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)");
            $stmt->execute([$chave, $valor]);
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function getAllSystemSettings() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes_sistema");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach ($results as $row) {
            $settings[$row['chave']] = $row['valor'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

require_once __DIR__ . '/../helpers/plugin_hooks.php';
require_once __DIR__ . '/../helpers/plugin_loader.php';
