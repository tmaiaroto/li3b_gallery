<?php
/**
 * Thumbnail generation class.
 *
 */
namespace li3b_gallery\extensions\util;

use li3b_gallery\models\Asset;
use lithium\analysis\Logger;
use lithium\action\Response;
use \MongoId;
use \MongoDate;
use \MongoGridFSFile;
use \SplFileInfo;
use \stdClass;

class Thumbnail extends \lithium\core\StaticObject {

	/**
	 * Holds the options passed to create() for other methods to use.
	 *
	 * @var array
	 */
	static $_options = array();

	/**
	 * Holds all sorts of information about the image that's being used
	 * to generate the thumbnail.
	 *
	 * @var array
	 */
	static $_image;

	/**
	 * Holds a copy of the "source" image.
	 * This could be a path, a MongoId, etc.
	 *
	 * @var mixed
	 */
	static $_source;

	/**
	 * Holds information about the destination for the
	 * generated thumbnail image. This is where it may
	 * be saved to on disk or in MongoDB.
	 *
	 * @var mixed
	 */
	static $_destination;

	/**
	 * Creates a thumbnail.
	 *
	 * Options are as follows:
	 * `size` - The size in x, y
	 * `quality` - The image quality if applicable (jpg, png have quality settings)
	 * `crop` - If it's ok to crop when necessary
	 * `letterbox` - If not cropping, this is a hex color value that fills the background
	 * `forceLetterBoxColor` - Forces the letterbox color for transparent images (ie. if an image has a giant hole in the center, you'd see the letterbox color behind it this way)
	 * `sharpen` - Whether or sharpen or not (makes smaller images look better)
	 * `cache` - Ignore cache if set to false, otherwise new images won't be created it not necessary
	 *
	 * @param type $source
	 * @param type $options
	 * @return array The path and mime-type.
	 */
	public static function create($source=null, $destination=null, array $options = array()) {
		$defaults = array(
			'size' => array(75, 75),
			'quality' => 85,
			'crop' => false,
			'letterbox' => null,
			'forceLetterboxColor' => false,
			'sharpen' => true,
			'cache' => true
		);
		$options += $defaults;

		$options['letterbox'] = is_string($options['letterbox']) ? static::_html2rgb($options['letterbox']):$options['letterbox'];

		// If realpath() isn't used to pass the image, and it's just a part of the route to the image.
		if(is_string($source) && substr($source, 0, 8) == '/li3b_gallery') {
			$source = LITHIUM_APP_PATH . '/libraries/li3b_gallery/webroot' . substr($source, 8);
		}

		// Round the thumbnail quality in case a decimal was provided.
		$options['quality'] = ceil($options['quality']);
		// ...Or if a value was entered beyond the extremes.
		if($options['quality'] > 100) {
			$options['quality'] = 100;
		}
		if($options['quality'] < 0) {
			$options['quality'] = 0;
		}

		// Ensure size is x, y. It doesn't need to be passed as a keyed array.
		// But we want to make sure it is a keyed array to make things easier.
		list($width, $height) = $options['size'];
		$options['size'] = array(
			'x' => $width,
			'y' => $height
		);
		// Set this orginally request size, because 'size' will change based
		// on various calculations.
		$options['originalSize'] = $options['size'];

		static::$_image = new stdClass();

		// Get the extension from the source, if possible.
		// NOTE: If the image is coming from GridFS, this will be set in _createFromAssetId()
		if(is_string($source) && preg_match('/([^\.]+$)/i', $source, $matches)) {
			static::$_image->ext = strtolower($matches[0]);
		}

		static::$_options = $options;
		static::$_source = $source;
		static::$_destination = $destination;

		// Need to detect the source.
		// It could be a local file on disk, it could be a remote image from another site, it could be an image stored in S3, Mongo GridFS, or ...
		switch(true) {
			default:
			case (empty($source)):
				// First off, source needs to be populated.
				return false;
			break;
			case (is_string($source) && file_exists($source)):
				$sourceInfo = new SplFileInfo(static::$_source);
				static::$_image->ext = $sourceInfo->getExtension();
				static::$_image->filename = $sourceInfo->getFilename();
				static::$_image->lastModified = $sourceInfo->getMTime();
				static::$_image->sourceId = hash('md5', $source);

				static::_setDestination();
				static::_setMimeType();

				// TODO: Take into consideration other destinations.
				// Also in the _setDestination() method.
				// So images can be created and stored to MongoDB, etc.
				// for caching as well. Not just the current server's local disk.

				// Return the file if cache was set to true and it has been created before (and is current).
				if($options['cache'] && $cacheImage = static::_getCache()) {
					return array('path' => $cacheImage, 'mimeType' => static::$_image->mimeType);
				}

				static::_createFromDisk();
			break;
			case ($source instanceof MongoGridFSFile):
				static::$_image->ext = $source->file['fileExt'];
				static::$_image->filename = $source->file['filename'];
				static::$_image->lastModified = strtotime($source->file['uploadDate']);
				static::$_image->sourceId = hash('md5', (string)$source->file['_id']);
				static::$_source = $source;

				static::_setDestination();
				static::_setMimeType();

				if($options['cache'] && $cacheImage = static::_getCache()) {
					return array('path' => $cacheImage, 'mimeType' => static::$_image->mimeType);
				}

				static::_createFromBytes();
			break;
			case ($source instanceof MongoId):
				$asset = Asset::find('first', array('conditions' => array('_id' => $source)));
				if(empty($asset)) {
					return false;
				}
				static::$_image->ext = $asset->fileExt;
				static::$_image->filename = $asset->filename;
				static::$_image->lastModified = $asset->uploadDate;
				static::$_image->sourceId = hash('md5', (string)$source->_id);
				static::$_source = $asset->file;

				static::_setDestination();
				static::_setMimeType();

				// Return the file if cache was set to true and it has been created before (and is current).
				if($options['cache'] && $cacheImage = static::_getCache()) {
					return array('path' => $cacheImage, 'mimeType' => static::$_image->mimeType);
				}

				static::_createFromBytes();
			break;
			case ((substr($source, 0, 7) == 'http://') || (substr($source, 0, 8) == 'https://')):
				// Note: This does make two HTTP requests.
				// TODO: Re-arrange code to make it one HTTP request...Low priority right now.
				// The headers should give us all we need to know about the image.
				$headers = get_headers($source, 1);

				$contentType = isset($headers['Content-Type']) ? $headers['Content-Type']:null;
				switch($contentType) {
					// Guess by default...
					default:
					case 'image/jpeg':
						static::$_image->ext = 'jpg';
					break;
					case 'image/png':
						static::$_image->ext = 'png';
					break;
					case 'image/gif':
						static::$_image->ext = 'gif';
					break;
				}

				// This will be the destination filename. Also used to check cache.
				// So let's simply hash the URL to generate the name.
				// Don't forget to re-add the extension.
				$sourceHash = hash('md5', $source);
				static::$_image->filename = $sourceHash . '.' . static::$_image->ext;
				static::$_image->lastModified = (isset($headers['Last-Modified'])) ? strtotime($headers['Last-Modified']):time();
				static::$_image->sourceId = $sourceHash;
				static::_setDestination();
				static::_setMimeType();

				// Return the file if cache was set to true and it has been created before (and is current).
				if($options['cache'] && $cacheImage = static::_getCache()) {
					return array('path' => $cacheImage, 'mimeType' => static::$_image->mimeType);
				}

				static::_createFromUrl();
			break;
		}

		switch(true) {
			// Returns the image directly to the browser.
			// Probably want to use this method via some sort of helper.
			default:
			case (empty(static::$_destination)):
				static::$_destination = null;

				switch(static::$_image->ext) {
					case 'png':
						if(static::$_options['quality'] != 0) {
							static::$_options['quality'] = (static::$_options['quality'] - 100) / 11.111111;
							static::$_options['quality'] = round(abs(static::$_options['quality']));
						}
						header('Content-Type:', 'image/png');
						$response = new Response(array(
							'headers' => array('Content-Type' => 'image/png'),
							'body' => imagepng(static::$_image->resource, null, static::$_options['quality'])
						));
						imagedestroy(static::$_image->resource);
					break;
					case 'gif':
						header('Content-Type:', 'image/gif');
						$response = new Response(array(
							'headers' => array('Content-Type' => 'image/gif'),
							'body' => imagegif(static::$_image->resource, null) // no quality setting
						));
						imagedestroy(static::$_image->resource);
					break;
					case 'jpg':
					case 'jpeg':
						// weird... header() must be called if the dimensions passed are greater than the image itself
						// otherwise, it will just display the image data...
						header('Content-Type:', 'image/jpeg');
						$response = new Response(array(
							'headers' => array('Content-Type' => 'image/jpeg'),
							'body' => imagejpeg(static::$_image->resource, null, static::$_options['quality'])
						));
						imagedestroy(static::$_image->resource);
					break;
					default:
						return false;
					break;
				}
				return $response;
			break;
			// If $destination was a path on disk...
			case (is_string(static::$_destination) && file_exists(static::$_destination) && is_writable(static::$_destination)):
				// Append the file name to the destination FIRST. It's used by _setCache().
				static::$_destination = static::$_destination . '/' . static::$_image->filename;

				static::_setCache();
				return array('path' => static::$_destination, 'mimeType' => static::$_image->mimeType);
			break;
			// If $destination was MongoDB...
			case (static::$_destination == 'mongo'):
				//var_dump('going to save thumbnail cache in mongodb now');
				//exit();
				static::_setCache();
				// static::$_destination was set by _setCache() as well if the destination was in MongoDB.
				return array('path' => static::$_destination, 'mimeType' => static::$_image->mimeType);
			break;
		}
	}

