<?php
function isMasterPanel() {
    $envSecret = getenv('GATEWAYPRO_MASTER_SECRET');
    if (empty($envSecret)) {
        return false;
    }
    $isMasterFlag = getSystemSetting('is_master_panel', '0') === '1';
    if (!$isMasterFlag) {
        return false;
    }
    $masterSecretKey = getSystemSetting('master_secret_key', '');
    if (empty($masterSecretKey) || !hash_equals($envSecret, $masterSecretKey)) {
        return false;
    }
    
    return true;
}
function licensesTableExists() {
    global $pdo;
    try {
        $result = $pdo->query("SELECT 1 FROM licencas_geradas LIMIT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}
