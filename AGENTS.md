# AGENTS.md

This file provides guidance to AI agents when working with the ddev-upsun add-on codebase.

## DDEV Upsun Add-on Project Overview

This is a DDEV add-on that provides experimental integration between [Upsun](https://upsun.com/) projects and [DDEV](https://ddev.com). It parses Upsun `.upsun/config.yaml` configuration files and generates equivalent DDEV settings for local development.

**⚠️ This add-on is experimental and under active development.**

For comprehensive DDEV add-on development documentation, see:

- [DDEV Add-on Development](https://docs.ddev.com/en/stable/users/extend/creating-add-ons/) - Complete add-on development guide
- [PHP-based Add-on Actions](https://docs.ddev.com/en/stable/users/extend/creating-add-ons/#action-types-bash-vs-php) - New PHP-based action technique used by this add-on

## Key Development Commands

### Testing

**Automated Tests:**
- `bats tests` - Run all add-on tests (primary testing strategy)

**PHP Container Testing:**
- PHP scripts are executed in DDEV containers during installation via `<?php` blocks in install.yaml
- Scripts run in ephemeral containers using the project's webserver image
- PHP tests serve as adjuncts to the main bats testing strategy
- Reference examples: [ddev-redis-php](https://github.com/rfay/ddev-redis-php) and [ddev-platformsh-php](https://github.com/rfay/ddev-platformsh-php)

**Manual Testing:**
- Create test project: `cd ~/tmp/ddev-test && ddev config --project-type=php`
- Install local add-on: `ddev add-on get ~/workspace/ddev-upsun`
- Test with sample `.upsun` configuration directory
- Verify `ddev pull upsun` functionality

**Test Projects:**
- Keep sample Upsun test fixtures in `tests/testdata/`
- Test various PHP versions (8.1-8.4)
- Test database types (MySQL, MariaDB, PostgreSQL)
- Test variety of services (ApacheSolr and Elasticsearch come next)

### Whitespace and Formatting

- **Never add trailing whitespace** - Blank lines must be completely empty (no spaces or tabs)
- Match existing indentation style exactly (spaces vs tabs, indentation depth)
- Preserve the file's existing line ending style
- Run linting tools to catch whitespace issues before committing

## Architecture

### Core Components

**Main Configuration:**
- `install.yaml` - Primary add-on configuration file
- `src/` - PHP source files for configuration parsing (executed in DDEV web container)
- `tests/` - Bats test files for add-on functionality
- `docker-compose.*.yaml` - Additional service definitions (if needed)

**Test Configurations:**
- `tests/testdata/drupal11-mariadb/` - Drupal 11 with MariaDB test configuration
- `tests/testdata/drupal11-mysql/` - Drupal 11 with MySQL test configuration
- `tests/testdata/drupal11-postgres/` - Drupal 11 with PostgreSQL test configuration

### Project Structure

The add-on follows DDEV add-on conventions:

- `install.yaml` - Add-on installation configuration and PHP action scripts
- `src/` - PHP classes for parsing Upsun configurations
- `tests/` - Bats test suite with test data fixtures
- `tests/testdata/` - Sample Upsun project configurations for testing

### Configuration System

The add-on processes Upsun configuration files:

- `.upsun/config.yaml` - Primary Upsun project configuration (INPUT)
- `.ddev/config.yaml` - Generated DDEV project configuration (OUTPUT)
- `.ddev/config.upsun.yaml` - Add-on specific DDEV configuration (OUTPUT)
- `.ddev/.env` - Environment variables for DDEV (OUTPUT)

## Development Notes

### Upsun Integration

**Configuration Source:**
- `.upsun/` directory contains Upsun project configuration files
- These are INPUT files that the add-on parses to CREATE DDEV configuration
- Common files: `.upsun/config.yaml`, `.upsun/.platform.app.yaml` equivalents

**Generated DDEV Configuration:**
- `.ddev/config.upsun.yaml` (add-on specific config)
- `.ddev/.env.upsun` (environment variables)

**Supported Translations:**
- PHP runtime versions to DDEV equivalents
- Database services (mysql, mariadb, postgresql)
- Environment variables and relationships
- Basic application configuration

**Unsupported Features (Error/Warn):**
- Multi-app configurations
- Complex service relationships beyond single database
- Workers and crons
- Advanced networking configurations

### Runtime Environment
- PHP parsing logic runs inside DDEV web container (no local PHP required)
- Upsun CLI available in DDEV web container for pull operations
- All file operations occur within DDEV context

### Development Workflow
- Test with real Upsun projects in `~/tmp/`
- Use `ddev get .` for local add-on installation during development
- Validate against DDEV add-on template requirements

### Code Quality

**Testing Strategy:**
- Use Bats framework for integration testing
- Test with multiple database types (MySQL, MariaDB, PostgreSQL)
- Test with multiple PHP versions (8.1-8.4)
- Validate generated DDEV configurations work correctly

**Security & Configuration:**
- Never commit secrets or API keys
- Handle Upsun credentials securely in pull operations
- Validate configuration files before processing

### Development Environment Setup

- **Temporary files**: Use `~/tmp` for temporary directories and test projects
- **Local testing**: Always test add-on installation with local path before publishing

## DDEV Add-on Development

### Project Structure
- `install.yaml` - Primary add-on configuration file
- `tests/` - Bats test files for add-on functionality
- `src/` - PHP source files for configuration parsing (executed in DDEV web container)
- `docker-compose.*.yaml` - Additional service definitions (if needed)

### Development Workflow
- Test with real Upsun projects in `~/tmp/`
- Use `ddev get .` for local add-on installation during development
- Validate against DDEV add-on template requirements

### Upsun Integration

**Configuration Source:**
- `.upsun/` directory contains Upsun project configuration files
- These are INPUT files that the add-on parses to CREATE DDEV configuration
- Common files: `.upsun/config.yaml`, `.upsun/.platform.app.yaml` equivalents

**Generated DDEV Configuration:**
- `.ddev/config.upsun.yaml` (add-on specific config)
- `.ddev/.env.upsun` (environment variables)

**Supported Translations:**
- PHP runtime versions to DDEV equivalents
- Database services (mysql, mariadb, postgresql)
- Environment variables and relationships
- Basic application configuration

**Unsupported Features (Error/Warn):**
- Multi-app configurations
- Complex service relationships beyond single database
- Workers and crons
- Advanced networking configurations

### Runtime Environment
- PHP parsing logic runs inside DDEV web container (no local PHP required)
- Upsun CLI available in DDEV web container for pull operations
- All file operations occur within DDEV context

## General DDEV Development Patterns

For standard DDEV organization patterns including communication style, branch naming, PR creation, security practices, and common development practices, see the [organization-wide AGENTS.md](https://github.com/ddev/.github/blob/main/AGENTS.md).

## Important Instruction Reminders

Do what has been asked; nothing more, nothing less.
NEVER create files unless they're absolutely necessary for achieving your goal.
ALWAYS prefer editing an existing file to creating a new one.
NEVER proactively create documentation files (*.md) or README files. Only create documentation files if explicitly requested by the User.

## Task Master AI Instructions

**Import Task Master's development workflow commands and guidelines, treat as if import is in the main CLAUDE.md file.**
@./.taskmaster/CLAUDE.md

- The DDEV PR and documentation for this PHP feature is https://github.com/ddev/ddev/pull/7523 - Read that and its docs to understand how it's used
- Reference implementation of PHP-based add-ons are in https://github.com/rfay/ddev-redis-php and https://github.com/rfay/ddev-platformsh-php