document.addEventListener('DOMContentLoaded', function() {
    // Função para confirmação de ações (usada em listar.php e view.php)
    window.confirmarAcao = function(titulo, mensagem, urlRedirecionar) {
        Swal.fire({
            title: titulo,
            text: mensagem,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, continuar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Se a ação for um GET, redireciona diretamente
                window.location.href = urlRedirecionar;
            }
        });
    };

    // Script para `modulos/financeiro/relatorios.php`
    // Esta lógica continua sendo melhor gerenciada diretamente no relatorios.php
    // pois depende de elementos específicos do HTML daquela página.

    // Funcionalidade para o formulário de recebimento de fatura (se existir 'receber_fatura.php')
    const formReceberFatura = document.getElementById('formReceberFatura');
    if (formReceberFatura) {
        formReceberFatura.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('acao', 'registrar_pagamento');

            Swal.fire({
                title: 'Registrar Pagamento?',
                text: 'Confirma o registro deste pagamento?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sim, registrar!',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../../ajax/financeiro_acoes.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire('Sucesso!', data.message, 'success')
                                .then(() => {
                                    window.location.href = 'view.php?tipo=fatura&id=' + formData.get('id_fatura');
                                });
                        } else {
                            Swal.fire('Erro!', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao registrar pagamento:', error);
                        Swal.fire('Erro!', 'Não foi possível registrar o pagamento.', 'error');
                    });
                }
            });
        });
    }
});
