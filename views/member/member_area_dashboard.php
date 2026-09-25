<?php
require_once __DIR__ . '/../../config/config.php';

// ... (código de sessão PHP inalterado) ...
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: /member_login");
    exit;
}
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'admin') {
    header("location: /admin");
    exit;
}
if (isset($_SESSION["tipo"]) && $_SESSION["tipo"] === 'infoprodutor') {
    header("location: /");
    exit;
}
$cliente_email = strtolower($_SESSION['usuario']);
$cliente_nome = $_SESSION['nome'] ?? $cliente_email;
$cursos_adquiridos = [];
$upload_dir = 'uploads/';

// Busca a logo do sistema
$logo_url = 'https://cdn.jsdelivr.net/gh/mathuzabr/img-packtypebot/logo-gatewaypro.png';
try {
    $stmt_logo = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'logo_url'");
    $stmt_logo->execute();
    $logo_result = $stmt_logo->fetch(PDO::FETCH_ASSOC);
    if ($logo_result && !empty($logo_result['valor'])) {
        $logo_url = '/' . ltrim($logo_result['valor'], '/');
    }
} catch (Exception $e) {
    // Mantém o fallback padrão
} 

try {
    // Busca os cursos adquiridos pelo aluno (incluindo data de expiração)
    $stmt = $pdo->prepare("
        SELECT
            aa.produto_id,
            aa.data_concessao,
            aa.data_expiracao,
            p.nome AS produto_nome,
            p.foto AS produto_foto,
            c.id AS curso_id,
            c.titulo AS curso_titulo,
            c.descricao AS curso_descricao,
            c.imagem_url AS curso_imagem_url,
            c.banner_url AS curso_banner_url
        FROM alunos_acessos aa
        JOIN produtos p ON aa.produto_id = p.id
        LEFT JOIN cursos c ON p.id = c.produto_id
        WHERE aa.aluno_email = ? 
        AND p.tipo_entrega = 'area_membros'
        AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
        ORDER BY aa.data_concessao DESC
    ");
    $stmt->execute([$cliente_email]);
    $cursos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Busca também cursos expirados para mostrar separadamente
    $stmt_expirados = $pdo->prepare("
        SELECT
            aa.produto_id,
            aa.data_concessao,
            aa.data_expiracao,
            p.nome AS produto_nome,
            p.foto AS produto_foto,
            c.id AS curso_id,
            c.titulo AS curso_titulo,
            c.descricao AS curso_descricao,
            c.imagem_url AS curso_imagem_url,
            c.banner_url AS curso_banner_url
        FROM alunos_acessos aa
        JOIN produtos p ON aa.produto_id = p.id
        LEFT JOIN cursos c ON p.id = c.produto_id
        WHERE aa.aluno_email = ? 
        AND p.tipo_entrega = 'area_membros'
        AND aa.data_expiracao IS NOT NULL 
        AND aa.data_expiracao <= NOW()
        ORDER BY aa.data_expiracao DESC
    ");
    $stmt_expirados->execute([$cliente_email]);
    $cursos_expirados = $stmt_expirados->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcula o progresso de cada curso considerando aulas desbloqueadas
    $cursos_adquiridos = [];
    foreach ($cursos_raw as $curso) {
        $total_aulas_desbloqueadas = 0;
        $aulas_concluidas = 0;
        
        if (!empty($curso['curso_id'])) {
            $data_concessao = new DateTime($curso['data_concessao']);
            $hoje = new DateTime();
            $dias_desde_compra = $data_concessao->diff($hoje)->days;
            
            // Busca todas as aulas do curso
            $stmt_aulas = $pdo->prepare("
                SELECT a.id, a.release_days
                FROM aulas a
                INNER JOIN modulos m ON a.modulo_id = m.id
                WHERE m.curso_id = ?
            ");
            $stmt_aulas->execute([$curso['curso_id']]);
            $aulas = $stmt_aulas->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($aulas as $aula) {
                // Verifica se a aula está desbloqueada
                if ($aula['release_days'] <= $dias_desde_compra) {
                    $total_aulas_desbloqueadas++;
                    
                    // Verifica se o aluno concluiu esta aula
                    $stmt_prog = $pdo->prepare("SELECT COUNT(*) FROM aluno_progresso WHERE aluno_email = ? AND aula_id = ?");
                    $stmt_prog->execute([$cliente_email, $aula['id']]);
                    if ($stmt_prog->fetchColumn() > 0) {
                        $aulas_concluidas++;
                    }
                }
            }
        }
        
        $curso['total_aulas'] = $total_aulas_desbloqueadas;
        $curso['aulas_concluidas'] = $aulas_concluidas;
        $cursos_adquiridos[] = $curso;
    }

} catch (PDOException $e) {
    $mensagem_erro = "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>Erro ao buscar seus cursos: " . htmlspecialchars($e->getMessage()) . "</div>";
    $cursos_adquiridos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Meus Cursos - Área de Membros GatewayPro</title>
    <?php
    // Adiciona favicon se configurado
    require_once __DIR__ . '/../../config/config.php';
    $favicon_url_raw = getSystemSetting('favicon_url', '');
    if (!empty($favicon_url_raw)) {
        $favicon_url = ltrim($favicon_url_raw, '/');
        if (strpos($favicon_url, 'http') !== 0) {
            if (strpos($favicon_url, 'uploads/') === 0) {
                $favicon_url = '/' . $favicon_url;
            } else {
                $favicon_url = '/' . $favicon_url;
            }
        }
        $favicon_ext = strtolower(pathinfo($favicon_url, PATHINFO_EXTENSION));
        $favicon_type = 'image/x-icon';
        if ($favicon_ext === 'png') {
            $favicon_type = 'image/png';
        } elseif ($favicon_ext === 'svg') {
            $favicon_type = 'image/svg+xml';
        }
        echo '<link rel="icon" type="' . htmlspecialchars($favicon_type) . '" href="' . htmlspecialchars($favicon_url) . '">' . "\n";
    }
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 text-gray-200 antialiased">

    <!-- Cabeçalho Premium Fixo (voltando para paleta 'gray') -->
    <header class="sticky top-0 z-50 w-full border-b border-gray-700/50 bg-gray-900/70 backdrop-blur-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-4">
                    <a href="/member_area_dashboard">
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="GatewayPro Logo" class="h-10">
                    </a>
                </div>
                <div class="flex items-center space-x-5">
                    <!-- Dropdown do Perfil -->
                    <div class="relative" id="profile-dropdown-container">
                        <button onclick="toggleProfileDropdown()" class="flex items-center space-x-2 font-medium text-gray-300 hover:text-white transition-colors cursor-pointer">
                            <span class="hidden md:block">Olá, <?php echo htmlspecialchars($cliente_nome); ?>!</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" id="dropdown-arrow"></i>
                        </button>
                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-gray-800 border border-gray-700 rounded-lg shadow-xl py-2 z-50">
                            <button onclick="openProfileModal()" class="w-full flex items-center space-x-3 px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors text-left">
                                <i data-lucide="user-cog" class="w-4 h-4"></i>
                                <span>Editar Perfil</span>
                            </button>
                            <?php 
                            // Inclui helper do master e verifica se aluno tem acesso a produto que gera licença
                            require_once __DIR__ . '/../../helpers/master_helper.php';
                            $showLicenseLink = false;
                            if (isMasterPanel()) {
                                $stmtLic = $pdo->prepare("
                                    SELECT COUNT(*) FROM alunos_acessos aa
                                    JOIN produtos p ON aa.produto_id = p.id
                                    WHERE aa.aluno_email = ? AND p.gera_licenca = 1
                                    AND (aa.data_expiracao IS NULL OR aa.data_expiracao > NOW())
                                ");
                                $stmtLic->execute([$cliente_email]);
                                $showLicenseLink = $stmtLic->fetchColumn() > 0;
                            }
                            if ($showLicenseLink): 
                            ?>
                            <a href="/member_licenses" class="flex items-center space-x-3 px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-green-400 transition-colors">
                                <i data-lucide="key" class="w-4 h-4"></i>
                                <span>Minhas Licenças</span>
                            </a>
                            <?php endif; ?>
                            <hr class="border-gray-700 my-1">
                            <a href="/member_logout" class="flex items-center space-x-3 px-4 py-2 text-gray-300 hover:bg-gray-700 hover:text-red-400 transition-colors">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                <span>Sair</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Modal Editar Perfil -->
    <div id="profile-modal" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-gray-800 rounded-xl border border-gray-700 w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between p-6 border-b border-gray-700">
                <h3 class="text-xl font-bold text-white">Editar Perfil</h3>
                <button onclick="closeProfileModal()" class="text-gray-400 hover:text-white transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="profile-form" class="p-6 space-y-4">
                <div>
                    <label for="profile-nome" class="block text-sm font-medium text-gray-300 mb-2">Nome</label>
                    <input type="text" id="profile-nome" name="nome" value="<?php echo htmlspecialchars($cliente_nome); ?>" required class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label for="profile-email" class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                    <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($cliente_email); ?>" required class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                </div>
                <div>
                    <label for="profile-senha-atual" class="block text-sm font-medium text-gray-300 mb-2">Senha Atual</label>
                    <input type="password" id="profile-senha-atual" name="senha_atual" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Digite para confirmar alterações">
                </div>
                <div>
                    <label for="profile-nova-senha" class="block text-sm font-medium text-gray-300 mb-2">Nova Senha <span class="text-gray-500">(opcional)</span></label>
                    <input type="password" id="profile-nova-senha" name="nova_senha" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Deixe vazio para manter a atual">
                </div>
                <div>
                    <label for="profile-confirmar-senha" class="block text-sm font-medium text-gray-300 mb-2">Confirmar Nova Senha</label>
                    <input type="password" id="profile-confirmar-senha" name="confirmar_senha" class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Repita a nova senha">
                </div>
                <div id="profile-error" class="hidden bg-red-900/30 border border-red-500 text-red-300 px-4 py-3 rounded-lg text-sm"></div>
                <div id="profile-success" class="hidden bg-green-900/30 border border-green-500 text-green-300 px-4 py-3 rounded-lg text-sm"></div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeProfileModal()" class="flex-1 px-4 py-3 bg-gray-700 text-gray-300 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" id="btn-save-profile" class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <?php if (isset($mensagem_erro)) echo $mensagem_erro; ?>

        <!-- Novo Título Premium -->
        <div class_id="intro-header" class="mb-10">
            <h1 class="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-green-400 to-green-500 mb-2">
                Sua Biblioteca de Cursos
            </h1>
            <p class="text-xl text-gray-400">
                Todo seu conhecimento adquirido em um só lugar. Pronto para começar?
            </p>
        </div>


        <?php if (empty($cursos_adquiridos)): ?>
            <!-- Tela de Boas-Vindas / Vazio -->
            <div class="bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700">
                <i data-lucide="inbox" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
                <p class="text-lg font-semibold text-white">Você ainda não possui cursos</p>
                <p class="mt-2 text-sm">Parece que você ainda não adquiriu nenhum produto. Explore nossa loja ou, se você acredita que isso é um erro, por favor, entre em contato com o suporte.</p>
            </div>
        <?php else: ?>
            
            <!-- 
                NOVO LAYOUT: GRID DE CURSOS 
            -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                
                <?php foreach ($cursos_adquiridos as $curso): ?>
                    <!-- O Card do Curso (agora em grid, não swiper) -->
                    <a href="/member_course_view?produto_id=<?php echo $curso['produto_id']; ?>" 
                       class="group bg-gray-800 rounded-2xl shadow-lg overflow-hidden transition-all duration-300 hover:shadow-2xl hover:scale-[1.02] border border-gray-700/50 flex flex-col">
                        
                        <!-- A "Capa" Robusta -->
                        <div class="relative aspect-video overflow-hidden">
                            <?php 
                            // ***********************************************
                            // LÓGICA DE IMAGEM CORRIGIDA E PRIORIZADA
                            // ***********************************************
                            $image_path = null;
                            $placeholder_url = 'https://placehold.co/600x400/1f2937/9ca3af?text=Curso+Sem+Imagem';

                            // 1. Priorizar a foto do PRODUTO (capa principal)
                            if (!empty($curso['produto_foto'])) {
                                $image_path = $upload_dir . $curso['produto_foto'];
                            } 
                            // 2. Se não houver, tentar a imagem do CURSO (módulo)
                            elseif (!empty($curso['curso_imagem_url'])) {
                                // Verificar se é uma URL completa ou um nome de arquivo
                                if (filter_var($curso['curso_imagem_url'], FILTER_VALIDATE_URL)) {
                                    $image_path = $curso['curso_imagem_url']; // É uma URL completa
                                } else {
                                    $image_path = $upload_dir . $curso['curso_imagem_url']; // Assumir que é um arquivo local
                                }
                            }

                            // 3. Se ainda assim for nulo, definir o placeholder diretamente
                            if (empty($image_path)) {
                                $image_path = $placeholder_url; 
                            }
                            ?>
                            <img src="<?php echo htmlspecialchars($image_path); ?>" 
                                 alt="<?php echo htmlspecialchars($curso['curso_titulo'] ?? $curso['produto_nome']); ?>"
                                 class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
                                 onerror="this.onerror=null; this.src='<?php echo $placeholder_url; ?>';">
                            
                            <!-- Overlay de "Play" que aparece no hover -->
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                                <i data-lucide="play-circle" class="w-16 h-16 text-white/80"></i>
                            </div>
                        </div>

                        <!-- Informações do Card -->
                        <div class="p-6 flex flex-col flex-grow">
                            <h3 class="text-2xl font-bold text-white mb-3 line-clamp-2">
                                <?php echo htmlspecialchars($curso['curso_titulo'] ?? $curso['produto_nome']); ?>
                            </h3>
                            <p class="text-gray-400 text-sm mb-4 line-clamp-3 flex-grow">
                                <?php echo htmlspecialchars($curso['curso_descricao'] ?? 'Acesse para ver mais detalhes.'); ?>
                            </p>
                            
                            <!-- Barra de Progresso -->
                            <div class="mt-4">
                                <?php 
                                $total_aulas = (int)($curso['total_aulas'] ?? 0);
                                $aulas_concluidas = (int)($curso['aulas_concluidas'] ?? 0);
                                $progresso_percentual = $total_aulas > 0 ? round(($aulas_concluidas / $total_aulas) * 100) : 0;
                                ?>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-semibold text-green-400">SEU PROGRESSO</span>
                                    <span class="text-sm font-bold text-white"><?php echo $progresso_percentual; ?>% Completo</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2.5">
                                    <div class="bg-green-500 h-2.5 rounded-full transition-all duration-300" style="width: <?php echo $progresso_percentual; ?>%"></div>
                                </div>
                                
                                <?php if (!empty($curso['data_expiracao'])): 
                                    $data_exp = new DateTime($curso['data_expiracao']);
                                    $hoje = new DateTime();
                                    $dias_restantes = $hoje->diff($data_exp)->days;
                                    $is_expiring_soon = $dias_restantes <= 7;
                                ?>
                                <div class="mt-3 flex items-center gap-2 <?php echo $is_expiring_soon ? 'text-yellow-400' : 'text-gray-400'; ?>">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    <span class="text-xs">
                                        <?php if ($is_expiring_soon): ?>
                                            Expira em <?php echo $dias_restantes; ?> dia<?php echo $dias_restantes != 1 ? 's' : ''; ?>!
                                        <?php else: ?>
                                            Acesso até <?php echo $data_exp->format('d/m/Y'); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                <div class="mt-3 flex items-center gap-2 text-green-400">
                                    <i data-lucide="infinity" class="w-4 h-4"></i>
                                    <span class="text-xs">Acesso vitalício</span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>

            </div> <!-- Fim do Grid de Cursos -->
        <?php endif; ?>

        <!-- 
            SEÇÃO DE OFERTAS EXCLUSIVAS (GRID 4 COLUNAS)
        -->
        <h2 class="text-3xl font-extrabold text-gray-100 mb-8 mt-12">Ofertas Exclusivas para Você</h2>
        
        <div id="exclusive-offers-loading" class="bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700" style="display: block;">
            <svg class="animate-spin h-8 w-8 text-green-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.96l2-2.669z"></path>
            </svg>
            <p class="text-lg font-semibold">Carregando ofertas...</p>
        </div>

        <div id="exclusive-offers-empty" class="bg-gray-800 p-8 rounded-lg shadow-md text-center text-gray-400 border border-gray-700" style="display: none;">
            <i data-lucide="tag-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i>
            <p class="text-lg font-semibold text-white">Nenhuma oferta exclusiva disponível.</p>
            <p class="mt-2 text-sm">Fique atento para futuras oportunidades!</p>
        </div>

        <!-- Grid de Ofertas Exclusivas (4 por linha) -->
        <div id="exclusive-offers-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" style="display: none;">
            <!-- Offers will be loaded here by JavaScript -->
        </div>

    </main>

    <script>
        lucide.createIcons();
        
        // Dropdown do Perfil
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profile-dropdown');
            const arrow = document.getElementById('dropdown-arrow');
            dropdown.classList.toggle('hidden');
            arrow.style.transform = dropdown.classList.contains('hidden') ? '' : 'rotate(180deg)';
        }
        
        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(e) {
            const container = document.getElementById('profile-dropdown-container');
            if (container && !container.contains(e.target)) {
                document.getElementById('profile-dropdown').classList.add('hidden');
                document.getElementById('dropdown-arrow').style.transform = '';
            }
        });
        
        // Modal de Perfil
        function openProfileModal() {
            document.getElementById('profile-modal').classList.remove('hidden');
            document.getElementById('profile-dropdown').classList.add('hidden');
            document.getElementById('dropdown-arrow').style.transform = '';
            document.getElementById('profile-error').classList.add('hidden');
            document.getElementById('profile-success').classList.add('hidden');
            document.getElementById('profile-senha-atual').value = '';
            document.getElementById('profile-nova-senha').value = '';
            document.getElementById('profile-confirmar-senha').value = '';
            lucide.createIcons();
        }
        
        function closeProfileModal() {
            document.getElementById('profile-modal').classList.add('hidden');
        }
        
        // Submit do formulário de perfil
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btn-save-profile');
            const errorDiv = document.getElementById('profile-error');
            const successDiv = document.getElementById('profile-success');
            
            const novaSenha = document.getElementById('profile-nova-senha').value;
            const confirmarSenha = document.getElementById('profile-confirmar-senha').value;
            
            // Validação de senha
            if (novaSenha && novaSenha !== confirmarSenha) {
                errorDiv.textContent = 'As senhas não coincidem.';
                errorDiv.classList.remove('hidden');
                successDiv.classList.add('hidden');
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Salvando...';
            errorDiv.classList.add('hidden');
            successDiv.classList.add('hidden');
            
            const formData = {
                nome: document.getElementById('profile-nome').value,
                email: document.getElementById('profile-email').value,
                senha_atual: document.getElementById('profile-senha-atual').value,
                nova_senha: novaSenha
            };
            
            fetch('/api/member_api.php?action=update_member_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message || 'Perfil atualizado com sucesso!';
                    successDiv.classList.remove('hidden');
                    
                    // Atualiza o nome no header se mudou
                    if (data.nome) {
                        const headerName = document.querySelector('#profile-dropdown-container button span');
                        if (headerName) {
                            headerName.textContent = 'Olá, ' + data.nome + '!';
                        }
                    }
                    
                    // Se o email mudou, redireciona para login
                    if (data.email_changed) {
                        setTimeout(() => {
                            alert('Email alterado. Por favor, faça login novamente.');
                            window.location.href = '/member_login';
                        }, 1500);
                    } else {
                        setTimeout(() => closeProfileModal(), 2000);
                    }
                } else {
                    errorDiv.textContent = data.error || 'Erro ao atualizar perfil.';
                    errorDiv.classList.remove('hidden');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                errorDiv.textContent = 'Erro ao atualizar perfil.';
                errorDiv.classList.remove('hidden');
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = 'Salvar';
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uploadDir = '<?php echo $upload_dir; ?>';

            const exclusiveOffersLoading = document.getElementById('exclusive-offers-loading');
            const exclusiveOffersEmpty = document.getElementById('exclusive-offers-empty');
            const exclusiveOffersGrid = document.getElementById('exclusive-offers-grid');

            function formatCurrency(value) {
                return parseFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
            }

            async function fetchExclusiveOffers() {
                exclusiveOffersLoading.style.display = 'block';
                exclusiveOffersEmpty.style.display = 'none';
                exclusiveOffersGrid.style.display = 'none';
                exclusiveOffersGrid.innerHTML = '';

                try {
                    const response = await fetch('/api/api?action=get_member_exclusive_offers');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();

                    if (data.offers && data.offers.length > 0) {
                        data.offers.forEach(offer => {
                            const card = document.createElement('div');
                            
                            const productPhoto = offer.product_photo ? uploadDir + offer.product_photo : 'https://placehold.co/280x160/1f2937/d1d5db?text=Produto';
                            const productPrice = formatCurrency(offer.product_price);
                            const checkoutLink = offer.custom_link ? offer.custom_link : `/checkout?p=${offer.checkout_hash}`;
                            const linkTarget = offer.custom_link ? 'target="_blank" rel="noopener noreferrer"' : '';
                            const buttonText = offer.custom_button_text ? offer.custom_button_text : `Comprar por ${productPrice}`;
                            const productDescription = offer.product_description || 'Oferta exclusiva para você.';
                            
                            card.innerHTML = `
                                <a href="${checkoutLink}" ${linkTarget} class="group bg-gray-800 rounded-xl overflow-hidden border border-gray-700 hover:border-green-500 transition-all duration-300 hover:shadow-xl hover:shadow-green-500/10 flex flex-col h-full">
                                    <div class="relative overflow-hidden">
                                        <img src="${productPhoto}" alt="${offer.product_name}" class="w-full h-40 object-cover transition-transform duration-300 group-hover:scale-105" onerror="this.onerror=null;this.src='https://placehold.co/280x160/1f2937/d1d5db?text=Produto';">
                                        <div class="absolute top-2 right-2 bg-black/60 rounded-full p-1.5">
                                            <i data-lucide="lock" class="w-4 h-4 text-red-400"></i>
                                        </div>
                                        <span class="absolute top-2 left-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full uppercase">
                                            Exclusivo
                                        </span>
                                    </div>
                                    <div class="p-4 flex flex-col flex-grow">
                                        <h3 class="text-lg font-bold text-white mb-2 line-clamp-2">${offer.product_name}</h3>
                                        <p class="text-gray-400 text-sm mb-4 line-clamp-2 flex-grow">${productDescription}</p>
                                        <span class="mt-auto inline-flex items-center justify-center bg-green-600 text-white font-semibold py-2.5 px-4 rounded-lg hover:bg-green-700 transition duration-300 text-sm">
                                            ${buttonText}
                                        </span>
                                    </div>
                                </a>
                            `;
                            exclusiveOffersGrid.appendChild(card);
                        });
                        
                        exclusiveOffersLoading.style.display = 'none';
                        exclusiveOffersGrid.style.display = 'grid';
                        lucide.createIcons();
                    } else {
                        exclusiveOffersLoading.style.display = 'none';
                        exclusiveOffersEmpty.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Error fetching exclusive offers:', error);
                    exclusiveOffersLoading.style.display = 'none';
                    exclusiveOffersEmpty.style.display = 'block';
                    exclusiveOffersEmpty.innerHTML = `<i data-lucide="cloud-off" class="mx-auto w-16 h-16 text-gray-600 mb-4"></i><p class="text-lg font-semibold text-red-500">Erro ao carregar ofertas!</p><p class="mt-2 text-sm text-gray-400">Tente novamente mais tarde ou entre em contato com o suporte.</p>`;
                    lucide.createIcons();
                }
            }

            fetchExclusiveOffers();
        });
    </script>
</body>
</html>