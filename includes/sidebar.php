<?php
/**
 * ============================================================
 *  Magnata Automóveis - Menu lateral (sidebar)
 *  Arquivo: includes/sidebar.php
 * ============================================================
 *  Incluído automaticamente pelo header.php.
 *  Itens com 'admin' => true só aparecem para administradores.
 * ============================================================
 */

$sidebarMenu = [
    'Principal' => [
        ['chave' => 'dashboard',    'texto' => 'Dashboard',    'icone' => 'bi-speedometer2',    'link' => 'dashboard.php'],
    ],
    'Cadastros' => [
        ['chave' => 'clientes',     'texto' => 'Clientes',     'icone' => 'bi-people',          'link' => 'clientes/index.php'],
        ['chave' => 'funcionarios', 'texto' => 'Funcionários', 'icone' => 'bi-person-badge',    'link' => 'funcionarios/index.php', 'admin' => true],
        ['chave' => 'marcas',       'texto' => 'Marcas',       'icone' => 'bi-tags',            'link' => 'marcas/index.php'],
        ['chave' => 'veiculos',     'texto' => 'Veículos',     'icone' => 'bi-car-front',       'link' => 'veiculos/index.php'],
    ],
    'Comercial' => [
        ['chave' => 'vendas',       'texto' => 'Vendas',       'icone' => 'bi-cart-check',      'link' => 'vendas/index.php'],
        ['chave' => 'pagamentos',   'texto' => 'Pagamentos',   'icone' => 'bi-cash-coin',       'link' => 'pagamentos/index.php'],
    ],
    'Relatórios' => [
        ['chave' => 'rel_vendas',   'texto' => 'Vendas',       'icone' => 'bi-graph-up-arrow',  'link' => 'relatorios/vendas.php'],
        ['chave' => 'rel_estoque',  'texto' => 'Estoque',      'icone' => 'bi-box-seam',        'link' => 'relatorios/estoque.php'],
    ],
];
?>
<aside class="sidebar" id="sidebar">
    <a href="<?= url('dashboard.php') ?>" class="sidebar-brand">
        <img src="<?= url('assets/img/logo.svg') ?>" alt="Magnata Automóveis" width="38" height="38">
        <div>
            <span class="brand-name">Magnata Automóveis</span>
            <span class="brand-sub">Veículos Premium</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <?php foreach ($sidebarMenu as $sidebarSecao => $sidebarItens): ?>
            <?php
            // Remove os itens restritos quando o usuário não é administrador
            $sidebarItensVisiveis = array_filter($sidebarItens, fn($sidebarItem) => empty($sidebarItem['admin']) || ehAdministrador());
            if (!$sidebarItensVisiveis) {
                continue;
            }
            ?>
            <div class="sidebar-section"><?= e($sidebarSecao) ?></div>
            <ul class="nav flex-column">
                <?php foreach ($sidebarItensVisiveis as $sidebarItem): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $paginaAtiva === $sidebarItem['chave'] ? 'active' : '' ?>"
                           href="<?= url($sidebarItem['link']) ?>">
                            <i class="bi <?= $sidebarItem['icone'] ?>"></i>
                            <span><?= e($sidebarItem['texto']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <?php if ($sidebarUsuario = usuarioLogado()): ?>
        <div class="sidebar-user">
            <span class="avatar"><?= e(mb_strtoupper(mb_substr($sidebarUsuario['nome'], 0, 1))) ?></span>
            <div class="flex-grow-1 overflow-hidden">
                <div class="user-name text-truncate"><?= e($sidebarUsuario['nome']) ?></div>
                <div class="user-role text-capitalize"><?= e($sidebarUsuario['perfil']) ?></div>
            </div>
            <a href="<?= url('auth/logout.php') ?>" class="btn-logout" title="Sair">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    <?php endif; ?>
</aside>
