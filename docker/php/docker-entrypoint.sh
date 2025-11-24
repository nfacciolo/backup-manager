#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'bin/console' ]; then

  if [ ! -f composer.json ]; then
    composer create-project symfony/skeleton:"7.3.x" ./tmp  --prefer-dist --no-progress --no-interaction
    cd tmp
    cp -R . ..
    cd -
    rm -Rf tmp/
  fi

	# Install symfony/orm-pack if not already installed
#	if ! composer show symfony/orm-pack >/dev/null 2>&1; then
#	    composer require symfony/orm-pack
#	fi
#
#	# Verify database is accessible
#	until bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
#	    (>&2 echo "Verifying database connection...")
#		sleep 1
#	done
#
#	# Run migrations only if there are migrations to execute
#	if bin/console doctrine:migrations:status --no-ansi 2>/dev/null | grep -q "New Migrations"; then
#	    bin/console doctrine:migrations:migrate --no-interaction
#	fi
	bin/console cache:clear
fi

exec docker-php-entrypoint "$@"
