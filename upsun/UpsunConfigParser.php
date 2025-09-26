<?php

declare(strict_types=1);

namespace Upsun;

/**
 * #ddev-generated
 * Upsun Configuration Parser
 *
 * Parses Upsun configuration files from the .upsun directory and extracts
 * relevant configuration data for DDEV translation.
 */
class UpsunConfigParser implements UpsunConfigParserInterface
{
    private string $projectRoot;
    private string $upsunDir;
    private ?array $appConfig = null;
    private ?array $servicesConfig = null;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->upsunDir = $this->projectRoot . '/.upsun';
        
        if (!is_dir($this->upsunDir)) {
            throw new UpsunConfigException("Upsun configuration directory not found: {$this->upsunDir}");
        }

        if (!extension_loaded('yaml')) {
            throw new UpsunConfigException("PHP YAML extension is required but not available");
        }
    }

    /**
     * Load and parse Upsun configuration files
     */
    public function parse(): void
    {
        $this->loadAppConfig();
        $this->loadServicesConfig();
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
     * Get database configuration from relationships
     */
    public function getDatabaseConfig(): ?array
    {
        if (!$this->appConfig) {
            throw new UpsunConfigException("Configuration not parsed. Call parse() first.");
        }

        $relationships = $this->appConfig['relationships'] ?? [];
        
        foreach ($relationships as $name => $config) {
            // Check if this is a service reference (relationships can be just service names)
            if ($this->servicesConfig && isset($this->servicesConfig[$name])) {
                $serviceConfig = $this->servicesConfig[$name];
                $type = $serviceConfig['type'] ?? null;
                
                if ($type && (str_starts_with($type, 'mysql:') ||
                             str_starts_with($type, 'mariadb:') ||
                             str_starts_with($type, 'oracle-mysql:') ||
                             str_starts_with($type, 'postgresql:'))) {
                    
                    $parts = explode(':', $type);
                    return [
                        'name' => $name,
                        'service' => $parts[0],
                        'version' => $parts[1] ?? 'latest',
                        'disk' => $serviceConfig['disk'] ?? null
                    ];
                }
            }
            
            // Also check for inline relationship config with type
            if (is_array($config) && isset($config['type'])) {
                $type = $config['type'];
                
                // Check for database services
                if (str_starts_with($type, 'mysql:') ||
                    str_starts_with($type, 'mariadb:') ||
                    str_starts_with($type, 'oracle-mysql:') ||
                    str_starts_with($type, 'postgresql:')) {
                    
                    $parts = explode(':', $type);
                    return [
                        'name' => $name,
                        'service' => $parts[0],
                        'version' => $parts[1] ?? 'latest',
                        'disk' => $config['disk'] ?? null
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
        $root = $rootLocation['root'] ?? null;

        // Basic framework detection based on web root
        if ($root === 'public') {
            // Could be Laravel, Symfony, etc.
            if (file_exists($this->projectRoot . '/artisan')) {
                return 'laravel';
            }
            if (file_exists($this->projectRoot . '/bin/console')) {
                return 'symfony';
            }
        }

        if ($root === 'web' || $root === 'docroot') {
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

        // Check for applications section (newer Upsun config style)
        if (isset($this->appConfig['applications']) && is_array($this->appConfig['applications'])) {
            foreach ($this->appConfig['applications'] as $appName => $appConfig) {
                if (isset($appConfig['docroot'])) {
                    return [
                        'docroot' => $appConfig['docroot'],
                        'app' => $appName,
                        // Optionally include more keys from $appConfig if needed
                    ];
                }
            }
        }

        // Fallback to legacy 'web' config
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

        return $this->appConfig['name'] ?? null;
    }

    /**
     * Load application configuration
     */
    private function loadAppConfig(): void
    {
        $configFile = $this->upsunDir . '/config.yaml';
        
        if (!file_exists($configFile)) {
            throw new UpsunConfigException("Upsun application config file not found: {$configFile}");
        }

        $content = file_get_contents($configFile);
        if ($content === false) {
            throw new UpsunConfigException("Unable to read Upsun config file: {$configFile}");
        }

        $parsed = yaml_parse($content);
        if ($parsed === false) {
            throw new UpsunConfigException("Invalid YAML in Upsun config file: {$configFile}");
        }

        // Upsun always uses applications section structure
        if (!isset($parsed['applications']) || !is_array($parsed['applications'])) {
            throw new UpsunConfigException("Invalid Upsun config: missing 'applications' section");
        }
        
        // Take the first application config from the applications section
        $appConfigs = $parsed['applications'];
        $this->appConfig = array_values($appConfigs)[0];
        
        // Store the services section if present
        if (isset($parsed['services'])) {
            $this->servicesConfig = $parsed['services'];
        }
        
        // Store the application name from the key
        $appNames = array_keys($appConfigs);
        $this->appConfig['name'] = $appNames[0];
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
        
        foreach ($relationships as $relationshipName => $relationshipConfig) {
            // Handle relationship format: "redis: 'cache:redis'"
            if (is_string($relationshipConfig)) {
                $parts = explode(':', $relationshipConfig);
                if (count($parts) >= 2) {
                    $serviceName = $parts[0]; // e.g., "cache" 
                    $serviceType = $parts[1]; // e.g., "redis"
                    
                    if ($serviceType === 'redis' && $this->servicesConfig && isset($this->servicesConfig[$serviceName])) {
                        $serviceConfig = $this->servicesConfig[$serviceName];
                        $type = $serviceConfig['type'] ?? null;
                        
                        if ($type && str_starts_with($type, 'redis:')) {
                            $typeParts = explode(':', $type);
                            return [
                                'relationship_name' => $relationshipName,
                                'service_name' => $serviceName,
                                'service' => 'redis',
                                'version' => $typeParts[1] ?? 'latest'
                            ];
                        }
                    }
                }
            }
            
            // Handle direct service references in relationships
            if ($this->servicesConfig && isset($this->servicesConfig[$relationshipName])) {
                $serviceConfig = $this->servicesConfig[$relationshipName];
                $type = $serviceConfig['type'] ?? null;
                
                if ($type && str_starts_with($type, 'redis:')) {
                    $typeParts = explode(':', $type);
                    return [
                        'relationship_name' => $relationshipName,
                        'service_name' => $relationshipName,
                        'service' => 'redis',
                        'version' => $typeParts[1] ?? 'latest'
                    ];
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
     * Load services configuration (if exists)
     */
    private function loadServicesConfig(): void
    {
        $servicesFile = $this->upsunDir . '/services.yaml';
        
        if (file_exists($servicesFile)) {
            $content = file_get_contents($servicesFile);
            if ($content !== false) {
                $parsed = yaml_parse($content);
                if ($parsed !== false) {
                    $this->servicesConfig = $parsed;
                }
            }
        }
    }
}

/**
 * Custom exception for Upsun configuration parsing errors
 */
class UpsunConfigException extends \Exception
{
}