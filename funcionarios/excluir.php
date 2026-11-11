<?php
/**
 * ============================================================
 *  AutoGestão - Exclusão de Funcionário
 *  Arquivo: funcionarios/excluir.php
 * ============================================================
 *  Implementa:
 *   - RF05: Exclusão controlada de funcionários.
 *   - RN13: Preservação de integridade histórica (impede exclusão
 *     de funcionários que possuem vendas cadastradas).
 *   - Impede que o administrador logado exclua a si próprio.
 *   - Restrito a administradores (exigirAdmin) e protegido por CSRF.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    definirMensagem('warning', 'Método de requisição inválido para esta operação.');
    redirecionar('funcionarios/index.php');
}

validarCSRF();

$id = (int) post('id_funcionario');

if ($id <= 0) {
    definirMensagem('danger', 'Identificador de funcionário inválido.');
    redirecionar('funcionarios/index.php');
}

// Impede que o próprio usuário logado exclua seu cadastro
$usuarioAtual = usuarioLogado();
if (($usuarioAtual['id_funcionario'] ?? 0) === $id) {
    definirMensagem('danger', 'Operação não permitida: você não pode excluir seu próprio cadastro de funcionário.');
    redirecionar('funcionarios/index.php');
}

$pdo = conectar();

try {
    // 1. Verifica se o funcionário existe
    $stmtFunc = $pdo->prepare('SELECT nome FROM funcionarios WHERE id_funcionario = :id LIMIT 1');
    $stmtFunc->execute([':id' => $id]);
    $funcionario = $stmtFunc->fetch();

    if (!$funcionario) {
        definirMensagem('danger', 'Funcionário não encontrado no sistema.');
        redirecionar('funcionarios/index.php');
    }

    // 2. RN13: Verifica se o funcionário possui vendas registradas
    $stmtVendas = $pdo->prepare('SELECT COUNT(*) FROM vendas WHERE id_funcionario = :id');
    $stmtVendas->execute([':id' => $id]);
    $totalVendas = (int) $stmtVendas->fetchColumn();

    if ($totalVendas > 0) {
        definirMensagem(
            'danger',
            'Não é possível excluir o colaborador "' . $funcionario['nome'] . '" pois constam '
            . $totalVendas . ' venda(s) associada(s) ao seu histórico profissional (Regra RN13).'
        );
        redirecionar('funcionarios/index.php');
    }

    // 3. Efetua a exclusão dentro de transação (remove usuário vinculado se houver e o funcionário)
    $pdo->beginTransaction();

    $stmtDelUser = $pdo->prepare('DELETE FROM usuarios WHERE id_funcionario = :id');
    $stmtDelUser->execute([':id' => $id]);

    $stmtDelFunc = $pdo->prepare('DELETE FROM funcionarios WHERE id_funcionario = :id');
    $stmtDelFunc->execute([':id' => $id]);

    $pdo->commit();

    definirMensagem('success', 'Funcionário "' . $funcionario['nome'] . '" excluído com sucesso.');
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro ao excluir funcionário: ' . $e->getMessage());
    definirMensagem('danger', 'Ocorreu um erro ao tentar excluir o funcionário. Verifique se existem dependências ativas.');
}

redirecionar('funcionarios/index.php');
