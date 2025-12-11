# ใช้ PHP เวอร์ชั่น 8.2 พร้อม Apache (คล้ายๆ กับโฮสต์เดิมที่คุณใช้)
FROM php:8.2-apache

# ติดตั้ง Extension ที่จำเป็นสำหรับการทำ API และเชื่อมต่อ Database
RUN docker-php-ext-install mysqli pdo pdo_mysql

# เปิดใช้งาน Mod Rewrite ของ Apache (เผื่อใช้ .htaccess)
RUN a2enmod rewrite
    
# ก๊อปปี้ไฟล์โค้ดทั้งหมดของคุณในโฟลเดอร์ปัจจุบัน ไปใส่ในโฟลเดอร์เว็บของ Server
COPY . /var/www/html/

# ตั้งค่าให้ Port 80 เป็น Port หลัก (Render จะ Map ให้เอง)
EXPOSE 80