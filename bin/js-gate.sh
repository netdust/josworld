#!/bin/sh
# Run npm commands in every non-vendored theme package.
# Usage: js-gate.sh <label> <npm-args>...   e.g.  js-gate.sh lint:js "run lint" "run lint:css"
set -eu
label=$1; shift
n=0
for f in app/content/themes/*/package.json; do
  [ -f "$f" ] || continue
  d=${f%/package.json}
  [ "$d" = app/content/themes/twentytwentyfive ] && continue
  for cmd in "$@"; do
    # shellcheck disable=SC2086 -- word-splitting IS the argument format
    npm $cmd --prefix "$d"
  done
  n=$((n+1))
done
[ "$n" -gt 0 ] || { echo "$label: no theme package.json found — gate did not run" >&2; exit 1; }
echo "$label: ran $n package(s)"
