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
  export PROJNAME="test-drupal-composer"
  mkdir -p ~/tmp
  export TESTDIR=$(mktemp -d ~/tmp/${PROJNAME}.XXXXXX)
  export DDEV_NONINTERACTIVE=true
  export DDEV_NO_INSTRUMENTATION=true
  ddev delete -Oy "${PROJNAME}" >/dev/null 2>&1 || true
  cd "${TESTDIR}"
  
  # Copy test fixture
  cp -r "${DIR}/tests/testdata/drupal-composer/." .
  
  # Configure DDEV project
  run ddev config --project-name="${PROJNAME}" --project-type=drupal11 --docroot=web
  assert_success
  
  # Start DDEV
  run ddev start
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

@test "install add-on with drupal-composer test fixture" {
  set -eu -o pipefail
  
  echo "# Installing add-on with project ${PROJNAME} in $(pwd)" >&3
  
  # Verify .upsun directory exists
  assert [ -d .upsun ]
  assert [ -f .upsun/config.yaml ]
  
  # Install the add-on
  run ddev add-on get "${DIR}"
  assert_success
  
  # Verify config.upsun.yaml was created
  assert [ -f .ddev/config.upsun.yaml ]
  
  # Check that PHP version was parsed correctly
  run grep "php_version.*8\.3" .ddev/config.upsun.yaml
  assert_success
  
  # Check that database config was parsed correctly  
  run grep -A 1 "database:" .ddev/config.upsun.yaml
  assert_success
  run grep "type.*mariadb" .ddev/config.upsun.yaml
  assert_success
  run grep "version.*10\.11" .ddev/config.upsun.yaml
  assert_success
  
  # Check that docroot was parsed correctly
  run grep "docroot.*web" .ddev/config.upsun.yaml
  assert_success
  
  # Check that application name was parsed correctly
  run grep "name.*drupal-composer" .ddev/config.upsun.yaml
  assert_success
  
  # Verify environment file was created with PHP settings
  assert [ -f .ddev/.env.upsun ]
  run grep "PHP_MEMORY_LIMIT=256M" .ddev/.env.upsun
  assert_success
  run grep "PHP_MAX_EXECUTION_TIME=300" .ddev/.env.upsun
  assert_success
  run grep "DRUPAL_ENVIRONMENT=production" .ddev/.env.upsun
  assert_success
  
  # Restart to apply configuration
  run ddev restart
  assert_success
  
  # Verify DDEV started successfully with new configuration
  run ddev status
  assert_success
  assert_output --partial "OK"
}