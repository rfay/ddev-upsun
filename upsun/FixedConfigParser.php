<?php

declare(strict_types=1);

namespace Upsun;

/**
 * #ddev-generated
 * Fixed Configuration Parser
 *
 * Parses Upsun Fixed (legacy Platform.sh) configuration files from
 * .platform.app.yaml and .platform/ directory, and provides the same
 * interface as UpsunConfigParser for compatibility.
 */
class FixedConfigParser implements UpsunConfigParserInterface
{
    private string $projectRoot;
    private string $platformDir;
    private string $appConfigFile;
    private ?array $appConfig = null;
    private ?array $servicesConfig = null;
    private ?array $routesConfig = null;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->platformDir = $this->projectRoot . '/.platform';
        $this->appConfigFile = $this->projectRoot . '/.platform.app.yaml';

        if (!file_exists($this->appConfigFile)) {
            throw new UpsunConfigException("Fixed configuration file not found: {$this->appConfigFile}");
        }

        if (!extension_loaded('yaml')) {
            throw new UpsunConfigException("PHP YAML extension is required but not available");
        }
    }

    /**
     * Load and parse Fixed configuration files
     */
    public function parse(): void
    {
        $this->loadAppConfig();
        $this->loadServicesConfig();
        $this->loadRoutesConfig();
    }

    /**
     * Get PHP version from application configuration
     */
    public function getPhpVersion(): ?string
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        $type = $this->appConfig['type'] ?? null;

        if (!$type || !str_starts_with($type, 'php:')) {
            return null;
        }

        return substr($type, 4); // Remove 'php:' prefix
    }

    /**
     * Get database configuration from relationships and services
     */
    public function getDatabaseConfig(): ?array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        $relationships = $this->appConfig['relationships'] ?? [];

        foreach ($relationships as $relationshipName => $relationshipValue) {
            // In Fixed format, relationships are typically like:
            // database: "database:mysql"
            if (is_string($relationshipValue)) {
                $parts = explode(':', $relationshipValue);
                if (count($parts) >= 2) {
                    $serviceName = $parts[0];
                    $serviceEndpoint = $parts[1];

                    // Look up the service in services configuration
                    if ($this->servicesConfig && isset($this->servicesConfig[$serviceName])) {
                        $serviceConfig = $this->servicesConfig[$serviceName];
                        $type = $serviceConfig['type'] ?? null;

                        if ($type && (str_starts_with($type, 'mysql:') ||
                                     str_starts_with($type, 'mariadb:') ||
                                     str_starts_with($type, 'oracle-mysql:') ||
                                     str_starts_with($type, 'postgresql:'))) {

                            $typeParts = explode(':', $type);
                            return [
                                'name' => $relationshipName,
                                'service' => $typeParts[0],
                                'version' => $typeParts[1] ?? 'latest',
                                'disk' => $serviceConfig['disk'] ?? null,
                                'endpoint' => $serviceEndpoint
                            ];
                        }
                    }
                }
            }

            // Handle direct service configuration in relationships
            elseif (is_array($relationshipValue) && isset($relationshipValue['type'])) {
                $type = $relationshipValue['type'];

                if (str_starts_with($type, 'mysql:') ||
                    str_starts_with($type, 'mariadb:') ||
                    str_starts_with($type, 'oracle-mysql:') ||
                    str_starts_with($type, 'postgresql:')) {

                    $typeParts = explode(':', $type);
                    return [
                        'name' => $relationshipName,
                        'service' => $typeParts[0],
                        'version' => $typeParts[1] ?? 'latest',
                        'disk' => $relationshipValue['disk'] ?? null
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Get application type (framework detection)
     */
    public function getApplicationType(): ?string
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        // Check for common framework indicators
        $webConfig = $this->appConfig['web'] ?? [];
        $locations = $webConfig['locations'] ?? [];
        $rootLocation = $locations['/'] ?? [];
        $documentRoot = $rootLocation['root'] ?? null;

        // Basic framework detection based on document root
        if ($documentRoot === 'public') {
            // Could be Laravel, Symfony, etc.
            if (file_exists($this->projectRoot . '/artisan')) {
                return 'laravel';
            }
            if (file_exists($this->projectRoot . '/bin/console')) {
                return 'symfony';
            }
        }

        if ($documentRoot === 'web' || $documentRoot === 'docroot') {
            // Drupal pattern
            if (file_exists($this->projectRoot . '/web/index.php') &&
                file_exists($this->projectRoot . '/composer.json')) {
                return 'drupal';
            }
        }

        // WordPress patterns
        if (file_exists($this->projectRoot . '/wp-config.php') ||
            file_exists($this->projectRoot . '/web/wp-config.php')) {
            return 'wordpress';
        }

        return 'php'; // Generic PHP application
    }

    /**
     * Get environment variables from configuration
     */
    public function getEnvironmentVariables(): array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        $variables = $this->appConfig['variables'] ?? [];
        $envVars = [];

        foreach ($variables as $section => $values) {
            if ($section === 'env' && is_array($values)) {
                // Direct environment variables
                foreach ($values as $key => $value) {
                    $envVars[strtoupper($key)] = (string) $value;
                }
            } elseif ($section === 'php' && is_array($values)) {
                // PHP configuration as environment variables
                foreach ($values as $key => $value) {
                    $envVars['PHP_' . strtoupper($key)] = (string) $value;
                }
            } else {
                // Other sections as prefixed environment variables
                if (is_array($values)) {
                    foreach ($values as $key => $value) {
                        $envVars[strtoupper($section . '_' . $key)] = (string) $value;
                    }
                } else {
                    $envVars[strtoupper($section)] = (string) $values;
                }
            }
        }

        return $envVars;
    }

    /**
     * Get Node.js version from dependencies
     */
    public function getNodejsVersion(): ?string
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        $dependencies = $this->appConfig['dependencies'] ?? [];
        $nodejsDeps = $dependencies['nodejs'] ?? [];

        // Look for specific nodejs version patterns
        foreach ($nodejsDeps as $package => $version) {
            if ($package === 'nodejs_version' || $package === 'nodejs') {
                if (is_string($version) && preg_match('/^(\d+)\.?(\d+)?/', $version, $matches)) {
                    return $matches[1] . ($matches[2] ?? '');
                }
            }
        }

        // Default to latest LTS if nodejs dependencies are present
        if (!empty($nodejsDeps)) {
            return '20'; // Latest LTS as of 2024
        }

        return null;
    }

    /**
     * Get mounts configuration
     */
    public function getMounts(): array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        return $this->appConfig['mounts'] ?? [];
    }

    /**
     * Get hooks configuration
     */
    public function getHooks(): array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        return $this->appConfig['hooks'] ?? [];
    }

    /**
     * Get web configuration
     */
    public function getWebConfig(): array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        return $this->appConfig['web'] ?? [];
    }

    /**
     * Get web document root from configuration
     */
    public function getDocumentRoot(): ?string
    {
        $webConfig = $this->getWebConfig();
        $locations = $webConfig['locations'] ?? [];
        $rootLocation = $locations['/'] ?? [];

        return $rootLocation['root'] ?? null;
    }

    /**
     * Get application name from configuration
     */
    public function getApplicationName(): ?string
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        return $this->appConfig['name'] ?? 'app';
    }

    /**
     * Get Redis configuration from relationships and services
     */
    public function getRedisConfig(): ?array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        $relationships = $this->appConfig['relationships'] ?? [];

        foreach ($relationships as $relationshipName => $relationshipValue) {
            if (is_string($relationshipValue)) {
                $parts = explode(':', $relationshipValue);
                if (count($parts) >= 2) {
                    $serviceName = $parts[0];
                    $serviceEndpoint = $parts[1];

                    if ($this->servicesConfig && isset($this->servicesConfig[$serviceName])) {
                        $serviceConfig = $this->servicesConfig[$serviceName];
                        $type = $serviceConfig['type'] ?? null;

                        if ($type && str_starts_with($type, 'redis:')) {
                            $typeParts = explode(':', $type);
                            return [
                                'relationship_name' => $relationshipName,
                                'service_name' => $serviceName,
                                'service' => 'redis',
                                'version' => $typeParts[1] ?? 'latest',
                                'endpoint' => $serviceEndpoint
                            ];
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get PHP runtime extensions
     */
    public function getPhpExtensions(): array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        return $this->appConfig['runtime']['extensions'] ?? [];
    }

    /**
     * Get raw application config
     */
    public function getApplicationConfig(): array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        return $this->appConfig;
    }

    /**
     * Get services configuration
     */
    public function getServicesConfig(): ?array
    {
        return $this->servicesConfig;
    }

    /**
     * Get routes configuration
     */
    public function getRoutesConfig(): ?array
    {
        return $this->routesConfig;
    }

    /**
     * Load application configuration from .platform.app.yaml
     */
    private function loadAppConfig(): void
    {
        if (!file_exists($this->appConfigFile)) {
            throw new UpsunConfigException("Fixed app config file not found: {$this->appConfigFile}");
        }

        $content = file_get_contents($this->appConfigFile);
        if ($content === false) {
            throw new UpsunConfigException("Unable to read Fixed app config file: {$this->appConfigFile}");
        }

        $parsed = yaml_parse($content);
        if ($parsed === false) {
            throw new UpsunConfigException("Invalid YAML in Fixed app config file: {$this->appConfigFile}");
        }

        $this->appConfig = $parsed;
    }

    /**
     * Load services configuration from .platform/services.yaml
     */
    private function loadServicesConfig(): void
    {
        $servicesFile = $this->platformDir . '/services.yaml';

        if (!file_exists($servicesFile)) {
            return; // Services configuration is optional
        }

        $content = file_get_contents($servicesFile);
        if ($content === false) {
            return; // Failed to read, but optional
        }

        $parsed = yaml_parse($content);
        if ($parsed !== false) {
            $this->servicesConfig = $parsed;
        }
    }

    /**
     * Load routes configuration from .platform/routes.yaml
     */
    private function loadRoutesConfig(): void
    {
        $routesFile = $this->platformDir . '/routes.yaml';

        if (!file_exists($routesFile)) {
            return; // Routes configuration is optional
        }

        $content = file_get_contents($routesFile);
        if ($content === false) {
            return; // Failed to read, but optional
        }

        $parsed = yaml_parse($content);
        if ($parsed !== false) {
            $this->routesConfig = $parsed;
        }
    }
}