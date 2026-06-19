<?php

require_once __DIR__ . '/vendor/autoload.php';

use AsaasSDK\AsaasClient;
use AsaasSDK\Exceptions\HttpException;
use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$apiKey = getenv('ASAAS_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    exit('ASAAS_API_KEY não configurado no servidor.');
}

$sandbox = (bool) getenv('ASAAS_SANDBOX');
$factory = new Psr17Factory();

$asaas = new AsaasClient(
    apiKey: $apiKey,
    httpClient: new GuzzleClient(['http_errors' => false]),
    requestFactory: $factory,
    streamFactory: $factory,
    sandbox: $sandbox,
);

// ---------------------------------------------------------------------------
// Filtros
// ---------------------------------------------------------------------------

$statusFiltro      = $_GET['status']      ?? '';
$billingFiltro     = $_GET['billingType'] ?? '';
$dueFrom           = $_GET['dueFrom']     ?? '';
$dueTo             = $_GET['dueTo']       ?? '';
$offset            = max(0, (int) ($_GET['offset'] ?? 0));
$limit             = 20;

$filters = ['limit' => $limit, 'offset' => $offset];
if ($statusFiltro  !== '') $filters['status']      = $statusFiltro;
if ($billingFiltro !== '') $filters['billingType'] = $billingFiltro;
if ($dueFrom       !== '') $filters['dueDateStart'] = $dueFrom;
if ($dueTo         !== '') $filters['dueDateFinish'] = $dueTo;

$payments   = [];
$totalCount = 0;
$listError  = '';

try {
    $result     = $asaas->listPayments($filters);
    $payments   = $result['data']       ?? [];
    $totalCount = $result['totalCount'] ?? 0;
} catch (\Throwable $e) {
    $listError = $e->getMessage();
}

// ---------------------------------------------------------------------------
// Resumo financeiro (3 chamadas paralelas em série — sandbox-friendly)
// ---------------------------------------------------------------------------

function fetchTotal(AsaasClient $c, string $status): array
{
    try {
        $r = $c->listPayments(['status' => $status, 'limit' => 1]);
        return ['count' => $r['totalCount'] ?? 0];
    } catch (\Throwable) {
        return ['count' => 0];
    }
}

$sumPending  = fetchTotal($asaas, 'PENDING');
$sumReceived = fetchTotal($asaas, 'RECEIVED');
$sumOverdue  = fetchTotal($asaas, 'OVERDUE');

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function statusBadge(string $s): string
{
    $map = [
        'PENDING'            => ['warning text-dark', 'Pendente'],
        'RECEIVED'           => ['success',           'Recebido'],
        'CONFIRMED'          => ['success',           'Confirmado'],
        'OVERDUE'            => ['danger',            'Vencido'],
        'REFUNDED'           => ['secondary',         'Estornado'],
        'RECEIVED_IN_CASH'   => ['info text-dark',    'Rec. em dinheiro'],
        'REFUND_REQUESTED'   => ['warning text-dark', 'Estorno solicitado'],
        'CHARGEBACK_REQUESTED' => ['danger',          'Chargeback'],
        'DUNNING_REQUESTED'  => ['dark',              'Negativação'],
        'DUNNING_RECEIVED'   => ['dark',              'Negativado'],
        'CANCELLED'          => ['secondary',         'Cancelado'],
    ];
    [$cls, $lbl] = $map[$s] ?? ['light text-dark', $s];
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}

function billingBadge(string $b): string
{
    $map = [
        'BOLETO'      => ['primary', 'Boleto'],
        'PIX'         => ['success', 'Pix'],
        'CREDIT_CARD' => ['info text-dark', 'Cartão'],
        'DEBIT_CARD'  => ['info text-dark', 'Débito'],
        'UNDEFINED'   => ['secondary', '—'],
    ];
    [$cls, $lbl] = $map[$b] ?? ['secondary', $b];
    return "<span class=\"badge bg-{$cls}\">{$lbl}</span>";
}

function fmtDate(?string $d): string
{
    if (!$d) return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $d;
}

function fmtMoney(float $v): string
{
    return 'R$&nbsp;' . number_format($v, 2, ',', '.');
}

function isReceivable(string $status): bool
{
    return in_array($status, ['PENDING', 'OVERDUE'], true);
}

$prevOffset = max(0, $offset - $limit);
$nextOffset = $offset + $limit;
$hasPrev    = $offset > 0;
$hasNext    = ($offset + $limit) < $totalCount;