	/**
	 * Removes the cached images.
	 * This is useful for cleaning up a system.
	 * Images can be removed from all or specific cache sources either
	 * individually or completely.
	 *
	 * By default, if no options are passed, everything will be removed.
	 *
	 * Note: Cached images on disk can be stored in a variety of places and
	 * this class does not choose that location nor has a default. So to remove
	 * cached images on disk, a path must ALWAYS be provided in `source` and
	 * note that only directories under the LITHIUM_APP_PATH are considered.
	 * LITHIUM_APP_PATH is always prefixed on to the source. If images were
	 * stored elsewhere (a NAS or something) they will need to be manually
	 * removed OR a symlink should be setup within the application path that
	 * points to the NAS.
	 *
	 * WARNING: The `source` option provides the directory. BE CAREFUL.
	 * Entire directories can be removed (provided there is filesystem permission).
	 *
	 * The `reference` option should be a MongoId or a file name.
	 * If it's a file name, then that file will be removed under the `source`
	 * directory. If not provided, the entire `source` directory will be removed.
	 *
	 * So to remove all 50x50 thumbnail images, for example, there would be no
	 * `reference` passed. It would just be the `source` which pointed to the
	 * directory on disk that held the 50x50 images. NOT taking into consideration
	 * the app path. So for example: "/webroot/img/_thumbnails/50x50"
	 *
	 * The `olderThan` option is a strtotime() compatible string.
	 *
	 * @param array $options
	 * @return boolean
	 */
	public static function clearCache(array $options = array()) {
		$defaults = array(
			'reference' => false,
			'source' => 'mongo',
			'olderThan' => false
		);
		$options += $defaults;

		$olderThan = is_string($options['olderThan']) ? new MongoDate(strtotime($options['olderThan'])):false;
		$reference = ($options['reference'] instanceof MongoId) ? hash('md5', (string)$options['reference']):$options['reference'];
		switch(true) {
			default:
			case ($options['source'] == 'mongo' || $options['source'] == 'grid.fs' || $options['source'] == 'mongodb'):
				$source = 'mongo';
			break;
			case(file_exists($options['source'])):
				$source = (substr($options['source'], 0, 1) == '/') ? $options['source']:'/' . $options['source'];
				$source = LITHIUM_APP_PATH . $source;
			break;
		}

		// Note: There is currently no support for Amazon S3 here...Not that there is anywhere else yet either.
		// But if used, it needs to be worked in here below.

		if($source == 'mongo') {
			// Remove all image cache files from MongoDB.
			$conditions = array(
				'_thumbnail' => true
			);

			if($olderThan) {
				$conditions['$lt'] = array('uploadDate' => $olderThan);
			}
			// In this case, the hash of the parent Item MongoId ($_image->sourceId).
			if($reference) {
				$conditions['ref'] = $options['reference'];
			}

			return Asset::remove($conditions);
		} else {
			if($reference) {
				$source = (substr($source, -1) == '/') ? $source . $reference:$source . '/' . $reference;
				// Ensure this really is a file...and not a directory.
				if(!is_dir($source) && file_exists($source)) {
					return @unlink($source);
				}
			} else {
				// Removes entire directory! Be CAREFUL.
				// Only paths within the application should be able to be removed, but that doesn't mean mistakes can't be made.
				if(file_exists($source) && is_writable($source)) {
					return @unlink($source);
				}
			}
		}

		return false;
	}

