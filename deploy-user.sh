#!/bin/bash
# User 앱 배포: rsync로 원자적 업데이트 (rm -rf 없이)
set -e

SSH_OPTS="-o StrictHostKeyChecking=no -o PubkeyAuthentication=no -o PreferredAuthentications=password"
REMOTE="root@49.247.173.220"
REMOTE_PATH="/var/www/slowok/slowok_back/public/user/"

echo "[1/3] rsync 전송..."
SSHPASS=$'km09as13!' sshpass -e rsync -avz --delete \
  -e "ssh $SSH_OPTS" \
  /Users/scoop/slowok/slowok_back/public/user/ \
  "$REMOTE:$REMOTE_PATH" 2>&1 | tail -3

echo "[2/3] 권한 설정..."
SSHPASS=$'km09as13!' sshpass -e ssh $SSH_OPTS "$REMOTE" \
  'chown -R www-data:www-data /var/www/slowok/slowok_back/public/user'

echo "[3/3] 확인..."
SSHPASS=$'km09as13!' sshpass -e ssh $SSH_OPTS "$REMOTE" \
  'ls /var/www/slowok/slowok_back/public/user/images/turtle.png && echo "turtle OK" && head -1 /var/www/slowok/slowok_back/public/user/user.html'

echo "배포 완료!"
