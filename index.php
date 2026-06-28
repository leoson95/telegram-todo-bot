<?php

require_once 'config.php';
require_once 'functions.php';

// ۲. دریافت اطلاعات از وب‌هوک
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;
$chat_id = null; $message_id = null; $is_callback = false;
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $message_id = $update['message']['message_id'];
    $text = trim($update['message']['text']);
} elseif (isset($update['callback_query'])) {
    $is_callback = true;
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $message_id = $update['callback_query']['message']['message_id'];
    $callback_data = $update['callback_query']['data'];
    $callback_query_id = $update['callback_query']['id'];
}
if (!$chat_id) exit;
// دریافت سشن
$stmt = $db->prepare("SELECT * FROM user_session WHERE chat_id = ?");
$stmt->execute([$chat_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    $db->prepare("INSERT INTO user_session (chat_id, main_message_id, temp_message_id, state, temp_text) VALUES (?, NULL, NULL, NULL, NULL)")->execute([$chat_id]);
    $session = ['chat_id' => $chat_id, 'main_message_id' => null, 'temp_message_id' => null, 'state' => null, 'temp_text' => null];
}
// --- پردازش درخواست‌ها ---
if (!$is_callback) {
    deleteMessage($chat_id, $message_id);
    if ($text === '/start') {
        $db->prepare("UPDATE user_session SET state = NULL, temp_text = NULL WHERE chat_id = ?")->execute([$chat_id]);
        deleteMessage($chat_id, $session['temp_message_id']);
        updateMainMessage($chat_id, $db, ['main_message_id' => $session['main_message_id']]);
    } else {
        if ($session['state'] === 'AWAITING_REM_TIME') {
            if (preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/', $text)) {
                $data = json_decode($session['temp_text'], true);
                $targetTarget = date('Y-m-d') . ' ' . $text;
                if (strtotime($targetTarget) < time()) {
                    $targetTarget = date('Y-m-d', strtotime('+1 day')) . ' ' . $text;
                }
                $stmt = $db->prepare("INSERT INTO tasks (chat_id, text, category, next_trigger, repeat_days) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$chat_id, $data['text'], $data['cat'], $targetTarget, $data['repeat']]);
                $db->prepare("UPDATE user_session SET state = NULL, temp_text = NULL, temp_message_id = NULL WHERE chat_id = ?")->execute([$chat_id]);
                deleteMessage($chat_id, $session['temp_message_id']);
                updateMainMessage($chat_id, $db, $session);
            } else {
                apiRequest('editMessageText', [
                    'chat_id' => $chat_id,
                    'message_id' => $session['temp_message_id'],
                    'text' => "⚠️ <b>فرمت نامعتبر است!</b>\nلطفاً ساعت را دقیقاً به شکل ۲۴ ساعته بفرستید.\nنمونه: <code>08:00</code> یا `22:30`",
                    'parse_mode' => 'HTML'
                ]);
            }
        } else {
            deleteMessage($chat_id, $session['temp_message_id']);
            $catKeyboard = [
                'inline_keyboard' => [
                    [['text' => "🔴 فوری", 'callback_data' => "addcat_🔴 فوری"], ['text' => "💅 کارای سالن", 'callback_data' => "addcat_💅 کارای سالن"]],
                    [['text' => "🛒 خریدای سالن", 'callback_data' => "addcat_🛒 خریدای سالن"], ['text' => "🏠 کارای خونه", 'callback_data' => "addcat_🏠 کارای خونه"]],
                    [['text' => "🛍️ خریدای خونه", 'callback_data' => "addcat_🛍️ خریدای خونه"], ['text' => "👤 کارای شخصی", 'callback_data' => "addcat_👤 کارای شخصی"]],
                    [['text' => "❌ انصراف", 'callback_data' => "cancel_temp"]]
                ]
            ];
            $tempRes = apiRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "🗂️ <b>انتخاب دسته‌بندی برای کار جدید:</b>\n« <i>" . htmlspecialchars($text) . "</i> »",
                'parse_mode' => 'HTML',
                'reply_markup' => $catKeyboard
            ]);
            $temp_msg_id = $tempRes['ok'] ? $tempRes['result']['message_id'] : null;
            $db->prepare("UPDATE user_session SET state = 'AWAITING_ADD_CAT', temp_text = ?, temp_message_id = ? WHERE chat_id = ?")
               ->execute([json_encode(['text' => $text]), $temp_msg_id, $chat_id]);
        }
    }
} else {
    if (strpos($callback_data, 'addcat_') === 0) {
        $category = str_replace('addcat_', '', $callback_data);
        $data = json_decode($session['temp_text'], true);
        $data['cat'] = $category;
        $db->prepare("UPDATE user_session SET temp_text = ? WHERE chat_id = ?")->execute([json_encode($data), $chat_id]);
        $remKeyboard = [
            'inline_keyboard' => [
                [['text' => "🔔 بله، تنظیم یادآوری", 'callback_data' => "wants_rem_yes"]],
                [['text' => "❌ خیر، فقط در لیست باشد", 'callback_data' => "wants_rem_no"]]
            ]
        ];
        apiRequest('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "🔔 <b>آیا این کار نیاز به یادآوری زمان‌بندی شده دارد؟</b>\n« <i>" . htmlspecialchars($data['text']) . "</i> »",
            'parse_mode' => 'HTML',
            'reply_markup' => $remKeyboard
        ]);
    } elseif ($callback_data === 'wants_rem_no') {
        $data = json_decode($session['temp_text'], true);
        $db->prepare("INSERT INTO tasks (chat_id, text, category, next_trigger, repeat_days) VALUES (?, ?, ?, NULL, 0)")->execute([$chat_id, $data['text'], $data['cat']]);
        $db->prepare("UPDATE user_session SET state = NULL, temp_text = NULL, temp_message_id = NULL WHERE chat_id = ?")->execute([$chat_id]);
        deleteMessage($chat_id, $message_id);
        updateMainMessage($chat_id, $db, $session);
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "کار بدون یادآوری اضافه شد."]);
    } elseif ($callback_data === 'wants_rem_yes') {
        $intervalKeyboard = [
            'inline_keyboard' => [
                [['text' => "🔄 هر روز", 'callback_data' => "setint_1"], ['text' => "🔄 یک روز در میان", 'callback_data' => "setint_2"]],
                [['text' => "📅 هفتگی (۷ روز یکبار)", 'callback_data' => "setint_7"]],
                [['text' => "❌ انصراف", 'callback_data' => "cancel_temp"]]
            ]
        ];
        apiRequest('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "⏱️ <b>دوره تکرار یادآوری را مشخص کنید:</b>",
            'parse_mode' => 'HTML',
            'reply_markup' => $intervalKeyboard
        ]);
    } elseif (strpos($callback_data, 'setint_') === 0) {
        $days = (int)str_replace('setint_', '', $callback_data);
        $data = json_decode($session['temp_text'], true);
        $data['repeat'] = $days;
        $db->prepare("UPDATE user_session SET state = 'AWAITING_REM_TIME', temp_text = ? WHERE chat_id = ?")->execute([json_encode($data), $chat_id]);
        apiRequest('editMessageText', [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => "🕒 <b>ساعت یادآوری را وارد کنید:</b>\n\nلطفاً ساعت مورد نظر را به صورت متنی (۲۴ ساعته) بفرستید.\nنمونه: <code>08:30</code> یا <code>21:00</code>",
            'parse_mode' => 'HTML'
        ]);
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
    } elseif (strpos($callback_data, 'cron_done_') === 0) {
        $task_id = str_replace('cron_done_', '', $callback_data);
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$task_id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($task) {
            if ($task['repeat_days'] == 0) {
                $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task_id]);
            }
        }
        deleteMessage($chat_id, $message_id);
        updateMainMessage($chat_id, $db, $session);
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "انجام شد! منو پاکسازی شد."]);
    } elseif (strpos($callback_data, 'cron_seen_') === 0) {
        deleteMessage($chat_id, $message_id);
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "تایید شد. تسک در لیست باقی ماند."]);
    } elseif ($callback_data === 'manage_start') {
        deleteMessage($chat_id, $session['temp_message_id']);
        $manageKeyboard = [
            'inline_keyboard' => [
                [['text' => "🔴 فوری", 'callback_data' => "mget_🔴 فوری"], ['text' => "💅 کارای سالن", 'callback_data' => "mget_💅 کارای سالن"]],
                [['text' => "🛒 خریدای سالن", 'callback_data' => "mget_🛒 خریدای سالن"], ['text' => "🏠 کارای خونه", 'callback_data' => "mget_🏠 کارای خونه"]],
                [['text' => "🛍️ خریدای خونه", 'callback_data' => "mget_🛍️ خریدای خونه"], ['text' => "👤 کارای شخصی", 'callback_data' => "mget_👤 کارای شخصی"]],
                [['text' => "❌ بستن پنل", 'callback_data' => "cancel_temp"]]
            ]
        ];
        $tempRes = apiRequest('sendMessage', [
            'chat_id' => $chat_id, 'text' => "🛠️ <b>منوی مدیریت | دسته‌بندی را انتخاب کنید:</b>", 'parse_mode' => 'HTML', 'reply_markup' => $manageKeyboard
        ]);
        $db->prepare("UPDATE user_session SET state = 'MANAGE_SELECT_CAT', temp_message_id = ? WHERE chat_id = ?")->execute([$tempRes['result']['message_id'], $chat_id]);
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id]);
    } elseif (strpos($callback_data, 'mget_') === 0) {
        $category = str_replace('mget_', '', $callback_data);
        $stmt = $db->prepare("SELECT * FROM tasks WHERE chat_id = ? AND category = ? AND is_done = 0");
        $stmt->execute([$chat_id, $category]);
        $catTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $taskKeyboard = [];
        foreach ($catTasks as $t) { $taskKeyboard[] = [['text' => "🗑️ " . $t['text'], 'callback_data' => "mdone_" . $t['id']]]; }
        if(count($catTasks)==0) { $taskKeyboard[] = [['text' => "موردی وجود ندارد", 'callback_data' => "none"]]; }
        $taskKeyboard[] = [['text' => "🔙 بازگشت", 'callback_data' => "manage_start"]];
        apiRequest('editMessageText', ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => "📌 <b>حذف از دسته: " . $category . "</b>", 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $taskKeyboard]]);
    } elseif (strpos($callback_data, 'mdone_') === 0) {
        $task_id = str_replace('mdone_', '', $callback_data);
        $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$task_id]);
        updateMainMessage($chat_id, $db, $session);
        deleteMessage($chat_id, $message_id);
        apiRequest('answerCallbackQuery', ['callback_query_id' => $callback_query_id, 'text' => "حذف شد."]);
    } elseif ($callback_data === 'cancel_temp') {
        $db->prepare("UPDATE user_session SET state = NULL, temp_text = NULL, temp_message_id = NULL WHERE chat_id = ?")->execute([$chat_id]);
        deleteMessage($chat_id, $message_id);
    }
}

// ارسال هدر موفقیت برای تلگرام
http_response_code(200);