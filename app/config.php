<?php
declare(strict_types=1);

function env_value(string $key, ?string $default = null): ?string
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $file = dirname(__DIR__) . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $env[trim($name)] = trim(trim($value), "\"'");
            }
        }
    }

    return $_ENV[$key] ?? getenv($key) ?: ($env[$key] ?? $default);
}

function app_config(): array
{
    return [
        'db_dsn' => env_value('DB_DSN', ''),
        'db_user' => env_value('DB_USER', ''),
        'db_pass' => env_value('DB_PASS', ''),
        'asset_base' => rtrim((string) env_value('APP_ASSET_BASE', ''), '/'),
        'asp_web_config' => env_value('ASP_WEB_CONFIG', '../github_remote_check/Web.config'),
    ];
}

function asp_connection_config(): ?array
{
    $config = app_config();
    $path = (string) $config['asp_web_config'];
    if (!preg_match('/^[A-Za-z]:[\/\\\\]/', $path)) {
        $path = dirname(__DIR__) . '/' . $path;
    }
    $path = realpath($path);
    if (!$path || !is_file($path)) {
        return null;
    }

    $xml = @simplexml_load_file($path);
    if (!$xml || !isset($xml->connectionStrings)) {
        return null;
    }

    foreach ($xml->connectionStrings->add as $entry) {
        if ((string) $entry['name'] !== 'EbookCityAzureSql') {
            continue;
        }
        $raw = (string) $entry['connectionString'];
        $parts = [];
        foreach (explode(';', $raw) as $piece) {
            if (!str_contains($piece, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $piece, 2);
            $parts[strtolower(trim($key))] = trim($value);
        }

        $server = $parts['server'] ?? $parts['data source'] ?? '';
        $server = preg_replace('/^tcp:/i', '', $server);
        $database = $parts['initial catalog'] ?? $parts['database'] ?? '';
        $user = $parts['user id'] ?? $parts['uid'] ?? '';
        $pass = $parts['password'] ?? $parts['pwd'] ?? '';

        if (!$server || !$database || str_contains($server, 'YOUR_SERVER') || str_contains($database, 'YOUR_DATABASE')) {
            return null;
        }

        return [
            'dsn' => 'sqlsrv:Server=' . $server . ';Database=' . $database . ';Encrypt=yes;TrustServerCertificate=no;LoginTimeout=30',
            'user' => $user,
            'pass' => $pass,
        ];
    }

    return null;
}
