<?php
/**
 * Carrega configurações do sistema e aplica dinamicamente
 * Este arquivo deve ser incluído no <head> de todas as páginas principais
 */

/**
 * Ajusta o brilho de uma cor hexadecimal
 * @param string $hex Cor em hexadecimal (#RRGGBB)
 * @param int $steps Passos de ajuste (negativo escurece, positivo clareia)
 * @return string Cor ajustada em hexadecimal
 */
if (!function_exists('adjustBrightness')) {
    function adjustBrightness($hex, $steps) {
        // Remove # se presente
        $hex = str_replace('#', '', $hex);
        
        // Converte para RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        // Ajusta o brilho
        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));
        
        // Converte de volta para hex
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                     str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                     str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}

/**
 * Converte cor hexadecimal para RGB
 * @param string $hex Cor em hexadecimal (#RRGGBB)
 * @return array Array com r, g, b
 */
if (!function_exists('hexToRgb')) {
    function hexToRgb($hex) {
        $hex = str_replace('#', '', $hex);
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
}

// Garante que config.php já foi incluído
if (!function_exists('getSystemSetting')) {
    require_once __DIR__ . '/config.php';
}

// Busca configurações
$cor_primaria = getSystemSetting('cor_primaria', '#32e768');
$logo_url_raw = getSystemSetting('logo_url', 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png');
$login_image_url_raw = getSystemSetting('login_image_url', '');
$nome_plataforma = getSystemSetting('nome_plataforma', 'GatewayPro');
$logo_checkout_url_raw = getSystemSetting('logo_checkout_url', '');
$favicon_url_raw = getSystemSetting('favicon_url', '');
$notification_image_url_raw = getSystemSetting('notification_image_url', '');

// Normaliza URLs: igual às imagens dos módulos
// Remove barra inicial se houver (valores antigos podem ter)
$logo_url = ltrim($logo_url_raw, '/');
if (empty($logo_url)) {
    $logo_url = 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png';
} elseif (strpos($logo_url, 'http') === 0) {
    // URL completa, mantém como está
} elseif (strpos($logo_url, 'uploads/') === 0) {
    // Adiciona barra inicial (igual às imagens dos módulos)
    $logo_url = '/' . $logo_url;
} else {
    // Outros casos, adiciona barra se necessário
    $logo_url = '/' . $logo_url;
}

$login_image_url = ltrim($login_image_url_raw, '/');
if (!empty($login_image_url) && strpos($login_image_url, 'http') !== 0) {
    if (strpos($login_image_url, 'uploads/') === 0) {
        // Adiciona barra inicial (igual às imagens dos módulos)
        $login_image_url = '/' . $login_image_url;
    } elseif (!empty($login_image_url)) {
        $login_image_url = '/' . $login_image_url;
    }
}

// Logo do checkout: se não configurada, usa a logo padrão
$logo_checkout_url = ltrim($logo_checkout_url_raw, '/');
if (empty($logo_checkout_url)) {
    $logo_checkout_url = $logo_url;
} elseif (strpos($logo_checkout_url, 'http') === 0) {
    // URL completa, mantém como está
} elseif (strpos($logo_checkout_url, 'uploads/') === 0) {
    // Adiciona barra inicial (igual às imagens dos módulos)
    $logo_checkout_url = '/' . $logo_checkout_url;
} else {
    $logo_checkout_url = '/' . $logo_checkout_url;
}

// Normaliza URL do favicon
$favicon_url = ltrim($favicon_url_raw, '/');
if (!empty($favicon_url) && strpos($favicon_url, 'http') !== 0) {
    if (strpos($favicon_url, 'uploads/') === 0) {
        // Adiciona barra inicial (igual às imagens dos módulos)
        $favicon_url = '/' . $favicon_url;
    } else {
        $favicon_url = '/' . $favicon_url;
    }
}

// Normaliza URL da imagem de notificações (se não configurada, usa a logo)
$notification_image_url = ltrim($notification_image_url_raw, '/');
if (empty($notification_image_url)) {
    $notification_image_url = $logo_url;
} elseif (strpos($notification_image_url, 'http') === 0) {
    // URL completa, mantém como está
} elseif (strpos($notification_image_url, 'uploads/') === 0) {
    $notification_image_url = '/' . $notification_image_url;
} else {
    $notification_image_url = '/' . $notification_image_url;
}

// Calcula cor hover
$cor_primaria_hover = adjustBrightness($cor_primaria, -10);

// Gera CSS dinâmico para a cor primária
?>
<style>
:root {
    --accent-primary: <?php echo htmlspecialchars($cor_primaria); ?>;
    --accent-primary-hover: <?php echo htmlspecialchars($cor_primaria_hover); ?>;
}

/* Classe utilitária para cor primária */
.bg-primary {
    background-color: var(--accent-primary) !important;
}

.bg-primary-hover:hover {
    background-color: var(--accent-primary-hover) !important;
}

.text-primary {
    color: var(--accent-primary) !important;
}

.border-primary {
    border-color: var(--accent-primary) !important;
}

.ring-primary:focus {
    ring-color: var(--accent-primary) !important;
}

/* Background dinâmico para sidebar-item-active */
.sidebar-item-active {
    background: <?php 
        $rgb = hexToRgb($cor_primaria);
        echo "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, 0.1)";
    ?> !important;
}

.sidebar-item-active i,
.sidebar-item-active span {
    filter: drop-shadow(0 0 4px <?php 
        $rgb = hexToRgb($cor_primaria);
        echo "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, 0.4)";
    ?>) !important;
}
</style>
<?php
// Gera tag <link rel="icon"> se favicon estiver configurado
if (!empty($favicon_url)) {
    // Determina o tipo MIME baseado na extensão
    $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
    $favicon_type = 'image/x-icon'; // padrão
    if ($favicon_ext === 'png') {
        $favicon_type = 'image/png';
    } elseif ($favicon_ext === 'svg') {
        $favicon_type = 'image/svg+xml';
    }
    echo '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">' . "\n";
}
?>

