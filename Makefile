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
ENV_FILE := $(APP_DIR)/.env
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
.PHONY: help
help: ## Show this help
	@echo "$(BLUE)╔════════════════════════════════════════════════════╗$(RESET)"
	@echo "$(BLUE)║  $(PROJECT_NAME) - Custom App Structure$(RESET)"
	@echo "$(BLUE)╚════════════════════════════════════════════════════╝$(RESET)"
	@echo ""
	@echo "$(GREEN)Branch:$(RESET) $$(git branch --show-current 2>/dev/null || echo 'none')"
	@echo "$(GREEN)DDEV:$(RESET) $$(if ddev describe >/dev/null 2>&1; then echo 'running'; else echo 'not installed'; fi)"
	@echo ""
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "setup" "Local + DDEV"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "setup-staging" "Init staging"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "dev" "Start dev"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "save" "Commit"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "deploy" "To staging"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "ship" "To production"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "feature" "make feature name=xyz"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "finish" "Merge → staging"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "sync-to-local" "Pull staging"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "sync-to-staging" "Push staging"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "template-save" "Export as template"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "template-load" "Import template"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "template-list" "List templates"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "status" "Full status"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "backup" "Local backup"
	@printf "  $(GREEN)%-20s$(RESET) %s\n" "rollback" "Emergency rollback"
	@echo ""
	@echo "$(BLUE)Quickstart:$(RESET) make setup && make dev"

# === SETUP ===
setup: ## Local setup
	@echo "$(BLUE)Setting up...$(RESET)"
	@$(MAKE) --no-print-directory _check-requirements
	@if [ ! -f $(ENV_FILE) ]; then cp $(ENV_EXAMPLE) $(ENV_FILE) && echo "$(YELLOW)Created $(ENV_FILE)$(RESET)"; fi
	@if ! ddev describe >/dev/null 2>&1; then ddev config --docroot=$(APP_DIR) --project-type=wordpress --project-name=$(PROJECT_NAME); fi
	@ddev start
	ddev composer install --working-dir=$(APP_DIR) 2>/dev/null || true
	@npm install 2>/dev/null || true
	@$(MAKE) --no-print-directory _git-setup
	@echo "$(GREEN)Setup complete$(RESET)"

setup-staging: ## Setup staging
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

setup-production: ## Setup production
	@make _check-production-config
	@echo "$(RED)SETUP PRODUCTION?$(RESET)"
	@make _confirm message="Continue?"
	@ssh $(PRODUCTION_HOST) "echo 'SSH OK'" || exit 1
	@ssh $(PRODUCTION_HOST) "cd $(PRODUCTION_PATH) && git init && git config receive.denyCurrentBranch ignore && git config user.name Deploy"
	@echo "$(GREEN)Production initialized$(RESET)"

# === WORKFLOW ===
dev: ## Start dev
	@$(MAKE) --no-print-directory _check-ddev
	@ddev start
	@$(MAKE) --no-print-directory _ensure-safe-branch
	@echo "$(GREEN)Branch: $$(git branch --show-current) | $(LOCAL_URL)$(RESET)"

save: ## Commit
	@$(MAKE) --no-print-directory _ensure-safe-branch
	@if [ -z "$$(git status --porcelain)" ]; then echo "$(YELLOW)No changes$(RESET)"; else \
	   git add -A; git status --short; read -p "Message: " m; [ -n "$$m" ] && git commit -m "$$m" && echo "$(GREEN)Saved$(RESET)" || echo "$(RED)Cancelled$(RESET)"; \
	fi

deploy: ## To staging
	@echo "$(BLUE)Deploying to staging...$(RESET)"
	@$(MAKE) --no-print-directory _ensure-clean-git
	@$(MAKE) --no-print-directory _verify-ssh-access host=$(STAGING_HOST)
	@$(MAKE) --no-print-directory _create-bundle
	@$(MAKE) --no-print-directory _deploy-bundle host=$(STAGING_HOST) path=$(STAGING_PATH) env=staging
	@echo "$(GREEN)$(STAGING_URL)$(RESET)"

ship: ## To production
	@make _check-production-config
	@echo "$(RED)SHIP TO LIVE?$(RESET)"
	@make _confirm message="Continue?"
	@make _ensure-clean-git
	@make _backup-production
	@make _verify-ssh-access host=$(PRODUCTION_HOST)
	@make _create-bundle
	@make _deploy-bundle host=$(PRODUCTION_HOST) path=$(PRODUCTION_PATH) env=production
	@echo "$(GREEN)$(PRODUCTION_URL)$(RESET)"