	/**
	 * Creates the image from a MongoDB GridFS file.
	 *
	 */
	private static function _createFromBytes() {
		$tmpfname = tempnam(sys_get_temp_dir(), "img");
		$handle = fopen($tmpfname, "w");
		fwrite($handle, static::$_source->getBytes());
		fclose($handle);
		static::$_source = $tmpfname;

		list($width, $height) = getimagesize(static::$_source);
		static::$_image->width = $width;
		static::$_image->height = $height;

		static::_generateImage();

		unlink($tmpfname);
	}

	/**
	 * Creates the image from a file on disk.
	 */
	private static function _createFromDisk() {
		// Get source image dimensions
		list($width, $height) = getimagesize(static::$_source);
		static::$_image->width = $width;
		static::$_image->height = $height;

		$newImage = static::_generateImage();
	}

	/**
	 * Creates the image from a remote image on another site.
	 */
	private static function _createFromUrl() {
		$tmpfname = tempnam(sys_get_temp_dir(), "img");
		$handle = fopen($tmpfname, "w");
		fwrite($handle, file_get_contents(static::$_source));
		fclose($handle);
		static::$_source = $tmpfname;

		list($width, $height) = getimagesize(static::$_source);
		static::$_image->width = $width;
		static::$_image->height = $height;

		static::_generateImage();

		unlink($tmpfname);
	}

