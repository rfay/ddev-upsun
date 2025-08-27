<?php

// #ddev-generated
// Debugging script for UpsunConfigParser
require_once 'upsun/UpsunConfigParser.php';

use Upsun\UpsunConfigParser;
use Upsun\UpsunConfigException;

try {
    echo "=== Debugging Upsun Config Parser ===\n";
    
    $projectRoot = realpath('..');
    echo "Project Root: " . $projectRoot . "\n";
    
    $parser = new UpsunConfigParser($projectRoot);
    $parser->parse();
    
    // Debug raw config structures
    $reflection = new ReflectionClass($parser);
    $appConfigProp = $reflection->getProperty('appConfig');
    $appConfigProp->setAccessible(true);
    $servicesConfigProp = $reflection->getProperty('servicesConfig');
    $servicesConfigProp->setAccessible(true);
    
    $appConfig = $appConfigProp->getValue($parser);
    $servicesConfig = $servicesConfigProp->getValue($parser);
    
    echo "Raw App Config: " . (print_r($appConfig, true)) . "\n";
    echo "Raw Services Config: " . (print_r($servicesConfig, true)) . "\n";
    
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
    
    $nodejsVersion = $parser->getNodejsVersion();
    echo "Node.js Version: " . ($nodejsVersion ?? 'NULL') . "\n";
    
    $mounts = $parser->getMounts();
    echo "Mounts: " . (print_r($mounts, true)) . "\n";
    
    $hooks = $parser->getHooks();
    echo "Hooks: " . (print_r($hooks, true)) . "\n";
    
} catch (UpsunConfigException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "UNEXPECTED ERROR: " . $e->getMessage() . "\n";
}