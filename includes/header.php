<?php
/**
 * ============================================================
 *  Magnata Automóveis - Cabeçalho padrão das páginas internas
 *  Arquivo: includes/header.php
 * ============================================================
 *  Como usar em uma página:
 *
 *    require_once __DIR__ . '/../includes/functions.php';
 *    $tituloPagina = 'Clientes';      // texto da aba e do topo
 *    $paginaAtiva  = 'clientes';      // item destacado na sidebar
 *    require_once __DIR__ . '/../includes/header.php';
 *       ... conteúdo da página ...
 *    require_once __DIR__ . '/../includes/footer.php';
 * ============================================================
 */

require_once __DIR__ . '/functions.php';

$tituloPagina = $tituloPagina ?? 'Magnata Automóveis';
$paginaAtiva  = $paginaAtiva  ?? '';
$usuario      = usuarioLogado();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?> | Magnata Automóveis</title>

    <link rel="icon" type="image/svg+xml" href="<?= url('assets/img/logo.svg') ?>">

    <!-- Bootstrap 5 e Bootstrap Icons (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Fonte Inter (Google Fonts) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Estilos próprios (Preto e Dourado) -->
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<div class="app-wrapper">

    <?php require __DIR__ . '/sidebar.php'; ?>

    <!-- Fundo escuro exibido atrás da sidebar no celular -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-main">

        <!-- Barra superior -->
        <header class="topbar">
            <button class="btn btn-icon d-lg-none me-2" id="btnSidebar" type="button" aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>

            <h1 class="topbar-title"><?= e($tituloPagina) ?></h1>

            <div class="ms-auto d-flex align-items-center gap-3">
                <span class="text-muted small d-none d-md-inline">
                    <i class="bi bi-calendar3 me-1 text-gold"></i><?= date('d/m/Y') ?>
                </span>

                <?php if ($usuario): ?>
                    <div class="dropdown">
                        <button class="btn btn-user dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($usuario['nome'], 0, 1))) ?></span>
                            <span class="d-none d-sm-inline"><?= e($usuario['nome']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li class="dropdown-header">
                                <span class="fw-bold text-dark"><?= e($usuario['email']) ?></span><br>
                                <small class="text-capitalize text-muted"><?= e($usuario['perfil']) ?></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= url('auth/logout.php') ?>">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sair
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Conteúdo da página -->
        <main class="app-content">
            <?php exibirMensagens(); ?>
