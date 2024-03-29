all:
	composer run-script qa-all --timeout=0

all-coverage:
	composer run-script qa-all-coverage --timeout=0

ci:
	composer run-script qa-ci --timeout=0

ci-extended:
	composer run-script qa-ci-extended --timeout=0

contrib:
	composer run-script qa-contrib --timeout=0

init:
	composer ensure-installed

cs:
	composer cs

cs-fix:
	composer cs-fix

unit:
	./vendor/bin/phpunit

unit-coverage:
	composer run-script unit-coverage --timeout=0

ci-coverage: init
	composer ci-coverage

generate-resources: init
	./vendor/bin/api-client-resource-generator ./resources.yml
