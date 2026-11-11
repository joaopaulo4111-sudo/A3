<?php
/**
 * ============================================================
 *  AutoGestão - Registro de Pagamento
 *  Arquivo: pagamentos/cadastrar.php
 * ============================================================
 *  Implementa:
 *   - RF10: Registro de pagamentos parciais ou integrais de vendas.
 *   - RN10: Validação estrita: a soma dos pagamentos não pode
 *     ultrapassar o saldo devedor da venda.
 *   - Transações PDO para consistência financeira.
 * ============================================================
 */

require_once __DIR__ . '/../includes/auth.php';
exigirLogin();

$tituloPagina = 'Lançar Pagamento';
$paginaAtiva  = 'pagamentos';

$pdo = conectar();

// Venda pré-selecionada via GET/POST
$idVendaSel = (int) ($_GET['id_venda'] ?? $_POST['id_venda'] ?? 0);

// Busca vendas ativas que possuem saldo pendente
$sqlVendasComSaldo = "
    SELECT v.id_venda, v.valor_total, v.data_venda,
           c.nome AS cliente_nome, c.cpf AS cliente_cpf,
           COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS total_pago,
           v.valor_total - COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS saldo_devedor
      FROM vendas v
      JOIN clientes c        ON c.id_cliente = v.id_cliente
      LEFT JOIN pagamentos p ON p.id_venda = v.id_venda
     WHERE v.status <> 'cancelada'
     GROUP BY v.id_venda, v.valor_total, v.data_venda, c.nome, c.cpf
    HAVING saldo_devedor > 0.009
     ORDER BY v.id_venda DESC
";
$vendasPendentes = $pdo->query($sqlVendasComSaldo)->fetchAll();

// Se uma venda foi selecionada, busca seus dados específicos
$vendaEscolhida = null;
if ($idVendaSel > 0) {
    foreach ($vendasPendentes as $vp) {
        if ((int) $vp['id_venda'] === $idVendaSel) {
            $vendaEscolhida = $vp;
            break;
        }
    }
}

