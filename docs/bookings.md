แหล่งข้อมูล: D7
ชื่อแฟ้มข้อมูล: แฟ้มข้อมูลการจอง (Reservation)
ชนิดแฟ้มข้อมูล: Transaction File
คำอธิบายแฟ้มข้อมูล: สำหรับเก็บข้อมูลหลักของรายการคำขอจองโต๊ะของลูกค้าและการอนุมัติโดยร้าน

ตารางที่ 3.13 แสดง Data Dictionary ของแฟ้มข้อมูลการจอง

| ชื่อแอทริบิวต์ | ความหมาย | ชนิดข้อมูล | ขนาด (ไบต์) | รูปแบบ | รูปแบบช่วงข้อมูล | ป้อนข้อมูล (Y/N) | คีย์หลักหรือคีย์นอก | ตารางที่อ้างอิง |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `reserve_id` | รหัสอ้างอิงการจอง | Varchar(50) | 50 | x(50) | CHITHOLECNX_... | Y | PK | - |
| `customer_name` | ชื่อลูกค้าผู้ทำการจอง | Varchar(255) | 255 | x(255) | a-z,A-Z,ก-ฮ | Y | FK | - |
| `customer_phone` | เบอร์โทรศัพท์ลูกค้า | Varchar(50) | 50 | x(50) | A-Z,0-9,a-z,+,\s,-,() | Y | - | - |
| `date` | วันที่จองใช้บริการ | Varchar(20) | 20 | YYYY-MM-DD | YYYY-MM-DD | Y | - | - |
| `time_slot` | ช่วงเวลาที่เข้าใช้งาน | Varchar(20) | 20 | hh:mm | hh:mm | Y | - | - |
| `pax` | จำนวนลูกค้าในกลุ่ม | Int | 11 | 9(11) | 0-9 | Y | - | - |
| `table_id` | รหัสอ้างอิงโต๊ะที่จอง | Varchar(50) | 50 | x(50) | A-Z,0-9,a-z,- | Y | FK | D3 (tables) |
| `status` | สถานะรายการจอง | Varchar(50) | 50 | x(50) | PENDING, CONFIRMED, CANCELLED | Y | - | - |
| `created_at` | วันเวลาที่บันทึกข้อมูล | Timestamp | 8 | YYYY-MM-DD hh:mm:ss | CURRENT_TIMESTAMP | Y | - | - |
| `updated_at` | วันเวลาอัปเดตล่าสุด | Timestamp | 8 | YYYY-MM-DD hh:mm:ss | CURRENT_TIMESTAMP | Y | - | - |

ตารางที่ 3.14 แสดงตัวอย่างการเก็บข้อมูลของแฟ้มข้อมูลการจอง

| reserve_id | customer_name | customer_phone | date | time_slot | pax | table_id | status | created_at |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| CHITHOLECNX_6a6f427f2f82b | Test User | +66800711996 | 2026-08-03 | 19:00 | 2 | d-1 | PENDING | 2026-08-03 15:00:00 |

หมายเหตุ: status PENDING=รอยืนยันอนุมัติ, CONFIRMED=อนุมัติการจองแล้ว, CANCELLED=ปฏิเสธ/ยกเลิกการจอง
