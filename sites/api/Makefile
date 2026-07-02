# API Makefile — PHP Backend
# Production: api.prigent.tech (separate subdomain)

.PHONY: help dev test push status
.DEFAULT_GOAL := help

PROD_LINK := production

help:
	@echo "API (PHP) Commands:"
	@echo "  make dev    — Check local Apache API"
	@echo "  make test   — Run API health check"
	@echo "  make push   — Push API to Gandi"
	@echo "  make status — Check sync status with production"

dev:
	@echo "🔌 API locale: http://localhost/api/"
	@curl -s http://localhost/api/health | python3 -m json.tool 2>/dev/null \
		&& echo "✅ API OK" \
		|| echo "❌ API down — sudo systemctl restart apache2"

test:
	@echo "🧪 Testing API endpoints..."
	@echo "--- Health ---"
	@curl -s http://localhost/api/health | python3 -m json.tool
	@echo "--- Auth (login test) ---"
	@curl -s http://localhost/api/auth/login -X POST \
		-H "Content-Type: application/json" \
		-d '{"email":"test@test.com","password":"test1234"}' | python3 -m json.tool

push:
	@echo "⬆️ Pushing API to Gandi..."
	@test -L $(PROD_LINK) || (echo "❌ Symlink $(PROD_LINK) missing. Create it:" && \
		echo "   ln -s /mnt/gandi/vhosts/api.prigent.tech/htdocs $(PROD_LINK)" && exit 1)
	@test -d $(PROD_LINK)/ || (echo "❌ Gandi not mounted at /mnt/gandi" && exit 1)
	rsync -avz --no-owner --no-group \
		--exclude='.env' \
		--exclude='.env.*' \
		--exclude='.git/' \
		--exclude='.gitignore' \
		--exclude='php_errors.log' \
		--exclude='uploads/' \
		--exclude='public/' \
		--exclude='vendor/' \
		--exclude='webiartisan-api.conf' \
		--exclude='Makefile' \
		--exclude='production' \
		--exclude='tests/' \
		--exclude='*.md' \
		--exclude='composer.lock' \
		--exclude='*.backup.*' \
		--exclude='test_*.php' \
		--exclude='test-*.php' \
		--exclude='verify-setup.sh' \
		./ $(PROD_LINK)/ \
		&& echo "✅ API pushed to Gandi" \
		|| echo "❌ Failed to push"

status:
	@echo "📊 Checking API sync status..."
	@test -L $(PROD_LINK) || (echo "❌ Symlink $(PROD_LINK) missing" && exit 1)
	rsync -avn --no-owner --no-group \
		--exclude='.env' \
		--exclude='.env.*' \
		--exclude='.git/' \
		--exclude='.gitignore' \
		--exclude='php_errors.log' \
		--exclude='uploads/' \
		--exclude='vendor/' \
		--exclude='webiartisan-api.conf' \
		--exclude='Makefile' \
		--exclude='production' \
		--exclude='tests/' \
		--exclude='*.md' \
		--exclude='composer.lock' \
		--exclude='*.backup.*' \
		--exclude='test_*.php' \
		--exclude='test-*.php' \
		--exclude='verify-setup.sh' \
		./ $(PROD_LINK)/
