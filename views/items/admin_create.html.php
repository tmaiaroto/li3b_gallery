<div class="grid_16">
	<h2 id="page-heading">Add Gallery Item</h2>  
</div>
<div class="clear"></div>

<div class="grid_12">
	<?=$this->form->create($document); ?>
	<fieldset class="admin">
		<legend>Primary Information</legend>
	    
	    <?=$this->form->submit('Add'); ?> <?=$this->html->link('Cancel', array('admin' => $this->minervaHtml->admin_prefix, 'library' => 'minerva', 'plugin' => 'minerva_gallery', 'controller' => 'items', 'action' => 'index')); ?>
	</fieldset>
	
</div>

<div class="grid_4">
    <div class="box">
        <h2>Options</h2>
	    <div class="block">
			<fieldset class="admin">
			
			</fieldset>
        </div>
    </div>
</div>

<?=$this->form->end(); ?>
<div class="clear"></div>