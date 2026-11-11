<?php
/**
 * ============================================================
 *  AutoGestão - Cancelamento de Pagamento
 *  Arquivo: pagamentos/cancelar.php
 * ============================================================
 *  Implementa:
 *   - Cancelamento seguro de lançamentos de pagamento.
 *   - Proteção CSRF.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definirMensagem('warning', 'Método de requisição inválido para esta operação.');
    redirecionar('pagamentos/index.php');
}

validarCSRF();

$id = (int) post('id_pagamento');

if ($id <= 0) {
    definirMensagem('danger', 'Identificador de pagamento inválido.');
    redirecionar('pagamentos/index.php');
}

$pdo = conectar();

try {
    $stmt = $pdo->prepare('SELECT id_pagamento, id_venda, valor, status FROM pagamentos WHERE id_pagamento = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $pagamento = $stmt->fetch();

    if (!$pagamento) {
        definirMensagem('danger', 'Pagamento não encontrado no sistema.');
        redirecionar('pagamentos/index.php');
    }

    if ($pagamento['status'] === 'cancelado') {
        definirMensagem('warning', 'Este pagamento já se encontra cancelado.');
        redirecionar('vendas/visualizar.php?id=' . $pagamento['id_venda']);
    }

    $stmtCancela = $pdo->prepare('UPDATE pagamentos SET status = "cancelado" WHERE id_pagamento = :id');
    $stmtCancela->execute([':id' => $id]);

    definirMensagem('success', 'Pagamento #' . $id . ' no valor de R$ ' . number_format((float) $pagamento['valor'], 2, ',', '.') . ' foi cancelado com sucesso.');
    redirecionar('vendas/visualizar.php?id=' . $pagamento['id_venda']);
} catch (PDOException $e) {
    error_log('Erro ao cancelar pagamento: ' . $e->getMessage());
    definirMensagem('danger', 'Ocorreu um erro ao cancelar o pagamento. Tente novamente.');
    redirecionar('pagamentos/index.php');
}
