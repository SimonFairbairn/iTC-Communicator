#! /usr/local/bin/php
<?php 

$scriptVersion = "1.0";

// Listen to the shell. Peer inside. Turn it upside down and shake the options out.
$options = getopt('v:c:p:', array("version:", "configuration:", "path:"));

$version = false;
if ( isset( $options['v'] ) || isset( $options['version'] ) ) {
	$version = isset( $options['v'] ) ? $options['v'] : $options['version'];
}
$config = "configuration.json";
if ( isset( $options['c'] ) || isset( $options['configuration'] ) ) {
	$config = isset( $options['c'] ) ? $options['c'] : $options['configuration'];
}
$path = false;
if ( isset( $options['p'] ) || isset( $options['path'] ) ) {
	$path = isset( $options['p'] ) ? $options['p'] : $options['path'];
	if ( $path == "" ) {
		$path = false;
	}
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
$metadataManager = new ManageMetadata( $version, $path );

$exitMessage = "Update aborted\n\n";

// First, we try loading it...
try {
	$metadataManager->loadMetadata( $config );
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

class ManageMetadata {
	private $_configuration;
	private $_version;
	private $_fullMetadata;
	private $_metadata;
	private $_metadataPath;
	private $_path;


	function __construct( $version = false, $path = false ) {
		$this->_version = $version;
		$this->_path = $path;
	}

	/**
	 * Loads the itmsp file from the info in the configuration file, which is loaded from the passed $filename
	 * 
	 * @param String $filename The location of the configuration file 
	 * 
	 * @throws InvalidArgumentException if it can't find the configuration file
	 * @throws InvalidArgumentException if it can't find the metadata file
	 * @throws InvalidArgumentException if it can't find the version within the metadata
	 * 
	 * @return void 
	 */
	function loadMetadata( $filename = false ) {
		if ( !$this->_loadConfiguration( $filename ) ) {
			throw new InvalidArgumentException("Error reading configuration file from `" . $this->_path . $filename . "`. Check that the file exists, is correctly formatted JSON, and that the path is correct.");
		}

		$this->_metadataPath = $this->_configurePathForFile( $this->_configuration->itmsFile . "/metadata.xml" );

		if ( file_exists( $this->_metadataPath ) && $this->_fullMetadata = simplexml_load_file( $this->_metadataPath ) ) {
			$newVersion = false;
			foreach ( $this->_fullMetadata->software->software_metadata->versions->version as $isNewVersion ) {
				if ( $isNewVersion['string'] == $this->_version ) {
					$newVersion = $isNewVersion;
				}
			}
			if ( !$newVersion ) {
				throw new InvalidArgumentException("Error reading version " . $this->_version . " from the metadata XML file in the itmsp package. Has this version been created in iTunes Connect?");
			} else {
				$this->_metadata = $newVersion;
				return true;
			}
		} else {
			throw new InvalidArgumentException("Error reading metadata file. Is the path to the itmsp package correct? Have you run the iTMSTransporter to download the latest version? See https://itunesconnect.apple.com/WebObjects/iTunesConnect.woa/ra/ng/resources_page for the Transporter User Guide.");
		}
	}	

	/**
	 * Update the metadata from the information provided in the configuration file
	 * 
	 * @throws InvalidArgumentException if it can't find the configuration file
	 * @throws InvalidArgumentException if it can't find the metadata
	 * 
	 * @return type
	 */
	function updateMetadata() {
		if ( !$this->_configuration ) {
			throw new InvalidArgumentException("Error loading configuration", 1);
		}
		if ( !$this->_metadata ) {
			throw new InvalidArgumentException("Error loading metadata", 1);
		}

		// Let's get the locales
		$configurationCopy = $this->_configuration->locales;
		
		foreach ( $this->_metadata->locales->locale as $localeNode ) {
			// If they already exist, we can update them and delete them from our local configuration copy
			if ( isset( $configurationCopy->$localeNode["name"])  ) {
				$this->_updateMetadataWithLocaleInfo( $configurationCopy->$localeNode["name"], $localeNode, $localeNode["name"] );
				unset( $configurationCopy->$localeNode["name"] );
			}
		}
	
		// If there's anything left, that means we're adding some new locales to the metadata
		foreach ( $configurationCopy as $locale => $remainingLocales ) {
			$child = $this->_metadata->locales->addChild("locale");
			$child->addAttribute( "name", $locale );

			// Get the title of the app from the first locale as a default
			$title = $this->_metadata->locales->locale[0]->title->__toString();
			$title =  htmlentities($title);
			$child->addChild("title", $title );

			$this->_updateMetadataWithLocaleInfo( $remainingLocales, $child );
			$this->_updateMetadataWithGlobalInfo( $this->_configuration, $child );
		}	
	}

	/**
	 * Tries to write the new metadata file to the itmsp package
	 * 
	 * @throws Exception if it is unable to write the file.
	 * 
	 * @return String 
	 */
	function writeMetadata() {
		if ( $this->_fullMetadata ) {
			if ( file_put_contents( $this->_metadataPath , $this->_fullMetadata->asXML() ) !== false ) {
				return "File written successfully";
			}
		}
		throw new Exception( "Unable to write file" );
		return "Unable to write file";
	}


	/**
	 * Attempts to read the configuration file
	 * 
	 * @param String $filename The location of the configuration file
	 * 
	 * @return Bool True if it was able to read and parse the JSON, false otherwise
	 */
	private function _loadConfiguration( $filename = false) {
		$filepath = $this->_path . $filename;
		if ( !file_exists( $filepath ) ) return false;
		if ( $file = file_get_contents($filepath) ) {
			$json = json_decode($file);	
			if ( $json !== NULL ) {
				$this->_configuration = $json;
				return true;
			}
		}
		return false;
	}

	/**
	 * Tries to figure out if we're dealing with an absolute path or a relative one
	 * It's not very smart, but it tries.
	 * 
	 * @param String $file The path to decipher
	 * @return String The deciphered path
	 */	
	private function _configurePathForFile( $file ) {
		
		// If we lead with a trailing slash, we're going to wrecklessly assume an absolute path. 
		if ( substr($file, 0, 1 ) == "/" ) {
			return  $file;
		} else {
			return  ( isset( $this->_path ) && $this->_path != "" ) ? $this->_path . "/" . $file : $file;
		}
	}

	/**
	 * Global info is used for every language variation. Right now, this is just the URLs
	 * 
	 * @param type $globalConfig The part of the configuration file featuring the right info
	 * @param type SimpleXMLElement $node The XML node to append to
	 * 
	 * @return void
	 */
	private function _updateMetadataWithGlobalInfo( $globalConfig, SimpleXMLElement $node ) {
		$this->_updateNodeWithNameWithValueToDoc( "software_url", $globalConfig->software_url, $node );
		$this->_updateNodeWithNameWithValueToDoc( "privacy_url", $globalConfig->privacy_url, $node );
		$this->_updateNodeWithNameWithValueToDoc( "support_url", $globalConfig->support_url, $node );
	}

	/**
	 * The meat and potatoes of this script. 
	 * 
	 * @param Object $localeInfo The locale info from the configuration file
	 * @param SimpleXMLElement $node The node to append things to
	 * @param String $screenshotLocale The current locale for the screenshots
	 * 
	 * @return void
	 */
	private function _updateMetadataWithLocaleInfo( $localeInfo, SimpleXMLElement $node, $screenshotLocale = false) {
		$this->_addNodeWithTitleFromFilenameToDoc( "description", $localeInfo->description, $node );
		$this->_addNodeWithTitleFromFilenameToDoc( "version_whats_new", $localeInfo->version_whats_new, $node );	
		$this->_updateNodeWithNameWithValueToDoc( "title", $localeInfo->title, $node );

		if ( isset( $localeInfo->keywords ) ) {
			unset( $node->keywords );
			if ( !isset( $node->keywords ) ) {
				$node->addChild( "keywords" );
			}
			foreach ( $localeInfo->keywords as $keyword ) {
				$this->_updateNodeWithNameWithValueToDoc( "keyword", $keyword, $node->keywords, true );
			}
		}

		// If we're not updating screenshots, we can stop here. 
		if ( !isset( $localeInfo->screenshots ) ) {
			return; 
		}

		unset( $node->software_screenshots );

		$node->addChild("software_screenshots");

		$path = $this->_configurePathForFile($localeInfo->screenshots);

		if ( file_exists( $path ) ) {
			$files = scandir( $path );
			foreach ( $files as $screenshot ) {
				$screenshot = strtolower( $screenshot );

				$screenshotPath = $path . "/" . $screenshot;

				if ( strpos($screenshot, "screenshot") !== false ) {
					$position = false;
					$type = false;
					$target = false;
					if ( substr( $screenshot, 0, 3) === "3_5" ) {
						$target = "iOS-3.5-in";
						$type = "3_5";
					}
					if ( substr( $screenshot, 0, 1) === "4" ) {
						$target = "iOS-4-in";
						$type = "4";
					}	
					if ( substr( $screenshot, 0, 3) === "4_7" ) {
						$target = "iOS-4.7-in";
						$type = "4_7";
					}	
					if ( substr( $screenshot, 0, 3) === "5_5" ) {
						$target = "iOS-5.5-in";
						$type = "5_5";
					}	
					if ( substr( $screenshot, 0, 4) === "ipad" ) {
						$target = "iOS-iPad";
						$type = "ipad";
					}

					$position = str_replace("$type-screenshot-", "", $screenshot );
					$position = str_replace(".png", "", $position);
					if ( !$position || $position > 5 ) continue;

					$newScreenshot = str_replace(".png", "-" . $screenshotLocale . ".png", $screenshot);

					$screen = $node->software_screenshots->addChild("software_screenshot");
					$screen->addAttribute("display_target", $target );
					$screen->addAttribute("position", $position );
					$screen->addChild('file_name', $newScreenshot );
					$screen->addChild('size', filesize( $screenshotPath ) );
					$fileNode = $screen->addChild('checksum' );
					$fileNode->addAttribute("type", "md5");
					$screen->checksum = md5_file( $screenshotPath );
					copy($screenshotPath, 
						$this->_configurePathForFile($this->_configuration->itmsFile . "/" . $newScreenshot) );

				}
			}
		} else {
			echo "ERROR: Screenshot directory not found: `$path`\n";
			echo "ERROR: Screenshots not updated.\n";
		}
	}

	/**
	 * Adds a node for the given title to the given child with the contents of the passed filename
	 * 
	 * @param String $title The new node name
	 * @param String $filename The filename to add the contents from
	 * @param SimpleXMLElement $child The child to add the new node to
	 * 
	 * @return Bool true if successfully added
	 */	
	private function _addNodeWithTitleFromFilenameToDoc( $title = false, $filename = false, SimpleXMLElement $child ) {
		$filepath = $this->_configurePathForFile($filename);
		if ( file_exists( $filepath ) ) {
			if ( $contents = file_get_contents( $filepath ) ) {
				return $this->_updateNodeWithNameWithValueToDoc( $title, $contents, $child );
			}
		}
		echo  "ERROR: Unable to add " . $title . " from `$filepath`\n";
		echo "ERROR: $title not updated\n";
		return false;
	}

	private function _updateNodeWithNameWithValueToDoc( $title = false, $contents = false, SimpleXMLElement $node, $append = false ) {
		if ( !$title || !$contents ) {
			echo "ERROR: $title not found in configruation file.\n";
			echo "ERROR: $title not updated.\n";
			return false;
		}
		if ( !$append && isset( $node->$title ) ) {
			$node->$title = $contents;
		} else {
			$node->addChild($title, $contents );
		}
		return true;
	}	
}


