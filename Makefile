# WordPress Custom App Makefile — Clean, Secure, Vite + Templates
# DEPLOYMENT: Git bundles (app/ → server root)
# SYNC: app/content/ ↔ /content/
#
# Version: 4.0

# ═══════════════════════════════════════════════════════════════════════════
#                          PROJECT CONFIGURATION
# ═══════════════════════════════════════════════════════════════════════════

# Defaults to the directory name (matches DDEV's default project naming);
# override with `make PROJECT_NAME=foo …` or set it here.
PROJECT_NAME ?= $(notdir $(CURDIR))
STAGING_HOST := #
STAGING_PATH := #
STAGING_URL := #
PRODUCTION_HOST := # Configure when ready
PRODUCTION_PATH := # Configure when ready
PRODUCTION_URL := # Configure when ready

# ═══════════════════════════════════════════════════════════════════════════
#                          FOLDER STRUCTURE (Custom)
# ═══════════════════════════════════════════════════════════════════════════

APP_DIR := app
CONTENT_LOCAL := $(APP_DIR)/content
CONTENT_REMOTE := content

UPLOADS_LOCAL := $(CONTENT_LOCAL)/uploads
UPLOADS_REMOTE := $(CONTENT_REMOTE)/uploads

PLUGINS_LOCAL := $(CONTENT_LOCAL)/plugins
PLUGINS_REMOTE := $(CONTENT_REMOTE)/plugins

THEMES_LOCAL := $(CONTENT_LOCAL)/themes
THEMES_REMOTE := $(CONTENT_REMOTE)/themes

MU_PLUGINS_LOCAL := $(CONTENT_LOCAL)/mu-plugins
MU_PLUGINS_REMOTE := $(CONTENT_REMOTE)/mu-plugins

# Vite theme slug — the theme owning the Vite build under $(THEMES_LOCAL).
# Defaults to the project name; override if your theme folder differs.
VITE_THEME ?= $(PROJECT_NAME)
VITE_PATH := $(THEMES_LOCAL)/$(VITE_THEME)

# ═══════════════════════════════════════════════════════════════════════════
#                          LOCAL DEVELOPMENT (DDEV)
# ═══════════════════════════════════════════════════════════════════════════

LOCAL_URL := https://$(PROJECT_NAME).ddev.site
# Root .env is the canonical local env file (see README + the wp-config
# load chain: app/.env first, root .env second and winning locally).
ENV_FILE := .env
ENV_EXAMPLE := .env.example

# ═══════════════════════════════════════════════════════════════════════════
#                          STORAGE & TEMPLATES
# ═══════════════════════════════════════════════════════════════════════════

BACKUP_DIR := backups
TEMP_DIR := /tmp
TEMPLATE_DIR := $(HOME)/.wordpress-templates
GITHUB_USER := netdust
TEMPLATE_PREFIX := template-

# ═══════════════════════════════════════════════════════════════════════════
#                          INTERNAL
# ═══════════════════════════════════════════════════════════════════════════

