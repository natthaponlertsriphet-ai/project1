แหล่งข้อมูล: D7
ชื่อแฟ้มข้อมูล: แฟ้มข้อมูลการจองโต๊ะ (Reservation / Booking)
ชนิดแฟ้มข้อมูล: Transaction File
คำอธิบายแฟ้มข้อมูล: สำหรับเก็บข้อมูลประวัติและสถานะการจองโต๊ะนั่งของลูกค้าทั้งหมดที่ทำรายการจองผ่านเว็บไซต์

ตารางที่ 3.13 แสดง Data Dictionary ของแฟ้มข้อมูลการจองโต๊ะ

| ชื่อแอทริบิวต์ | ความหมาย | ชนิดข้อมูล | ขนาด (ไบต์) | รูปแบบ | รูปแบบช่วงข้อมูล | ป้อนข้อมูล (Y/N) | คีย์หลักหรือคีย์นอก | ตารางที่อ้างอิง |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `reservation_id` | รหัสอ้างอิงการจอง | Varchar(50) | 50 | x(50) | A-Z,0-9,a-z,- | N | PK | - |
| `customer_name` | ชื่อผู้จองโต๊ะ | Varchar(255) | 255 | x(255) | a-z,A-Z,ก-ฮ | Y | - | - |
| `customer_phone` | เบอร์โทรศัพท์ติดต่อ | Varchar(50) | 50 | 0xxxxxxxxx | 0-9 | Y | FK | customer |
| `reservation_date` | วันที่เข้าใช้บริการ | Varchar(20) | 20 | YYYY-MM-DD | 0-9 | Y | - | - |
| `reservation_time` | เวลาที่เข้าใช้บริการ | Varchar(20) | 20 | hh:mm - hh:mm | 0-9 | Y | - | - |
| `guest_count` | จำนวนผู้เข้าใช้บริการ | Int | 4 | 99 | 0-9 | Y | - | - |
| `table_id` | รหัสโต๊ะที่เลือกจอง | Varchar(50) | 50 | x(50) | A-Z,0-9,a-z,- | N | FK | table |
| `reservation_status` | สถานะรายการจอง | Varchar(50) | 50 | x(50) | PENDING, CONFIRMED, COMPLETED, CANCELLED, CANCEL_REQUESTED | N | - | - |
| `cancel_reason` | เหตุผลการยกเลิก | Text | 65535 | x(65535) | a-z,A-Z,ก-ฮ | Y | - | - |
| `created_at` | วันเวลาที่เริ่มทำรายการ | Timestamp | 8 | YYYY-MM-DD hh:mm:ss | CURRENT_TIMESTAMP | N | - | - |
| `updated_at` | วันเวลาที่อัปเดตสถานะ | Timestamp | 8 | YYYY-MM-DD hh:mm:ss | CURRENT_TIMESTAMP | N | - | - |

ตารางที่ 3.14 แสดงตัวอย่างการเก็บข้อมูลของแฟ้มข้อมูลการจองโต๊ะ

| reservation_id | customer_name | customer_phone | reservation_date | reservation_time | guest_count | table_id | reservation_status | cancel_reason | created_at | updated_at |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| CHITHOLECNX_123 | นก แสนดี | 0800711996 | 2026-08-30 | 19:30 - 21:00 | 4 | table_1 | PENDING | - | 2026-08-29 18:00:00 | 2026-08-29 18:00:00 |
