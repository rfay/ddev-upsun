[![add-on registry](https://img.shields.io/badge/DDEV-Add--on_Registry-blue)](https://addons.ddev.com)
[![tests](https://github.com/rfay/ddev-upsun/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/rfay/ddev-upsun/actions/workflows/tests.yml?query=branch%3Amain)
[![last commit](https://img.shields.io/github/last-commit/rfay/ddev-upsun)](https://github.com/rfay/ddev-upsun/commits)
[![release](https://img.shields.io/github/v/release/rfay/ddev-upsun)](https://github.com/rfay/ddev-upsun/releases/latest)

# DDEV Upsun

## Overview

This add-on automatically configures your [DDEV](https://ddev.com/) project to match your Upsun platform configuration. It parses your `.upsun/` configuration files and generates equivalent DDEV settings for local development.

## Features

- **Automatic Configuration**: Detects `.upsun/` directory and processes configuration files
- **PHP Version Mapping**: Translates Upsun PHP runtime to DDEV equivalents
- **Database Integration**: Configures MySQL, MariaDB, or PostgreSQL services to match Upsun
- **Environment Variables**: Maps Upsun environment variables to DDEV equivalents

## Installation

```bash
ddev add-on get rfay/ddev-upsun
ddev restart
```

After installation, make sure to commit the `.ddev` directory to version control.

## Usage

The add-on automatically processes your Upsun configuration during installation. For projects with existing `.upsun/` directories:

1. The add-on detects your Upsun configuration
2. Generates corresponding DDEV configuration files
3. Updates `.ddev/config.yaml` with appropriate settings
4. Creates `.ddev/config.upsun.yaml` with Upsun-specific configuration

### Supported Configurations

- **PHP Versions**: All supported by Upsun, including 8.1-8.4
- **Database Services**: mysql, mariadb, postgresql
- **Basic Application Configuration**: Web root, document root
- **Environment Variables and Relationships**

### Limitations

- Multi-app configurations are not supported
- Complex service relationships beyond single database
- Workers and cron jobs are not translated
- Advanced networking configurations

## Credits

**Contributed and maintained by [@rfay](https://github.com/rfay)**
