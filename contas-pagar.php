<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

use AsaasSDK\AsaasClient;
use AsaasSDK\Exceptions\HttpException;
use GuzzleHttp\Client as GuzzleClient;
use Nyholm\Psr7\Factory\Psr17Factory;

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$apiKey = asaas_config('api_key');
if (!$apiKey) {
    http_response_code(500);
    exit('API key não configurada. Crie config.php no servidor (veja config.example.php).');
}

$sandbox = (bool) asaas_config('sandbox', false);
$factory = new Psr17Factory();

$asaas = new AsaasClient(
    apiKey: $apiKey,
    httpClient: new GuzzleClient(['http_errors' => false]),
    requestFactory: $factory,
    streamFactory: $factory,
    sandbox: $sandbox,
);

// ---------------------------------------------------------------------------
// Ações POST
// ---------------------------------------------------------------------------

$flash = ['type' => '', 'msg' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        match ($action) {
            'create' => handleCreate($asaas, $_POST),
            'pay'    => handlePay($asaas, $_POST['id']),
            'cancel' => handleCancel($asaas, $_POST['id']),
            default  => null,
        };
        $flash = ['type' => 'success', 'msg' => msgSuccess($action)];
    } catch (HttpException $e) {
        $flash = ['type' => 'danger', 'msg' => "Erro {$e->statusCode}: {$e->getMessage()}"];
    } catch (\Throwable $e) {
        $flash = ['type' => 'danger', 'msg' => $e->getMessage()];
    }
}

function handleCreate(AsaasClient $c, array $p): void
{
    $data = [
        'identificationField' => trim($p['identificationField']),
        'dueDate'             => $p['dueDate'],
        'value'               => (float) str_replace(',', '.', $p['value']),
    ];
    if (!empty($p['description'])) {
        $data['description'] = trim($p['description']);
    }
    $c->createBill($data);
}

function handlePay(AsaasClient $c, string $id): void
{
    $c->payBill($id);
}

function handleCancel(AsaasClient $c, string $id): void
{
    $c->cancelBill($id);
}

function msgSuccess(string $action): string
{
    return match ($action) {
        'create' => 'Conta a pagar cadastrada com sucesso.',
        'pay'    => 'Pagamento realizado com sucesso.',
        'cancel' => 'Conta cancelada com sucesso.',
        default  => 'Operação concluída.',
    };
}

// ---------------------------------------------------------------------------
// Listagem
// ---------------------------------------------------------------------------

$statusFiltro = $_GET['status'] ?? '';
$offset       = max(0, (int) ($_GET['offset'] ?? 0));
$limit        = 15;

$filters = ['limit' => $limit, 'offset' => $offset];
if ($statusFiltro !== '') {
    $filters['status'] = $statusFiltro;
}

$bills      = [];
$totalCount = 0;
$listError  = '';

try {
    $result     = $asaas->listBills($filters);
    $bills      = $result['data'] ?? [];
    $totalCount = $result['totalCount'] ?? 0;
} catch (\Throwable $e) {
    $listError = $e->getMessage();
}

// ---------------------------------------------------------------------------
// Helpers de exibição
// ---------------------------------------------------------------------------

function statusBadge(string $status): string
{
    $map = [
        'PENDING'   => ['warning', 'Pendente'],
        'SCHEDULED' => ['info',    'Agendado'],
        'PAID'      => ['success', 'Pago'],
        'CANCELLED' => ['secondary','Cancelado'],
        'FAILED'    => ['danger',  'Falhou'],
    ];
    [$color, $label] = $map[$status] ?? ['light', $status];
    return "<span class=\"badge bg-{$color} text-dark\">{$label}</span>";
}

function fmtDate(string $date): string
{
    if (!$date) return '—';
    $d = DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));
    return $d ? $d->format('d/m/Y') : $date;
}

function fmtMoney(float $value): string
{
    return 'R$&nbsp;' . number_format($value, 2, ',', '.');
}

function canPay(string $status): bool
{
    return in_array($status, ['PENDING', 'SCHEDULED'], true);
}

function canCancel(string $status): bool
{
    return in_array($status, ['PENDING', 'SCHEDULED'], true);
}

