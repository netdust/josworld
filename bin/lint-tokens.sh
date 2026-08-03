#!/usr/bin/env sh
# lint-tokens.sh — fail on layout literals that should be tokens.
#
# The payload is rendered per stack by scaffold_wp_starter (netdust-wp-manager):
# app/wp -> web/wp | app/wp, app/content -> web/app | app/content,
# {{SLUG*}} -> the site slug. A hardcoded `web/app` is correct-by-coincidence on a
# Bedrock render and BROKEN on a stackwp one — and nothing downstream notices until
# a stackwp scaffold's gate goes red. This lint is the pre-push catch.
#
# Usage (from the payload repo root):  sh gate/bin/lint-tokens.sh
# Exit 0 = clean, 1 = literals found (each printed as file:line:match).

set -eu

cd "$(dirname "$0")/../.." || exit 1

# Layout literals: <web|app>/<wp|app|content>. Lines already carrying a token are
# fine — that is the rendered form, e.g. `app/content/mu-plugins`.
matches=$(
    grep -rnE '\b(web|app)/(wp|app|content)\b' gate/ theme/ mu-plugins/ 2>/dev/null \
        | grep -v '{{GATE_' \
        | grep -v '{{SLUG' \
        | grep -v '\.phpstan-cache/' \
        || true
)

if [ -n "$matches" ]; then
    echo "FAIL: untokenized layout literals in the payload — these break stackwp renders:" >&2
    echo "$matches" >&2
    echo "" >&2
    echo "Fix: replace the layout path with app/wp / app/content," >&2
    echo "or, if the line is genuinely stack-neutral prose, reword it." >&2
    exit 1
fi

echo "token lint: clean — no untokenized layout literals"
exit 0
