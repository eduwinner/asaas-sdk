<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/_bootstrap.php';

use AsaasSDK\WebhookHandler;

$handler = new WebhookHandler(
    authToken: asaas_config('webhook_token') ?: null
);

$handler->handle(function (array $evento) {
    $tipo     = $evento['event']   ?? 'DESCONHECIDO';
    $pagamento = $evento['payment'] ?? [];

    match ($tipo) {
        'PAYMENT_CREATED'           => onPagamentoCriado($pagamento),
        'PAYMENT_RECEIVED'          => onPagamentoRecebido($pagamento),
        'PAYMENT_CONFIRMED'         => onPagamentoConfirmado($pagamento),
        'PAYMENT_OVERDUE'           => onPagamentoVencido($pagamento),
        'PAYMENT_DELETED'           => onPagamentoCancelado($pagamento),
        'PAYMENT_REFUNDED'          => onPagamentoEstornado($pagamento),
        'PAYMENT_AWAITING_CHARGEBACK' => onChargebackAguardando($pagamento),
        default                     => logEvento("Evento não tratado: $tipo", $evento),
    };
});

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function onPagamentoCriado(array $p): void
{
    logEvento('Pagamento criado', $p);
    // TODO: registrar cobrança no banco, notificar cliente, etc.
}

function onPagamentoRecebido(array $p): void
{
    logEvento('Pagamento recebido', $p);
    // TODO: liberar acesso, atualizar status no banco, etc.
}

function onPagamentoConfirmado(array $p): void
{
    logEvento('Pagamento confirmado', $p);
    // TODO: confirmar pedido
}

function onPagamentoVencido(array $p): void
{
    logEvento('Pagamento vencido', $p);
    // TODO: notificar cliente, suspender acesso, etc.
}

function onPagamentoCancelado(array $p): void
{
    logEvento('Pagamento cancelado', $p);
}

function onPagamentoEstornado(array $p): void
{
    logEvento('Pagamento estornado', $p);
    // TODO: revogar acesso, processar devolução
}

function onChargebackAguardando(array $p): void
{
    logEvento('Chargeback aguardando', $p);
    // TODO: acionar equipe de disputas
}

// ---------------------------------------------------------------------------
// Log helper
// ---------------------------------------------------------------------------

function logEvento(string $msg, array $data): void
{
    $linha = date('Y-m-d H:i:s') . " | $msg | id: " . ($data['id'] ?? '—') . PHP_EOL;
    file_put_contents(__DIR__ . '/asaas_webhook.log', $linha, FILE_APPEND);
}
