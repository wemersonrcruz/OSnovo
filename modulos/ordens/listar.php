<?php
// ALL REDIRECTION AND CORE LOGIC MUST COME BEFORE including the header.
// This ensures no output is sent before potential redirects.

require_once '../../includes/config.php'; // Ensure config is loaded first for functions and PDO

// Requer que o usuário esteja logado
requerLogin();

// Verifica permissão para acessar este módulo
if (!verificarPermissao(['Administrador', 'Atendente'])) { // Assuming 'Atendente' or 'Administrador' can view
    redirecionarComMensagem('../../dashboard.php', 'error', 'Você não tem permissão para visualizar ordens de serviço.');
}

$titulo_pagina = "Ordens de Serviço";
$body_id = "page-ordens-listar"; // Adicione um ID para o body se necessário para CSS específico
$breadcrumb = [
    ['texto' => 'Ordens de Serviço', 'link' => 'modulos/ordens/listar.php']
];

// Consulta as ordens de serviço
try {
    $query = "SELECT os.*, c.nome as nome_cliente
              FROM ordens_servico os
              JOIN clientes c ON os.id_cliente = c.id_cliente
              ORDER BY os.data_abertura DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $ordens = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Erro ao consultar ordens de serviço: " . $e->getMessage());
    redirecionarComMensagem('../../dashboard.php', 'error', 'Erro ao carregar lista de ordens de serviço.');
}

require_once '../../includes/header.php';
?>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Lista de Ordens de Serviço</h6>
        <a href="adicionar.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle"></i> Nova OS
        </a>
    </div>
    <div class="card-body">
        <?php if (empty($ordens)): ?>
            <div class="alert alert-info text-center" role="alert">
                Nenhuma ordem de serviço encontrada.
            </div>
        <?php else: ?>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col" style="width: 5%;">ID</th>
                            <th scope="col" style="width: 15%;">Data</th>
                            <th scope="col">Cliente</th>
                            <th scope="col" style="width: 15%;">Equipamento</th>
                            <th scope="col" style="width: 10%;">Status</th>
                            <th scope="col" style="width: 15%;">Valor</th>
                            <th scope="col" style="width: 10%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordens as $os): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($os['id_os']); ?></td>
                                <td><?php echo formatarData($os['data_abertura'], 'd/m/Y'); ?></td>
                                <td><?php echo htmlspecialchars($os['nome_cliente']); ?></td>
                                <td><?php echo htmlspecialchars($os['equipamento']); ?></td>
                                <td>
                                    <span class="badge <?php
                                        switch($os['status']) {
                                            case 'Aberta': echo 'bg-primary'; break;
                                            case 'Em Andamento': echo 'bg-warning text-dark'; break;
                                            case 'Concluída': echo 'bg-success'; break;
                                            case 'Cancelada': echo 'bg-danger'; break;
                                            default: echo 'bg-secondary';
                                        }
                                    ?>">
                                        <?php echo htmlspecialchars($os['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo formatarMoeda($os['valor_final']); ?></td>
                                <td>
                                    <a href="view.php?id=<?php echo htmlspecialchars($os['id_os']); ?>" class="btn btn-sm btn-info" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="editar.php?id=<?php echo htmlspecialchars($os['id_os']); ?>" class="btn btn-sm btn-warning" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-md-none">
                <?php foreach ($ordens as $os): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title text-primary">OS #<?php echo htmlspecialchars($os['id_os']); ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted">Cliente: <?php echo htmlspecialchars($os['nome_cliente']); ?></h6>
                            <p class="card-text mb-1">
                                <strong>Data:</strong> <?php echo formatarData($os['data_abertura'], 'd/m/Y'); ?><br>
                                <strong>Equipamento:</strong> <?php echo htmlspecialchars($os['equipamento']); ?><br>
                                <strong>Status:</strong>
                                <span class="badge <?php
                                    switch($os['status']) {
                                        case 'Aberta': echo 'bg-primary'; break;
                                        case 'Em Andamento': echo 'bg-warning text-dark'; break;
                                        case 'Concluída': echo 'bg-success'; break;
                                        case 'Cancelada': echo 'bg-danger'; break;
                                        default: echo 'bg-secondary';
                                    }
                                ?>">
                                    <?php echo htmlspecialchars($os['status']); ?>
                                </span><br>
                                <strong>Valor:</strong> <?php echo formatarMoeda($os['valor_final']); ?>
                            </p>
                            <div class="mt-3">
                                <a href="view.php?id=<?php echo htmlspecialchars($os['id_os']); ?>" class="btn btn-sm btn-info me-2">
                                    <i class="bi bi-eye"></i> Visualizar
                                </a>
                                <a href="editar.php?id=<?php echo htmlspecialchars($os['id_os']); ?>" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil"></i> Editar
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
