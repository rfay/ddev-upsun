<?php
/**
 * Simple test script for UpsunConfigParser
 * Run this to verify the parser works with example configurations
 */

require_once __DIR__ . '/UpsunConfigParser.php';

use Upsun\UpsunConfigParser;
use Upsun\UpsunConfigException;

function testParser(string $exampleDir): void
{
    echo "Testing: {$exampleDir}\n";
    echo str_repeat('-', 40) . "\n";

    try {
        $parser = new UpsunConfigParser($exampleDir);
        $parser->parse();

        echo "✅ Configuration parsed successfully\n";
        echo "Name: " . ($parser->getApplicationName() ?? 'N/A') . "\n";
        echo "PHP Version: " . ($parser->getPhpVersion() ?? 'N/A') . "\n";
        echo "Document Root: " . ($parser->getDocumentRoot() ?? 'N/A') . "\n";
        echo "Application Type: " . ($parser->getApplicationType() ?? 'N/A') . "\n";

        $dbConfig = $parser->getDatabaseConfig();
        if ($dbConfig) {
            echo "Database: {$dbConfig['service']} {$dbConfig['version']}\n";
        } else {
            echo "Database: None configured\n";
        }

        $envVars = $parser->getEnvironmentVariables();
        echo "Environment Variables: " . count($envVars) . "\n";
        foreach ($envVars as $key => $value) {
            echo "  {$key}={$value}\n";
        }

    } catch (UpsunConfigException $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// Test with example configurations
$examples = [
    __DIR__ . '/../prd/examples/drupal-composer',
    __DIR__ . '/../prd/examples/wordpress-composer',
    __DIR__ . '/../prd/examples/laravel-api',
    __DIR__ . '/../prd/examples/symfony-webapp'
];

foreach ($examples as $example) {
    // Create symlink to .upsun for testing
    $upsunLink = $example . '/.upsun';
    $upsunDir = $example . '/upsun';
    
    if (is_dir($upsunDir) && !is_link($upsunLink)) {
        symlink($upsunDir, $upsunLink);
    }
    
    if (is_dir($upsunLink)) {
        testParser($example);
    } else {
        echo "⚠️  Skipping {$example} - no .upsun directory\n\n";
    }
}

echo "Testing completed!\n";