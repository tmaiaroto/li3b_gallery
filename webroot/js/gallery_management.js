/**
 * Adds an item to the specified gallery.
 *
 * @param gallery_id The gallery id
 * @param item_id The item id
 * @return boolean
*/
function addGalleryItem(gallery_id, item_id) {
	if(typeof(gallery_id) == 'undefined' || typeof(item_id) == 'undefined') {
		return false;
	}
	
	$.get('/admin/plugin/li3b_gallery/items/meta/' + item_id + '.json', function(data) {
		console.dir(data.data);
		var item_title = data.data['title'];
		if(item_title.length > 27) {
			item_title = item_title.substr(0, 27) + '...';
		}
		var html = '';
		html += '<div class="gallery_item" id="gallery_item_' + item_id + '">';
			html += '<div class="gallery_item_image_wrapper">';
					html += '<div class="gallery_item_image">';
						// Note: This won't be a resized image. It will be the full size scaled down. Until page refresh.
						// There's no way around that short of having thumbnails generated from the controller or on the upload process instead of the helper.
						// html += '<img id="image_for_' + item_id + '" src="' + data.data['source'] + '" width="175" alt="' + data.data['title'] + '" />';
						// Now it will be because we have routes resizing images instead of just the helper...
						html += '<img id="image_for_' + item_id + '" src="/li3b_gallery/images/175/175/' + data.data['source'] + '?crop=true" alt="' + data.data['title'] + '" />';
					html += '</div>';
			html += '</div>';
			html += '<div class="gallery_item_info">';
					html += '<h3 id="title_for_' + item_id + '">' + item_title + '</h3>';
			html += '</div>';
			html += '<div class="gallery_item_actions">';
					html += '<a href="#" onClick="removeItemFromGallery(\'' + item_id + '\', \'' + gallery_id + '\'); return false;" class="remove" rel="' + item_id + '" title="Remove from this gallery">Remove</a>';
					html += '<a href="#" onClick="editGalleryItem(\'' + item_id + '\'); return false;" class="edit" rel="' + item_id + '" title="Edit item information">Edit</a>';
					html += '<a href="#" onClick="setGalleryCover(\'' + gallery_id + '\', \'' + item_id + '\'); return false;" class="cover" rel="' + item_id + '" title="Set this image as the gallery cover image.">Cover Image</a>';
					// Will come back and add keyword tagging and geo tagging later.
					// html += '<a href="#" class="tags" rel="' + item_id + '" title="Tags for this item">Tags</a>';
					// html += '<a href="#" class="location" rel="' + item_id + '" title="Plot this item on a map">Geo</a>';
			html += '</div>';
		html += '</div>';
		
		$(html).hide().appendTo('.gallery_items').fadeIn('medium');
		// $('.gallery_items').append(html);
		return true;
	});
}

/**
 * Removes an item from the current gallery.
 * Removed items will fade out.
 * 
 * @param item_id The gallery item id
 * @param gallery_id The gallery id
 * @return Success of the action
*/
function removeItemFromGallery(item_id, gallery_id) {
	if(typeof(item_id) == 'undefined') {
		return false;
	}
	$.get('/admin/plugin/li3b_gallery/items/association/remove/' + item_id + '/' + gallery_id + '.json', function(data) {
		if(data.success == true) {
			$('#gallery_item_' + item_id).fadeOut('medium');
			return true;
		} else {
			return false;
		}
	});
}

/**
 * Pops up a dialog to edit a gallery item's title and description.
 * 
 * @param item_id The gallery item id
 * @return 
*/
function editGalleryItem(item_id) {
	// set item id to edit (important, so form posts to proper url)
	$('#edit_item_id').val(item_id);

	// set the item's current data, since multiple updates could make this data stale, 
	// we need to get it fresh...
	$.get('/admin/plugin/li3b_gallery/items/meta/' + item_id + '.json', function(data) {
		$('#edit_item_form #Title').val(data.data['title']);
		$('#edit_item_form #Description').val(data.data['description']);
		$('#edit_item_form #Published').attr('checked', data.data['published']);
		var d = new Date(data.data['modified'] * 1000);
		var modified = Date.parse(d.toDateString()).toString('MMMM d, yyyy');
		$('#edit_gallery_item_last_update').text(modified);

		$('#edit_gallery_item_thumbnail').html('<img src="' + $('#gallery_item_' + item_id).find('.gallery_item_image img').attr('src') + '" alt="' + $('#image_for_' + item_id).attr('alt') + '" />');
	});

	/*
	$("#edit_gallery_item_modal").dialog({
		height: 400,
		width: 450,
		modal: true
	});
	*/
	// using Twitter bootstrap
	$( "#edit_gallery_item_modal" ).modal({
		height: 400,
		width: 450,
		modal: true
	});
	return false;
}