# === FEATURE BRANCHES ===
feature: ## make feature name=login
	@if [ -z "$(name)" ]; then echo "$(RED)Usage: make feature name=xyz$(RESET)"; exit 1; fi
	@if ! echo "$(name)" | grep -qE '^[a-zA-Z][a-zA-Z0-9._-]*$$'; then echo "$(RED)Invalid name$(RESET)"; exit 1; fi
	@if git show-ref --verify --quiet refs/heads/feature/$(name); then echo "$(RED)Exists$(RESET)"; exit 1; fi
	@git checkout staging 2>/dev/null || git checkout -b staging
	@git checkout -b feature/$(name)
	@echo "$(GREEN)feature/$(name) created$(RESET)"

finish: ## Merge → staging
	@BRANCH=$$(git branch --show-current); \
	if ! echo "$$BRANCH" | grep -q "^feature/"; then echo "$(RED)Not on feature$(RESET)"; exit 1; fi; \
	read -p "Merge $$BRANCH → staging? [y/N]: " c; [ "$$c" = "y" ] || exit 1; \
	git checkout staging && git merge --no-ff "$$BRANCH" -m "Merge $$BRANCH" && git branch -d "$$BRANCH" && echo "$(GREEN)Done$(RESET)"

# === DATABASE ===
db-clean: ## Clean DB
	@echo "Cleaning DB..."
	@ddev wp comment delete $$(ddev wp comment list --status=spam,trash --format=ids) --force 2>/dev/null || true
	@ddev wp post delete $$(ddev wp post list --post_type=revision --format=ids) --force 2>/dev/null || true
	@ddev wp transient delete --all
	@ddev wp db optimize
	@echo "$(GREEN)DB cleaned$(RESET)"

db-backup: ## Backup DB
	@mkdir -p $(BACKUP_DIR)
	@ddev export-db --file=$(BACKUP_DIR)/local-$$(date +%Y%m%d-%H%M%S).sql.gz
	@echo "$(GREEN)DB backed up$(RESET)"

# === SYNC ===
sync-to-local: ## Pull staging → local
	@echo "$(BLUE)Syncing staging → local...$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@ddev export-db --file=$(BACKUP_DIR)/local-before-sync-$$(date +%s).sql.gz
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db export --path=. -" > $(TEMP_DIR)/staging.sql
	@ddev import-db --src=$(TEMP_DIR)/staging.sql
	@ddev wp search-replace "$(STAGING_URL)" "$(LOCAL_URL)" --all-tables
	@rsync -avz --delete $(STAGING_HOST):$(STAGING_PATH)/$(UPLOADS_REMOTE)/ $(UPLOADS_LOCAL)/
	@ddev wp cache flush
	@echo "$(GREEN)Synced$(RESET)"

sync-to-staging: ## Push local → staging
	@echo "$(BLUE)Syncing local → staging...$(RESET)"
	@$(MAKE) --no-print-main _check-ddev
	@$(MAKE) --no-print-directory _confirm message="Overwrite staging?"
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db export --path=. -" > $(BACKUP_DIR)/staging-$$(date +%s).sql
	@ddev export-db --file=$(TEMP_DIR)/local.sql.gz && gunzip $(TEMP_DIR)/local.sql.gz
	@sed -i.bak "s|$(LOCAL_URL)|$(STAGING_URL)|g" $(TEMP_DIR)/local.sql && rm -f $(TEMP_DIR)/local.sql.bak
	@scp $(TEMP_DIR)/local.sql $(STAGING_HOST):$(TEMP_DIR)/
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp db import $(TEMP_DIR)/local.sql --path=. && rm $(TEMP_DIR)/local.sql"
	@rsync -avz --delete $(UPLOADS_LOCAL)/ $(STAGING_HOST):$(STAGING_PATH)/$(UPLOADS_REMOTE)/
	@ssh $(STAGING_HOST) "cd $(STAGING_PATH) && wp cache flush --path=."
	@echo "$(GREEN)Synced$(RESET)"

