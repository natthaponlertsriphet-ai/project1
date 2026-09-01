<?php
require_once __DIR__ . '/config_line.php';

/**
 * Sends a raw LINE Push Message to a specific LINE user ID.
 */
function sendLinePushMessage($to, $messages) {
    if (LINE_CHANNEL_ACCESS_TOKEN === 'YOUR_CHANNEL_ACCESS_TOKEN_HERE' || empty($to)) {
        return false;
    }

    $url = 'https://api.line.me/v2/bot/message/push';
    $payload = json_encode([
        'to' => $to,
        'messages' => $messages
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}



/**
 * Sends a notification to the restaurant admin when a new booking is submitted.
 */
function notifyAdminNewBooking($booking) {
    if (!defined('LINE_ADMIN_USER_ID') || LINE_ADMIN_USER_ID === '' || empty(LINE_ADMIN_USER_ID)) {
        return false;
    }

    $refId = $booking['id'];
    $name = $booking['customer_name'];
    $phone = $booking['customer_phone'];
    $date = $booking['date'];
    $time = $booking['time_slot'];
    $pax = $booking['pax'];
    $tableNum = $booking['table_number'] ?? 'N/A';
    
    $zone = $booking['table_zone'] ?? 'N/A';
    if ($zone === 'INDOOR') $zoneText = 'ห้องแอร์ (Indoor)';
    elseif ($zone === 'OUTDOOR') $zoneText = 'โซนด้านนอก (Outdoor)';
    elseif ($zone === 'STAGE') $zoneText = 'หน้าเวที (Stage)';
    elseif ($zone === 'INDOOR_WINDOW') $zoneText = 'ติดกระจก (Window Side)';
    elseif ($zone === 'INDOOR_CENTER') $zoneText = 'ตรงกลาง (Center)';
    elseif ($zone === 'BAR') $zoneText = 'หน้าบาร์ (Bar)';
    elseif ($zone === 'WALKWAY') $zoneText = 'โซนทางเดิน (Walkway)';
    else $zoneText = $zone;

    $altText = "🆕 มีคิวจองโต๊ะใหม่เข้ามาจากคุณ " . $name;

    // Detect server host dynamically (supports ngrok, localtunnel, localhost, etc.)
    $host = $_SERVER['HTTP_HOST'] ?? 'width-visibly-revisit.ngrok-free.dev';
    $protocol = (strpos($host, 'localhost') !== false) ? 'http://' : 'https://';
    $logoUrl = $protocol . $host . '/images/logo/755221157_122278964708129427_8713818424547983601_n.jpg';
    $adminUrl = $protocol . $host . '/admin/index.php';

    $bubble = [
        "type" => "bubble",
        "styles" => [
            "header" => ["backgroundColor" => "#000000"],
            "body" => ["backgroundColor" => "#121212"],
            "footer" => ["backgroundColor" => "#121212"]
        ],
        "header" => [
            "type" => "box",
            "layout" => "vertical",
            "contents" => [
                [
                    "type" => "text",
                    "text" => "CHIT HOLE CNX",
                    "color" => "#eab308", // Gold brand color
                    "weight" => "bold",
                    "size" => "sm"
                ]
            ]
        ],
        "body" => [
            "type" => "box",
            "layout" => "vertical",
            "contents" => [
                [
                    "type" => "text",
                    "text" => "🆕 มีรายการจองโต๊ะใหม่เข้ามา!",
                    "weight" => "bold",
                    "size" => "md",
                    "color" => "#ffffff"
                ],
                [
                    "type" => "box",
                    "layout" => "vertical",
                    "margin" => "md",
                    "contents" => [
                        [
                            "type" => "box",
                            "layout" => "horizontal",
                            "margin" => "sm",
                            "contents" => [
                                ["type" => "text", "text" => "รหัสอ้างอิง", "color" => "#aaaaaa", "size" => "xs", "flex" => 3],
                                ["type" => "text", "text" => $refId, "color" => "#ffffff", "size" => "xs", "weight" => "bold", "flex" => 7]
                            ]
                        ],
                        [
                            "type" => "box",
                            "layout" => "horizontal",
                            "margin" => "sm",
                            "contents" => [
                                ["type" => "text", "text" => "ชื่อลูกค้า", "color" => "#aaaaaa", "size" => "xs", "flex" => 3],
                                ["type" => "text", "text" => $name, "color" => "#ffffff", "size" => "xs", "flex" => 7]
                            ]
                        ],
                        [
                            "type" => "box",
                            "layout" => "horizontal",
                            "margin" => "sm",
                            "contents" => [
                                ["type" => "text", "text" => "เบอร์โทร", "color" => "#aaaaaa", "size" => "xs", "flex" => 3],
                                ["type" => "text", "text" => $phone, "color" => "#ffffff", "size" => "xs", "flex" => 7]
                            ]
                        ],
                        [
                            "type" => "box",
                            "layout" => "horizontal",
                            "margin" => "sm",
                            "contents" => [
                                ["type" => "text", "text" => "วันเวลา", "color" => "#aaaaaa", "size" => "xs", "flex" => 3],
                                ["type" => "text", "text" => $date . " @ " . $time, "color" => "#ffffff", "size" => "xs", "flex" => 7]
                            ]
                        ],
                        [
                            "type" => "box",
                            "layout" => "horizontal",
                            "margin" => "sm",
                            "contents" => [
                                ["type" => "text", "text" => "โต๊ะ & ที่นั่ง", "color" => "#aaaaaa", "size" => "xs", "flex" => 3],
                                ["type" => "text", "text" => "Table " . $tableNum . " (" . $zoneText . ") - " . $pax . " Pax", "color" => "#ffffff", "size" => "xs", "flex" => 7]
                            ]
                        ]
                    ]
                ],
                [
                    "type" => "separator",
                    "margin" => "md",
                    "color" => "#333333"
                ]
            ]
        ],
        "footer" => [
            "type" => "box",
            "layout" => "vertical",
            "contents" => [
                [
                    "type" => "button",
                    "action" => [
                        "type" => "uri",
                        "label" => "เปิดระบบหลังบ้าน",
                        "uri" => $adminUrl
                    ],
                    "color" => "#10b981", // Success green
                    "style" => "primary",
                    "height" => "sm"
                ]
            ]
        ]
    ];

    $messages = [
        [
            "type" => "flex",
            "altText" => $altText,
            "contents" => $bubble
        ]
    ];

    $sent = sendLinePushMessage(LINE_ADMIN_USER_ID, $messages);

    // Also send notification to the LINE Group if configured
    if (defined('LINE_GROUP_ID') && LINE_GROUP_ID !== '' && !empty(LINE_GROUP_ID)) {
        sendLinePushMessage(LINE_GROUP_ID, $messages);
    }

    return $sent;
}
