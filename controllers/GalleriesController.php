<?php
namespace li3b_gallery\controllers;

use li3b_gallery\models\Gallery;
use li3b_gallery\models\Item;
use li3b_core\util\Util;
use lithium\util\Set;
use lithium\util\Inflector;
use li3_flash_message\extensions\storage\FlashMessage;
use \MongoDate;
use \MongoId;

use lithium\analysis\Logger;

class GalleriesController extends \lithium\action\Controller {

	public function admin_index() {
		$this->_render['layout'] = 'admin';

		// Default options for pagination, merge with URL parameters
		$defaults = array('page' => 1, 'limit' => 10, 'order' => 'created.desc');
		$params = Set::merge($defaults, $this->request->params);

		if((isset($params['page'])) && ($params['page'] == 0)) {
			$params['page'] = 1;
		}
		list($limit, $page, $order) = array($params['limit'], $params['page'], $params['order']);

		// never allow a limit of 0
		$limit = ($limit < 0) ? 1:$limit;

		$conditions = array();

		// Get the documents and the total
		$documents = Gallery::find('all', array(
			'conditions' => $conditions,
			'limit' => (int)$limit,
			'offset' => ((int)$page - 1) * (int)$limit,
			'order' => $params['order']
		));

		$total = Gallery::find('count', array(
			'conditions' => $conditions
		));

		$totalPages = ((int)$limit > 0) ? ceil($total / $limit):0;

		// Set data for the view template
		$this->set(compact('documents', 'limit', 'page', 'totalPages', 'total'));
	}

	public function admin_create() {
		$this->_render['layout'] = 'admin';

		$document = Gallery::create();
		if(!empty($this->request->data)) {
			// IMPORTANT: Use MongoDate() when inside an array/object because $_schema isn't deep
			$now = new MongoDate();

			$this->request->data['created'] = $now;
			$this->request->data['modified'] = $now;

			// Set the pretty URL that gets used by a lot of front-end actions.
			$this->request->data['url'] = $this->_generateUrl();

			if($document->save($this->request->data)) {
				FlashMessage::write('Successfully created the gallery, you can now add items to it.', 'default');
				return $this->redirect(array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'index'));
			} else {
				FlashMessage::write('There was a problem creating the gallery, please try again.', 'default');
			}

		}

