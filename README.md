[![add-on registry](https://img.shields.io/badge/DDEV-Add--on_Registry-blue)](https://addons.ddev.com)
[![tests](https://github.com/ddev/ddev-upsun/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/ddev/ddev-upsun/actions/workflows/tests.yml?query=branch%3Amain)
[![last commit](https://img.shields.io/github/last-commit/ddev/ddev-upsun)](https://github.com/ddev/ddev-upsun/commits)
[![release](https://img.shields.io/github/v/release/ddev/ddev-upsun)](https://github.com/ddev/ddev-upsun/releases/latest)

# DDEV Upsun (EXPERIMENTAL)

> **Warning:** This add-on is experimental and under active development.
## Overview

[Upsun](https://upsun.com/) is a unified, secure, enterprise-grade platform for building, running and scaling web applications.

This repository provides experimental integration between your Upsun project and [DDEV](https://ddev.com). It tries to  configure your DDEV project to match your Upsun platform configuration by parsing your `.upsun/config.yaml` configuration file and generating equivalent DDEV settings for local development.

**🚨 This add-on is experimental and under active development. Please report issues and provide feedback!**

## Using with an Upsun project

### Dependencies

* Make sure you have DDEV v1.24.8+ installed.
* Your project should have a valid `.upsun/config.yaml` file.

### Install
1. Clone your project repository
2. `cd` into your project directory
3. Run `ddev config` and answer the questions as appropriate
4. Run `ddev add-on get ddev/ddev-upsun`
5. Run `ddev start`
6. Run `ddev pull upsun` to retrieve a copy of the database and file mounts from your Upsun environment.

### Upgrade

To upgrade your version of ddev-upsun, repeat the `ddev add-on get ddev/ddev-upsun` to get the latest release. To see the installed version, `ddev add-on list --installed`.

### Run it again if you change your Upsun configuration

If you change your `.upsun/config.yaml`, repeat the `ddev add-on get ddev/ddev-upsun` so that the generated DDEV configuration will be updated.

## What does it do right now?

* Works with Upsun php-based projects, for example `php:8.1`, `php:8.2`, `php:8.3`, or `php:8.4`. It does not work with non-PHP projects.
* Takes your checked-out Upsun project and configures DDEV based on that information:
    * PHP version mapping to DDEV equivalents
    * Database services (MySQL, MariaDB, PostgreSQL)
    * Basic environment variables and relationships
    * A working `ddev pull upsun` integration
* Supports the following services:
    * **Databases**
      * MariaDB
      * MySQL
      * PostgreSQL
    * **Cache/Memory**
      * Redis
      * Memcached
    * **Search**
      * OpenSearch

## What has been tested

These project types are included in the automated tests that run with every change:

* **Drupal 11** - Fully tested with all three database types:
  * [drupal11-mariadb](tests/testdata/drupal11-mariadb/)
  * [drupal11-mysql](tests/testdata/drupal11-mysql/)
  * [drupal11-postgres](tests/testdata/drupal11-postgres/)

Each test configuration includes coverage for Redis, OpenSearch, and Memcached services.

## What has NOT been tested yet

* Multi-application Upsun projects
* Frameworks other than Drupal (Laravel, Symfony, etc.)
* Complex service relationships
* Workers and cron jobs
* Advanced Upsun features

## Limitations

* **Single-app projects only** - Multi-app configurations are not supported
* **Basic service relationships** - Complex service relationships beyond single database are not translated
* **No worker/cron translation** - Workers and cron jobs are not translated to DDEV equivalents
* **Limited environment variables** - Only basic Upsun environment variables are mapped
* **PHP projects only** - Non-PHP runtimes are not supported

## Community feedback requested!

**Your experience is important**: Please let us know about how it went for you in any of the [DDEV support venues](https://ddev.readthedocs.io/en/stable/users/support/), especially [Discord](https://discord.gg/5wjP76mBJD).

We're particularly interested in:

* Which project types and frameworks you'd like to see supported
* What Upsun services and features are most important for your workflow
* Any issues or edge cases you encounter
* Success stories and improvements

## Notes

* If your local project has a different database type than the upstream (Upsun) database, it will conflict, so please back up your database with `ddev export-db` and `ddev delete` before starting the project with new configuration based on upstream.
* This add-on is based on lessons learned from [ddev-platformsh](https://github.com/ddev/ddev-platformsh) but adapted for Upsun's configuration format, and using the new DDEV [PHP-based actions](https://docs.ddev.com/en/stable/users/extend/creating-add-ons/#action-types-bash-vs-php) add-on technique. 

## What will it do in the future

- [x] Basic PHP project support with database configuration
- [x] Drupal 11 testing
- [ ] Support Upsun-Fixed (Platform.sh-style) configuration, `.platform.app.yaml` files
- [ ] Laravel project support and testing
- [ ] Symfony project support and testing
- [ ] WordPress project support and testing
- [ ] Multi-app project support
- [ ] Worker and cron job translation
- [ ] Enhanced service relationship mapping
- [ ] Let us know what's important to you on [Discord](https://ddev.com/s/discord) and in the issue queue here!

## Credits

**Maintained by [@rfay](https://github.com/rfay)**
