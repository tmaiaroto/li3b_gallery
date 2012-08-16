<?php $this->html->style(array('/li3b_gallery/css/galleries_admin.css', '/li3b_gallery/css/agile-uploader.css'), array('inline' => false)); ?>
<?php echo $this->html->script(array('/li3b_gallery/js/date.js', '/li3b_gallery/js/jquery/jquery.flash.min.js', '/li3b_gallery/js/jquery/agile-uploader-3.0.js', '/li3b_gallery/js/gallery_management.js'), array('inline' => true)); ?>

<div class="row">
	<div class="span12">
		<h2 id="page-heading">Manage Gallery: <?=$document->title; ?></h2>
		<br />
	</div>
</div>

<div class="row">
<div class="span8 gallery_items" id="gallery_<?=$document['_id']; ?>">
	<?php
	if(empty($gallery_items)) {
		echo '<p>This gallery has no images.</p>';
	} else {
	foreach($gallery_items as $item) {
	?>
		<div class="gallery_item thumbnail" id="gallery_item_<?=$item['_id']; ?>">
			<div class="gallery_item_image_wrapper">
				<div class="gallery_item_image">
					
					<?php //echo $this->html->image('/li3b_gallery/images/'.$item['filename'], array('alt' => $item['title'])); ?>
					<?php echo $this->html->image('/li3b_gallery/images/175/175/' . (string)$item['source'] . '?crop=true', array('alt' => $item['title'])); ?>
				</div>
			</div>
			<div class="gallery_item_info">
				<?php $title = (strlen($item['title']) > 56) ? substr($item['title'], 0, 56) . '...':$item['title']; ?>
				<h3 id="title_for_<?=$item['title']; ?>"><?=$title; ?></h3>
				<?php 
				/*
				$p = array('Not Published', 'Published');
				echo '<p class="publish_status">' . $p[(int)$item['published']] . '</p>';
				 * 
				 */
		?>
			</div>
			<div class="gallery_item_actions">
				<?=$this->html->link('Remove', '#', array('class' => 'icon-remove remove', 'rel' => (string) $item['_id'],  'title' => 'Remove from this gallery')); ?>
				<?=$this->html->link('Edit', '#', array('class' => 'icon-pencil edit', 'rel' => (string) $item['_id'], 'title' => 'Edit item information')); ?>
				<?php
				$starClass = (isset($document['coverImage']['source']) && $item['source'] == $document['coverImage']['source']) ? 'icon-star':'icon-star-empty';
				?>
				<?=$this->html->link('Cover Image', '#', array('class' => $starClass . ' cover', 'rel' => (string) $item['_id'], 'title' => 'Set this image as the gallery cover image.')); ?>
				<?php
				/* This is GLOBAL visibility... So may want to re-think how "publish" works...
				 * What if this item was in multiple galleries? But the user only want it temporarily not displayed on the current?
				<?php $publish_status = ($item->published) ? 'published':'unpublished'; ?>
				<?=$this->html->link($publish_status, '#', array('class' => 'publish_status ' . $publish_status, 'title' => 'Change visibility')); ?>
				 *
				 * Also disable keyword tagging and geo tagging links for now...we can add that later
				<?=$this->html->link('Tags', '#', array('class' => 'tags', 'rel' => (string) $item->_id, 'title' => 'Tags for this item')); ?>
				<?=$this->html->link('Geo', '#', array('class' => 'location', 'rel' => (string) $item->_id, 'title' => 'Plot this item on a map')); ?>
				*/
				?>
			</div>
		</div>
	<?php
	}}
	?>
	<br />
</div>