$prevOffset = max(0, $offset - $limit);
$nextOffset = $offset + $limit;
$hasPrev    = $offset > 0;
$hasNext    = ($offset + $limit) < $totalCount;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contas a Pagar — Asaas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .table th { white-space: nowrap; }
        .id-col { font-family: monospace; font-size: .8rem; color: #6c757d; }
        .line-col { font-family: monospace; font-size: .75rem; word-break: break-all; max-width: 260px; }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Contas a Pagar</h4>
        <span class="badge bg-<?= $sandbox ? 'warning text-dark' : 'success' ?>">
            <?= $sandbox ? 'Sandbox' : 'Produção' ?>
        </span>
    </div>

    <?php if ($flash['msg']): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($listError): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($listError) ?></div>
    <?php endif; ?>

    <!-- Filtro por status -->
    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos os status</option>
                <option value="PENDING"   <?= $statusFiltro === 'PENDING'   ? 'selected' : '' ?>>Pendente</option>
                <option value="SCHEDULED" <?= $statusFiltro === 'SCHEDULED' ? 'selected' : '' ?>>Agendado</option>
                <option value="PAID"      <?= $statusFiltro === 'PAID'      ? 'selected' : '' ?>>Pago</option>
                <option value="CANCELLED" <?= $statusFiltro === 'CANCELLED' ? 'selected' : '' ?>>Cancelado</option>
                <option value="FAILED"    <?= $statusFiltro === 'FAILED'    ? 'selected' : '' ?>>Falhou</option>
            </select>
        </div>
        <div class="col-auto">
            <span class="form-text"><?= $totalCount ?> conta(s) encontrada(s)</span>
        </div>
    </form>

    <!-- Tabela -->
    <?php if ($bills): ?>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Descrição</th>
                        <th>Linha digitável</th>
                        <th>Vencimento</th>
                        <th class="text-end">Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bills as $bill): ?>
                    <?php $id = $bill['id']; $status = $bill['status'] ?? ''; ?>
                    <tr>
                        <td class="id-col"><?= htmlspecialchars($id) ?></td>
                        <td><?= htmlspecialchars($bill['description'] ?? '—') ?></td>
                        <td class="line-col"><?= htmlspecialchars($bill['identificationField'] ?? '—') ?></td>
                        <td><?= fmtDate($bill['dueDate'] ?? '') ?></td>
                        <td class="text-end"><?= fmtMoney((float)($bill['value'] ?? 0)) ?></td>
                        <td><?= statusBadge($status) ?></td>
                        <td>
                            <?php if (canPay($status)): ?>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Confirma o pagamento desta conta?')">
                                <input type="hidden" name="action" value="pay">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                <button class="btn btn-success btn-sm">Pagar</button>
                            </form>
                            <?php endif; ?>
                            <?php if (canCancel($status)): ?>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Confirma o cancelamento?')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                                <button class="btn btn-outline-secondary btn-sm">Cancelar</button>
                            </form>
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
                <a class="page-link" href="?status=<?= urlencode($statusFiltro) ?>&offset=<?= $prevOffset ?>">
                    &laquo; Anterior
                </a>
            </li>
            <li class="page-item disabled">
                <span class="page-link text-muted">
                    <?= $offset + 1 ?>–<?= min($offset + $limit, $totalCount) ?> de <?= $totalCount ?>
                </span>
            </li>
            <li class="page-item <?= !$hasNext ? 'disabled' : '' ?>">
                <a class="page-link" href="?status=<?= urlencode($statusFiltro) ?>&offset=<?= $nextOffset ?>">
                    Próxima &raquo;
                </a>
            </li>
        </ul>
    </nav>

    <?php else: ?>
        <div class="text-muted mb-3">Nenhuma conta encontrada.</div>
    <?php endif; ?>

    <!-- Formulário nova conta -->
    <div class="card shadow-sm mt-4">
        <div class="card-header">Nova conta a pagar</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="create">

                <div class="col-12">
                    <label class="form-label fw-semibold">Linha digitável do boleto <span class="text-danger">*</span></label>
                    <input type="text" name="identificationField" class="form-control font-monospace"
                           placeholder="00000.00000 00000.000000 00000.000000 0 00000000000000"
                           required maxlength="54">
                    <div class="form-text">47 ou 48 dígitos, com ou sem pontos/espaços.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Vencimento <span class="text-danger">*</span></label>
                    <input type="date" name="dueDate" class="form-control"
                           value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Valor (R$) <span class="text-danger">*</span></label>
                    <input type="text" name="value" class="form-control"
                           placeholder="150,00" required pattern="[\d]+([,\.]\d{1,2})?">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Descrição</label>
                    <input type="text" name="description" class="form-control"
                           placeholder="Fornecedor XYZ — Fev/2026">
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Cadastrar conta</button>
                </div>
            </form>
        </div>
    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
