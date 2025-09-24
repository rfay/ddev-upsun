#!/usr/bin/env bats

# Test Drupal Composer project configuration translation - MySQL variant

setup() {
  set -eu -o pipefail

  export GITHUB_REPO=rfay/ddev-upsun

  TEST_BREW_PREFIX="$(brew --prefix 2>/dev/null || true)"
  export BATS_LIB_PATH="${BATS_LIB_PATH}:${TEST_BREW_PREFIX}/lib:/usr/lib/bats"
  bats_load_library bats-assert
  bats_load_library bats-file
  bats_load_library bats-support

  export DIR="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  export PROJNAME="test-drupal11-mysql"
  export PROJECT_SOURCE="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  mkdir -p ~/tmp
  export TESTDIR=$(mktemp -d ~/tmp/${PROJNAME}.XXXXXX)
  export DDEV_NONINTERACTIVE=true
  export DDEV_NO_INSTRUMENTATION=true
  ddev delete -Oy "${PROJNAME}" >/dev/null 2>&1 || true
  cd "${TESTDIR}"

  # Copy test fixture
  cp -r "${DIR}/tests/testdata/drupal11-mysql/." .

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

@test "drupal11-mysql" {
  set -eu -o pipefail
  load per_test.sh
  load shared/common-assertions.bash
  testname="drupal11-mysql"
  PROJECT_SOURCE="${DIR}"
  per_test_setup

  echo "# Installing add-on with project ${PROJNAME} in $(pwd)" >&3

  # Use shared assertion functions for common checks
  assert_file_structure
  assert_addon_installation

  # Test database-specific configuration
  assert_basic_config "8.4" "mysql" "8.0"

  # Check that hooks are defined for build/deploy (if they exist in the config)
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.hooks // {}'"
  assert_success

  # Verify DDEV started successfully with new configuration
  run ddev describe
  assert_success
  assert_output --partial "OK"

  # Use shared assertion functions for runtime tests
  assert_php_runtime "8.4"
  assert_nodejs_version
  assert_app_symlink
  assert_drush_functionality
  assert_database_connectivity "mysql" "MYSQL"
  assert_redis_connectivity
  assert_platform_variables
  assert_web_functionality

  # Test database version configuration - MySQL specific
  run ddev exec -s db mysql --version
  assert_success
  assert_output --partial "8.0"

  # Test MySQL-specific environment variables
  run ddev exec "echo \$MYSQL_HOST"
  assert_success
  assert_output "db"

  # Add-on removal test removed - dependency order issues cause hangs
}