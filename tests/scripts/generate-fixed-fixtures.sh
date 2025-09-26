#!/bin/bash

# Generate Fixed format test fixtures from existing Flex format fixtures
# This creates the minimum needed for Fixed format testing

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TESTS_DIR="$(dirname "$SCRIPT_DIR")"
TESTDATA_DIR="$TESTS_DIR/testdata"

# Function definitions
generate_fixed_config() {
    local variant="$1"
    local flex_config="$2"
    local output_dir="$3"

    echo "🔄 Parsing $flex_config..."

    # Extract key values from Flex config using basic parsing
    local php_version=$(grep "type: php:" "$flex_config" | sed 's/.*php://' | tr -d ' ')
    local app_name=$(grep -A5 "applications:" "$flex_config" | grep "^  [a-z]" | head -n1 | sed 's/://g' | tr -d ' ')

    # Create .platform.app.yaml
    create_platform_app_yaml "$variant" "$php_version" "$app_name" "$output_dir/.platform.app.yaml"

    # Create .platform/services.yaml
    create_platform_services_yaml "$variant" "$output_dir/.platform/services.yaml"

    # Create .platform/routes.yaml
    create_platform_routes_yaml "$variant" "$output_dir/.platform/routes.yaml"
}

create_platform_app_yaml() {
    local variant="$1"
    local php_version="$2"
    local app_name="$3"
    local output_file="$4"

    echo "📝 Creating .platform.app.yaml..."

    # Determine database relationship based on variant
    local db_relationship=""
    case "$variant" in
        "drupal11-mariadb") db_relationship="database: 'db:mysql'" ;;
        "drupal11-mysql") db_relationship="database: 'db:mysql'" ;;
        "drupal11-postgres") db_relationship="database: 'db:postgresql'" ;;
    esac

    cat > "$output_file" << EOF
# The name of this app. Must be unique within a project.
name: $app_name

# The runtime the application uses.
type: php:$php_version

# The relationships of the application with services or other applications.
relationships:
    $db_relationship
    redis: 'cache:redis'

# The configuration of app when it is exposed to the web.
web:
    locations:
        "/":
            root: "web"
            expires: 5m
            passthru: "/index.php"
            allow: false
            rules:
                '\.(jpe?g|png|gif|svgz?|css|js|map|ico|bmp|eot|woff2?|otf|ttf)$':
                    allow: true

# The size of the persistent disk of the application (in MB).
disk: 2048

# The 'mounts' describe writable, persistent filesystem mounts in the application.
mounts:
    '/web/sites/default/files':
        source: local
        source_path: 'files'
    '/tmp':
        source: local
        source_path: 'tmp'
    '/private':
        source: local
        source_path: 'private'
    '/.drush':
        source: local
        source_path: 'drush'
    '/drush-backups':
        source: local
        source_path: 'drush-backups'

# PHP extensions
runtime:
    extensions:
        - redis
        - sodium
        - apcu
        - blackfire
EOF
}

create_platform_services_yaml() {
    local variant="$1"
    local output_file="$2"

    echo "📝 Creating .platform/services.yaml..."

    # Determine database configuration based on variant
    case "$variant" in
        "drupal11-mariadb")
            cat > "$output_file" << EOF
# The services of the project.

db:
    type: mariadb:11.8
    disk: 2048

cache:
    type: redis:8.0
EOF
            ;;
        "drupal11-mysql")
            cat > "$output_file" << EOF
# The services of the project.

db:
    type: oracle-mysql:8.0
    disk: 2048

cache:
    type: redis:8.0
EOF
            ;;
        "drupal11-postgres")
            cat > "$output_file" << EOF
# The services of the project.

db:
    type: postgresql:17
    disk: 2048

cache:
    type: redis:8.0
EOF
            ;;
    esac
}

create_platform_routes_yaml() {
    local variant="$1"
    local output_file="$2"

    echo "📝 Creating .platform/routes.yaml..."

    cat > "$output_file" << EOF
# The routes of the project.

"https://{default}/":
    type: upstream
    upstream: "drupal:http"

"https://www.{default}/":
    type: redirect
    to: "https://{default}/"
EOF
}

# Main execution
echo "🔧 Generating Fixed format test fixtures..."

for variant in drupal11-mariadb drupal11-mysql drupal11-postgres; do
    echo "📦 Converting $variant to Fixed format..."

    FLEX_DIR="$TESTDATA_DIR/$variant"
    FIXED_DIR="$TESTDATA_DIR/$variant-fixed"

    if [[ ! -d "$FLEX_DIR" ]]; then
        echo "❌ Source fixture not found: $FLEX_DIR"
        continue
    fi

    # Remove existing fixed directory if it exists
    [[ -d "$FIXED_DIR" ]] && rm -rf "$FIXED_DIR"

    # Copy entire flex fixture as starting point
    cp -r "$FLEX_DIR" "$FIXED_DIR"
    echo "✅ Copied base fixture"

    # Remove Flex format files
    rm -rf "$FIXED_DIR/.upsun"
    echo "🗑️  Removed .upsun directory"

    # Clean up generated DDEV configuration but preserve only .ddev/.env
    if [[ -d "$FIXED_DIR/.ddev" ]]; then
        # Preserve the .env file
        if [[ -f "$FIXED_DIR/.ddev/.env" ]]; then
            cp "$FIXED_DIR/.ddev/.env" /tmp/ddev-env-backup
        fi

        # Remove entire .ddev directory (it has been populated from previous test runs)
        rm -rf "$FIXED_DIR/.ddev"

        # Restore only the .env file
        if [[ -f "/tmp/ddev-env-backup" ]]; then
            mkdir -p "$FIXED_DIR/.ddev"
            cp /tmp/ddev-env-backup "$FIXED_DIR/.ddev/.env"
            rm /tmp/ddev-env-backup
        fi
        echo "🗑️  Cleaned DDEV directory (preserved only .env file)"
    fi

    # Create Fixed format directory structure
    mkdir -p "$FIXED_DIR/.platform"

    # Generate Fixed format files from Flex config
    generate_fixed_config "$variant" "$FLEX_DIR/.upsun/config.yaml" "$FIXED_DIR"

    echo "✅ Generated Fixed format config for $variant"
    echo ""
done

echo "🎉 All Fixed format fixtures generated!"