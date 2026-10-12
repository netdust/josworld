#!/bin/bash
# Go-live for jos.agency, run ON jos-server as josagency. The production site is built next to the live webroot
# (www-next); www/ is Combell's placeholder until `swap`.
#
#   bash go-live.sh prepare   # safe any time: fresh staging dump -> production DB, code and uploads delta, users
#   bash go-live.sh swap      # the switch: www -> www-placeholder, www-next -> www, rewrite flush, cron on
#   bash go-live.sh mail      # AFTER FluentSMTP has a real connection: take the mail block out
#   bash go-live.sh rollback  # put the placeholder back
#
# Order on the day: freeze staging -> prepare -> look at the diff output -> swap -> verify -> mail.
set -euo pipefail

BASE=/data/sites/web/josagency
S=$BASE/subsites/staging.jos.agency
N=$BASE/www-next
LIVE=$BASE/www
OLD=$BASE/www-placeholder
DUMP=$BASE/tmp/staging-dump.sql
BLOCK=content/mu-plugins/00-block-outgoing-mail.php

wp_next() { ( cd "$N" && wp --path=wp "$@" ); }

prepare() {
  [ -d "$N" ] || { echo "$N is missing"; exit 1; }

  echo "== code and uploads: staging -> www-next (the .env, the Matomo config and the mail block stay)"
  rsync -a --delete \
    --exclude=/uploads/matomo/config --exclude=/uploads/matomo/tmp \
    --exclude=/cache --exclude=/debug.log --exclude=/logs \
    --exclude=/upgrade --exclude=/upgrade-temp-backup \
    --exclude=00-block-outgoing-mail.php \
    "$S"/content/ "$N"/content/
  rsync -a "$S"/composer.json "$S"/composer.lock "$N"/

  echo "== database: fresh staging dump -> production"
  ( cd "$S" && wp --path=wp db export "$DUMP" --default-character-set=utf8mb4 )
  wp_next db import "$DUMP"
  wp_next search-replace 'https://staging.jos.agency' 'https://jos.agency' --all-tables-with-prefix --skip-columns=guid --report-changed-only
  wp_next search-replace 'staging.jos.agency' 'jos.agency' --all-tables-with-prefix --skip-columns=guid --report-changed-only
  wp_next option update blog_public 1

  echo "== users: jw-build-bot goes (its content moves to admin); admin stays"
  if wp_next user get jw-build-bot --field=ID >/dev/null 2>&1; then
    wp_next user delete jw-build-bot --reassign="$(wp_next user get admin --field=ID)" --yes
  fi
  wp_next user list --fields=ID,user_login,roles --format=table

  echo "== caches and rewrite rules"
  wp_next cache flush || true
  wp_next rewrite flush --hard
  wp_next option get home
  echo "prepared"
}

swap() {
  [ -d "$N" ] || { echo "$N is missing"; exit 1; }
  [ ! -e "$OLD" ] || { echo "$OLD already exists — refusing"; exit 1; }
  grep -q '^DB_NAME=ID247703_jos$' "$N/.env" || { echo "www-next/.env is not the production DB"; exit 1; }
  wp_next option get home | grep -qx 'https://jos.agency' || { echo "home is not https://jos.agency"; exit 1; }

  mv "$LIVE" "$OLD"
  mv "$N" "$LIVE"
  sed -i '/^DISABLE_WP_CRON=/d' "$LIVE/.env"
  ( cd "$LIVE" && wp --path=wp rewrite flush --hard )
  echo "live. Cron is on: add the system cron for wp-cron.php (Combell), then verify and run 'mail'."
}

mail() {
  [ -f "$LIVE/$BLOCK" ] || { echo "the mail block is already gone"; exit 0; }
  rm "$LIVE/$BLOCK"
  echo "mail block removed: send a real test mail now"
}

rollback() {
  [ -d "$OLD" ] || { echo "$OLD is missing"; exit 1; }
  mv "$LIVE" "$N"
  mv "$OLD" "$LIVE"
  echo "placeholder is live again; the built site is back at $N"
}

case "${1:-}" in
  prepare) prepare ;;
  swap) swap ;;
  mail) mail ;;
  rollback) rollback ;;
  *) sed -n 2,12p "$0"; exit 1 ;;
esac
