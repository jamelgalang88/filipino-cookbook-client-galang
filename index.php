<?php

$configPath = __DIR__ . '/config.php';

if (!file_exists($configPath)) {
    $configPath = __DIR__ . '/config.example.php';
}

$config = require $configPath;
$apiBaseUrl = rtrim($config['api_base_url'], '/');
$apiToken = $config['api_token'];
$apiDeveloper = $config['api_developer'];

// Fixed reference list of origins. The API does not expose a GET /api/origins
// endpoint, so this list is kept in sync manually with the `origins` table.
$originsReference = [
    1 => 'Bacolod',
    2 => 'Bicol Region',
    3 => 'Ilocos Region',
    4 => 'Philippines',
];

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Picks the count value out of a category-counts row regardless of which
// field name the API happens to use for it.
function pickCount($item)
{
    foreach (['food_count', 'foods_count', 'count', 'total_foods', 'total'] as $key) {
        if (isset($item[$key])) {
            return $item[$key];
        }
    }
    return '—';
}

function requestApi($baseUrl, $token, $endpoint, $method = 'GET', $payload = null)
{
    $url = $baseUrl . $endpoint;
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
    ];
    $bodyContent = null;

    if ($payload !== null) {
        $bodyContent = json_encode($payload);
        $headers[] = 'Content-Type: application/json';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 10,
        ]);

        if ($bodyContent !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyContent);
        }

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
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $bodyContent,
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

// Returns a short, human-readable heading for a given HTTP status code so
// the person using the client understands what actually went wrong instead
// of seeing one generic "Request Error" label for every failure type.
function errorHeading($status)
{
    if ($status === 400) {
        return 'Invalid Request';
    }
    if ($status === 401) {
        return 'Unauthorized';
    }
    if ($status === 404) {
        return 'Not Found';
    }
    if ($status === 'Validation') {
        return 'Please Fix the Form';
    }
    if (is_int($status) && $status >= 500) {
        return 'Server Error';
    }
    return 'Request Error';
}

$view = $_GET['view'] ?? 'foods';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $view = $_POST['view'] ?? $view;
}
$search = trim($_GET['search'] ?? '');
$foodId = trim($_GET['food_id'] ?? '');
$categoryFilterId = trim($_GET['category_id'] ?? '');

$allowedViews = ['foods', 'search', 'details', 'categories', 'ingredients', 'add-food', 'random-food', 'by-category', 'category-counts'];
if (!in_array($view, $allowedViews, true)) {
    $view = 'foods';
}

