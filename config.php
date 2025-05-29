<?php
// Inicia a sessão PHP. É crucial que esta linha seja a primeira no arquivo.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// *** NOVO: Definir a base do caminho absoluto do seu site ***
// Esta variável deve ser configurada para a raiz do seu projeto web.
// Por exemplo, se seu site está em 'http://localhost/meu_sistema/', defina-a como '/meu_sistema/'.
// Se seu site está na raiz do domínio (ex: 'http://localhost/'), defina-a como '/'.
define('BASE_URL', '/'); // Ou ajuste conforme a sua estrutura de diretórios no servidor web

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sisos');
define('DB_CHARSET', 'utf8mb4');

// Configurações do sistema (valores padrão, serão sobrescritos pelo DB se existirem)
define('SISTEMA_NOME', 'Sistema de Ordem de Serviço');
define('SISTEMA_VERSAO', '1.0.0');
define('PAGINA_PADRAO', 'dashboard.php'); // Página para onde o usuário é redirecionado após o login

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Conexão com o banco de dados
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION, // Lança exceções em caso de erros
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // Retorna linhas como arrays associativos
        PDO::ATTR_EMULATE_PREPARES  => false,                // Desativa a emulação de prepared statements para segurança
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Em um ambiente de produção, você deve logar o erro e exibir uma mensagem genérica ao usuário.
    // Para desenvolvimento, podemos mostrar o erro.
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função para carregar configurações do banco de dados
function carregarConfiguracoes() {
    global $pdo;
    $configuracoes = [];
    
    try {
        $stmt = $pdo->query("SELECT chave, valor FROM configuracoes");
        while ($row = $stmt->fetch()) {
            $configuracoes[$row['chave']] = $row['valor'];
        }
    } catch (PDOException $e) {
        // Loga o erro, mas não interrompe a execução, pois as configurações padrão serão usadas.
        error_log("Erro ao carregar configurações do banco de dados: " . $e->getMessage());
    }
    
    return $configuracoes;
}

// Carrega configurações do banco de dados
$configuracoes = carregarConfiguracoes();

// Define constantes para configurações importantes, usando valores do DB ou padrão
// *** MODIFICADO: Usar BASE_URL para o caminho da logo ***
define('SISTEMA_LOGO', BASE_URL . ($configuracoes['logo_url'] ?? 'assets/img/logo.png')); // Caminho absoluto para a imagem
define('SISTEMA_EMPRESA', $configuracoes['nome_empresa'] ?? 'Minha Empresa');
define('MOEDA_SIMBOLO', $configuracoes['moeda_simbolo'] ?? 'R$');

// Inclui o arquivo de funções gerais.
// É importante que 'functions.php' seja incluído após a conexão PDO ser estabelecida.
require_once __DIR__ . '/functions.php';

// Verifica se o usuário já está logado e, se sim, redireciona para o dashboard.
// Isso evita que um usuário logado acesse a página de login novamente.
if (usuarioLogado() && basename($_SERVER['PHP_SELF']) === 'login.php') {
    header('Location: ' . PAGINA_PADRAO);
    exit();
}
?>
