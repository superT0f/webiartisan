.PHONY: dev build push clean install

dev:
	npm run dev

build:
	VITE_API_URL=https://api.prigent.tech \
	VITE_CITY_SLUG=livry \
	VITE_CITY_NAME=Livry \
	VITE_CITY_LAT=49.1081 \
	VITE_CITY_LNG=-0.7658 \
	VITE_CITY_CP=14240 \
	VITE_APP_VERSION=1.1.0 \
		npm run build

push: build
	rsync -avz --no-owner --no-group --delete dist/ $(HOME)/mnt/gandi/vhosts/artisans-livry.prigent.tech/htdocs/

clean:
	rm -rf dist node_modules

install:
	npm install
