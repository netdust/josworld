#!/bin/bash
# Go-live for jos.agency, run ON jos-server as josagency. The production site is built next to the live webroot
# (www-next); www/ is Combell's placeholder until `swap`.
#
#   bash go-live.sh prepare   # BEFORE the swap only: fresh staging dump -> production DB (backed up first), code and
#                             # uploads delta, users, Matomo settings. Refuses once the site has been swapped in.
#   bash go-live.sh swap      # the switch: www -> www-placeholder, www-next -> www, rewrite flush, cron on. Refuses while
#                             # the copied database holds mail that would go to real people (FORCE=1 to override)
#   bash go-live.sh mail      # AFTER FluentSMTP has a real connection: drops the (inert) mail-block file
#   bash go-live.sh rollback  # put the placeholder back
#
# The mail block does NOT protect production: it switches itself off on the production host, which www-next
# already is (WP_HOME=https://jos.agency). The safety is the check in `swap`, and an empty CRM queue.
# Order on the day: freeze staging -> prepare -> look at the output -> swap -> verify -> SMTP -> mail.
set -euo pipefail

BASE=/data/sites/web/josagency
S=$BASE/subsites/staging.jos.agency
N=$BASE/www-next
LIVE=$BASE/www
OLD=$BASE/www-placeholder
DUMP=$BASE/tmp/staging-dump.sql
BLOCK=content/mu-plugins/00-block-outgoing-mail.php
SWAPPED=$BASE/tmp/go-live-swapped
BACKUPS=$BASE/tmp/backups
MATOMO=$BASE/tmp/matomo-go-live.php

wp_next() { ( cd "$N" && wp --path=wp "$@" ); }

prepare() {
  [ -d "$N" ] || { echo "$N is missing"; exit 1; }
  if [ -e "$SWAPPED" ] || [ -e "$OLD" ]; then
    [ "${FORCE_PREPARE:-}" = 1 ] || { echo "the site has been live: prepare would overwrite the production database with staging's. FORCE_PREPARE=1 to do it anyway"; exit 1; }
  fi

  echo "== code and uploads: staging -> www-next (the .env, the Matomo config and the mail block stay)"
  rsync -a --delete \
    --exclude=/uploads/matomo/config --exclude=/uploads/matomo/tmp --exclude=/plugins/matomo.wpload_dir.php \
    --exclude=/cache --exclude=/debug.log --exclude=/logs \
    --exclude=/upgrade --exclude=/upgrade-temp-backup \
    --exclude=00-block-outgoing-mail.php \
    "$S"/content/ "$N"/content/
  rsync -a "$S"/composer.json "$S"/composer.lock "$N"/

  echo "== database: fresh staging dump -> production (production backed up first)"
  mkdir -p "$BACKUPS"
  wp_next db export "$BACKUPS/production-before-prepare-$(date +%Y%m%d-%H%M%S).sql" --default-character-set=utf8mb4
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

  echo "== Matomo: the import brought staging's settings (tracking off, no goals); set production's"
  if [ -f "$MATOMO" ]; then wp_next --user=1 eval-file "$MATOMO" apply; else echo "MISSING $MATOMO: copy scripts/once/2026-10-12-matomo-go-live.php there and run it with 'apply'"; fi

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
  [ -f "$N/content/plugins/matomo.wpload_dir.php" ] || { echo "content/plugins/matomo.wpload_dir.php is missing: the tracker would record nothing"; exit 1; }

  echo "== mail that would go out the moment cron runs"
  queued=$(wp_next --user=1 eval 'global $wpdb; $p = $wpdb->prefix; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}fc_campaign_emails WHERE status IN (\"pending\",\"scheduled\")") + (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}fcal_bookings WHERE start_time > NOW() AND status = \"scheduled\"") + (int) $wpdb->get_var("SELECT COUNT(*) FROM {$p}fc_subscribers");')
  echo "queued campaign mails + upcoming bookings + CRM contacts: $queued"
  if [ "$queued" != "0" ] && [ "${FORCE:-}" != 1 ]; then echo "refusing: the copied database holds people and mail. Empty them, or FORCE=1"; exit 1; fi

  mv "$LIVE" "$OLD"
  mv "$N" "$LIVE"
  touch "$SWAPPED"
  sed -i '/^DISABLE_WP_CRON=/d' "$LIVE/.env"
  ( cd "$LIVE" && wp --path=wp rewrite flush --hard )
  echo "live. Cron is on: add the system cron for wp-cron.php (Combell), then verify and run 'mail'."
}

mail() {
  ( cd "$LIVE" && wp --path=wp --user=1 eval 'echo is_array($o = get_option("fluentmail-settings")) && ! empty($o["connections"]) ? "FluentSMTP: connection set" : "FluentSMTP: NO CONNECTION: mail leaves through plain PHP mail()", "\n";' )
  [ -f "$LIVE/$BLOCK" ] && rm "$LIVE/$BLOCK" && echo "the (inert) mail-block file is removed"
  echo "send a real test mail now"
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
