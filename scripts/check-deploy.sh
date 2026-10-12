#!/usr/bin/env bash
# Verify a deployed iERA App: ./scripts/check-deploy.sh https://ieraca.wees.bio
set -u
URL="${1:-}"; [ -z "$URL" ] && { echo "Usage: $0 https://your.subdomain"; exit 1; }
URL="${URL%/}"; ok=0; bad=0
pass() { echo "  ok    $1"; ok=$((ok+1)); }
fail() { echo "  FAIL  $1"; bad=$((bad+1)); }
code() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$@"; }

echo "Checking $URL"
c=$(code "$URL/api/health"); body=$(curl -s --max-time 20 "$URL/api/health")
[ "$c" = "200" ] && echo "$body" | grep -q '"db":"ok"' && pass "health endpoint reports database ok" || fail "health endpoint ($c): $body"
c=$(code "$URL/login"); [ "$c" = "200" ] && pass "login page loads" || fail "login page returned $c"
c=$(code "$URL/assets/css/app.css"); [ "$c" = "200" ] && pass "static assets served" || fail "assets returned $c (document root must be the public/ folder)"
c=$(code "$URL/dashboard"); [ "$c" = "302" ] && pass "protected page redirects to sign-in" || fail "/dashboard returned $c (mod_rewrite / .htaccess problem?)"
c=$(code "$URL/config/config.php"); { [ "$c" = "404" ] || [ "$c" = "403" ]; } && pass "config is not web reachable" || fail "config/config.php reachable ($c) - document root is wrong!"
c=$(code "$URL/storage/logs/app.log"); { [ "$c" = "404" ] || [ "$c" = "403" ]; } && pass "storage is not web reachable" || fail "storage reachable ($c) - document root is wrong!"
c=$(code "$URL/"); { [ "$c" = "302" ] || [ "$c" = "200" ]; } && ! curl -s --max-time 20 "$URL/" | grep -q "Index of /" && pass "no directory listing at the root" || fail "root shows a directory listing or returned $c"
h=$(curl -s -I --max-time 20 "$URL/login")
echo "$h" | grep -qi "strict-transport-security" && pass "HSTS header present (mod_headers on)" || fail "security headers missing (mod_headers off?)"
echo "$h" | grep -qi "x-frame-options" && pass "frame protection header present" || fail "X-Frame-Options missing"
case "$URL" in https://*) http="${URL/https:/http:}"; c=$(code "$http/login"); [ "$c" = "301" ] && pass "HTTP redirects to HTTPS" || fail "HTTP returned $c instead of 301";; esac
echo; echo "$ok passed, $bad failed"
[ "$bad" -eq 0 ]
