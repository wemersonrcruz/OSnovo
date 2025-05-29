// assets/js/modulos/ordens.js

// Funções globais que podem ser úteis no futuro, se não existirem em functions.js
if (typeof formatCurrency !== 'function') {
    function formatCurrency(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }
}

/**
 * Inicializa o formulário de Ordem de Serviço, com lógica para adicionar/remover serviços e produtos.
 * @param {Array} initialServicos Array de objetos de serviços já selecionados.
 * @param {Array} initialProdutos Array de objetos de produtos já selecionados.
 * @param {Array} todosServicosDisponiveis Array de todos os serviços disponíveis (para dropdown).
 * @param {Array} todosProdutosDisponiveis Array de todos os produtos disponíveis (para dropdown).
 */
function initOrdemServicoForm(initialServicos, initialProdutos, todosServicosDisponiveis, todosProdutosDisponiveis) {
    let servicosSelecionados = initialServicos || [];
    let produtosSelecionados = initialProdutos || [];

    // Mapeia os dados para facilitar a busca por ID
    const mapServicosDisponiveis = new Map(todosServicosDisponiveis.map(s => [s.id_servico.toString(), s]));
    const mapProdutosDisponiveis = new Map(todosProdutosDisponiveis.map(p => [p.id_produto.toString(), p]));

    // Inicializar Select2 para os dropdowns
    $('.select2-clientes').select2({
        placeholder: "Selecione um Cliente",
        allowClear: true,
        theme: "bootstrap-5"
    });

    $('.select2-servicos').select2({
        placeholder: "Selecione um Serviço",
        allowClear: true,
        theme: "bootstrap-5"
    });

    $('.select2-produtos').select2({
        placeholder: "Selecione um Produto",
        allowClear: true,
        theme: "bootstrap-5"
    });

    /**
     * Calcula e atualiza os totais de serviços, produtos e o valor final da OS.
     */
    function calculateTotals() {
        let totalServicos = 0;
        servicosSelecionados.forEach(s => totalServicos += parseFloat(s.subtotal || 0));
        $('#total_servicos_os').text(formatCurrency(totalServicos));

        let totalProdutos = 0;
        produtosSelecionados.forEach(p => totalProdutos += parseFloat(p.subtotal || 0));
        $('#total_produtos_os').text(formatCurrency(totalProdutos));

        const valorDesconto = parseFloat($('#valor_desconto').val()) || 0;
        const valorFinal = (totalServicos + totalProdutos) - valorDesconto;
        $('#valor_final_os').val(formatCurrency(valorFinal));

        // Atualiza os campos hidden para envio via POST
        $('#servicos_selecionados_json').val(JSON.stringify(servicosSelecionados));
        $('#produtos_selecionados_json').val(JSON.stringify(produtosSelecionados));
    }

    /**
     * Adiciona uma linha na tabela de serviços.
     * @param {Object} servico Objeto do serviço a ser adicionado.
     */
    function addServicoRow(servico) {
        const newRow = `
            <tr data-id="${servico.id_servico}" data-type="servico">
                <td>${servico.nome_servico}</td>
                <td><input type="number" class="form-control form-control-sm item-quantidade" value="${servico.quantidade}" min="1"></td>
                <td><input type="number" class="form-control form-control-sm item-preco-unitario" value="${servico.preco_unitario.toFixed(2)}" step="0.01" min="0"></td>
                <td class="item-subtotal">${formatCurrency(servico.subtotal)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remover-item"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `;
        $('#tabela_servicos_os tbody').append(newRow);
    }

    /**
     * Adiciona uma linha na tabela de produtos.
     * @param {Object} produto Objeto do produto a ser adicionado.
     */
    function addProdutoRow(produto) {
        const newRow = `
            <tr data-id="${produto.id_produto}" data-type="produto">
                <td>${produto.nome_produto}</td>
                <td><input type="number" class="form-control form-control-sm item-quantidade" value="${produto.quantidade}" min="1" data-max-estoque="${produto.estoque_disponivel}"></td>
                <td><input type="number" class="form-control form-control-sm item-preco-unitario" value="${produto.preco_unitario.toFixed(2)}" step="0.01" min="0"></td>
                <td class="item-subtotal">${formatCurrency(produto.subtotal)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remover-item"><i class="bi bi-trash"></i></button>
                </td>
            </tr>
        `;
        $('#tabela_produtos_os tbody').append(newRow);
    }

    // Popula as tabelas com os dados iniciais (se vierem do PHP)
    servicosSelecionados.forEach(servico => addServicoRow(servico));
    produtosSelecionados.forEach(produto => addProdutoRow(produto));

    // Evento para adicionar Serviço
    $('#add_servico_btn').on('click', function() {
        const selectedOption = $('#servico_add option:selected');
        const idServico = selectedOption.val();
        const nomeServico = selectedOption.data('nome');
        const preco = parseFloat(selectedOption.data('preco'));
        const quantidade = parseInt($('#quantidade_servico').val());

        if (!idServico || isNaN(quantidade) || quantidade <= 0 || isNaN(preco)) {
            alert('Selecione um serviço e insira uma quantidade válida.');
            return;
        }

        // Verifica se o serviço já foi adicionado
        if (servicosSelecionados.some(s => s.id_servico == idServico)) {
            alert('Este serviço já foi adicionado.');
            return;
        }

        const subtotal = quantidade * preco;
        const newServico = {
            id_servico: idServico,
            nome_servico: nomeServico,
            quantidade: quantidade,
            preco_unitario: preco,
            subtotal: subtotal
        };
        servicosSelecionados.push(newServico);
        addServicoRow(newServico);

        $('#servico_add').val('').trigger('change'); // Limpa a seleção
        $('#quantidade_servico').val(1);
        calculateTotals();
    });

    // Evento para adicionar Produto
    $('#add_produto_btn').on('click', function() {
        const selectedOption = $('#produto_add option:selected');
        const idProduto = selectedOption.val();
        const nomeProduto = selectedOption.data('nome');
        const preco = parseFloat(selectedOption.data('preco'));
        const estoqueDisponivel = parseInt(selectedOption.data('estoque'));
        const quantidade = parseInt($('#quantidade_produto').val());

        if (!idProduto || isNaN(quantidade) || quantidade <= 0 || isNaN(preco)) {
            alert('Selecione um produto e insira uma quantidade válida.');
            return;
        }
        if (quantidade > estoqueDisponivel) {
            alert(`Quantidade solicitada (${quantidade}) excede o estoque disponível (${estoqueDisponivel}).`);
            return;
        }

        // Verifica se o produto já foi adicionado
        if (produtosSelecionados.some(p => p.id_produto == idProduto)) {
            alert('Este produto já foi adicionado.');
            return;
        }

        const subtotal = quantidade * preco;
        const newProduto = {
            id_produto: idProduto,
            nome_produto: nomeProduto,
            quantidade: quantidade,
            preco_unitario: preco,
            subtotal: subtotal,
            estoque_disponivel: estoqueDisponivel // Mantém o estoque atual para validação
        };
        produtosSelecionados.push(newProduto);
        addProdutoRow(newProduto);

        $('#produto_add').val('').trigger('change'); // Limpa a seleção
        $('#quantidade_produto').val(1);
        calculateTotals();
    });

    // Eventos para remover item e atualizar subtotal/totais
    $(document).on('click', '.remover-item', function() {
        const row = $(this).closest('tr');
        const itemId = row.data('id');
        const itemType = row.data('type'); // 'servico' ou 'produto'

        if (itemType === 'servico') {
            servicosSelecionados = servicosSelecionados.filter(s => s.id_servico != itemId);
        } else if (itemType === 'produto') {
            produtosSelecionados = produtosSelecionados.filter(p => p.id_produto != itemId);
        }
        row.remove();
        calculateTotals();
    });

    // Evento para atualizar quantidade e preço unitário
    $(document).on('change keyup', '.item-quantidade, .item-preco-unitario', function() {
        const row = $(this).closest('tr');
        const itemId = row.data('id');
        const itemType = row.data('type');

        let quantidade = parseFloat(row.find('.item-quantidade').val());
        let precoUnitario = parseFloat(row.find('.item-preco-unitario').val());

        if (isNaN(quantidade) || quantidade < 0) {
            quantidade = 0;
            row.find('.item-quantidade').val(0);
        }
        if (isNaN(precoUnitario) || precoUnitario < 0) {
            precoUnitario = 0;
            row.find('.item-preco-unitario').val(0);
        }

        if (itemType === 'produto') {
            const maxEstoque = parseInt(row.find('.item-quantidade').data('max-estoque'));
            if (quantidade > maxEstoque) {
                alert(`Quantidade (${quantidade}) excede o estoque disponível (${maxEstoque}).`);
                row.find('.item-quantidade').val(maxEstoque);
                quantidade = maxEstoque;
            }
            const index = produtosSelecionados.findIndex(p => p.id_produto == itemId);
            if (index !== -1) {
                produtosSelecionados[index].quantidade = quantidade;
                produtosSelecionados[index].preco_unitario = precoUnitario;
                produtosSelecionados[index].subtotal = quantidade * precoUnitario;
            }
        } else if (itemType === 'servico') {
             const index = servicosSelecionados.findIndex(s => s.id_servico == itemId);
             if (index !== -1) {
                servicosSelecionados[index].quantidade = quantidade;
                servicosSelecionados[index].preco_unitario = precoUnitario;
                servicosSelecionados[index].subtotal = quantidade * precoUnitario;
            }
        }
        
        // Atualiza o subtotal na linha da tabela
        const subtotal = quantidade * precoUnitario;
        row.find('.item-subtotal').text(formatCurrency(subtotal));

        calculateTotals();
    });

    // Evento para o campo de desconto
    $('#valor_desconto').on('change keyup', function() {
        calculateTotals();
    });

    // Inicializar cálculos ao carregar a página
    calculateTotals();
}