	/**
	 * Process the image
	 *
	 * @param $options Array[required] The options to create and transform the image
	 * @return GD image object
	 */
	private static function _generateImage() {
		// $x and $y here are the image source offsets
		$x = NULL;
		$y = NULL;
		$dx = $dy = 0;

		// The crop option may have been passed as true, but check to see if a crop is even necessary.
		if((static::$_options['size']['x'] > static::$_image->width) && (static::$_options['size']['y'] > static::$_image->height)) {
			static::$_options['crop'] = false;
		}

		// don't allow new width or height to be greater than the original
		if(static::$_options['size']['x'] > static::$_image->width) {
			static::$_options['size']['x'] = static::$_image->width;
		}

		if(static::$_options['size']['y'] > static::$_image->height) {
			static::$_options['size']['y'] = static::$_image->height;
		}

		// generate new w/h if not provided (cool, idiot proofing)
		if(static::$_options['size']['x'] && !static::$_options['size']['y']) {
			static::$_options['size']['y'] = static::$_image->height * ( static::$_options['size']['x'] / static::$_image->width );
		} elseif(static::$_options['size']['y'] && !static::$_options['size']['x']) {
			static::$_options['size']['x'] = static::$_image->width * ( static::$_options['size']['y'] / static::$_image->height );
		} elseif(!static::$_options['size']['x'] && !static::$_options['size']['y']) {
			static::$_options['size']['x'] = static::$_image->width;
			static::$_options['size']['y'] = static::$_image->height;
		}

		// set some default values for other variables we set differently based on options like letterboxing, etc.
		$newWidth = static::$_options['size']['x'];
		$newHeight = static::$_options['size']['y'];
		$xCenter = ceil($newWidth/2); //horizontal middle // TODO: possibly add options to change where the crop is from
		$yCenter = ceil($newHeight/2); //vertical middle

		// If the thumbnail is going to be square and we're cropping (otherwise it won't just be square, but it'll be cropped too if the source isn't already a square image)
		if(static::$_options['size']['x'] == static::$_options['size']['y'] && static::$_options['crop'] === true) {
			if(static::$_image->width > static::$_image->height) {
				$x = ceil((static::$_image->width - static::$_image->height) / 2 );
				static::$_image->width = static::$_image->height;
			} elseif(static::$_image->height > static::$_image->width) {
				$y = ceil((static::$_image->height - static::$_image->width) / 2);
				static::$_image->height = static::$_image->width;
			}
		// else if the thumbnail is rectangular, don't stretch it
		} else {
			// Just in case there was something wrong with the image and we didn't have a height we can divide with.
			if(static::$_image->height <= 0) {
				return false;
			}
			// if we aren't cropping then keep aspect ratio and contain image within the specified size
			if(static::$_options['crop'] === false) {
				$ratioOrig = static::$_image->width/static::$_image->height;
				if (static::$_options['size']['x']/static::$_options['size']['y'] > $ratioOrig) {
					static::$_options['size']['x'] = ceil(static::$_options['size']['y']*$ratioOrig);
				} else {
					static::$_options['size']['y'] = ceil(static::$_options['size']['x']/$ratioOrig);
				}
			}
			// if we are cropping...
			if(static::$_options['crop'] === true) {
				$ratioOrig = static::$_image->width/static::$_image->height;
				if (static::$_options['size']['x']/static::$_options['size']['y'] > $ratioOrig) {
					$newHeight = ceil(static::$_options['size']['x']/$ratioOrig);
					$newWidth = static::$_options['size']['x'];
				} else {
					$newWidth = ceil(static::$_options['size']['y']*$ratioOrig);
					$newHeight = static::$_options['size']['y'];
				}
				$xCenter = ceil($newWidth/2); //horizontal middle // TODO: possibly add options to change where the crop is from
				$yCenter = ceil($newHeight/2); //vertical middle
			}
		}

		// CREATE THE NEW THUMBNAIL IMAGE, by taking the source and applying transformations to it.
		switch(static::$_image->ext) {
			case 'jpg':
			case 'jpeg':
				$im = imagecreatefromjpeg(static::$_source);
			break;
			case 'png':
				$im = imagecreatefrompng(static::$_source);
			break;
			case 'gif':
				$im = imagecreatefromgif(static::$_source);
			break;
			default:
			case null:
				return false;
			break;
		}

		if(!empty(static::$_options['letterbox'])) {
			// if letterbox, use the originally passed dimensions (keeping the final image size to whatever was requested, fitting the other image inside this box)
			$newImage = ImageCreatetruecolor(static::$_options['originalSize']['x'], static::$_options['originalSize']['y']);
			// We want to now set the destination coordinates so we center the image (take overal "box" size and divide in half and subtract by final resized image size divided in half)
			$dx = ceil((static::$_options['originalSize']['x'] / 2) - (static::$_options['size']['x'] / 2));
			$dy = ceil((static::$_options['originalSize']['y'] / 2) - (static::$_options['size']['y'] / 2));
		} else {
			// otherwise, use adjusted resize dimensions
			$newImage = ImageCreatetruecolor(static::$_options['size']['x'], static::$_options['size']['y']);
		}
		// If we're cropping, we need to use a different calculated width and height
		$croppedImage = false;
		if(static::$_options['crop'] === true) {
			$croppedImage = imagecreatetruecolor(round($newWidth), round($newHeight));
		}

		// If PNG or GIF, handle the transparency.
		if((static::$_image->ext == 'png') || (static::$_image->ext == 'gif')) {
			$trnprtIndx = imagecolortransparent($im);
			// If we have a specific transparent color that was saved with the image
			if ($trnprtIndx >= 0) {
				// Get the original image's transparent color's RGB values
				$trnprtColor = imagecolorsforindex($im, $trnprtIndx);
				// Allocate the same color in the new image resource
				$trnprtIndx = imagecolorallocate($newImage, $trnprtColor['red'], $trnprtColor['green'], $trnprtColor['blue']);
				// Completely fill the background of the new image with allocated color.
				imagefill($newImage, 0, 0, $trnprtIndx);
				// Set the background color for new image to transparent
				imagecolortransparent($newImage, $trnprtIndx);
				if($croppedImage) { imagefill($croppedImage, 0, 0, $trnprtIndx); imagecolortransparent($croppedImage, $trnprtIndx); } // do the same for the image if cropped
				} elseif(static::$_image->ext == 'png') {
					// ...a png may, instead, have an alpha channel that determines its translucency

					// Fill the (currently empty) new cropped image with a transparent background
					if($croppedImage) {
						$transparentIndex = imagecolortransparent($croppedImage); // allocate
						//imagepalettecopy($im, $croppedImage); // Don't need to copy the pallette
						imagefill($croppedImage, 0, 0, $transparentIndex);
						//imagecolortransparent($croppedImage, $transparentIndex); // we need this and the next line even?? for all the trouble i went through, i'm leaving it in case it needs to be turned back on.		
						//imagetruecolortopalette($croppedImage, true, 256);
					}

					// Fill the new image with a transparent background
					imagealphablending($newImage, false);
					// Create/allocate a new transparent color for image
					$trnprtIndx = imagecolorallocatealpha($newImage, 0, 0, 0, 127); // $trnprtIndx = imagecolortransparent($newImage, imagecolorallocatealpha($newImage, 0, 0, 0, 127)); // seems to be no difference, but why call an extra function?
					imagefill($newImage, 0, 0, $trnprtIndx); // Completely fill the background of the new image with allocated color.
					imagesavealpha($newImage, true);  // Restore transparency blending
				}
		}

		// PNG AND GIF can have transparent letterbox and that area needs to be filled too (it already is though if it's transparent)
		if(!empty(static::$_options['letterbox'])) {
			$backgroundColor = imagecolorallocate($newImage, 255, 255, 255); // default white
			if((is_array(static::$_options['letterbox'])) && (count(static::$_options['letterbox']) == 3)) {
				$backgroundColor = imagecolorallocate($newImage, static::$_options['letterbox'][0], static::$_options['letterbox'][1], static::$_options['letterbox'][2]);
			}

			// Transparent images like png and gif will show the letterbox color in their transparent areas so it will look weird
			if((static::$_image->ext == 'gif') || (static::$_image->ext == 'png')) {
				// But we will give the user a choice, forcing letterbox will effectively "flood" the background with that color.
				if(static::$_options['forceLetterboxColor'] === true) {
					imagealphablending($newImage, true);
					if($croppedImage) { imagefill($croppedImage, 0, 0, $backgroundColor); }
				} else {
					// If the user doesn't force letterboxing color on gif and png, make it transaprent ($trnprtIndx from above)
					$backgroundColor = $trnprtIndx;
				}
			}
			imagefill($newImage, 0, 0, $backgroundColor);
		}

		// If cropping, we have to set some coordinates
		if(static::$_options['crop'] === true) {
			imagecopyresampled($croppedImage, $im, 0, 0, 0, 0, $newWidth, $newHeight, static::$_image->width, static::$_image->height);
			// if letterbox we may have to set some coordinates as well depending on the image dimensions ($dx, $dy) unless its letterbox style
			if(empty(static::$_options['letterbox'])) {
				imagecopyresampled($newImage, $croppedImage, 0, 0, ($xCenter-(static::$_options['size']['x']/2)), ($yCenter-(static::$_options['size']['y']/2)), static::$_options['size']['x'], static::$_options['size']['y'], static::$_options['size']['x'], static::$_options['size']['y']);
			} else {
				imagecopyresampled($newImage, $croppedImage,$dx,$dy, ($xCenter-(static::$_options['size']['x']/2)), ($yCenter-(static::$_options['size']['y']/2)), static::$_options['size']['x'], static::$_options['size']['y'], static::$_options['size']['x'], static::$_options['size']['y']);
			}
		} else {
			imagecopyresampled($newImage,$im,$dx,$dy,$x,$y,static::$_options['size']['x'],static::$_options['size']['y'],static::$_image->width,static::$_image->height);
		}

		// SHARPEN (optional) -- can't sharpen transparent/translucent PNG
		if((static::$_options['sharpen'] === true) && (static::$_image->ext != 'png') && (static::$_image->ext != 'gif')) {
				$sharpness = static::_findSharp(static::$_image->width, static::$_options['size']['x']);
				$sharpenMatrix = array(
					array(-1, -2, -1),
					array(-2, $sharpness + 12, -2),
					array(-1, -2, -1)
				);
				$divisor = $sharpness;
				$offset = 0;
				imageconvolution($newImage, $sharpenMatrix, $divisor, $offset);
		}

		static::$_image->resource = $newImage;
		return true;
	}

