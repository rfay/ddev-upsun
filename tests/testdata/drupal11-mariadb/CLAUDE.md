# Test Upsun Add-on Repository

## Purpose

This repository serves as an authoritative source of working Upsun configurations for testing the [ddev-upsun add-on](https://github.com/rfay/ddev-upsun). Each branch contains verified, deployable configurations for different project types.

## About Upsun

Upsun is a Platform-as-a-Service (PaaS) that allows you to develop, deploy, and scale applications in the cloud. 

- **Documentation**: https://docs.upsun.com/
- **Getting Started**: https://docs.upsun.com/get-started/
- **Console**: https://console.upsun.com/

## Using the Upsun Command

### Installation
Follow the installation guide: https://docs.upsun.com/administration/cli/install.html

### Getting Help
- `upsun list` - Shows all available commands
- `upsun help [command]` - Get help for a specific command
- `upsun --help` - General help and global options

### Common Commands
- `upsun environment:url` - Get the URL of your deployed site
- `upsun activity:list` - View deployment history and status
- `upsun activity:log [id]` - View detailed logs for a specific deployment (essential for debugging)
- `upsun activity:log` - View logs for the most recent activity
- `upsun environment:list` - List all environments
- `upsun ssh` - SSH into your environment
- `upsun ssh -e <environment>` - SSH into specific environment
- `upsun ssh -e <environment> '<command>'` - Run command directly via SSH

### Project Structure
- **Main branch**: Basic HTML/Markdown static site with pandoc auto-generation
- **drupal11 branch**: Fully functional Drupal 11 site with image styles, AVIF support
- **Future branches**: Framework-specific configurations (Laravel, etc.)

## Key Learnings from Drupal 11 Implementation

### Critical Configuration Requirements

#### 1. Relationship Naming Determines Environment Variables
The relationship name in `.upsun/config.yaml` determines the environment variable prefix:

```yaml
relationships:
  mariadb: "mariadb:mysql"  # Creates MARIADB_HOST, MARIADB_PORT, etc.
  database: "mariadb:mysql" # Would create DATABASE_HOST, DATABASE_PORT, etc.
```

The relationship name **must match** what your `.environment` file expects.

#### 2. Environment Variable Mapping (.environment file)
Upsun provides service connection info via `PLATFORM_RELATIONSHIPS` JSON, but Drupal expects `DB_*` variables. The `.environment` file maps these:

```bash
export DB_HOST="$MARIADB_HOST"
export DB_PORT="$MARIADB_PORT"  
export DB_PATH="$MARIADB_PATH"
export DB_USERNAME="$MARIADB_USERNAME"
export DB_PASSWORD="$MARIADB_PASSWORD"
export DB_SCHEME="$MARIADB_SCHEME"
```

#### 3. Web Configuration: Nginx vs Apache
**Critical**: Upsun uses **Nginx**, not Apache. `.htaccess` files are completely ignored.

For Drupal image styles to work, you need proper web location configuration:

```yaml
web:
  locations:
    "/":
      root: "web"
      expires: 5m
      passthru: "/index.php"
      allow: false
      rules:
        '\.(avif|webp|jpe?g|png|gif|svgz?|css|js|map|ico|bmp|eot|woff2?|otf|ttf)$':
          allow: true
        '^/sites/[^/]+/settings.*?\.php$':
          scripts: false
    "/sites/default/files":
      root: "web/sites/default/files"  
      allow: true
      expires: 5m
      passthru: "/index.php"  # CRITICAL for on-demand image style generation
      scripts: false
```

The `passthru: "/index.php"` in `/sites/default/files` is **essential** - without it, image styles return 404.

#### 4. Required Dependencies and Scripts
- **platformsh/config-reader** - Required for parsing Upsun environment
- **Drush scripts** - Use official versions from [upsun/snippets](https://github.com/upsun/snippets/tree/main/examples/drupal11/drush):
  - `drush/upsun_generate_drush_yml.php` - Creates drush.yml with site URL
  - `drush/upsun_deploy_drupal.sh` - Handles cache rebuild, DB updates, config import

#### 5. Complete Mount Configuration
```yaml
mounts:
  "/web/sites/default/files": "shared:files/files"
  "/tmp": "shared:files/tmp" 
  "/private": "shared:files/private"
  "/.drush": "shared:files/drush"        # Required for drush.yml generation
  "/drush-backups": "shared:files/drush-backups"
```

### Essential Debugging Commands
- `upsun activity:log` - View deployment logs
- `upsun ssh -e <env> 'env | grep DB_'` - Check database variables
- `upsun ssh -e <env> 'env | grep MARIADB_'` - Check service variables  
- `upsun ssh -e <env> 'echo $PLATFORM_RELATIONSHIPS | base64 -d | jq'` - View raw relationships
- `upsun ssh -e <env> 'cd web && drush st'` - Check Drupal status
- `upsun ssh -e <env> 'cd web && drush sql-cli'` - Test database connection

### Common Issues and Solutions

#### Image Styles Return 404
**Problem**: Missing `passthru: "/index.php"` in `/sites/default/files` location  
**Solution**: Add passthru rule to allow Drupal to generate styles on-demand

#### Missing DB Environment Variables  
**Problem**: Wrong relationship name in config  
**Solution**: Relationship name must match what `.environment` file expects (e.g., `mariadb` → `MARIADB_*`)

#### Config-reader Class Not Found
**Problem**: Missing autoloader in drush scripts  
**Solution**: Add `require_once(__DIR__ . '/../vendor/autoload.php');` to PHP scripts

#### Drush Commands Not Found During Deploy
**Problem**: Drush not in PATH or wrong path reference  
**Solution**: Use `vendor/bin/drush` or ensure PATH includes composer bin directory

## Resources

### Official Documentation
- **Drupal on Upsun**: https://docs.upsun.com/get-started/stacks/drupal.html
- **Laravel on Upsun**: https://docs.upsun.com/get-started/stacks/laravel.html  
- **Web Configuration**: https://docs.upsun.com/create-apps/app-reference/single-runtime-image.html

### Official Examples
- **Upsun Snippets**: https://github.com/upsun/snippets/tree/main/examples
- **Drupal 11 Example**: https://github.com/upsun/snippets/tree/main/examples/drupal11
- **Dev Center Posts**: https://devcenter.upsun.com/posts/drupal-and-upsun/

### Key Insight: Always Use Official Examples
The official examples in `upsun/snippets` are the most reliable source for configuration patterns. The documentation sometimes lags behind, but the examples are tested and working.

## Upsun Repository Patterns Discovered

### Official Resource Types
1. **`upsun/snippets`** - Production-ready config examples (Drupal 11, etc.)
2. **`upsun/[framework]-scaffold`** - Composer plugins for automatic setup (Drupal only so far)
3. **`upsun/demo-project-[framework]`** - Tutorial/learning projects (Symfony found)
4. **`platformsh-templates/[framework]`** - Platform.sh templates (Laravel found)

### Laravel Implementation Plan

**Important Discovery**: `platformsh-templates/laravel` exists but uses **different configuration format** from Upsun (`.platform.app.yaml` vs `.upsun/config.yaml`). These templates are **not directly compatible**.

**Approach**: Create Laravel implementation from scratch using:
- **Upsun Laravel Guide**: https://docs.upsun.com/get-started/stacks/laravel.html
- **Upsun Config Format**: Use `.upsun/config.yaml` syntax
- **Official Snippets Pattern**: Follow same approach as successful Drupal 11 implementation

**Next Steps**:
1. Create simple Laravel project as starting point
2. Build `.upsun/config.yaml` for Laravel (PHP 8.3+ runtime)
3. Configure database relationships and environment variables
4. Test deployment and document learnings
5. Create `laravel` branch as reference implementation