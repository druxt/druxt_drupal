#!/usr/bin/env bash
##
# Stop the Cloudflare quick tunnel and clean up stale state.
#
# Pairs with scripts/start-cloudflared.sh. Runs during `make stop` via the
# stop- custom script hook.

set -eu

PID_FILE=.logs/cloudflared.pid

if [ -f "$PID_FILE" ]; then
  PID="$(cat "$PID_FILE" 2>/dev/null || true)"
  if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
    kill "$PID" 2>/dev/null || true
    echo "[cloudflared] Tunnel stopped (PID ${PID})."
  fi
  rm -f "$PID_FILE"
fi

# Remove TUNNEL_URL so the next `make start` gets a fresh tunnel and
# `make drush`/`make login` don't point at a dead URL.
if [ -f .env ]; then
  sed -i '/^TUNNEL_URL=/d' .env
fi
