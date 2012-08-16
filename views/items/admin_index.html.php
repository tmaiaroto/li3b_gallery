<div class="grid_16">
    <h2 id="page-heading">All Gallery Items</h2>
</div>

<div class="clear"></div>

<div class="grid_12">
    <table>
        <thead>
            <tr>
                <th>Item Title</th>
                <th>Owner</th>
                <th>Last Modified</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <?php foreach($documents as $page) { ?>
        <tr>
        </tr>
        <?php } ?>
    </table>

<?=$this->Paginator->paginate(); ?>
<br />
<em>Showing page <?=$page_number; ?> of <?=$total_pages; ?>. <?=$total; ?> total record<?php echo ((int) $total > 1 || (int) $total == 0) ? 's':''; ?>.</em>
</div>

<div class="grid_4">
    <div class="box">
        <h2>Search for Items</h2>
	    <div class="block">
		<?=$this->html->query_form(array('label' => 'Query ')); ?>
            </div>
    </div>
</div>

<div class="clear"></div>

<script type="text/javascript">
    $(document).ready(function() {
	/*$('#create_new_page').live('hover', function() {
	    $('#new_page_type').show();
	});*/
    });
</script>