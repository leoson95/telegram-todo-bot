<?php

// --- توابع کمکی ---

/**
 * ارسال درخواست به API تلگرام
 */
function apiRequest($method, $parameters) {
    global $apiUrl;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/' . $method);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * حذف یک پیام مشخص از تلگرام
 */
function deleteMessage($chat_id, $msg_id) {
    if ($msg_id) {
        apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $msg_id]);
    }
}

/**
 * دریافت و ایجاد نمای لیست کارهای اصلی کاربر
 */
function renderMainList($chat_id, $db) {
    $stmt = $db->prepare("SELECT * FROM tasks WHERE chat_id = ? AND is_done = 0");
    $stmt->execute([$chat_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $categories = [
        '🔴 فوری' => [], '💅 کارای سالن' => [], '🛒 خریدای سالن' => [],
        '🏠 کارای خونه' => [], '🛍️ خریدای خونه' => [], '👤 کارای شخصی' => []
    ];

    foreach ($tasks as $task) {
        if (array_key_exists($task['category'], $categories)) {
            $categories[$task['category']][] = $task;
        }
    }

    $textOutput = "📋 <b>لیست کار ها و خرید ها</b>\n";
    $textOutput .= "━━━━━━━━━━━━━━━━━━━━━\n\n";

    $hasTasks = false;
    foreach ($categories as $catName => $catTasks) {
        $count = count($catTasks);
        if ($count > 0) {
            $hasTasks = true;
            $textOutput .= "📂 <b>" . $catName . "</b> │ 📋 <code>" . $count . " کار</code>\n\n\n";
            foreach ($catTasks as $t) {
                $status = (!empty($t['next_trigger'])) ? " ⏰" : "";
                $textOutput .= "▫️ " . htmlspecialchars($t['text']) . $status . "\n";
            }
            $textOutput .= "─────────────────────\n\n";
        }
    }

    if (!$hasTasks) {
        $textOutput .= "🕊️ <i>در حال حاضر هیچ کاری در لیست شما وجود ندارد!</i>\n\n";
        $textOutput .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
    }

    $keyboard = [[['text' => "⚡ مدیریت و اتمام کارها", 'callback_data' => "manage_start"]]];
    return ['text' => $textOutput, 'keyboard' => $keyboard];
}

/**
 * به‌روزرسانی پیام اصلی شامل لیست کارها
 */
function updateMainMessage($chat_id, $db, $session) {
    $listData = renderMainList($chat_id, $db);
    $params = [
        'chat_id' => $chat_id,
        'text' => $listData['text'],
        'parse_mode' => 'HTML',
        'reply_markup' => ['inline_keyboard' => $listData['keyboard']]
    ];

    $success = false;
    if ($session['main_message_id']) {
        $params['message_id'] = $session['main_message_id'];
        $res = apiRequest('editMessageText', $params);
        if ($res && $res['ok']) {
            $success = true;
        }
    }

    if (!$success) {
        unset($params['message_id']);
        $res = apiRequest('sendMessage', $params);
        if ($res && $res['ok']) {
            $new_msg_id = $res['result']['message_id'];
            $db->prepare("UPDATE user_session SET main_message_id = ? WHERE chat_id = ?")->execute([$new_msg_id, $chat_id]);
        }
    }
}
