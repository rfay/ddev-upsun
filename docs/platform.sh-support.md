# Platform.sh Support Analysis and Implementation Strategy

This document analyzes the feasibility and approach for adding Platform.sh support to ddev-upsun, creating a unified add-on for both Upsun and Platform.sh projects.

## Background

Platform.sh has rebranded to Upsun, but continues to support their original configuration format. Both platforms share the same underlying concepts and service definitions, making unified support technically feasible.

## Platform Configuration Comparison

### Upsun Configuration Structure
```
.upsun/
└── config.yaml          # Unified configuration (applications, services, routes)
```

**Example:**
```yaml
applications:
  myapp:
    type: "php:8.4"
    # ... application config

services:
  cache:
    type: redis:8.0
  search:
    type: elasticsearch:7.17

routes:
  "https://{all}/":
    type: upstream
    upstream: "myapp:http"
```

### Platform.sh Configuration Structure
```
.platform/
├── services.yaml        # Service definitions
├── routes.yaml          # Route configuration
└── .platform.app.yaml  # Application configuration (in project root)
```

**Example services.yaml:**
```yaml
cache:
  type: redis:8.0
search:
  type: elasticsearch:7.17
```

**Example .platform.app.yaml:**
```yaml
name: myapp
type: "php:8.4"
relationships:
  redis: "cache:redis"
  elasticsearch: "search:elasticsearch"
```

## Key Similarities

### Service Definitions
Both platforms use identical service definition formats:
- **Type specification**: `type: servicename:version`
- **Service types**: redis, elasticsearch, mariadb, postgresql, etc.
- **Version patterns**: Major.minor version numbering
- **Relationships**: Both connect applications to services

### Application Configuration
- **Runtime specification**: `type: "php:8.4"`
- **Web configuration**: Similar location and routing patterns
- **Hooks**: Both support build, deploy, and post-deploy hooks
- **Environment variables**: Similar variable definition patterns

### Supported Services
Both platforms support the same core services that map to DDEV add-ons:
- Redis → `ddev/ddev-redis`
- Elasticsearch → `ddev/ddev-elasticsearch`
- Memcached → `ddev/ddev-memcached`
- And others as documented in [service-detection.md](./service-detection.md)

## Existing ddev-platformsh Analysis

### Current Implementation Challenges

The existing `ddev/ddev-platformsh` add-on demonstrates significant architectural issues:

#### Scale and Complexity
- **322 lines** in `install.yaml` with complex bash/Go template logic
- **11 shell scripts** for various service generation tasks
- **13 bats test files** and **20 test data projects**
- Extremely difficult to maintain and debug

#### Template-Based Architecture Problems
```yaml
{{ if not (hasPrefix "php" .platformapp.type) }}
  printf "Unsupported application type {{ .platformapp.type }}" >&2
  exit 5
{{ end }}
```

- Complex conditional logic embedded in YAML templates
- Nested loops and variable manipulation in bash
- Error-prone string concatenation and base64 encoding
- No proper error handling or debugging capabilities

#### Manual Service Installation
```bash
{{ if hasKey $supported_services $service_def.type }}
  echo "Running 'ddev add-on get {{ $service_addon }}'"
  ddev add-on get {{ $service_addon }}
{{ end }}
```

- Manual add-on installation in post-install actions
- Bypasses DDEV's dependency system entirely
- No dependency ordering or conflict resolution
- Fragile installation process

### What We Can Learn

The ddev-platformsh implementation validates our architectural approach:

1. **Bash/template complexity** is exactly what we're avoiding with PHP classes
2. **Manual service installation** demonstrates the need for proper dynamic dependencies
3. **Template debugging difficulty** shows the value of structured, testable code
4. **Maintenance overhead** justifies investing in cleaner architecture

## Proposed Platform.sh Support Strategy

### Technical Approach

#### 1. Expand File Detection
Modify service detection to check multiple configuration sources:

```php
$configFiles = [
    // Upsun style (current)
    $upsunDir . '/config.yaml',
    $upsunDir . '/.platform.app.yaml',
    
    // Platform.sh style (new)
    $projectRoot . '/.platform/services.yaml',
    $projectRoot . '/.platform.app.yaml'
];
```

#### 2. Unified Service Parsing
Since both platforms use identical service definition formats, the same parsing logic works:

```php
// Works for both platforms
if (preg_match_all('/^\s*(\w+):\s*$.*?^\s*type:\s*(\w+):([0-9.]+)/ms', 
                   $remaining, $serviceMatches, PREG_SET_ORDER)) {
    // Process services
}
```

#### 3. Platform Detection and Reporting
```php
if (strpos($file, '/.platform/') !== false) {
    $configType = 'Platform.sh';
} else {
    $configType = 'Upsun';
}
echo "✅ Detected {$serviceType} service from {$configType} config\n";
```

#### 4. Maintain Service Mappings
Use the same service-to-add-on mappings for both platforms:

```php
$serviceToAddon = [
    'elasticsearch' => 'ddev/ddev-elasticsearch',
    'redis' => 'ddev/ddev-redis',
    'memcached' => 'ddev/ddev-memcached',
    // ... etc
];
```

### Implementation Benefits

#### 1. Unified Tooling
- **Single add-on** for both Upsun and Platform.sh projects
- **Consistent experience** regardless of platform
- **Reduced maintenance** compared to separate add-ons

#### 2. Clean Architecture
- **PHP classes** instead of bash templates
- **Proper error handling** and debugging
- **Unit testable** service detection logic
- **Maintainable codebase** with clear separation of concerns

#### 3. Modern Dependency Management
- **Dynamic dependencies** via DDEV's runtime system
- **Proper dependency ordering** and conflict resolution
- **Automatic service installation** without manual intervention

