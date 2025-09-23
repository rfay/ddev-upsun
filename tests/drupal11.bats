#!/usr/bin/env bats

# Test Drupal Composer project configuration translation

setup() {
  set -eu -o pipefail

  export GITHUB_REPO=rfay/ddev-upsun

  TEST_BREW_PREFIX="$(brew --prefix 2>/dev/null || true)"
  export BATS_LIB_PATH="${BATS_LIB_PATH}:${TEST_BREW_PREFIX}/lib:/usr/lib/bats"
  bats_load_library bats-assert
  bats_load_library bats-file
  bats_load_library bats-support

  export DIR="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  export PROJNAME="test-drupal11"
  export PROJECT_SOURCE="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  mkdir -p ~/tmp
  export TESTDIR=$(mktemp -d ~/tmp/${PROJNAME}.XXXXXX)
  export DDEV_NONINTERACTIVE=true
  export DDEV_NO_INSTRUMENTATION=true
  ddev delete -Oy "${PROJNAME}" >/dev/null 2>&1 || true
  cd "${TESTDIR}"
  
  # Copy test fixture
  cp -r "${DIR}/tests/testdata/drupal11/." .
  
  # Configure DDEV project with bogus settings to ensure add-on overrides them
  run ddev config --project-name="${PROJNAME}" --project-type=drupal11 --docroot=web --fail-on-hook-fail --php-version=5.6 --database=mariadb:10.1 --web-environment-add=PLATFORM_PROJECT=bogus,PLATFORM_ENVIRONMENT=bogus
  assert_success
}

teardown() {
  set -eu -o pipefail
  ddev delete -Oy ${PROJNAME} >/dev/null 2>&1
  if [ -n "${GITHUB_ENV:-}" ]; then
    [ -e "${GITHUB_ENV:-}" ] && echo "TESTDIR=${HOME}/tmp/${PROJNAME}" >> "${GITHUB_ENV}"
  else
    [ "${TESTDIR}" != "" ] && rm -rf "${TESTDIR}"
  fi
}

