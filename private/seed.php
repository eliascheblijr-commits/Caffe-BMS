<?php

/**
 * One-time / idempotent seeder for local development and testing.
 *
 * Run with:
 *   docker compose exec app php private/seed.php
 * or, if you have PHP + DB access locally:
 *   php private/seed.php
 *
 * Deliberately standalone — doesn't require bootstrap.php (which is built
 * for the web request lifecycle: sessions, headers) or composer's
 * vendor/autoload (PHPMailer isn't needed here). Just PDO and a minimal
 * .env reader.
 *
 * Creates a demo franchise with TWO branches, one user per role, and a
 * starter menu on the first branch only — so the whole login -> order ->
 * kitchen -> payment loop can be tested immediately, and the admin's branch
 * switcher has something real to switch between (the second branch starts
 * deliberately empty). Safe to re-run — every insert checks for an
 * existing row first.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

$appRoot = dirname(__DIR__);
$envPath = $appRoot . '/.env';

$env = [];
if (is_readable($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

function env_val(array $env, string $key, string $default = ''): string
{
    return isset($env[$key]) && $env[$key] !== '' ? $env[$key] : $default;
}

$host = env_val($env, 'DB_HOST', '127.0.0.1');
$port = env_val($env, 'DB_PORT', '3306');
$name = env_val($env, 'DB_NAME', 'caffe');
$user = env_val($env, 'DB_USER', 'root');
$pass = env_val($env, 'DB_PASS', '');

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "Could not connect to the database: {$e->getMessage()}\n");
    exit(1);
}

echo "Connected to database '{$name}' on {$host}.\n\n";

// ---------------------------------------------------------------------------
// 1. Demo franchise
// ---------------------------------------------------------------------------
$franchiseName = 'Demo Franchise';
$stmt = $pdo->prepare('SELECT id FROM franchises WHERE name = :name');
$stmt->execute([':name' => $franchiseName]);
$franchiseId = $stmt->fetchColumn();

if ($franchiseId === false) {
    $insert = $pdo->prepare('INSERT INTO franchises (name) VALUES (:name)');
    $insert->execute([':name' => $franchiseName]);
    $franchiseId = (int) $pdo->lastInsertId();
    echo "Created franchise '{$franchiseName}' (id {$franchiseId}).\n";
} else {
    $franchiseId = (int) $franchiseId;
    echo "Franchise '{$franchiseName}' already exists (id {$franchiseId}) — reusing it.\n";
}

// ---------------------------------------------------------------------------
// 2. Two demo branches under it — the second exists mainly to give the
//    admin branch switcher something real to switch between.
// ---------------------------------------------------------------------------
$branches = [
    ['name' => 'Demo Cafe - Downtown', 'slug' => 'demo-cafe-downtown', 'address' => '123 Main Street'],
    ['name' => 'Demo Cafe - Uptown', 'slug' => 'demo-cafe-uptown', 'address' => '456 High Street'],
];

$branchIds = [];
foreach ($branches as $branch) {
    $stmt = $pdo->prepare('SELECT id FROM cafes WHERE slug = :slug');
    $stmt->execute([':slug' => $branch['slug']]);
    $cafeId = $stmt->fetchColumn();

    if ($cafeId === false) {
        $insert = $pdo->prepare(
            "INSERT INTO cafes (franchise_id, name, slug, address, contact_phone, status)
             VALUES (:franchise_id, :name, :slug, :address, :phone, 'active')"
        );
        $insert->execute([
            ':franchise_id' => $franchiseId,
            ':name' => $branch['name'],
            ':slug' => $branch['slug'],
            ':address' => $branch['address'],
            ':phone' => '555-0100',
        ]);
        $cafeId = (int) $pdo->lastInsertId();
        echo "Created cafe '{$branch['name']}' (id {$cafeId}, slug '{$branch['slug']}').\n";
    } else {
        $cafeId = (int) $cafeId;
        echo "Cafe '{$branch['name']}' already exists (id {$cafeId}) — reusing it.\n";
    }

    $branchIds[$branch['slug']] = $cafeId;
}

$primaryCafeId = $branchIds['demo-cafe-downtown'];

// ---------------------------------------------------------------------------
// 3. One staff user per role. Admin is franchise-wide (cafe_id NULL);
//    everyone else is pinned to the downtown branch.
// ---------------------------------------------------------------------------
$seedPassword = 'Password123!';
$staffToSeed = [
    ['role' => 'admin',   'name' => 'Ada Admin',    'email' => 'admin@demo-cafe.test',   'cafe_id' => null],
    ['role' => 'manager', 'name' => 'Mona Manager', 'email' => 'manager@demo-cafe.test', 'cafe_id' => $primaryCafeId],
    ['role' => 'cashier', 'name' => 'Cody Cashier', 'email' => 'cashier@demo-cafe.test', 'cafe_id' => $primaryCafeId],
    ['role' => 'barista', 'name' => 'Bea Barista',  'email' => 'barista@demo-cafe.test', 'cafe_id' => $primaryCafeId],
];

$passwordHash = password_hash($seedPassword, PASSWORD_DEFAULT);

echo "\n";

foreach ($staffToSeed as $staff) {
    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name');
    $roleStmt->execute([':name' => $staff['role']]);
    $roleId = $roleStmt->fetchColumn();

    if ($roleId === false) {
        fwrite(STDERR, "Role '{$staff['role']}' not found — did the schema seed roles? Skipping.\n");
        continue;
    }

    $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $existing->execute([':email' => $staff['email']]);

    if ($existing->fetchColumn() !== false) {
        echo "User {$staff['email']} already exists — skipping.\n";
        continue;
    }

    $insert = $pdo->prepare(
        "INSERT INTO users (full_name, email, phone_number, password, franchise_id, cafe_id, role_id, status)
         VALUES (:full_name, :email, :phone, :password, :franchise_id, :cafe_id, :role_id, 'active')"
    );
    $insert->execute([
        ':full_name' => $staff['name'],
        ':email' => $staff['email'],
        ':phone' => '555-0101',
        ':password' => $passwordHash,
        ':franchise_id' => $franchiseId,
        ':cafe_id' => $staff['cafe_id'],
        ':role_id' => $roleId,
    ]);
    echo "Created {$staff['role']} user: {$staff['email']}\n";
}

// ---------------------------------------------------------------------------
// 4. A small starter menu on the downtown branch only, so the cashier/
//    kitchen flow isn't empty. Uptown starts empty on purpose.
// ---------------------------------------------------------------------------
$menu = [
    'Coffee' => [
        ['name' => 'Espresso', 'price' => '3.00', 'description' => 'Double shot.'],
        ['name' => 'Latte', 'price' => '4.50', 'description' => 'Espresso with steamed milk.'],
        ['name' => 'Cold Brew', 'price' => '4.00', 'description' => 'Slow-steeped, served over ice.'],
    ],
    'Pastries' => [
        ['name' => 'Croissant', 'price' => '3.50', 'description' => 'Butter croissant, baked daily.'],
        ['name' => 'Blueberry Muffin', 'price' => '3.75', 'description' => null],
    ],
];

echo "\n";

foreach ($menu as $categoryName => $items) {
    $catStmt = $pdo->prepare('SELECT id FROM menu_categories WHERE cafe_id = :cafe_id AND name = :name');
    $catStmt->execute([':cafe_id' => $primaryCafeId, ':name' => $categoryName]);
    $categoryId = $catStmt->fetchColumn();

    if ($categoryId === false) {
        $insertCat = $pdo->prepare('INSERT INTO menu_categories (cafe_id, name) VALUES (:cafe_id, :name)');
        $insertCat->execute([':cafe_id' => $primaryCafeId, ':name' => $categoryName]);
        $categoryId = (int) $pdo->lastInsertId();
        echo "Created menu category '{$categoryName}'.\n";
    } else {
        $categoryId = (int) $categoryId;
    }

    foreach ($items as $item) {
        $itemStmt = $pdo->prepare('SELECT id FROM menu_items WHERE cafe_id = :cafe_id AND name = :name');
        $itemStmt->execute([':cafe_id' => $primaryCafeId, ':name' => $item['name']]);

        if ($itemStmt->fetchColumn() !== false) {
            continue;
        }

        $insertItem = $pdo->prepare(
            'INSERT INTO menu_items (cafe_id, category_id, name, description, price, is_available)
             VALUES (:cafe_id, :category_id, :name, :description, :price, 1)'
        );
        $insertItem->execute([
            ':cafe_id' => $primaryCafeId,
            ':category_id' => $categoryId,
            ':name' => $item['name'],
            ':description' => $item['description'],
            ':price' => $item['price'],
        ]);
        echo "  + {$item['name']} (\${$item['price']})\n";
    }
}

echo "\nDone. Log in at /login.php with:\n";
foreach ($staffToSeed as $staff) {
    $scope = $staff['cafe_id'] === null ? 'all branches' : 'Downtown branch only';
    echo "  {$staff['role']}: {$staff['email']} / {$seedPassword}  ({$scope})\n";
}
echo "\nPublic menu: /index.php?cafe=demo-cafe-downtown\n";
echo "Log in as admin and use the branch switcher in the header to see Uptown (empty on purpose).\n";