	/**
	* Computes for sharpening the image.
	*
	* function from Ryan Rud (http://adryrun.com)
	*/
	private static function _findSharp($orig, $final) {
		$final = $final * (750.0 / $orig);
		$a = 52;
		$b = -0.27810650887573124;
		$c = .00047337278106508946;
		$result = $a + $b * $final + $c * $final * $final;
		return max(round($result), 0);
	}

	/**
	 * Converts web hex value into rgb array.
	 *
	 * @param $color String[required] The web hex string (ex. #0000 or 0000)
	 * @return array The rgb array
	 */
	private static function _html2rgb($color) {
		if ($color[0] == '#')
			$color = substr($color, 1);
		if (strlen($color) == 6)
			list($r, $g, $b) = array($color[0].$color[1], $color[2].$color[3], $color[4].$color[5]);
		elseif (strlen($color) == 3)
			list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
		else
			return false;
		$r = hexdec($r); $g = hexdec($g); $b = hexdec($b);
		return array($r, $g, $b);
	}

	/**
	 * Pass a full path like /var/www/htdocs/site/webroot/files
	 * Don't include trailing slash.
	 *
	 * @param $path String[optional]
	 * @return String Path.
	 */
	private static function _createPath($path = null) {
		$directories = explode('/', $path);
		// If on a Windows platform, define root accordingly (assumes <drive letter>: syntax)
		if (substr($directories[0], -1) == ':') {
			$root = $directories[0];
			array_shift($directories);
		} else {
			// Initialize root to empty string on *nix platforms
			$root = '';
			// looks to see if a slash was included in the path to begin with and if so it removes it
			if ($directories[0] == '') {
				array_shift($directories);
			}
		}
		foreach ($directories as $directory) {
			if (!file_exists($root.'/'.$directory)) {
				mkdir($root.'/'.$directory);
			}
			$root = $root.'/'.$directory;
		}
		// put a trailing slash on
		$root = $root.'/';
		return $root;
	}

