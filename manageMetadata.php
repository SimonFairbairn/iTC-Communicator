<?php 

class MetadataManager {
	private $_configuration;
	private $_version;
	private $_fullMetadata;
	private $_metadata;
	private $_metadataPath;
	private $_path;
	private $_previousVersionExists = false;

	private $_currentLocale;
	private $_currentNode;

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
		if ( !$this->loadConfiguration( $filename ) ) {
			throw new InvalidArgumentException("Error reading configuration file from `" . $this->_path . $filename . "`. Check that the file exists, is correctly formatted JSON, and that the path is correct.");
		}

		$this->_metadataPath = $this->_configurePathForFile( $this->_configuration->itmsFile . "/metadata.xml" );
		if ( file_exists( $this->_metadataPath ) && $this->_fullMetadata = simplexml_load_file( $this->_metadataPath ) ) {
			$newVersion = false;
			// Counts the number of versions. If there's only one, then the app hasn't received an update
			// And the what's new should be ignored.
			$versionCount = 0;
			foreach ( $this->_fullMetadata->software->software_metadata->versions->version as $isNewVersion ) {
				$versionCount++;
				if ( $isNewVersion['string'] == $this->_version ) {
					$newVersion = $isNewVersion;
				}
			}
			$_previousVersionExists = ( $versionCount > 1 ) ? true : false;
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

		$this->_updateIAPs();

		// Let's get the locales
		$configurationCopy = $this->_configuration->locales;
		
		foreach ( $this->_metadata->locales->locale as $localeNode ) {
			// If they already exist, we can update them and delete them from our local configuration copy
			if ( isset( $configurationCopy->$localeNode["name"])  ) {
				$this->_currentLocale = $localeNode["name"]->__toString();
				$this->_currentNode = $localeNode;
				$this->_updateMetadataWithLocaleInfo( );
				
				unset( $configurationCopy->$localeNode["name"] );
			}
		}
		// If there's anything left, that means we're adding some new locales to the metadata
		foreach ( $configurationCopy as $locale => $remainingLocales ) {
			$child = $this->_metadata->locales->addChild("locale");
			$this->_currentNode = $child;
			$child->addAttribute( "name", $locale );

			// Get the title of the app from the first locale as a default
			$title = $this->_metadata->locales->locale[0]->title->__toString();
			$title =  htmlentities($title);
			$child->addChild("title", $title );

			$this->_currentLocale = $locale;

			$this->_updateMetadataWithLocaleInfo( );
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
			if ( DEBUG ) {
				print_r( $this->_fullMetadata->asXML() );
			}
			$dom = new DOMDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dom->loadXML($this->_fullMetadata->asXML());

			if ( file_put_contents( $this->_metadataPath , $dom->saveXML() ) !== false ) {
				return "File written successfully";
			}
		}
		throw new Exception( "Unable to write file" );
		return "Unable to write file";
	}


	private function _updateIAPs() {
		$node = $this->_fullMetadata->software->software_metadata;
		// Do we got IAPs?
		if ( isset( $this->_configuration->iaps ) ) {
			unset( $node->in_app_purchases );
			$node->addChild( "in_app_purchases" );
			$idx = 0;
			foreach ( $this->_configuration->iaps as $iap ) {
				
				if ( !isset( $iap->id ) || !isset( $iap->ref ) || !isset( $iap->type) || !isset( $iap->price_tier) || !isset( $iap->locales )  ) {
					print( "ERROR: One or more required fields (id, ref, type, price_tier, at least one locale) missing for In App Purchase at index $idx. Ignoring...\n");
					continue;
				} 

				$validType = false;
				switch ( $iap->type ) {
					case "consumable" : 
					case "non-consumable" : 
					case "subscription" : 
					case "free-subscription" : 
						$validType = true;
						break;
				}
				if ( !$validType ) {
					print( "ERROR: Type is not valid (consumable, non-consumable, subscription, free-subscription) for In App Purchase at index $idx. Ignoring...\n");
					continue;
				}


				$child = $node->in_app_purchases->addChild("in_app_purchase");
				$this->_updateNodeWithNameWithValueToDoc("product_id", $iap->id, $child);
				$this->_updateNodeWithNameWithValueToDoc("reference_name", $iap->ref, $child);
				$this->_updateNodeWithNameWithValueToDoc("type", $iap->type, $child);

				$products = $child->addChild("products");
				$product = $products->addChild("product");

				$this->_updateNodeWithNameWithValueToDoc("wholesale_price_tier", $iap->price_tier, $product);
				$this->_updateNodeWithNameWithValueToDoc("cleared_for_sale", "true", $product);
				
				$path = $this->_configurePathForFile( $iap->screenshot );
				$screenshotNode = $child->addChild("review_screenshot");
				$this->_addScreenshotWithPathToNode( $path, $screenshotNode );

				$locales = $child->addChild('locales');

				foreach ( $iap->locales as $name => $localeInfo ) {
					$locale = $locales->addChild('locale');
					$locale->addAttribute('name', $name);
					$this->_updateNodeWithNameWithValueToDoc("title", $localeInfo->title, $locale);
					$this->_updateNodeWithNameWithValueToDoc("description", $localeInfo->description, $locale);
			
				}

				$idx++;
			}
		}		
	}

