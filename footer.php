            </div> </div> </div> <footer class="mt-auto py-3 bg-light border-top">
        <div class="container-fluid text-center">
            <span class="text-muted">© <?php echo date('Y'); ?> <?php echo SISTEMA_EMPRESA; ?>. Todos os direitos reservados. Versão <?php echo SISTEMA_VERSAO; ?></span>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>    
    
    <script src="//code.jquery.com/ui/1.13.2/jquery-ui.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script src="<?= BASE_URL ?>assets/js/app.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/functions.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/dashboard.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/produtos.js?v=<?= time() ?>"></script> 
    <script src="<?= BASE_URL ?>assets/js/modulos/clientes.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/servicos.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/ordens.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/locacao.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/financeiro.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/usuarios.js?v=<?= time() ?>"></script>
    <script src="<?= BASE_URL ?>assets/js/modulos/configuracoes.js?v=<?= time() ?>"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var sidebarToggle = document.getElementById('sidebarToggle');
            var wrapper = document.getElementById('wrapper');

            if (sidebarToggle && wrapper) {
                sidebarToggle.addEventListener('click', function() {
                    wrapper.classList.toggle('toggled');
                });
            }
        });

        // Inicialização global do Select2 (se você quiser que todos os selects com classe 'select2' sejam Select2)
        // Certifique-se de que esta parte seja executada APÓS o Select2.min.js ser carregado.
        $(document).ready(function() {
            $('.select2').select2({
                theme: "bootstrap-5" // Aplica o tema Bootstrap 5 ao Select2
            });
        });

// Example using jQuery (assuming it's loaded)
$(document).ready(function() {
    // When the "Registrar Pagamento" button in the table is clicked
    $('.btn-registrar-pagamento').on('click', function() {
        const idFatura = $(this).data('id');
        const clienteNome = $(this).data('cliente');
        const valorTotal = $(this).data('valor');

        // Populate the modal fields
        $('#pagamentoIdFatura').val(idFatura);
        $('#pagamentoClienteNome').val(clienteNome);
        $('#pagamentoValorTotal').val(valorTotal.toFixed(2)); // Format to 2 decimal places if it's a number
        $('#pagamentoValorPago').val(valorTotal.toFixed(2)); // Pre-fill with total, user can change
        $('#pagamentoMetodo').val(''); // Reset method
        $('#pagamentoObservacoes').val(''); // Clear observations

        // Show the modal
        const myModal = new bootstrap.Modal(document.getElementById('modalRegistrarPagamento'));
        myModal.show();
    });

    // Handle the submission of the payment registration form inside the modal
    $('#formRegistrarPagamento').on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission

        const idFatura = $('#pagamentoIdFatura').val();
        const valorPago = $('#pagamentoValorPago').val();
        const metodoPagamento = $('#pagamentoMetodo').val();
        const observacoes = $('#pagamentoObservacoes').val();

        // Basic validation
        if (!valorPago || parseFloat(valorPago) <= 0) {
            alert('Por favor, insira um valor pago válido.');
            return;
        }
        if (!metodoPagamento) {
            alert('Por favor, selecione um método de pagamento.');
            return;
        }

        // Send data using AJAX
        $.ajax({
            url: 'processar_pagamento.php', // This PHP file needs to be created
            type: 'POST',
            data: {
                id_fatura: idFatura,
                valor_pago: valorPago,
                metodo_pagamento: metodoPagamento,
                observacoes: observacoes
            },
            success: function(response) {
                // Assuming 'response' is a JSON object like { success: true, message: '...' }
                if (response.success) {
                    alert(response.message);
                    // Hide the modal
                    const myModal = bootstrap.Modal.getInstance(document.getElementById('modalRegistrarPagamento'));
                    myModal.hide();
                    // Optionally, reload the page or update the table to reflect the new status
                    location.reload();
                } else {
                    alert('Erro ao registrar pagamento: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('Ocorreu um erro ao processar sua solicitação.');
                console.error(xhr.responseText);
            }
        });
    });
});
    </script>
</body>
</html>
