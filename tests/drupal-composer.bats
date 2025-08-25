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
  
  # Use ddev debug configyaml --full-yaml with yq to check parsed configuration values
  
  # Check that PHP version was parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.php_version'"
  assert_success
  assert_output "8.3"
  
  # Check that database config was parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.database.type'"
  assert_success
  assert_output "mariadb"
  
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.database.version'"
  assert_success
  assert_output "10.11"
  
  # Check that docroot was parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.docroot'"
  assert_success
  assert_output "web"
  
  # Check that environment variables were parsed correctly using ddev debug configyaml --full-yaml
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.web_environment | length'"
  assert_success
  assert_output "3"
  
  # Check specific environment variables
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.web_environment[] | select(test(\"^PHP_MEMORY_LIMIT=\"))'"
  assert_success
  assert_output "PHP_MEMORY_LIMIT=256M"
  
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.web_environment[] | select(test(\"^PHP_MAX_EXECUTION_TIME=\"))'"
  assert_success
  assert_output "PHP_MAX_EXECUTION_TIME=300"
  
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.web_environment[] | select(test(\"^DRUPAL_ENVIRONMENT=\"))'"
  assert_success
  assert_output "DRUPAL_ENVIRONMENT=production"
  
  # Restart to apply configuration
  run ddev restart
  assert_success
  
  # Verify DDEV started successfully with new configuration
  run ddev status
  assert_success
  assert_output --partial "OK"
}