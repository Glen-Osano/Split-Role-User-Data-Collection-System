<?php
// ─── Show ALL errors (helpful for XAMPP debugging) ─────────────
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ─── Database Config ───────────────────────────────────────────
$host   = 'localhost';
$user   = 'root';
$pass   = '';          // XAMPP default = empty password
$dbname = 'userdb';

// ─── Step 1: Connect WITHOUT selecting a database first ────────
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("
        <div style='font-family:monospace;background:#1a0000;color:#ff6b6b;padding:2rem;border-radius:8px;margin:2rem'>
            <h2>❌ Cannot connect to MySQL</h2>
            <p style='margin-top:1rem'>Error: <strong>" . $conn->connect_error . "</strong></p>
            <hr style='border-color:#ff6b6b33;margin:1rem 0'>
            <p>Fix: Open XAMPP Control Panel and click <strong>Start</strong> next to MySQL</p>
        </div>
    ");
}

// ─── Step 2: Create database if it doesn't exist ───────────────
if (!$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci")) {
    die("Failed to create database: " . $conn->error);
}

// ─── Step 3: Select the database ──────────────────────────────
$conn->select_db($dbname);
$conn->set_charset('utf8mb4');

// ─── Step 4: Create users table ───────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS users (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(150) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ─── Step 5: Create user_inputs table ─────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS user_inputs (
        user_id INT PRIMARY KEY,
        dob     DATE DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ─── Step 6: Create the VIEW (drop first to avoid conflicts) ───
$conn->query("DROP VIEW IF EXISTS user_report");
$conn->query("
    CREATE VIEW user_report AS
    SELECT
        u.id,
        u.name,
        u.email,
        ui.dob,
        CASE
            WHEN ui.dob IS NOT NULL
            THEN TIMESTAMPDIFF(YEAR, ui.dob, CURDATE())
            ELSE NULL
        END AS age,
        u.created_at
    FROM users u
    LEFT JOIN user_inputs ui ON u.id = ui.user_id
");
?>