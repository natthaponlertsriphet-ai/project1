========================================================================
             CHIT HOLE CNX - Table Reservation & Management System
========================================================================

[ข้อมูลโปรเจกต์ (Project Overview)]
ระบบเว็บแอปพลิเคชันจองโต๊ะ ตารางดนตรีสด เมนูคราฟต์เบียร์ และโปรโมชันร้าน CHIT HOLE CNX
พัฒนาด้วย PHP (Vanilla PHP), MySQL / SQLite, HTML5, CSS3, JavaScript (Bootstrap 5)
รองรับการแจ้งเตือนผ่าน LINE Messaging API (ทั้ง Admin Chat และ LINE Group)

------------------------------------------------------------------------
[บัญชีผู้ใช้งานระบบ (Default Login Credentials)]
------------------------------------------------------------------------
1. สิทธิ์ผู้ดูแลระบบ (ADMIN):
   - URL เข้าใช้งาน: /login.php หรือ /admin/index.php
   - Email: admin@chithole.com
   - Password: admin (หรือรหัสผ่านที่ตั้งไว้ในระบบ)

2. สิทธิ์พนักงาน (STAFF):
   - URL เข้าใช้งาน: /login.php
   - Email: staff@chithole.com  / Password: staff
   - Email: nook@chithole.com   / Password: nook

------------------------------------------------------------------------
[ไฟล์ฐานข้อมูล (Database SQL)]
------------------------------------------------------------------------
- ไฟล์ฐานข้อมูล: database.sql
- ประกอบด้วยตาราง: admin, menu, music, promotion, reservation, staff, table
- สามารถนำเข้า (Import) ไฟล์ database.sql เข้าไปยัง MySQL / MariaDB (เช่น phpMyAdmin หรือ MySQL Workbench) ได้ทันที

------------------------------------------------------------------------
[การเชื่อมต่อฐานข้อมูลและการตั้งค่า (Configuration)]
------------------------------------------------------------------------
- การตั้งค่าเชื่อมต่อฐานข้อมูลอยู่ที่ไฟล์: db.php
- การตั้งค่า LINE Messaging API และ LINE Group ID อยู่ที่ไฟล์: config_line.php และ .env
- ตัวอย่างการตั้งค่าในไฟล์ .env:
  MYSQLHOST=localhost
  MYSQLUSER=root
  MYSQLPASSWORD=
  MYSQLDATABASE=chithole
  MYSQLPORT=3306
  LINE_CHANNEL_ID=2011291021
  LINE_CHANNEL_SECRET=78249fb26f438d4353987129941ced5b
  LINE_CHANNEL_ACCESS_TOKEN=ZrnDPm7k4ImZ8fe5TNMTO8wFQpCaXm7gTpugk+R3gnIAdslHsCpn8peL9Lbiutht4Z8I04xnPFB5oJAXuZk2J1wpLNKsEQ7lhnL0Bzwqmem651JexEcR9bFaZ0jXXedyyqlEzwq3yHbVkwHKhwy71gdB04t89/1O/w1cDnyilFU=
  LINE_ADMIN_USER_ID=Ub31b624096f005348877004618e72421
  LINE_GROUP_ID=C47d40d414eee4c9c6cf4f5a851f598f9

------------------------------------------------------------------------
[วิธีการติดตั้งและรันโปรเจกต์ (Installation & Running)]
------------------------------------------------------------------------
1. นำโฟลเดอร์โปรเจกต์ไปวางไว้ใน Web Server (เช่น XAMPP htdocs, Nginx, Apache หรือ Azure App Service)
2. สร้าง Database ใน MySQL ชื่อ `chithole` และนำเข้าไฟล์ `database.sql`
3. เปิดหน้าเว็บผ่านเบราว์เซอร์ เช่น http://localhost/project1/ หรือตามโดเมนที่ตั้งไว้
========================================================================