	/**
	 * Attempts to read the configuration file
	 * 
	 * @param String $filename The location of the configuration file
	 * 
	 * @return Bool True if it was able to read and parse the JSON, false otherwise
	 */
	public function loadConfiguration( $filename = false) {
		$filepath = $this->_path . $filename;
		if ( !file_exists( $filepath ) ) return false;
		if ( $file = file_get_contents($filepath) ) {
			$json = json_decode($file);	
			if ( $json !== NULL ) {
				$this->_configuration = $json;
				return $json;
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
	 * The meat and potatoes of this script. 
	 * 
	 * @param Object $localeInfo The locale info from the configuration file
	 * @param SimpleXMLElement $node The node to append things to
	 * @param String $screenshotLocale The current locale for the screenshots
	 * 
	 * @return void
	 */
	private function _updateMetadataWithLocaleInfo() {

		$node = $this->_currentNode;
		$locale = $this->_currentLocale;
		$localeInfo = $this->_configuration->locales->$locale;

		if ( DEBUG ) {
			print_r( $this->_currentLocale );
			print_r( $localeInfo );
			// print_r( $node );
		}

		$this->_updateNodeWithNameWithValueToDoc( "software_url", $localeInfo->software_url, $node);
		$this->_updateNodeWithNameWithValueToDoc( "privacy_url", $localeInfo->privacy_url, $node );
		$this->_updateNodeWithNameWithValueToDoc( "support_url", $localeInfo->support_url, $node );

		$this->_updateNodeWithNameWithValueToDoc( "title", $localeInfo->title, $node );
		$this->_addNodeWithTitleFromFilenameToDoc( "description", $localeInfo->description, $node );
		if ( $this->_previousVersionExists ) {
			$this->_addNodeWithTitleFromFilenameToDoc( "version_whats_new", $localeInfo->version_whats_new, $node );	
		}

		if ( isset( $localeInfo->keywords ) ) {
			unset( $node->keywords );
			if ( !isset( $node->keywords ) ) {
				$node->addChild( "keywords" );
			}
			foreach ( $localeInfo->keywords as $keyword ) {
				$this->_updateNodeWithNameWithValueToDoc( "keyword", $keyword, $node->keywords, true );
			}
		}

		$this->_addScreenshots( );

	}



	private function _addScreenshots() {
		$locale = $this->_currentLocale;
		$localeInfo = $this->_configuration->locales->$locale;
		$node = $this->_currentNode;

		// If we're not updating screenshots, we can stop here. 
		if ( !isset( $localeInfo->screenshots ) ) {
			return; 
		}
		$screenshotLocale = $this->_currentLocale;

		unset( $node->software_screenshots );

		$node->addChild("software_screenshots");

		$path = $this->_configurePathForFile($localeInfo->screenshots);

		if ( file_exists( $path ) ) {
			$files = scandir( $path );

			// Go through each file
			foreach ( $files as $screenshot ) {

				// Lowercase everything
				$screenshot = strtolower( $screenshot );

				// Get the path
				$screenshotPath = $path . "/" . $screenshot;

				// Check that the conventions are correct
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

					$positionString = str_replace("$type-screenshot-", "", $screenshot );
					$position = substr($positionString, 0, 1);
					$isLocal = str_replace(".png", "", $positionString);

					// If there's localisation info in this screenshot
					if ( $isLocal != $position ) {
						$lowerScreenshotLocale = strtolower($screenshotLocale);
						if ( strpos( $screenshot, strtolower($screenshotLocale) ) === false ) {
							print("Screenshot locales don't match: $screenshotLocale $screenshot\n");
							continue;
						}
					}

					if ( !$position || $position > 5 ) continue;
					if ( isset( $localeInfo->localise_screenshots ) ) {
						if ( strpos( $screenshot, strtolower($screenshotLocale) ) === false ) {
							continue;
						}
					} 
					$newScreenshot = $screenshot;

					$screen = $node->software_screenshots->addChild("software_screenshot");
					$screen->addAttribute("display_target", $target );
					$screen->addAttribute("position", $position );
					$this->_addScreenshotWithPathToNode( $screenshotPath, $screen);

				}
			}
		} else {
			echo "ERROR: Screenshot directory not found (locale: $screenshotLocale): `$path`\n";
			echo "ERROR: Screenshots not updated.\n";
		}		
	}

	private function _addScreenshotWithPathToNode( $screenshotPath, SimpleXMLElement $node ) {
		$info = pathinfo($screenshotPath);
		$newScreenshot = $info['basename'];

		$node->addChild('file_name', $newScreenshot );
		$node->addChild('size', filesize( $screenshotPath ) );
		$fileNode = $node->addChild('checksum' );
		$fileNode->addAttribute("type", "md5");
		$node->checksum = md5_file( $screenshotPath );
		copy($screenshotPath, 
						$this->_configurePathForFile($this->_configuration->itmsFile . "/" . $newScreenshot) );

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
