<?php
/**
 * KIM INVENTORIES — db_connect.php
 * PDO connection to MySQL. Auto-creates database, tables, and seed data.
 */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'kim_inventories';

try {
    // Connect without database first to create it if needed
    $pdo = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$DB_NAME`");

    // ─── Create Tables ──────────────────────────────────────────────────────

    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `user_id`    INT AUTO_INCREMENT PRIMARY KEY,
        `email`      VARCHAR(255) NOT NULL UNIQUE,
        `username`   VARCHAR(100) NOT NULL,
        `password`   VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Category table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `category` (
        `category_id`   INT AUTO_INCREMENT PRIMARY KEY,
        `category_name` VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB");

    // Products table (includes quantity and unit for full inventory tracking)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `product_id`   INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`      INT NOT NULL,
        `category_id`  INT NOT NULL,
        `product_name` VARCHAR(255) NOT NULL,
        `price`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `quantity`     INT NOT NULL DEFAULT 0,
        `unit`         VARCHAR(50) DEFAULT '',
        FOREIGN KEY (`user_id`)     REFERENCES `users`(`user_id`)    ON DELETE CASCADE,
        FOREIGN KEY (`category_id`) REFERENCES `category`(`category_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ─── Seed Default Data (only if tables are empty) ────────────────────────

    // Seed categories
    $catCount = $pdo->query("SELECT COUNT(*) FROM `category`")->fetchColumn();
    if ($catCount == 0) {
        $stmt = $pdo->prepare("INSERT INTO `category` (`category_name`) VALUES (?)");
        foreach (['Beverages', 'Pastries', 'Light Meals', 'Others'] as $cat) {
            $stmt->execute([$cat]);
        }
    }

    // Seed default admin user
    $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    if ($userCount == 0) {
        $hashedPw = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO `users` (`email`, `username`, `password`) VALUES (?, ?, ?)");
        $stmt->execute(['admin@kiminventories.com', 'Admin', $hashedPw]);

        // Seed sample products for admin (user_id = 1)
        $adminId = $pdo->lastInsertId();

        // Get category IDs
        $cats = [];
        $rows = $pdo->query("SELECT category_id, category_name FROM `category`")->fetchAll();
        foreach ($rows as $r) {
            $cats[$r['category_name']] = $r['category_id'];
        }

        $sampleProducts = [
            ['Americano',        'Beverages',   85,  20, 'cup'],
            ['Café Latte',       'Beverages',   110, 15, 'cup'],
            ['Green Tea',        'Beverages',   70,   8, 'cup'],
            ['Sparkling Water',  'Beverages',   40,  12, 'bottle'],
            ['Croissant',        'Pastries',    65,  80, 'piece'],
            ['Cinnamon Roll',    'Pastries',    75,  75, 'piece'],
            ['Blueberry Muffin', 'Pastries',    55,   5, 'piece'],
            ['Chocolate Cake',   'Pastries',    90,  85, 'slice'],
            ['Butter Cookie',    'Pastries',    45,  60, 'piece'],
            ['Club Sandwich',    'Light Meals', 150, 50, 'plate'],
            ['Pasta Salad',      'Light Meals', 120,  3, 'plate'],
            ['Caesar Salad',     'Light Meals', 130, 47, 'plate'],
            ['Mixed Nuts',       'Others',      90,  25, 'pack'],
            ['Energy Bar',       'Others',      45,  15, 'piece'],
        ];

        $stmt = $pdo->prepare("INSERT INTO `products` (`user_id`, `category_id`, `product_name`, `price`, `quantity`, `unit`) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($sampleProducts as $p) {
            $catId = $cats[$p[1]] ?? 1;
            $stmt->execute([$adminId, $catId, $p[0], $p[2], $p[3], $p[4]]);
        }
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
