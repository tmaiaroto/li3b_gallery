<?php
namespace li3b_gallery\controllers;

use li3b_gallery\models\Asset;
use li3b_gallery\extensions\util\Thumbnail;
use li3b_gallery\models\Item;
use li3b_gallery\models\Gallery;
use li3_flash_message\extensions\storage\FlashMessage;
use lithium\util\Set;
use \MongoDate;
use \MongoId;

use lithium\analysis\Logger;

class ItemsController extends \lithium\action\Controller {
	
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
		$documents = Item::find('all', array(
			'conditions' => $conditions,
			'limit' => (int)$limit,
			'offset' => ((int)$page - 1) * (int)$limit,
			'order' => $params['order']
		));
		
		$total = Item::find('count', array(
			'conditions' => $conditions
		));
		
		$page_number = (int)$page;
		$total_pages = ((int)$limit > 0) ? ceil($total / $limit):0;
		
		// Set data for the view template
		$this->set(compact('documents', 'limit', 'page_number', 'total_pages', 'total'));
	}
	
	/**
	 * Creates a new item for a gallery.
	 * This is a request made by Agile Uploader.
	 * 
	 */
	public function admin_create() {
		if(!empty($this->request->data['Filedata'])) {
			//Logger::debug(json_encode($this->request->data));
			
			// IMPORTANT: Use MongoDate() when inside an array/object because $_schema isn't deep
			$now = new MongoDate();
			$data = array();
			
			// IMPORTANT: The current/target gallery id must be passed in order to associate the item.
			// Otherwise, it'd be stored loose in the system.
			$gallery_id = $this->request->data['gallery_id'];
			
			// If there was only one file uploaded, stick it into a multi-dimensional array.
			// It's just easier to always run the foreach() and code the processing stuff once and here.
			// For now...while we're saving to disk.
			if(!isset($this->request->data['Filedata'][0]['error'])) {
				$this->request->data['Filedata'] = array($this->request->data['Filedata']);
			}
			
			foreach($this->request->data['Filedata'] as $file) {
				
				// Save file to gridFS
				if ($file['error'] == UPLOAD_ERR_OK) {
					$ext = substr(strrchr($file['name'], '.'), 1);
					switch(strtolower($ext)) {
						case 'jpg':	
						case 'jpeg':
						case 'png':
						case 'gif':
						case 'png':
							//$file_data = file_get_contents($file['tmp_name']);
							//$gridFile = Asset::create(array('file' => $file_data, 'file_name' => $file['name'], 'file_type' => $ext));
							// Don't get the contents, just give MongoDB the path to the tmp file. It will save it.
							// When doing it this way, an uploadDate is automatically set too. This removes the need to manually set a created date field.
							// Also, I'm not sure files can be "modified" so the modified field is also out.
							// Also, 'file_name' was redundant. 'filename' is now: hostname + UUID + extension. This allows "duplicate" files.
							// So if user A uploads "image.jpg" and user B also uploads another "image.jpg" even though they are different 
							// (or even exactly the same)...They are two entries in GridFs.
							$gridFile = Asset::create(array('file' => $file['tmp_name'], 'filename' => (string)uniqid(php_uname('n') . '.') . '.'.$ext, 'fileExt' => $ext));
							$gridFile->save();
						break;
						default:
							//exit();
						break;
					}
				}
				
				// If file saved, save item
				if ($gridFile->_id) {
					// Create an Item object
					$id = new MongoId();
					$title = trim(str_replace(array('-', '_', '.'.$ext), array(' ', ' ', '', ''), $file['name']));
					
					// TODO: If files were saved to disk, this would be the full path on disk.
					// If files were saved in S3, it would be a path on S3, etc.
					$source = (string)$gridFile->_id . '.' . $ext;
					
					$service = 'mongo';
					
					// However, created and modified must be set for here.
					$document = Item::create(array(
						'_id' => $id,
						'created' => $now,
						'modified' => $now,
						'service' => $service,
						'source' => $source,
						'title' => $title,
						'originalFilename' => $file['name'],
						'_galleries' => array($gallery_id),
						'published' => true
					)); 
					
					if($document->save($data)) {
						FlashMessage::write('Successfully added item(s) to the gallery.', array(), 'default');
					}
				}
			}
			return;
		}
		FlashMessage::write('Sorry, there was seemingly nothing to upload, please add some files and try again.', array(), 'default');
		
	}
	
	/**
	 * The manage method serves as both an index listing of all galleries as well
	 * as a management tool for items within the gallery.
	 * 
	 * If no $id is passed, then an indexed listing of galleries (with links) will appear.
	 * Clicking on one of these listed galleries will then return to this method with
	 * and $id value present.
	 * 
	 * When an $id is present, the user will be able to add existing gallery items to 
	 * the gallery as well as upload new gallery items to be associated with the gallery.
	 * 
	 * @param string $id The Page id for the gallery
	 * @return
	 */
	public function admin_manage($id=null) {
		if(empty($id)) {
			FlashMessage::write('You must provide a gallery id to manage its items.', array(), 'default');
			return $this->redirect(array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'index'));
		}
		
		$this->_render['layout'] = 'admin';
		
		// Only pull the latest 30 gallery items from the entire system...
		// Because it's reasonable. There could be thousands of items and paging 
		// through is an option, but not practical for the design and user experience.
		// 30 of the latest is enough and the user can make a search to find what 
		// they are after. The point of this listing of items is to allow the user to
		// associate an existing item in the system with the current gallery. 
		// It's not going to be as common as adding brand new items instead.
		// Well, unless the user really goes back to share items across multiple 
		// galleries on a regular basis...I don't think it's common, but possible.
		// So showing 15 plus a search is plenty.
		$conditions = array('published' => true, '_galleries' => array('$nin' => array($id)));

		// For search queries for items
		if((isset($this->request->query['q'])) && (!empty($this->request->query['q']))) {
			$search_schema = Item::searchSchema();
			$search_conditions = array();
			// For each searchable field, adjust the conditions to include a regex
			foreach($search_schema as $k => $v) {
				$search_regex = new \MongoRegex('/' . $this->request->query['q'] . '/i');
				$conditions['$or'][] = array($k => $search_regex);
			}
		}
		// Find the unassociated gallery items
		$items = Item::find('all', array(
			'conditions' => $conditions,
			'limit' => 15,
			'order' => array('created' => 'desc')
		));

		// Find all items for the current gallery
		$gallery_items = Item::find('all', array('conditions' => array('_galleries' => $id)));

		// Find the gallery document itself
		$document = Gallery::find('first', array('conditions' => array('_id' => $id)));

		// Order those gallery items based on the gallery document's gallery_item_order field (if set)

		if(isset($document->gallery_item_order) && !empty($document->gallery_item_order)) {
			// This sort() method is the awesome.
			$ordering = $document->gallery_item_order->data();
			// data() must be called so that the iterator loads up all the documents...
			// Something that has to be fixed I guess. Then data() doesn't need to be called.
			$gallery_items = $gallery_items->data();
			
			$ordering = array_flip($ordering);
			foreach ($gallery_items as $key => $item) {
				if (isset($ordering[$item['_id']])) $gallery_order[$ordering[$item['_id']]] = $item; 
			}
			if (isset($gallery_order)) {
				$gallery_items = $gallery_order;
				ksort($gallery_items);
			}
			
			/* Need to fix
			$gallery_items->sort(function($a, $b) use ($ordering) {
				if($a['_id'] == $b['_id']) {
					return strcmp($a['_id'], $b['_id']);
				}
				$cmpa = array_search($a['_id'], $ordering);
				$cmpb = array_search($b['_id'], $ordering);
				return ($cmpa > $cmpb) ? 1 : -1;
			});
			*/
		}

		$this->set(compact('document', 'items', 'gallery_items'));
	}	
	
	/**
	 * Gets/updates the meta data for an Item.
	 * This would include: title, description, tags, geo, etc.
	 * 
	 * This method is meant to be called via AJAX.
	 * 
	 * @param string $id The item MongoId
	 * @return JSON Resposne
	*/
	public function admin_meta($id=null) {
		// Set the response to return
		$response = array('success' => true);
		
		// If there was no item id provided
		if(empty($id)) {
			$response['success'] = false;
		}
		
		// Check to ensure that JSON was used to make the POST request
		if(!$this->request->is('json')) {
			$response['success'] = false;
		}
		
		// If we're ok so far (meaning we have an $id, the user is authorized, the and its a JSON request)...
		if($response['success'] === true) {	
			// If update...
			if(!empty($this->request->data)) {
				$now = new MongoDate();
				$data = array(
					'modified' => $now,
					'title' => $this->request->data['title'],
					'description' => $this->request->data['description'],
					'tags' => (isset($this->request->data['tags'])) ? $this->request->data['tags'] : ''
					// 'location' => $this->request->data['geo']
				);

				// Remove anything that's empty so we don't update the document with empty data since
				// different AJAX calls update some fields and not others. It's not just one update call.
				$data = array_filter($data);
				
		$data['published'] = (boolean)$this->request->data['published'];
				// Update
				$response['success'] = Item::update(
					array(
						'$set' => $data
					),
					array('_id' => $id),
					array('atomic' => false)
				);
			
			} 
		}
		
		// Always set the latest data regardless of why this method was called.
		$document = Item::find('first', array('conditions' => array('_id' => $id)));
		$response['data'] = $document->data();
		
		$this->render(array('json' => $response));
	}
	
	/**
	 * Removes/Adds the item from/to a gallery.
	 * 
	 * This method is meant to be called via AJAX.
	 * 
	 * @param string $action "remove" or "add" ("remove" "0" and "false" will all remove an item)
	 * @param string $id The item MongoId
	 * @param string $gallery_id The gallery MongoId
	 * @return JSON Resposne
	*/
	public function admin_association($action='remove', $id=null, $gallery_id=null) {
		
		// Set the response to return
		$response = array('success' => true);
		
		// If there was no item or gallery id provided
		if(empty($id) || empty($gallery_id)) {
			$response['success'] = false;
		}
		
		// If $action is anything other than remove, 0, or false, the association will be added.
		$remove = ($action == 'remove' || $action == '0' || $action == 'false') ? true:false;
		
		// Check to ensure that JSON was used to make the POST request
		if(!$this->request->is('json')) {
			$response['success'] = false;
		}
		
		
		// If we have what we need, update the item
		if($response['success'] === true) {
			if($remove) {
				$item_update_query = array('$pull' => array('_galleries' => $gallery_id));
			} else {
				$item_update_query = array('$addToSet' => array('_galleries' => $gallery_id));
			}
			
			$response['success'] = Item::update(
				$item_update_query,
				array('_id' => $id),
				array('atomic' => false)
			);
			
			// Also $pull the item id from the gallery's document ordering field
			// The success of this is less important because if for some reason it isn't updated,
			// it should straighten out later when items are re-ordered and it doesn't even matter
			// if it's dirty. This is because it's not an association, it's just an ordering and 
			// if an item doesn't exist in the order it will simply be ignored.
			if($remove) {
				$updateQuery = array('$pull' => array('gallery_item_order' => $id));
			} else {
				// If new association, the item will be added at the end of the order
				$updateQuery = array('$addToSet' => array('gallery_item_order' => $id));
			}
			
			Gallery::update(
				$updateQuery,
				array('_id' => $gallery_id),
				array('atomic' => false)
			);
			
		}
		
		$this->render(array('json' => $response));
	}
	
	/**
	 * Sets item order for a given gallery.
	 * 
	 * This method is meant to be called via AJAX.
	 * 
	 * @param string $gallery_id The gallery MongoId
	 * @return JSON Resposne
	*/
	public function admin_order($gallery_id=null) {
		// Set the response to return
		$response = array('success' => true);
		
		// If there was no gallery id provided
		if(empty($gallery_id)) {
			$response['success'] = false;
		}
		
		// Check to ensure that JSON was used to make the POST request
		if(!$this->request->is('json')) {
			$response['success'] = false;
		}
		
		// If we have what we need, update the item
		if($response['success'] === true) {
			if(isset($this->request->data['order']) && is_array($this->request->data['order'])) {
				$response['success'] = Gallery::update(
					array('$set' => array('gallery_item_order' => $this->request->data['order'])),
					array('_id' => $gallery_id),
					array('atomic' => false)
				);
			} else {
				$response['success'] = false;
			}
		}
		
		$this->render(array('json' => $response));
	}
	
	/**
	 * Clears thumbnail image cache.
	 * 
	 * From time to time there will be a lot of thumbnails generated in
	 * the database or on disk. There's no real way to tell which are
	 * still in use and which aren't. This method will remove all of them.
	 * They will be re-created on demand when requseted by visitors or admins
	 * looking at pages that use them or loading URLs to generate them.
	 * 
	 */
	public function admin_clear_cache() {
		if(Thumbnail::clearCache()) {
			FlashMessage::write('Successfully cleared the thumbnail image cache.', array(), 'default');
		} else {
			FlashMessage::write('Could not clear the thumbnail image cache, please try again.', array(), 'default');
		}
		
		return $this->redirect(array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'index'));
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
			$document = Page::find('first', array('conditions' => array($field => $id, 'published' => true)));
			if(empty($document)) {
				$response['success'] = false;
			}
			
			if($response['success'] === true) {
				// Find all items for the current gallery
				$gallery_items = Item::find('all', array('conditions' => array('_galleries' => (string)$document->_id)));
				
				// Order those gallery items based on the gallery document's gallery_item_order field (if set)
				if(isset($document->gallery_item_order) && !empty($document->gallery_item_order)) {
					// This sort() method is the awesome.
					$ordering = $document->gallery_item_order->data();
					// data() must be called so that the iterator loads up all the documents...
					// Something that has to be fixed I guess. Then data() doesn't need to be called.
					$gallery_items->data();
					$gallery_items->sort(function($a, $b) use ($ordering) {
						if($a['_id'] == $b['_id']) {
						  return strcmp($a['_id'], $b['_id']);
						}
						$cmpa = array_search($a['_id'], $ordering);
						$cmpb = array_search($b['_id'], $ordering);
						return ($cmpa > $cmpb) ? 1 : -1;
					});

				}
				$response['gallery'] = $document->data();
				$response['items'] = $gallery_items->data();
			}
		}
		$this->render(array('json' => $response));
	}
	
}
?>