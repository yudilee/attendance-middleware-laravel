<?php

namespace App\Services;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OdooService
{
    protected string $url;
    protected string $db;
    protected string $username;
    protected string $password;
    protected ?int $uid = null;
    protected bool $enabled = false;

    public function __construct()
    {
        // Check AppConfig overrides first, then config/odoo.php
        $this->enabled = AppConfig::where('key', 'odoo_sync_enabled')->value('value') === '1'
            ?: config('odoo.enabled', false);

        $this->url = rtrim(AppConfig::where('key', 'odoo_url')->value('value') ?: config('odoo.url'), '/');
        $this->db = AppConfig::where('key', 'odoo_db')->value('value') ?: config('odoo.db');
        $this->username = AppConfig::where('key', 'odoo_username')->value('value') ?: config('odoo.username');
        $this->password = AppConfig::where('key', 'odoo_password')->value('value') ?: config('odoo.password');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function authenticate(): int|false
    {
        if (empty($this->url) || empty($this->db) || empty($this->username) || empty($this->password)) {
            return false;
        }

        try {
            $response = Http::timeout(10)->post("{$this->url}/jsonrpc", [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'authenticate',
                    'args' => [$this->db, $this->username, $this->password, []],
                ],
                'id' => rand(1000, 9999),
            ]);

            if ($response->successful()) {
                $res = $response->json();
                if (isset($res['result']) && is_numeric($res['result']) && $res['result'] > 0) {
                    $this->uid = (int) $res['result'];
                    return $this->uid;
                }
            }
        } catch (\Exception $e) {
            Log::error("Odoo authentication exception: " . $e->getMessage());
        }

        return false;
    }

    public function testConnection(): array
    {
        if (empty($this->url) || empty($this->db) || empty($this->username) || empty($this->password)) {
            return [
                'success' => false,
                'message' => 'Odoo credentials (URL, DB, Username, Password) are not configured.',
            ];
        }

        try {
            // 1. Version check
            $verRes = Http::timeout(8)->post("{$this->url}/jsonrpc", [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'version',
                    'args' => [],
                ],
                'id' => rand(1000, 9999),
            ]);

            if (!$verRes->successful()) {
                return [
                    'success' => false,
                    'message' => "Cannot reach Odoo at {$this->url} (HTTP {$verRes->status()})",
                ];
            }

            $versionInfo = $verRes->json()['result'] ?? [];

            // 2. Auth test
            $uid = $this->authenticate();
            if (!$uid) {
                return [
                    'success' => false,
                    'message' => "Connected to Odoo server, but login failed for database '{$this->db}' and user '{$this->username}'.",
                    'server_version' => $versionInfo['server_version'] ?? 'Unknown',
                ];
            }

            return [
                'success' => true,
                'message' => "Successfully connected & authenticated with Odoo (UID: {$uid}).",
                'server_version' => $versionInfo['server_version'] ?? 'Unknown',
                'protocol_version' => $versionInfo['protocol_version'] ?? 1,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Connection failed: " . $e->getMessage(),
            ];
        }
    }

    public function executeKw(string $model, string $method, array $args = [], array $kwargs = []): mixed
    {
        if (!$this->uid) {
            $this->authenticate();
        }

        if (!$this->uid) {
            throw new \Exception("Cannot execute Odoo operation: Not authenticated.");
        }

        $response = Http::timeout(20)->post("{$this->url}/jsonrpc", [
            'jsonrpc' => '2.0',
            'method' => 'call',
            'params' => [
                'service' => 'object',
                'method' => 'execute_kw',
                'args' => [
                    $this->db,
                    $this->uid,
                    $this->password,
                    $model,
                    $method,
                    $args,
                    $kwargs,
                ],
            ],
            'id' => rand(1000, 9999),
        ]);

        if (!$response->successful()) {
            throw new \Exception("Odoo RPC error HTTP: " . $response->status());
        }

        $data = $response->json();
        if (isset($data['error'])) {
            $errMsg = $data['error']['data']['message'] ?? $data['error']['message'] ?? 'Unknown RPC error';
            throw new \Exception("Odoo RPC Error: " . $errMsg);
        }

        return $data['result'] ?? null;
    }

    public function searchRead(string $model, array $domain = [], array $fields = [], int $limit = 100, int $offset = 0): array
    {
        return $this->executeKw($model, 'search_read', [$domain], [
            'fields' => $fields,
            'limit' => $limit,
            'offset' => $offset,
        ]) ?? [];
    }

    public function create(string $model, array $values): int
    {
        return (int) $this->executeKw($model, 'create', [$values]);
    }

    public function write(string $model, array $ids, array $values): bool
    {
        return (bool) $this->executeKw($model, 'write', [$ids, $values]);
    }
}
