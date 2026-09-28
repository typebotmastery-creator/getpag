<?php
/**
 * GatewayPro - ajustes de entrega de e-mail
 * =========================================================================
 * Este arquivo centraliza duas coisas que estavam repetidas (e erradas) em
 * varios pontos de envio de e-mail do sistema:
 *
 * 1) Configuracao de SMTP
 *    O codigo antigo repetia o bloco SMTP em 11 lugares e, em 6 deles, desligava
 *    a validacao do certificado do servidor:
 *        'verify_peer' => false, 'allow_self_signed' => true
 *    Isso aceitava qualquer certificado, o que permite que alguem no caminho
 *    da conexao leia os e-mails dos alunos (senha de acesso, links de produtos).
 *    Aqui a validacao fica ligada por padrao e o bloco existe em um lugar so.
 *
 * 2) Cabecalhos que ajudam a nao cair em spam
 *    - Reply-To:            respostas do aluno chegam em voce, e nao no no-reply
 *    - List-Unsubscribe:    obrigatorio desde 2024 para Gmail e Yahoo. O
 *                           destinatario precisa poder sair com um clique
 *    - X-Mailer / Message-ID: identificam a plataforma para o filtro antispam
 *
 * ATENCAO - LEIA ANTES DE ESPERAR O SPAM ACABAR
 *    Ajustar cabecalho sozinho NAO resolve a entrega quando o remetente e
 *    @gmail.com. Quem manda e o seu servidor, mas o dominio gmail.com pertence
 *    ao Google, entao nao existe registro SPF/DKIM/DMARC que voce possa criar
 *    para ele. Sem esses registros, o e-mail sempre nasce com score baixo.
 *    A solucao definitiva e usar um dominio proprio ou um servico de e-mail
 *    transacional (Brevo, Resend, Mailgun, SES). Ver SETUP_LOCAL.md.
 * =========================================================================
 */

// `use` vale so para o arquivo que declara, e precisa ficar no escopo de fora.
// Sem esta linha, as constantes PHPMailer::ENCRYPTION_* abaixo seriam
// procuradas na classe global "PHPMailer", que nao existe -> erro fatal.
use PHPMailer\PHPMailer\PHPMailer;

