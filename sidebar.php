<?php
$nivel_usuario = $_SESSION['usuario_nivel'];

// Certifique-se de que BASE_URL foi definida antes de incluir este arquivo.
// Você pode adicionar um fallback simples se não tiver certeza:
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Função auxiliar para verificar a permissão, se não estiver definida
if (!function_exists('verificarPermissao')) {
    function verificarPermissao($nivel_necessario) {
        // Implemente sua lógica de verificação de permissão aqui
        // Por exemplo:
        return isset($_SESSION['usuario_nivel']) && $_SESSION['usuario_nivel'] === $nivel_necessario;
    }
}
?>
<div id="sidebar-wrapper" class="d-flex flex-column text-white" style="min-height: 100vh; background-color: #343a40;">
    <div class="sidebar-header text-center mb-4">
        <h4 class="fs-4">Menu</h4>
    </div>

    <ul class="nav nav-pills flex-column mb-auto px-2">
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>dashboard.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? ' active' : ''; ?>">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modulos/clientes/listar.php" class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'clientes/') !== false ? ' active' : ''; ?>">
                <i class="bi bi-people me-2"></i> Clientes
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modulos/servicos/listar.php" class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'servicos/') !== false ? ' active' : ''; ?>">
                <i class="bi bi-tools me-2"></i> Serviços
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modulos/produtos/listar.php" class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'produtos/') !== false ? ' active' : ''; ?>">
                <i class="bi bi-box-seam me-2"></i> Produtos
            </a>
        </li>
        <li class="nav-item">
            <a href="<?php echo BASE_URL; ?>modulos/ordens/listar.php" class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'ordens/') !== false ? ' active' : ''; ?>">
                <i class="bi bi-clipboard-check me-2"></i> Ordens de Serviço
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'locacao/') !== false ? ' active' : ''; ?>" data-bs-toggle="collapse" href="#locacaoCollapse">
                <i class="bi bi-cash-coin me-2"></i> Locação
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse<?php echo strpos($_SERVER['PHP_SELF'], 'locacao/') !== false ? ' show' : ''; ?>" id="locacaoCollapse">
                <ul class="nav flex-column ps-4">
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>modulos/locacao/listar.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'Alugueis' ? ' active' : ''; ?>">Listar</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>modulos/locacao/impressoras/listar.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'Impressoras' ? ' active' : ''; ?>">Impressoras</a>
                    </li>
                </ul>
            </div>
        </li>

        <li class="nav-item">
            <a class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'financeiro/') !== false ? ' active' : ''; ?>" data-bs-toggle="collapse" href="#financeiroCollapse">
                <i class="bi bi-cash-coin me-2"></i> Financeiro
                <i class="bi bi-chevron-down ms-auto"></i>
            </a>
            <div class="collapse<?php echo strpos($_SERVER['PHP_SELF'], 'financeiro/') !== false ? ' show' : ''; ?>" id="financeiroCollapse">
                <ul class="nav flex-column ps-4">
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>modulos/financeiro/listar.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'listar.php' ? ' active' : ''; ?>">Listar</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>modulos/financeiro/faturas.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'faturas.php' ? ' active' : ''; ?>">Faturas</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>modulos/financeiro/recibos.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'recibos.php' ? ' active' : ''; ?>">Recibos</a>
                    </li>
                    <li class="nav-item">
                        <a href="<?php echo BASE_URL; ?>modulos/financeiro/relatorios.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'relatorios.php' ? ' active' : ''; ?>">Relatórios</a>
                    </li>
                </ul>
            </div>
        </li>

        <?php if (verificarPermissao('Administrador')): ?>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>modulos/usuarios/listar.php" class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'usuarios/') !== false ? ' active' : ''; ?>">
                    <i class="bi bi-person-lines-fill me-2"></i> Usuários
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white<?php echo strpos($_SERVER['PHP_SELF'], 'configuracoes/') !== false ? ' active' : ''; ?>" data-bs-toggle="collapse" href="#configCollapse">
                    <i class="bi bi-gear me-2"></i> Configurações
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse<?php echo strpos($_SERVER['PHP_SELF'], 'configuracoes/') !== false ? ' show' : ''; ?>" id="configCollapse">
                    <ul class="nav flex-column ps-4">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>modulos/configuracoes/empresa.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'empresa.php' ? ' active' : ''; ?>">Empresa</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>modulos/configuracoes/sistema.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'sistema.php' ? ' active' : ''; ?>">Sistema</a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>modulos/configuracoes/email.php" class="nav-link text-white<?php echo basename($_SERVER['PHP_SELF']) == 'email.php' ? ' active' : ''; ?>">E-mail</a>
                        </li>
                    </ul>
                </div>
            </li>
        <?php endif; ?>
    </ul>

    <div class="mt-auto pt-3 border-top text-center small text-muted px-2">
        <div><?php echo SISTEMA_NOME; ?></div>
        <div>v<?php echo SISTEMA_VERSAO; ?></div>
    </div>
</div>
