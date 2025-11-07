SHELL := /bin/bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c

VERSION      ?= v0.1.0
BRANCH_DEV   ?= dev
BRANCH_MAIN  ?= main
REMOTE       ?= origin

# ---- Utilitaire: forcer SSH pour GitHub (si HTTPS) ----
force_ssh_remote:
	@url="$$(git remote get-url $(REMOTE))"; \
	if [[ "$$url" =~ ^https://github.com/ ]]; then \
		echo "🔐 Switching remote to SSH..."; \
		git remote set-url $(REMOTE) "$$(echo "$$url" | sed 's#https://github.com/#git@github.com:#')"; \
	fi
	@echo "Remote $(REMOTE): $$(git remote get-url $(REMOTE))"
	@ssh -T git@github.com >/dev/null 2>&1 || true

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

# ---------- Preflight ----------
preflight: install_gitleaks force_ssh_remote
	@echo "🔎 Preflight: secrets & branches..."
	@gitleaks detect --source . --no-banner --redact
	@echo "✅ Secrets check passed"
	@echo "🔎 Sync $(BRANCH_DEV) & $(BRANCH_MAIN) ..."
	git fetch $(REMOTE)
	git checkout $(BRANCH_DEV)
	git pull --rebase $(REMOTE) $(BRANCH_DEV)
	# bugfix: slash manquant
	@git show-ref --verify --quiet refs/heads/$(BRANCH_MAIN) || git branch $(BRANCH_MAIN) $(REMOTE)/$(BRANCH_MAIN)
	git checkout $(BRANCH_MAIN)
	git pull --rebase $(REMOTE) $(BRANCH_MAIN)
	git checkout $(BRANCH_DEV)

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

# ---------- Core flow ----------
commit: ensure-branch
	@if [ -n "$$(git status --porcelain)" ]; then \
		echo "📝 Committing changes..."; \
		git add .; \
		git commit -m "chore(release): prepare $(VERSION)"; \
	else \
		echo "✅ Nothing to commit."; \
	fi

push: force_ssh_remote
	# push dev avec retries
	tries=0; until git push $(REMOTE) $(BRANCH_DEV); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ push $(BRANCH_DEV) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries..."; sleep 3; \
	done

merge: force_ssh_remote
	git checkout $(BRANCH_MAIN)
	git merge --no-ff --no-edit $(BRANCH_DEV) || true
	# push main avec retries
	tries=0; until git push $(REMOTE) $(BRANCH_MAIN); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ push $(BRANCH_MAIN) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries..."; sleep 3; \
	done
	git checkout $(BRANCH_DEV)

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
	tries=0; until git push $(REMOTE) $(VERSION); do \
		tries=$$((tries+1)); \
		if [ $$tries -ge 5 ]; then echo "❌ push tag $(VERSION) failed after $$tries tries"; exit 128; fi; \
		echo "⏳ Retry $$tries..."; sleep 3; \
	done

release: preflight commit ensure-clean push merge tag

test:
	@if [ -d build ]; then cd build && ctest --output-on-failure; else echo "ℹ️ No build dir; skipping tests"; fi

changelog:
	bash scripts/update_changelog.sh || true
