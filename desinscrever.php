<?php
/**
 * GatewayPro - desinscricao de e-mail
 * =========================================================================
 * Destino do cabecalho List-Unsubscribe que o sistema envia nos e-mails.
 *
 * Por que esta pagina precisa funcionar de verdade:
 * O Gmail e o Yahoo exigem que o link de descadastro exista e funcione. Se o
 * destinatario clica e recebe erro 404, ele marca como spam - e a reputacao de
 * envio piora ainda mais. Entao o link nao e cosmetico: e o que segura a
 * reputacao do dominio.
 *
 * Dois modos, ambos o mesmo endereco:
 *   GET  -> aberto por um link clicado dentro do cliente de e-mail. Mostra uma
 *           pagina curta confirmando a saida.
 *   POST -> o proprio botao "Unsubscribe" do Gmail envia
 *           "List-Unsubscribe=One-Click". Exige resposta curta e sem HTML.
 *
 * O token e um HMAC do e-mail com MAIL_UNSUBSCRIBE_SECRET. Sem ele, ninguem
 * consegue desinscrever o e-mail de outra pessoa. E como o token e derivado
 * (nao guardado), o mesmo e-mail gera sempre o mesmo link.
 * =========================================================================
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/mail_helper.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// --- resposta curta para o one-click do Gmail (POST) ---
$ehOneClick = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($ehOneClick) {
    header('Content-Type: text/plain; charset=utf-8');
} else {
    header('Content-Type: text/html; charset=utf-8');
}

$email  = isset($_REQUEST['email']) ? trim((string)$_REQUEST['email']) : '';
$token  = isset($_REQUEST['token']) ? trim((string)$_REQUEST['token']) : '';
$erro   = '';
$fez    = false;

// A mensagem de erro e a mesma para e-mail invalido, token invalido e e-mail
// inexistente. Sem isso, da para usar esta pagina para descobrir quem tem
// cadastro na plataforma.
$msgGenerica = 'Nao foi possivel confirmar a desinscricao. O link pode estar incompleto ou ter expirado.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erro = $msgGenerica;
} elseif (!mailUnsubscribeTokenValido($email, $token)) {
    $erro = $msgGenerica;
} else {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Guarda em configuracoes (chave/valor) para nao precisar de migracao
        // de banco: a VPS ja tem essa tabela e o INSERT e idempotente.
        $chave = 'email_unsubscribed_' . hash('sha256', strtolower($email));

        $stmt = $pdo->prepare(
            'INSERT INTO configuracoes (chave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)'
        );
        $stmt->execute([$chave, date('Y-m-d H:i:s')]);

        $fez = true;
    } catch (Exception $e) {
        error_log('DESINSCREVER: falha ao gravar: ' . $e->getMessage());
        $erro = 'Nao foi possivel registrar sua saida agora. Tente novamente em instantes.';
    }
}

// --- o Gmail so espera confirmacao curta no one-click ---
if ($ehOneClick) {
    if ($fez) {
        echo "Desinscrito com sucesso.\n";
    } elseif (!empty($erro)) {
        echo $erro . "\n";
    } else {
        echo "Desinscrito com sucesso.\n";
    }
    exit;
}

$titulo = $fez ? 'Voce saiu da lista' : 'Nao foi possivel sair';
$detalhe = $fez
    ? 'Este endereco nao recebera mais e-mails de acesso a produto. Se voce fez uma compra, seus dados continuam disponiveis na plataforma.'
    : ($erro !== '' ? $erro : 'O link nao trouxe as informacoes necessarias.');

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></title>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
         background: #0b0f19; color: #e5e7eb; margin: 0;
         min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  .card { max-width: 460px; margin: 24px; padding: 32px; text-align: center; }
  .icone { font-size: 40px; line-height: 1; margin-bottom: 16px; }
  h1 { font-size: 20px; margin: 0 0 12px; }
  p { font-size: 14px; line-height: 1.6; color: #9ca3af; margin: 0; }
  .ok { color: #34d399; }
  .erro { color: #f87171; }
</style>
</head>
<body>
  <div class="card">
    <div class="icone"><?php echo $fez ? '&#10003;' : '&#9888;'; ?></div>
    <h1 class="<?php echo $fez ? 'ok' : 'erro'; ?>"><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars($detalhe, ENT_QUOTES, 'UTF-8'); ?></p>
  </div>
</body>
</html>
