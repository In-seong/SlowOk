#!/bin/bash
# SlowOK 배포 스크립트
# 사용법: ./deploy.sh [server_user@server_ip]

set -e

SERVER=${1:-"user@server"}
REMOTE_PATH="/var/www/slowok"
LOCAL_BACK="../slowok_back"
LOCAL_FRONT="../slowok_front"

echo "=== SlowOK 배포 시작 ==="

# 1. 프론트엔드 빌드
echo "[1/5] 프론트엔드 빌드..."
cd "$LOCAL_FRONT"
npm run build:user
npm run build:admin
cd -

# 2. 서버 디렉토리 생성
echo "[2/5] 서버 디렉토리 준비..."
ssh "$SERVER" "sudo mkdir -p $REMOTE_PATH && sudo chown -R \$(whoami):\$(whoami) $REMOTE_PATH"

# 3. 파일 전송 (빌드된 프론트 포함)
echo "[3/5] 파일 전송..."
rsync -avz --delete \
  --exclude='.env' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='storage/logs/*.log' \
  --exclude='storage/framework/cache/data/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  --exclude='.git' \
  --exclude='database/database.sqlite' \
  "$LOCAL_BACK/" "$SERVER:$REMOTE_PATH/slowok_back/"

# 4. 서버에서 설정
echo "[4/5] 서버 설정..."
ssh "$SERVER" << 'REMOTE_CMD'
cd /var/www/slowok/slowok_back

# Composer 의존성 설치
composer install --no-dev --optimize-autoloader --no-interaction

# .env 없으면 복사 안내
if [ ! -f .env ]; then
    echo "⚠️  .env 파일이 없습니다. deploy/.env.production을 참고하여 .env를 생성하세요."
    echo "   cp deploy/.env.production .env"
    echo "   php artisan key:generate"
fi

# 마이그레이션
php artisan migrate --force

# 캐시 최적화
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 스토리지 링크
php artisan storage:link 2>/dev/null || true

# 권한 설정
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

REMOTE_CMD

# 5. Nginx 재시작
echo "[5/5] Nginx 재시작..."
ssh "$SERVER" "sudo nginx -t && sudo systemctl reload nginx"

echo "=== 배포 완료 ==="
echo "  User:  https://slowokuser.revuplan.com"
echo "  Admin: https://slowokadmin.revuplan.com"
