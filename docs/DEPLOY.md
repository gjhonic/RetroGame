# Деплой на VPS

Схема: GitHub Actions при пуше в `main` (после зелёного `test`-джоба)
собирает прод-релиз (`composer install --no-dev`, `npm run build`) и
выкладывает его на сервер по SSH/rsync в отдельную папку, затем по SSH
запускает `deploy/activate-release.sh`, который прогоняет миграции и
атомарно переключает симлинк `current` на новый релиз. Старый код при
этом продолжает обслуживать запросы до самого момента переключения —
без даунтайма и с мгновенным откатом (просто вернуть симлинк назад).

Схема каталогов на сервере:

```
/var/www/retrogame/
├── releases/
│   ├── <sha1>/
│   └── <sha2>/          ← последние 5 релизов, старые чистит activate-release.sh
├── shared/
│   ├── .env.local        ← секреты, не в git
│   ├── var/log/
│   └── public/uploads/   ← обложки игр (GameImageDownloader)
└── current -> releases/<sha2>   ← на него смотрит Nginx
```

Ниже — настройка с нуля на чистом Ubuntu 24.04 (команды выполняются под
root или через `sudo`; замените `retrogame.example.com` и пароли на свои).

## 1. Пакеты: PHP 8.4, Postgres, Nginx

```bash
apt update && apt upgrade -y
apt install -y curl gnupg2 ca-certificates lsb-release apt-transport-https

# PHP 8.4 (Ubuntu 24.04 в стандартных репозиториях его ещё не имеет)
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-pgsql php8.4-intl php8.4-mbstring \
    php8.4-xml php8.4-curl php8.4-zip php8.4-opcache php8.4-bcmath

apt install -y postgresql postgresql-contrib nginx

curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

Node/npm на сервере **не нужен** — фронтенд собирается в GitHub Actions,
на сервер приезжает уже готовый `public/build/`.

## 2. PostgreSQL

```bash
sudo -u postgres psql -c "CREATE USER retrogame WITH PASSWORD 'сгенерируйте-длинный-пароль';"
sudo -u postgres psql -c "CREATE DATABASE retrogame OWNER retrogame;"
```

## 3. Пользователь деплоя и структура каталогов

```bash
adduser --disabled-password --gecos "" retrogame
mkdir -p /var/www/retrogame/{releases,shared/var/log,shared/public/uploads}
chown -R retrogame:retrogame /var/www/retrogame
```

Nginx и php-fpm обычно работают от `www-data` — добавьте его в группу
`retrogame`, чтобы читать файлы релиза:

```bash
usermod -aG retrogame www-data
chmod -R g+rX /var/www/retrogame
```

## 4. sudoers: reload php-fpm без пароля

`activate-release.sh` перезагружает php-fpm после каждого деплоя. Даём
пользователю `retrogame` право **только** на этот один reload, не полный sudo:

```bash
echo 'retrogame ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm' \
    > /etc/sudoers.d/retrogame-php-fpm-reload
visudo -c   # проверить синтаксис перед выходом
```

## 5. SSH-доступ для GitHub Actions

```bash
sudo -u retrogame ssh-keygen -t ed25519 -C "github-actions-deploy" -f /tmp/deploy_key -N ""
sudo -u retrogame mkdir -p /home/retrogame/.ssh
sudo -u retrogame sh -c 'cat /tmp/deploy_key.pub >> /home/retrogame/.ssh/authorized_keys'
chmod 600 /home/retrogame/.ssh/authorized_keys
cat /tmp/deploy_key   # это значение — в секрет DEPLOY_SSH_KEY (см. шаг 7), потом удалить файл
rm /tmp/deploy_key /tmp/deploy_key.pub
```

## 6. Nginx

```bash
cp deploy/nginx.conf /etc/nginx/sites-available/retrogame.conf
# отредактировать server_name на реальный домен
ln -s /etc/nginx/sites-available/retrogame.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

