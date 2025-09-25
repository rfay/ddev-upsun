<?php

declare(strict_types=1);

namespace Upsun;

/**
 * #ddev-generated
 * Fixed Configuration Detector
 *
 * Detects Upsun Fixed (legacy Platform.sh) configuration format by checking
 * for presence of .platform.app.yaml and absence of .upsun/config.yaml.
 */
class FixedConfigDetector
{
    private string $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
    }

    /**
     * Detect if project uses Upsun Fixed configuration format
     *
     * Detection logic:
     * - .platform.app.yaml file must exist
     * - .upsun/config.yaml must NOT exist (complete Flex takes precedence)
     *
     * @return bool True if Fixed format is detected
     */
    public function isFixedFormat(): bool
    {
        $platformAppYaml = $this->projectRoot . '/.platform.app.yaml';
        $upsunConfigYaml = $this->projectRoot . '/.upsun/config.yaml';

        // Check if .platform.app.yaml exists
        if (!file_exists($platformAppYaml)) {
            return false;
        }

        // Check that complete Flex configuration does NOT exist (Flex format takes precedence)
        if (file_exists($upsunConfigYaml)) {
            return false;
        }

        return true;
    }

    /**
     * Get the path to the main Fixed configuration file
     */
    public function getMainConfigPath(): string
    {
        return $this->projectRoot . '/.platform.app.yaml';
    }

    /**
     * Get the path to the Fixed services directory
     */
    public function getServicesDir(): string
    {
        return $this->projectRoot . '/.platform';
    }

    /**
     * Check if Fixed services configuration exists
     */
    public function hasServicesConfig(): bool
    {
        $servicesFile = $this->getServicesDir() . '/services.yaml';
        return file_exists($servicesFile);
    }

    /**
     * Check if Fixed routes configuration exists
     */
    public function hasRoutesConfig(): bool
    {
        $routesFile = $this->getServicesDir() . '/routes.yaml';
        return file_exists($routesFile);
    }

    /**
     * Get configuration type string for logging
     */
    public function getFormatName(): string
    {
        return 'Upsun Fixed (legacy Platform.sh)';
    }

    /**
     * Validate that Fixed configuration files are readable
     *
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(): array
    {
        $errors = [];
        $mainConfig = $this->getMainConfigPath();

        if (!is_readable($mainConfig)) {
            $errors[] = "Main configuration file is not readable: {$mainConfig}";
        }

        // Check if main config file has valid YAML content
        if (file_exists($mainConfig)) {
            $content = file_get_contents($mainConfig);
            if ($content === false) {
                $errors[] = "Unable to read configuration file: {$mainConfig}";
            } elseif (!extension_loaded('yaml')) {
                $errors[] = "PHP YAML extension is required but not available";
            } else {
                $parsed = yaml_parse($content);
                if ($parsed === false) {
                    $errors[] = "Invalid YAML in configuration file: {$mainConfig}";
                }
            }
        }

        // Validate optional services configuration
        if ($this->hasServicesConfig()) {
            $servicesFile = $this->getServicesDir() . '/services.yaml';
            if (!is_readable($servicesFile)) {
                $errors[] = "Services configuration file is not readable: {$servicesFile}";
            }
        }

        // Validate optional routes configuration
        if ($this->hasRoutesConfig()) {
            $routesFile = $this->getServicesDir() . '/routes.yaml';
            if (!is_readable($routesFile)) {
                $errors[] = "Routes configuration file is not readable: {$routesFile}";
            }
        }

        return $errors;
    }

    /**
     * Get summary information about detected Fixed configuration
     */
    public function getConfigSummary(): array
    {
        if (!$this->isFixedFormat()) {
            return [];
        }

        return [
            'format' => 'fixed',
            'format_name' => $this->getFormatName(),
            'main_config' => $this->getMainConfigPath(),
            'services_dir' => $this->getServicesDir(),
            'has_services' => $this->hasServicesConfig(),
            'has_routes' => $this->hasRoutesConfig(),
        ];
    }
}