<?php
/**
 * #ddev-generated
 * DDEV Upsun Add-on Installation Hook
 *
 * This script is executed when the Upsun add-on is installed via `ddev add-on get`.
 * It detects and processes Upsun configuration files (both Flex and Fixed formats)
 * to generate DDEV equivalents.
 */

declare(strict_types=1);

// Set error handling for strict mode
error_reporting(E_ALL);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});


/**
 * Main installation process
 */
function installUpsunAddOn(): void
{
    $projectRoot = realpath('..');

    echo "🔍 Detecting Upsun configuration format...\n";

    // Load format detector and interface
    require_once __DIR__ . '/UpsunConfigParserInterface.php';
    require_once __DIR__ . '/FixedConfigDetector.php';

    $detector = new \Upsun\FixedConfigDetector($projectRoot);

    // Check for Upsun Flex format first (takes precedence)
    $upsunDir = $projectRoot . '/.upsun';
    $isFlexFormat = is_dir($upsunDir) && file_exists($upsunDir . '/config.yaml');

    // Check for Upsun Fixed format
    $isFixedFormat = $detector->isFixedFormat();

    if ($isFlexFormat) {
        echo "✅ Found Upsun Flex configuration (.upsun/config.yaml)\n";
        processFlexConfiguration($projectRoot);
    } elseif ($isFixedFormat) {
        echo "✅ Found Upsun Fixed configuration (.platform.app.yaml)\n";
        echo "ℹ️  Processing legacy Platform.sh format...\n";
        processFixedConfiguration($projectRoot, $detector);
    } else {
        echo "⚠️  No Upsun configuration found.\n";
        echo "   Expected: .upsun/config.yaml (Flex format) OR .platform.app.yaml (Fixed format)\n";
        echo "   Configuration will be processed when available.\n";
        return;
    }

    echo "✅ Upsun configuration processed and DDEV files generated!\n";
    echo "💡 Run 'ddev restart' to apply configuration changes.\n";
}

/**
 * Process Upsun Flex format configuration
 */
function processFlexConfiguration(string $projectRoot): void
{
    echo "📄 Processing Upsun Flex format...\n";

    // Load Flex format parser and generator
    require_once __DIR__ . '/UpsunConfigParser.php';
    require_once __DIR__ . '/DdevConfigGenerator.php';

    try {
        $parser = new \Upsun\UpsunConfigParser($projectRoot);
        $parser->parse();

        $generator = new \Upsun\DdevConfigGenerator($parser, $projectRoot);
        $generator->generate();

        echo "   ✅ Flex format configuration processed successfully\n";
    } catch (\Exception $e) {
        echo "   ❌ Error processing Flex configuration: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Process Upsun Fixed format configuration
 */
function processFixedConfiguration(string $projectRoot, \Upsun\FixedConfigDetector $detector): void
{
    echo "📄 Processing Upsun Fixed format (legacy Platform.sh)...\n";

    // Validate Fixed format configuration
    $validationErrors = $detector->validateConfig();
    if (!empty($validationErrors)) {
        echo "   ❌ Fixed format validation errors:\n";
        foreach ($validationErrors as $error) {
            echo "      • $error\n";
        }
        throw new \Exception("Fixed format configuration validation failed");
    }

    // Display configuration summary
    $summary = $detector->getConfigSummary();
    echo "   📋 Configuration Summary:\n";
    echo "      Format: {$summary['format_name']}\n";
    echo "      Main config: {$summary['main_config']}\n";
    if ($summary['has_services']) {
        echo "      Services: {$summary['services_dir']}/services.yaml\n";
    }
    if ($summary['has_routes']) {
        echo "      Routes: {$summary['services_dir']}/routes.yaml\n";
    }

    // Load Fixed format parser and generator
    require_once __DIR__ . '/FixedConfigParser.php';
    require_once __DIR__ . '/DdevConfigGenerator.php';

    try {
        $parser = new \Upsun\FixedConfigParser($projectRoot);
        $parser->parse();

        $generator = new \Upsun\DdevConfigGenerator($parser, $projectRoot);
        $generator->generate();

        echo "   ✅ Fixed format configuration processed successfully\n";
    } catch (\Exception $e) {
        echo "   ❌ Error processing Fixed configuration: " . $e->getMessage() . "\n";
        throw $e;
    }
}

// Execute installation
installUpsunAddOn();