/**
 * Sets the cover image for a gallery.
 * 
 * @param gallery_id The gallery id
 * @param item_id The gallery item id
 * @return Success of the action
*/
function setGalleryCover(gallery_id, item_id) {
	if(typeof(item_id) == 'undefined') {
		return false;
	}
	$.get('/admin/plugin/li3b_gallery/galleries/set_cover_image/' + gallery_id + '/' + item_id + '.json', function(data) {
		if(data.success == true) {
			$('.cover').addClass('icon-star-empty').removeClass('icon-star');
			$('.cover', '#gallery_item_' + item_id).removeClass('icon-star-empty').addClass('icon-star');
			return true;
		} else {
			return false;
		}
	});
}

/**
 * Adds an unassocited item to the gallery.
 * 
 * @param item_id The item id
 * @param gallery_id The gallery id to add the item to
 * @return boolean Success of the action
*/
function addUnassociatedItem(item_id, gallery_id) {
	if(typeof(item_id) == 'undefined' || typeof(gallery_id) == 'undefined') {
		return false;
	}
	
	$.get('/admin/plugin/li3b_gallery/items/association/add/' + item_id + '/' + gallery_id + '.json', function(data) {
		if(data.success == true) {
			addGalleryItem(gallery_id, item_id);
			$('#gallery_item_' + item_id).fadeOut('medium');
			return true;
		} else {
			return false;
		}
	});
}

/**
 * Enables the listed gallery items to be sorted.
 * Ideally, the div with class of 'gallery_items' will
 * have an id of 'gallery_ID' where ID is the gallery id.
 * Then this method won't need to be passed anything.
 * 
 * @param gallery_id The current gallery id (optional if id is set on .gallery_items div)
 * @return
 */
function enableSorting(gallery_id) {
	if(typeof(gallery_id) == 'undefined' || gallery_id == false) {
		gallery_id = $('.gallery_items').attr('id');
		gallery_id = gallery_id.substr(8);
	}
		
	$(".gallery_items").sortable({
		stop: function(event, ui) {
			var gallery_order = new Array();
			$('.gallery_items').children().each(function() {
				gallery_order.push($(this).attr('id').substr(13));
			});
			
			$.ajax({
				type: 'POST',
				url: '/admin/plugin/li3b_gallery/items/order/' + gallery_id + '.json',
				data: {'order':gallery_order},
				success: function(data) {
					// data.success
					// TODO: Flash a confirmation message that lets the user know the order was updated
				}
			});
		}
	});
}

/**
 * Enables Agile Uploader
 * @param gallery_id The gallery id
 * @return
*/
function enableAgileUploader(gallery_id) {
	$('#agile_uploader').agileUploader({
		flashSrc: '/li3b_gallery/swf/agile-uploader.swf',
		flashWidth: 66,
		removeIcon: '/li3b_gallery/img/trash-icon.png',
		genericFileIcon: '/li3b_gallery/img/file-icon.png',
		submitRedirect: '/admin/plugin/li3b_gallery/items/manage/' + gallery_id,
		formId: 'upload-new-items',
		flashVars: {
			form_action: '/admin/plugin/li3b_gallery/items/create',
			file_limit: 20,
			file_filter: '*.jpg;*.jpeg;*.gif;*.png;*.JPG;*.JPEG;*.GIF;*.PNG',
			resize: 'jpg,jpeg,gif',
			force_preview_thumbnail: 'true',
			firebug: 'false',
			button_up:'/li3b_gallery/img/add-file-normal.png',
			button_over:'/li3b_gallery/img/add-file-over.png',
			button_down:'/li3b_gallery/img/add-file-over.png'
		}
	});
}

// Events to load on ready
$(document).ready(function() {
	// Submit the edit item form via AJAX
	$('#edit_item_form').submit(function() {
		$.ajax({
			type: 'POST',
			url: '/admin/plugin/li3b_gallery/items/meta/' + $('#edit_item_id').val() + '.json',
			data: $(this).serialize(),
			success: function(data) {
				// update the thumbnail on the page
				$('#title_for_' + data.data['_id']).text(data.data['title']);

				// and close the dialog
				//$( "#edit_gallery_item_modal" ).dialog("close");
				$( "#edit_gallery_item_modal" ).modal("hide");

				//refresh the page
				window.location.reload();
			}
		});
		return false;
	});
});