$endpoint = '/api/foods';
$pageTitle = 'All Foods';
$tokenToUse = $apiToken;
$method = 'GET';
$payload = null;
$foodName = trim($_POST['food_name'] ?? '');
$categoryId = trim($_POST['category_id'] ?? '');
$originId = trim($_POST['origin_id'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$ingredientIds = trim($_POST['ingredient_ids'] ?? '');

// Categories used to populate the Add Food dropdown, and to validate that a
// submitted category_id actually exists before it ever reaches the API.
$categoryOptions = [];
$validationErrors = [];

if ($view === 'search') {
    $pageTitle = 'Search Foods';
    $endpoint = $search !== '' ? '/api/foods/search/' . rawurlencode($search) : '/api/foods';
} elseif ($view === 'details') {
    $pageTitle = 'Food Details';
    $endpoint = $foodId !== '' ? '/api/foods/' . rawurlencode($foodId) : '/api/foods';
} elseif ($view === 'random-food') {
    $pageTitle = 'Random Food';
    $endpoint = '/api/foods/random';
} elseif ($view === 'by-category') {
    $pageTitle = 'Foods by Category';
    $endpoint = $categoryFilterId !== ''
        ? '/api/categories/' . rawurlencode($categoryFilterId) . '/foods'
        : '/api/foods';
} elseif ($view === 'category-counts') {
    $pageTitle = 'Category Food Counts';
    $endpoint = '/api/categories/counts';
} elseif ($view === 'categories') {
    $pageTitle = 'Categories';
    $endpoint = '/api/categories';
} elseif ($view === 'ingredients') {
    $pageTitle = 'Ingredients';
    $endpoint = '/api/ingredients';
} elseif ($view === 'add-food') {
    $pageTitle = 'Add Food';
    $endpoint = '/api/foods';

    // Always fetch the live category list so the dropdown reflects the
    // actual database, whether this is the initial form view or a
    // resubmission after a validation error.
    $categoriesResult = requestApi($apiBaseUrl, $apiToken, '/api/categories');
    if ($categoriesResult['ok'] && is_array($categoriesResult['data'])) {
        $categoryOptions = $categoriesResult['data'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $validCategoryIds = array_map(
            static fn ($category) => (int) $category['category_id'],
            $categoryOptions
        );
        $validOriginIds = array_keys($originsReference);

        $parsedIngredientIds = array_values(array_filter(array_map(
            'intval',
            preg_split('/\s*,\s*/', $ingredientIds, -1, PREG_SPLIT_NO_EMPTY)
        )));

        if ($foodName === '') {
            $validationErrors[] = 'Food name is required.';
        }
        if ($categoryId === '' || !in_array((int) $categoryId, $validCategoryIds, true)) {
            $validationErrors[] = 'Please choose a valid category.';
        }
        if ($originId === '' || !in_array((int) $originId, $validOriginIds, true)) {
            $validationErrors[] = 'Please choose a valid origin.';
        }
        if ($instructions === '') {
            $validationErrors[] = 'Instructions are required.';
        }
        if (empty($parsedIngredientIds)) {
            $validationErrors[] = 'Enter at least one valid ingredient ID (e.g. 1, 2, 3).';
        }

        if (empty($validationErrors)) {
            $method = 'POST';
            $payload = [
                'food_name' => $foodName,
                'category_id' => (int) $categoryId,
                'origin_id' => (int) $originId,
                'instructions' => $instructions,
                'ingredient_ids' => $parsedIngredientIds,
            ];
        }
    }
}

if ($view === 'add-food' && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($validationErrors)) {
    $result = [
        'ok' => false,
        'status' => 'Validation',
        'data' => null,
        'message' => implode(' ', $validationErrors),
    ];
} elseif ($view === 'add-food' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $result = ['ok' => true, 'status' => 'Ready', 'data' => null, 'message' => ''];
} else {
    $result = requestApi($apiBaseUrl, $tokenToUse, $endpoint, $method, $payload);
}
$items = $result['data'];

if (($view === 'details' || $view === 'random-food') && $result['ok'] && isset($items['food_id'])) {
    $items = [$items];
}

// Search and Details are sub-views of the Foods list, so the Foods nav
// button stays highlighted while browsing either of them.
$navActive = in_array($view, ['foods', 'search', 'details', 'by-category'], true) ? 'foods' : $view;

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
            --shadow-sm: 0 1px 2px rgba(32, 35, 29, 0.06), 0 1px 1px rgba(32, 35, 29, 0.04);
            --shadow-md: 0 12px 24px -8px rgba(32, 35, 29, 0.18), 0 4px 8px -4px rgba(32, 35, 29, 0.1);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background:
                radial-gradient(1200px 480px at 10% -10%, rgba(15, 118, 110, 0.05), transparent 60%),
                var(--bg);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, Helvetica, sans-serif;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }

        *:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 2px;
        }

        header {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
            padding: 28px 24px 24px;
            position: relative;
            overflow: hidden;
        }

        header::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent-dark) 55%, transparent);
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
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        header p {
            color: var(--muted);
            max-width: 640px;
        }

        h2 {
            font-size: 19px;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h2::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            flex: none;
        }

        nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        nav a,
        button {
            border: 1px solid var(--accent);
            background: var(--accent);
            color: #ffffff;
            padding: 10px 16px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }

        nav a:hover,
        button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        nav a:active,
        button:active {
            transform: translateY(0);
            box-shadow: var(--shadow-sm);
        }

        nav a.secondary {
            background: transparent;
            color: var(--accent-dark);
            border: 1.5px solid var(--line);
            box-shadow: none;
        }

        nav a.secondary:hover {
            border-color: var(--accent);
            background: rgba(15, 118, 110, 0.06);
        }

        .toolbar,
        .notice,
        .endpoint-list,
        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
        }

        .toolbar,
        .notice,
        .endpoint-list {
            padding: 18px 20px;
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

        .add-form {
            display: grid;
            gap: 14px;
        }

        .add-form label {
            display: grid;
            gap: 6px;
            font-weight: 700;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            font: inherit;
            background: #ffffff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
            outline: none;
        }

        input:hover,
        textarea:hover,
        select:hover {
            border-color: var(--accent-dark);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
        }

        .card {
            padding: 20px;
            border-top: 3px solid var(--accent);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-md);
        }

        .card h3 {
            margin: 0 0 8px;
            font-size: 19px;
            letter-spacing: -0.01em;
        }

        .meta {
            color: var(--muted);
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 14px;
        }

        .chip {
            background: #e7f5f1;
            color: #0f4f49;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 12.5px;
            font-weight: 600;
            transition: background-color 0.15s ease, transform 0.15s ease;
        }

        .chip:hover {
            background: #d7efe9;
            transform: translateY(-1px);
        }

        .status {
            display: inline-flex;
            align-items: center;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 13px;
        }

        .status.ok {
            color: var(--accent-dark);
            background: rgba(15, 118, 110, 0.12);
        }

        .status.error {
            color: var(--error);
            background: rgba(185, 28, 28, 0.1);
        }

        .field-hint {
            font-weight: 400;
            font-size: 13px;
            color: var(--muted);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        th,
        td {
            padding: 13px 14px;
            border-bottom: 1px solid var(--line);
            text-align: left;
        }

        th {
            background: #eef3ea;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--muted);
        }

        tbody tr {
            transition: background-color 0.12s ease;
        }

        tbody tr:nth-child(even) {
            background: rgba(15, 118, 110, 0.025);
        }

        tbody tr:hover {
            background: rgba(15, 118, 110, 0.08);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        footer {
            width: min(1120px, calc(100% - 32px));
            margin: 18px auto 32px;
            color: var(--muted);
            font-size: 14px;
            padding-top: 16px;
            border-top: 1px solid var(--line);
        }

        code {
            background: #eef3ea;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.92em;
        }
    </style>
</head>
<body>
    <header>
        <h1>Filipino Cookbook Client</h1>
        <p>Driver application that consumes the secured Filipino Cookbook API and presents the JSON data as readable interface elements.</p>
        <nav>
            <a class="<?= $navActive === 'foods' ? 'active' : 'secondary' ?>" href="?view=foods">Foods</a>
            <a class="<?= $navActive === 'categories' ? 'active' : 'secondary' ?>" href="?view=categories">Categories</a>
            <a class="<?= $navActive === 'ingredients' ? 'active' : 'secondary' ?>" href="?view=ingredients">Ingredients</a>
            <a class="<?= $navActive === 'add-food' ? 'active' : 'secondary' ?>" href="?view=add-food">Add Food</a>
            <a class="<?= $navActive === 'random-food' ? 'active' : 'secondary' ?>" href="?view=random-food">Random Food</a>
            <a class="<?= $navActive === 'category-counts' ? 'active' : 'secondary' ?>" href="?view=category-counts">Category Counts</a>
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

                <form method="get">
                    <input type="hidden" name="view" value="by-category">
                    <input name="category_id" value="<?= e($categoryFilterId) ?>" placeholder="Category ID">
                    <button type="submit">View Foods</button>
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
                <?php if ($method === 'POST'): ?>
                    <br>
                    Method used: <code>POST</code>
                <?php endif; ?>
            </p>
        </section>

        <?php if ($view === 'add-food'): ?>
            <section class="notice">
                <h2>New Food Entry</h2>
                <form class="add-form" method="post">
                    <input type="hidden" name="view" value="add-food">

                    <label>
                        Food Name
                        <input name="food_name" value="<?= e($foodName) ?>" maxlength="100" required>
                    </label>

                    <div class="form-row">
                        <label>
                            Category
                            <select name="category_id" required>
                                <option value="">Select a category</option>
                                <?php foreach ($categoryOptions as $category): ?>
                                    <option
                                        value="<?= e($category['category_id']) ?>"
                                        <?= (string) $categoryId === (string) $category['category_id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label>
                            Origin
                            <select name="origin_id" required>
                                <option value="">Select an origin</option>
                                <?php foreach ($originsReference as $originIdOption => $originName): ?>
                                    <option
                                        value="<?= e($originIdOption) ?>"
                                        <?= (string) $originId === (string) $originIdOption ? 'selected' : '' ?>
                                    >
                                        <?= e($originName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <label>
                        Instructions
                        <textarea name="instructions" required><?= e($instructions) ?></textarea>
                    </label>

                    <label>
                        Ingredient IDs
                        <span class="field-hint">Comma-separated, from the Ingredients list &mdash; example: 1, 2, 3</span>
                        <input name="ingredient_ids" value="<?= e($ingredientIds) ?>" placeholder="Example: 1, 2, 3" required>
                    </label>

                    <button type="submit">Submit Food</button>
                </form>
            </section>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <section class="notice">
                    <h2><?= $result['ok'] ? 'Food Submitted' : errorHeading($result['status']) ?></h2>
                    <p>
                        <?php
                        $message = $result['message'];
                        if (is_array($result['data']) && isset($result['data']['message'])) {
                            $message = $result['data']['message'];
                        }
                        echo e($message ?: 'The API request was completed.');
                        ?>
                    </p>
                </section>
            <?php endif; ?>
        <?php elseif (!$result['ok']): ?>
            <section class="notice">
                <h2><?= e(errorHeading($result['status'])) ?></h2>
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
        <?php elseif ($view === 'category-counts'): ?>
            <table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Number of Foods</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['category_name'] ?? $item['name'] ?? '') ?></td>
                            <td><?= e(pickCount($item)) ?></td>
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
            <p><code>GET /api/foods/random</code> displays a random food.</p>
            <p><code>GET /api/categories</code> displays food categories.</p>
            <p><code>GET /api/categories/{id}/foods</code> displays foods belonging to one category.</p>
            <p><code>GET /api/categories/counts</code> displays the number of foods in each category.</p>
            <p><code>GET /api/ingredients</code> displays ingredients.</p>
            <p><code>POST /api/foods</code> adds a new food record.</p>
        </section>
    </main>

    <footer>
        API developed by: <?= e($apiDeveloper) ?>
    </footer>
</body>
</html>