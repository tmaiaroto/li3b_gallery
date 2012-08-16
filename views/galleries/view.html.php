<?php
$this->html->style('/li3b_gallery/css/gallery', array('inline' => false));
//var_dump($galleryItems);

if(!empty($galleryItems)) {
	echo '<div id="li3bGalleryCarousel" class="carousel slide">';
		echo '<!-- Carousel items -->';
		echo '<div class="carousel-inner">';
		$i=0;
		foreach($galleryItems as $item) {
			$active = ($i == 0) ? ' active':'';
			echo '<div class="item' . $active . '">';
				echo $this->html->image($item['sized']);
				echo '<div class="carousel-caption">';
					echo '<h4>' . $item['title'] . '</h4>';
					echo (isset($item['description'])) ? '<p>' . $item['description'] . '</p>':'';
				echo '</div>';
			echo '</div>';
			$i++;
		}
		echo '</div>';
		echo '<!-- Carousel nav -->';
		echo '<a class="carousel-control left" href="#li3bGalleryCarousel" data-slide="prev">&lsaquo;</a>';
		echo '<a class="carousel-control right" href="#li3bGalleryCarousel" data-slide="next">&rsaquo;</a>';
	echo '</div>';
}
?>