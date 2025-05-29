<?php
// Inclui o cabeçalho da página, que contém a conexão com o banco de dados e outras configurações iniciais.
require_once '../../includes/header.php';
// Inclui as funções auxiliares do sistema.
require_once '../../includes/functions.php';

if (!verificarPermissao(['Administrador', 'Atendente'])) {
    redirecionarComMensagem('../../dashboard.php', 'error', 'Você não tem permissão para acessar este módulo.');
}

// Define variáveis para filtros e paginação
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Número de itens por página

// Parâmetros de filtro
$status_filter = $_GET['status'] ?? '';
$data_inicio_filter = $_GET['data_inicio'] ?? '';
$data_fim_filter = $_GET['data_fim'] ?? '';
$cliente_filter = $_GET['cliente'] ?? '';
$tipo_documento_filter = $_GET['tipo_documento'] ?? ''; // Renomeado de tipo_lancamento_filter para tipo_documento_filter

$all_finance_records = []; // Array para armazenar todos os registros combinados

// --- Consulta para Faturas (financeiro_faturas) ---
$params = [];
$where_clauses = ["1=1"];

if (!empty($status_filter)) {
    // A função formatStatusForQuery não é mais necessária, pois usamos os ENUMs diretamente
    $where_clauses[] = "ff.status_fatura = :status_fatura";
    $params[':status_fatura'] = ucwords(str_replace('_', ' ', strtolower($status_filter))); // Garante que o status seja formatado corretamente
}
if (!empty($data_inicio_filter)) {
    $where_clauses[] = "ff.data_emissao >= :data_inicio";
    $params[':data_inicio'] = $data_inicio_filter . ' 00:00:00';
}
if (!empty($data_fim_filter)) {
    $where_clauses[] = "ff.data_emissao <= :data_fim";
    $params[':data_fim'] = $data_fim_filter . ' 23:59:59';
}
if (!empty($cliente_filter)) {
    $where_clauses[] = "c.nome LIKE :cliente";
    $params[':cliente'] = '%' . $cliente_filter . '%';
}
if (!empty($tipo_documento_filter)) {
    $where_clauses[] = "ff.tipo_documento = :tipo_documento";
    $params[':tipo_documento'] = $tipo_documento_filter;
}

$sql_base = "
    SELECT
        ff.id_fatura AS id,
        ff.tipo_documento AS tipo_lancamento,
        c.nome AS nome_cliente,
        ff.valor_total AS valor,
        ff.observacoes AS descricao,
        ff.data_emissao AS data_origem,
        ff.data_vencimento AS data_vencimento,
        ff.status_fatura AS status_origem,
        GROUP_CONCAT(fp.valor_pago ORDER BY fp.data_pagamento SEPARATOR '; ') AS valores_pagos,
        GROUP_CONCAT(fp.data_pagamento ORDER BY fp.data_pagamento SEPARATOR '; ') AS datas_pagamentos,
        GROUP_CONCAT(fp.metodo_pagamento ORDER BY fp.data_pagamento SEPARATOR '; ') AS metodos_pagamentos
    FROM
        financeiro_faturas ff
    JOIN
        clientes c ON ff.id_cliente = c.id_cliente
    LEFT JOIN
        financeiro_pagamentos fp ON ff.id_fatura = fp.id_fatura
    WHERE " . implode(' AND ', $where_clauses) . "
    GROUP BY ff.id_fatura
    ORDER BY ff.data_emissao DESC
";

// Contagem total para paginação
$sql_count = "
    SELECT COUNT(DISTINCT ff.id_fatura)
    FROM financeiro_faturas ff
    JOIN clientes c ON ff.id_cliente = c.id_cliente
    WHERE " . implode(' AND ', $where_clauses);

try {
    // Contar total de registros
    $stmt_count = $pdo->prepare($sql_count);
    foreach ($params as $key => $val) {
        $stmt_count->bindValue($key, $val);
    }
    $stmt_count->execute();
    $total_records = $stmt_count->fetchColumn(); // Use fetchColumn() for COUNT(*)

    $total_pages = ceil($total_records / $limit);
    $offset = ($page - 1) * $limit;

    // Adicionar LIMIT e OFFSET à consulta principal
    $sql_base .= " LIMIT :limit OFFSET :offset";
    $params[':limit'] = $limit;
    $params[':offset'] = $offset;

    $stmt = $pdo->prepare($sql_base);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $financeiro_registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "<div class='alert alert-danger'>Erro ao carregar registros financeiros: " . $e->getMessage() . "</div>";
    error_log("Erro ao carregar registros financeiros: " . $e->getMessage());
    $financeiro_registros = []; // Garante que a variável esteja definida mesmo em caso de erro
    $total_pages = 1;
}

?>

