<?php
require_once '../../includes/config.php';
requerLogin();

if (!verificarPermissao(['Administrador', 'Atendente'])) {
    redirecionarComMensagem('../../dashboard.php', 'error', 'Você não tem permissão para acessar este módulo.');
}

$titulo_pagina = "Listar Financeiro";
$body_id = "page-financeiro-listar";
$breadcrumb = [
    ['texto' => 'Financeiro', 'link' => '#'],
    ['texto' => 'Listar', 'link' => 'listar.php']
];

$tipo_documento = filter_input(INPUT_GET, 'tipo', FILTER_SANITIZE_STRING) ?? 'faturas'; // 'faturas' ou 'recibos'
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$cliente_filter = filter_input(INPUT_GET, 'cliente_id', FILTER_VALIDATE_INT);
$data_inicio_filter = filter_input(INPUT_GET, 'data_inicio', FILTER_SANITIZE_STRING);
$data_fim_filter = filter_input(INPUT_GET, 'data_fim', FILTER_SANITIZE_STRING);
$search_query = sanitizar($_GET['q'] ?? '');

$query_params = [];
$where_clauses = [];

$query_base_faturas = "
    SELECT
        ff.id_fatura, ff.id_os, ff.id_fatura_locacao, ff.id_cliente, ff.data_emissao, ff.data_vencimento, ff.valor_total, ff.status_fatura, ff.tipo_documento,
        c.nome AS nome_cliente,
        os.id_os AS numero_os,
        lf.data_referencia_fatura AS ref_locacao
    FROM
        financeiro_faturas ff
    JOIN
        clientes c ON ff.id_cliente = c.id_cliente
    LEFT JOIN
        ordens_servico os ON ff.id_os = os.id_os
    LEFT JOIN
        locacoes_faturas lf ON ff.id_fatura_locacao = lf.id_fatura_locacao
";

$query_base_recibos = "
    SELECT
        fp.id_pagamento, fp.id_fatura, fp.data_pagamento, fp.valor_pago, fp.metodo_pagamento,
        ff.id_fatura, ff.valor_total AS valor_total_fatura, ff.status_fatura, ff.tipo_documento,
        c.nome AS nome_cliente,
        os.id_os AS numero_os,
        lf.data_referencia_fatura AS ref_locacao
    FROM
        financeiro_pagamentos fp
    JOIN
        financeiro_faturas ff ON fp.id_fatura = ff.id_fatura
    JOIN
        clientes c ON ff.id_cliente = c.id_cliente
    LEFT JOIN
        ordens_servico os ON ff.id_os = os.id_os
    LEFT JOIN
        locacoes_faturas lf ON ff.id_fatura_locacao = lf.id_fatura_locacao
";

if ($tipo_documento === 'faturas') {
    $current_query = $query_base_faturas;
    if ($status_filter) {
        $where_clauses[] = "ff.status_fatura = ?";
        $query_params[] = $status_filter;
    }
    if ($cliente_filter) {
        $where_clauses[] = "ff.id_cliente = ?";
        $query_params[] = $cliente_filter;
    }
    if ($data_inicio_filter) {
        $where_clauses[] = "ff.data_emissao >= ?";
        $query_params[] = $data_inicio_filter . ' 00:00:00';
    }
    if ($data_fim_filter) {
        $where_clauses[] = "ff.data_emissao <= ?";
        $query_params[] = $data_fim_filter . ' 23:59:59';
    }
    if ($search_query) {
        $where_clauses[] = "(c.nome LIKE ? OR os.id_os LIKE ?)";
        $query_params[] = "%" . $search_query . "%";
        $query_params[] = "%" . $search_query . "%";
    }
} else { // recibos
    $current_query = $query_base_recibos;
    if ($status_filter) { // Para recibos, o status se refere ao status da fatura
        $where_clauses[] = "ff.status_fatura = ?";
        $query_params[] = $status_filter;
    }
    if ($cliente_filter) {
        $where_clauses[] = "ff.id_cliente = ?";
        $query_params[] = $cliente_filter;
    }
    if ($data_inicio_filter) {
        $where_clauses[] = "fp.data_pagamento >= ?";
        $query_params[] = $data_inicio_filter . ' 00:00:00';
    }
    if ($data_fim_filter) {
        $where_clauses[] = "fp.data_pagamento <= ?";
        $query_params[] = $data_fim_filter . ' 23:59:59';
    }
    if ($search_query) {
        $where_clauses[] = "(c.nome LIKE ? OR ff.id_fatura LIKE ?)";
        $query_params[] = "%" . $search_query . "%";
        $query_params[] = "%" . $search_query . "%";
    }
}