RED := \033[31m
GREEN := \033[32m
YELLOW := \033[33m
BLUE := \033[34m
RESET := \033[0m

export PATH := $(PATH):/usr/bin:/bin

# === HELP ===
.PHONY: help setup setup-staging setup-production dev save deploy ship \
        feature finish db-clean sync-to-local sync-to-staging \
        template-save template-load template-list status backup rollback ssh
.PHONY: _create-bundle _deploy-bundle _check-requirements _check-ddev \
        _check-staging-config _check-production-config _verify-ssh-access \
        _git-setup _ensure-safe-branch _ensure-clean-git _confirm \
        _init-remote-git _backup-production
# Help is auto-generated from the `## ` comments on public targets:
# every `target: ## description` line below shows up in `make help`.
help: ## Show this help
	@echo "$(BLUE)╔════════════════════════════════════════════════════╗$(RESET)"
	@echo "$(BLUE)║  $(PROJECT_NAME) - Custom App Structure$(RESET)"
	@echo "$(BLUE)╚════════════════════════════════════════════════════╝$(RESET)"
	@echo ""
	@echo "$(GREEN)Branch:$(RESET) $$(git branch --show-current 2>/dev/null || echo 'none')"
	@echo "$(GREEN)DDEV:$(RESET) $$(if ddev describe >/dev/null 2>&1; then echo 'running'; else echo 'not installed'; fi)"
	@echo ""
	@grep -hE '^[a-zA-Z][a-zA-Z0-9_-]*:.*## ' $(MAKEFILE_LIST) | \
	   awk 'BEGIN {FS = ":.*## "} {sub(/\r$$/, "", $$2); printf "  $(GREEN)%-20s$(RESET) %s\n", $$1, $$2}'
	@echo ""
	@echo "$(BLUE)Quickstart:$(RESET) make setup && make dev"

# === SETUP ===
setup: ## Local setup: DDEV + Composer + WP core + theme npm
	@echo "$(BLUE)Setting up...$(RESET)"
	@$(MAKE) --no-print-directory _check-requirements
	@if [ ! -f $(ENV_FILE) ]; then cp $(ENV_EXAMPLE) $(ENV_FILE) && echo "$(YELLOW)Created $(ENV_FILE)$(RESET)"; fi
	@if ! ddev describe >/dev/null 2>&1; then ddev config --docroot=$(APP_DIR) --project-type=wordpress --project-name=$(PROJECT_NAME); fi
	@ddev start
	ddev composer install --working-dir=$(APP_DIR)
	@if [ ! -f $(APP_DIR)/wp/wp-settings.php ]; then echo "$(YELLOW)Downloading WordPress core into $(APP_DIR)/wp...$(RESET)"; ddev wp core download; fi
	@if [ -f $(VITE_PATH)/package.json ]; then echo "$(YELLOW)Installing theme dependencies ($(VITE_THEME))...$(RESET)"; cd $(VITE_PATH) && npm install; fi
	@$(MAKE) --no-print-directory _git-setup
	@echo "$(GREEN)Setup complete$(RESET)"

setup-staging: ## Init staging remote: git, first bundle, DB, uploads
	@echo "$(BLUE)Setting up staging...$(RESET)"
	@$(MAKE) --no-print-directory _check-staging-config
	@$(MAKE) --no-print-directory _ensure-clean-git
	@$(MAKE) --no-print-directory _verify-ssh-access host=$(STAGING_HOST)
	@$(MAKE) --no-print-directory _init-remote-git host=$(STAGING_HOST) path=$(STAGING_PATH)
	@$(MAKE) --no-print-directory _create-bundle
	@$(MAKE) --no-print-directory _deploy-bundle host=$(STAGING_HOST) path=$(STAGING_PATH) env=staging
	@if ddev describe >/dev/null 2>&1; then \
	   ddev export-db --file=$(TEMP_DIR)/init.sql.gz && gunzip $(TEMP_DIR)/init.sql.gz; \
	   sed -i.bak "s|$(LOCAL_URL)|$(STAGING_URL)|g" $(TEMP_DIR)/init.sql && rm -f $(TEMP_DIR)/init.sql.bak; \
	   scp $(TEMP_DIR)/init.sql $(STAGING_HOST):$(TEMP_DIR)/; \
	   ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db import $(TEMP_DIR)/init.sql --path=. && rm $(TEMP_DIR)/init.sql"; \
	   [ -d "$(UPLOADS_LOCAL)" ] && rsync -avz $(UPLOADS_LOCAL)/ $(STAGING_HOST):$(STAGING_PATH)/$(UPLOADS_REMOTE)/; \
	   ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp cache flush --path=."; \
	   echo "$(GREEN)Staging ready$(RESET)"; \
	else \
	   echo "$(YELLOW)Git ready. Run from DDEV to push DB$(RESET)"; \
	fi

setup-production: ## Init production remote (confirmed)
	@$(MAKE) --no-print-directory _check-production-config
	@echo "$(RED)SETUP PRODUCTION?$(RESET)"
	@$(MAKE) --no-print-directory _confirm message="Continue?"
	@ssh $(PRODUCTION_HOST) "echo 'SSH OK'" || exit 1
	@ssh $(PRODUCTION_HOST) "cd $(PRODUCTION_PATH) && git init && git config receive.denyCurrentBranch ignore && git config user.name Deploy"
	@echo "$(GREEN)Production initialized$(RESET)"

# === WORKFLOW ===
dev: ## Start DDEV on a safe (non-main) branch
	@$(MAKE) --no-print-directory _check-ddev
	@ddev start
	@$(MAKE) --no-print-directory _ensure-safe-branch
	@echo "$(GREEN)Branch: $$(git branch --show-current) | $(LOCAL_URL)$(RESET)"

save: ## Stage everything + commit (prompted message)
	@$(MAKE) --no-print-directory _ensure-safe-branch
	@if [ -z "$$(git status --porcelain)" ]; then echo "$(YELLOW)No changes$(RESET)"; else \
	   git add -A; git status --short; read -p "Message: " m; [ -n "$$m" ] && git commit -m "$$m" && echo "$(GREEN)Saved$(RESET)" || echo "$(RED)Cancelled$(RESET)"; \
	fi

deploy: ## Bundle + deploy to staging
	@echo "$(BLUE)Deploying to staging...$(RESET)"
	@$(MAKE) --no-print-directory _check-staging-config
	@$(MAKE) --no-print-directory _ensure-clean-git
	@$(MAKE) --no-print-directory _verify-ssh-access host=$(STAGING_HOST)
	@$(MAKE) --no-print-directory _create-bundle
	@$(MAKE) --no-print-directory _deploy-bundle host=$(STAGING_HOST) path=$(STAGING_PATH) env=staging
	@echo "$(GREEN)$(STAGING_URL)$(RESET)"

ship: ## Bundle + deploy to production (confirmed, DB backup first)
	@$(MAKE) --no-print-directory _check-production-config
	@echo "$(RED)SHIP TO LIVE?$(RESET)"
	@$(MAKE) --no-print-directory _confirm message="Continue?"
	@$(MAKE) --no-print-directory _ensure-clean-git
	@$(MAKE) --no-print-directory _backup-production
	@$(MAKE) --no-print-directory _verify-ssh-access host=$(PRODUCTION_HOST)
	@$(MAKE) --no-print-directory _create-bundle
	@$(MAKE) --no-print-directory _deploy-bundle host=$(PRODUCTION_HOST) path=$(PRODUCTION_PATH) env=production
	@echo "$(GREEN)$(PRODUCTION_URL)$(RESET)"

# === FEATURE BRANCHES ===
feature: ## Create feature/<name> from staging (name=xyz)
	@if [ -z "$(name)" ]; then echo "$(RED)Usage: make feature name=xyz$(RESET)"; exit 1; fi
	@if ! echo "$(name)" | grep -qE '^[a-zA-Z][a-zA-Z0-9._-]*$$'; then echo "$(RED)Invalid name$(RESET)"; exit 1; fi
	@if git show-ref --verify --quiet refs/heads/feature/$(name); then echo "$(RED)Exists$(RESET)"; exit 1; fi
	@git checkout staging 2>/dev/null || git checkout -b staging
	@git checkout -b feature/$(name)
	@echo "$(GREEN)feature/$(name) created$(RESET)"

finish: ## Merge current feature branch into staging
	@BRANCH=$$(git branch --show-current); \
	if ! echo "$$BRANCH" | grep -q "^feature/"; then echo "$(RED)Not on feature$(RESET)"; exit 1; fi; \
	read -p "Merge $$BRANCH → staging? [y/N]: " c; [ "$$c" = "y" ] || exit 1; \
	git checkout staging && git merge --no-ff "$$BRANCH" -m "Merge $$BRANCH" && git branch -d "$$BRANCH" && echo "$(GREEN)Done$(RESET)"

# === DATABASE ===
## REVIEW: db-clean bulk-deletes spam/trash comments and ALL post revisions with
## no confirmation prompt (other destructive targets gate on _confirm). Keep
## as-is, add _confirm, or drop? — Stefan's call at close-out.
db-clean: ## Delete spam/trash comments, revisions, transients; optimize DB
	@echo "Cleaning DB..."
	@ddev wp comment delete $$(ddev wp comment list --status=spam,trash --format=ids) --force 2>/dev/null || true
	@ddev wp post delete $$(ddev wp post list --post_type=revision --format=ids) --force 2>/dev/null || true
	@ddev wp transient delete --all
	@ddev wp db optimize
	@echo "$(GREEN)DB cleaned$(RESET)"

# === SYNC ===
sync-to-local: ## Pull staging DB + uploads into local
	@echo "$(BLUE)Syncing staging → local...$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@mkdir -p $(BACKUP_DIR)
	@ddev export-db --file=$(BACKUP_DIR)/local-before-sync-$$(date +%s).sql.gz
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db export --path=. -" > $(TEMP_DIR)/staging.sql
	@ddev import-db --src=$(TEMP_DIR)/staging.sql
	@ddev wp search-replace "$(STAGING_URL)" "$(LOCAL_URL)" --all-tables
	@rsync -avz --delete $(STAGING_HOST):$(STAGING_PATH)/$(UPLOADS_REMOTE)/ $(UPLOADS_LOCAL)/
	@ddev wp cache flush
	@echo "$(GREEN)Synced$(RESET)"

sync-to-staging: ## Push local DB + uploads to staging (confirmed)
	@echo "$(BLUE)Syncing local → staging...$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@$(MAKE) --no-print-directory _confirm message="Overwrite staging?"
	@mkdir -p $(BACKUP_DIR)
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db export --path=. -" > $(BACKUP_DIR)/staging-$$(date +%s).sql
	@ddev export-db --file=$(TEMP_DIR)/local.sql.gz && gunzip $(TEMP_DIR)/local.sql.gz
	@sed -i.bak "s|$(LOCAL_URL)|$(STAGING_URL)|g" $(TEMP_DIR)/local.sql && rm -f $(TEMP_DIR)/local.sql.bak
	@scp $(TEMP_DIR)/local.sql $(STAGING_HOST):$(TEMP_DIR)/
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db import $(TEMP_DIR)/local.sql --path=. && rm $(TEMP_DIR)/local.sql"
	@rsync -avz --delete $(UPLOADS_LOCAL)/ $(STAGING_HOST):$(STAGING_PATH)/$(UPLOADS_REMOTE)/
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp cache flush --path=."
	@echo "$(GREEN)Synced$(RESET)"

# === TEMPLATES ===
## REVIEW: the template-save/load/list subsystem predates the wp-starter payload
## flow (new-site.sh --stack=stackwp now scaffolds theme + mu-plugins directly).
## Possibly superseded; template-load also overwrites the local DB + content
## (behind a confirm). Keep, or retire in favor of the payload flow? — Stefan's
## call at close-out.
template-save: ## Export DB + content as template (name=x)
	@if [ -z "$(name)" ]; then echo "$(RED)Usage: make template-save name=my-template$(RESET)"; exit 1; fi
	@if ! echo "$(name)" | grep -qE '^[a-zA-Z][a-zA-Z0-9._-]*$$'; then echo "$(RED)Invalid name$(RESET)"; exit 1; fi
	@echo "$(BLUE)Saving template: $(name)$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@SAVE_DIR="$(TEMPLATE_DIR)/$(TEMPLATE_PREFIX)$(name)"; \
	mkdir -p "$(TEMPLATE_DIR)"; rm -rf "$$SAVE_DIR"; mkdir -p "$$SAVE_DIR"; \
	ddev export-db --file="$$SAVE_DIR/database.sql.gz" && gunzip "$$SAVE_DIR/database.sql.gz"; \
	sed -i.bak "s|$(LOCAL_URL)|__SITE_URL__|g" "$$SAVE_DIR/database.sql" && rm -f "$$SAVE_DIR/database.sql.bak"; \
	[ -d "$(CONTENT_LOCAL)" ] && cp -R $(CONTENT_LOCAL) "$$SAVE_DIR/content"; \
	echo "# Template: $(name)\nCreated: $$(date)\nSource: $(PROJECT_NAME)" > "$$SAVE_DIR/README.md"; \
	cd "$$SAVE_DIR" && git init -b main && git add . && git commit -m "Template: $(name)"; \
	if git ls-remote "git@github.com:$(GITHUB_USER)/$(TEMPLATE_PREFIX)$(name).git" >/dev/null 2>&1; then \
	   git remote add origin "git@github.com:$(GITHUB_USER)/$(TEMPLATE_PREFIX)$(name).git"; \
	   git push -u origin main && echo "$(GREEN)Pushed to GitHub$(RESET)"; \
	else \
	   echo "$(GREEN)Saved locally: $$SAVE_DIR$(RESET)"; \
	   echo "To push: cd $$SAVE_DIR && git remote add origin ... && git push"; \
	fi

template-load: ## Import template: overwrites local DB + content (name=x)
	@if [ -z "$(name)" ]; then echo "$(RED)Usage: make template-load name=my-template$(RESET)"; exit 1; fi
	@echo "$(BLUE)Loading template: $(name)$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@echo "$(RED)This will OVERWRITE the local DB and $(CONTENT_LOCAL)/$(RESET)"
	@$(MAKE) --no-print-directory _confirm message="Continue?"
	@LOAD_DIR="$(TEMPLATE_DIR)/$(TEMPLATE_PREFIX)$(name)"; \
	if [ -d "$$LOAD_DIR" ] && [ -f "$$LOAD_DIR/README.md" ]; then \
	   echo "Using local template"; \
	elif git clone "git@github.com:$(GITHUB_USER)/$(TEMPLATE_PREFIX)$(name).git" "$$LOAD_DIR" 2>/dev/null; then \
	   echo "Cloned from GitHub"; \
	else \
	   echo "$(RED)Template not found — run 'make template-list'$(RESET)"; exit 1; \
	fi; \
	mkdir -p $(BACKUP_DIR); \
	[ -f "$$LOAD_DIR/database.sql" ] && ddev export-db --file="$(BACKUP_DIR)/before-$$(date +%s).sql.gz" && \
	   ddev import-db --src="$$LOAD_DIR/database.sql" && \
	   ddev wp search-replace '__SITE_URL__' "$(LOCAL_URL)" --all-tables; \
	[ -d "$$LOAD_DIR/content" ] && mkdir -p $(CONTENT_LOCAL) && rsync -av --delete "$$LOAD_DIR/content/" $(CONTENT_LOCAL)/; \
	ddev wp cache flush; \
	echo "$(GREEN)Template '$(name)' loaded$(RESET)"

template-list: ## List templates (local + GitHub)
	@echo "$(BLUE)Templates$(RESET)"
	@echo "$(YELLOW)Local:$(RESET)"
	@if [ -d "$(TEMPLATE_DIR)" ]; then \
	   for t in $(TEMPLATE_DIR)/$(TEMPLATE_PREFIX)*; do \
	      [ -d "$$t" ] && [ -f "$$t/README.md" ] && \
	      name=$$(basename "$$t" | sed 's/^$(TEMPLATE_PREFIX)//') && \
	      created=$$(grep "Created:" "$$t/README.md" 2>/dev/null | cut -d: -f2- | xargs) && \
	      printf "  • %-20s %s\n" "$$name" "($$created)"; \
	   done 2>/dev/null || echo "  (none)"; \
	else \
	   echo "  (none)"; \
	fi
	@echo "$(YELLOW)GitHub:$(RESET)"
	@gh repo list $(GITHUB_USER) --limit 100 2>/dev/null | grep "$(TEMPLATE_PREFIX)" | while read l; do \
	   name=$$(echo "$$l" | awk '{print $$1}' | cut -d/ -f2 | sed 's/^$(TEMPLATE_PREFIX)//'); \
	   updated=$$(echo "$$l" | awk '{print $$3,$$4,$$5}'); \
	   printf "  • %-20s %s\n" "$$name" "(updated $$updated)"; \
	done 2>/dev/null || echo "  (install 'gh' CLI)"

# === UTILITIES ===
status: ## Show local branch/changes + last staging commit
	@echo "$(BLUE)STATUS$(RESET)"
	@echo "$(YELLOW)LOCAL:$(RESET) $$(git branch --show-current) | $$(git status --porcelain | wc -l) changes"
	@echo "$(YELLOW)STAGING:$(RESET) $$(ssh $(STAGING_HOST) "cd $(STAGING_PATH) && git log --oneline -1" 2>/dev/null || echo 'Not accessible')"

backup: ## Export local DB + archive uploads to backups/
	@mkdir -p $(BACKUP_DIR)
	@ddev export-db --file=$(BACKUP_DIR)/local-$$(date +%s).sql.gz
	@if [ -d "$(UPLOADS_LOCAL)" ]; then tar -czf $(BACKUP_DIR)/uploads-$$(date +%s).tar.gz $(UPLOADS_LOCAL); fi
	@echo "$(GREEN)Backup complete$(RESET)"

## REVIEW: rollback does `git reset --hard HEAD~1` on the remote, but every
## _deploy-bundle checkout is a SINGLE PARENTLESS commit (fresh temp repo per
## deploy) — HEAD~1 cannot resolve, so this rollback likely fails exactly when
## needed. The backup-<timestamp> tags _deploy-bundle creates are never used;
## resetting to the latest backup-* tag is probably the intended mechanism.
## Redesign needs a call on the deploy history model — Stefan, at close-out.
# NOTE: the production-config check and the confirm prompt are INLINED here
# (no $(MAKE) recursion on the compound line) so that `make -n rollback`
# prints the ssh reset instead of executing it — make -n runs any recipe
# line that contains $(MAKE).
rollback: ## Emergency remote rollback (see REVIEW note)
	@echo "$(RED)ROLLBACK$(RESET)"
	@read -p "1) Staging  2) Production: " e; \
	if [ "$$e" = "1" ]; then H=$(STAGING_HOST); P=$(STAGING_PATH); else \
	   { [ -n "$(PRODUCTION_HOST)" ] && [ -n "$(PRODUCTION_PATH)" ]; } || { echo "$(RED)Production not set$(RESET)"; exit 1; }; \
	   H=$(PRODUCTION_HOST); P=$(PRODUCTION_PATH); fi; \
	read -p "$(YELLOW)Rollback $$H? [type 'yes']: $(RESET)" c; [ "$$c" = "yes" ] || exit 1; \
	ssh $$H "cd $$P && git reset --hard HEAD~1 && wp cache flush --path=." && \
	echo "$(GREEN)Rolled back$(RESET)"

ssh: ## Open SSH shell in the staging path
	@ssh $(STAGING_HOST) -t "cd $(STAGING_PATH); bash"

# === BUNDLE & DEPLOY (APP → ROOT) ===
# Working-tree copy note (S5): an earlier --exclude-from="$(git ls-files …)"
# on the content rsync was a verified NO-OP — git ls-files emits repo-root-
# relative paths while rsync matches patterns relative to the transfer root,
# so no tracked file was ever excluded. The real WIP guard is _ensure-clean-git,
# which every caller (deploy/ship/setup-staging) runs first: with a clean tree,
# tracked files are byte-identical to the `git archive HEAD` content already in
# BUILD_DIR, so the copy below only ADDS the gitignored third-party
# plugins/themes/mu-plugins.
_create-bundle:
	@set -e; \
	BUNDLE="deploy-$$(git rev-parse --short HEAD)-$$(date +%s).bundle"; \
	BUILD_DIR="$(TEMP_DIR)/build-$$$$"; \
	TEMP_REPO="$(TEMP_DIR)/deploy-$$$$"; \
	WORK_DIR="$$(pwd)"; \
	echo "$(YELLOW)Building bundle from branch $$(git branch --show-current)...$(RESET)"; \
	\
	mkdir -p "$$BUILD_DIR"; \
	git archive HEAD | tar -x -C "$$BUILD_DIR"; \
	\
	echo "$(YELLOW)Adding gitignored third-party files (tree is clean, see _ensure-clean-git)...$(RESET)"; \
	for dir in themes plugins mu-plugins; do \
	   rsync -a --exclude=node_modules $(APP_DIR)/content/$$dir/ "$$BUILD_DIR/$(APP_DIR)/content/$$dir/"; \
	done; \
	\
	if [ -f "$$BUILD_DIR/$(VITE_PATH)/package.json" ]; then \
	   echo "$(YELLOW)Building theme assets in /tmp ($(VITE_THEME))...$(RESET)"; \
	   ( cd "$$BUILD_DIR/$(VITE_PATH)" && npm ci -q && npm run build ); \
	fi; \
	\
	mkdir -p "$$TEMP_REPO" && cd "$$TEMP_REPO" && git init -q; \
	git config user.name "$$(git -C "$$WORK_DIR" config user.name)"; \
	git config user.email "$$(git -C "$$WORK_DIR" config user.email)"; \
	\
	DEPLOY_EXCLUDES="--exclude=node_modules --exclude=.git"; \
	if [ -f "$$BUILD_DIR/.deployignore" ]; then DEPLOY_EXCLUDES="$$DEPLOY_EXCLUDES --exclude-from=$$BUILD_DIR/.deployignore"; fi; \
	rsync -a $$DEPLOY_EXCLUDES "$$BUILD_DIR/$(APP_DIR)/" "$$TEMP_REPO"/; \
	rm -f "$$TEMP_REPO/.gitignore"; \
	\
	git add -A && git commit -m "Deploy" --no-verify -q; \
	git bundle create $(TEMP_DIR)/$$BUNDLE HEAD; \
	git bundle verify $(TEMP_DIR)/$$BUNDLE >/dev/null; \
	\
	cd "$$WORK_DIR"; \
	rm -rf "$$TEMP_REPO" "$$BUILD_DIR"; \
	echo "$(GREEN)✓ Ready: $$BUNDLE$(RESET)"; \
	echo "$$BUNDLE" > $(TEMP_DIR)/.bundle-name

# The remote chain runs under `set -e`: a failed bundle-verify, tag, checkout
# or composer install is FATAL. Only the genuinely optional legs (.env absent,
# wp-cli absent) are soft, behind explicit if-guards. The ssh exit status is
# checked BEFORE local cleanup so `rm -f` can never mask a failed deploy.
_deploy-bundle:
	@BUNDLE=$$(cat $(TEMP_DIR)/.bundle-name); \
	scp $(TEMP_DIR)/$$BUNDLE $(host):$(path)/ || exit 1; \
	ssh $(host) "set -e; cd $(path); \
	   git bundle verify $$BUNDLE; \
	   git tag backup-$$(date +%s); \
	   if [ -f .env ]; then cp .env .env.backup; else echo 'No .env to backup'; fi; \
	   REF=\$$(git bundle list-heads $$BUNDLE | awk '{print \$$1}'); \
	   git bundle unbundle $$BUNDLE; \
	   git checkout -f \$$REF; \
	   if [ -f .env.backup ]; then mv .env.backup .env; fi; \
	   composer install --no-dev --optimize-autoloader; \
	   if command -v wp >/dev/null 2>&1 && [ -f wp/wp-settings.php ]; then wp cache flush; else echo 'wp-cli skipped'; fi; \
	   rm $$BUNDLE" || exit 1; \
	echo "$(GREEN)Deployed$(RESET)"; \
	rm -f $(TEMP_DIR)/$$BUNDLE $(TEMP_DIR)/.bundle-name

# === HELPERS ===
_check-requirements:
	@command -v ddev git rsync ssh >/dev/null || { echo "$(RED)Missing: ddev, git, rsync, ssh$(RESET)"; exit 1; }

_check-ddev:
	@ddev describe >/dev/null 2>&1 || { echo "$(RED)DDEV not running$(RESET)"; exit 1; }

_check-staging-config:
	@[ -n "$(STAGING_HOST)" ] && [ -n "$(STAGING_PATH)" ] || { echo "$(RED)Staging not set$(RESET)"; exit 1; }

_check-production-config:
	@[ -n "$(PRODUCTION_HOST)" ] && [ -n "$(PRODUCTION_PATH)" ] || { echo "$(RED)Production not set$(RESET)"; exit 1; }

_verify-ssh-access:
	@ssh -o ConnectTimeout=5 $(host) "echo OK" >/dev/null || { echo "$(RED)SSH failed$(RESET)"; exit 1; }

_git-setup:
	@[ -d .git ] || git init
	@git checkout -b staging 2>/dev/null || true

_ensure-safe-branch:
	@case $$(git branch --show-current) in main|master|none) git checkout staging 2>/dev/null || git checkout -b staging ;; esac

_ensure-clean-git:
	@[ -z "$$(git status --porcelain)" ] || { echo "$(RED)Uncommitted changes$(RESET)"; exit 1; }

_confirm:
	@read -p "$(YELLOW)$(message) [type 'yes']: $(RESET)" c; [ "$$c" = "yes" ] || exit 1

_init-remote-git:
	@ssh $(host) "cd $(path) && [ ! -d .git ] && git init && git config receive.denyCurrentBranch ignore && git config user.name Deploy"

_backup-production:
	@mkdir -p $(BACKUP_DIR)
	@F=$(BACKUP_DIR)/prod-$$(date +%s).sql.gz; \
	ssh $(PRODUCTION_HOST) "cd $(PRODUCTION_PATH) && bash -c 'set -o pipefail; wp db export --path=. - | gzip'" > $$F \
	   || { rm -f $$F; echo "$(RED)Production DB export failed$(RESET)"; exit 1; }; \
	[ -s $$F ] || { rm -f $$F; echo "$(RED)Production backup is empty$(RESET)"; exit 1; }; \
	echo "$(GREEN)Production backed up: $$F$(RESET)"

