SHELL := /bin/bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c

VERSION ?= v0.1.0
BRANCH_DEV  ?= dev
BRANCH_MAIN ?= main

.PHONY: help release commit push merge tag test changelog preflight ensure-clean ensure-branch

help:
	@echo "Available commands:"
	@echo "  make commit                        - Add and commit all files (on dev branch)"
	@echo "  make push                          - Push the dev branch"
	@echo "  make merge                         - Merge dev into main (no-ff)"
	@echo "  make tag VERSION=vX.Y.Z            - Create and push an annotated Git tag"
	@echo "  make release VERSION=vX.Y.Z        - Full release: preflight + commit + push + merge + tag"
	@echo "  make test                          - Compile and run tests"
	@echo "  make changelog                     - Update CHANGELOG via script"

preflight:
	@echo "🔎 Preflight: checking tools & secrets..."
	command -v gitleaks >/dev/null
	gitleaks detect --source . --no-banner --redact
	@echo "✅ Secrets check passed"
	@echo "🔎 Preflight: fetching & syncing $(BRANCH_DEV) ..."
	git fetch origin
	git checkout $(BRANCH_DEV)
	git pull --rebase origin $(BRANCH_DEV)
	@echo "🔎 Preflight: syncing $(BRANCH_MAIN) ..."
	git fetch origin
	@git show-ref --verify --quiet refs/heads/$(BRANCH_MAIN) || git branch $(BRANCH_MAIN) origin/$(BRANCH_MAIN)
	git checkout $(BRANCH_MAIN)
	git pull --rebase origin $(BRANCH_MAIN)
	git checkout $(BRANCH_DEV)

ensure-clean:
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "❌ Working tree not clean. Commit or stash first."; \
		exit 1; \
	fi

ensure-branch:
	@if [ "$$(git rev-parse --abbrev-ref HEAD)" != "$(BRANCH_DEV)" ]; then \
		echo "❌ You must be on $(BRANCH_DEV) to commit."; \
		exit 1; \
	fi

commit: ensure-branch
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "📝 Committing changes..."; \
		git add .; \
		git commit -m "🚀 Update: automated commit via Makefile"; \
	else \
		echo "✅ Nothing to commit."; \
	fi

push:
	git push origin $(BRANCH_DEV)

merge:
	@git checkout $(BRANCH_MAIN)
	git merge --no-ff --no-edit $(BRANCH_DEV)
	git push origin $(BRANCH_MAIN)
	@git checkout $(BRANCH_DEV)

tag:
	@if ! [[ "$(VERSION)" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$$ ]]; then \
		echo "❌ VERSION must look like vX.Y.Z (got '$(VERSION)')"; exit 1; \
	fi
	if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "❌ Tag $(VERSION) already exists."; exit 1; \
	fi
	git tag -a $(VERSION) -m "chore(release): $(VERSION)"
	git push origin $(VERSION)

release: preflight commit ensure-clean push merge tag

test:
	@if [ -d build ]; then cd build && ctest --output-on-failure; else echo "ℹ️ No build dir; skipping tests"; fi

changelog:
	bash scripts/update_changelog.sh || true