		$this->set(compact('document'));
	}

	public function admin_update($id=null) {
		if(empty($id)) {
			FlashMessage::write('You must provide a gallery id to update.', 'default');
			return $this->redirect(array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'index'));
		}
		$this->_render['layout'] = 'admin';

		$document = Gallery::find('first', array('conditions' => array('_id' => $id)));
		if(!empty($this->request->data)) {
			// IMPORTANT: Use MongoDate() when inside an array/object because $_schema isn't deep
			$now = new MongoDate();

			$this->request->data['modified'] = $now;

			// Set the pretty URL that gets used by a lot of front-end actions.
			// Pass the document _id so that it doesn't change the pretty URL on an update.
			$this->request->data['url'] = $this->_generateUrl($document->_id);

			if($document->save($this->request->data)) {
				FlashMessage::write('The gallery has been successfully updated.', 'default');
				return $this->redirect(array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'index'));
			} else {
				FlashMessage::write('There was a problem updating the gallery, please try again.', 'default');
			}

		}

		$this->set(compact('document'));
	}

	/**
	 * Sets the cover image for the gallery.
	 *
	 * @param $id The gallery id
	 * @param $imageId The image id
	 */
	public function admin_set_cover_image($id=null, $imageId=null) {
		// Set the response to return
		$response = array('success' => true);

		// If there was no item id provided
		if(empty($id) || empty($imageId)) {
			$response['success'] = false;
		}

		// Check to ensure that JSON was used to make the POST request
		if(!$this->request->is('json')) {
			$response['success'] = false;
		}

		$image = Item::find('first', array('conditions' => array('_id' => $imageId)));

		if($image) {
			$response['success'] = true;
		}

		if($response['success'] == true) {
			$response['success'] = Gallery::update(
				array('$set' => array(
					'coverImage.id' => $image->_id,
					'coverImage.source' => $image->source
				)),
				array('_id' => $id)
			);
		}

		$this->render(array('json' => $response));
	}

	/**
	 * Public index method to list all published galleries in the system.
	 *
	 * @return
	 */
	public function index() {
		// Default options for pagination, merge with URL parameters
		$defaults = array('page' => 1, 'limit' => 10, 'order' => 'created.desc');
		$params = Set::merge($defaults, $this->request->params);

		if((isset($params['page'])) && ($params['page'] == 0)) {
			$params['page'] = 1;
		}
		list($limit, $page, $order) = array($params['limit'], $params['page'], $params['order']);

		// never allow a limit of 0
		$limit = ($limit < 0) ? 1:$limit;

		$conditions = array('published' => 1);

		// Get the documents and the total
		$documents = Gallery::find('all', array(
			'conditions' => $conditions,
			'limit' => (int)$limit,
			'offset' => ((int)$page - 1) * (int)$limit,
			'order' => $params['order']
		));

		$total = Gallery::find('count', array(
			'conditions' => $conditions
		));

		$totalPages = ((int)$limit > 0) ? ceil($total / $limit):0;

		// Set data for the view template
		$this->set(compact('documents', 'limit', 'page', 'totalPages', 'total'));
	}

	/**
	 * Public view method.
	 * This would be a page on the web site that included a
	 * @param type $id
	 */
	public function view($id=null) {
		if(preg_match('/[0-9a-f]{24}/', $id)) {
			$field = '_id';
		} else {
			$field = 'url';
		}

		$galleryItems = array();

		// Find the gallery document itself (by _id or url)
		$document = Gallery::find('first', array('conditions' => array($field => $id, 'published' => true)));
		if($document) {
			// Find all items for the current gallery
			$galleryItems = Item::find('all', array(
				'conditions' => array('published' => true, '_galleries' => (string)$document->_id)
			));
			$galleryItems = (!empty($galleryItems)) ? $galleryItems->data():array();

			// Order those gallery items based on the gallery document's galleryItemOrder field (if set)
			if(isset($document->galleryItemOrder) && !empty($document->galleryItemOrder)) {
				$ordering = $document->galleryItemOrder->data();
				$ordering = array_flip($ordering);

				$orderedItems = false;
				foreach ($galleryItems as $key => $item) {
					if (isset($ordering[$item['_id']])) {
						$orderedItems[$ordering[$item['_id']]] = $item;
						unset($galleryItems[$key]);
					}
				}
				if ($orderedItems) {
					ksort($orderedItems);
					$galleryItems = $orderedItems += $galleryItems;
				}
			}

			foreach($galleryItems as $k => $v) {
				if($v['service'] == 'mongo') {
					unset($galleryItems[$k]['service']);
					unset($galleryItems[$k]['originalFilename']);

					$domain = 'http';
					if ((isset($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] == 'on')) {$domain .= 's';}
					$domain .= '://';
					if ($_SERVER['SERVER_PORT'] != '80') {
						$domain .= $_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_PORT'];
					} else {
						$domain .= $_SERVER['HTTP_HOST'];
					}
					$galleryItems[$k]['sized'] = $domain . '/li3b_gallery/images/780/520/' . $galleryItems[$k]['source'] . '?letterbox=333333';
					$galleryItems[$k]['source'] = $domain . '/li3b_gallery/images/' . $galleryItems[$k]['source'];
				}
			}
		}

		$this->set(compact('document', 'galleryItems'));
	}

	/**
	 * Returns a JSON feed of gallery items.
	 *
	 * @param string $id The gallery id or URL
	 * @return string The gallery in JSON format
	*/
	public function feed($id=null) {
		$response = array('success' => true);

		if(empty($id)) {
			$response['success'] = false;
		}

		// Check to ensure that JSON was used to make the POST request
		if(!$this->request->is('json')) {
			$response['success'] = false;
		}

		if(preg_match('/[0-9a-f]{24}/', $id)) {
			$field = '_id';
		} else {
			$field = 'url';
		}

		if($response['success'] === true) {
			// Find the gallery document itself (by _id or url)
			$document = Gallery::find('first', array('conditions' => array($field => $id, 'feedPublished' => true)));
			if(empty($document)) {
				$response['success'] = false;
			}

			if($response['success'] === true) {
				// Find all items for the current gallery
				$galleryItems = Item::find('all', array(
					'conditions' => array('published' => true, '_galleries' => (string)$document->_id)
				));
				$galleryItems = (!empty($galleryItems)) ? $galleryItems->data():array();

				// Order those gallery items based on the gallery document's galleryItemOrder field (if set)
				if(isset($document->galleryItemOrder) && !empty($document->galleryItemOrder)) {
					$ordering = $document->galleryItemOrder->data();
					$ordering = array_flip($ordering);

					$orderedItems = false;
					foreach ($galleryItems as $key => $item) {
						if (isset($ordering[$item['_id']])) {
							$orderedItems[$ordering[$item['_id']]] = $item;
							unset($galleryItems[$key]);
						}
					}
					if ($orderedItems) {
						ksort($orderedItems);
						$galleryItems = $orderedItems += $galleryItems;
					}
				}

				foreach($galleryItems as $k => $v) {
					if($v['service'] == 'mongo') {
						unset($galleryItems[$k]['service']);
						unset($galleryItems[$k]['originalFilename']);

						$domain = 'http';
						if ((isset($_SERVER['HTTPS'])) && ($_SERVER['HTTPS'] == 'on')) {$domain .= 's';}
						$domain .= '://';
						if ($_SERVER['SERVER_PORT'] != '80') {
							$domain .= $_SERVER['HTTP_HOST'].':'.$_SERVER['SERVER_PORT'];
						} else {
							$domain .= $_SERVER['HTTP_HOST'];
						}
						$galleryItems[$k]['thumbnail'] = $domain . '/li3b_gallery/images/175/175/' . $galleryItems[$k]['source'];
						$galleryItems[$k]['source'] = $domain . '/li3b_gallery/images/' . $galleryItems[$k]['source'];
					}
				}

				$response['gallery'] = $document->data();
				$response['items'] = $galleryItems;
			}
		}
		$this->render(array('json' => $response));
	}

	/**
	 * Generates a pretty URL for the gallery document.
	 *
	 * @return string
	*/
	private function _generateUrl($id=null) {
		$url = '';
		$url_field = Gallery::urlField();
		$url_separator = Gallery::urlSeparator();
		if($url_field != '_id' && !empty($url_field)) {
			if(is_array($url_field)) {
				foreach($url_field as $field) {
					if(isset($this->request->data[$field]) && $field != '_id') {
						$url .= $this->request->data[$field] . ' ';
					}
				}
				$url = Inflector::slug(trim($url), $url_separator);
			} else {
				$url = Inflector::slug($this->request->data[$url_field], $url_separator);
			}
		}

		// Last check for the URL...if it's empty for some reason set it to "user"
		if(empty($url)) {
			$url = 'gallery';
		}

		// Then get a unique URL from the desired URL (numbers will be appended if URL is duplicate) this also ensures the URLs are lowercase
		$options = array(
			'url' => $url,
			'model' => 'li3b_gallery\models\Gallery'
		);
		// If an id was passed, this will ensure a document can use its own pretty URL on update instead of getting a new one.
		if(!empty($id)) {
			$options['id'] = $id;
		}
		return Util::uniqueUrl($options);
	}

}
?>