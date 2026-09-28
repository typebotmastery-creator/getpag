<?php
/**
 * Teste dos cabecalhos de e-mail (nao envia nada, so monta a mensagem).
 * Rodar:  C:\xampp\php\php.exe F:\checkout\teste-cabecalho.php
 */
chdir(__DIR__);
require_once 'config/config.php';
$phpmailer_path = __DIR__ . '/PHPMailer/src/';
require_once $phpmailer_path . 'Exception.php';
require_once $phpmailer_path . 'PHPMailer.php';
require_once $phpmailer_path . 'SMTP.php';
require_once __DIR__ . '/helpers/mail_helper.php';

use PHPMailer\PHPMailer\PHPMailer;

$cfg = [
    'host'       => 'smtp.gmail.com',
    'port'       => 587,
    'username'   => 'typebotmastery@gmail.com',
    'password'   => 'senha-que-nao-e-usada-aqui',
    'encryption' => 'tls',
    'from_email' => 'typebotmastery@gmail.com',
    'from_name'  => 'Comunidade Mastery',
];

$mail = new PHPMailer(true);
mailConfigurarSmtp($mail, $cfg);
$mail->CharSet = 'UTF-8';
$mail->addAddress('aluno@exemplo.com', 'Aluno Teste');
$mail->Subject = 'Acesso ao seu Produto da CM';
$mail->isHTML(true);
$mail->Body = '<p>Parabéns!</p>';
$mail->AltBody = 'Parabéns!';
mailCabecalhosEntrega($mail, $cfg, 'aluno@exemplo.com');

echo "=== CABECALHOS MONTADOS (createHeader) ===\n";
echo $mail->createHeader();

echo "\n=== CABECALHOS CUSTOM (o que o helper adicionou) ===\n";
foreach ($mail->getCustomHeaders() as $ch) {
    echo "  " . $ch[0] . ": " . $ch[1] . "\n";
}

echo "\n=== CONFERINDO O QUE IMPORTA ===\n";
$h = $mail->createHeader() . "\n";
foreach ($mail->getCustomHeaders() as $ch) {
    $h .= $ch[0] . ": " . $ch[1] . "\n";
}
$checks = [
    'List-Unsubscribe'          => 'List-Unsubscribe: <http',
    'List-Unsubscribe-Post'     => 'List-Unsubscribe-Post: List-Unsubscribe=One-Click',
    'Reply-To'                  => 'Reply-To: Comunidade Mastery <typebotmastery@gmail.com>',
    'remetente = usuario SMTP'  => 'From: Comunidade Mastery <typebotmastery@gmail.com>',
    'X-Mailer'                  => 'X-Mailer: GatewayPro',
];
foreach ($checks as $rotulo => $procura) {
    printf("  %-28s %s\n", $rotulo, (stripos($h, $procura) !== false ? 'OK' : 'FALTOU'));
}

echo "\n=== SSL: verificacao de certificado ===\n";
$o = $mail->SMTPOptions['ssl'];
foreach (['verify_peer', 'verify_peer_name', 'allow_self_signed'] as $k) {
    printf("  %-20s %s\n", $k, var_export($o[$k], true));
}
echo "  MAIL_VERIFICAR_CERTIFICADO = " . var_export(MAIL_VERIFICAR_CERTIFICADO, true) . "\n";

echo "\n=== LINK de desinscricao ===\n";
$url = mailUnsubscribeUrl('aluno@exemplo.com');
echo "  $url\n";
echo "  mesmo e-mail gera mesmo link: " .
     (mailUnsubscribeUrl('aluno@exemplo.com') === $url ? 'sim' : 'NAO') . "\n";
echo "  token valido para o e-mail: " .
     (mailUnsubscribeTokenValido('aluno@exemplo.com', mailUnsubscribeToken('aluno@exemplo.com')) ? 'sim' : 'NAO') . "\n";
echo "  token recusado em outro e-mail: " .
     (mailUnsubscribeTokenValido('outro@exemplo.com', mailUnsubscribeToken('aluno@exemplo.com')) ? 'NAO' : 'sim') . "\n";