@test "install add-on: drupal11" {
  set -eu -o pipefail
  load per_test.sh
  testname="drupal11"
  PROJECT_SOURCE="${DIR}"
  per_test_setup

  echo "# Installing add-on with project ${PROJNAME} in $(pwd)" >&3
  
  # Verify .upsun directory exists
  assert [ -d .upsun ]
  assert [ -f .upsun/config.yaml ]
  
  # Verify config.upsun.yaml was created
  assert [ -f .ddev/config.upsun.yaml ]

  # Verify that the upsun add-on and its dependencies were added
  run bash -c "ddev add-on list --installed -j | jq -r .raw.[].Name"
  assert_success
  for addon in upsun redis ddev-opensearch memcached; do
    assert_output --partial "${addon}"
  done
  
  # Capture DDEV configuration once for multiple assertions
  run ddev debug configyaml --full-yaml 2>/dev/null
  assert_success
  config_yaml="$output"

  # Check that PHP version was parsed correctly
  run bash -c "echo '$config_yaml' | yq '.php_version'"
  assert_success
  assert_output "8.4"

  # Check that database config was parsed correctly
  run bash -c "echo '$config_yaml' | yq '.database.type'"
  assert_success
  assert_output "mariadb"

  run bash -c "echo '$config_yaml' | yq '.database.version'"
  assert_success
  assert_output "11.8"

  # Check that docroot was parsed correctly
  run bash -c "echo '$config_yaml' | yq '.docroot'"
  assert_success
  assert_output "web"

  # Check that Node.js version was parsed correctly
  run bash -c "echo '$config_yaml' | yq '.nodejs_version'"
  assert_success
  assert_output "20"

  # Check that environment variables were parsed correctly
  run bash -c "echo '$config_yaml' | yq '.web_environment[] | select(test(\"^N_PREFIX=\"))'"
  assert_success
  assert_output "N_PREFIX=/app/.global"

  # Check that PHP extensions were added as webimage_extra_packages
  run bash -c "echo '$config_yaml' | yq '.webimage_extra_packages[]'"
  assert_success
  assert_output --partial "php8.4-redis"

  # Check that Dockerfile.upsun was created with the /app symlink
  assert [ -f .ddev/web-build/Dockerfile.upsun ]
  
  # Check that the Dockerfile contains the symlink creation
  run bash -c "grep 'ln -sf /var/www/html /app' .ddev/web-build/Dockerfile.upsun"
  assert_success
  
  # Check that hooks are defined for build/deploy (if they exist in the config)
  # Note: hooks might be empty if no build/deploy hooks are defined in upsun config
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.hooks // {}'"
  assert_success
  
  # Verify mount directories were created
  assert [ -d web/sites/default/files ]
  assert [ -d tmp ]
  assert [ -d private ]
  assert [ -d .drush ]
  assert [ -d drush-backups ]

  # Start DDEV to apply configuration and build containers
  run ddev start -y
  assert_success
  
  # Verify DDEV started successfully with new configuration
  run ddev status
  assert_success
  assert_output --partial "OK"

  # If drush can show admin theme most things are working.
  run ddev drush status --field='Admin theme'
  assert_success
  assert_output --partial "claro"

  # Want to make sure database is accessible
  run bash -c 'echo "show tables;" | ddev drush sql-cli >/dev/null 2>&1 '
  assert_success

  # Test PHP version configuration
  run ddev php --version
  assert_success
  assert_output --partial "PHP 8.4"
  
  # Test Node.js version configuration
  run ddev exec node --version
  assert_success
  assert_output --partial "v20"
  
  # Test database version configuration
  run ddev exec -s db mysql --version
  assert_success
  assert_output --partial "11.8"
  
  # Test /app symlink was created during container build
  run ddev exec "ls -la /app"
  assert_success
  assert_output --partial "/var/www/html"
  
  # Verify /app symlink points to the correct location by checking content
  run ddev exec "ls /app"
  assert_success
  # Should see same contents as /var/www/html (web, composer.json, etc.)
  assert_output --partial "web"

  # Test environment variables are available
  run ddev exec "echo \$N_PREFIX"
  assert_success
  assert_output "/app/.global"
  
  # Test Redis service is running (should be installed by pre-start hook)
  run ddev redis-cli ping
  assert_success
  assert_output "PONG"
  
  # Test all PHP extensions from runtime config are loaded
  # Get the list of extensions from .upsun/config.yaml
  run bash -c "yq '.applications.drupal.runtime.extensions[]' .upsun/config.yaml | grep -v blackfire"
  assert_success
  
  # Test each detected extension (excluding blackfire which may not be available in DDEV)
  while IFS= read -r ext; do
    if [ -n "$ext" ]; then
      run ddev exec "php -r \"echo extension_loaded('$ext') ? 'loaded' : 'not loaded';\""
      assert_success
      assert_output "loaded"
    fi
  done <<< "$output"

  # Test database connectivity via environment variables
  run ddev exec "echo \$DB_HOST && echo \$DB_USERNAME && echo \$DB_PASSWORD"
  assert_success
  assert_output --partial "db"

  # Test database connectivity via PLATFORM_RELATIONSHIPS
  run ddev exec "echo \$PLATFORM_RELATIONSHIPS | base64 -d | jq -r \".mariadb[0].host\""
  assert_success
  assert_output "db"

  # Test actual database connectivity
  run ddev exec "mysql -h\$DB_HOST -u\$DB_USERNAME -p\$DB_PASSWORD \$DB_DATABASE -e 'SELECT 1;'"
  assert_success
  assert_output --partial "1"

  # Test MARIADB_* environment variables are available
  run ddev exec "echo \$MARIADB_HOST"
  assert_success
  assert_output "db"

  # Test Redis environment variables are available
  run ddev exec "echo \$REDIS_HOST"
  assert_success
  assert_output "redis"

  # Test cache connectivity via environment variables
  run ddev redis-cli -h redis ping
  assert_success
  assert_output "PONG"

  # Test critical PLATFORM_* variables exist and have correct values
  run ddev exec "echo \$PLATFORM_APP_DIR"
  assert_success
  assert_output "/app"

  run ddev exec "echo \$PLATFORM_DOCUMENT_ROOT"
  assert_success
  assert_output "/var/www/html/web"

  # Test PLATFORM_ROUTES contains correct URL
  run ddev exec "echo \$PLATFORM_ROUTES | base64 -d | jq -r \"keys[0]\""
  assert_success
  # Extract just the hostname from DDEV_PRIMARY_URL for comparison
  expected_url=$(ddev exec "echo \$DDEV_PRIMARY_URL")
  assert_output --partial "${expected_url}/"

  # Test drush configuration symlink exists
  run ddev exec "ls -la ~/.drush/drush.yml"
  assert_success
  assert_output --partial "/var/www/html/.drush/drush.yml"

  # Test drush Site URI is correctly configured
  run ddev drush status --fields=uri --format=string
  assert_success
  expected_url=$(ddev exec "echo \$DDEV_PRIMARY_URL")
  assert_output --partial "${expected_url}"

  # Test drush can connect to database
  run ddev drush status --fields=db-status --format=string
  assert_success
  assert_output "Connected"

  # Test that PLATFORM_PROJECT detection works
  run ddev exec "echo \$PLATFORM_PROJECT"
  assert_success
  # Should output the project ID, not be empty
  assert [ -n "$output" ]

  # Test add-on removal
  run ddev stop
  assert_success
  
  run ddev add-on remove upsun
  assert_success
  
  # Verify generated files were removed
  assert [ ! -f .ddev/config.upsun.yaml ]
  assert [ ! -f .ddev/web-build/Dockerfile.upsun ]
  
  # Verify project files were removed (they get installed in .ddev/upsun/)
  assert [ ! -f .ddev/upsun/install-hook.php ]
  assert [ ! -f .ddev/upsun/UpsunConfigGenerator.php ]
  assert [ ! -f .ddev/upsun/UpsunConfigParser.php ]
  assert [ ! -f .ddev/upsun/debug-parser.php ]
}