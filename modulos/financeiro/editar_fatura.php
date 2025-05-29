<?php
// Inclui o cabeçalho da página, que contém a conexão com o banco de dados e outras configurações iniciais.
require_once '../../includes/config.php';
// Inclui as funções auxiliares do sistema.
require_once '../../includes/functions.php';

// Verifica a permissão do usuário
if (!verificarPermissao(['Administrador', 'Atendente'])) {
    redirecionarComMensagem('../../dashboard.php', 'error', 'Você não tem permissão para acessar esta página.');
}

$fatura_id = $_GET['id'] ?? null;
$fatura = null;
$clientes = [];
$servicos = []; // Para carregar os serviços disponíveis
$produtos = []; // Para carregar os produtos disponíveis
$itens_fatura = []; // Para carregar os itens existentes na fatura

// Verifica se um ID de fatura foi fornecido
if (!$fatura_id || !is_numeric($fatura_id)) {
    redirecionarComMensagem('listar.php', 'error', 'ID da fatura inválido.');
}

try {
    // Carregar dados da fatura existente
    $stmt_fatura = $pdo->prepare("
        SELECT 
            ff.*, 
            c.nome AS nome_cliente 
        FROM 
            financeiro_faturas ff
        JOIN 
            clientes c ON ff.id_cliente = c.id_cliente
        WHERE 
            ff.id_fatura = :id_fatura
    ");
    $stmt_fatura->bindValue(':id_fatura', $fatura_id, PDO::PARAM_INT);
    $stmt_fatura->execute();
    $fatura = $stmt_fatura->fetch(PDO::FETCH_ASSOC);

    if (!$fatura) {
        redirecionarComMensagem('listar.php', 'error', 'Fatura não encontrada.');
    }

    // Carregar itens da fatura
    $stmt_itens = $pdo->prepare("
        SELECT 
            fi.*,
            COALESCE(s.nome_servico, p.nome_produto) AS nome_item,
            COALESCE(s.valor_servico, p.preco_venda) AS preco_unitario_tabela
        FROM 
            faturas_itens fi
        LEFT JOIN 
            servicos s ON fi.id_servico = s.id_servico
        LEFT JOIN 
            produtos p ON fi.id_produto = p.id_produto
        WHERE 
            fi.id_fatura = :id_fatura
        ORDER BY fi.id_fatura_item ASC
    ");
    $stmt_itens->bindValue(':id_fatura', $fatura_id, PDO::PARAM_INT);
    $stmt_itens->execute();
    $itens_fatura = $stmt_itens->fetchAll(PDO::FETCH_ASSOC);

    // Carregar lista de clientes para o select
    $stmt_clientes = $pdo->query("SELECT id_cliente, nome FROM clientes ORDER BY nome ASC");
    $clientes = $stmt_clientes->fetchAll(PDO::FETCH_ASSOC);

    // Carregar lista de serviços
    $stmt_servicos = $pdo->query("SELECT id_servico, nome_servico, valor_servico FROM servicos ORDER BY nome_servico ASC");
    $servicos = $stmt_servicos->fetchAll(PDO::FETCH_ASSOC);

    // Carregar lista de produtos
    $stmt_produtos = $pdo->query("SELECT id_produto, nome_produto, preco_venda FROM produtos ORDER BY nome_produto ASC");
    $produtos = $stmt_produtos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erro ao carregar dados da fatura para edição: " . $e->getMessage());
    redirecionarComMensagem('listar.php', 'error', 'Erro ao carregar dados da fatura: ' . $e->getMessage());
}

// Processa o formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_cliente = $_POST['id_cliente'] ?? null;
    $tipo_documento = $_POST['tipo_documento'] ?? null;
    $data_emissao = $_POST['data_emissao'] ?? null;
    $data_vencimento = $_POST['data_vencimento'] ?? null;
    $observacoes = $_POST['observacoes'] ?? null;
    $status_fatura = $_POST['status_fatura'] ?? null;
    $ids_item = $_POST['item_id'] ?? [];
    $tipos_item = $_POST['item_tipo'] ?? []; // 'servico' ou 'produto'
    $ids_item_origem = $_POST['item_id_origem'] ?? []; // id_servico ou id_produto
    $quantidades = $_POST['item_quantidade'] ?? [];
    $precos_unitarios = $_POST['item_preco_unitario'] ?? [];
    $ids_fatura_item_existente = $_POST['id_fatura_item_existente'] ?? []; // IDs dos itens já na fatura

    // Validação básica (pode ser expandida)
    if (empty($id_cliente) || empty($tipo_documento) || empty($data_emissao) || empty($status_fatura)) {
        redirecionarComMensagem('editar_fatura.php?id=' . $fatura_id, 'error', 'Por favor, preencha todos os campos obrigatórios da fatura.');
    }

    try {
        $pdo->beginTransaction();

        // 1. Atualizar dados da fatura principal
        $stmt_update_fatura = $pdo->prepare("
            UPDATE financeiro_faturas 
            SET 
                id_cliente = :id_cliente,
                tipo_documento = :tipo_documento,
                data_emissao = :data_emissao,
                data_vencimento = :data_vencimento,
                observacoes = :observacoes,
                status_fatura = :status_fatura
            WHERE id_fatura = :id_fatura
        ");

        $stmt_update_fatura->bindValue(':id_cliente', $id_cliente, PDO::PARAM_INT);
        $stmt_update_fatura->bindValue(':tipo_documento', $tipo_documento);
        $stmt_update_fatura->bindValue(':data_emissao', $data_emissao);
        $stmt_update_fatura->bindValue(':data_vencimento', !empty($data_vencimento) ? $data_vencimento : null);
        $stmt_update_fatura->bindValue(':observacoes', $observacoes);
        $stmt_update_fatura->bindValue(':status_fatura', $status_fatura);
        $stmt_update_fatura->bindValue(':id_fatura', $fatura_id, PDO::PARAM_INT);
        $stmt_update_fatura->execute();

        // 2. Gerenciar itens da fatura (faturas_itens)
        // Primeiro, identifique os itens que foram removidos
        $current_item_ids_in_db = array_column($itens_fatura, 'id_fatura_item');
        $items_to_delete = array_diff($current_item_ids_in_db, $ids_fatura_item_existente);

        if (!empty($items_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($items_to_delete), '?'));
            $stmt_delete_items = $pdo->prepare("DELETE FROM faturas_itens WHERE id_fatura_item IN ($placeholders)");
            $stmt_delete_items->execute(array_values($items_to_delete));
        }

        $valor_total_fatura = 0;
        // Processar os itens enviados pelo formulário
        for ($i = 0; $i < count($ids_item); $i++) {
            $item_id = $ids_item[$i]; // Este pode ser um ID temporário ou o ID real do item na fatura
            $item_tipo = $tipos_item[$i];
            $item_id_origem = $ids_item_origem[$i];
            $quantidade = (int)$quantidades[$i];
            $preco_unitario = (float)str_replace(',', '.', $precos_unitarios[$i]); // Garante que o decimal seja ponto

            $valor_item = $quantidade * $preco_unitario;
            $valor_total_fatura += $valor_item;

            // Determinar se é um serviço ou produto
            $id_servico = ($item_tipo == 'servico') ? $item_id_origem : null;
            $id_produto = ($item_tipo == 'produto') ? $item_id_origem : null;

            if ($item_id == 'novo') { // É um novo item
                $stmt_insert_item = $pdo->prepare("
                    INSERT INTO faturas_itens (
                        id_fatura, id_servico, id_produto, quantidade, preco_unitario, valor_total_item
                    ) VALUES (
                        :id_fatura, :id_servico, :id_produto, :quantidade, :preco_unitario, :valor_total_item
                    )
                ");
                $stmt_insert_item->bindValue(':id_fatura', $fatura_id, PDO::PARAM_INT);
                $stmt_insert_item->bindValue(':id_servico', $id_servico, PDO::PARAM_INT);
                $stmt_insert_item->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
                $stmt_insert_item->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
                $stmt_insert_item->bindValue(':preco_unitario', $preco_unitario);
                $stmt_insert_item->bindValue(':valor_total_item', $valor_item);
                $stmt_insert_item->execute();
            } else { // Item existente, precisa ser atualizado
                $stmt_update_item = $pdo->prepare("
                    UPDATE faturas_itens 
                    SET 
                        id_servico = :id_servico, 
                        id_produto = :id_produto, 
                        quantidade = :quantidade, 
                        preco_unitario = :preco_unitario, 
                        valor_total_item = :valor_total_item
                    WHERE id_fatura_item = :id_fatura_item
                ");
                $stmt_update_item->bindValue(':id_servico', $id_servico, PDO::PARAM_INT);
                $stmt_update_item->bindValue(':id_produto', $id_produto, PDO::PARAM_INT);
                $stmt_update_item->bindValue(':quantidade', $quantidade, PDO::PARAM_INT);
                $stmt_update_item->bindValue(':preco_unitario', $preco_unitario);
                $stmt_update_item->bindValue(':valor_total_item', $valor_item);
                $stmt_update_item->bindValue(':id_fatura_item', $item_id, PDO::PARAM_INT);
                $stmt_update_item->execute();
            }
        }
        
        // 3. Atualizar o valor total da fatura após adicionar/remover itens
        $stmt_update_valor_total = $pdo->prepare("
            UPDATE financeiro_faturas 
            SET valor_total = :valor_total 
            WHERE id_fatura = :id_fatura
        ");
        $stmt_update_valor_total->bindValue(':valor_total', $valor_total_fatura);
        $stmt_update_valor_total->bindValue(':id_fatura', $fatura_id, PDO::PARAM_INT);
        $stmt_update_valor_total->execute();

        $pdo->commit();
        redirecionarComMensagem('listar.php', 'success', 'Fatura atualizada com sucesso!');

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ao atualizar fatura: " . $e->getMessage());
        redirecionarComMensagem('editar_fatura.php?id=' . $fatura_id, 'error', 'Erro ao atualizar fatura: ' . $e->getMessage());
    }
}
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Editar Fatura #<?php echo htmlspecialchars($fatura['id_fatura']); ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="listar.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Voltar para Listagem
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    Dados da Fatura
                </div>
                <div class="card-body">
                    <form action="editar_fatura.php?id=<?php echo htmlspecialchars($fatura_id); ?>" method="POST" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="id_cliente" class="form-label">Cliente</label>
                                <select class="form-select" id="id_cliente" name="id_cliente" required>
                                    <option value="">Selecione o Cliente</option>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <option value="<?php echo htmlspecialchars($cliente['id_cliente']); ?>" 
                                                <?php echo ($fatura['id_cliente'] == $cliente['id_cliente']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cliente['nome']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor, selecione um cliente.
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                                <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                    <option value="">Selecione o Tipo</option>
                                    <option value="OS" <?php echo ($fatura['tipo_documento'] == 'OS') ? 'selected' : ''; ?>>Ordem de Serviço</option>
                                    <option value="Locação" <?php echo ($fatura['tipo_documento'] == 'Locação') ? 'selected' : ''; ?>>Locação</option>
                                    <option value="Avulso" <?php echo ($fatura['tipo_documento'] == 'Avulso') ? 'selected' : ''; ?>>Avulso</option>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor, selecione o tipo de documento.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="data_emissao" class="form-label">Data de Emissão</label>
                                <input type="date" class="form-control" id="data_emissao" name="data_emissao" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($fatura['data_emissao']))); ?>" required>
                                <div class="invalid-feedback">
                                    Por favor, informe a data de emissão.
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="data_vencimento" class="form-label">Data de Vencimento</label>
                                <input type="date" class="form-control" id="data_vencimento" name="data_vencimento" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($fatura['data_vencimento']))); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="status_fatura" class="form-label">Status da Fatura</label>
                                <select class="form-select" id="status_fatura" name="status_fatura" required>
                                    <option value="Pendente" <?php echo ($fatura['status_fatura'] == 'Pendente') ? 'selected' : ''; ?>>Pendente</option>
                                    <option value="Paga" <?php echo ($fatura['status_fatura'] == 'Paga') ? 'selected' : ''; ?>>Paga</option>
                                    <option value="Parcialmente Paga" <?php echo ($fatura['status_fatura'] == 'Parcialmente Paga') ? 'selected' : ''; ?>>Parcialmente Paga</option>
                                    <option value="Atrasada" <?php echo ($fatura['status_fatura'] == 'Atrasada') ? 'selected' : ''; ?>>Atrasada</option>
                                    <option value="Cancelada" <?php echo ($fatura['status_fatura'] == 'Cancelada') ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor, selecione o status da fatura.
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="observacoes" class="form-label">Observações</label>
                                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?php echo htmlspecialchars($fatura['observacoes']); ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h4 class="mb-3">Itens da Fatura</h4>
                        <div id="invoice-items-container">
                            <?php if (!empty($itens_fatura)): ?>
                                <?php foreach ($itens_fatura as $index => $item): ?>
                                    <div class="row g-3 mb-3 invoice-item" data-item-index="<?php echo $index; ?>">
                                        <input type="hidden" name="id_fatura_item_existente[]" value="<?php echo htmlspecialchars($item['id_fatura_item']); ?>">
                                        <input type="hidden" name="item_id[]" value="<?php echo htmlspecialchars($item['id_fatura_item']); ?>">
                                        <div class="col-md-4">
                                            <label for="item_tipo_<?php echo $index; ?>" class="form-label">Tipo</label>
                                            <select class="form-select item-type" name="item_tipo[]" id="item_tipo_<?php echo $index; ?>">
                                                <option value="servico" <?php echo (!empty($item['id_servico'])) ? 'selected' : ''; ?>>Serviço</option>
                                                <option value="produto" <?php echo (!empty($item['id_produto'])) ? 'selected' : ''; ?>>Produto</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5">
                                            <label for="item_id_origem_<?php echo $index; ?>" class="form-label">Item</label>
                                            <select class="form-select item-source-id" name="item_id_origem[]" id="item_id_origem_<?php echo $index; ?>" required>
                                                <?php if (!empty($item['id_servico'])): ?>
                                                    <?php foreach ($servicos as $servico): ?>
                                                        <option value="<?php echo htmlspecialchars($servico['id_servico']); ?>" 
                                                                data-price="<?php echo htmlspecialchars($servico['valor_servico']); ?>" 
                                                                <?php echo ($item['id_servico'] == $servico['id_servico']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($servico['nome_servico']); ?> (R$ <?php echo number_format($servico['valor_servico'], 2, ',', '.'); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php elseif (!empty($item['id_produto'])): ?>
                                                    <?php foreach ($produtos as $produto): ?>
                                                        <option value="<?php echo htmlspecialchars($produto['id_produto']); ?>" 
                                                                data-price="<?php echo htmlspecialchars($produto['preco_venda']); ?>" 
                                                                <?php echo ($item['id_produto'] == $produto['id_produto']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($produto['nome_produto']); ?> (R$ <?php echo number_format($produto['preco_venda'], 2, ',', '.'); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <option value="">Selecione um item</option>
                                                <?php endif; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Por favor, selecione um serviço ou produto.
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <label for="item_quantidade_<?php echo $index; ?>" class="form-label">Qtd</label>
                                            <input type="number" class="form-control item-quantity" id="item_quantidade_<?php echo $index; ?>" name="item_quantidade[]" min="1" value="<?php echo htmlspecialchars($item['quantidade']); ?>" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label for="item_preco_unitario_<?php echo $index; ?>" class="form-label">Preço Unit.</label>
                                            <input type="text" class="form-control item-unit-price" id="item_preco_unitario_<?php echo $index; ?>" name="item_preco_unitario[]" value="<?php echo number_format($item['preco_unitario'], 2, ',', '.'); ?>" required>
                                        </div>
                                        <div class="col-md-1 d-flex align-items-end">
                                            <button type="button" class="btn btn-danger remove-item-btn"><i class="bi bi-x-circle"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="btn btn-secondary mt-3" id="add-item-btn">Adicionar Item</button>

                        <div class="row g-3 mt-4">
                            <div class="col-12 text-end">
                                <label for="valor_total_display" class="form-label h5">Valor Total da Fatura:</label>
                                <span class="h5" id="valor_total_display">R$ <?php echo number_format($fatura['valor_total'], 2, ',', '.'); ?></span>
                                <input type="hidden" name="valor_total" id="valor_total_hidden" value="<?php echo htmlspecialchars($fatura['valor_total']); ?>">
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Salvar Alterações</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

<script>
    // Example starter JavaScript for disabling form submissions if there are invalid fields
    (function () {
        'use strict'

        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation')

        // Loop over them and prevent submission
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()

    document.addEventListener('DOMContentLoaded', function() {
        const invoiceItemsContainer = document.getElementById('invoice-items-container');
        const addItemBtn = document.getElementById('add-item-btn');
        const servicos = <?php echo json_encode($servicos); ?>;
        const produtos = <?php echo json_encode($produtos); ?>;

        let itemIndex = invoiceItemsContainer.children.length > 0 ? Array.from(invoiceItemsContainer.children).pop().dataset.itemIndex + 1 : 0;

        function createItemRow(itemData = null) {
            const newRow = document.createElement('div');
            newRow.classList.add('row', 'g-3', 'mb-3', 'invoice-item');
            newRow.dataset.itemIndex = itemIndex++;

            const isExistingItem = itemData !== null && itemData.id_fatura_item !== undefined;
            
            newRow.innerHTML = `
                ${isExistingItem ? `<input type="hidden" name="id_fatura_item_existente[]" value="${itemData.id_fatura_item}">` : ''}
                <input type="hidden" name="item_id[]" value="${isExistingItem ? itemData.id_fatura_item : 'novo'}">
                <div class="col-md-4">
                    <label for="item_tipo_${newRow.dataset.itemIndex}" class="form-label">Tipo</label>
                    <select class="form-select item-type" name="item_tipo[]" id="item_tipo_${newRow.dataset.itemIndex}">
                        <option value="servico" ${itemData && itemData.id_servico ? 'selected' : ''}>Serviço</option>
                        <option value="produto" ${itemData && itemData.id_produto ? 'selected' : ''}>Produto</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="item_id_origem_${newRow.dataset.itemIndex}" class="form-label">Item</label>
                    <select class="form-select item-source-id" name="item_id_origem[]" id="item_id_origem_${newRow.dataset.itemIndex}" required>
                        <option value="">Selecione um item</option>
                    </select>
                    <div class="invalid-feedback">
                        Por favor, selecione um serviço ou produto.
                    </div>
                </div>
                <div class="col-md-1">
                    <label for="item_quantidade_${newRow.dataset.itemIndex}" class="form-label">Qtd</label>
                    <input type="number" class="form-control item-quantity" id="item_quantidade_${newRow.dataset.itemIndex}" name="item_quantidade[]" min="1" value="${itemData ? itemData.quantidade : '1'}" required>
                </div>
                <div class="col-md-1">
                    <label for="item_preco_unitario_${newRow.dataset.itemIndex}" class="form-label">Preço Unit.</label>
                    <input type="text" class="form-control item-unit-price" id="item_preco_unitario_${newRow.dataset.itemIndex}" name="item_preco_unitario[]" value="${itemData ? parseFloat(itemData.preco_unitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0,00'}" required>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-danger remove-item-btn"><i class="bi bi-x-circle"></i></button>
                </div>
            `;

            invoiceItemsContainer.appendChild(newRow);
            bindItemRowEvents(newRow);
            
            // Populate items and set selected if existing item
            const itemTypeSelect = newRow.querySelector('.item-type');
            const itemSourceIdSelect = newRow.querySelector('.item-source-id');
            const unitPriceInput = newRow.querySelector('.item-unit-price');

            populateItemSelect(itemTypeSelect.value, itemSourceIdSelect, itemData);
            if (itemData) {
                 // Set initial values for quantity and unit price, ensuring proper formatting
                newRow.querySelector('.item-quantity').value = itemData.quantidade;
                unitPriceInput.value = parseFloat(itemData.preco_unitario).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            calculateTotal();
        }

        function populateItemSelect(type, selectElement, itemData = null) {
            selectElement.innerHTML = '<option value="">Selecione um item</option>';
            let items = [];
            if (type === 'servico') {
                items = servicos;
            } else if (type === 'produto') {
                items = produtos;
            }

            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id_servico || item.id_produto;
                option.dataset.price = item.valor_servico || item.preco_venda;
                option.textContent = `${item.nome_servico || item.nome_produto} (R$ ${parseFloat(option.dataset.price).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`;

                if (itemData) {
                    if (type === 'servico' && itemData.id_servico === item.id_servico) {
                        option.selected = true;
                    } else if (type === 'produto' && itemData.id_produto === item.id_produto) {
                        option.selected = true;
                    }
                }
                selectElement.appendChild(option);
            });

            // If an item was pre-selected, trigger the change event to set its price
            if (itemData) {
                const selectedOption = selectElement.querySelector('option:checked');
                if (selectedOption) {
                    const unitPriceInput = selectElement.closest('.invoice-item').querySelector('.item-unit-price');
                    unitPriceInput.value = parseFloat(selectedOption.dataset.price).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            }
        }

        function bindItemRowEvents(row) {
            const itemTypeSelect = row.querySelector('.item-type');
            const itemSourceIdSelect = row.querySelector('.item-source-id');
            const itemQuantityInput = row.querySelector('.item-quantity');
            const itemUnitPriceInput = row.querySelector('.item-unit-price');
            const removeItemBtn = row.querySelector('.remove-item-btn');

            itemTypeSelect.addEventListener('change', function() {
                populateItemSelect(this.value, itemSourceIdSelect);
                itemUnitPriceInput.value = '0,00'; // Reset price when type changes
                calculateTotal();
            });

            itemSourceIdSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && selectedOption.dataset.price) {
                    itemUnitPriceInput.value = parseFloat(selectedOption.dataset.price).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                } else {
                    itemUnitPriceInput.value = '0,00';
                }
                calculateTotal();
            });

            itemQuantityInput.addEventListener('input', calculateTotal);
            itemUnitPriceInput.addEventListener('input', function() {
                // Remove non-numeric characters except comma, replace comma with dot for calculation
                this.value = this.value.replace(/[^0-9,.]/g, '');
                if (this.value.includes(',')) {
                    this.value = this.value.replace('.', '').replace(',', '.');
                }
                calculateTotal();
                // Reformat to Brazilian currency on blur
                this.addEventListener('blur', function() {
                    let value = parseFloat(this.value);
                    if (isNaN(value)) value = 0;
                    this.value = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }, { once: true });
            });

            removeItemBtn.addEventListener('click', function() {
                row.remove();
                calculateTotal();
            });
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.invoice-item').forEach(row => {
                const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
                let unitPrice = row.querySelector('.item-unit-price').value;
                unitPrice = parseFloat(unitPrice.replace('.', '').replace(',', '.')) || 0; // Handle Brazilian decimal

                total += (quantity * unitPrice);
            });
            document.getElementById('valor_total_display').textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('valor_total_hidden').value = total.toFixed(2);
        }

        addItemBtn.addEventListener('click', function() {
            createItemRow();
        });

        // Initialize existing items with event listeners
        document.querySelectorAll('.invoice-item').forEach(row => {
            bindItemRowEvents(row);
            // Ensure select boxes are populated correctly on load based on their current value
            const itemTypeSelect = row.querySelector('.item-type');
            const itemSourceIdSelect = row.querySelector('.item-source-id');
            // itemData is already available from PHP for initial load, but for dynamic changes,
            // we re-populate based on the selected type.
            populateItemSelect(itemTypeSelect.value, itemSourceIdSelect, {
                id_servico: itemTypeSelect.value === 'servico' ? itemSourceIdSelect.value : null,
                id_produto: itemTypeSelect.value === 'produto' ? itemSourceIdSelect.value : null
            });
        });

        calculateTotal(); // Calculate initial total on page load
    });
</script>
