#!/bin/sh
# E2E gate: host-side Playwright against the live DDEV URL (decision 7).
# Single source for URL derivation + fixture seeding; called by BOTH
# `composer test:e2e` and the `ddev test-e2e` host command.
#
# URL derivation (SC-5 — no hardcoded project URL outside .ddev/config.yaml):
#   1. WP_BASE_URL already exported          (caller override / CI)
#   2. DDEV_PRIMARY_URL                      (set by ddev for host commands)
#   3. `ddev exec printenv DDEV_PRIMARY_URL` (plain `composer test:e2e` on the host)
#   else: fail loudly. Never a hardcoded fallback.
set -eu

if [ -z "${WP_BASE_URL:-}" ]; then
  if [ -n "${DDEV_PRIMARY_URL:-}" ]; then
    WP_BASE_URL=$DDEV_PRIMARY_URL
  elif command -v ddev >/dev/null 2>&1; then
    WP_BASE_URL=$(ddev exec printenv DDEV_PRIMARY_URL | tr -d '\r')
  fi
fi
[ -n "${WP_BASE_URL:-}" ] || {
  echo "e2e: cannot derive WP_BASE_URL — set it, or run via the two blessed invocations:" >&2
  echo "e2e:   \`composer test:e2e\` (host, DDEV project running) or \`ddev test-e2e\`" >&2
  exit 1
}
export WP_BASE_URL

# --- Fixtures: seeded via wp-cli BEFORE playwright runs, never through the UI.
# The e2e credential is single-sourced HERE (review C4 I4): generated per run
# unless the caller provides one, seeded create-or-update (no silent drift),
# and handed to the spec via the environment. No password literal anywhere.
E2E_PASS="${E2E_PASS:-$(head -c16 /dev/urandom | od -An -tx1 | tr -d ' \n')}"
export E2E_PASS

if command -v ddev >/dev/null 2>&1; then
  # Test admin: create, or converge the password on the existing user.
  ddev exec wp user create e2e-admin e2e-admin@example.test \
    --role=administrator --user_pass="$E2E_PASS" >/dev/null 2>&1 \
    || ddev exec wp user update e2e-admin --user_pass="$E2E_PASS" >/dev/null

  # Fixture post (slug pinned so the seeded permalink is deterministic)
  if [ -z "$(ddev exec wp post list --name=e2e-fixture-post --post_type=post --post_status=publish --field=ID)" ]; then
    ddev exec wp post create --post_title='E2E Fixture Post' --post_name=e2e-fixture-post \
      --post_content='Seeded by bin/e2e.sh via wp-cli.' --post_status=publish
  fi

  # Derive the fixture URL at seed time (review C4 I5) — the spec navigates
  # this, never a hardcoded path.
  E2E_FIXTURE_URL=$(ddev exec wp post list --name=e2e-fixture-post --field=url | tr -d '\r')
  export E2E_FIXTURE_URL
else
  # S7: WP_BASE_URL was provided but ddev is not on PATH — cannot seed.
  echo "e2e: ddev unavailable — running unseeded (fixtures must pre-exist;" >&2
  echo "e2e: provide E2E_PASS and E2E_FIXTURE_URL for the seeded fixtures)" >&2
fi

# S3: the spec + config are typechecked as an enforced preflight — the repo's
# tsconfig/typescript deps exist FOR this step.
npx tsc --noEmit

# Exit status of the suite is the exit status of the gate (set -e + last command)
npx playwright test "$@"
