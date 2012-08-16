<?php
/**
 * Gallery Plugin
 * 
*/
namespace li3b_gallery\models;

use lithium\core\Libraries;

class Gallery extends \li3b_core\models\BaseModel {
	
	protected $_meta = array(
		'locked' => true,
		'connection' => 'li3b_mongodb',
		'source' => 'li3b_gallery.galleries'
	);
	
	// Add new fields here
	protected $_schema = array(
		'_id' => array('type' => 'id'),
		'title' => array('type' => 'string'),
		'description' => array('type' => 'string'),
		'tags' => array('type' => 'array'),
		'coverImage' => array('type' => 'array'),
		'galleryItemOrder' => array('type' => 'array'),
		'published' => array('type' => 'boolean'),
		'feedPublished' => array('type' => 'boolean'),
		'url' => array('type' => 'string'),
		'modified' => array('type' => 'date'),
		'created' => array('type' => 'date')
	);
	
	public $url_field = 'title';
	
	public $url_separator = '-';
	
	public $search_schema = array(
		'description' => array(
			'weight' => 1
		),
		'tags' => array(
			'weight' => 1
		)
	);
	
	public static function __init() {
		$class =  __CLASS__; 
		$libConfig = Libraries::get('gallery');
		if(isset($libConfig['collectionPrefix'])) {
			 static::_object()->_meta['source'] = $libConfig['collectionPrefix'] . 'galleries';
		}
		
		parent::__init();
	}
}	
?>