$qBase = http_build_query(array_filter([
    'status'      => $statusFiltro,
    'billingType' => $billingFiltro,
    'dueFrom'     => $dueFrom,
    'dueTo'       => $dueTo,
]));

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contas a Receber — Asaas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body        { background: #f8f9fa; }
        .table th   { white-space: nowrap; }
        .id-col     { font-family: monospace; font-size: .78rem; color: #6c757d; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .link-boleto{ font-size: .8rem; }
        .card-stat  { border-left: 4px solid; }
    </style>
</head>
<body>
<div class="container-lg py-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0">Contas a Receber</h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?= $sandbox ? 'warning text-dark' : 'success' ?>">
                <?= $sandbox ? 'Sandbox' : 'Produção' ?>
            </span>
            <a href="contas-pagar.php" class="btn btn-sm btn-outline-secondary">→ Contas a Pagar</a>
        </div>
    </div>

    <!-- Resumo -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm card-stat border-warning h-100">
                <div class="card-body">
                    <div class="text-muted small">Pendentes</div>
                    <div class="fs-4 fw-bold"><?= $sumPending['count'] ?></div>
                    <a href="?status=PENDING" class="stretched-link text-decoration-none small">Ver pendentes &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm card-stat border-success h-100">
                <div class="card-body">
                    <div class="text-muted small">Recebidos</div>
                    <div class="fs-4 fw-bold"><?= $sumReceived['count'] ?></div>
                    <a href="?status=RECEIVED" class="stretched-link text-decoration-none small">Ver recebidos &rarr;</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm card-stat border-danger h-100">
                <div class="card-body">
                    <div class="text-muted small">Vencidos</div>
                    <div class="fs-4 fw-bold text-danger"><?= $sumOverdue['count'] ?></div>
                    <a href="?status=OVERDUE" class="stretched-link text-decoration-none small">Ver vencidos &rarr;</a>
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
            <label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="PENDING"  <?= $statusFiltro === 'PENDING'  ? 'selected' : '' ?>>Pendente</option>
                <option value="RECEIVED" <?= $statusFiltro === 'RECEIVED' ? 'selected' : '' ?>>Recebido</option>
                <option value="CONFIRMED"<?= $statusFiltro === 'CONFIRMED'? 'selected' : '' ?>>Confirmado</option>
                <option value="OVERDUE"  <?= $statusFiltro === 'OVERDUE'  ? 'selected' : '' ?>>Vencido</option>
                <option value="REFUNDED" <?= $statusFiltro === 'REFUNDED' ? 'selected' : '' ?>>Estornado</option>
                <option value="CANCELLED"<?= $statusFiltro === 'CANCELLED'? 'selected' : '' ?>>Cancelado</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Tipo</label>
            <select name="billingType" class="form-select form-select-sm">
                <option value="">Todos</option>
                <option value="BOLETO"      <?= $billingFiltro === 'BOLETO'      ? 'selected' : '' ?>>Boleto</option>
                <option value="PIX"         <?= $billingFiltro === 'PIX'         ? 'selected' : '' ?>>Pix</option>
                <option value="CREDIT_CARD" <?= $billingFiltro === 'CREDIT_CARD' ? 'selected' : '' ?>>Cartão</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">Vencimento de</label>
            <input type="date" name="dueFrom" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dueFrom) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small mb-1">até</label>
            <input type="date" name="dueTo" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($dueTo) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
            <a href="?" class="btn btn-outline-secondary btn-sm">Limpar</a>
        </div>
        <div class="col-auto ms-auto">
            <span class="form-text"><?= $totalCount ?> cobrança(s)</span>
        </div>
    </form>

    <!-- Tabela -->
    <?php if ($payments): ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Vencimento</th>
                        <th>Pagamento</th>
                        <th class="text-end">Valor</th>
                        <th class="text-end">Líquido</th>
                        <th>Status</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <?php
                        $status  = $p['status']      ?? '';
                        $billing = $p['billingType'] ?? '';
                        $id      = $p['id']          ?? '';
                    ?>
                    <tr>
                        <td class="id-col" title="<?= htmlspecialchars($id) ?>">
                            <?= htmlspecialchars(substr($id, 0, 12)) ?>…
                        </td>
                        <td><?= htmlspecialchars($p['customerName'] ?? $p['customer'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($p['description'] ?? '—') ?></td>
                        <td><?= billingBadge($billing) ?></td>
                        <td><?= fmtDate($p['dueDate']     ?? null) ?></td>
                        <td><?= fmtDate($p['paymentDate'] ?? null) ?></td>
                        <td class="text-end"><?= fmtMoney((float)($p['value']    ?? 0)) ?></td>
                        <td class="text-end text-success"><?= fmtMoney((float)($p['netValue'] ?? 0)) ?></td>
                        <td><?= statusBadge($status) ?></td>
                        <td>
                            <?php
                            $link = $p['invoiceUrl'] ?? $p['bankSlipUrl'] ?? null;
                            if ($link && isReceivable($status)):
                            ?>
                                <a href="<?= htmlspecialchars($link) ?>" target="_blank"
                                   class="btn btn-outline-primary btn-sm link-boleto">
                                    <?= $billing === 'PIX' ? 'QR Code' : 'Boleto' ?>
                                </a>
                            <?php endif; ?>
                        </td>
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
        <div class="text-muted">Nenhuma cobrança encontrada para os filtros selecionados.</div>
    <?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
