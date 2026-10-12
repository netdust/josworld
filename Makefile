# josworld — project Makefile.
#
# This file is YOURS. `make devops-update` never touches it.
# Everything else (Makefile.netdust, mk/, scripts/) is vendored from the
# netdust-devops plugin — edit those upstream, not here.
#
# Run `make` for the verb list.

STACK := wp

# The core refuses any command-line variable it does not know. A target below
# that takes its own input declares it here, before the include, or the core
# never sees the declaration: `_CLI_EXTRA := NAME`.
include Makefile.netdust

# ── Project-specific targets below ───────────────────────────────────────────
# Add anything this project needs that the shared core does not have. To
# override a shared recipe, define it here — this file is read after the
# include, so your definition wins. Make prints an "overriding recipe" warning
# when it does; that warning is expected and means the override took.

# ── The payload backup reads the remote tree under structure.webroot ─────────
# `_backup-payload` does `cd $REMOTE/$(WEBROOT)`: on a stack whose environment directory IS the web root
# (rsync, custom-app: the payload lands at <env path>/content/…), there is no `app/` on the server, so
# a production ship stopped at "No such file or directory" before deploying anything. The deploy and the
# rollback address the payload at <env path>/<payload path>; the backup does the same here. To go
# upstream into netdust-devops, then drop this line after `make devops-update`.
_backup-payload: WEBROOT := .