	/**
	 * If the image is being generated and saved to disk, this will create
	 * the path and return the full path to disk for the $_destination to use.
	 *
	 * If the cache is in MongoDB, then this will not adjust $_destination.
	 *
	 * TODO: In the future, we also need to account for Amazon S3, etc.
	 *
	 * @return
	 */
	private static function _setDestination() {
		if(empty(static::$_destination)) {
			return null;
		}

		switch(true) {
			// MongoDB cache.
			case (static::$_destination == 'mongo'):
			case (static::$_destination == 'mongodb'):
			case (static::$_destination == 'grid.fs'):
				// They all mean "mongo" - standardize it.
				static::$_destination = 'mongo';
				// Do nothing here.
			break;
			// Save to disk by default.
			default:
				// Note: This is important. The size (x, y) is set as what was passed to this method (100, 100 by default).
				// After this point, when the thumbnail is being generated, the size values in static::$_options may change.
				// The reason is because if the thumbnail requires a resize that doesn't fit the same aspect ratio and there
				// is no crop...It may turn an image into 100x90 instead of 100x100. However, we still want to save images
				// under a path related to the requested size. So even though the image is 100x90, if it's stored on disk,
				// it will be stored under a "100x100" directory if array(100,100) was passed for the size. This is so that
				// the image can be cached and used in the future instead of re-generating the image each time.
				static::$_destination .= (substr(static::$_destination, -1) == '/') ? static::$_options['size']['x'] . 'x' . static::$_options['size']['y']:'/' . static::$_options['size']['x'] . 'x' . static::$_options['size']['y'];
				static::_createPath(static::$_destination);
			break;
		}
	}

