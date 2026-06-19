<?php
/**
 * Bootstrap compartilhado — carrega config do servidor.
 * Prioridade: config.php > $_SERVER > getenv()
 */

$_cfg = file_exists(__DIR__ . '/config.php')
    ? (require __DIR__ . '/config.php')
    : [];

function asaas_config(string $key, $default = '')
{
    global $_cfg;

    // 1. config.php local
    if (isset($_cfg[$key])) return $_cfg[$key];

    // Mapeia chaves amigáveis para env vars
    $envMap = [
        'api_key'       => 'ASAAS_API_KEY',
        'sandbox'       => 'ASAAS_SANDBOX',
        'webhook_token' => 'ASAAS_WEBHOOK_TOKEN',
    ];

    $envName = $envMap[$key] ?? strtoupper($key);

    // 2. $_SERVER (funciona com PHP-FPM + SetEnv no .htaccess)
    if (isset($_SERVER[$envName]) && $_SERVER[$envName] !== '') {
        return $_SERVER[$envName];
    }

    // 3. getenv() (funciona com mod_php e CLI)
    $val = getenv($envName);
    if ($val !== false && $val !== '') return $val;

    return $default;
}