if (!defined('GATEWAYPRO_MAIL_HELPER')) {
    define('GATEWAYPRO_MAIL_HELPER', true);

    /**
     * Verifica o certificado TLS do servidor SMTP.
     *
     * Desligar isso e o que permite ler o e-mail no caminho. Fica ligado por
     * padrao. Se algum dia der erro de certificado, corrija o servidor SMTP em
     * vez de desligar isto - e o jeito facil de vazar dado de aluno.
     */
    if (!defined('MAIL_VERIFICAR_CERTIFICADO')) {
        define('MAIL_VERIFICAR_CERTIFICADO', true);
    }

    /**
     * URL base do sistema, montada a partir da requisicao atual.
     * Mesmo metodo ja usado em api/forgot_password.php.
     */
    function mailBaseUrl() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    /**
     * Chave usada para assinar o token de desinscricao.
     * Sem uma chave propria nao dava para validar o token sem expor a senha do
     * banco, entao ela fica no config e precisa ser a mesma na VPS.
     */
    function mailUnsubscribeSecret() {
        if (defined('MAIL_UNSUBSCRIBE_SECRET') && MAIL_UNSUBSCRIBE_SECRET !== '') {
            return (string)MAIL_UNSUBSCRIBE_SECRET;
        }
        // Fallback: derivar de um segredo de verdade (senha do banco) em vez de
        // dados publicos como o caminho da pasta e a versao do PHP. Com o
        // fallback anterior, quem soubesse o caminho da instalacao e a versao do
        // PHP forjava o token de desinscricao de qualquer e-mail - ou seja,
        // cancellava a entrega para clientes dos outros.
        if (defined('DB_PASS') && DB_PASS !== '') {
            return hash('sha256', 'gwpro-unsub|' . DB_PASS . '|' . __DIR__ . '|' . PHP_VERSION_ID);
        }
        return hash('sha256', __DIR__ . '|' . (PHP_VERSION_ID));
    }

    /**
     * Gera o token de desinscricao de um e-mail.
     * O token e derivado do e-mail + chave, sem gravar nada no banco: o mesmo
     * e-mail sempre gera o mesmo token, e ninguem consegue forjar sem a chave.
     */
    function mailUnsubscribeToken($email) {
        return hash_hmac('sha256', strtolower(trim($email)), mailUnsubscribeSecret());
    }

    /**
     * Confere se um token pertence ao e-mail informado.
     * hash_equals evita que o token seja descobert por tempo de resposta.
     */
    function mailUnsubscribeTokenValido($email, $token) {
        if (empty($email) || empty($token)) {
            return false;
        }
        return hash_equals(mailUnsubscribeToken($email), $token);
    }

    /**
     * Monta o link de desinscricao de um e-mail.
     */
    function mailUnsubscribeUrl($email) {
        return mailBaseUrl() . '/desinscrever'
             . '?email=' . rawurlencode($email)
             . '&token=' . mailUnsubscribeToken($email);
    }

    /**
     * Aplica a configuracao de SMTP no objeto PHPMailer.
     *
     * Substitui os 11 blocos repetidos. Preserva a decisao ja existente no
     * sistema de usar o usuario SMTP como remetente - sem isso o Gmail
     * recusa com "Sender address rejected".
     *
     * @param PHPMailer $mail
     * @param array $cfg host, port, username, password, encryption, from_name
     */
    function mailConfigurarSmtp($mail, array $cfg) {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = isset($cfg['port']) ? (int)$cfg['port'] : 587;
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['username'];
        $mail->Password = $cfg['password'];

        // Validacao de certificado LIGADA por padrao (ver nota no topo).
        // allow_self_signed continua false: certificado autoassinado em um
        // servidor de producao e motivo suficiente para investigar.
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => (bool)MAIL_VERIFICAR_CERTIFICADO,
                'verify_peer_name'  => (bool)MAIL_VERIFICAR_CERTIFICADO,
                'allow_self_signed' => !MAIL_VERIFICAR_CERTIFICADO
            )
        );

        $encryption = $cfg['encryption'] ?? 'tls';
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $fromName = $cfg['from_name'] ?? 'GatewayPro';
        // O remetente e o usuario SMTP: o Gmail so aceita quando os dois batem.
        $mail->setFrom($cfg['username'], $fromName);

        return $mail;
    }

    /**
     * Aplica os cabecalhos que ajudam contra spam.
     *
     * Chame DEPOIS de configurar remetente, destinatario e corpo - cabecalho e
     * montado no envio, entao a ordem importa.
     *
     * @param PHPMailer $mail
     * @param array $cfg  precisa de 'username' e 'from_email'
     * @param string $emailDestinatario  quem vai receber, usado no unsubscribe
     */
    function mailCabecalhosEntrega($mail, array $cfg, $emailDestinatario = '') {
        // Respostas do aluno chegam para o remetente real, e nao para o
        // endereco que o aluno nao domina.
        $replyTo = $cfg['from_email'] ?? '';
        if (empty($replyTo) || $replyTo === $cfg['username']) {
            $replyTo = $cfg['username'];
        }
        if (!empty($replyTo)) {
            $mail->addReplyTo($replyTo, $cfg['from_name'] ?? 'GatewayPro');
        }

        $mail->XMailer = 'GatewayPro';

        // Gmail e Yahoo exigem List-Unsubscribe para quem envia em volume.
        // O link precisa funcionar de verdade, senao clicar em "spam" so piora
        // a reputacao - por isso o destino e desinscrever.php.
        if (!empty($emailDestinatario) && filter_var($emailDestinatario, FILTER_VALIDATE_EMAIL)) {
            $unsubscribeUrl = mailUnsubscribeUrl($emailDestinatario);
            $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
            // "One-Click": o proprio botao do Gmail envia POST nessa URL
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        return $mail;
    }
}