# === TEMPLATES ===
template-save: ## Export site as template
	@if [ -z "$(name)" ]; then echo "$(RED)Usage: make template-save name=my-template$(RESET)"; exit 1; fi
	@if ! echo "$(name)" | grep -qE '^[a-zA-Z][a-zA-Z0-9._-]*$$'; then echo "$(RED)Invalid name$(RESET)"; exit 1; fi
	@echo "$(BLUE)Saving template: $(name)$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@SAVE_DIR="$(TEMPLATE_DIR)/$(TEMPLATE_PREFIX)$(name)"; \
	mkdir -p "$(TEMPLATE_DIR)"; rm -rf "$$SAVE_DIR"; mkdir -p "$$SAVE_DIR"; \
	ddev export-db --file="$$SAVE_DIR/database.sql.gz" && gunzip "$$SAVE_DIR/database.sql.gz"; \
	sed -i.bak "s|$(LOCAL_URL)|__SITE_URL__|g" "$$SAVE_DIR/database.sql" && rm -f "$$SAVE_DIR/database.sql.bak"; \
	[ -d "$(CONTENT_LOCAL)" ] && cp -R $(CONTENT_LOCAL) "$$SAVE_DIR/content"; \
	[ -d "src" ] && cp -R src "$$SAVE_DIR/src"; \
	echo "# Template: $(name)\nCreated: $$(date)\nSource: $(PROJECT_NAME)" > "$$SAVE_DIR/README.md"; \
	cd "$$SAVE_DIR" && git init -b main && git add . && git commit -m "Template: $(name)"; \
	if git ls-remote "git@github.com:$(GITHUB_USER)/$(TEMPLATE_PREFIX)$(name).git" >/dev/null 2>&1; then \
	   git remote add origin "git@github.com:$(GITHUB_USER)/$(TEMPLATE_PREFIX)$(name).git"; \
	   git push -u origin main && echo "$(GREEN)Pushed to GitHub$(RESET)"; \
	else \
	   echo "$(GREEN)Saved locally: $$SAVE_DIR$(RESET)"; \
	   echo "To push: cd $$SAVE_DIR && git remote add origin ... && git push"; \
	fi

template-load: ## Import template
	@if [ -z "$(name)" ]; then echo "$(RED)Usage: make template-load name=my-template$(RESET)"; exit 1; fi
	@echo "$(BLUE)Loading template: $(name)$(RESET)"
	@$(MAKE) --no-print-directory _check-ddev
	@echo "$(RED)This will OVERWRITE DB, content & src folders$(RESET)"
	@read -p "Continue? [y/N]: " c && [ "$$c" = "y" ] || exit 1
	@LOAD_DIR="$(TEMPLATE_DIR)/$(TEMPLATE_PREFIX)$(name)"; \
	if [ -d "$$LOAD_DIR" ] && [ -f "$$LOAD_DIR/README.md" ]; then \
	   echo "Using local template"; \
	elif git clone "git@github.com:$(GITHUB_USER)/$(TEMPLATE_PREFIX)$(name).git" "$$LOAD_DIR" 2>/dev/null; then \
	   echo "Cloned from GitHub"; \
	else \
	   echo "$(RED)Template not found$(RESET)"; $(MAKE) template-list; exit 1; \
	fi; \
	mkdir -p $(BACKUP_DIR); \
	[ -f "$$LOAD_DIR/database.sql" ] && ddev export-db --file="$(BACKUP_DIR)/before-$$(date +%s).sql.gz" && \
	   ddev import-db --src="$$LOAD_DIR/database.sql" && \
	   ddev wp search-replace '__SITE_URL__' "$(LOCAL_URL)" --all-tables; \
	[ -d "$$LOAD_DIR/content" ] && mkdir -p $(CONTENT_LOCAL) && rsync -av --delete "$$LOAD_DIR/content/" $(CONTENT_LOCAL)/; \
	[ -d "$$LOAD_DIR/src" ] && mkdir -p src && rsync -av --delete "$$LOAD_DIR/src/" src/; \
	ddev wp cache flush; \
	echo "$(GREEN)Template '$(name)' loaded$(RESET)"

template-list: ## List templates
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
status: ## Show status
	@echo "$(BLUE)STATUS$(RESET)"
	@echo "$(YELLOW)LOCAL:$(RESET) $$(git branch --show-current) | $$(git status --porcelain | wc -l) changes"
	@echo "$(YELLOW)STAGING:$(RESET) $$(ssh $(STAGING_HOST) "cd $(STAGING_PATH) && git log --oneline -1" 2>/dev/null || echo 'Not accessible')"

