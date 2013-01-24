<?php

//TODO
/// associate to object id then... in mongodb they are unique (completely unique)
// also need to check things like php.ini settings for max file sizes, etc.
// figure out file mimetype and size restrictions on uploads that are "user" defined in the code (or a settings file/db)

namespace li3b_gallery\models;

use lithium\util\Validator;
use lithium\util\Inflector as Inflector;
use \MongoDate;

class Asset extends \lithium\data\Model {

	// Use the gridfs in MongoDB
	protected $_meta = array(
		'source' => 'fs.files',
		'connection' => 'li3b_mongodb'
	);

	// I get appended to with the plugin's Asset model (a good way to add extra meta data).
	public static $fields = array(
		// 'url' => array('label' => 'URL'),  ?? pretty urls for download?
		// Easy value to isolate all thumbnail cache images (so they can be removed - all at once even)
		'_thumbnail' => array('type' => 'boolean'),
		// not technically required, but is common.
		'filename' => array('type' => 'string'),
		// file extension is not needed by Mongo, we use it for working with resizing/generating images.
		'fileExt' => array('type' => 'string'),
		// the mime-type
		'contentType' => array('type' => 'string'),
		// This represents the 'type' of asset, or what it's associated to:
		'ref' => array('type' => 'string'),
		'file' => array('label' => 'Profile Image', 'type' => 'file')
	);

	public static $validate = array(
	);

	public static function __init() {
		self::$fields += static::$fields;
		self::$validate += static::$validate;

		// Future compatibility.
		if(method_exists('\lithium\data\Model', '__init')) {
			parent::__init();
		}
	}

	/**
	 * Stores in GridFS.
	 * Call this insetad of save()
	 *
	 * @return mixed False on fail, ObjectId on success
	 */
	public static function store($filename=false, $metadata=array()) {
		if(!$filename) {
			return false;
		}

		$ext = isset($metadata['fileExt']) ? strtolower($metadata['fileExt']):null;
		switch($ext) {
			default:
				$mimeType = 'text/plain';
			break;
			case 'jpg':
			case 'jpeg':
				$mimeType = 'image/jpeg';
			break;
			case 'png':
				$mimeType = 'image/png';
			break;
			case 'gif':
				$mimeType = 'image/gif';
			break;
		}
		$metadata['contentType'] = $mimeType;

		if(file_exists($filename)) {
			$db = self::connection();
			$grid = $db->connection->getGridFS();
			return $grid->storeFile($filename, $metadata);
		}

		return false;
	}
}

/* FILTERS
 *
*/
Asset::applyFilter('save', function($self, $params, $chain) {
	// Set the mime-type based on file extension.
	// This is used in the Content-Type header later on.
	// Doing this here in a filter saves some work in other places and all
	// that's required is a file extension.
	$ext = isset($params['entity']->fileExt) ? strtolower($params['entity']->fileExt):null;
	switch($ext) {
		default:
			$mimeType = 'text/plain';
		break;
		case 'jpg':
		case 'jpeg':
			$mimeType = 'image/jpeg';
		break;
		case 'png':
			$mimeType = 'image/png';
		break;
		case 'gif':
			$mimeType = 'image/gif';
		break;
	}
	$params['data']['contentType'] = $mimeType;

	return $chain->next($self, $params, $chain);
});

// Second, let's get the validation rules picked up from our $validate property
Asset::applyFilter('validates', function($self, $params, $chain) {
	$params['options']['rules'] = Asset::$validate;
	return $chain->next($self, $params, $chain);
});
?>