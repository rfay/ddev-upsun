<?php

require_once 'upsun/UpsunConfigParser.php';

use Upsun\UpsunConfigParser;
use Upsun\UpsunConfigException;

try {
    echo "=== Debugging Upsun Config Parser ===\n";
    
    $parser = new UpsunConfigParser('/Users/rfay/workspace/d11');
    $parser->parse();
    
    echo "Application Name: " . ($parser->getApplicationName() ?? 'NULL') . "\n";
    echo "PHP Version: " . ($parser->getPhpVersion() ?? 'NULL') . "\n";
    echo "Document Root: " . ($parser->getDocumentRoot() ?? 'NULL') . "\n";
    echo "Application Type: " . ($parser->getApplicationType() ?? 'NULL') . "\n";
    
    $dbConfig = $parser->getDatabaseConfig();
    echo "Database Config: " . (print_r($dbConfig, true)) . "\n";
    
    $envVars = $parser->getEnvironmentVariables();
    echo "Environment Variables: " . (print_r($envVars, true)) . "\n";
    
    $webConfig = $parser->getWebConfig();
    echo "Web Config: " . (print_r($webConfig, true)) . "\n";
    
} catch (UpsunConfigException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "UNEXPECTED ERROR: " . $e->getMessage() . "\n";
}