	/**
	 * Sets the mime-type. Useful for the Response class.
	 * This will get returned from the Thumbnail::create() method.
	 *
	 */
	private static function _setMimeType() {
		// Set the mime type in case we are directly returning the new thumbnail image.
		switch(static::$_image->ext) {
			case 'jpg':
			case 'jpeg':
			default:
				static::$_image->mimeType = 'image/jpeg';
			break;
			case 'png':
				static::$_image->mimeType = 'image/png';
			break;
			case 'gif':
				static::$_image->mimeType = 'image/gif';
			break;
		}
	}

	/**
	 * Checks if a cached version of the image already exists and is usable.
	 * By usable, we mean if it's not stale. If the source image has updated
	 * since the cache image was created, it is not usable.
	 *
	 * If there is a usable cache image, it will be returned, otherwise
	 * false will be returned and the new image will be generated.
	 *
	 * This method will also clean up stale cache files/documents.
	 *
	 * @return mixed
	 */
	private static function _getCache() {
		if(empty(static::$_destination)) {
			return false;
		}
		// Return the route path (which renders an image from grid.fs) if cached exists in MongoDB.
		if(static::$_destination == 'mongo') {
			$thumbSizeAsString = static::$_options['size']['x'] . 'x' . static::$_options['size']['y'];
			$image = Asset::find('first', array('conditions' => array('filename' => sys_get_temp_dir() . '/' . static::$_image->sourceId . '_' . $thumbSizeAsString)));
			if($image && $image->uploadDate->sec > static::$_image->lastModified) {
				return '/li3b_gallery/images/' . $image->_id . '.' . $image->fileExt;
			}
		}

		// Return the file path if cached exists on disk.
		if(file_exists(static::$_destination . '/' . static::$_image->filename)) {
			// And the generated thumbnail is newer than the source image...
			$existingFile = new SplFileInfo(static::$_destination);
			if($existingFile->getMTime() > static::$_image->lastModified) {
				return static::$_destination . '/' . static::$_image->filename;
			}
		}

		return false;
	}

