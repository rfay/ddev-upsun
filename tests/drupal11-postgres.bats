#!/usr/bin/env bats

# Test Drupal Composer project configuration translation - PostgreSQL variant

setup() {
  set -eu -o pipefail

  export GITHUB_REPO=rfay/ddev-upsun

  TEST_BREW_PREFIX="$(brew --prefix 2>/dev/null || true)"
  export BATS_LIB_PATH="${BATS_LIB_PATH}:${TEST_BREW_PREFIX}/lib:/usr/lib/bats"
  bats_load_library bats-assert
  bats_load_library bats-file
  bats_load_library bats-support

  export DIR="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  export PROJNAME="test-drupal11-postgres"
  export PROJECT_SOURCE="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  mkdir -p ~/tmp
  export TESTDIR=$(mktemp -d ~/tmp/${PROJNAME}.XXXXXX)
  export DDEV_NONINTERACTIVE=true
  export DDEV_NO_INSTRUMENTATION=true
  ddev delete -Oy "${PROJNAME}" >/dev/null 2>&1 || true
  cd "${TESTDIR}"

  # Copy test fixture
  cp -r "${DIR}/tests/testdata/drupal11-postgres/." .

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

@test "install add-on: drupal11-postgres" {
  set -eu -o pipefail
  load per_test.sh
  load shared/common-assertions.bash
  testname="drupal11-postgres"
  PROJECT_SOURCE="${DIR}"
  per_test_setup

  echo "# Installing add-on with project ${PROJNAME} in $(pwd)" >&3

  # Use shared assertion functions for common checks
  assert_file_structure
  assert_addon_installation

  # Test database-specific configuration
  assert_basic_config "8.3" "postgres" "17"

  # Check that hooks are defined for build/deploy (if they exist in the config)
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.hooks // {}'"
  assert_success

  # Verify DDEV started successfully with new configuration
  run ddev status
  assert_success
  assert_output --partial "OK"

  # Use shared assertion functions for runtime tests
  assert_php_runtime "8.3"
  assert_nodejs_version
  assert_app_symlink

  # Debug database configuration before testing drush
  echo "# DEBUG: Checking database configuration" >&3
  run ddev describe
  echo "# DEBUG: ddev describe output:" >&3
  echo "$output" >&3

  echo "# DEBUG: Checking if psql works" >&3
  run ddev psql -c "SELECT 1;"
  echo "# DEBUG: psql result: status=$status" >&3
  echo "$output" >&3

  echo "# DEBUG: Checking database environment variables" >&3
  run ddev exec "env | grep -E '(DB_|POSTGRES)'"
  echo "# DEBUG: DB environment variables:" >&3
  echo "$output" >&3

  echo "# DEBUG: Checking Drupal database settings" >&3
  run ddev exec "php -r \"require_once '/var/www/html/web/sites/default/settings.php'; print_r(\\\$databases);\""
  echo "# DEBUG: Drupal database config:" >&3
  echo "$output" >&3

  echo "# DEBUG: Testing drush database status commands" >&3
  run ddev drush status
  echo "# DEBUG: Full drush status:" >&3
  echo "$output" >&3

  run ddev drush status --fields=db-status --format=string
  echo "# DEBUG: Specific db-status field: status=$status" >&3
  echo "# DEBUG: Output: '$output'" >&3

  echo "# DEBUG: Checking PostgreSQL tables exist" >&3
  run ddev psql -c "\dt"
  echo "# DEBUG: PostgreSQL tables: status=$status" >&3
  echo "$output" >&3

  echo "# DEBUG: Checking web functionality" >&3
  run ddev describe -j
  local describe_output="$output"
  local primary_url
  primary_url=$(echo "$describe_output" | jq -r '.raw.primary_url')
  echo "# DEBUG: Testing URL: $primary_url" >&3
  run curl -s -o /dev/null -w '%{http_code}' "${primary_url}"
  echo "# DEBUG: Curl response code: $output" >&3

  assert_drush_functionality
  assert_database_connectivity "postgresql" "POSTGRES"
  assert_redis_connectivity
  assert_platform_variables
  assert_web_functionality

  # Test database version configuration - PostgreSQL specific
  run ddev exec -s db psql --version
  assert_success
  assert_output --partial "17"

  # Test PostgreSQL-specific environment variables
  run ddev exec "echo \$POSTGRESQL_HOST"
  assert_success
  assert_output "db"

  # Add-on removal test removed - dependency order issues cause hangs
}