# Deploy — Nginx + PHP-FPM + HTTPS

คู่มือนี้ใช้ได้กับ Ubuntu 22.04/24.04 และ domain จริง 1 โดเมนที่ชี้มาที่ server แล้ว
Frontend กับ API อยู่ origin เดียวกัน จึงไม่ต้องเปิด CORS

ไฟล์ config ตัวอย่าง: [`deploy/nginx.conf.example`](../deploy/nginx.conf.example)

## Railway (Docker) — ทางเลือกไม่ต้องมี server เอง

โปรเจกต์มี `Dockerfile` (nginx + php-fpm + static frontend ใน container เดียว) พร้อม `docker/entrypoint.sh` ที่รัน migrate ทุก deploy และ retry loop ของ notification ทุก 60 วินาที

ขั้นตอนบน https://railway.app:

1. **New Project → Deploy from GitHub repo** → เลือก repo นี้ (Railway ตรวจ `Dockerfile` อัตโนมัติ)
2. **Add a database → MySQL** — Railway ฉีด `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` ให้ทุก service ใน project
3. ที่ service เว็บ → **Variables** ใส่:
   - `APP_URL` = URL ที่ Railway ให้ (เช่น `https://team-finder-production.up.railway.app`) — ต้อง Generate Domain ก่อนจึงจะรู้ค่า
   - `DRY_RUN=false`
   - `MAIL_DSN` / `MAIL_FROM` / `MAIL_FROM_NAME` — SMTP ของคุณ
   - `LINE_CHANNEL_ID` / `LINE_CHANNEL_SECRET` / `LINE_ACCESS_TOKEN` / `LINE_REDIRECT_URI` = `{APP_URL}/line/callback`
4. **Deploy** — entrypoint จะ migrate schema ให้เอง (database ยังว่าง = ได้ schema ล่าสุดทันที)
5. LINE Developers Console → LINE Login channel → App settings → เพิ่ม Callback URL `https://{domain}/line/callback`

หมายเหตุ:

- secrets ใส่เฉพาะใน Railway Variables — ห้าม commit ทุกกรณี
- `db.php`/`notifications.php` อ่าน env ก่อน แล้ว fallback เป็นไฟล์ local เดิม — dev ทำงานเหมือนเดิม
- ลบ service หรือ volume ของ Railway = ข้อมูล MySQL หาย — สำรองก่อน (`mysqldump` ผ่าน `railway run`)

## 1. ติดตั้ง packages

```bash
sudo apt update
sudo apt install -y nginx mariadb-server git unzip curl \
  php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  certbot
```

- ตรวจสอบ socket ของ PHP-FPM จริงก่อนใช้ config: `ls /run/php/`
- Ubuntu 24.04 ใช้ `php8.3-fpm.sock`; ถ้าใช้ PHP 8.2 ให้แก้ path ใน config
- ต้องมี Composer 2 และ Node 20+ สำหรับ build frontend (ติดตั้งตามเอกสารทางการ)

## 2. วางโค้ดและตั้งค่า backend

```bash
sudo mkdir -p /var/www
sudo git clone https://github.com/Littlebluebies/team-finder.git /var/www/team-finder
cd /var/www/team-finder/backend
sudo composer install --no-dev --optimize-autoloader
sudo cp config/db-local.example.php config/db-local.php
sudo nano config/db-local.php   # ใส่ dsn/username/password ของ production
```

สร้างฐานข้อมูลและ user แยก (อย่าใช้ `root`):

```bash
sudo mysql -e "CREATE DATABASE team_finder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'teamfinder'@'localhost' IDENTIFIED BY 'CHANGE_ME_STRONG';"
sudo mysql -e "GRANT ALL ON team_finder.* TO 'teamfinder'@'localhost'; FLUSH PRIVILEGES;"
sudo -u www-data php yii migrate --interactive=0
```

## 3. Build frontend

```bash
cd /var/www/team-finder/frontend
sudo npm ci
sudo npm run build
```

ค่าเริ่มต้น `VITE_API_URL=/api` จึงเรียก API ผ่าน origin เดียวกัน ไม่ต้องตั้ง `.env`
ถ้าแยกโดเมน API ต้องเปิด CORS ฝั่ง backend ซึ่งยังไม่มีในรอบนี้

## 4. สิทธิ์ไฟล์

```bash
sudo chown -R www-data:www-data \
  /var/www/team-finder/backend/runtime \
  /var/www/team-finder/backend/web/assets
sudo chown root:www-data /var/www/team-finder/backend/config/db-local.php
sudo chmod 640 /var/www/team-finder/backend/config/db-local.php
```

`backend/config/db-local.php` ต้องไม่อยู่ใน Git และไม่ควร readable by everyone

## 5. Nginx + HTTPS

1. แก้ `deploy/nginx.conf.example`: `server_name`, `root`, path ใต้ `fastcgi_param SCRIPT_FILENAME`, และ PHP socket
2. ขอ certificate ก่อนเปิด nginx (standalone ต้องใช้ port 80 ว่าง):

```bash
sudo systemctl stop nginx
sudo certbot certonly --standalone -d teamfinder.example.com
```

3. ติดตั้ง config แล้วเปิด service:

```bash
sudo cp /var/www/team-finder/deploy/nginx.conf.example /etc/nginx/sites-available/team-finder
sudo ln -s /etc/nginx/sites-available/team-finder /etc/nginx/sites-enabled/team-finder
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl start nginx
sudo systemctl reload php8.3-fpm
```

4. ต่ออายุ certificate อัตโนมัติ:

```bash
sudo certbot renew --dry-run
```

Config ตัวอย่างส่ง `YII_ENV=prod` และ `YII_DEBUG=0` ผ่าน FastCGI โดยตรง
จึงไม่ต้องแก้ `backend/web/index.php` ตอน deploy

## 6. ตรวจหลัง deploy

```bash
curl -i https://teamfinder.example.com/api/skills
curl -I http://teamfinder.example.com          # ต้องได้ 301 ไป https
curl -I https://teamfinder.example.com/me      # SPA fallback ต้องได้ 200
```

ทดสอบ flow จริงบนเบราว์เซอร์: สมัคร → สร้างโพสต์ → สมัครด้วยอีกบัญชี → accept → เห็น LINE ID เฉพาะ accepted

## 7. อัปเดตเวอร์ชัน

```bash
cd /var/www/team-finder
sudo git pull
cd backend && sudo composer install --no-dev --optimize-autoloader && sudo -u www-data php yii migrate --interactive=0
cd ../frontend && sudo npm ci && sudo npm run build
sudo systemctl reload php8.3-fpm nginx
```

ก่อน migrate ทุกครั้งให้ backup:

```bash
mysqldump -u teamfinder -p team_finder > team_finder-$(date +%F).sql
```

## Checklist ความปลอดภัย

- [ ] เปลี่ยนรหัส DB เป็นค่าสุ่มที่ยาว และไม่ commit `db-local.php`
- [ ] ลบ demo user `demo@teamfinder.local` ออกจากฐานข้อมูล production
- [ ] ตรวจว่า `YII_ENV=prod`, `YII_DEBUG=0` มีผล (error ไม่แสดง stack trace)
- [ ] จำกัดสิทธิ์ `db-local.php` เป็น `640` และเจ้าของเป็น `root:www-data`
- [ ] เปิด HTTPS และตรวจ `certbot renew --dry-run`
- [ ] ตั้ง backup ฐานข้อมูลรายวัน
- [ ] ยังไม่มี rate limit / email verification / password reset — อย่าเปิดสาธารณะก่อนเพิ่มถ้าจำเป็น
