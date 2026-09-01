<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config_line.php';

// Verify signature from LINE header for security
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';
$rawBody = file_get_contents('php://input');

// Log webhook payload for debugging and retrieving group IDs
if (!empty($rawBody)) {
    file_put_contents(__DIR__ . '/line_webhook_log.txt', date('[Y-m-d H:i:s] ') . $rawBody . PHP_EOL, FILE_APPEND);
}

$calculatedSignature = base64_encode(hash_hmac('sha256', $rawBody, LINE_CHANNEL_SECRET, true));

if ($signature !== $calculatedSignature) {
    // Return 200 to LINE Verification request but block invalid ones
    if (empty($signature)) {
        http_response_code(200);
        echo "Ok (No signature for verification test)";
        exit;
    }
    http_response_code(400);
    exit("Invalid signature");
}

$data = json_decode($rawBody, true);
if (empty($data['events'])) {
    http_response_code(200);
    exit();
}

foreach ($data['events'] as $event) {
    $replyToken = $event['replyToken'] ?? null;
    if (empty($replyToken)) {
        continue;
    }

    // 1. Handle Join Event (when bot is added to a group/room)
    if ($event['type'] === 'join') {
        $sourceType = $event['source']['type'] ?? '';
        if ($sourceType === 'group' || $sourceType === 'room') {
            $groupId = $event['source']['groupId'] ?? ($event['source']['roomId'] ?? '');
            $replyText = "สวัสดีค่ะ ยินดีที่ได้ร่วมงานกับทางร้าน CHIT HOLE CNX ค่ะ! 🍻✨\n\n📌 กลุ่มนี้พร้อมใช้แจ้งเตือนคิวจองโต๊ะของลูกค้าแล้วค่ะ\n\n🔑 LINE Group ID สำหรับนำไปตั้งค่าคือ:\n$groupId\n\n(กรุณานำไอดีนี้ไปคัดลอกใส่ใน config_line.php เพื่อให้ระบบส่งข้อความแจ้งเตือนมายังกลุ่มนี้ค่ะ)";
            sendLineReplyMessage($replyToken, $replyText);
        }
        continue;
    }

    // 2. Handle Text Message Event
    if ($event['type'] !== 'message' || $event['message']['type'] !== 'text') {
        continue;
    }

    $userId = $event['source']['userId'] ?? '';
    $userMessage = trim($event['message']['text']);
    $sourceType = $event['source']['type'] ?? 'user';
    $groupId = $event['source']['groupId'] ?? ($event['source']['roomId'] ?? '');

    // If inside a group chat, let the bot reply to group queries
    if ($sourceType === 'group' || $sourceType === 'room') {
        if (in_array(mb_strtolower($userMessage), ['เช็คไอดีกลุ่ม', 'เช็คไอดี', 'ไอดีกลุ่ม', 'get group id', 'group id'])) {
            $replyText = "🔑 LINE Group ID ของกลุ่มแชทนี้คือ:\n$groupId";
            sendLineReplyMessage($replyToken, $replyText);
        }
        continue;
    }

    // Guide customers to check status on the website and keep them out of chatbot
    $replyText = "สวัสดีค่ะ ช่องทางนี้เป็นระบบแจ้งเตือนอัตโนมัติภายในสำหรับทางร้านเท่านั้นค่ะ\n\nหากคุณเป็นลูกค้าที่ต้องการตรวจสอบสถานะการอนุมัติการจองโต๊ะนั่ง กรุณาตรวจสอบผ่านหน้าเว็บไซต์ของร้านโดยตรงได้เลยค่ะ 🍻✨\n\n👉 โดยไปที่เมนู 'ตรวจสอบสถานะการจองโต๊ะ' แล้วกรอกรหัสการจองหรือเบอร์โทรศัพท์ของคุณเพื่อเช็คสถานะการจองล่าสุดได้ทันทีค่ะ";

    // Send reply via LINE Reply API
    sendLineReplyMessage($replyToken, $replyText);
}

/**
 * Replies to the LINE server with a simple text reply token.
 */
function sendLineReplyMessage($replyToken, $text) {
    if (LINE_CHANNEL_ACCESS_TOKEN === 'YOUR_CHANNEL_ACCESS_TOKEN_HERE') {
        return;
    }

    $url = 'https://api.line.me/v2/bot/message/reply';
    $payload = json_encode([
        'replyToken' => $replyToken,
        'messages' => [
            [
                'type' => 'text',
                'text' => $text
            ]
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ]);

    curl_exec($ch);
    curl_close($ch);
}
