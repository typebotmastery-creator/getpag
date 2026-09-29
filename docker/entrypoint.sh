#!/bin/bash
set -e

DB_HOST="${DB_HOST:-db}"
DB_USER="${DB_USER:-gatewaypro}"
DB_PASSWORD="${DB_PASSWORD:-gatewaypro_secret_2024}"
DB_NAME="${DB_NAME:-checkout}"

echo "============================================"
echo "GatewayPro - Inicializando..."
echo "============================================"

CONFIG_DIR="/var/www/html/config"
CONFIG_FILE="$CONFIG_DIR/config.php"
mkdir -p "$CONFIG_DIR"

# Garante que uploads/config exista e seja gravavel pelo Apache na inicializacao.
# O volume named app_uploads pode ja existir sem a subpasta (de deploys com a
# imagem antiga), e o .dockerignore exclui uploads/* da imagem. Sem isso os
# uploads de logo/favicon falham com "No such file or directory".
UPLOADS_CONFIG="/var/www/html/uploads/config"
if [ ! -d "$UPLOADS_CONFIG" ]; then
    mkdir -p "$UPLOADS_CONFIG" 2>/dev/null || echo "AVISO: nao consegui criar $UPLOADS_CONFIG"
fi
chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/uploads 2>/dev/null || true

echo "Diretorio de uploads: $(ls -ld /var/www/html/uploads /var/www/html/uploads/config 2>/dev/null | tr '\n' ' ')"

# Espera o banco subir. Em Swarm o depends_on do compose e ignorado, entao o
# app pode subir antes do MySQL. Sem esta espera o config.php chama die() e o
# container morre em laco.
if [ -n "${DB_HOST:-}" ]; then
    echo "Aguardando o banco em ${DB_HOST}..."
    for i in $(seq 1 60); do
        if php -r '$h=getenv("DB_HOST");$d=getenv("DB_NAME");$u=getenv("DB_USER");$p=getenv("DB_PASSWORD");
            try { new PDO("mysql:host=$h;dbname=$d;charset=utf8mb4",$u,$p); exit(0); }
            catch (Exception $e) { exit(1); }' 2>/dev/null; then
            echo "Banco pronto apos ${i}s."
            break
        fi
        if [ "$i" -eq 60 ]; then
            echo "AVISO: o banco nao respondeu em 60s. Seguindo assim mesmo;"
            echo "se o app nao abrir, confira DB_HOST/DB_USER/DB_PASSWORD no stack."
        fi
        sleep 1
    done
fi

# Escapa um valor para ser escrito dentro de aspas simples no PHP.
# Sem isso, uma senha com ' ou \ quebra o config inteiro.
#
# NAO escapa $ nem ` de proposito: o heredoc insere o valor da variavel sem
# reinterpretar as barras, entao $ e ` ja chegam literais no arquivo. Escapar
# aqui faria a senha receber uma barra a mais na hora de conectar no banco.
php_escape() {
    local s="$1"
    s="${s//\\/\\\\}"   # \  -> \\
    s="${s//\'/\\\'}"   # '  -> \'
    printf '%s' "$s"
}

# Licenca. Padrao e NAO pedir chave: quem compra o codigo roda a propria
# instancia, entao ja entra direto no sistema.
#   (vazio) ou true  -> o app entra sem chave de ativacao
#   false            -> o app exige uma chave valida em /ativacao
# So existe para quem quiser reativar a checagem por conta propria.
case "$(printf '%s' "${LICENCA_DESATIVADA:-true}" | tr '[:upper:]' '[:lower:]')" in
    false|0|no|off) LICENCA_OFF="false" ;;
    *)              LICENCA_OFF="true"  ;;
esac

DB_HOST_E="$(php_escape "$DB_HOST")"
DB_USER_E="$(php_escape "$DB_USER")"
DB_PASS_E="$(php_escape "$DB_PASSWORD")"
DB_NAME_E="$(php_escape "$DB_NAME")"
MAIL_SECRET_E="$(php_escape "${MAIL_UNSUBSCRIBE_SECRET:-troque_esta_chave}")"

cat > "$CONFIG_FILE" << EOFCONFIG
<?php
define('DB_HOST', '${DB_HOST_E}');
define('DB_USER', '${DB_USER_E}');
define('DB_PASS', '${DB_PASS_E}');
define('DB_NAME', '${DB_NAME_E}');

// Chave de ativacao. true = nao pede chave, o app entra direto.
// Quem compra o codigo e dono da propria instalacao.
define('LICENCA_DESATIVADA', ${LICENCA_OFF});

// Chave usada para assinar os links de descadastro de e-mail.
// Vem do ambiente para nao ficar gravada no codigo.
define('MAIL_UNSUBSCRIBE_SECRET', '${MAIL_SECRET_E}');

// true = exige certificado valido no envio de e-mail (recomendado em producao).
define('MAIL_VERIFICAR_CERTIFICADO', true);

date_default_timezone_set('America/Sao_Paulo');

try {
    \$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->exec("SET time_zone = '-03:00';");
} catch (PDOException \$e) {
    die("ERRO: Não foi possível conectar ao banco de dados. " . \$e->getMessage());
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getSystemSetting(\$chave, \$default = '') {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ?");
        \$stmt->execute([\$chave]);
        \$result = \$stmt->fetch(PDO::FETCH_ASSOC);
        return \$result ? \$result['valor'] : \$default;
    } catch (PDOException \$e) {
        return \$default;
    }
}

function setSystemSetting(\$chave, \$valor) {
    global \$pdo;
    if (!\$pdo) return false;
    try {
        \$stmt_check = \$pdo->prepare("SELECT id FROM configuracoes_sistema WHERE chave = ?");
        \$stmt_check->execute([\$chave]);
        \$exists = \$stmt_check->fetch(PDO::FETCH_ASSOC);
        if (\$exists) {
            \$stmt = \$pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?");
            \$stmt->execute([\$valor, \$chave]);
        } else {
            \$stmt = \$pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)");
            \$stmt->execute([\$chave, \$valor]);
        }
        return true;
    } catch (PDOException \$e) {
        return false;
    }
}

function getAllSystemSettings() {
    global \$pdo;
    try {
        \$stmt = \$pdo->prepare("SELECT chave, valor FROM configuracoes_sistema");
        \$stmt->execute();
        \$results = \$stmt->fetchAll(PDO::FETCH_ASSOC);
        \$settings = [];
        foreach (\$results as \$row) {
            \$settings[\$row['chave']] = \$row['valor'];
        }
        return \$settings;
    } catch (PDOException \$e) {
        return [];
    }
}

require_once __DIR__ . '/../helpers/plugin_hooks.php';
require_once __DIR__ . '/../helpers/plugin_loader.php';
?>
EOFCONFIG

chown www-data:www-data "$CONFIG_FILE"
echo "Config gerado!"

echo "============================================"
echo "GatewayPro pronto!"
echo "============================================"

exec "$@"