if (!empty($where_clauses)) {
    $current_query .= " WHERE " . implode(" AND ", $where_clauses);
}

if ($tipo_documento === 'faturas') {
    $current_query .= " ORDER BY ff.data_emissao DESC";
} else {
    $current_query .= " ORDER BY fp.data_pagamento DESC";
}

try {
    $stmt = $pdo->prepare($current_query);
    $stmt->execute($query_params);
    $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Para o filtro de cliente
    $stmt_clientes = $pdo->query("SELECT id_cliente, nome FROM clientes ORDER BY nome");
    $clientes_disponiveis = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao listar documentos financeiros: " . $e->getMessage());
    adicionarMensagem('error', 'Erro ao carregar documentos financeiros: ' . $e->getMessage());
    $documentos = [];
    $clientes_disponiveis = [];
}

include '../../includes/header.php';
?>

<div class="container my-4"> <div class="row justify-content-center"> <main class="col-lg-10 col-md-12 px-md-4"> <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <?php echo ($tipo_documento === 'faturas') ? 'Faturas' : 'Recibos'; ?>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="faturas.php" class="btn btn-sm btn-outline-primary">Nova Fatura</a>
                        <a href="recibos.php" class="btn btn-sm btn-outline-success">Novo Recibo</a>
                    </div>
                </div>
            </div>

            <?php exibirMensagemFlash(); ?>

            <div class="card mb-4">
                <div class="card-header">
                    Filtros
                </div>
                <div class="card-body">
                    <form action="" method="GET" class="row g-3 align-items-end">
                        <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($tipo_documento); ?>">

                        <div class="col-md-4 col-lg-3">
                            <label for="status_filter" class="form-label">Status</label>
                            <select class="form-select" id="status_filter" name="status">
                                <option value="">Todos</option>
                                <?php if ($tipo_documento === 'faturas'): ?>
                                    <option value="Pendente" <?php echo ($status_filter == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="Paga" <?php echo ($status_filter == 'Paga') ? 'selected' : ''; ?>>Paga</option>
                                    <option value="Atrasada" <?php echo ($status_filter == 'Atrasada') ? 'selected' : ''; ?>>Atrasada</option>
                                    <option value="Cancelada" <?php echo ($status_filter == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                    <option value="Parcialmente Paga" <?php echo ($status_filter == 'Parcialmente Paga') ? 'selected' : ''; ?>>Parcialmente Paga</option>
                                <?php else: // Recibos ?>
                                    <option value="Paga" <?php echo ($status_filter == 'Paga') ? 'selected' : ''; ?>>Fatura Paga</option>
                                    <option value="Parcialmente Paga" <?php echo ($status_filter == 'Parcialmente Paga') ? 'selected' : ''; ?>>Fatura Parcialmente Paga</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-8 col-lg-4">
                            <label for="cliente_filter" class="form-label">Cliente</label>
                            <select class="form-select select2-cliente" id="cliente_filter" name="cliente_id" data-placeholder="Selecione um cliente">
                                <option value=""></option>
                                <?php foreach ($clientes_disponiveis as $cliente): ?>
                                    <option value="<?php echo $cliente['id_cliente']; ?>" <?php echo ($cliente_filter == $cliente['id_cliente']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cliente['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="data_inicio_filter" class="form-label">Data Início</label>
                            <input type="date" class="form-control" id="data_inicio_filter" name="data_inicio" value="<?php echo htmlspecialchars($data_inicio_filter); ?>">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="data_fim_filter" class="form-label">Data Fim</label>
                            <input type="date" class="form-control" id="data_fim_filter" name="data_fim" value="<?php echo htmlspecialchars($data_fim_filter); ?>">
                        </div>
                        <div class="col-md-8 col-lg-3">
                            <label for="search_query" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="search_query" name="q" placeholder="Buscar por cliente, OS, etc." value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-4 col-lg-1 d-grid">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                        <div class="col-md-4 col-lg-1 d-grid">
                            <a href="listar.php?tipo=<?php echo htmlspecialchars($tipo_documento); ?>" class="btn btn-secondary">Limpar</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <?php if ($tipo_documento === 'faturas'): ?>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID Fatura</th>
                                <th>Cliente</th>
                                <th>Origem</th>
                                <th>Data Emissão</th>
                                <th>Vencimento</th>
                                <th>Valor Total</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documentos)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">Nenhuma fatura encontrada.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documentos as $fatura): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fatura['id_fatura']); ?></td>
                                        <td><?php echo htmlspecialchars($fatura['nome_cliente']); ?></td>
                                        <td>
                                            <?php
                                            if ($fatura['tipo_documento'] === 'OS' && $fatura['numero_os']) {
                                                echo 'OS: <a href="../ordens/view.php?id=' . $fatura['numero_os'] . '">' . htmlspecialchars($fatura['numero_os']) . '</a>';
                                            } elseif ($fatura['tipo_documento'] === 'Locação' && $fatura['ref_locacao']) {
                                                echo 'Locação Ref: ' . formatarData($fatura['ref_locacao'], 'm/Y');
                                            } else {
                                                echo 'Avulso';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo formatarData($fatura['data_emissao']); ?></td>
                                        <td><?php echo formatarData($fatura['data_vencimento']); ?></td>
                                        <td><?php echo formatarMoeda($fatura['valor_total']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeClass($fatura['status_fatura']); ?>">
                                                <?php echo htmlspecialchars($fatura['status_fatura']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-nowrap">
                                                <a href="view.php?tipo=fatura&id=<?php echo $fatura['id_fatura']; ?>" class="btn btn-info btn-sm me-1" title="Ver Detalhes">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="faturas.php?id=<?php echo $fatura['id_fatura']; ?>" class="btn btn-warning btn-sm me-1" title="Editar Fatura">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm btn-excluir" data-id="<?php echo $fatura['id_fatura']; ?>" data-tipo="fatura" title="Excluir Fatura">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php else: // Recibos ?>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID Recibo</th>
                                <th>Fatura Ref.</th>
                                <th>Cliente</th>
                                <th>Data Pagamento</th>
                                <th>Valor Pago</th>
                                <th>Método</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documentos)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">Nenhum recibo encontrado.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documentos as $recibo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($recibo['id_pagamento']); ?></td>
                                        <td>
                                            <a href="view.php?tipo=fatura&id=<?php echo $recibo['id_fatura']; ?>" title="Ver Fatura">
                                                Fatura #<?php echo htmlspecialchars($recibo['id_fatura']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($recibo['nome_cliente']); ?></td>
                                        <td><?php echo formatarData($recibo['data_pagamento']); ?></td>
                                        <td><?php echo formatarMoeda($recibo['valor_pago']); ?></td>
                                        <td><?php echo htmlspecialchars($recibo['metodo_pagamento']); ?></td>
                                        <td>
                                            <div class="d-flex flex-nowrap">
                                                <a href="view.php?tipo=recibo&id=<?php echo $recibo['id_pagamento']; ?>" class="btn btn-info btn-sm me-1" title="Ver Detalhes">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="recibos.php?id=<?php echo $recibo['id_pagamento']; ?>" class="btn btn-warning btn-sm me-1" title="Editar Recibo">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-danger btn-sm btn-excluir" data-id="<?php echo $recibo['id_pagamento']; ?>" data-tipo="recibo" title="Excluir Recibo">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializa Select2 para clientes
        $('.select2-cliente').select2({
            theme: "bootstrap-5",
            width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
            placeholder: $(this).data('placeholder'),
            allowClear: true, // Permite limpar a seleção
            minimumInputLength: 0, // Não requer digitação mínima para buscar (para popular com todos os clientes)
            // Se você quiser autocomplete para clientes, você precisaria de um endpoint AJAX aqui.
            // Por enquanto, ele carrega todos os clientes no PHP e o Select2 faz a busca local.
        });

        // Lidar com a exclusão de documentos financeiros
        document.querySelectorAll('.btn-excluir').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const tipo = this.dataset.tipo; // 'fatura' ou 'recibo'
                const confirmacao = confirm(`Tem certeza que deseja excluir este ${tipo === 'fatura' ? 'fatura' : 'recibo'}?`);

                if (confirmacao) {
                    fetch('../../ajax/financeiro_acoes.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=excluir_${tipo}&id=${id}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                            location.reload(); // Recarrega a página para atualizar a lista
                        } else {
                            alert('Erro: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Erro:', error);
                        alert('Ocorreu um erro ao tentar excluir o documento.');
                    });
                }
            });
        });
    });
</script>
