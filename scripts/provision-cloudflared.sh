#!/usr/bin/env bash
##
# Configure Drupal to accept requests via the Cloudflare tunnel.
#
# Runs after `make provision` installs Drupal (creating settings.php), so
# the settings.local.php include can be uncommented. Pairs with
# scripts/start-cloudflared.sh which starts the tunnel during `make start`.

set -eu

case "${CLOUDFLARE_TUNNEL:-}" in
  ""|0|false|no)
    exit 0
    ;;
esac

SETTINGS_PHP="build/web/sites/default/settings.php"

if [ ! -f "$SETTINGS_PHP" ]; then
  echo "[cloudflared] settings.php not found; skipping Drupal config."
  exit 0
fi

# Uncomment the settings.local.php include if it is still commented out.
php -r '
$file = "build/web/sites/default/settings.php";
$content = file_get_contents($file);
$search = "# if (file_exists(\$app_root . \x27/\x27 . \$site_path . \x27/settings.local.php\x27)) {\n#   include \$app_root . \x27/\x27 . \$site_path . \x27/settings.local.php\x27;\n# }";
$replace = "if (file_exists(\$app_root . \x27/\x27 . \$site_path . \x27/settings.local.php\x27)) {\n  include \$app_root . \x27/\x27 . \$site_path . \x27/settings.local.php\x27;\n}";
file_put_contents($file, str_replace($search, $replace, $content));
'

cat > build/web/sites/default/settings.local.php <<'PHP'
<?php

$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['127.0.0.1', '::1'];
$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^\[?::1\]?$',
  '^[a-z0-9-]+\.trycloudflare\.com$',
];
PHP

echo "[cloudflared] Drupal trusted-host and reverse-proxy settings configured."
