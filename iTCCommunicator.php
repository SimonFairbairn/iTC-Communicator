#! /usr/local/bin/php
<?php 

require_once('manageMetadata.php');

define('DEBUG', false);

$scriptVersion = "1.0.3";

// Listen to the shell. Peer inside. Turn it upside down and shake the options out.
$options = getopt('v:c:p:');

$version = false;
$version = ( isset( $options['v'] )  ) ? $options['v'] : false;

$config = (isset( $options['c'] )) ? $options['c'] : false;
if ( !$config ) {
	$config = "configuration.json";
}

$path = (isset( $options['p'] ) ) ? $options['p'] : false;
if ( $path == "" ) {
	$path = false;
}

if ( !$version ) {
	echo "\niTCCommunicator Version $scriptVersion\n";
	echo "\nERROR: You must provide a valid version number\n\n";
	echo "-v --version : The version number you want to edit. This version must already exist in iTunesConnect.\n";
	echo "-c --configuration : The configuration file you want to use. Deafaults to ./configuration.json.\n";
	echo "-p --path : The default path to use. If your configuration file uses relative paths, set this if you want to run this script from elsewhere.\n\n";
	die();
}

// Alright, if we got this far, let's attmept to upate the sucker.
$metadataManager = new MetadataManager( $version, $path );

$exitMessage = "Update aborted\n\n";

// First, we try loading it...
try {
	$metadataManager->loadMetadata( $config );
	$path = ( $path ) ? $path : "./$config";
	echo "Metadata loaded successfully from $path\n";
} catch (Exception $e) {
	echo "\n"	. $e->getMessage(). "\n\n";
	die($exitMessage);
}

// ...then we try updating it...
try {
	$metadataManager->updateMetadata();
	echo "Metadata updated successfully\n";
} catch (Exception $e) {
	echo "\n" . $e->getMessage() . "\n\n";
	die($exitMessage);
}

// ...finally, we try writing it.
try {
	$metadataManager->writeMetadata();
	echo "Metadata written successfully\n";
} catch ( Exception $e ) {
	echo "Couldn't write file: "	. $e->getMessage() . "\n";	
	die($exitMessage);
}

