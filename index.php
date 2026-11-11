<?php
/**
 * ============================================================
 *  AutoGestão - Ponto de Entrada da Raiz
 *  Arquivo: index.php
 * ============================================================
 *  Redireciona o usuário para o Dashboard se ele já estiver
 *  autenticado, ou para a tela de Login caso contrário.
 * ============================================================
 */

require_once __DIR__ . '/includes/functions.php';

if (usuarioLogado()) {
    redirecionar('dashboard.php');
} else {
    redirecionar('auth/login.php');
}
