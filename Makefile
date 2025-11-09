# ---------------- Base Shell ----------------
SHELL := /bin/bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c

# ---------------- Variables ----------------
VERSION      ?= v0.1.0
BRANCH_DEV   ?= dev
BRANCH_MAIN  ?= main
REMOTE       ?= origin

# ---------------- PHONY ----------------
.PHONY: force_ssh_remote install_gitleaks check_secrets preflight \
        ensure-branch ensure-clean commit push merge tag release \
        test changelog help

# ---------------- Help ----------------
help:
	@echo "Targets:"
	@echo "  release VERSION=vX.Y.Z  Run full release flow (commit -> sync -> push/merge -> tag)"
	@echo "  commit                  Commit all changes on $(BRANCH_DEV)"
	@echo "  preflight               Sync branches with retries (fetch & rebase)"
	@echo "  push                    Push $(BRANCH_DEV) with retries"
	@echo "  merge                   Merge $(BRANCH_DEV) -> $(BRANCH_MAIN) and push with retries"
	@echo "  tag VERSION=vX.Y.Z      Create and push annotated tag"
	@echo "  test                    Run ctest in ./build if present"
	@echo "  changelog               Run scripts/update_changelog.sh if present"

# ---------------- Git Remote (force SSH) ----------------
force_ssh_remote:
	@echo "🔐 Forcing SSH for GitHub remotes..."
	# Transforme automatiquement tout URL https://github.com/* en git@github.com:* (global)
	@git config --global url."git@github.com:".insteadOf https://github.com/
	# Réécrit l'origin actuel s'il est encore en HTTPS
	@url="$$(git remote get-url $(REMOTE))"; \
	if [[ "$$url" =~ ^https://github.com/ ]]; then \
		new="$${url/https:\/\/github.com\//git@github.com:}"; \
		echo "🔁 Switching $(REMOTE) to $$new"; \
		git remote set-url $(REMOTE) "$$new"; \
	fi
	@echo "Remote $(REMOTE): $$(git remote get-url $(REMOTE))"
	# Check SSH (ne plante pas si réseau HS)
	@ssh -T git@github.com >/dev/null 2>&1 || true

# ---------------- Tools / Security ----------------
install_gitleaks:
	@echo "🔧 Checking gitleaks..."
	@if ! command -v gitleaks >/dev/null 2>&1; then \
		echo "⚙️  Installing gitleaks (script installer)..."; \
		curl -sSfL https://raw.githubusercontent.com/gitleaks/gitleaks/master/install.sh | bash -s -- -b /usr/local/bin || { \
			echo "⚠️  Installer script failed; trying tarball fallback..."; \
			curl -sSfL -o /tmp/gitleaks.tar.gz https://github.com/gitleaks/gitleaks/releases/latest/download/gitleaks_Linux_x86_64.tar.gz; \
			tar -xzf /tmp/gitleaks.tar.gz -C /tmp gitleaks; \
			sudo mv /tmp/gitleaks /usr/local/bin/gitleaks; \
			sudo chmod +x /usr/local/bin/gitleaks; \
		}; \
	fi
	@gitleaks version

check_secrets: install_gitleaks
	@echo "🔎 Preflight: secrets scan..."
	@gitleaks detect --source . --no-banner --redact
	@echo "✅ Secrets check passed"

# ---------------- Guards ----------------
ensure-branch:
	@if [ "$$(git rev-parse --abbrev-ref HEAD)" != "$(BRANCH_DEV)" ]; then \
		echo "❌ You must be on $(BRANCH_DEV) to run this target."; \
		exit 1; \
	fi

ensure-clean:
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "❌ Working tree not clean. Commit or stash first."; \
		git status --porcelain; \
		exit 1; \
	fi

# ---------------- Sync (avec retries) ----------------
preflight: force_ssh_remote
	@echo "🔎 Sync $(BRANCH_DEV) & $(BRANCH_MAIN) ..."
	# fetch avec retries (réseau/DNS)
	@tries=0; until git fetch $(REMOTE); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ git fetch failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries (fetch)..."; sleep 3; \
	done
	# S'assurer qu'on a bien les deux branches locales
	@git show-ref --verify --quiet refs/heads/$(BRANCH_DEV) || git branch $(BRANCH_DEV) $(REMOTE)/$(BRANCH_DEV) || true
	@git show-ref --verify --quiet refs/heads/$(BRANCH_MAIN) || git branch $(BRANCH_MAIN) $(REMOTE)/$(BRANCH_MAIN) || true

	# Rebase dev sur sa remote (avec retries)
	@tries=0; until git checkout $(BRANCH_DEV) && git pull --rebase $(REMOTE) $(BRANCH_DEV); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ rebase $(BRANCH_DEV) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries (pull --rebase $(BRANCH_DEV))..."; sleep 3; \
	done

	# Rebase main sur sa remote (avec retries)
	@tries=0; until git checkout $(BRANCH_MAIN) && git pull --rebase $(REMOTE) $(BRANCH_MAIN); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ rebase $(BRANCH_MAIN) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries (pull --rebase $(BRANCH_MAIN))..."; sleep 3; \
	done

	@git checkout $(BRANCH_DEV)
	@echo "✅ Preflight sync OK"

# ---------------- Core Flow ----------------
commit: ensure-branch
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "📝 Committing changes..."; \
		git add -A; \
		git commit -m "chore(release): prepare $(VERSION)"; \
	else \
		echo "✅ Nothing to commit."; \
	fi

push: force_ssh_remote
	# push dev avec retries
	@tries=0; until git push $(REMOTE) $(BRANCH_DEV); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ push $(BRANCH_DEV) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries..."; sleep 3; \
	done

merge: force_ssh_remote
	git checkout $(BRANCH_MAIN)
	git merge --no-ff --no-edit $(BRANCH_DEV)
	# push main avec retries
	@tries=0; until git push $(REMOTE) $(BRANCH_MAIN); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ push $(BRANCH_MAIN) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries..."; sleep 3; \
	done
	git checkout $(BRANCH_DEV)
	@echo "✅ Merge & push to $(BRANCH_MAIN) OK"

tag: force_ssh_remote
	@if ! [[ "$(VERSION)" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$$ ]]; then \
		echo "❌ VERSION must look like vX.Y.Z (got '$(VERSION)')"; exit 1; \
	fi
	@if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "❌ Tag $(VERSION) already exists."; exit 1; \
	fi
	@echo "🏷️  Creating annotated tag $(VERSION)..."
	git tag -a $(VERSION) -m "chore(release): $(VERSION)"
	# push tag avec retries
	@tries=0; until git push $(REMOTE) $(VERSION); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ push tag $(VERSION) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries..."; sleep 3; \
	done
	@echo "✅ Tag $(VERSION) pushed"

# ---------------- Orchestration ----------------
# Ordre sûr : commit -> preflight(sync) -> ensure-clean -> push dev -> merge to main -> tag
release: ensure-branch force_ssh_remote check_secrets commit preflight ensure-clean push merge tag
	@echo "🎉 Release $(VERSION) done!"

# ---------------- Extras ----------------
test:
	@composer test

coverage:
	@XDEBUG_MODE=coverage vendor/bin/phpunit

publish-mods:
	php bin/ivi modules:publish-assets

publish-mods-force:
	php bin/ivi modules:publish-assets --force

changelog:
	@bash scripts/update_changelog.sh || true
