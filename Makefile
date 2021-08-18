phpcs:
	vendor/bin/phpcs --standard=ruleset.xml --encoding=utf-8 -sp src tests

lint:
	vendor/bin/parallel-lint -e php,phpt --exclude vendor .

phpstan:
	vendor/bin/phpstan analyse -l 2 -c phpstan.neon src tests/KdybyTests

run-tests:
	vendor/bin/tester -s -C ./tests/KdybyTests/

coveralls:
	vendor/bin/php-coveralls --verbose --config tests/.coveralls.yml
