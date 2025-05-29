<?php
// ALL REDIRECTION AND CORE LOGIC MUST COME BEFORE including the header.
// This ensures no output is sent before potential redirects.

require_once '../../includes/config.php'; // Ensure config is loaded first for functions and PDO

// Requer que o usuário esteja logado
requerLogin();

// Verifica permissão para acessar este módulo
if (!verificarPermissao(['Administrador', 'Técnico', 'Atendente'])) {
    redirecionarComMensagem('../../dashboard.php', 'error', 'Você não tem permissão para acessar este módulo.');
}

$titulo_pagina = "Editar Ordem de Serviço";
$body_id = "page-ordens-editar"; // Adicione um ID para o body se necessário para CSS específico
$breadcrumb = [
    ['texto' => 'Ordens de Serviço', 'link' => 'listar.php'],
    ['texto' => 'Editar', 'link' => '']
];

$id_os = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_os) {
    redirecionarComMensagem('listar.php', 'error', 'ID da Ordem de Serviço inválido.');
}

$errors = [];
$old_data = [];
$servicos_disponiveis = null;
$produtos_disponiveis = null;
$clientes_disponiveis = [];
$ordem_servico = null;

try {
    // Carregar dados da Ordem de Serviço
    $stmt_os = $pdo->prepare("SELECT * FROM ordens_servico WHERE id_os = ?");
    $stmt_os->execute([$id_os]);
    $ordem_servico = $stmt_os->fetch(PDO::FETCH_ASSOC);

    if (!$ordem_servico) {
        redirecionarComMensagem('listar.php', 'error', 'Ordem de Serviço não encontrada.');
    }

    // Carregar serviços da OS
    $stmt_os_servicos = $pdo->prepare("
        SELECT os.id_servico, s.nome_servico, os.quantidade, os.preco_unitario, os.subtotal
        FROM os_servicos os
        JOIN servicos s ON os.id_servico = s.id_servico
        WHERE os.id_os = ?
    ");
    $stmt_os_servicos->execute([$id_os]);
    $servicos_selecionados_db = $stmt_os_servicos->fetchAll(PDO::FETCH_ASSOC);

    // Carregar produtos da OS
    $stmt_os_produtos = $pdo->prepare("
        SELECT op.id_produto, p.nome_produto, op.quantidade, op.preco_unitario, op.subtotal, p.estoque
        FROM os_produtos op
        JOIN produtos p ON op.id_produto = p.id_produto
        WHERE op.id_os = ?
    ");
    $stmt_os_produtos->execute([$id_os]);
    $produtos_selecionados_db = $stmt_os_produtos->fetchAll(PDO::FETCH_ASSOC);

    // Carregar serviços, produtos e clientes para os dropdowns (ativos)
    $stmt_servicos = $pdo->query("SELECT id_servico, nome_servico, preco FROM servicos WHERE ativo = TRUE ORDER BY nome_servico");
    $servicos_disponiveis = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);

    $stmt_produtos = $pdo->query("SELECT id_produto, nome_produto, preco_venda, estoque FROM produtos ORDER BY nome_produto"); // Inclui produtos com estoque zero para edição
    $produtos_disponiveis = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

    $stmt_clientes = $pdo->query("SELECT id_cliente, nome FROM clientes ORDER BY nome");
    $clientes_disponiveis = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao carregar dados para editar OS: " . $e->getMessage());
    redirecionarComMensagem('listar.php', 'error', 'Erro ao carregar dados da Ordem de Serviço: ' . $e->getMessage());
}

// Se o formulário foi enviado (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação do token CSRF
    if (!validarTokenCSRF($_POST['csrf_token'])) {
        redirecionarComMensagem("editar.php?id={$id_os}", 'error', 'Erro de segurança: Token CSRF inválido.');
    }

    // Coleta e sanitiza os dados do formulário
    $id_cliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
    $equipamento = sanitizar($_POST['equipamento'] ?? '');
    $numero_serie = sanitizar($_POST['numero_serie'] ?? '');
    $defeito_relatado = sanitizar($_POST['defeito_relatado'] ?? '');
    $solucao_aplicada = sanitizar($_POST['solucao_aplicada'] ?? '');
    $observacoes_tecnicas = sanitizar($_POST['observacoes_tecnicas'] ?? '');
    $status = sanitizar($_POST['status'] ?? '');
    $valor_desconto = filter_input(INPUT_POST, 'valor_desconto', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0]]) ?: 0.00;

    $servicos_selecionados = json_decode($_POST['servicos_selecionados_json'] ?? '[]', true);
    $produtos_selecionados = json_decode($_POST['produtos_selecionados_json'] ?? '[]', true);

    // Armazena os dados no old_data para repopular o formulário em caso de erro
    $old_data = compact('id_cliente', 'equipamento', 'numero_serie', 'defeito_relatado', 'solucao_aplicada', 'observacoes_tecnicas', 'status', 'valor_desconto');
    $old_data['servicos_selecionados'] = $servicos_selecionados;
    $old_data['produtos_selecionados'] = $produtos_selecionados;

    // Validação básica dos campos
    if (empty($id_cliente)) {
        $errors[] = 'O campo Cliente é obrigatório.';
    }
    if (empty($equipamento)) {
        $errors[] = 'O campo Equipamento é obrigatório.';
    }
    if (empty($defeito_relatado)) {
        $errors[] = 'O campo Defeito Relatado é obrigatório.';
    }
    $status_validos = ['Aberta', 'Em Andamento', 'Concluída', 'Cancelada', 'Aguardando Peças', 'Aguardando Aprovação'];
    if (!in_array($status, $status_validos)) {
        $errors[] = 'Status da Ordem de Serviço inválido.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $id_usuario_fechamento = null;
            if ($status === 'Concluída' || $status === 'Cancelada') {
                $id_usuario_fechamento = $_SESSION['id_usuario']; // ID do usuário logado que está fechando/cancelando
            } else {
                // Se o status mudar de Concluída/Cancelada para outro, limpar data_fechamento e id_usuario_fechamento
                if ($ordem_servico['status'] === 'Concluída' || $ordem_servico['status'] === 'Cancelada') {
                    $stmt_clear_fechamento = $pdo->prepare("UPDATE ordens_servico SET data_fechamento = NULL, id_usuario_fechamento = NULL WHERE id_os = ?");
                    $stmt_clear_fechamento->execute([$id_os]);
                }
            }
            $data_fechamento = ($status === 'Concluída' || $status === 'Cancelada') ? date('Y-m-d H:i:s') : null;


            // 1. Atualizar a tabela ordens_servico
            $stmt_update_os = $pdo->prepare("
                UPDATE ordens_servico SET
                    id_cliente = ?,
                    equipamento = ?,
                    numero_serie = ?,
                    defeito_relatado = ?,
                    solucao_aplicada = ?,
                    observacoes_tecnicas = ?,
                    status = ?,
                    valor_desconto = ?,
                    data_fechamento = ?,
                    id_usuario_fechamento = ?
                WHERE id_os = ?
            ");
            $stmt_update_os->execute([
                $id_cliente,
                $equipamento,
                $numero_serie,
                $defeito_relatado,
                $solucao_aplicada,
                $observacoes_tecnicas,
                $status,
                $valor_desconto,
                $data_fechamento,
                $id_usuario_fechamento,
                $id_os
            ]);

            $valor_total_servicos = 0;
            $valor_total_produtos = 0;

            // 2. Processar serviços
            // Apaga todos os serviços existentes para esta OS e insere os novos (simples, mas eficaz para pequenas listas)
            $stmt_delete_servicos = $pdo->prepare("DELETE FROM os_servicos WHERE id_os = ?");
            $stmt_delete_servicos->execute([$id_os]);

            foreach ($servicos_selecionados as $servico) {
                // Buscar o preço atual do serviço do banco de dados para evitar manipulação de preço pelo cliente
                $stmt_preco_servico = $pdo->prepare("SELECT preco FROM servicos WHERE id_servico = ?");
                $stmt_preco_servico->execute([$servico['id_servico']]);
                $preco_real = $stmt_preco_servico->fetchColumn();

                if ($preco_real === false) {
                    throw new Exception("Serviço com ID {$servico['id_servico']} não encontrado ou inativo.");
                }

                $quantidade = $servico['quantidade'];
                $subtotal = $quantidade * $preco_real;

                $stmt_insert_servico = $pdo->prepare("INSERT INTO os_servicos (id_os, id_servico, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert_servico->execute([$id_os, $servico['id_servico'], $quantidade, $preco_real, $subtotal]);
                $valor_total_servicos += $subtotal;
            }

            // 3. Processar produtos
            // Lógica de estoque: precisamos reverter o estoque dos produtos antigos e subtrair dos novos.
            // Primeiro, pegamos a lista de produtos atual da OS no DB
            $stmt_current_os_products = $pdo->prepare("SELECT id_produto, quantidade FROM os_produtos WHERE id_os = ?");
            $stmt_current_os_products->execute([$id_os]);
            $current_os_products = $stmt_current_os_products->fetchAll(PDO::FETCH_KEY_PAIR); // [id_produto => quantidade]

            // Reverter estoque dos produtos removidos ou com quantidade alterada
            foreach ($current_os_products as $prod_id => $old_qty) {
                $found_in_new = false;
                foreach ($produtos_selecionados as $new_prod) {
                    if ($new_prod['id_produto'] == $prod_id) {
                        $found_in_new = true;
                        // Se a quantidade mudou, ajustar o estoque
                        if ($new_prod['quantidade'] < $old_qty) {
                            $diff_qty = $old_qty - $new_prod['quantidade'];
                            $stmt_add_stock = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id_produto = ?");
                            $stmt_add_stock->execute([$diff_qty, $prod_id]);
                        } else if ($new_prod['quantidade'] > $old_qty) {
                            $diff_qty = $new_prod['quantidade'] - $old_qty;
                            $stmt_subtract_stock = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id_produto = ?");
                            $stmt_subtract_stock->execute([$diff_qty, $prod_id]);
                        }
                        break;
                    }
                }
                if (!$found_in_new) {
                    // Produto foi removido, devolver ao estoque
                    $stmt_add_stock = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id_produto = ?");
                    $stmt_add_stock->execute([$old_qty, $prod_id]);
                }
            }

            // Inserir/Atualizar produtos na os_produtos
            // Apaga todos os produtos existentes para esta OS e insere os novos (simplifica o controle, mas o estoque já foi ajustado)
            $stmt_delete_produtos = $pdo->prepare("DELETE FROM os_produtos WHERE id_os = ?");
            $stmt_delete_produtos->execute([$id_os]);

            foreach ($produtos_selecionados as $produto) {
                $stmt_produto_info = $pdo->prepare("SELECT preco_venda FROM produtos WHERE id_produto = ?");
                $stmt_produto_info->execute([$produto['id_produto']]);
                $preco_real = $stmt_produto_info->fetchColumn();

                if ($preco_real === false) {
                    throw new Exception("Produto com ID {$produto['id_produto']} não encontrado.");
                }
                
                $quantidade = $produto['quantidade'];
                $subtotal = $quantidade * $preco_real;

                // Para novos produtos (que não estavam na OS antes) ou aumento de quantidade, o estoque já foi ajustado na lógica acima.
                // Mas garantimos que não estamos subtraindo a mais.

                $stmt_insert_produto = $pdo->prepare("INSERT INTO os_produtos (id_os, id_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert_produto->execute([$id_os, $produto['id_produto'], $quantidade, $preco_real, $subtotal]);
                $valor_total_produtos += $subtotal;
            }

            // 4. Calcular valor final e atualizar OS
            $valor_final = ($valor_total_servicos + $valor_total_produtos) - $valor_desconto;

            $stmt_update_final_values = $pdo->prepare("
                UPDATE ordens_servico SET
                    valor_total_servicos = ?,
                    valor_total_produtos = ?,
                    valor_final = ?
                WHERE id_os = ?
            ");
            $stmt_update_final_values->execute([$valor_total_servicos, $valor_total_produtos, $valor_final, $id_os]);

            $pdo->commit();
            redirecionarComMensagem('listar.php', 'success', 'Ordem de Serviço #' . $id_os . ' atualizada com sucesso!');

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erro ao editar OS: " . $e->getMessage());
            $errors[] = 'Erro ao atualizar Ordem de Serviço: ' . $e->getMessage();
            // Re-popula os dados antigos em caso de erro
            $old_data['servicos_selecionados'] = $servicos_selecionados;
            $old_data['produtos_selecionados'] = $produtos_selecionados;
        }
    } else {
        // Se houver erros de validação, os dados antigos já estão em $old_data
        // Os serviços e produtos selecionados já estão em $old_data['servicos_selecionados'] e $old_data['produtos_selecionados']
    }
} else {
    // Se a página for carregada via GET, usa os dados do DB para preencher old_data
    $old_data = $ordem_servico;
    $old_data['servicos_selecionados'] = $servicos_selecionados_db;
    // Para produtos selecionados, precisamos adicionar o estoque real para validação no JS
    $old_data['produtos_selecionados'] = array_map(function($prod) use ($produtos_disponiveis) {
        $found_prod = array_values(array_filter($produtos_disponiveis, fn($p) => $p['id_produto'] == $prod['id_produto']));
        // Ajusta o estoque disponível do produto para considerar a quantidade já selecionada nesta OS
        $prod['estoque_disponivel'] = ($found_prod[0]['estoque'] ?? 0) + $prod['quantidade'];
        return $prod;
    }, $produtos_selecionados_db);

}

$csrf_token = gerarTokenCSRF();

require_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Formulário de Edição de Ordem de Serviço #<?php echo htmlspecialchars($id_os); ?></h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo sanitizar($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="editar.php?id=<?php echo htmlspecialchars($id_os); ?>" method="POST" id="formOrdemServico">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="servicos_selecionados_json" id="servicos_selecionados_json">
                        <input type="hidden" name="produtos_selecionados_json" id="produtos_selecionados_json">

                        <fieldset class="mb-4 p-3 border rounded">
                            <legend class="float-none w-auto px-2 fs-5">Informações da OS</legend>
                            <div class="row g-3">
                                <div class="col-md-6 col-lg-4">
                                    <label for="id_cliente" class="form-label">Cliente <span class="text-danger">*</span></label>
                                    <select class="form-select select2-clientes" id="id_cliente" name="id_cliente" required>
                                        <option value="">Selecione um Cliente</option>
                                        <?php foreach ($clientes_disponiveis as $cliente): ?>
                                            <option value="<?php echo htmlspecialchars($cliente['id_cliente']); ?>" <?php echo (isset($old_data['id_cliente']) && (int)$old_data['id_cliente'] === (int)$cliente['id_cliente']) ? 'selected' : ''; ?>>
                                                <?php echo sanitizar($cliente['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <label for="equipamento" class="form-label">Equipamento <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="equipamento" name="equipamento" value="<?php echo sanitizar($old_data['equipamento'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <label for="numero_serie" class="form-label">Número de Série</label>
                                    <input type="text" class="form-control" id="numero_serie" name="numero_serie" value="<?php echo sanitizar($old_data['numero_serie'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="defeito_relatado" class="form-label">Defeito Relatado <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="defeito_relatado" name="defeito_relatado" rows="3" required><?php echo sanitizar($old_data['defeito_relatado'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label for="solucao_aplicada" class="form-label">Solução Aplicada</label>
                                    <textarea class="form-control" id="solucao_aplicada" name="solucao_aplicada" rows="3"><?php echo sanitizar($old_data['solucao_aplicada'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label for="observacoes_tecnicas" class="form-label">Observações Técnicas</label>
                                    <textarea class="form-control" id="observacoes_tecnicas" name="observacoes_tecnicas" rows="3"><?php echo sanitizar($old_data['observacoes_tecnicas'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Aberta" <?php echo (isset($old_data['status']) && $old_data['status'] === 'Aberta') ? 'selected' : ''; ?>>Aberta</option>
                                        <option value="Em Andamento" <?php echo (isset($old_data['status']) && $old_data['status'] === 'Em Andamento') ? 'selected' : ''; ?>>Em Andamento</option>
                                        <option value="Aguardando Peças" <?php echo (isset($old_data['status']) && $old_data['status'] === 'Aguardando Peças') ? 'selected' : ''; ?>>Aguardando Peças</option>
                                        <option value="Aguardando Aprovação" <?php echo (isset($old_data['status']) && $old_data['status'] === 'Aguardando Aprovação') ? 'selected' : ''; ?>>Aguardando Aprovação</option>
                                        <option value="Concluída" <?php echo (isset($old_data['status']) && $old_data['status'] === 'Concluída') ? 'selected' : ''; ?>>Concluída</option>
                                        <option value="Cancelada" <?php echo (isset($old_data['status']) && $old_data['status'] === 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="data_abertura" class="form-label">Data Abertura</label>
                                    <input type="text" class="form-control" id="data_abertura" value="<?php echo formatarData($ordem_servico['data_abertura'] ?? ''); ?>" readonly>
                                </div>
                                <div class="col-md-4">
                                    <label for="data_fechamento" class="form-label">Data Fechamento</label>
                                    <input type="text" class="form-control" id="data_fechamento" value="<?php echo formatarData($ordem_servico['data_fechamento'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 p-3 border rounded">
                            <legend class="float-none w-auto px-2 fs-5">Serviços</legend>
                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-md-7 col-lg-6">
                                    <label for="servico_add" class="form-label">Adicionar Serviço</label>
                                    <select class="form-select select2-servicos" id="servico_add">
                                        <option value="">Selecione um Serviço</option>
                                        <?php foreach ($servicos_disponiveis as $servico): ?>
                                            <option value="<?php echo htmlspecialchars($servico['id_servico']); ?>" data-nome="<?php echo sanitizar($servico['nome_servico']); ?>" data-preco="<?php echo htmlspecialchars($servico['preco']); ?>">
                                                <?php echo sanitizar($servico['nome_servico']) . ' (R$ ' . formatarMoeda($servico['preco']) . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 col-lg-2">
                                    <label for="quantidade_servico" class="form-label">Qtd.</label>
                                    <input type="number" class="form-control" id="quantidade_servico" value="1" min="1">
                                </div>
                                <div class="col-md-3 col-lg-4">
                                    <button type="button" class="btn btn-info w-100" id="add_servico_btn"><i class="bi bi-plus-circle me-1"></i> Adicionar Serviço</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle" id="tabela_servicos_os">
                                    <thead>
                                        <tr>
                                            <th>Serviço</th>
                                            <th style="width: 120px;">Qtd.</th>
                                            <th style="width: 150px;">Preço Unit.</th>
                                            <th style="width: 150px;">Subtotal</th>
                                            <th style="width: 80px;" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($old_data['servicos_selecionados']) && !empty($old_data['servicos_selecionados'])): ?>
                                            <?php foreach ($old_data['servicos_selecionados'] as $s_old): ?>
                                                <tr data-id="<?php echo htmlspecialchars($s_old['id_servico']); ?>" data-type="servico">
                                                    <td><?php echo sanitizar($s_old['nome_servico']); ?></td>
                                                    <td><input type="number" class="form-control form-control-sm item-quantidade" value="<?php echo htmlspecialchars($s_old['quantidade']); ?>" min="1"></td>
                                                    <td><input type="number" class="form-control form-control-sm item-preco-unitario" value="<?php echo number_format($s_old['preco_unitario'], 2, '.', ''); ?>" step="0.01" min="0"></td>
                                                    <td class="item-subtotal"><?php echo formatarMoeda($s_old['subtotal']); ?></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-sm remover-item" title="Remover"><i class="bi bi-trash"></i></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Total Serviços:</th>
                                            <th id="total_servicos_os">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </fieldset>

                        <fieldset class="mb-4 p-3 border rounded">
                            <legend class="float-none w-auto px-2 fs-5">Produtos/Peças</legend>
                            <div class="row g-3 mb-3 align-items-end">
                                <div class="col-md-7 col-lg-6">
                                    <label for="produto_add" class="form-label">Adicionar Produto</label>
                                    <select class="form-select select2-produtos" id="produto_add">
                                        <option value="">Selecione um Produto</option>
                                        <?php foreach ($produtos_disponiveis as $produto): ?>
                                            <option value="<?php echo htmlspecialchars($produto['id_produto']); ?>" data-nome="<?php echo sanitizar($produto['nome_produto']); ?>" data-preco="<?php echo htmlspecialchars($produto['preco_venda']); ?>" data-estoque="<?php echo htmlspecialchars($produto['estoque']); ?>">
                                                <?php echo sanitizar($produto['nome_produto']) . ' (R$ ' . formatarMoeda($produto['preco_venda']) . ' - Estoque: ' . $produto['estoque'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 col-lg-2">
                                    <label for="quantidade_produto" class="form-label">Qtd.</label>
                                    <input type="number" class="form-control" id="quantidade_produto" value="1" min="1">
                                </div>
                                <div class="col-md-3 col-lg-4">
                                    <button type="button" class="btn btn-info w-100" id="add_produto_btn"><i class="bi bi-plus-circle me-1"></i> Adicionar Produto</button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle" id="tabela_produtos_os">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th style="width: 120px;">Qtd.</th>
                                            <th style="width: 150px;">Preço Unit.</th>
                                            <th style="width: 150px;">Subtotal</th>
                                            <th style="width: 80px;" class="text-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($old_data['produtos_selecionados']) && !empty($old_data['produtos_selecionados'])): ?>
                                            <?php foreach ($old_data['produtos_selecionados'] as $p_old): ?>
                                                <tr data-id="<?php echo htmlspecialchars($p_old['id_produto']); ?>" data-type="produto">
                                                    <td><?php echo sanitizar($p_old['nome_produto']); ?></td>
                                                    <td><input type="number" class="form-control form-control-sm item-quantidade" value="<?php echo htmlspecialchars($p_old['quantidade']); ?>" min="1" data-max-estoque="<?php echo htmlspecialchars($p_old['estoque_disponivel'] ?? 9999); ?>"></td>
                                                    <td><input type="number" class="form-control form-control-sm item-preco-unitario" value="<?php echo number_format($p_old['preco_unitario'], 2, '.', ''); ?>" step="0.01" min="0"></td>
                                                    <td class="item-subtotal"><?php echo formatarMoeda($p_old['subtotal']); ?></td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-danger btn-sm remover-item" title="Remover"><i class="bi bi-trash"></i></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Total Produtos:</th>
                                            <th id="total_produtos_os">R$ 0,00</th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </fieldset>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4 offset-md-8">
                                <label for="valor_desconto" class="form-label">Desconto (R$)</label>
                                <input type="number" class="form-control form-control-lg" id="valor_desconto" name="valor_desconto" value="<?php echo number_format($old_data['valor_desconto'] ?? 0, 2, '.', ''); ?>" step="0.01" min="0">
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4 offset-md-8">
                                <div class="input-group">
                                    <span class="input-group-text bg-primary text-white fw-bold">Valor Total OS:</span>
                                    <input type="text" class="form-control form-control-lg text-end fw-bold bg-light" id="valor_final_os" value="R$ 0,00" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row justify-content-end">
                            <button type="submit" class="btn btn-primary me-md-2 mb-2 mb-md-0"><i class="bi bi-save me-1"></i> Salvar Alterações</button>
                            <a href="listar.php" class="btn btn-secondary"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>

<script src="../../assets/js/modulos/ordens.js"></script>
<script>
    // Dados para inicialização do JS (passados do PHP)
    const initialServicosSelecionados = <?php echo json_encode($old_data['servicos_selecionados'] ?? []); ?>;
    const initialProdutosSelecionados = <?php echo json_encode($old_data['produtos_selecionados'] ?? []); ?>;
    const todosServicosDisponiveis = <?php echo json_encode($servicos_disponiveis); ?>;
    const todosProdutosDisponiveis = <?php echo json_encode($produtos_disponiveis); ?>;

    $(document).ready(function() {
        // Inicializa as máscaras (se necessário, pode ser no app.js global)
        // Ex: $('#numero_serie').mask('AAAAAAAAAAAAAA'); // Exemplo de máscara, se houver um padrão
        
        // Chamada da função de inicialização do JS do módulo ordens.js
        initOrdemServicoForm(
            initialServicosSelecionados, 
            initialProdutosSelecionados, 
            todosServicosDisponiveis, 
            todosProdutosDisponiveis
        );
    });
</script>
