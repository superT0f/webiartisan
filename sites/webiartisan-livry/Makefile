.PHONY: dev build push clean install

dev:
	npm run dev

build:
	npm run build

push: build
	rsync -avz --delete dist/ /mnt/gandi/vhosts/artisans-livry.prigent.tech/htdocs/

clean:
	rm -rf dist node_modules

install:
	npm install
