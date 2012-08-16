<div class="row">
	<div class="span12">
		<h2 id="page-heading">Create New Gallery</h2>
		
		<?=$this->form->create($document, array('class' => 'form-horizontal')); ?>
			<fieldset>
				<div class="control-group">
					<?=$this->form->label('title', 'Gallery Title', array('class' => 'control-label')); ?>
					<div class="controls">
						<?=$this->form->field('title', array('class' => 'input-xlarge', 'label' => false)); ?>
					</div>
				</div>
				
				<div class="control-group">
					<?=$this->form->label('description', 'Gallery Description', array('class' => 'control-label')); ?>
					<div class="controls">
						<?=$this->form->field('description', array('type' => 'textarea', 'class' => 'input-xlarge', 'label' => false)); ?>
						<p class="help-block">This description is optional and may not even be used depending on the design of the site.</p>
					</div>
				</div>
				
				<div class="control-group">
					<?=$this->form->label('published', 'Published', array('class' => 'control-label')); ?>
					<div class="controls">
						<label class="checkbox">
							<?=$this->form->checkbox('published', array('class' => false, 'label' => false)); ?>If checked, this gallery will be visible on the web site.
						</label>
					</div>
				</div>
				
				<div class="control-group">
					<?=$this->form->label('feedPublished', 'Feed', array('class' => 'control-label')); ?>
					<div class="controls">
						<label class="checkbox">
							<?=$this->form->checkbox('feedPublished', array('class' => false, 'label' => false)); ?>If checked, this gallery will have a publicly accessible JSON feed (other sites could then display the gallery for example).
						</label>
					</div>
				</div>
			</fieldset>
			<?=$this->form->submit('Save', array('class' => 'btn btn-primary')); ?>
			<a href="javascript:history.go(-1);" class="btn">Cancel</a>
		<?=$this->form->end(); ?>
	</div>
</div>
