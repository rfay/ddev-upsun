#!/usr/bin/env bats

# Unified test suite for both Upsun Flex and Fixed configuration formats
# Tests all database variants (MariaDB, MySQL, PostgreSQL) with both formats

setup() {
  set -eu -o pipefail

  export GITHUB_REPO=rfay/ddev-upsun

  TEST_BREW_PREFIX="$(brew --prefix 2>/dev/null || true)"
  export BATS_LIB_PATH="${BATS_LIB_PATH}:${TEST_BREW_PREFIX}/lib:/usr/lib/bats"
  bats_load_library bats-assert
  bats_load_library bats-file
  bats_load_library bats-support

  export DIR="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  export PROJECT_SOURCE="$(cd "$(dirname "${BATS_TEST_FILENAME}")/.." >/dev/null 2>&1 && pwd)"
  mkdir -p ~/tmp
  export DDEV_NONINTERACTIVE=true
  export DDEV_NO_INSTRUMENTATION=true

  # Extract test parameters from BATS test name
  # Expected format: "test-drupal11-mariadb-flex" or "test-drupal11-postgres-fixed"
  if [[ "${BATS_TEST_DESCRIPTION}" =~ test-drupal11-([^-]+)-([^-]+) ]]; then
    export TEST_DATABASE="${BASH_REMATCH[1]}"    # mariadb/mysql/postgres
    export TEST_FORMAT="${BASH_REMATCH[2]}"      # flex/fixed
    export PROJNAME="test-drupal11-${TEST_DATABASE}-${TEST_FORMAT}"

    # Map to fixture directory name (now consistently named with format suffix)
    export testname="drupal11-${TEST_DATABASE}-${TEST_FORMAT}"
  else
    skip "Invalid test name format: ${BATS_TEST_DESCRIPTION}"
  fi

  # Set expectations based on test variant
  set_test_expectations

  export TESTDIR=$(mktemp -d ~/tmp/${PROJNAME}.XXXXXX)
  ddev delete -Oy "${PROJNAME}" >/dev/null 2>&1 || true
  cd "${TESTDIR}"

  # Copy appropriate test fixture
  cp -r "${DIR}/tests/testdata/${testname}/." .

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

set_test_expectations() {
  # Set expected values based on test variant
  case "${TEST_DATABASE}-${TEST_FORMAT}" in
    "mariadb-flex"|"mariadb-fixed")
      export EXPECTED_PHP_VERSION="8.3"
      export EXPECTED_DB_TYPE="mariadb"
      export EXPECTED_DB_VERSION="11.8"
      export EXPECTED_DDEV_DB_CONFIG="mariadb:11.8"
      ;;
    "mysql-flex"|"mysql-fixed")
      export EXPECTED_PHP_VERSION="8.4"
      export EXPECTED_DB_TYPE="mysql"
      export EXPECTED_DB_VERSION="8.0"
      export EXPECTED_DDEV_DB_CONFIG="mysql:8.0"
      ;;
    "postgres-flex"|"postgres-fixed")
      export EXPECTED_PHP_VERSION="8.3"
      export EXPECTED_DB_TYPE="postgres"
      export EXPECTED_DB_VERSION="17"
      export EXPECTED_DDEV_DB_CONFIG="postgres:17"
      ;;
    *)
      export EXPECTED_PHP_VERSION="8.3"
      export EXPECTED_DB_TYPE="mariadb"
      export EXPECTED_DB_VERSION="11.8"
      export EXPECTED_DDEV_DB_CONFIG="mariadb:11.8"
      ;;
  esac

  # Set expected format detection message
  if [[ "${TEST_FORMAT}" == "fixed" ]]; then
    export EXPECTED_FORMAT_MESSAGE="Fixed format"
  else
    export EXPECTED_FORMAT_MESSAGE="Flex format"
  fi
}

