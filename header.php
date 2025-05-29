<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Verifica se o usuário está logado. Se não estiver, redireciona para a página de login.
requerLogin();

// Define o título da página. Se não for definido na página que inclui o header, usa o nome do sistema.
$titulo_pagina = isset($titulo_pagina) ? $titulo_pagina : SISTEMA_NOME;

// *** REMOVIDO: $base_url não é mais necessário aqui, pois BASE_URL é uma constante global ***
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo_pagina; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css">
    
    <link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>    

    <link href="<?php echo BASE_URL; ?>assets/css/custom-bootstrap.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>assets/css/style.css" rel="stylesheet">
    
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/favicon.ico">

    <style>
        /* Estilos para o layout geral */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        #wrapper {
            display: flex;
            width: 100%;
            overflow-x: hidden; /* Evita rolagem horizontal */
        }
        #sidebar-wrapper {
            min-width: 250px;
            max-width: 250px;
            background-color: #343a40; /* Cor de fundo da sidebar */
            transition: margin .25s ease-out;
            padding-top: 1rem;
        }
        #page-content-wrapper {
            flex-grow: 1;
            padding: 20px;
            background-color: #f8f9fa; /* Cor de fundo do conteúdo */
        }

        /* Esconde a sidebar em telas pequenas por padrão */
        @media (max-width: 768px) {
            #sidebar-wrapper {
                margin-left: -250px;
            }
            #wrapper.toggled #sidebar-wrapper {
                margin-left: 0;
            }
        }

        /* Estilos para a barra de navegação */
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
        }
        .navbar-brand img {
            margin-right: 0.5rem;
        }
        .dropdown-menu {
            border-radius: 0.5rem;
        }
        .dropdown-item:active {
            background-color: #0d6efd;
            color: #fff;
        }

        /* Estilos para o sidebar */
        .sidebar-heading {
            padding: 0.875rem 1.25rem;
            font-size: 1.2rem;
            color: #fff;
            text-align: center;
        }
        .list-group-item {
            background-color: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.7);
            padding: 0.75rem 1.25rem;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .list-group-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .list-group-item.active {
            background-color: #0d6efd;
            color: #fff;
            border-radius: 0.375rem;
        }
        .list-group-item i {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body id="<?php echo isset($body_id) ? $body_id : ''; ?>">
    <nav class="navbar navbar-expand navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <button class="btn btn-link btn-sm me-2 text-white d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <a class="navbar-brand d-none d-md-flex align-items-center" href="<?php echo BASE_URL; ?>dashboard.php">
                <img src="<?php echo SISTEMA_LOGO; ?>" alt="Logo" height="30" class="d-inline-block align-top me-2">
                <span class="d-none d-lg-inline"><?php echo SISTEMA_EMPRESA; ?></span>
            </a>
            
            <div class="ms-auto">
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                3 </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">Notificações</h6></li>
                            <li><a class="dropdown-item" href="#">Nova OS cadastrada</a></li>
                            <li><a class="dropdown-item" href="#">Pagamento recebido</a></li>
                            <li><a class="dropdown-item" href="#">Locação atrasada</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#">Ver todas</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <span class="d-none d-md-inline"><?php echo $_SESSION['usuario_nome'] ?? 'Usuário'; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>modulos/usuarios/perfil.php"><i class="bi bi-person me-2"></i>Perfil</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>modulos/configuracoes/sistema.php"><i class="bi bi-gear me-2"></i>Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div id="wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <div id="page-content-wrapper">
            <div class="container-fluid px-4 py-3">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800"><?php echo $titulo_pagina; ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb bg-transparent mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a></li>
                            <?php if (isset($breadcrumb)): ?>
                                <?php foreach ($breadcrumb as $item): ?>
                                    <li class="breadcrumb-item<?php echo empty($item['link']) ? ' active' : ''; ?>">
                                        <?php if (!empty($item['link'])): ?>
                                            <a href="<?php echo BASE_URL . $item['link']; ?>"><?php echo $item['texto']; ?></a>
                                        <?php else: ?>
                                            <?php echo $item['texto']; ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ol>
                    </nav>
                </div>
                
                <?php exibirMensagemFlash(); ?>
