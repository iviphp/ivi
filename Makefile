# ===== Makefile (corrigé) =====
SHELL := /bin/bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c

VERSION      ?= v0.1.0
BRANCH_DEV   ?= dev
BRANCH_MAIN  ?= main

.PHONY: help release commit push merge tag test changelog preflight ensure-clean ensure-branch install_gitleaks

help:
	@echo "Available commands:"
	@echo "  make commit                        - Add and commit all files (on $(BRANCH_DEV) branch)"
	@echo "  make push                          - Push the $(BRANCH_DEV) branch"
	@echo "  make merge                         - Merge $(BRANCH_DEV) into $(BRANCH_MAIN)"
	@echo "  make tag VERSION=vX.Y.Z           - Create and push a Git tag (default: $(VERSION))"
	@echo "  make release VERSION=vX.Y.Z       - Full release: preflight + commit + push + merge + tag"
	@echo "  make test                          - Compile and run tests"
	@echo "  make changelog                     - Update CHANGELOG.md using script"

# ---------- Safety helpers ----------
ensure-branch:
	@if [ "$$(git rev-parse --abbrev-ref HEAD)" != "$(BRANCH_DEV)" ]; then \
		echo "❌ You must be on $(BRANCH_DEV) to commit."; \
		exit 1; \
	fi

ensure-clean:
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "❌ Working tree not clean. Commit or stash first."; \
		exit 1; \
	fi

# ---------- Tools ----------
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

# ---------- Preflight (runs before release) ----------
preflight: install_gitleaks
	@echo "🔎 Preflight: secrets & branches..."
	@gitleaks detect --source . --no-banner --redact
	@echo "✅ Secrets check passed"
	@echo "🔎 Sync $(BRANCH_DEV) & $(BRANCH_MAIN) ..."
	git fetch origin
	git checkout $(BRANCH_DEV)
	git pull --rebase origin $(BRANCH_DEV)
	@git show-ref --verify --quiet refs/heads/$(BRANCH_MAIN) || git branch $(BRANCH_MAIN) origin$(BRANCH_MAIN)
	git checkout $(BRANCH_MAIN)
	git pull --rebase origin $(BRANCH_MAIN)
	git checkout $(BRANCH_DEV)

# ---------- Core flow ----------
commit: ensure-branch
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "📝 Committing changes..."; \
		git add .; \
		git commit -m "chore(release): prepare $(VERSION)"; \
	else \
		echo "✅ Nothing to commit."; \
	fi

push:
	git push origin $(BRANCH_DEV)

merge:
	git checkout $(BRANCH_MAIN)
	git merge --no-ff --no-edit $(BRANCH_DEV)
	git push origin $(BRANCH_MAIN)
	git checkout $(BRANCH_DEV)

tag:
	@if ! [[ "$(VERSION)" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$$ ]]; then \
		echo "❌ VERSION must look like vX.Y.Z (got '$(VERSION)')"; exit 1; \
	fi
	@if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "❌ Tag $(VERSION) already exists."; exit 1; \
	fi
	@echo "🏷️  Creating annotated tag $(VERSION)..."
	git tag -a $(VERSION) -m "chore(release): $(VERSION)"
	git push origin $(VERSION)

release: preflight commit ensure-clean push merge tag

# ---------- Misc ----------
test:
	@if [ -d build ]; then cd build && ctest --output-on-failure; else echo "ℹ️ No build dir; skipping tests"; fi

changelog:
	bash scripts/update_changelog.sh || true
