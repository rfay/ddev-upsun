#!/usr/bin/env bash

# Common assertion functions for Drupal 11 Upsun add-on tests

# Assert basic DDEV configuration matches expected values
assert_basic_config() {
  local expected_php_version="$1"
  local expected_db_type="$2"
  local expected_db_version="$3"

  # Capture DDEV configuration once for multiple assertions
  run ddev debug configyaml --full-yaml 2>/dev/null
  assert_success
  config_yaml="$output"

  # Check that PHP version was parsed correctly
  run bash -c "echo '$config_yaml' | yq '.php_version'"
  assert_success
  assert_output "$expected_php_version"

  # Check that database config was parsed correctly
  run bash -c "echo '$config_yaml' | yq '.database.type'"
  assert_success
  assert_output "$expected_db_type"

  run bash -c "echo '$config_yaml' | yq '.database.version'"
  assert_success
  assert_output "$expected_db_version"

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
  assert_output --partial "php${expected_php_version}-redis"
}

# Assert database connectivity for different database types
assert_database_connectivity() {
  local db_type="$1"
  local db_var_prefix="$2"  # MYSQL, MARIADB, or POSTGRESQL

  # Test database environment variables are available
  run ddev exec "echo \$${db_var_prefix}_HOST"
  assert_success
  assert_output "db"

  # Test database connectivity via environment variables
  run ddev exec "echo \$DB_HOST && echo \$DB_USERNAME && echo \$DB_PASSWORD"
  assert_success
  assert_output --partial "db"

  # Test database connectivity via PLATFORM_RELATIONSHIPS
  local relationship_key
  case $db_type in
    mysql) relationship_key="mysql" ;;
    mariadb) relationship_key="mariadb" ;;
    postgresql) relationship_key="postgresql" ;;
    *) echo "Unknown database type: $db_type" >&2; return 1 ;;
  esac

  run ddev exec "echo \$PLATFORM_RELATIONSHIPS | base64 -d | jq -r \".${relationship_key}[0].host\""
  assert_success
  assert_output "db"

  # Test actual database connectivity
  if [ "$db_type" = "postgresql" ]; then
    run ddev exec "PGPASSWORD=\$DB_PASSWORD psql -h\$DB_HOST -U\$DB_USERNAME \$DB_DATABASE -c 'SELECT 1;'"
  else
    run ddev exec "mysql -h\$DB_HOST -u\$DB_USERNAME -p\$DB_PASSWORD \$DB_DATABASE -e 'SELECT 1;'"
  fi
  assert_success
  assert_output --partial "1"
}

# Assert Redis and cache connectivity
assert_redis_connectivity() {
  # Test Redis service is running (should be installed by pre-start hook)
  run ddev redis-cli ping
  assert_success
  assert_output "PONG"

  # Test Redis environment variables are available
  run ddev exec "echo \$REDIS_HOST"
  assert_success
  assert_output "redis"

  # Test cache connectivity via environment variables
  run ddev redis-cli -h redis ping
  assert_success
  assert_output "PONG"
}

# Assert PLATFORM_* environment variables
assert_platform_variables() {
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
  # Get primary URL for comparison
  run ddev describe -j
  assert_success
  local describe_output="$output"
  local expected_url
  expected_url=$(echo "$describe_output" | jq -r '.raw.primary_url')

  # Check that PLATFORM_ROUTES key starts with the expected URL
  run ddev exec "echo \$PLATFORM_ROUTES | base64 -d | jq -r \"keys[0]\""
  assert_success
  assert_output --partial "${expected_url}/"

  # Test that PLATFORM_PROJECT detection works
  run ddev exec "echo \$PLATFORM_PROJECT"
  assert_success
  # Should output the project ID, not be empty
  assert [ -n "$output" ]
}

# Assert drush configuration and connectivity
assert_drush_functionality() {
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

  # If drush can show admin theme most things are working
  run ddev drush status --field='Admin theme'
  assert_success
  assert_output --partial "claro"

  # Database connectivity is already tested in assert_database_connectivity
}

# Assert PHP runtime and extensions
assert_php_runtime() {
  local expected_php_version="$1"

  # Test PHP version configuration
  run ddev php --version
  assert_success
  assert_output --partial "PHP ${expected_php_version}"

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
}

# Assert Node.js version
assert_nodejs_version() {
  run ddev exec node --version
  assert_success
  assert_output --partial "v20"
}

# Assert /app symlink functionality
assert_app_symlink() {
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
}

# Assert add-on installation and dependencies
assert_addon_installation() {
  # Verify that the upsun add-on and its dependencies were added
  run ddev add-on list --installed
  assert_success

  # Simple text-based check for add-on names in the output
  for addon in upsun redis ddev-opensearch memcached; do
    assert_output --partial "${addon}"
  done
}

# Assert file and directory structure
assert_file_structure() {
  # Verify .upsun directory exists
  assert [ -d .upsun ]
  assert [ -f .upsun/config.yaml ]

  # Verify config.upsun.yaml was created
  assert [ -f .ddev/config.upsun.yaml ]

  # Check that Dockerfile.upsun was created with the /app symlink
  assert [ -f .ddev/web-build/Dockerfile.upsun ]

  # Check that the Dockerfile contains the symlink creation
  run grep 'ln -sf /var/www/html /app' .ddev/web-build/Dockerfile.upsun
  assert_success

  # Verify mount directories were created
  assert [ -d web/sites/default/files ]
  assert [ -d tmp ]
  assert [ -d private ]
  assert [ -d .drush ]
  assert [ -d drush-backups ]
}

# Assert web functionality by testing actual HTTP response
assert_web_functionality() {
  # Get the primary URL from DDEV
  run ddev describe -j
  assert_success
  local describe_output="$output"
  local primary_url
  primary_url=$(echo "$describe_output" | jq -r '.raw.primary_url')

  # Test that we get a reasonable HTTP response code
  run curl -s -o /dev/null -w '%{http_code}' "${primary_url}"
  assert_success
  assert_output "200"

  # Test that the site responds with expected content
  run curl -s "${primary_url}"
  assert_success
  assert_output --partial "View recipe"
}

# Assert add-on removal
assert_addon_removal() {
  run ddev stop -y
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