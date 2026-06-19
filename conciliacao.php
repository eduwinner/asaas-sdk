<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

use AsaasSDK\AsaasClient;
use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$apiKey = asaas_config('api_key');
if (!$apiKey) { http_response_code(500); exit('API key não configurada. Crie config.php no servidor (veja config.example.php).'); }

$sandbox = (bool) asaas_config('sandbox', false);
$factory = new Psr17Factory();
$asaas   = new AsaasClient(
    apiKey: $apiKey,
    httpClient: new GuzzleClient(['http_errors' => false]),
    requestFactory: $factory,
    streamFactory: $factory,
    sandbox: $sandbox,
);

// ---------------------------------------------------------------------------
// Período padrão: mês atual
// ---------------------------------------------------------------------------

$startDate  = $_GET['startDate']  ?? date('Y-m-01');
$finishDate = $_GET['finishDate'] ?? date('Y-m-d');
$typeFiltro = $_GET['type']       ?? '';
$offset     = max(0, (int) ($_GET['offset'] ?? 0));
$limit      = 25;

$filters = ['limit' => $limit, 'offset' => $offset, 'startDate' => $startDate, 'finishDate' => $finishDate];
if ($typeFiltro !== '') $filters['type'] = $typeFiltro;

// ---------------------------------------------------------------------------
// Dados
// ---------------------------------------------------------------------------

$transactions = [];
$totalCount   = 0;
$balance      = [];
$listError    = '';

try {
    $balance = $asaas->getBalance();
} catch (\Throwable $e) {
    $balance = ['balance' => 0, 'availableForWithdrawal' => 0];
}

try {
    $result       = $asaas->listFinancialTransactions($filters);
    $transactions = $result['data']       ?? [];
    $totalCount   = $result['totalCount'] ?? 0;
} catch (\Throwable $e) {
    $listError = $e->getMessage();
}

// ---------------------------------------------------------------------------
// Totais do período (calculado sobre a página atual, não o total)
// Para totais reais do período, buscar sem paginação é necessário.
// Usamos os dados retornados para exibir resumo da página.
// ---------------------------------------------------------------------------

$totalCredito = 0.0;
$totalDebito  = 0.0;

foreach ($transactions as $t) {
    $v = (float) ($t['value'] ?? 0);
    if ($v >= 0) $totalCredito += $v;
    else         $totalDebito  += abs($v);
}

// ---------------------------------------------------------------------------
// Lógica de conciliação automática
// ---------------------------------------------------------------------------

/**
 * Retorna status de conciliação baseado nos campos do movimento.
 * CONCILIADO   — vinculado a um documento (payment, bill, transfer)
 * PARCIAL      — tem descrição mas sem link estruturado
 * PENDENTE     — sem vínculo identificável
 */
function reconciliationStatus(array $t): string
{
    if (!empty($t['payment']['id']))  return 'CONCILIADO';
    if (!empty($t['transferId']))     return 'CONCILIADO';
    if (!empty($t['bill']['id']))     return 'CONCILIADO';
    if (!empty($t['description']))    return 'PARCIAL';
    return 'PENDENTE';
}

function reconciliationBadge(string $s): string
{
    return match ($s) {
        'CONCILIADO' => '<span class="badge bg-success">Conciliado</span>',
        'PARCIAL'    => '<span class="badge bg-warning text-dark">Parcial</span>',
        default      => '<span class="badge bg-danger">Sem vínculo</span>',
    };
}

