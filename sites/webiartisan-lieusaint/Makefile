.PHONY: dev build push clean install

APP_VERSION := $(shell cat ../../.version 2>/dev/null || echo 2.0.1)
export VITE_APP_VERSION := $(APP_VERSION)

dev:
	npm run dev

build:
	VITE_API_URL=https://api.prigent.tech \
	VITE_CITY_SLUG=lieusaint \
	VITE_CITY_NAME=Lieusaint \
	VITE_CITY_LAT=48.6316 \
	VITE_CITY_LNG=2.5512 \
	VITE_CITY_CP=77127 \
	VITE_APP_VERSION=$(VITE_APP_VERSION) \
		npm run build

push: build
	# 1) assets hashés SANS --delete : l'ancien index.html reste servi et fonctionne
	rsync -avz --no-owner --no-group --exclude='index.html' dist/ $(HOME)/mnt/gandi/vhosts/artisans-lieusaint.prigent.tech/htdocs/
	# 2) bascule d'index.html en dernier (rsync écrit via temp + rename = atomique)
	rsync -avz --no-owner --no-group dist/index.html $(HOME)/mnt/gandi/vhosts/artisans-lieusaint.prigent.tech/htdocs/index.html
	# 3) ménage différé : ne supprimer que les chunks de plus de 7 jours
	find $(HOME)/mnt/gandi/vhosts/artisans-lieusaint.prigent.tech/htdocs/assets -type f -mtime +7 -delete 2>/dev/null || true

clean:
	rm -rf dist node_modules

install:
	npm install
