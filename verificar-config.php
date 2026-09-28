<?php
/**
 * Verificador de configuracao de producao.
 *
 * Rode na VPS depois de adicionar as constantes de e-mail no config/config.php:
 *
 *   php verificar-config.php
 *
 * Este script nao imprime nenhum segredo. Apenas diz se cada item esta OK.
 * Ele recusa rodar pelo navegador, para nao expor o estado do servidor.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script so roda por linha de comando.\n");
}

// Buffer iniciado antes de qualquer echo: config/config.php chama
// session_start() e reclamaria se ja tivesse saida. Limpa logo apos o require.
ob_start();

$falhas = 0;
$avisos = 0;

function ok($msg)    { echo "  [ OK ]    $msg\n"; }
function aviso($msg) { global $avisos; $avisos++; echo "  [AVISO]  $msg\n"; }
function erro($msg)  { global $falhas;  $falhas++;  echo "  [FALHA]  $msg\n"; }

echo "\nGatewayPro - verificacao de producao\n";
echo str_repeat('=', 60) . "\n\n";

// ------------------------------------------------------------------
echo "1. Ambiente\n";
echo str_repeat('-', 60) . "\n";

echo "  PHP:            " . PHP_VERSION . "\n";
echo "  memory_limit:   " . ini_get('memory_limit') . "\n";
echo "  upload_max:     " . ini_get('upload_max_filesize') . "\n";
echo "  post_max_size:  " . ini_get('post_max_size') . "\n";

foreach (['curl', 'mbstring', 'pdo_mysql', 'openssl', 'fileinfo', 'json'] as $ext) {
    if (extension_loaded($ext)) {
        ok("extensao $ext");
    } else {
        erro("extensao $ext AUSENTE");
    }
}

foreach (['/config', '/helpers', '/uploads'] as $dir) {
    if (is_dir(__DIR__ . $dir)) {
        $w = is_writable(__DIR__ . $dir);
        if ($w) { ok("pasta $dir existe e e gravavel"); }
        else    { erro("pasta $dir existe mas NAO e gravavel"); }
    } else {
        erro("pasta $dir NAO existe");
    }
}

// ------------------------------------------------------------------
echo "\n2. Banco de dados\n";
echo str_repeat('-', 60) . "\n";

// config/config.php chama session_start(); o buffer iniciado no topo do
// script evita o aviso de "headers already sent".
if (!file_exists(__DIR__ . '/config/config.php')) {
    echo "\n  [FALHA]  config/config.php nao encontrado\n";
    ob_end_flush();
    echo "\nFim. Corrija antes de continuar.\n";
    exit(1);
}

require_once __DIR__ . '/config/config.php';
echo ob_get_clean();

echo "  DB_HOST: " . DB_HOST . "\n";
echo "  DB_NAME: " . DB_NAME . "\n";

global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    erro("conexao com o banco nao established (\\$pdo nao e PDO)");
} else {
    ok("conexao com o banco OK");

    $versao = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "  MySQL: $versao\n";

    foreach (['produtos', 'usuarios', 'vendas', 'configuracoes', 'configuracoes_sistema'] as $t) {
        $existe = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($t))->fetchColumn();
        if ($existe) {
            $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
            ok("tabela $t existe ($n linhas)");
        } else {
            aviso("tabela $t nao existe - alguma parte do sistema pode nao funcionar");
        }
    }
}

// ------------------------------------------------------------------
echo "\n3. Constantes de e-mail\n";
echo str_repeat('-', 60) . "\n";

if (defined('MAIL_VERIFICAR_CERTIFICADO')) {
    if (MAIL_VERIFICAR_CERTIFICADO === true) {
        ok("MAIL_VERIFICAR_CERTIFICADO = true (seguro)");
    } else {
        erro("MAIL_VERIFICAR_CERTIFICADO esta DESLIGADO - e-mail pode ser lido no caminho");
    }
} else {
    aviso("MAIL_VERIFICAR_CERTIFICADO nao definida no config (o helper assume true)");
}

$temSegredo = defined('MAIL_UNSUBSCRIBE_SECRET') && MAIL_UNSUBSCRIBE_SECRET !== '';
if ($temSegredo) {
    $v = MAIL_UNSUBSCRIBE_SECRET;
    if ($v === 'troque_esta_chave') {
        erro("MAIL_UNSUBSCRIBE_SECRET ainda e o valor de exemplo - gere um novo");
    } elseif (strlen($v) < 32) {
        aviso("MAIL_UNSUBSCRIBE_SECRET tem menos de 32 caracteres - use um valor maior");
    } else {
        ok("MAIL_UNSUBSCRIBE_SECRET definido (" . strlen($v) . " caracteres, valor nao exibido)");
    }
} else {
    aviso("MAIL_UNSUBSCRIBE_SECRET nao definida - o helper vai derivar do DB_PASS");
    aviso("  funciona, mas o recomendado e definir no config. Ver DEPLOY.md secao 5.");
}

if (defined('LICENCA_DESATIVADA')) {
    if (LICENCA_DESATIVADA === true) {
        aviso("LICENCA_DESATIVADA = true - a checagem de licenca esta DESLIGADA");
        aviso("  se este e o seu ambiente de producao, remova esta constante");
    } else {
        ok("LICENCA_DESATIVADA = false (licenca ativa)");
    }
} else {
    ok("LICENCA_DESATIVADA nao definida (licenca ativa - correto para producao)");
}

// ------------------------------------------------------------------
echo "\n4. Sistema de e-mail\n";
echo str_repeat('-', 60) . "\n";

if (file_exists(__DIR__ . '/helpers/mail_helper.php')) {
    require_once __DIR__ . '/helpers/mail_helper.php';
    ok("helpers/mail_helper.php carregado");
    echo "  URL base detectada: " . mailBaseUrl() . "\n";
} else {
    erro("helpers/mail_helper.php nao encontrado");
}

if (file_exists(__DIR__ . '/desinscrever.php')) {
    ok("desinscrever.php existe");
} else {
    erro("desinscrever.php nao encontrado - link de descadastro vai dar 404");
}

// ------------------------------------------------------------------
echo "\n5. Cabecalho de seguranca (.htaccess)\n";
echo str_repeat('-', 60) . "\n";

if (file_exists(__DIR__ . '/.htaccess')) {
    ok(".htaccess existe na raiz");
    $h = file_get_contents(__DIR__ . '/.htaccess');
    $regras = [
        '^helpers(/.*)?$'         => 'bloqueia /helpers',
        '^config(/.*)?$'          => 'bloqueia /config',
        '^PHPMailer(/.*)?$'       => 'bloqueia /PHPMailer',
        '^gateways(/.*)?$'        => 'bloqueia /gateways',
        '^docker(/.*)?$'          => 'bloqueia /docker',
        '^/uploads/.*\.php$'      => 'bloqueia PHP dentro de uploads',
    ];
    foreach ($regras as $needle => $descricao) {
        if (strpos($h, $needle) !== false) {
            ok($descricao);
        } else {
            erro("regra ausente: $descricao");
        }
    }
} else {
    erro(".htaccess NAO existe na raiz - todas as URLs limpas vao quebrar");
}

// ------------------------------------------------------------------
echo "\n" . str_repeat('=', 60) . "\n";
echo "Resultado: $falhas falha(s), $avisos aviso(s)\n";

if ($falhas > 0) {
    echo "\nNAO publique com falhas. Corrija os itens [FALHA] acima.\n";
    exit(1);
}
if ($avisos > 0) {
    echo "\nPode publicar, mas confira os [AVISO] - alguns so importam se voce for vender.\n";
} else {
    echo "\nTudo certo. Pode publicar.\n";
}
echo "\n";
