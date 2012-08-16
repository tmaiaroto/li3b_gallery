<div class="row">
	<div class="span12">
		<h2 id="page-heading">Galleries</h2>
		
		<ul class="thumbnails">
		<?php 
		foreach($documents as $gallery) {
			echo '<li class="span3">';
				echo '<div class="thumbnail">';
					if(isset($gallery->coverImage->source)) {
						echo $this->html->image('/li3b_gallery/images/240/160/' . $gallery->coverImage->source . '?letterbox=000000');
					} else {
						echo '<div class="no-thumbnail">No Cover Image Set</div>';
					}
					echo '<div class="caption">';
						echo '<h3>' . $gallery->title . '</h3>';
						echo '<p class="small"><em>Created: ' . $this->time->to('nice', $gallery->created) . '</em></p>';
						echo '<p>' . $gallery->description . '</p>';
						echo '<p>';
							echo $this->html->link('Manage Images', array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'items', 'action' => 'manage', 'admin' => true, 'args' => array($gallery->_id)), array('class' => 'btn btn-primary'));
							echo ' ';
							echo $this->html->link('Edit', array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'update', 'admin' => true, 'args' => array($gallery->_id)), array('class' => 'btn'));
							echo ' ';
							echo $this->html->link('Delete', array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'delete', 'admin' => true, 'args' => array($gallery->_id)), array('onClick' => 'return confirm(\'Are you sure you want to delete ' . $gallery->title . '?\')', 'class' => 'btn'));
						echo '</p>';
					echo '</div>';
				echo '</div>';
			echo '</li>';
		}
		?>
		</ul>			
		<?=$this->BootstrapPaginator->paginate(); ?>
		<br />
		<em>Showing page <?=$page; ?> of <?=$totalPages; ?>. <?=$total; ?> total record<?php echo ((int) $total > 1 || (int) $total == 0) ? 's':''; ?>.</em>
	</div>
</div>