run_unified_test() {
  # Common test suite for all variants

  # Install add-on and start project
  source "${DIR}/tests/per_test.sh"
  per_test_setup

  # Test project is running properly
  echo "# Testing ${TEST_FORMAT} format configuration" >&3

  # Check that project is running first
  run ddev describe
  assert_success

  # Test PHP version configuration
  echo "# Testing PHP version: expected ${EXPECTED_PHP_VERSION}" >&3
  run ddev exec "php -v"
  assert_success
  assert_output --partial "PHP ${EXPECTED_PHP_VERSION}"

  # Test database configuration
  echo "# Testing database: expected ${EXPECTED_DDEV_DB_CONFIG}" >&3
  run ddev describe -j
  assert_success
  # Extract database info from JSON output
  db_type=$(echo "$output" | jq -r '.raw.database_type' 2>/dev/null || echo "")
  db_version=$(echo "$output" | jq -r '.raw.database_version' 2>/dev/null || echo "")
  [[ "$db_type" == "${EXPECTED_DB_TYPE}" ]] || (echo "Database type mismatch: got '$db_type', expected '${EXPECTED_DB_TYPE}'" && false)
  [[ "$db_version" == "${EXPECTED_DB_VERSION}" ]] || (echo "Database version mismatch: got '$db_version', expected '${EXPECTED_DB_VERSION}'" && false)

  # Test Redis service is installed and running
  echo "# Testing Redis service" >&3
  run ddev describe -j
  assert_success
  redis_service=$(echo "$output" | jq -r '.raw.services.redis.short_name' 2>/dev/null || echo "")
  [[ "$redis_service" == "redis" ]] || (echo "Redis service mismatch: got '$redis_service', expected 'redis'" && false)

  # Test environment variables are set correctly
  echo "# Testing environment variables" >&3
  run ddev exec "env | grep PLATFORM_"
  assert_success
  assert_output --partial "PLATFORM_PROJECT="
  assert_output --partial "PLATFORM_ENVIRONMENT="

  # Test database connectivity
  echo "# Testing database connectivity" >&3
  if [[ "${EXPECTED_DB_TYPE}" == "postgres" ]]; then
    run ddev exec "psql -h db -U db -d db -c 'SELECT 1;'"
  else
    run ddev exec "mysql -h db -u db -pdb -e 'SELECT 1;'"
  fi
  assert_success

  # Test Redis connectivity
  echo "# Testing Redis connectivity" >&3
  run ddev redis-cli ping
  assert_success
  assert_output "PONG"

  # Test Drupal is working
  echo "# Testing Drupal functionality" >&3
  run ddev exec "cd web && ../vendor/bin/drush status --field=bootstrap"
  assert_success
  assert_output "Successful"

  # Format-specific additional tests
  if [[ "${TEST_FORMAT}" == "fixed" ]]; then
    echo "# Testing Fixed format specific features" >&3
    # Verify Fixed format files exist in fixture
    assert_file_exists ".platform.app.yaml"
    assert_file_exists ".platform/services.yaml"
    assert_file_not_exists ".upsun"

    # Test that Fixed format service detection worked by checking Redis is available
    run ddev redis-cli ping
    assert_success
    assert_output "PONG"
  else
    echo "# Testing Flex format specific features" >&3
    # Verify Flex format files exist in fixture
    assert_file_exists ".upsun/config.yaml"
    assert_file_not_exists ".platform.app.yaml"
  fi

  echo "# All tests passed for ${testname}!" >&3
}

# Test matrix - all 6 combinations
@test "test-drupal11-mariadb-flex" {
  run_unified_test
}

@test "test-drupal11-mariadb-fixed" {
  run_unified_test
}

@test "test-drupal11-mysql-flex" {
  run_unified_test
}

@test "test-drupal11-mysql-fixed" {
  run_unified_test
}

@test "test-drupal11-postgres-flex" {
  run_unified_test
}

@test "test-drupal11-postgres-fixed" {
  run_unified_test
}