$erros = [];
$valorStr       = $vendaEscolhida ? number_format((float) $vendaEscolhida['saldo_devedor'], 2, ',', '.') : '';
$formaPagamento = 'pix';
$status         = 'pago';
$dataPagamento  = date('Y-m-d\TH:i');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCSRF();

    $idVendaSel     = (int) post('id_venda');
    $valorStr       = post('valor');
    $formaPagamento = post('forma_pagamento');
    $status         = post('status');
    $dataPagamento  = post('data_pagamento');

    $valor = converterValor($valorStr);

    if ($idVendaSel <= 0) {
        $erros[] = 'Selecione a venda para a qual deseja registrar o pagamento.';
    }

    if ($valor <= 0) {
        $erros[] = 'O valor do pagamento deve ser maior que zero.';
    }

    if (!in_array($formaPagamento, ['dinheiro', 'pix', 'cartão', 'transferência'], true)) {
        $erros[] = 'Selecione uma forma de pagamento válida.';
    }

    if (!in_array($status, ['pago', 'pendente'], true)) {
        $erros[] = 'Selecione o status do pagamento.';
    }

    if (empty($dataPagamento)) {
        $dataPagamento = date('Y-m-d H:i:s');
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            // Bloqueia a linha da venda para leitura consistente (FOR UPDATE)
            $stmtCheca = $pdo->prepare("
                SELECT v.id_venda, v.valor_total, v.status,
                       COALESCE(SUM(CASE WHEN p.status = 'pago' THEN p.valor END), 0) AS total_pago
                  FROM vendas v
                  LEFT JOIN pagamentos p ON p.id_venda = v.id_venda
                 WHERE v.id_venda = :id
                 GROUP BY v.id_venda, v.valor_total, v.status
                 FOR UPDATE
            ");
            $stmtCheca->execute([':id' => $idVendaSel]);
            $vendaAtual = $stmtCheca->fetch();

            if (!$vendaAtual) {
                throw new Exception('Venda não encontrada no sistema.');
            }

            if ($vendaAtual['status'] === 'cancelada') {
                throw new Exception('Não é possível registrar pagamentos para uma venda cancelada.');
            }

            $saldoAtual = (float) $vendaAtual['valor_total'] - (float) $vendaAtual['total_pago'];

            // RN10: Pagamentos não podem ultrapassar o saldo devedor
            if ($status === 'pago' && ($valor - $saldoAtual) > 0.009) {
                throw new Exception(
                    'O valor informado (R$ ' . number_format($valor, 2, ',', '.') . ') ultrapassa o saldo devedor restante da venda (R$ '
                    . number_format($saldoAtual, 2, ',', '.') . '). Regra RN10.'
                );
            }

            // Grava o pagamento
            $stmtInsert = $pdo->prepare('
                INSERT INTO pagamentos (id_venda, valor, forma_pagamento, data_pagamento, status)
                VALUES (:id_venda, :valor, :forma, :data, :status)
            ');
            $stmtInsert->execute([
                ':id_venda' => $idVendaSel,
                ':valor'    => $valor,
                ':forma'    => $formaPagamento,
                ':data'     => date('Y-m-d H:i:s', strtotime($dataPagamento)),
                ':status'   => $status,
            ]);

            $idPagamento = (int) $pdo->lastInsertId();

            $pdo->commit();

            definirMensagem('success', 'Pagamento #' . $idPagamento . ' de R$ ' . number_format($valor, 2, ',', '.') . ' registrado com sucesso!');
            redirecionar('vendas/visualizar.php?id=' . $idVendaSel);

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erro ao registrar pagamento: ' . $e->getMessage());
            $erros[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 fw-bold text-dark mb-1">Registrar Pagamento</h2>
        <p class="text-muted small mb-0">Lance um pagamento parcial ou integral associado a uma venda.</p>
    </div>
    <div>
        <a href="<?= url('pagamentos/index.php') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Voltar aos Pagamentos
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card-app">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-cash-coin text-primary"></i> Dados do Recebimento
                </h3>
            </div>
            <div class="card-body">

                <?php if (!empty($erros)): ?>
                    <div class="alert alert-danger shadow-sm mb-4">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Atenção:</div>
                        <ul class="mb-0 ps-3">
                            <?php foreach ($erros as $erro): ?>
                                <li><?= e($erro) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (empty($vendasPendentes)): ?>
                    <div class="alert alert-info shadow-sm mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        Todas as vendas ativas estão totalmente quitadas! Não há vendas com saldo em aberto no momento.
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= url('pagamentos/cadastrar.php') ?>" novalidate>
                    <?= campoCSRF() ?>

                    <div class="mb-3">
                        <label for="id_venda" class="form-label fw-semibold text-secondary small">
                            SELECIONE A VENDA <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="id_venda" name="id_venda" required autofocus
                                onchange="atualizarSaldoVenda(this)">
                            <option value="">Selecione uma venda com saldo pendente...</option>
                            <?php foreach ($vendasPendentes as $vp): ?>
                                <option value="<?= $vp['id_venda'] ?>"
                                        data-saldo="<?= (float) $vp['saldo_devedor'] ?>"
                                        data-total="<?= (float) $vp['valor_total'] ?>"
                                        data-cliente="<?= e($vp['cliente_nome']) ?>"
                                        <?= $idVendaSel === (int) $vp['id_venda'] ? 'selected' : '' ?>>
                                    Venda #<?= $vp['id_venda'] ?> &mdash; <?= e($vp['cliente_nome']) ?> (Saldo Restante: <?= formatarMoeda($vp['saldo_devedor']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Caixa com o saldo da venda selecionada -->
                    <div class="p-3 bg-light rounded border mb-3" id="boxSaldo" style="display: <?= $vendaEscolhida ? 'block' : 'none' ?>;">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted small">Saldo Devedor desta Venda (RN10):</span>
                                <div class="fs-5 fw-bold text-danger" id="txtSaldoDevedor">
                                    <?= $vendaEscolhida ? formatarMoeda($vendaEscolhida['saldo_devedor']) : 'R$ 0,00' ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="preencherValorTotal()">
                                Preencher Saldo Total
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="valor" class="form-label fw-semibold text-secondary small">
                                VALOR DO PAGAMENTO (R$) <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control mascara-moeda fw-bold text-success fs-5"
                                   id="valor" name="valor" placeholder="0,00" value="<?= e($valorStr) ?>" required>
                            <div class="form-text">Pode ser parcelado ou integral, até o saldo devedor (RN10).</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="forma_pagamento" class="form-label fw-semibold text-secondary small">
                                FORMA DE PAGAMENTO <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="forma_pagamento" name="forma_pagamento" required>
                                <option value="pix"           <?= $formaPagamento === 'pix' ? 'selected' : '' ?>>PIX</option>
                                <option value="transferência" <?= $formaPagamento === 'transferência' ? 'selected' : '' ?>>Transferência Bancária</option>
                                <option value="cartão"        <?= $formaPagamento === 'cartão' ? 'selected' : '' ?>>Cartão de Crédito / Débito</option>
                                <option value="dinheiro"      <?= $formaPagamento === 'dinheiro' ? 'selected' : '' ?>>Dinheiro em Espécie</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="status" class="form-label fw-semibold text-secondary small">
                                SITUAÇÃO DO PAGAMENTO <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="pago"     <?= $status === 'pago' ? 'selected' : '' ?>>Pago (Valor liquidado na conta)</option>
                                <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente (Aguardando compensação)</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="data_pagamento" class="form-label fw-semibold text-secondary small">
                                DATA E HORA DO PAGAMENTO <span class="text-danger">*</span>
                            </label>
                            <input type="datetime-local" class="form-control" id="data_pagamento" name="data_pagamento"
                                   value="<?= e($dataPagamento) ?>" required>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="<?= url('pagamentos/index.php') ?>" class="btn btn-light border">Cancelar</a>
                        <button type="submit" class="btn btn-navy" <?= empty($vendasPendentes) ? 'disabled' : '' ?>>
                            <i class="bi bi-check-lg me-1"></i> Confirmar Pagamento
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
let saldoVendaAtual = <?= $vendaEscolhida ? (float) $vendaEscolhida['saldo_devedor'] : 0 ?>;

function atualizarSaldoVenda(select) {
    const opt = select.options[select.selectedIndex];
    const box = document.getElementById('boxSaldo');
    if (!opt || !opt.value) {
        box.style.display = 'none';
        saldoVendaAtual = 0;
        return;
    }

    saldoVendaAtual = parseFloat(opt.getAttribute('data-saldo')) || 0;
    document.getElementById('txtSaldoDevedor').textContent = 'R$ ' + saldoVendaAtual.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    box.style.display = 'block';
}

function preencherValorTotal() {
    if (saldoVendaAtual > 0) {
        document.getElementById('valor').value = saldoVendaAtual.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
