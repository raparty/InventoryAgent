.PHONY: install test analyse lint all

## Install Composer dependencies
install:
	composer install --prefer-dist --no-progress

## Run the PHPUnit test suite
test:
	vendor/bin/phpunit

## Run PHPStan static analysis (level 5)
analyse:
	vendor/bin/phpstan analyse --level=5

## PHP syntax lint check (excludes vendor/)
lint:
	find . -path ./vendor -prune -o -name '*.php' -print | xargs -I{} php -l {}

## Run lint + analyse + test
all: lint analyse test
