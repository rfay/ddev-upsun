[![add-on registry](https://img.shields.io/badge/DDEV-Add--on_Registry-blue)](https://addons.ddev.com)
[![tests](https://github.com/rfay/ddev-upsun/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/rfay/ddev-upsun/actions/workflows/tests.yml?query=branch%3Amain)
[![last commit](https://img.shields.io/github/last-commit/rfay/ddev-upsun)](https://github.com/rfay/ddev-upsun/commits)
[![release](https://img.shields.io/github/v/release/rfay/ddev-upsun)](https://github.com/rfay/ddev-upsun/releases/latest)

# DDEV Upsun

## Overview

This add-on integrates Upsun into your [DDEV](https://ddev.com/) project.

## Installation

```bash
ddev add-on get rfay/ddev-upsun
ddev restart
```

After installation, make sure to commit the `.ddev` directory to version control.

## Usage

| Command | Description |
| ------- | ----------- |
| `ddev describe` | View service status and used ports for Upsun |
| `ddev logs -s upsun` | Check Upsun logs |

## Advanced Customization

To change the Docker image:

```bash
ddev dotenv set .ddev/.env.upsun --upsun-docker-image="ddev/ddev-utilities:latest"
ddev add-on get rfay/ddev-upsun
ddev restart
```

Make sure to commit the `.ddev/.env.upsun` file to version control.

All customization options (use with caution):

| Variable | Flag | Default |
| -------- | ---- | ------- |
| `UPSUN_DOCKER_IMAGE` | `--upsun-docker-image` | `ddev/ddev-utilities:latest` |

## Credits

**Contributed and maintained by [@rfay](https://github.com/rfay)**
