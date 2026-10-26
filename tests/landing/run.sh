#!/usr/bin/env bash
# Тесты лендинга на синтетике: отдельная временная база, локальный сервер, ничего не уходит наружу.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
TMP="$(mktemp -d)"; export GF_DB_PATH="$TMP/test.sqlite"
PORT=8791
cleanup() { [ -n "${PID:-}" ] && kill "$PID" 2>/dev/null || true; rm -rf "$TMP"; }
trap cleanup EXIT

python3 -I "$ROOT/scripts/analytics/validate_utm.py" "$ROOT/docs/analytics/marketing/data/CAMPAIGN_MATRIX.csv"
php "$ROOT/tests/landing/test_utm.php"

# Синтетическая короткая ссылка M01 во временной базе.
CODE=$(php -r '
  $_SERVER["SCRIPT_FILENAME"] = "'"$ROOT"'/go-flow/index.php"; $_SERVER["SCRIPT_NAME"] = "/go-flow/index.php";
  require "'"$ROOT"'/go-flow/lib/bootstrap.php";
  $c = link_code();
  db()->prepare("INSERT INTO links (code, label, utm_source, utm_medium, utm_campaign, utm_content, utm_adgroup, utm_creative) VALUES (?,?,?,?,?,?,?,?)")
      ->execute([$c, "test M01", "telegram", "paid_social", "2026q4_specialists_java_launch", "post_a", "tgch_java_a", "post_a"]);
  echo $c;')
php -S 127.0.0.1:$PORT -t "$ROOT" "$ROOT/tools/dev-router.php" >"$TMP/server.log" 2>&1 & PID=$!
for i in $(seq 1 30); do curl -s -o /dev/null "http://127.0.0.1:$PORT/go-flow/" && break; sleep 0.2; done
GF_BASE="http://127.0.0.1:$PORT/go-flow" GF_LINK_CODE="$CODE" php "$ROOT/tests/landing/test_flow.php"
echo "Все тесты лендинга прошли."