До первого деплоя `current` ещё не существует — `nginx -t` всё равно
пройдёт (symlink проверяется только при запросе), но сайт будет отдавать
502, пока не выполнится первый деплой (шаг 9).

## 7. Секреты приложения — `shared/.env.local`

```bash
sudo -u retrogame tee /var/www/retrogame/shared/.env.local > /dev/null <<'EOF'
APP_ENV=prod
APP_SECRET=сгенерируйте: php -r "echo bin2hex(random_bytes(16));"
DATABASE_URL="postgresql://retrogame:пароль-из-шага-2@127.0.0.1:5432/retrogame?serverVersion=16&charset=utf8"
ADMIN_EMAIL=admin@your-domain.tld
ADMIN_PASSWORD=длинный-случайный-пароль
STEAM_API_KEY=ключ-с-https://steamcommunity.com/dev/apikey
EOF
chmod 600 /var/www/retrogame/shared/.env.local
```

## 8. GitHub Secrets

В настройках репозитория → Settings → Secrets and variables → Actions
(при желании — внутри Environment `production`, тогда деплой можно
привязать к ручному подтверждению):

| Secret | Значение |
|---|---|
| `DEPLOY_SSH_KEY` | приватный ключ из шага 5 (`/tmp/deploy_key`, целиком, с `-----BEGIN...`) |
| `DEPLOY_HOST` | IP или домен сервера |
| `DEPLOY_USER` | `retrogame` |
| `DEPLOY_PATH` | `/var/www/retrogame` |

## 9. Первый деплой

Просто запушьте в `main` — джоб `deploy` в `.github/workflows/ci.yml`
соберёт релиз и выложит его. Проверить: `ssh retrogame@host "ls -la /var/www/retrogame/current"`
должен указывать на свежую директорию в `releases/`.

Если `activate-release.sh` падает на `doctrine:migrations:migrate` —
скорее всего, не совпадает `DATABASE_URL`/пароль из шага 7.

## 10. Первый администратор

```bash
ssh retrogame@host
cd /var/www/retrogame/current
php bin/console app:user:create-admin --env=prod
```

Без `--email`/`--password` возьмёт `ADMIN_EMAIL`/`ADMIN_PASSWORD` из
`shared/.env.local`. Команда идемпотентна — можно перевыполнить, если
захотите сменить пароль.

## 11. Периодические задачи

**Крон** (импорт игр из Steam) — `crontab -u retrogame -e`, взять строку
из [`deploy/crontab.example`](../deploy/crontab.example). Каждый запуск
виден в админке на `/admin/cron-runs`.

**Messenger-консьюмер** — пока не нужен (в приложении нет собственных
сообщений, см. комментарий в файле), но если понадобится:

```bash
cp deploy/retrogame-worker.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now retrogame-worker
```

После каждого релиза его нужно перезапускать (`systemctl restart
retrogame-worker`) — `activate-release.sh` пока делает это только для
php-fpm; если включите воркер, допишите туда `sudo systemctl restart
retrogame-worker` по аналогии (и не забудьте sudoers-правило на него, как в шаге 4).

## 12. HTTPS

```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d retrogame.example.com
```

Certbot сам допишет `server { listen 443 ssl; ... }` и редирект с 80 в
`/etc/nginx/sites-available/retrogame.conf`.

## Откат

```bash
ssh retrogame@host
ls /var/www/retrogame/releases          # выбрать предыдущий sha
ln -sfn /var/www/retrogame/releases/<предыдущий-sha> /var/www/retrogame/current
sudo systemctl reload php8.4-fpm
```

Миграции откатом симлинка **не откатываются** — если проблемный релиз
добавлял миграцию, которая уже применилась, откат кода не отменяет
изменения схемы. Для таких случаев есть `down()` в самой миграции
(`php bin/console doctrine:migrations:migrate prev --env=prod`), но
это отдельное осознанное действие, не часть быстрого отката.
