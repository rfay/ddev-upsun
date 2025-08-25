<?php
/**
 * DDEV Upsun Add-on Installation Hook
 * 
 * This script is executed when the Upsun add-on is installed via `ddev add-on get`.
 * It detects and processes Upsun configuration files to generate DDEV equivalents.
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
    $projectRoot = '/var/www/html';
    $ddevDir = $projectRoot . '/.ddev';
    $upsunDir = $projectRoot . '/.upsun';
    
    echo "🔍 Checking for Upsun configuration...\n";
    
    if (!is_dir($upsunDir)) {
        echo "⚠️  No .upsun directory found. Upsun configuration will be processed when available.\n";
        return;
    }
    
    echo "✅ Found .upsun directory, processing configuration...\n";
    
    // Load configuration processor and generator
    require_once __DIR__ . '/UpsunConfigParser.php';
    require_once __DIR__ . '/DdevConfigGenerator.php';
    
    $parser = new \Upsun\UpsunConfigParser($projectRoot);
    $parser->parse();
    
    $generator = new \Upsun\DdevConfigGenerator($parser, $projectRoot);
    $generator->generate();
    
    echo "✅ Upsun configuration processed and DDEV files generated!\n";
    echo "💡 Run 'ddev restart' to apply configuration changes.\n";
}

// Execute installation
installUpsunAddOn();