function docLink(array $t): string
{
    if (!empty($t['payment']['id'])) {
        $id   = $t['payment']['id'];
        $desc = $t['payment']['description'] ?? $id;
        return '<span class="text-success small" title="' . htmlspecialchars($id) . '">
                    ↑ Cobrança: ' . htmlspecialchars(mb_strimwidth($desc, 0, 35, '…')) . '
                </span>';
    }
    if (!empty($t['transferId'])) {
        return '<span class="text-primary small">↔ Transferência</span>';
    }
    if (!empty($t['bill']['id'])) {
        $id = $t['bill']['id'];
        return '<span class="text-danger small" title="' . htmlspecialchars($id) . '">
                    ↓ Conta a pagar
                </span>';
    }
    return '<span class="text-muted small">—</span>';
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function fmtDate(?string $d): string
{
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $d;
}

function fmtMoney(float $v, bool $sign = false): string
{
    $fmt = 'R$&nbsp;' . number_format(abs($v), 2, ',', '.');
    if ($sign) $fmt = ($v >= 0 ? '+' : '−') . ' ' . $fmt;
    return $fmt;
}

function typeBadge(float $v): string
{
    return $v >= 0
        ? '<span class="badge bg-success-subtle border border-success text-success">Crédito</span>'
        : '<span class="badge bg-danger-subtle border border-danger text-danger">Débito</span>';
}

$hasPrev = $offset > 0;
$hasNext = ($offset + $limit) < $totalCount;
$qBase   = http_build_query(array_filter([
    'startDate'  => $startDate,
    'finishDate' => $finishDate,
    'type'       => $typeFiltro,
]));

$prevOffset = max(0, $offset - $limit);
$nextOffset = $offset + $limit;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conciliação Bancária — Asaas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body        { background: #f8f9fa; }
        .table th   { white-space: nowrap; font-size: .85rem; }
        .table td   { font-size: .85rem; vertical-align: middle; }
        .mono       { font-family: monospace; }
        .row-credit { background: #f0fff4; }
        .row-debit  { background: #fff5f5; }
        .saldo-box  { font-size: 1.5rem; font-weight: 700; }
        .card-stat  { border-left: 4px solid; }
    </style>
</head>
<body>
<div class="container-lg py-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">Conciliação Bancária</h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?= $sandbox ? 'warning text-dark' : 'success' ?>">
                <?= $sandbox ? 'Sandbox' : 'Produção' ?>
            </span>
            <a href="contas-receber.php" class="btn btn-sm btn-outline-secondary">Receber</a>
            <a href="contas-pagar.php"   class="btn btn-sm btn-outline-secondary">Pagar</a>
        </div>
    </div>

    <!-- Cards de saldo -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm card-stat border-primary">
                <div class="card-body">
                    <div class="text-muted small">Saldo atual</div>
                    <div class="saldo-box text-primary">
                        <?= fmtMoney((float)($balance['balance'] ?? 0)) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm card-stat border-success">
                <div class="card-body">
                    <div class="text-muted small">Disponível p/ saque</div>
                    <div class="saldo-box text-success">
                        <?= fmtMoney((float)($balance['availableForWithdrawal'] ?? 0)) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm card-stat border-secondary">
                <div class="card-body">
                    <div class="text-muted small">Período (página atual)</div>
                    <div class="d-flex gap-3 mt-1">
                        <div>
                            <span class="text-success fw-bold"><?= fmtMoney($totalCredito) ?></span>
                            <div class="text-muted small">créditos</div>
                        </div>
                        <div>
                            <span class="text-danger fw-bold"><?= fmtMoney($totalDebito) ?></span>
                            <div class="text-muted small">débitos</div>
                        </div>
                        <div>
                            <?php $liquido = $totalCredito - $totalDebito; ?>
                            <span class="fw-bold <?= $liquido >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= fmtMoney($liquido, true) ?>
                            </span>
                            <div class="text-muted small">líquido</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($listError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($listError) ?></div>
    <?php endif; ?>

    <!-- Filtros -->
    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-md-2">
            <label class="form-label small mb-1">De</label>
            <input type="date" name="startDate" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Até</label>
            <input type="date" name="finishDate" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($finishDate) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Tipo</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="CREDIT" <?= $typeFiltro === 'CREDIT' ? 'selected' : '' ?>>Créditos</option>
                <option value="DEBIT"  <?= $typeFiltro === 'DEBIT'  ? 'selected' : '' ?>>Débitos</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a href="?startDate=<?= date('Y-m-01') ?>&finishDate=<?= date('Y-m-d') ?>"
               class="btn btn-outline-secondary btn-sm">Mês atual</a>
        </div>
        <div class="col-auto ms-auto">
            <span class="form-text"><?= $totalCount ?> movimentação(ões)</span>
        </div>
    </form>

    <!-- Legenda conciliação -->
    <div class="d-flex gap-3 mb-2 small text-muted">
        <span><span class="badge bg-success">Conciliado</span> — vinculado a cobrança, conta a pagar ou transferência</span>
        <span><span class="badge bg-warning text-dark">Parcial</span> — tem descrição, sem documento linkado</span>
        <span><span class="badge bg-danger">Sem vínculo</span> — requer revisão manual</span>
    </div>

    <!-- Tabela -->
    <?php if ($transactions): ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Documento vinculado</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Saldo após</th>
                        <th>Conciliação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($transactions as $t):
                    $v    = (float) ($t['value'] ?? 0);
                    $bal  = (float) ($t['balance'] ?? 0);
                    $reco = reconciliationStatus($t);
                    $rowClass = $v >= 0 ? 'row-credit' : 'row-debit';
                ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="mono"><?= fmtDate($t['date'] ?? null) ?></td>
                        <td><?= typeBadge($v) ?></td>
                        <td><?= htmlspecialchars(mb_strimwidth($t['description'] ?? '—', 0, 60, '…')) ?></td>
                        <td><?= docLink($t) ?></td>
                        <td class="text-end mono fw-bold <?= $v >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= fmtMoney($v, true) ?>
                        </td>
                        <td class="text-end mono text-muted">
                            <?= fmtMoney($bal) ?>
                        </td>
                        <td><?= reconciliationBadge($reco) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação -->
    <nav>
        <ul class="pagination pagination-sm">
            <li class="page-item <?= !$hasPrev ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= $qBase ?>&offset=<?= $prevOffset ?>">&laquo; Anterior</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link text-muted">
                    <?= $offset + 1 ?>–<?= min($offset + $limit, $totalCount) ?> de <?= $totalCount ?>
                </span>
            </li>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                <a class="page-link" href="?<?= $qBase ?>&offset=<?= $nextOffset ?>">Próxima &raquo;</a>
            </li>
        </ul>
    </nav>

    <?php else: ?>
        <div class="text-muted">Nenhuma movimentação no período selecionado.</div>
    <?php endif; ?>

    <!-- Nota técnica -->
    <div class="alert alert-light border mt-4 small text-muted">
        <strong>Como funciona a conciliação automática:</strong>
        cada movimento do extrato Asaas carrega o campo <code>payment.id</code> (se gerado por cobrança),
        <code>transferId</code> (se transferência) ou <code>bill.id</code> (se conta a pagar).
        Movimentos sem vínculo são taxas, ajustes manuais ou entradas via depósito externo — precisam de
        conciliação manual no seu sistema.
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
