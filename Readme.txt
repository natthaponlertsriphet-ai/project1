CHIT HOLE CNX Table Booking & Administration System
================================

1. ข้อมูลโปรแกรม
- Language / Backend: PHP (Vanilla PHP 8.0+)
- Frontend: HTML5 + Vanilla CSS + JavaScript (Bootstrap 5 & Tailwind CSS)
- Database: MySQL / MariaDB (หรือ Azure Database for MySQL)
- Notification: LINE Messaging API (Flex Messages & Group Bot)

2. บัญชีสำหรับทดสอบระบบ
- ผู้ใช้งาน (User / Customer)
  สามารถเข้าจองโต๊ะและตรวจสอบสถานะการจองได้ทันทีผ่านหน้าเว็บโดยไม่ต้องลงทะเบียนบัญชี

- เจ้าหน้าที่ (Staff / Employee)
  Email: staff@chithole.com
  Password: staff

  Email: nook@chithole.com
  Password: nook

- ผู้ดูแลระบบ (Admin)
  Email: admin@chithole.com
  Password: admin

3. วิธีติดตั้ง
ข้อกำหนดเบื้องต้น:
- Web Server (เช่น XAMPP, Apache, Nginx หรือ Azure App Service)
- PHP (เวอร์ชัน 8.0 ขึ้นไป พร้อม PDO Extension)
- MySQL / MariaDB Server

ขั้นตอน:
1) แตกไฟล์ ZIP และนำไฟล์ซอร์สโค้ดไปวางในโฟลเดอร์ Web Server (เช่น XAMPP htdocs หรือ wwwroot)
2) สร้างฐานข้อมูล MySQL โดย Import ไฟล์ database.sql
3) คัดลอก .env.example เป็น .env (หรือแก้ไขไฟล์ db.php และ config_line.php)
4) แก้ไขค่าเชื่อมต่อ MySQL ในไฟล์ .env หรือ db.php ให้ตรงกับเครื่องของคุณ เช่น:
   DB_HOST="127.0.0.1"
   DB_PORT="3306"
   DB_NAME="chithole"
   DB_USER="root"
   DB_PASS=""
5) ตั้งค่า LINE Messaging API Credentials ใน .env หรือ config_line.php (หากต้องการใช้งานระบบแจ้งเตือน LINE Bot)
6) เปิดเบราว์เซอร์เข้าใช้งานผ่าน URL เช่น http://localhost/project1/ หรือตามโดเมนที่ตั้งไว้

หมายเหตุ:
- ไม่ได้แนบ .git และ .kilo เพื่อลดขนาดไฟล์และป้องกันประวัติที่ไม่จำเป็น
- แนบไฟล์ database.sql ซึ่งประกอบด้วยโครงสร้างตารางและข้อมูลเริ่มต้นครบถ้วน
- ระบบรองรับการทำงานทั้งบนเดสก์ท็อปและมือถือ (รวมถึง Safari บน iOS/macOS พร้อมป้องกันการแคช)