#### 4. Migration Path
- **Drop-in replacement** for ddev-platformsh
- **Gradual migration** from Platform.sh to Upsun
- **No user intervention** required for basic functionality

## Implementation Plan

### Phase 1: Basic Platform.sh Support
1. Expand file detection to include `.platform/services.yaml`
2. Add platform detection and reporting
3. Test with existing Platform.sh projects
4. Ensure service detection works for both formats

### Phase 2: Enhanced Compatibility
1. Handle Platform.sh-specific configuration patterns
2. Add relationship parsing for complex service connections
3. Support Platform.sh environment variable patterns
4. Test with real-world Platform.sh projects

### Phase 3: Migration Tools
1. Provide migration guidance from ddev-platformsh
2. Add compatibility layer for existing Platform.sh workflows
3. Document differences and migration steps
4. Support gradual Platform.sh to Upsun migrations

### Phase 4: Advanced Features
1. Support multi-app Platform.sh configurations
2. Handle complex routing scenarios
3. Add Platform.sh CLI integration where beneficial
4. Provide configuration validation and recommendations

## Migration from ddev-platformsh

### For Users
1. **Remove existing add-on**: `ddev add-on remove ddev-platformsh`
2. **Install ddev-upsun**: `ddev add-on get ddev/ddev-upsun`
3. **Automatic detection**: Platform.sh configs are detected automatically
4. **Same functionality**: Services and relationships work the same way

### For Projects
- **No config changes required**: Existing Platform.sh configurations work as-is
- **Same service support**: All supported services continue to work
- **Improved reliability**: More robust service detection and installation
- **Better debugging**: Clear error messages and logging

## Testing Strategy

### Test Coverage Areas
1. **Service detection** across both configuration formats
2. **Version extraction** and environment variable creation
3. **Dynamic dependency** installation for various services
4. **Multi-service projects** with complex relationships
5. **Edge cases** and error conditions

### Test Data Sources
1. **Adapt existing tests** from ddev-platformsh test data
2. **Real Platform.sh projects** to ensure compatibility
3. **Upsun project examples** for regression testing
4. **Synthetic test cases** for edge conditions

### Validation Criteria
1. **Service detection accuracy** across both platforms
2. **Dependency installation success** for all supported services
3. **Environment variable creation** with correct versions
4. **No regressions** in existing Upsun functionality

## Technical Considerations

### Configuration Parsing Differences
While service definitions are identical, there are subtle differences in how configurations are structured:

- **Upsun**: Services embedded in unified config
- **Platform.sh**: Services in separate dedicated file

The same parsing logic works for both, but file discovery needs to be comprehensive.

### Relationship Handling
Platform.sh uses explicit relationship definitions in application config:
```yaml
relationships:
  redis: "cache:redis"
```

This provides additional context that could enhance service detection accuracy.

### Environment Variable Compatibility
Both platforms expect similar environment variables, but with different prefixes:
- **Platform.sh**: `PLATFORM_RELATIONSHIPS`, `PLATFORM_ROUTES`
- **Upsun**: Similar patterns but potentially different naming

### Version Compatibility
Both platforms support the same service versions, making version detection and mapping straightforward.

## Risk Assessment

### Low Risks
- **Service definition compatibility**: Formats are identical
- **Version mapping**: Same version patterns across platforms
- **Basic functionality**: Core service detection logic is platform-agnostic

### Medium Risks
- **Edge cases**: Complex Platform.sh configurations might have unique patterns
- **Migration complexity**: Users migrating from ddev-platformsh might expect identical behavior
- **Testing coverage**: Ensuring comprehensive test coverage across both platforms

### High Risks
- **Configuration subtleties**: Unexpected differences in configuration interpretation
- **Performance impact**: Additional file checking might slow installation
- **Maintenance burden**: Supporting two platforms increases complexity

### Risk Mitigation
1. **Extensive testing** with real-world projects from both platforms
2. **Gradual rollout** starting with basic service detection
3. **Clear documentation** of differences and limitations
4. **Community feedback** during development process

## Success Metrics

### Technical Metrics
- **Service detection accuracy**: >95% for both platforms
- **Installation success rate**: Equal to current Upsun-only success rate
- **Performance impact**: <10% increase in installation time

### User Experience Metrics
- **Migration success**: Smooth transition from ddev-platformsh
- **Support requests**: No increase in support burden
- **Community adoption**: Positive feedback from both communities

### Maintenance Metrics
- **Code complexity**: Manageable increase in codebase size
- **Test coverage**: Comprehensive coverage for both platforms
- **Bug reports**: No increase in platform-specific issues

## Conclusion

Adding Platform.sh support to ddev-upsun is technically feasible and strategically valuable. The unified configuration approach provides significant advantages over the existing ddev-platformsh implementation:

1. **Cleaner architecture** with maintainable PHP classes
2. **Proper dependency management** using DDEV's runtime system
3. **Unified user experience** across both platforms
4. **Future-proof design** that can adapt to platform evolution

The implementation can be done incrementally, starting with basic service detection and expanding to full Platform.sh compatibility over time. This approach provides a modern, maintainable alternative to the existing ddev-platformsh add-on while supporting both current and future platform configurations.

## References

- [Upsun Available Services](https://docs.upsun.com/add-services.html#available-services)
- [Platform.sh Application Reference](https://docs.platform.sh/create-apps.html)
- [Platform.sh Services Configuration](https://docs.platform.sh/add-services.html)
- [DDEV Add-on Development Guide](https://ddev.readthedocs.io/en/stable/developers/add-ons/)
- [ddev-platformsh Repository](https://github.com/ddev/ddev-platformsh)
- [DDEV Dynamic Dependencies PR](https://github.com/ddev/ddev/pull/7586)