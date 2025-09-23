# DDEV-Upsun Add-on - Product Requirements Document

## Overview

The DDEV-Upsun add-on provides integration between Upsun hosting platform and DDEV local development environment. This add-on interprets Upsun configuration files and translates them into equivalent DDEV configuration, enabling developers to replicate their Upsun production environment locally.

## Background

This project builds upon lessons learned from the [DDEV-Platform.sh add-on](https://github.com/ddev/ddev-platformsh), which used a complex bash/golang template approach. The DDEV-Upsun add-on will leverage the new PHP-based add-on implementation technique for cleaner, more maintainable code.

### Key Reference Documentation
- **DDEV Add-ons**: [Template](https://github.com/ddev/ddev-addon-template) and [Documentation](https://ddev.readthedocs.io/en/stable/users/extend/additional-services/)
- **New PHP Add-on Implementation**: [PR #7523](https://github.com/ddev/ddev/pull/7523) and [Developer Docs](https://github.com/rfay/ddev/tree/20250806_rfay_php_addon/docs/content/developers/tmp)
- **Upsun Documentation**: [Upsun Docs](https://docs.upsun.com)
  - [PHP Support](https://docs.upsun.com/languages/php.html)
  - [MySQL/MariaDB](https://docs.upsun.com/add-services/mysql.html)
  - [PostgreSQL](https://docs.upsun.com/add-services/postgresql.html)
- **DDEV Documentation**: [DDEV Docs](https://ddev.readthedocs.io)

## Goals

### Primary Goal
Enable developers to check out an Upsun project and automatically configure DDEV to match the production environment, supporting `ddev pull upsun` functionality.

### Success Criteria
- Successfully parse basic Upsun configuration from `.upsun` directory
- Translate PHP versions and database configurations to DDEV equivalents
- Enable `ddev pull upsun` command for database and file synchronization
- Provide clear error messages when translation is not possible

## Scope

### In Scope (Phase 1)
- **Single-app Upsun projects** with one database relationship
- **PHP version translation** from Upsun to DDEV configuration
- **Database version translation** (MySQL, PostgreSQL, MariaDB)
- **Basic service configuration** interpretation
- **Environment variable mapping** from Upsun to DDEV
- **Integration with existing `ddev pull upsun` functionality**
- **Error handling and user feedback** for unsupported configurations

### Out of Scope (Future Phases)
- Multi-app Upsun projects
- Complex service relationships beyond single database
- Advanced Upsun features (workers, crons, etc.)
- Redis, Elasticsearch, or other advanced services
- Multi-database configurations

## Technical Requirements

### Architecture
- **Implementation Language**: PHP (following new add-on technique)
- **Configuration Parser**: PHP-based Upsun config interpreter
- **Integration Method**: DDEV add-on framework
- **Template Engine**: Native PHP (no external templating)

### Reference Examples
Translation examples for common frameworks are provided in `prd/examples/`:
- **drupal-composer**: Drupal 10 with PHP 8.3 and MariaDB 10.11
- **wordpress-composer**: WordPress with PHP 8.2 and Oracle MySQL 8.0
- **laravel-api**: Laravel API with PHP 8.4 and PostgreSQL 16
- **symfony-webapp**: Symfony webapp with PHP 8.3 and PostgreSQL 15

Each example contains source Upsun configuration (`upsun/.upsun/config.yaml`) and target DDEV configuration (`ddev/.ddev/config.yaml`) demonstrating the translation patterns.

### Core Components

#### 1. Upsun Configuration Parser
- Parse `.upsun/` directory configuration files (input source files)
- Extract application and service definitions from Upsun project
- Identify database relationships and versions
- Detect PHP runtime configuration

#### 2. DDEV Configuration Translator
- Map PHP versions between Upsun and DDEV
- Translate database service types and versions
- Convert environment variables
- Generate appropriate DDEV configuration files
- **Generate PLATFORM_* environment variables** for application compatibility

#### 3. PLATFORM_* Environment Variables Generator
- Generate all required PLATFORM_* environment variables for Upsun application compatibility
- **Generated Variables**:
  - `PLATFORM_APP_DIR` - Application directory path (`/var/www/html`)
  - `PLATFORM_APPLICATION_NAME` - Application name from Upsun config
  - `PLATFORM_BRANCH` - Current Git branch (detected automatically)
  - `PLATFORM_DOCUMENT_ROOT` - Web document root path
  - `PLATFORM_ENVIRONMENT_TYPE` - Environment type (`development` for DDEV)
  - `PLATFORM_PROJECT_ENTROPY` - Random string for security purposes
  - `PLATFORM_RELATIONSHIPS` - Base64-encoded JSON of service connections
  - `PLATFORM_ROUTES` - Base64-encoded JSON of route definitions
  - `PLATFORM_VARIABLES` - Base64-encoded JSON of user variables
  - `PLATFORM_SMTP_HOST` - DDEV's mailpit SMTP host
  - `PLATFORM_CACHE_DIR` - Cache directory path
  - `PLATFORM_TREE_ID` - Git commit hash or generated identifier
- **External Variables** (user-configured):
  - `PLATFORM_PROJECT` - Upsun project ID (set via `ddev config`)
  - `PLATFORM_ENVIRONMENT` - Upsun environment name (set via `ddev config`)

#### 4. Validation and Error Handling
- Validate Upsun configuration compatibility
- Provide clear error messages for unsupported features
- Graceful degradation when partial translation is possible

### Supported Configurations

**Note**: Version support is based on current Upsun documentation as of project creation. Verify current versions at [Upsun docs](https://docs.upsun.com).

#### PHP Applications
- PHP versions: All Upsun-supported versions (8.4, 8.3, 8.2, 8.1) - [Current versions](https://docs.upsun.com/languages/php.html)
- Common PHP frameworks (Drupal, WordPress, Laravel, Symfony)
- Composer-based dependency management

#### Database Services  
- MariaDB/MySQL: 11.8, 11.4, 10.11, 10.6 - [Current versions](https://docs.upsun.com/add-services/mysql.html)
- Oracle MySQL: 8.0, 5.7 - [Current versions](https://docs.upsun.com/add-services/mysql.html)
- PostgreSQL: 17, 16, 15, 14, 13, 12 - [Current versions](https://docs.upsun.com/add-services/postgresql.html)

#### Basic Services
- Single database relationship per application
- Standard web server configuration (nginx/apache)
- Basic environment variable mapping

## User Experience

### Installation Flow
1. User adds DDEV-Upsun add-on to existing project: `ddev get ddev/ddev-upsun`
2. Add-on detects `.upsun` directory and configuration
3. Add-on parses Upsun config and generates DDEV configuration
4. User can immediately use `ddev start` with translated configuration

### Pull Workflow
1. User runs `ddev pull upsun` 
2. Add-on connects to Upsun environment using CLI within DDEV web container
3. Database and files are synchronized to local DDEV

### Error Scenarios
- **Unsupported Configuration**: Clear message explaining what's not supported
- **Authentication Issues**: Helpful debugging information for Upsun CLI setup
- **Version Conflicts**: Suggestions for compatible alternatives

## Testing Strategy

### Test Fixture Validation Challenges

The add-on relies on test fixtures containing realistic Upsun configurations, but this approach has two critical validation gaps:

#### 1. Fixture Validity Problem
**Challenge**: How do we verify that test fixtures actually work on Upsun?
- Fixtures may contain outdated or invalid Upsun configuration syntax
- Version incompatibilities may not surface until real deployment
- Service relationship definitions may be theoretically correct but practically broken

**Proposed Solutions**:
- **Automated Fixture Validation**: Deploy each fixture to actual Upsun environments as part of CI pipeline
- **Upsun Environment Matrix**: Maintain live test projects for each fixture scenario
- **Configuration Linting**: Use Upsun CLI validation tools against fixtures before testing

#### 2. Environment Equivalence Problem
**Challenge**: How do we verify that DDEV projects match their Upsun counterparts?
- Add-on may successfully translate configuration but produce non-equivalent environments
- Critical differences (PHP extensions, database versions, environment variables) may go undetected
- Pull operations may work but environment behavior may differ subtly

**Proposed Solutions**:
- **Cross-Platform Validation Suite**: Automated comparison of key environment attributes
  - PHP version, extensions, and configuration
  - Database server version and configuration
  - Environment variables and application behavior
- **Behavioral Testing**: Run identical application tests in both environments
- **Performance Benchmarking**: Compare application performance between Upsun and DDEV

#### 3. Integrated Testing Workflow
**Multi-Repository Testing Coordination**:
- **Test Fixtures Repository**: Maintain validated Upsun configurations as test fixtures
- **Live Test Projects**: Actual Upsun projects for each test scenario (basic-php-mysql, php-postgresql, multi-service)
- **Validation Scripts**: Automated comparison between deployed Upsun environments and generated DDEV environments

**Testing Matrix**:
```
Test Scenarios:
├── basic-php-mysql/     (PHP 8.3 + MariaDB 10.11)
├── php-postgresql/      (PHP 8.2 + PostgreSQL 16) 
├── wordpress-composer/  (WordPress + MySQL 8.0)
├── laravel-api/         (Laravel + PostgreSQL 15)
└── symfony-webapp/      (Symfony + PostgreSQL 16)
```

Each scenario requires:
1. Fixture validation against live Upsun deployment
2. DDEV translation and setup
3. Environment equivalence verification
4. Pull operation validation

#### 4. Task Master Coordination Strategy
**Multi-Repository Workflow Management**:

Use Task Master to coordinate complex testing workflows across multiple repositories and environments:

```bash
# Create systematic testing tasks for each scenario
task-master add-task --prompt="Validate basic-php-mysql fixture on live Upsun"
task-master add-task --prompt="Test DDEV translation for basic-php-mysql scenario"
task-master add-task --prompt="Compare Upsun vs DDEV environment equivalence for basic-php-mysql"
task-master add-task --prompt="Validate pull operation for basic-php-mysql scenario"

# Expand complex validation tasks into subtasks
task-master expand --id=<fixture-validation-id> --research --num=5
# Generates subtasks like:
# - Deploy fixture to Upsun test environment
# - Verify application startup and database connectivity
# - Run smoke tests on deployed application
# - Document any configuration issues discovered
# - Update fixture with corrections if needed

# Track progress across multiple test environments
task-master update-subtask --id=X.Y --prompt="Upsun deployment successful, app responding on HTTPS"
task-master update-subtask --id=X.Z --prompt="DDEV translation created config.yaml with PHP 8.3, MariaDB 10.11"
```

**Parallel Testing Coordination**:
- **Git Worktree Management**: Use Task Master to track which worktrees are testing which scenarios
- **Environment State Tracking**: Log current state of each test environment (deployed, testing, validated)
- **Cross-Environment Results**: Compare results between Upsun and DDEV environments systematically
- **Dependency Management**: Ensure fixture validation completes before environment comparison testing

**Task Master Integration Benefits**:
- **Progress Visibility**: Track testing progress across multiple repositories and environments
- **Systematic Validation**: Ensure no test scenarios are skipped or forgotten
- **Knowledge Capture**: Document issues, solutions, and validation results in task notes
- **Iterative Improvement**: Update testing procedures based on lessons learned from each scenario

## Implementation Strategy

### Phase 1: Core Functionality (MVP)
1. Basic Upsun configuration parsing
2. PHP version translation
3. Single database relationship support
4. Basic DDEV configuration generation
5. Test framework integration (Drupal, WordPress, Laravel, Symfony)
6. **Basic fixture validation pipeline**

### Phase 2: Enhanced Features
1. **PLATFORM_* Environment Variables Implementation** - Generate all required PLATFORM_* environment variables for application compatibility
2. Improved error handling and validation
3. Support for additional database versions
4. Environment variable management
5. Enhanced pull command features
6. **Cross-platform environment validation**

### Phase 3: Advanced Integration
1. Multi-service support (within single-app constraint)
2. Advanced Upsun features translation
3. Performance optimizations
4. Comprehensive testing suite
5. **Automated equivalence testing and reporting**

## Dependencies

### External Dependencies
- DDEV framework (latest version supporting PHP add-ons)
- Upsun CLI tool (provided within DDEV web container)

### Internal Dependencies
- DDEV's new PHP add-on implementation framework
- DDEV's pull command infrastructure
- DDEV's configuration management system

## Success Metrics

### Functional Metrics
- Successfully parse and translate 90% of basic Upsun configurations
- Zero-configuration setup for standard PHP+database projects
- Pull command success rate > 95% for supported configurations

### User Experience Metrics
- Time to first successful `ddev start` < 2 minutes after add-on installation
- Clear error messages for 100% of unsupported scenarios
- Documentation coverage for all supported use cases

## Risks and Mitigation

### Technical Risks
- **Upsun API Changes**: Regular testing and version compatibility checks
- **DDEV Framework Changes**: Close collaboration with DDEV core team
- **Configuration Complexity**: Start with simple cases, expand gradually

### User Experience Risks
- **Unsupported Configurations**: Clear documentation of limitations
- **Authentication Complexity**: Comprehensive setup documentation
- **Performance Issues**: Optimize parsing and translation logic

## Future Considerations

### Extensibility
- Plugin architecture for custom configuration translators
- Support for additional Upsun services
- Integration with other hosting platforms

### Community
- Open source development model
- Community contributions for additional service support
- Regular feedback collection and iteration

## Conclusion

The DDEV-Upsun add-on will provide essential integration between Upsun hosting and DDEV local development, focusing on the most common use cases first while building a foundation for future enhancements. The PHP-based implementation approach will provide better maintainability and extensibility compared to previous bash-based solutions.