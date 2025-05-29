<?php
// ajax/buscar_clientes.php
require_once '../includes/config.php'; // Ajuste o caminho conforme a estrutura de pastas
require_once '../includes/functions.php'; // Ajuste o caminho conforme a estrutura de pastas
header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Requisição inválida ou termo de busca ausente.'];

// Verifica se a requisição é GET e se o parâmetro 'q' (query) está presente
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['q'])) {
    $termo_busca = trim($_GET['q']); // Remove espaços em branco do início/fim

    // Verifica se o termo de busca não está vazio após o trim
    if (empty($termo_busca)) {
        $response = ['status' => 'error', 'message' => 'Termo de busca vazio.'];
        echo json_encode($response);
        exit; // Termina a execução se o termo estiver vazio
    }

    $termo_busca_like = '%' . $termo_busca . '%';

    try {
        $stmt = $pdo->prepare("
            SELECT
                id_cliente,
                nome,
                -- Ajustado para usar cpf_cnpj
                CASE
                    WHEN LENGTH(cpf_cnpj) = 11 THEN 'CPF'
                    WHEN LENGTH(cpf_cnpj) = 14 THEN 'CNPJ'
                    ELSE 'Outro'
                END AS tipo_documento,
                cpf_cnpj AS documento -- Usando cpf_cnpj como o campo de documento principal
            FROM clientes
            WHERE LOWER(nome) LIKE LOWER(?) OR LOWER(cpf_cnpj) LIKE LOWER(?)
            ORDER BY nome
            LIMIT 10
        ");
        $stmt->execute([$termo_busca_like, $termo_busca_like]);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($clientes) {
            $response = ['status' => 'success', 'data' => $clientes];
        } else {
            $response = ['status' => 'success', 'message' => 'Nenhum cliente encontrado.', 'data' => []];
        }

    } catch (PDOException $e) {
        // Agora você verá a mensagem de erro específica do PDO no seu log ou na resposta.
        error_log("ERRO FATAL (PDO) ao buscar clientes via AJAX: " . $e->getMessage());
        $response = ['status' => 'error', 'message' => 'Erro interno ao buscar clientes no banco de dados. Detalhe: ' . $e->getMessage()];
    } catch (Exception $e) {
        error_log("ERRO FATAL (Geral) inesperado em buscar_clientes.php: " . $e->getMessage());
        $response = ['status' => 'error', 'message' => 'Um erro inesperado ocorreu. Detalhe: ' . $e->getMessage()];
    }
}

echo json_encode($response);
?>
