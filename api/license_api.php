<?php
/**
 * API Pública de Validação de Licenças
 * Este endpoint é usado pelos painéis clientes para validar licenças
 * Só funciona no painel master legítimo
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/master_helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function sendResponse($success, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(['success' => $success] + $data);
    exit;
}

// Verifica se é o painel master LEGÍTIMO
if (!isMasterPanel()) {
    sendResponse(false, ['error' => 'Este painel não é o servidor de licenças.'], 403);
}

// Verifica autenticação via token
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$apiToken = getSystemSetting('license_api_token', '');

if (empty($apiToken)) {
    // Gera um token se não existir
    $apiToken = bin2hex(random_bytes(32));
    setSystemSetting('license_api_token', $apiToken);
}

// Extrai o token do header
$providedToken = '';
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $providedToken = $matches[1];
}

if ($providedToken !== $apiToken) {
    sendResponse(false, ['error' => 'Token de autenticação inválido.'], 401);
}

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, ['error' => 'Método não permitido.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$activationKey = $input['activationKey'] ?? '';
$extensionId = $input['extensionId'] ?? '';

if ($action !== 'validate') {
    sendResponse(false, ['reason' => 'Ação inválida. Use: validate'], 400);
}

if (empty($activationKey)) {
    sendResponse(false, [
        'valid' => false,
        'reason' => 'Chave de ativação é obrigatória.'
    ]);
}

try {
    // Busca a licença no banco
    $stmt = $pdo->prepare("
        SELECT * FROM licencas_geradas 
        WHERE chave_licenca = ?
    ");
    $stmt->execute([$activationKey]);
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$license) {
        sendResponse(true, [
            'valid' => false,
            'reason' => 'Chave de ativação não encontrada.'
        ]);
    }
    
    // Verifica status
    if ($license['status'] === 'revogada') {
        sendResponse(true, [
            'valid' => false,
            'reason' => 'Esta licença foi revogada.'
        ]);
    }
    
    // Se já está ativada, verifica se expirou
    if ($license['status'] === 'ativada' && !empty($license['data_expiracao'])) {
        $expDate = new DateTime($license['data_expiracao']);
        $now = new DateTime();
        
        if ($now > $expDate) {
            // Atualiza status para expirada
            $stmtUpdate = $pdo->prepare("UPDATE licencas_geradas SET status = 'expirada' WHERE id = ?");
            $stmtUpdate->execute([$license['id']]);
            
            sendResponse(true, [
                'valid' => false,
                'reason' => 'Licença expirada em ' . $expDate->format('d/m/Y'),
                'expirationDate' => $license['data_expiracao']
            ]);
        }
    }
    
    // Se está disponível (primeira ativação), ativa agora
    if ($license['status'] === 'disponivel') {
        $dataAtivacao = date('Y-m-d H:i:s');
        $dataExpiracao = null;
        
        // Calcula data de expiração baseada nos dias
        if (!empty($license['dias_validade'])) {
            $expDate = new DateTime();
            $expDate->modify("+{$license['dias_validade']} days");
            $dataExpiracao = $expDate->format('Y-m-d');
        }
        
        // Atualiza a licença
        $stmtUpdate = $pdo->prepare("
            UPDATE licencas_geradas 
            SET status = 'ativada',
                data_ativacao = ?,
                data_expiracao = ?,
                instalacao_id = ?,
                ip_ativacao = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([
            $dataAtivacao,
            $dataExpiracao,
            $extensionId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $license['id']
        ]);
        
        sendResponse(true, [
            'valid' => true,
            'activationKey' => $activationKey,
            'licenseType' => $license['tipo_licenca'],
            'licenseDays' => $license['dias_validade'],
            'expirationDate' => $dataExpiracao,
            'message' => 'Licença ativada com sucesso!'
        ]);
    }
    
    // Se já está ativada e válida
    if ($license['status'] === 'ativada') {
        sendResponse(true, [
            'valid' => true,
            'activationKey' => $activationKey,
            'licenseType' => $license['tipo_licenca'],
            'licenseDays' => $license['dias_validade'],
            'expirationDate' => $license['data_expiracao'],
            'message' => 'Licença válida.'
        ]);
    }
    
    // Status desconhecido
    sendResponse(true, [
        'valid' => false,
        'reason' => 'Status de licença inválido: ' . $license['status']
    ]);
    
} catch (PDOException $e) {
    error_log("LICENSE_API Error: " . $e->getMessage());
    sendResponse(false, ['error' => 'Erro interno do servidor.'], 500);
}
