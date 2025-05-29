<?php
require_once __DIR__ . '../includes/config.php';
require_once __DIR__ . '../includes/functions.php';

// Define o cabeçalho para indicar que a resposta é JSON
header('Content-Type: application/json');

// Garante que o usuário esteja logado para evitar acesso não autorizado
if (!usuarioLogado()) {
    echo json_encode(['error' => 'Acesso não autorizado.']);
    exit;
}

$termo_busca = sanitizar($_GET['termo'] ?? '');

$clientes = [];

if (!empty($termo_busca) && strlen($termo_busca) >= 2) { // Mínimo de 2 caracteres para a busca
    try {
        $stmt = $pdo->prepare("
            SELECT id_cliente, nome, cpf_cnpj, email
            FROM clientes
            WHERE nome LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?
            LIMIT 10
        ");
        $param = "%{$termo_busca}%";
        $stmt->execute([$param, $param, $param]);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erro ao buscar clientes via AJAX: " . $e->getMessage());
        // Em um ambiente de produção, não exponha detalhes do erro ao usuário
        echo json_encode(['error' => 'Erro interno do servidor ao buscar clientes.']);
        exit;
    }
}

echo json_encode($clientes);
?>
