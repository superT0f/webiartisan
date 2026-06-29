.PHONY: dev build push clean install

dev:
	npm run dev

build:
	VITE_API_URL=https://api.prigent.tech \
	VITE_CITY_SLUG=vert-saint-denis \
	VITE_CITY_NAME=Vert-Saint-Denis \
	VITE_CITY_LAT=48.5644 \
	VITE_CITY_LNG=2.6186 \
	VITE_CITY_CP=77240 \
	VITE_APP_VERSION=1.1.0 \
		npm run build

push: build
	rsync -avz --no-owner --no-group --delete dist/ $(HOME)/mnt/gandi/vhosts/artisans-vert-saint-denis.prigent.tech/htdocs/

clean:
	rm -rf dist node_modules

install:
	npm install