backup: ## Full backup
	@mkdir -p $(BACKUP_DIR)
	@ddev export-db --file=$(BACKUP_DIR)/local-$$(date +%s).sql.gz
	@[ -d "$(UPLOADS_LOCAL)" ] && tar -czf $(BACKUP_DIR)/uploads-$$(date +%s).tar.gz $(UPLOADS_LOCAL)
	@echo "$(GREEN)Backup complete$(RESET)"

rollback: ## Emergency rollback
	@echo "$(RED)ROLLBACK$(RESET)"
	@read -p "1) Staging  2) Production: " e; \
	if [ "$$e" = "1" ]; then H=$(STAGING_HOST); P=$(STAGING_PATH); else make _check-production-config; H=$(PRODUCTION_HOST); P=$(PRODUCTION_PATH); fi; \
	make _confirm message="Rollback $$H?"; \
	ssh $$H "cd $$P && git reset --hard HEAD~1 && wp cache flush --path=." && \
	echo "$(GREEN)Rolled back$(RESET)"

ssh: ## SSH to staging
	@ssh $(STAGING_HOST) -t "cd $(STAGING_PATH); bash"

# === BUNDLE & DEPLOY (APP → ROOT) ===
_create-bundle:
	@set -e; \
	BUNDLE="deploy-$$(git rev-parse --short HEAD)-$$(date +%s).bundle"; \
	BUILD_DIR="$(TEMP_DIR)/build-$$$$"; \
	TEMP_REPO="$(TEMP_DIR)/deploy-$$$$"; \
	WORK_DIR="$$(pwd)"; \
	echo "$(YELLOW)Building bundle from staging branch...$(RESET)"; \
	\
	mkdir -p "$$BUILD_DIR"; \
	git archive HEAD | tar -x -C "$$BUILD_DIR"; \
	\
	echo "$(YELLOW)Adding gitignored third-party files (not your WIP code)...$(RESET)"; \
	for dir in themes plugins mu-plugins; do \
	   TRACKED="/tmp/tracked-$$dir-$$$$"; \
	   git ls-files $(APP_DIR)/content/$$dir/ > "$$TRACKED"; \
	   rsync -a --exclude-from="$$TRACKED" $(APP_DIR)/content/$$dir/ "$$BUILD_DIR/$(APP_DIR)/content/$$dir/"; \
	   rm -f "$$TRACKED"; \
	done; \
	\
	if [ -f "$$BUILD_DIR/package.json" ]; then \
	   echo "$(YELLOW)Building assets in /tmp (PHPStorm won't see this)...$(RESET)"; \
	   cd "$$BUILD_DIR" && npm ci -q && VITE_THEME=$(VITE_THEME) npm run build; \
	fi; \
	\
	mkdir -p "$$TEMP_REPO" && cd "$$TEMP_REPO" && git init -q; \
	git config user.name "$$(git -C "$$WORK_DIR" config user.name)"; \
	git config user.email "$$(git -C "$$WORK_DIR" config user.email)"; \
	\
	rsync -a --exclude-from="$$BUILD_DIR/.deployignore" "$$BUILD_DIR/$(APP_DIR)/" "$$TEMP_REPO"/; \
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

_deploy-bundle:
	@BUNDLE=$$(cat $(TEMP_DIR)/.bundle-name); \
	scp $(TEMP_DIR)/$$BUNDLE $(host):$(path)/ || exit 1; \
	ssh $(host) "cd $(path) && \
	   git bundle verify $$BUNDLE && \
	   git tag backup-$$(date +%s) && \
	   [ -f .env ] && cp .env .env.backup || echo 'No .env to backup' && \
	   REF=\$$(git bundle list-heads $$BUNDLE | awk '{print \$$1}') && \
	   git bundle unbundle $$BUNDLE && \
	   git checkout -f \$$REF && \
	   [ -f .env.backup ] && mv .env.backup .env || echo 'No .env to restore' && \
	   composer install --no-dev --optimize-autoloader && \
	   [ -f wp/wp-settings.php ] && wp cache flush || echo 'wp-cli skipped' && \
	   rm $$BUNDLE" && \
	echo "$(GREEN)Deployed$(RESET)"; rm -f $(TEMP_DIR)/$$BUNDLE $(TEMP_DIR)/.bundle-name

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
	@ssh $(PRODUCTION_HOST) "cd $(PRODUCTION_PATH) && wp db export --path=. - | gzip" > $(BACKUP_DIR)/prod-$$(date +%s).sql.gz
	@echo "$(GREEN)Production backed up$(RESET)"

# === ALIASES ===
s: save
d: deploy

p: sync-to-local
