# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

PHP SDK for Asaas payment gateway (including White Label/BaaS). Namespace: `AsaasSDK\`. PHP 8.1+.

## Commands

```bash
# Install dependencies
composer install

# Autoload dump after adding classes
composer dump-autoload
```

No test suite configured yet.

## Architecture

**PSR-based, bring-your-own HTTP client.** The SDK never ships a concrete HTTP client — consumers must provide a `Psr\Http\Client\ClientInterface`, `RequestFactoryInterface`, and `StreamFactoryInterface`. `php-http/discovery` is available for auto-discovery.

### Key files

- `src/AsaasClient.php` — single entrypoint for all API calls. All requests route through private `call()`, which sets `access_token` header, encodes JSON body, and throws on HTTP 4xx/5xx.
- `src/WebhookHandler.php` — reads raw `php://input`, logs to `asaas_webhook.log`, invokes caller-supplied callback, responds 200.
- `src/Exceptions/AsaasException.php` — connection-level errors.
- `src/Exceptions/HttpException.php` — HTTP 4xx/5xx errors; exposes `$statusCode`.

### API environments

`AsaasClient` takes `$sandbox = false`. When true, base URL switches to `https://sandbox.asaas.com/api/v3`; production is `https://www.asaas.com/api/v3`.

### Multi-tenant usage

`withApiKey(string $apiKey): self` clones the client with a different key — intended for White Label sub-account operations without re-instantiating dependencies.

### Adding new endpoints

Add a public method to `AsaasClient` that delegates to `$this->call($method, $endpoint, $data)`. No routing layer, no resource classes — keep it flat.
