<?php

/**
 * Verifica as credenciais do usuário
 */
function verificarCredenciais($username, $senha) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ? AND ativo = 1 LIMIT 1");
        $stmt->execute([$username]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            return $usuario;
        }
    } catch (PDOException $e) {
        error_log("Erro ao verificar credenciais: " . $e->getMessage());
    }
    
    return false;
}

/**
 * Atualiza o último login do usuário
 */
function atualizarUltimoLogin($id_usuario) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
    } catch (PDOException $e) {
        error_log("Erro ao atualizar último login: " . $e->getMessage());
    }
}

/**
 * Verifica se o usuário está logado
 */
function usuarioLogado() {
    return isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true;
}

/**
 * Redireciona para a página de login se o usuário não estiver logado
 */
function requerLogin() {
    if (!usuarioLogado()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Verifica se o usuário tem permissão de acesso
 * @param string|array $nivel_requerido O nível ou array de níveis de permissão requeridos.
 * Ex: 'Administrador', ['Administrador', 'Atendente']
 * @return bool True se o usuário tiver a permissão, false caso contrário.
 */
function verificarPermissao($nivel_requerido) {
    $niveis = ['Atendente' => 1, 'Técnico' => 2, 'Administrador' => 3];
    
    $nivel_usuario = $_SESSION['usuario_nivel'] ?? 'Atendente'; // Padrão 'Atendente' se não definido
    $nivel_requerido_array = is_array($nivel_requerido) ? $nivel_requerido : [$nivel_requerido];
    
    foreach ($nivel_requerido_array as $nivel) {
        if (($niveis[$nivel_usuario] ?? 0) >= ($niveis[$nivel] ?? 0)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Formata valores monetários para exibição no formato brasileiro (R$ 1.234,56)
 * @param float $valor O valor numérico a ser formatado.
 * @return string O valor formatado com o símbolo da moeda.
 */
function formatarMoeda($valor) {
    return MOEDA_SIMBOLO . ' ' . number_format($valor, 2, ',', '.');
}

/**
 * Converte valores monetários do formato brasileiro (1.234,56) para float (1234.56)
 * Ideal para salvar no banco de dados ou realizar cálculos.
 * @param string $valor_string O valor monetário como string no formato brasileiro.
 * @return float O valor numérico em formato float.
 */
function converterParaFloat($valor_string) {
    if (empty($valor_string) && $valor_string !== '0' && $valor_string !== '0,00' && $valor_string !== '0.00') {
        return 0.00;
    }
    $valor_limpo = str_replace('.', '', $valor_string); // Remove pontos de milhar
    $valor_limpo = str_replace(',', '.', $valor_limpo); // Troca vírgula por ponto decimal
    return (float)$valor_limpo;
}


/**
 * Formata datas para exibição
 * @param string $data A data no formato do banco de dados (YYYY-MM-DD HH:MM:SS).
 * @param string $formato O formato desejado para a exibição (padrão 'd/m/Y H:i').
 * @return string A data formatada ou string vazia se a data for inválida.
 */
function formatarData($data, $formato = 'd/m/Y H:i') {
    if (empty($data) || $data == '0000-00-00 00:00:00') {
        return '';
    }
    
    try {
        $date = new DateTime($data);
        return $date->format($formato);
    } catch (Exception $e) {
        error_log("Erro ao formatar data '{$data}': " . $e->getMessage());
        return ''; // Retorna vazio em caso de erro de data inválida
    }
}

/**
 * Sanitiza dados de entrada para prevenir XSS e outros ataques.
 * Pode ser usado para strings ou arrays de strings.
 * @param mixed $dados Os dados a serem sanitizados (string ou array).
 * @return mixed Os dados sanitizados.
 */
function sanitizar($dados) {
    if (is_array($dados)) {
        return array_map('sanitizar', $dados);
    }
    
    // Remove espaços em branco do início e fim e converte caracteres especiais para entidades HTML
    return htmlspecialchars(trim($dados), ENT_QUOTES, 'UTF-8');
}

/**
 * Busca clientes para autocomplete (usado em AJAX)
 * @param string $termo O termo de busca.
 * @return array Um array de clientes encontrados.
 */
function buscarClientes($termo) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id_cliente, nome, cpf_cnpj, telefone, email 
            FROM clientes 
            WHERE nome LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?
            LIMIT 10
        ");
        $termo_like = "%$termo%";
        $stmt->execute([$termo_like, $termo_like, $termo_like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar clientes: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca produtos para autocomplete (usado em AJAX)
 * @param string $termo O termo de busca.
 * @return array Um array de produtos encontrados.
 */
function buscarProdutos($termo) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id_produto, nome_produto, preco_venda, estoque 
            FROM produtos 
            WHERE nome_produto LIKE ? OR descricao LIKE ?
            LIMIT 10
        ");
        $termo_like = "%$termo%";
        $stmt->execute([$termo_like, $termo_like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar produtos: " . $e->getMessage());
        return [];
    }
}

/**
 * Busca serviços para autocomplete (usado em AJAX)
 * @param string $termo O termo de busca.
 * @return array Um array de serviços encontrados.
 */
function buscarServicos($termo) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id_servico, nome_servico, preco 
            FROM servicos 
            WHERE nome_servico LIKE ? OR descricao LIKE ? AND ativo = 1
            LIMIT 10
        ");
        $termo_like = "%$termo%";
        $stmt->execute([$termo_like, $termo_like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar serviços: " . $e->getMessage());
        return [];
    }
}

/**
 * Gera um token CSRF e o armazena na sessão.
 * @return string O token CSRF gerado.
 */
function gerarTokenCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida um token CSRF.
 * @param string $token O token a ser validado.
 * @return bool True se o token for válido, false caso contrário.
 */
function validarTokenCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Exibe mensagens flash armazenadas na sessão.
 */
function exibirMensagemFlash() {
    if (isset($_SESSION['mensagem_flash'])) {
        $mensagem = $_SESSION['mensagem_flash'];
        unset($_SESSION['mensagem_flash']);
        
        $classes = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        $classe = $classes[$mensagem['tipo']] ?? 'alert-info';
        
        echo '<div class="alert ' . $classe . ' alert-dismissible fade show" role="alert">';
        echo $mensagem['mensagem'];
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

/**
 * Redireciona para uma URL com uma mensagem flash.
 *
 * @param string $url O URL para redirecionar.
 * @param string $tipo O tipo de mensagem (e.g., 'success', 'error', 'warning', 'info').
 * @param string $mensagem O texto da mensagem.
 */
function redirecionarComMensagem($url, $tipo, $mensagem) {
    $_SESSION['mensagem_flash'] = [
        'tipo' => $tipo,
        'mensagem' => $mensagem
    ];
    header('Location: ' . $url);
    exit();
}

/**
 * Valida um CPF ou CNPJ.
 * Nota: Esta é uma implementação simplificada. Para produção, use bibliotecas mais robustas.
 * @param string $documento O CPF ou CNPJ a ser validado.
 * @return bool True se o documento for válido, false caso contrário.
 */
function validarCpfCnpj($documento) {
    $documento = preg_replace('/[^0-9]/', '', $documento);
    
    // Valida CPF (simplificado)
    if (strlen($documento) == 11) {
        // Exemplo de validação básica para CPF (apenas dígitos repetidos)
        if (preg_match('/(\d)\1{10}/', $documento)) {
            return false;
        }
        // Adicione aqui a lógica completa de validação de CPF
        return true; 
    } 
    // Valida CNPJ (simplificado)
    elseif (strlen($documento) == 14) {
        // Exemplo de validação básica para CNPJ (apenas dígitos repetidos)
        if (preg_match('/(\d)\1{13}/', $documento)) {
            return false;
        }
        // Adicione aqui a lógica completa de validação de CNPJ
        return true; 
    }
    
    return false;
}

function getConfiguracao($chave, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
        $stmt->execute([$chave]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Erro ao obter configuração '{$chave}': " . $e->getMessage());
        return null;
    }
}
