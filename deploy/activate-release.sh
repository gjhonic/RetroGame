#!/usr/bin/env bash
#
# Запускается НА VPS через `ssh ... bash -s < activate-release.sh` из
# GitHub Actions (.github/workflows/ci.yml, job deploy) уже после того, как
# rsync выложил собранный релиз в $DEPLOY_PATH/releases/$RELEASE.
#
# Ожидает переменные окружения: DEPLOY_PATH, RELEASE (обе пробрасываются
# через `ssh user@host "DEPLOY_PATH=... RELEASE=... bash -s" < activate-release.sh`).
#
# Схема каталогов на сервере:
#   $DEPLOY_PATH/releases/<sha>/   — очередной релиз (код + собранный фронтенд)
#   $DEPLOY_PATH/shared/           — то, что переживает релизы: .env.local, логи, аплоады
#   $DEPLOY_PATH/current           — симлинк на активный релиз (на него смотрит Nginx)

set -euo pipefail

: "${DEPLOY_PATH:?DEPLOY_PATH не задан}"
: "${RELEASE:?RELEASE не задан}"

RELEASE_DIR="$DEPLOY_PATH/releases/$RELEASE"
SHARED_DIR="$DEPLOY_PATH/shared"
KEEP_RELEASES=5

if [ ! -d "$RELEASE_DIR" ]; then
    echo "Релиз $RELEASE_DIR не найден — rsync отработал раньше этого шага?" >&2
    exit 1
fi

# --- Общие для всех релизов данные: секреты и то, что должно переживать деплой ---
mkdir -p "$SHARED_DIR/var/log" "$SHARED_DIR/public/uploads"

if [ ! -f "$SHARED_DIR/.env.local" ]; then
    echo "Нет $SHARED_DIR/.env.local — заведите его вручную перед первым деплоем (см. docs/DEPLOY.md)." >&2
    exit 1
fi

ln -sfn "$SHARED_DIR/.env.local" "$RELEASE_DIR/.env.local"
ln -sfn "$SHARED_DIR/var/log" "$RELEASE_DIR/var/log"
rm -rf "$RELEASE_DIR/public/uploads"
ln -sfn "$SHARED_DIR/public/uploads" "$RELEASE_DIR/public/uploads"

# --- Прод-приготовления ---
cd "$RELEASE_DIR"

php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# --- Атомарное переключение ---
ln -sfn "$RELEASE_DIR" "$DEPLOY_PATH/current"

# Требует sudoers-правила NOPASSWD только на этот конкретный reload (см. docs/DEPLOY.md).
sudo systemctl reload php8.4-fpm

# --- Уборка: оставляем последние $KEEP_RELEASES релизов ---
cd "$DEPLOY_PATH/releases"
ls -1t | tail -n "+$((KEEP_RELEASES + 1))" | xargs -r rm -rf

echo "Релиз $RELEASE активирован."
