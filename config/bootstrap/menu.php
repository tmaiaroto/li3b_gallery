<?php
use li3b_core\models\BootstrapMenu as Menu;

Menu::applyFilter('static_menu',  function($self, $params, $chain) {
	if($params['name'] == 'admin') {
		$self::$static_menus['admin']['xgalleries'] = array(
			'title' => 'Galleries <b class="caret"></b>',
			'url' => '#',
			'activeIf' => array('library' => 'li3b_gallery', 'controller' => 'galleries'),
			'options' => array('escape' => false),
			'subItems' => array(
				array(
					'title' => 'List All',
					'url' => array('library' => 'li3b_gallery', 'admin' => true, 'controller' => 'galleries', 'action' => 'index')
				),
				array(
					'title' => 'Create New',
					'url' => array('library' => 'li3b_gallery', 'admin' => true, 'controller' => 'galleries', 'action' => 'create')
				),
				array(
					'title' => 'Clear Thumbnail Cache',
					'url' => array('library' => 'li3b_gallery', 'admin' => true, 'controller' => 'items', 'action' => 'clear_cache')
				)
			)
		);
	}
	
	return $chain->next($self, $params, $chain);
});
?>