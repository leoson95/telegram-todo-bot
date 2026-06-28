<?php
// تنظیمات زمان
$timezone = getenv('TIMEZONE') ?: 'Asia/Tehran';
date_default_timezone_set($timezone);

// تنظیمات توکن ربات
$botToken = getenv('BOT_TOKEN');
if (empty($botToken)) {
    error_log("BOT_TOKEN is not set in the environment.");
    exit("Configuration Error: Bot token is not configured.");
}
$apiUrl = "https://api.telegram.org/bot" . $botToken;

// تنظیمات دیتابیس
$dbPath = getenv('DB_PATH') ?: '/app/data/todo.db';

// اطمینان از وجود پوشه دیتابیس
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

// اتصال به دیتابیس SQLite و ارتقای خودکار ساختار
try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ایجاد جداول اگر وجود نداشته باشند
    $db->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id TEXT,
        text TEXT,
        category TEXT,
        is_done INTEGER DEFAULT 0,
        next_trigger TEXT,
        repeat_days INTEGER DEFAULT 0
    )");

    try { $db->exec("ALTER TABLE tasks ADD COLUMN next_trigger TEXT"); } catch(Exception $e){}
    try { $db->exec("ALTER TABLE tasks ADD COLUMN repeat_days INTEGER DEFAULT 0"); } catch(Exception $e){}

    $db->exec("CREATE TABLE IF NOT EXISTS user_session (
        chat_id TEXT PRIMARY KEY,
        main_message_id INTEGER,
        temp_message_id INTEGER,
        state TEXT,
        temp_text TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chat_id TEXT,
        name TEXT,
        UNIQUE(chat_id, name)
    )");
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    exit("Database connection failed. Please check the configuration.");
}
