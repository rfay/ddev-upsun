<?php

declare(strict_types=1);

namespace Upsun;

/**
 * #ddev-generated
 * Upsun Configuration Parser Interface
 *
 * Common interface for both Upsun Flex and Fixed configuration parsers
 * to ensure compatibility with DdevConfigGenerator.
 */
interface UpsunConfigParserInterface
{
    /**
     * Load and parse configuration files
     */
    public function parse(): void;

    /**
     * Get PHP version from application configuration
     */
    public function getPhpVersion(): ?string;

    /**
     * Get database configuration from relationships
     */
    public function getDatabaseConfig(): ?array;

    /**
     * Get application type (framework detection)
     */
    public function getApplicationType(): ?string;

    /**
     * Get environment variables from configuration
     */
    public function getEnvironmentVariables(): array;

    /**
     * Get Node.js version from dependencies
     */
    public function getNodejsVersion(): ?string;

    /**
     * Get mounts configuration
     */
    public function getMounts(): array;

    /**
     * Get hooks configuration
     */
    public function getHooks(): array;

    /**
     * Get web configuration
     */
    public function getWebConfig(): array;

    /**
     * Get web document root from configuration
     */
    public function getDocumentRoot(): ?string;

    /**
     * Get application name from configuration
     */
    public function getApplicationName(): ?string;

    /**
     * Get Redis configuration from relationships and services
     */
    public function getRedisConfig(): ?array;

    /**
     * Get PHP runtime extensions
     */
    public function getPhpExtensions(): array;

    /**
     * Get raw application config
     */
    public function getApplicationConfig(): array;
}