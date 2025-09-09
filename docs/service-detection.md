# Upsun Service Detection and Dynamic Dependencies

This document explains how ddev-upsun automatically detects Upsun services and installs corresponding DDEV add-ons.

## Overview

Upsun projects can define various services (Redis, Elasticsearch, etc.) in their configuration. The ddev-upsun add-on automatically detects these services and installs the appropriate DDEV add-ons as dynamic dependencies.

## Supported Services

Based on [Upsun's available services](https://docs.upsun.com/add-services.html#available-services), the following service mappings are supported:

| Upsun Service | DDEV Add-on | Notes |
|---------------|-------------|-------|
| `elasticsearch` | `ddev/ddev-elasticsearch` | Version-aware |
| `kafka` | `chx/ddev-kafka` | |
| `memcached` | `ddev/ddev-memcached` | |
| `mongodb` | `ddev/ddev-mongo` | |
| `opensearch` | `ddev/ddev-opensearch` | Version-aware |
| `rabbitmq` | `ddev/ddev-rabbitmq` | |
| `redis` | `ddev/ddev-redis` | Version-aware |
| `solr` | `ddev/ddev-solr` | Version-aware |
| `varnish` | `ddev/ddev-varnish` | |

## Service Detection Process

### 1. Configuration Sources

The add-on checks for service definitions in:
- `.upsun/config.yaml` (primary)
- `.upsun/.platform.app.yaml` (fallback for Platform.sh compatibility)

### 2. Detection Logic

Services are detected by parsing the `services:` section of the Upsun configuration:

```yaml
services:
  cache:
    type: redis:8.0
  search:
    type: elasticsearch:7.17
  database:
    type: mariadb:11.8
```

The detection logic:
1. Locates the `services:` section
2. Extracts service definitions with regex pattern: `servicename:` followed by `type: servicetype:version`
3. Maps service types to DDEV add-ons using the service mapping table
4. Creates runtime dependencies for DDEV to install

### 3. Version Information

For services that support version-specific configuration (Redis, Elasticsearch, Solr, OpenSearch), version information is:

1. **Extracted** from the service definition (e.g., `redis:8.0` → version `8.0`)
2. **Stored** in `.env.upsun-services` as environment variables
3. **Made available** for DDEV add-on configuration

#### Environment Variable Format

Service versions are stored as:
```bash
# .env.upsun-services
UPSUN_REDIS_VERSION=8.0
UPSUN_ELASTICSEARCH_VERSION=7.17
UPSUN_SOLR_VERSION=9.4
```

### 4. Using Version Information

DDEV add-ons can access version information using:

```bash
# Load all service versions
ddev dotenv set UPSUN_REDIS_VERSION=8.0

# Or load specific versions as needed
ddev exec -s redis redis-server --version
```

## Implementation Details

### Dynamic Dependencies

Service detection creates a `.runtime-deps-upsun` file containing the list of DDEV add-ons to install:

```
ddev/ddev-redis
ddev/ddev-elasticsearch
```

DDEV processes this file and automatically installs the specified add-ons before proceeding with the main ddev-upsun installation.

### Timing Constraints

Service detection currently runs in **pre-install actions** due to DDEV's dependency resolution timing:

1. Pre-install actions (service detection happens here)
2. Runtime dependency installation
3. Project file installation
4. Post-install actions

This timing constraint requires inline PHP code in `install.yaml` rather than using dedicated PHP classes.

## Version-Specific Configuration Examples

### Redis Configuration

For Redis services, the detected version can be used to:
- Configure Redis-specific features
- Set appropriate memory limits
- Enable version-specific optimizations

### Elasticsearch/OpenSearch

Version information helps with:
- Index mapping compatibility
- Query syntax variations
- Feature availability

### Solr

Solr versions affect:
- Schema configuration
- Core management
- Search feature availability

## Future Enhancements

### Planned Improvements

1. **Better Version Mapping**: More sophisticated version compatibility checking
2. **Custom Service Support**: Allow users to define custom service mappings
3. **Configuration Validation**: Verify service configurations are valid
4. **Multi-App Support**: Handle Upsun multi-application configurations

### Architecture Improvements

See [ddev-runtime-detection.md](./ddev-runtime-detection.md) for planned improvements to DDEV's runtime dependency system that would enable cleaner service detection implementation.

## Troubleshooting

### Services Not Detected

If services aren't being detected:

1. **Check configuration format**: Ensure services are defined in the `services:` section
2. **Verify file location**: Services must be in `.upsun/config.yaml` or `.upsun/.platform.app.yaml`
3. **Review service types**: Only supported service types (see table above) are detected
4. **Check syntax**: Service definitions must follow `type: servicetype:version` format

### Missing Add-ons

If DDEV add-ons aren't installed:

1. **Check detection output**: Review installation logs for service detection messages
2. **Verify dependencies file**: Look for `.runtime-deps-upsun` in project root during installation
3. **Manual installation**: Install missing add-ons manually with `ddev add-on get`

### Version Issues

If service versions aren't available:

1. **Check environment file**: Verify `.env.upsun-services` exists in `.ddev/` directory
2. **Load variables**: Use `ddev dotenv set` to load specific versions
3. **Verify format**: Ensure version follows `servicetype:major.minor` format in Upsun config

## Contributing

To add support for new Upsun services:

1. Add service mapping to the `$serviceToAddon` array in service detection logic
2. Update the supported services table in this document
3. Add any version-specific handling if needed
4. Test with actual Upsun configurations
5. Update troubleshooting documentation

For architectural improvements, see the roadmap in [ddev-runtime-detection.md](./ddev-runtime-detection.md).