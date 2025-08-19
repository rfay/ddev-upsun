# DDEV-Upsun Add-on - Product Requirements Document

## Overview

The DDEV-Upsun add-on provides integration between Upsun hosting platform and DDEV local development environment. This add-on interprets Upsun configuration files and translates them into equivalent DDEV configuration, enabling developers to replicate their Upsun production environment locally.

## Background

This project builds upon lessons learned from the DDEV-Platform.sh add-on, which used a complex bash/golang template approach. The DDEV-Upsun add-on will leverage the new PHP-based add-on implementation technique for cleaner, more maintainable code.

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

#### 3. Validation and Error Handling
- Validate Upsun configuration compatibility
- Provide clear error messages for unsupported features
- Graceful degradation when partial translation is possible

### Supported Configurations

#### PHP Applications
- PHP versions: All Upsun-supported versions (8.4, 8.3, 8.2, 8.1)
- Common PHP frameworks (Drupal, WordPress, Laravel, Symfony)
- Composer-based dependency management

#### Database Services
- MariaDB/MySQL: 11.8, 11.4, 10.11, 10.6
- Oracle MySQL: 8.0, 5.7
- PostgreSQL: 17, 16, 15, 14, 13, 12

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

## Implementation Strategy

### Phase 1: Core Functionality (MVP)
1. Basic Upsun configuration parsing
2. PHP version translation
3. Single database relationship support
4. Basic DDEV configuration generation
5. Test framework integration (Drupal, WordPress, Laravel, Symfony)

### Phase 2: Enhanced Features
1. Improved error handling and validation
2. Support for additional database versions
3. Environment variable management
4. Enhanced pull command features

### Phase 3: Advanced Integration
1. Multi-service support (within single-app constraint)
2. Advanced Upsun features translation
3. Performance optimizations
4. Comprehensive testing suite

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