#!/usr/bin/env bash

cd "$(dirname "$0")/../"

export PATH="$HOME/.composer/vendor/bin:$PATH"

composer install

if [[ ! -z "$WP_VERSION" ]] ; then
  echo -en "travis_fold:start:install_wp_tests\r"
  bash ./bin/install-wp-tests.sh wordpress_test root '' localhost $WP_VERSION
  echo -en "travis_fold:end:install_wp_tests\r"
fi

echo "Running with the following versions:"
echo "php -v"
php -v
echo "./vendor/bin/phpunit --version"
./vendor/bin/phpunit --version

# Run PHPUnit tests
if [[ ! -z "$WP_VERSION" ]] ; then
  ./vendor/bin/phpunit --coverage-clover=clover.xml || exit 1;
fi

# Run phpcs
if [[ "$WP_TRAVISCI" == "phpcs" ]] ; then
    ./vendor/bin/phpcs
fi
