#!/bin/sh
# S3 (Collaudatore ad Hoc): a new CodeIgniter 4 application with Shield, then the module zip wired exactly as
# documentation/installation.html#module and #wiring say (S3: the Composer command of the 1.0.0 documentation, ^1.0).
# Usage: module-app.sh <ci-version> <module-zip-path> <how: spark|installer>
# Database settings come from the environment of the call (DBH, DBP, DBN, DBU, DBW), never from a file of the suite.
set -eu
CI="$1"; ZIP="$2"; HOW="$3"
export COMPOSER_HOME=/tmp/composer COMPOSER_NO_INTERACTION=1
cd /var/www
composer create-project --no-dev --prefer-dist "codeigniter4/appstarter:$CI" app >/tmp/s3-composer.log 2>&1
cd app
composer require --update-no-dev -W "codeigniter4/framework:$CI" >>/tmp/s3-composer.log 2>&1
composer require --update-no-dev codeigniter4/shield >>/tmp/s3-composer.log 2>&1
# the buyer's own .env: environment, address and database
# development, except for the installer path: with Shield's toolbar collector a development application answers 500 to
# every page until Shield's tables exist (S3 observation), so the installer is opened in production, then development
ENVN=development; [ "$HOW" = installer ] && ENVN=production
cat > .env <<EOF
CI_ENVIRONMENT = $ENVN
app.baseURL = 'http://localhost:8091/'
database.default.hostname = $DBH
database.default.port = $DBP
database.default.database = $DBN
database.default.username = $DBU
database.default.password = $DBW
database.default.DBDriver = MySQLi
email.protocol = smtp
email.SMTPHost = host.docker.internal
email.SMTPPort = 1026
email.SMTPCrypto = ''
email.fromEmail = noreply@s2.test
email.fromName = 'RoleWarden S3'
EOF
# "Shield must already be installed and set up (php spark shield:setup)"; the sender is in .env (mail to Mailpit),
# its migrate question answered no
printf 'n\n' | php spark shield:setup >>/tmp/s3-composer.log 2>&1
# the module zip as modules/rolewarden, Composer path repository
mkdir -p modules
cd modules && unzip -q "$ZIP" && cd ..
composer config repositories.rolewarden '{"type": "path", "url": "modules/rolewarden", "options": {"symlink": false}}'
composer require --update-no-dev 'rolewarden/codeigniter4-rolewarden:^1.0' >>/tmp/s3-composer.log 2>&1
composer require --update-no-dev codeigniter4/translations >>/tmp/s3-composer.log 2>&1
# wiring (installation.html#wiring)
php /tmp/module-wire.php
if [ "$HOW" = spark ]; then
  # installation.html#migrations, by hand
  php spark migrate -n 'CodeIgniter\Settings'
  php spark migrate -n 'CodeIgniter\Shield'
  php spark migrate -n RoleWarden
  php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'
  printf '%s\n%s\n' "$OPW" "$OPW" | php spark shield:user create -n s2owner -e owner@s2.test
  printf 'y\n' | php spark shield:user activate -e owner@s2.test
fi
echo "framework: $(php -r 'require "vendor/autoload.php"; echo \CodeIgniter\CodeIgniter::CI_VERSION;') php: $(php -r 'echo PHP_VERSION;')"
composer show | grep -E '^(codeigniter4|rolewarden)/' || true