<div class="span4">
	<div class="row">
		<div class="span4">
			<div class="alert alert-info">
				<h2>Gallery Information</h2>
				<!--
				<p><strong>Modified</strong><br /><?=$this->time->to('nice', $document->modified); ?></p>
				-->
				<p><strong>Created:</strong> <?=$this->time->to('nice', $document->created); ?></p>
				<p><strong>Published:</strong> <?=($document->published) ? 'Yes':'No'; ?></p>
				<p><strong>JSON Feed Published:</strong> <?=($document->feedPublished) ? 'Yes':'No'; ?></p>
				<?php /* <p><?=$this->html->link('View gallery JSON feed.', array('library' => 'gallery', 'controller' => 'gallery', 'action' => 'feed', 'type' => 'json', 'args' => array($document->url)), array('target' => '_blank')); ?></p> */ ?>
				<p><i class="icon-pencil"></i> <?=$this->html->link('Click here to edit gallery settings.', array('admin' => true, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'update', 'args' => array($document->_id))); ?></p>
				<p><i class="icon-eye-open"></i> <?=$this->html->link('Click here to view this gallery.', array('admin' => null, 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'view', 'args' => array($document->_id)), array('target' => '_blank')); ?></p>
				<?php
				if($document->feedPublished) {
				?>
				<p><i class="icon-share"></i> <?=$this->html->link('Click here to view this gallery\'s JSON feed.', array('admin' => null, 'type' => 'json', 'library' => 'li3b_gallery', 'controller' => 'galleries', 'action' => 'feed', 'args' => array($document->url)), array('target' => '_blank')); ?></p>
				<?php
				}
				?>
			</div>
		</div>
	</div>
	
	<div class="row">
		<div class="span4">
		<h2>Add New Images</h2>
			<form id="upload-new-items" enctype="multipart/form-data">
				<input type="hidden" name="gallery_id" value="<?=$document['_id']; ?>" />
			</form>
			<div id="agile_uploader"></div>
			<button class="upload_files btn btn-primary" onClick="document.getElementById('agileUploaderSWF').submit();">Upload</button>
			<div class="clear"></div>
		</div>
	</div>

	<div class="row">
		<div class="span4">
		<h2>Add Existing Media</h2>
			<p>
				You can also add other items in the system, possibly from another gallery, to this gallery as well. You can search too.
				<?=$this->html->queryForm(array('buttonLabel' => 'Search', 'inputClass' => 'searchItems input-large')); ?>
			</p>
			<div class="unassociated_gallery_items">
				<?php
				$i=1;
				foreach($items as $item) {
					$alt_class = ($i % 2 == 0) ? ' alt':'';
				?>
					<div class="unassociated_gallery_item<?=$alt_class; ?>" id="gallery_item_<?=$item->_id; ?>">
							<div class="unassociated_gallery_item_image">
								<?php echo $this->html->image('/li3b_gallery/images/50/50/' . (string)$item->source . '?crop=true', array('alt' => $item->title, 'id' => 'image_for_' . $item->_id)); ?>
							</div>
							<div class="unassociated_gallery_info_wrapper">
								<div class="unassociated_gallery_item_info">
									<?php $title = (strlen($item->title) > 37) ? substr($item->title, 0, 37) . '...':$item->title; ?>
									<h3 id="title_for_<?=$item->_id; ?>"><?=$title; ?></h3>
								</div>
							</div>
							<?=$this->html->link('+', '#', array('class' => 'add_unassociated_item', 'rel' => (string) $item->_id,  'title' => 'Add item to this gallery')); ?>
					</div>
				<?php
				$i++;
				}
				?>
			</div>
			<div class="clear"></div>
		</div>
	</div>
</div>
</div>

<div id="edit_gallery_item_modal" class="modal hide" title="Edit Item">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal">×</button>
		<h3>Update Item Information</h3>
	</div>
	<div id="edit_gallery_item_thumbnail"></div>
	<?=$this->form->create(null, array('action' => '#', 'id' => 'edit_item_form', 'class' => 'form-vertical')); ?>
		<fieldset>
			<?=$this->form->field('item_id', array('wrap' => array('class' => 'modal_input'), 'type' => 'hidden', 'label' => false, 'id' => 'edit_item_id')); ?>
			<?=$this->form->field('title', array('wrap' => array('class' => 'modal_input'))); ?>
			<?=$this->form->field('description', array('wrap' => array('class' => 'modal_input'), 'type' => 'textarea')); ?>
			<label class="checkbox"><?=$this->form->field('published', array('type' => 'checkbox', 'label' => false)); ?><strong>Published</strong></label>
			<div class="last_update">
				<p>Last updated: <span id="edit_gallery_item_last_update"></span></p>
			</div>
			<div class="submit_edit">
				<?=$this->form->submit('Save', array('class' => 'btn btn-primary')); ?>
				<?=$this->html->link('Cancel', '#', array('class' => 'close-btn btn')); ?>
			</div>
		</fieldset>
	<?=$this->form->end(); ?>
</div>

<script type="text/javascript">
$(document).ready(function() {
	// Agile Uploader
	enableAgileUploader('<?=$document['_id']; ?>');

	// Enable sorting
	enableSorting();

	// Helpful tooltips
	$('a').tipsy({gravity: 's'});

	// Add an Unassociated Item to Gallery
	$('a.add_unassociated_item').click(function() {
		var item_id = $(this).attr('rel');
		addUnassociatedItem(item_id, '<?=$document['_id']; ?>');
		return false;
	});

	// Remove Item From Gallery
	$('a.remove').click(function() {
		var item_id = $(this).attr('rel');
		if (confirm('Are you sure?')) {
		  removeItemFromGallery(item_id, '<?=$document['_id']; ?>');
		}
		return false;
	});

	// Edit Gallery Item
	$('a.edit').click(function() {
		var item_id = $(this).attr('rel');
		editGalleryItem(item_id);
		return false;
	});
	
	// Make Item Gallery Cover
	$('a.cover').click(function() {
		var item_id = $(this).attr('rel');
		setGalleryCover('<?=$document['_id']; ?>', item_id);
		return false;
	});
	
	$('a.close-btn').click(function(e) {
	 
	 e.preventDefault();
	//$("#edit_gallery_item_modal").dialog("close");
	$("#edit_gallery_item_modal").modal("hide");
	});

});
</script>
<style type="text/css">
.ui-corner-all { border-radius: 0px; -moz-border-radius-bottomright: 0px; -moz-border-radius-bottomleft: 0px; -moz-border-radius-topright: 0px; -moz-border-radius-topleft: 0px;}
</style>