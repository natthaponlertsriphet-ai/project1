แหล่งข้อมูล: D3
ชื่อแฟ้มข้อมูล: แฟ้มข้อมูลผังที่นั่งและโต๊ะ (Table)
ชนิดแฟ้มข้อมูล: Master File
คำอธิบายแฟ้มข้อมูล: สำหรับเก็บข้อมูลโต๊ะและตำแหน่งที่นั่งภายในร้าน

ตารางที่ 3.5 แสดง Data Dictionary ของแฟ้มข้อมูลผังที่นั่งและโต๊ะ

| ชื่อแอทริบิวต์ | ความหมาย | ชนิดข้อมูล | ขนาด (ไบต์) | รูปแบบ | รูปแบบช่วงข้อมูล | ป้อนข้อมูล (Y/N) | คีย์หลักหรือคีย์นอก | ตารางที่อ้างอิง |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `table_id` | รหัสโต๊ะ | Varchar(50) | 50 | x(50) | A-Z,0-9,a-z,- | Y | PK | - |
| `number` | หมายเลขโต๊ะ | Varchar(50) | 50 | x(50) | A-Z,0-9,a-z | Y | FK | - |
| `zone` | โซนที่ตั้งโต๊ะ | Varchar(50) | 50 | x(50) | OUTDOOR, INDOOR_WINDOW, INDOOR_CENTER, STAGE, BAR, WALKWAY | Y | - | - |
| `capacity` | จำนวนที่นั่งสูงสุด | Int | 11 | 9(11) | 0-9 | Y | - | - |
| `status` | สถานะโต๊ะ | Varchar(50) | 50 | x(50) | AVAILABLE, OCCUPIED | Y | - | - |
| `image` | เส้นทางไฟล์ภาพโต๊ะ | Varchar(255) | 255 | x(255) | a-z,A-Z,0-9,/,. | N | - | - |

ตารางที่ 3.6 แสดงตัวอย่างการเก็บข้อมูลของแฟ้มข้อมูลผังที่นั่งและโต๊ะ

| table_id | number | zone | capacity | status | image |
| :--- | :--- | :--- | :--- | :--- | :--- |
| d-1 | D1 | OUTDOOR | 2 | AVAILABLE | images/tables/uploaded_1785355572_table1.jpg |
| w-1 | 01 | INDOOR_WINDOW | 8 | AVAILABLE | NULL |
| c-3 | 03 | INDOOR_CENTER | 4 | AVAILABLE | NULL |
| b-16 | 16 | BAR | 4 | AVAILABLE | NULL |

หมายเหตุ: status AVAILABLE=ว่าง (เปิดบริการ), OCCUPIED=ไม่ว่าง (ปิดบริการหรือจองเต็มแล้ว)
