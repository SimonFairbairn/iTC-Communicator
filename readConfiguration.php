<?php

require_once('manageMetadata.php');

$metadataManager = new MetadataManager();
$configFile = 'configuration.json';
$options = getopt('si:c:');

if ( isset( $options['c'] ) ) {
	$configFile = $options['c'];
}
$config = $metadataManager->loadConfiguration($configFile);
if ( !$config ) {
	die();
}

if ( isset( $options['s'] ) ) {
	echo $config->sku;
} else if ( isset( $options['i'] ) ) {
	if ( isset( $config->itmsFile ) ) {
		echo $config->itmsFile;	
	} 
	
} else {
	echo $config->appleID;
}


if ( !isset( $config->itmsFile ) ) {
	$path = getcwd();
	$itmsFile = $path . "/" . $config->sku . ".itmsp";

	$configPath = $path . "/" . $configFile;

	$config->itmsFile = $itmsFile;
	$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	file_put_contents($configPath, $json);
}