	/**
	 * Sets the cache by saving the image.
	 * It may save to disk or in MongoDB or elsewhere.
	 *
	 * @return boolean If the image was saved somewhere or not
	 */
	private static function _setCache() {
		// If saving to MnogoDB set the destination to null.
		if(static::$_destination == 'mongo') {
			static::$_destination = null;
		}

		// If the destination is null then $image will be the stream.
		// If it was a path on disk, the image will be saved.
		$imageStream = false;
		// Ensure we have a resource.
		if(!isset(static::$_image->resource)) {
			return false;
		}
		switch(static::$_image->ext) {
			case 'png':
				if(static::$_options['quality'] != 0) {
					static::$_options['quality'] = (static::$_options['quality'] - 100) / 11.111111;
					static::$_options['quality'] = round(abs(static::$_options['quality']));
				}
				ob_start();
				imagepng(static::$_image->resource, static::$_destination, static::$_options['quality']);
				$imageStream = ob_get_contents();
				ob_end_clean();
				imagedestroy(static::$_image->resource);
			break;
			case 'gif':
				ob_start();
				imagegif(static::$_image->resource, static::$_destination); // no quality setting
				$imageStream = ob_get_contents();
				ob_end_clean();
				imagedestroy(static::$_image->resource);
			break;
			case 'jpg':
			case 'jpeg':
				ob_start();
				imagejpeg(static::$_image->resource, static::$_destination, static::$_options['quality']);
				$imageStream = ob_get_contents();
				ob_end_clean();
				imagedestroy(static::$_image->resource);
			break;
			default:
				return false;
			break;
		}

		//
		if($imageStream) {
			// Note: Again, remember that static::$_image->width can change if needed.
			// The $_options holds the original size request. Use those.
			$thumbSizeAsString = static::$_options['size']['x'] . 'x' . static::$_options['size']['y'];

			// First, remove any other images to keep things tidy.
			// Remember, if this method is even called, it means the cache does
			// not exist or it's stale.
			/*
			Asset::remove(array(
				'_thumbnail' => true,
				'filename' => sys_get_temp_dir() . '/' . static::$_image->sourceId . '_' . $thumbSizeAsString
			));
			*/
			// Use this instead for now...I'm not sure the above removes from chunks.
			$db = Asset::connection();
			$db->connection->getGridFs()->remove(array(
				'_thumbnail' => true,
				'filename' => sys_get_temp_dir() . '/' . static::$_image->sourceId . '_' . $thumbSizeAsString
			));

			// MongoDB class wants to save an image off something on disk.
			// The image stream COULD be written directly, it does work...
			// But an error message comes up because it expects a path instead.
			// So, for now, write to a temp file. Also, with this, an uploadDate
			// field is automatically set. Used for cache expiration.
			// Note: The temporary filename should include the size string as well.
			// This is because GridFs acts like a filesystem and won't overwrite.
			$tmpfname = sys_get_temp_dir() . '/' . static::$_image->sourceId . '_' . $thumbSizeAsString;
			$handle = fopen($tmpfname, 'w');
			fwrite($handle, $imageStream);
			fclose($handle);

			/*
			$gridFile = Asset::create(array(
				'file' => $tmpfname,
				'fileExt' => static::$_image->ext,
				// the only thing we could do with ref now is find all thumbnails generated for an image...
				// which may come in handy... so we'll leave it even though we aren't using it yet.
				'ref' => static::$_image->sourceId,
				'_thumbnail' => true
			));
			$saved = $gridFile->save();
			*/

			// NOTE: Use the new store() method... It seems to be more reliable.
			$gridFileId = Asset::store(
				$tmpfname,
				array(
					'fileExt' => static::$_image->ext,
					'ref' => static::$_image->sourceId,
					'_thumbnail' => true
				)
			);

			// Remove the temp file after we no longer need it.
			unlink($tmpfname);

			// Set the destination so we we can use the newly created cache image.
			// NOTE: If routes.php changes, this will need to as well.
			// static::$_destination = '/li3b_gallery/images/' . (string)$gridFile->_id . '.' . static::$_image->ext;
			static::$_destination = '/li3b_gallery/images/' . (string)$gridFileId . '.' . static::$_image->ext;

			if($gridFileId) {
				return true;
			}
			//return $saved;
		}

		// If it hasn't returned false yet, it should have succeeded in saving to disk.
		return true;
	}

}
?>