<?php

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config.example.php';
}

$config = require $configPath;
$apiBaseUrl = rtrim($config['api_base_url'], '/');
$apiToken = $config['api_token'];
$apiDeveloper = $config['api_developer'];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function requestApi($baseUrl, $token, $endpoint)
{
    $url = $baseUrl . $endpoint;
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'message' => $error ?: 'Unable to connect to the API.',
            ];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        $statusCode = 0;

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $statusCode = (int) $matches[1];
        }

        if ($body === false) {
            return [
                'ok' => false,
                'status' => $statusCode,
                'data' => null,
                'message' => 'Unable to connect to the API.',
            ];
        }
    }

    $data = json_decode($body, true);

    return [
        'ok' => $statusCode >= 200 && $statusCode < 300 && json_last_error() === JSON_ERROR_NONE,
        'status' => $statusCode,
        'data' => $data,
        'message' => json_last_error() === JSON_ERROR_NONE ? '' : 'The API returned an invalid JSON response.',
    ];
}

$view = $_GET['view'] ?? 'foods';
$search = trim($_GET['search'] ?? '');
$foodId = trim($_GET['food_id'] ?? '');

$allowedViews = ['foods', 'search', 'details', 'categories', 'ingredients', 'invalid-test'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'foods';
}

$endpoint = '/api/foods';
$pageTitle = 'All Foods';
$tokenToUse = $apiToken;

if ($view === 'search') {
    $pageTitle = 'Search Foods';
    $endpoint = $search !== '' ? '/api/foods/search/' . rawurlencode($search) : '/api/foods';
} elseif ($view === 'details') {
    $pageTitle = 'Food Details';
    $endpoint = $foodId !== '' ? '/api/foods/' . rawurlencode($foodId) : '/api/foods';
} elseif ($view === 'categories') {
    $pageTitle = 'Categories';
    $endpoint = '/api/categories';
} elseif ($view === 'ingredients') {
    $pageTitle = 'Ingredients';
    $endpoint = '/api/ingredients';
} elseif ($view === 'invalid-test') {
    $pageTitle = 'Invalid Token Test';
    $endpoint = '/api/foods';
    $tokenToUse = 'invalid-token-for-testing';
}

$result = requestApi($apiBaseUrl, $tokenToUse, $endpoint);
$items = $result['data'];

if ($view === 'details' && $result['ok'] && isset($items['food_id'])) {
    $items = [$items];
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filipino Cookbook Client</title>
    <style>
        :root {
            --bg: #f6f7f3;
            --panel: #ffffff;
            --ink: #20231d;
            --muted: #697063;
            --line: #dfe4d8;
            --accent: #0f766e;
            --accent-dark: #115e59;
            --warn: #b45309;
            --error: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.5;
        }

        header {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
            padding: 24px;
        }

        main {
            width: min(1120px, calc(100% - 32px));
            margin: 24px auto;
        }

        h1,
        h2,
        p {
            margin-top: 0;
        }

        h1 {
            margin-bottom: 6px;
            font-size: 30px;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }

        nav a,
        button {
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #ffffff;
            padding: 10px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        nav a.secondary {
            background: transparent;
            color: var(--accent-dark);
        }

        .toolbar,
        .notice,
        .endpoint-list,
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
        }

        .toolbar,
        .notice,
        .endpoint-list {
            padding: 16px;
            margin-bottom: 18px;
        }

        .forms {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 14px;
        }

        form {
            display: flex;
            gap: 8px;
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .card {
            padding: 18px;
        }

        .card h3 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .meta {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 12px;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .chip {
            background: #e7f5f1;
            color: #0f4f49;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 13px;
        }

        .status {
            font-weight: 700;
        }

        .status.ok {
            color: var(--accent-dark);
        }

        .status.error {
            color: var(--error);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 8px;
            overflow: hidden;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        th {
            background: #eef3ea;
        }

        footer {
            width: min(1120px, calc(100% - 32px));
            margin: 18px auto 32px;
            color: var(--muted);
            font-size: 14px;
        }

        code {
            background: #eef3ea;
            padding: 2px 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <header>
        <h1>Filipino Cookbook Client</h1>
        <p>Driver application that consumes the secured Filipino Cookbook API and presents the JSON data as readable interface elements.</p>
        <nav>
            <a href="?view=foods">Foods</a>
            <a class="secondary" href="?view=categories">Categories</a>
            <a class="secondary" href="?view=ingredients">Ingredients</a>
            <a class="secondary" href="?view=invalid-test">Invalid Token Test</a>
        </nav>
    </header>

    <main>
        <section class="toolbar">
            <div class="forms">
                <form method="get">
                    <input type="hidden" name="view" value="search">
                    <input name="search" value="<?= e($search) ?>" placeholder="Search food name">
                    <button type="submit">Search</button>
                </form>

                <form method="get">
                    <input type="hidden" name="view" value="details">
                    <input name="food_id" value="<?= e($foodId) ?>" placeholder="Food ID">
                    <button type="submit">Find</button>
                </form>
            </div>
        </section>

        <section class="notice">
            <h2><?= e($pageTitle) ?></h2>
            <p>
                Endpoint used: <code><?= e($endpoint) ?></code>
                <br>
                Response status:
                <span class="status <?= $result['ok'] ? 'ok' : 'error' ?>">
                    <?= e($result['status'] ?: 'No response') ?>
                </span>
            </p>
        </section>

        <?php if (!$result['ok']): ?>
            <section class="notice">
                <h2>Request Error</h2>
                <p>
                    <?php
                    $message = $result['message'];
                    if (is_array($result['data']) && isset($result['data']['message'])) {
                        $message = $result['data']['message'];
                    }
                    echo e($message ?: 'The API request was not successful.');
                    ?>
                </p>
            </section>
        <?php elseif ($view === 'categories' || $view === 'ingredients'): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['category_id'] ?? $item['ingredient_id'] ?? '') ?></td>
                            <td><?= e($item['category_name'] ?? $item['ingredient_name'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($items as $food): ?>
                    <article class="card">
                        <h3><?= e($food['food_name'] ?? 'Unnamed Food') ?></h3>
                        <div class="meta">
                            ID <?= e($food['food_id'] ?? '') ?> |
                            <?= e($food['category_name'] ?? 'No category') ?> |
                            <?= e($food['origin_name'] ?? 'No origin') ?>
                        </div>
                        <p><?= e($food['instructions'] ?? 'No cooking instructions provided.') ?></p>

                        <?php if (!empty($food['ingredients']) && is_array($food['ingredients'])): ?>
                            <div class="chips">
                                <?php foreach ($food['ingredients'] as $ingredient): ?>
                                    <span class="chip"><?= e($ingredient) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="endpoint-list">
            <h2>API Endpoints Used</h2>
            <p><code>GET /api/foods</code> displays all foods.</p>
            <p><code>GET /api/foods/{id}</code> displays one food by ID.</p>
            <p><code>GET /api/foods/search/{name}</code> searches foods by name.</p>
            <p><code>GET /api/categories</code> displays food categories.</p>
            <p><code>GET /api/ingredients</code> displays ingredients.</p>
        </section>
    </main>

    <footer>
        API developed by: <?= e($apiDeveloper) ?>
    </footer>
</body>
</html>
