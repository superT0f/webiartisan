.PHONY: help push test clean

PROD_DIR := $(HOME)/mnt/gandi/vhosts/app.prigent.tech/htdocs

help:
	@echo "WebiArtisan — App landing page (app.prigent.tech)"
	@echo "  make push  — Deploy landing page to Gandi"
	@echo "  make test  — Quick HTTP check"
	@echo "  make clean — Remove production logs backup"

push:
	@echo "⬆️ Deploying landing page to app.prigent.tech..."
	@test -d $(PROD_DIR) || (echo "❌ Gandi not mounted at $(PROD_DIR)" && exit 1)
	rsync -avz --no-owner --no-group \
		--exclude='.git/' \
		--exclude='Makefile' \
		--exclude='README.md' \
		./ $(PROD_DIR)/
	@echo "✅ Landing page deployed"

test:
	@echo "🧪 Testing app.prigent.tech..."
	@curl -s -o /dev/null -w "root: %{http_code}\n" https://app.prigent.tech/
	@curl -s -o /dev/null -w "/login redirect: %{http_code}\n" https://app.prigent.tech/login

clean:
	@echo "🧹 Cleaning old backups..."
	@rm -rf $(PROD_DIR)/uploads-old $(PROD_DIR)/index.html.old.bak $(PROD_DIR)/.htaccess.old.bak