<div class="container-fluid">
    <div class="row">
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Financeiro - Faturas</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="adicionar_fatura.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-plus-circle"></i> Nova Fatura
                    </a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    Filtros
                </div>
                <div class="card-body">
                    <form method="GET" action="listar.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="tipo_documento" class="form-label">Tipo Documento</label>
                            <select class="form-select" id="tipo_documento" name="tipo_documento">
                                <option value="">Todos</option>
                                <option value="OS" <?php echo $tipo_documento_filter == 'OS' ? 'selected' : ''; ?>>Ordem de Serviço</option>
                                <option value="Locação" <?php echo $tipo_documento_filter == 'Locação' ? 'selected' : ''; ?>>Locação</option>
                                <option value="Avulso" <?php echo $tipo_documento_filter == 'Avulso' ? 'selected' : ''; ?>>Avulso</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Status da Fatura</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Pendente" <?php echo $status_filter == 'Pendente' ? 'selected' : ''; ?>>Pendente</option>
                                <option value="Paga" <?php echo $status_filter == 'Paga' ? 'selected' : ''; ?>>Paga</option>
                                <option value="Cancelada" <?php echo $status_filter == 'Cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                <option value="Atrasada" <?php echo $status_filter == 'Atrasada' ? 'selected' : ''; ?>>Atrasada</option>
                                <option value="Parcialmente Paga" <?php echo $status_filter == 'Parcialmente Paga' ? 'selected' : ''; ?>>Parcialmente Paga</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label">Data Início (Emissão)</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="data_fim" class="form-label">Data Fim (Emissão)</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($data_fim_filter); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="cliente" class="form-label">Cliente</label>
                            <input type="text" class="form-control" id="cliente" name="cliente" placeholder="Nome do Cliente" value="<?php echo htmlspecialchars($cliente_filter); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                            <a href="listar.php" class="btn btn-secondary">Limpar Filtros</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID Fatura</th>
                            <th>Tipo Documento</th>
                            <th>Cliente</th>
                            <th>Valor Total</th>
                            <th>Data Emissão</th>
                            <th>Data Vencimento</th>
                            <th>Status</th>
                            <th>Pagamentos</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($financeiro_registros)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Nenhuma fatura encontrada com os filtros aplicados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($financeiro_registros as $registro): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($registro['id']); ?></td>
                                    <td><?php echo htmlspecialchars($registro['tipo_lancamento']); ?></td>
                                    <td><?php echo htmlspecialchars($registro['nome_cliente']); ?></td>
                                    <td>R$ <?php echo number_format($registro['valor'], 2, ',', '.'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($registro['data_origem'])); ?></td>
                                    <td>
                                        <?php
                                            echo (!empty($registro['data_vencimento']) && $registro['data_vencimento'] != '0000-00-00') ? date('d/m/Y', strtotime($registro['data_vencimento'])) : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php
                                            $status_display = strtolower($registro['status_origem']); // Use o status original da fatura
                                            $status_class = 'secondary'; // Default fallback

                                            // Lógica para status 'Atrasada' - pode ser aplicada no front-end
                                            $is_atrasada = false;
                                            if ($status_display == 'pendente' &&
                                                (!empty($registro['data_vencimento']) && $registro['data_vencimento'] != '0000-00-00 00:00:00') &&
                                                (strtotime($registro['data_vencimento']) < time()) ) {
                                                $is_atrasada = true;
                                            }

                                            if ($is_atrasada) {
                                                $status_class = 'danger';
                                                $status_display = 'Atrasada'; // Sobrescreve para exibição
                                            } else {
                                                switch ($status_display) {
                                                    case 'paga': $status_class = 'success'; break;
                                                    case 'pendente': $status_class = 'warning'; break;
                                                    case 'cancelada': $status_class = 'info'; break;
                                                    case 'parcialmente paga': $status_class = 'primary'; break;
                                                    default: $status_class = 'secondary'; break;
                                                }
                                            }
                                            echo $status_class;
                                        ?>"><?php echo ucwords(str_replace('_', ' ', $status_display)); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        // Exibir detalhes de pagamentos se existirem
                                        if (!empty($registro['valores_pagos'])) {
                                            $valores_pagos = explode('; ', $registro['valores_pagos']);
                                            $datas_pagamentos = explode('; ', $registro['datas_pagamentos']);
                                            $metodos_pagamentos = explode('; ', $registro['metodos_pagamentos']);

                                            echo "<ul>";
                                            foreach ($valores_pagos as $index => $valor_pago) {
                                                echo "<li>R$ " . number_format($valor_pago, 2, ',', '.') . " em " . date('d/m/Y H:i', strtotime($datas_pagamentos[$index])) . " (" . htmlspecialchars($metodos_pagamentos[$index]) . ")</li>";
                                            }
                                            echo "</ul>";
                                        } else {
                                            echo "Nenhum pagamento registrado.";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $registro['id']; ?>" class="btn btn-sm btn-info" title="Ver Detalhes">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="editar_fatura.php?id=<?php echo $registro['id']; ?>" class="btn btn-sm btn-primary" title="Editar Fatura">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="adicionar_pagamento.php?fatura_id=<?php echo $registro['id']; ?>" class="btn btn-sm btn-success" title="Adicionar Pagamento">
                                            <i class="bi bi-currency-dollar"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger btn-delete-financeiro" data-id="<?php echo $registro['id']; ?>" data-type="fatura" title="Excluir Fatura">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <nav aria-label="Paginação de faturas">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" tabindex="-1" aria-disabled="true">Anterior</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Próximo</a>
                    </li>
                </ul>
            </nav>

            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmar Exclusão</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Tem certeza de que deseja excluir esta fatura e seus pagamentos? Esta ação é irreversível.
                            <input type="hidden" id="deleteRecordId">
                            <input type="hidden" id="deleteRecordType">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Excluir</button>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php
// Inclui o rodapé da página.
require_once '../../includes/footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
    var deleteButtons = document.querySelectorAll('.btn-delete-financeiro');
    var confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    var deleteRecordIdInput = document.getElementById('deleteRecordId');
    var deleteRecordTypeInput = document.getElementById('deleteRecordType');

    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var type = this.getAttribute('data-type'); // 'fatura'
            deleteRecordIdInput.value = id;
            deleteRecordTypeInput.value = type;
            confirmDeleteModal.show();
        });
    });

    confirmDeleteBtn.addEventListener('click', function() {
        var id = deleteRecordIdInput.value;
        var type = deleteRecordTypeInput.value;

        // Redireciona para um script de exclusão com base no tipo
        window.location.href = `delete_financeiro.php?id=${id}&type=${type}`;
    });
});
</script>
