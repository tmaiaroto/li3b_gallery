<?php
/**
 * This template is used when going to this URL:
 * /minerva/plugin/minerva_gallery/admin/pages/read/tom-s-gallery
 * 
 * Minerva's admin pages index does not link to that URL.
 * Routing needs to be fixed. Big time.
 */
?>
<div class="grid_16">
    <h2 id="page-heading"><?=$document->title; ?></h2>
</div>

<div class="clear"></div>

<div class="grid_12">
    <?php echo $document->body; ?>
	
	
</div>
<div class="grid_4">
    <div class="box">
        <h2>Details</h2>
        <div class="block">
            <p><strong>Created</strong><br /><?=$this->minervaTime->to('nice', $document->created); ?></p>
            <p><strong>Modified</strong><br /><?=$this->minervaTime->to('nice', $document->modified); ?></p>
            <p><strong>Published</strong><br /><?=($document->published) ? 'Yes':'No'; ?></p>
			<p><strong>JSON Feed URL</strong><br /><?=$this->html->link('Click here to view', array('library' => 'minerva_gallery', 'controller' => 'items', 'action' => 'feed', 'type' => 'json', 'args' => array($document->url))); ?></p>
        </div>
    </div>
</div>

<div class="clear"></div>