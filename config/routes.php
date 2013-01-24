<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2009, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use \lithium\net\http\Router;
use li3b_gallery\models\Asset;
use \lithium\action\Response;
use lithium\action\Dispatcher;
use li3b_gallery\extensions\util\Thumbnail;

// Simply obfuscated. This is not the end of the world if someone hits it.
// It means more CPU work for making new images...But not a big deal.
// It can also be changed and disabled if need be too.
Router::connect('/li3b_gallery/clear-db-image-cache-UWjKGnu4sVDi', array(), function($request) {
	// Note: To remove a cache file disk, a path would be passed under a 'source' key.
	// This could come from ars passed in the request...But be careful about santizing the passed data!
	// Otherwise, something undesireable could be deleted.
	return new Response(array(
		'headers' => array('Content-type' => 'text/plain'),
		'body' => (Thumbnail::clearCache()) ? 'Image cache cleared.':'Image cache could not be cleared.'
	));
});

// Route for images stored in GridFS, thumbnails.
Router::connect('/li3b_gallery/images/{:width:[0-9]+}/{:height:[0-9]+}/{:args}.(jpe?g|png|gif)', array(), function($request) {
	$image = Asset::find('first', array('conditions' => array('_id' => $request->params['args'][0])));

	if(!$image || !$image->file) {
		header("Status: 404 Not Found");
		header("HTTP/1.0 404 Not Found");
		die;
	}

	$width = isset($request->params['width']) ? (int)$request->params['width']:100;
	$height = isset($request->params['height']) ? (int)$request->params['height']:100;

	$options = array(
		'size' => array($width, $height),
		'ext' => $image->fileExt
	);

	$options['letterbox'] = isset($request->query['letterbox']) ? $request->query['letterbox']:null;
	$options['forceLetterboxColor'] = isset($request->query['forceLetterboxColor']) ? (bool)$request->query['forceLetterboxColor']:false;
	$options['crop'] = isset($request->query['crop']) ? (bool)$request->query['crop']:false;
	$options['sharpen'] = isset($request->query['sharpen']) ? (bool)$request->query['sharpen']:false;
	$options['quality'] = isset($request->query['quality']) ? (int)$request->query['quality']:85;

	// EXAMPLE REMOTE IMAGE with local disk cache and database cache.
	//$image = 'http://oddanimals.com/images/lime-cat.jpg';
	//$file = Thumbnail::create($image, LITHIUM_APP_PATH . '/webroot/img/_thumbnails', $options);
	//$file = Thumbnail::create($image, 'grid.fs', $options);

	// EXAMPLE IMAGE FROM DISK with local disk cache and database cache.
	//$file = Thumbnail::create(LITHIUM_APP_PATH . '/webroot/img/glyphicons-halflings.png', LITHIUM_APP_PATH . '/webroot/img/_thumbnails', $options);
	//$file = Thumbnail::create(LITHIUM_APP_PATH . '/webroot/img/glyphicons-halflings.png', 'grid.fs', $options);

	// EXAMPLE IMAGE FROM MONGODB with local disk cache and database cache.
	//$file = Thumbnail::create($image->file, LITHIUM_APP_PATH . '/webroot/img/_thumbnails', $options);

	$file = Thumbnail::create($image->file, 'grid.fs', $options);
	// The path will be a path on disk for a route if the destination was a cache in MonoDB.
	// Handle both.
	if(file_exists($file['path'])) {
		return new Response(array(
			'headers' => array('Content-type' => $file['mimeType']),
			'body' => file_get_contents($file['path'])
		));
	}

	// Technically, a redirect.
	return new Response(array(
		'location' => $file['path']
	));

});

// Route for images stored in GridFS.
Router::connect('/li3b_gallery/images/{:args}.(jpe?g|png|gif)', array(), function($request) {
	$image = Asset::find('first', array('conditions' => array('_id' => $request->params['args'][0])));

	if(!$image || !$image->file){
		header("Status: 404 Not Found");
		header("HTTP/1.0 404 Not Found");
		die;
	}

	return new Response(array(
		'headers' => array('Content-type' => $image->contentType),
		'body' => $image->file->getBytes()
	));
});

?>