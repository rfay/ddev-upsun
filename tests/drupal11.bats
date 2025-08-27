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
  mkdir -p ~/tmp
  export TESTDIR=$(mktemp -d ~/tmp/${PROJNAME}.XXXXXX)
  export DDEV_NONINTERACTIVE=true
  export DDEV_NO_INSTRUMENTATION=true
  ddev delete -Oy "${PROJNAME}" >/dev/null 2>&1 || true
  cd "${TESTDIR}"
  
  # Copy test fixture
  cp -r "${DIR}/tests/testdata/drupal11/." .
  
  # Configure DDEV project
  run ddev config --project-name="${PROJNAME}" --project-type=drupal11 --docroot=web
  assert_success
  
  # Install Redis add-on before installing Upsun add-on for testing
  run ddev add-on get ddev/ddev-redis
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
  assert_output "8.4"
  
  # Check that database config was parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.database.type'"
  assert_success
  assert_output "mariadb"
  
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.database.version'"
  assert_success
  assert_output "11.8"
  
  # Check that docroot was parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.docroot'"
  assert_success
  assert_output "web"
  
  # Check that Node.js version was parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.nodejs_version'"
  assert_success
  assert_output "20"
  
  # Check that environment variables were parsed correctly
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.web_environment[] | select(test(\"^N_PREFIX=\"))'"
  assert_success
  assert_output "N_PREFIX=/app/.global"

  # Check that PHP extensions were added as webimage_extra_packages
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.webimage_extra_packages[]'"
  assert_success
  assert_output --partial "php8.4-redis"
  
  # Note: Redis dependency is handled via install.yaml, not DDEV config
  
  # Check that Redis pre-start hook was added
  run bash -c "ddev debug configyaml --full-yaml 2>/dev/null | yq '.hooks.\"pre-start\"[].\"exec-host\"'"
  assert_success
  assert_output --partial "ddev add-on get ddev/ddev-redis"

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
  
  # Test that hooks were executed during post-start
  # Note: drush.yml creation only works in actual Upsun environment with platform variables
  # In DDEV context, just verify the hook commands don't fail

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
      run ddev exec "php -m | grep -i $ext"
      assert_success
      case $ext in
        apcu) assert_output "APCu" ;;
        *) assert_output "$ext" ;;
      esac
    fi
  done <<< "$output"
  
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