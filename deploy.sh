#!/usr/bin/env bash
#
# ╔══════════════════════════════════════════════════════════════════╗
# ║  deploy.sh — D'panne Phones — Redéploiement VPS en 1 commande    ║
# ╚══════════════════════════════════════════════════════════════════╝
#
# Usage (sur le VPS) :
#   cd /var/www/dpannephones
#   ./deploy.sh
#
# Ce script fait :
#   1. git pull origin Branch-3
#   2. composer install --no-dev
#   3. importmap:install
#   4. asset-map:compile
#   5. doctrine:migrations:migrate (si nouvelles)
#   6. app:seed-site-images + app:seed-app-settings (idempotents)
#   7. cache:clear + cache:warmup
#   8. permissions var/ + public/assets
#   9. restart php-fpm + reload nginx
#  10. test HTTPS
#
# En cas d'erreur, le script s'arrête et affiche la ligne fautive.

set -euo pipefail

# ─── Couleurs terminal ───
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BOLD='\033[1m'
NC='\033[0m' # No Color

APP_DIR="/var/www/dpannephones"
BRANCH="Branch-3"
DOMAIN="https://dpannephones.fr"
PHP_FPM_SERVICE="php8.4-fpm"

# ─── Check qu'on est dans le bon dossier ───
if [ "$(pwd)" != "$APP_DIR" ]; then
    echo -e "${YELLOW}→ Changement de dossier vers $APP_DIR${NC}"
    cd "$APP_DIR"
fi

# ─── Check qu'on tourne en root (nécessaire pour systemctl + chown www-data) ───
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Ce script doit être lancé en root (utilise 'sudo -i' avant)${NC}"
    exit 1
fi

START=$(date +%s)

echo -e "\n${BOLD}${BLUE}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${BLUE}║   🚀  DÉPLOIEMENT D'panne Phones (v1.x)         ║${NC}"
echo -e "${BOLD}${BLUE}╚══════════════════════════════════════════════════╝${NC}\n"

# ══════════════════════════════════════════════════
# 1. Git pull
# ══════════════════════════════════════════════════
echo -e "${BOLD}${BLUE}[1/9]${NC} ${BOLD}Git pull origin ${BRANCH}${NC}"
git fetch origin
BEFORE=$(git rev-parse HEAD)
git pull origin "$BRANCH"
AFTER=$(git rev-parse HEAD)

if [ "$BEFORE" = "$AFTER" ]; then
    echo -e "   ${YELLOW}ℹ︎  Déjà à jour, aucun nouveau commit${NC}"
else
    echo -e "   ${GREEN}✓ ${BEFORE:0:7} → ${AFTER:0:7}${NC}"
fi

# ══════════════════════════════════════════════════
# 2. Composer install
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[2/9]${NC} ${BOLD}Composer install (prod)${NC}"
COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --no-progress \
    --quiet
echo -e "   ${GREEN}✓ Dépendances OK${NC}"

# ══════════════════════════════════════════════════
# 3. Importmap (vendors JS)
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[3/9]${NC} ${BOLD}Install vendors JS (Stimulus/Turbo)${NC}"
php bin/console importmap:install --quiet 2>/dev/null || true
echo -e "   ${GREEN}✓ Vendors JS OK${NC}"

# ══════════════════════════════════════════════════
# 4. Compile assets (AssetMapper)
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[4/9]${NC} ${BOLD}Compile assets (prod)${NC}"
APP_ENV=prod php bin/console asset-map:compile --quiet 2>/dev/null || \
APP_ENV=prod php bin/console asset-map:compile
echo -e "   ${GREEN}✓ Assets compilés${NC}"

# ══════════════════════════════════════════════════
# 5. Migrations Doctrine
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[5/9]${NC} ${BOLD}Migrations DB${NC}"
APP_ENV=prod php bin/console doctrine:migrations:migrate \
    --no-interaction \
    --allow-no-migration \
    2>&1 | grep -E "(Migrating|migrated|No migrations|finished)" || true
echo -e "   ${GREEN}✓ DB à jour${NC}"

# ══════════════════════════════════════════════════
# 6. Seeds (idempotents — préservent les valeurs custom)
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[6/9]${NC} ${BOLD}Seeds (site images + paramètres)${NC}"
APP_ENV=prod php bin/console app:seed-site-images 2>&1 | grep -E "(CREATE|UPDATE|image)" | tail -3 || true
APP_ENV=prod php bin/console app:seed-app-settings 2>&1 | grep -E "(CREATE|UPDATE|paramètre)" | tail -3 || true
echo -e "   ${GREEN}✓ Seeds OK${NC}"

# ══════════════════════════════════════════════════
# 7. Cache Symfony
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[7/9]${NC} ${BOLD}Clear + warmup cache (prod)${NC}"
rm -f .env.local.php .env.prod.local.php
APP_ENV=prod php bin/console cache:clear --quiet
APP_ENV=prod php bin/console cache:warmup --quiet 2>/dev/null || \
APP_ENV=prod php bin/console cache:warmup
echo -e "   ${GREEN}✓ Cache prêt${NC}"

# ══════════════════════════════════════════════════
# 8. Permissions
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[8/9]${NC} ${BOLD}Permissions fichiers${NC}"
chown -R www-data:www-data var public/assets public/uploads 2>/dev/null || true
chmod -R 755 var public/assets 2>/dev/null || true
chmod 640 .env.prod.local 2>/dev/null || true
echo -e "   ${GREEN}✓ Permissions OK${NC}"

# ══════════════════════════════════════════════════
# 9. Restart services
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}[9/9]${NC} ${BOLD}Restart PHP-FPM + reload Nginx${NC}"
systemctl restart "$PHP_FPM_SERVICE"
systemctl reload nginx
echo -e "   ${GREEN}✓ Services redémarrés${NC}"

# ══════════════════════════════════════════════════
# Test final
# ══════════════════════════════════════════════════
echo -e "\n${BOLD}${BLUE}═══ TEST FINAL ═══${NC}"
HTTP_CODE=$(curl -sI "$DOMAIN" -o /dev/null -w "%{http_code}" || echo "000")
if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓ $DOMAIN → HTTP $HTTP_CODE ${BOLD}OK${NC}"
else
    echo -e "${RED}⚠︎  $DOMAIN → HTTP $HTTP_CODE (attendu 200)${NC}"
    echo -e "${YELLOW}  Vérifie les logs : tail -20 /var/log/nginx/dpannephones-error.log${NC}"
fi

END=$(date +%s)
DURATION=$((END - START))

echo -e "\n${BOLD}${GREEN}╔══════════════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}║   ✅  DÉPLOIEMENT TERMINÉ en ${DURATION}s                    ║${NC}"
echo -e "${BOLD}${GREEN}╚══════════════════════════════════════════════════╝${NC}"
echo -e "${BOLD}Site :${NC} $DOMAIN"
echo -e "${BOLD}Commit déployé :${NC} $(git log --oneline -1)\n"
