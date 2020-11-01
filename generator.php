<?php

$uri_arr = explode('/', $_SERVER['REQUEST_URI']); 	// Get array from URL parts
$img = end($uri_arr);			// Get file from end of URL
$img = explode('?', $img);		// Prepare to remove any query string
$img = $img[0];					// Keep only the filename, remove query string
$img_arr = explode('.', $img);	// Get filename only, exclude extension

try {
	$video_id = $img_arr[0];
} catch (Exception $e) {
	die('Invalid or no video ID.');
}

$thumbnail = new Imagick(); // Prepare Imagick object

if (is_numeric($video_id)) { // Vimeo IDs are all numbers...

	$url = get_vimeo_thumbnail($video_id);

	$data = file_get_contents("http://vimeo.com/api/v2/video/$video_id.json");
	$data = json_decode($data);

	try {
		$thumbnail->readImage($data[0]->thumbnail_large);
	} catch (Exception $e) {
		error_log($e);
	}
} else { // If it has letters, perhaps it's a YouTube ID...

	// Available thumbnail sizes
	$youtube_thumbnail_sizes = array(
		'maxresdefault.jpg',
		'sddefault.jpg',
		'mqdefault.jpg',
	);

	// Try the largest thumbnail size first; if it's a fit, then continue, otherwise try the next one down...
	foreach ($youtube_thumbnail_sizes as $img_file) {
		$url = 'http://img.youtube.com/vi/' . $video_id . '/' . $img_file;

		try {
			$thumbnail->readImage($url);
			break;
		} catch (Exception $e) {
			error_log($e);
		}
	}
}

/**
 * Set default options here
 */
$options = array(
	'play_button_opacity' => 1,
	'width' => 640,
	'save' => true,
);

/**
 * The default $_GET functionality doesn't appear to work in this unique setup 
 * where PHP is handling an image file 404 error. Use parse_str to set it up.
 */
parse_str($_SERVER['REDIRECT_QUERY_STRING'], $_GET);

if (isset($_GET['play_button_opacity'])) {
	$options['play_button_opacity'] = (float) $_GET['play_button_opacity'];
}

if ($options['play_button_opacity'] > 1) {
	$options['play_button_opacity'] = 1;
}

if (isset($_GET['width'])) {
	$options['width'] = (int) $_GET['width'];
}

if ($options['width'] > 1920) {
	$options['width'] = 1920;
}

if (
	isset($_GET['save']) &&
	($_GET['save'] === 'false' ||
		$_GET['save'] === 'no' ||
		$_GET['save'] === 0)
) {
	$options['save'] = false;
}

/**
 * Adjust the thumbnail background's width/height, keeping its ratio
 * in proportion (in case it's fetching a 4:3 aspect thumbnail, for example)
 */
$thumbnail_bg_original_width = $thumbnail->getImageWidth();
$thumbnail_bg_original_height = $thumbnail->getImageHeight();
$thumbnail_bg_ratio = $thumbnail_bg_original_height / $thumbnail_bg_original_width;
$options['height'] = intval($options['width'] * $thumbnail_bg_ratio);

$thumbnail->resizeImage($options['width'], $options['height'], null, 1);



/**
 * Prepare the play button overlay image.
 */
$play_button = new Imagick();
$play_button->readImage(
	'https://tools.missionmike.dev/thumbnail-generator/assets/youtube_play.png'
);

$play_button_original_width = $play_button->getImageWidth();
$play_button_original_height = $play_button->getImageHeight();

$play_button_width = $options['width'] / 8; // Play button width defaults to 8% of the size of the thumbnail.
$play_button_size_adjustment = $play_button_width / $play_button_original_width;
$play_button_height = $play_button_original_height * $play_button_size_adjustment;

$play_button->resizeImage($play_button_width, $play_button_height, null, 1);

$play_button->evaluateImage(Imagick::EVALUATE_MULTIPLY, $options['play_button_opacity'], Imagick::CHANNEL_ALPHA); // Overlay the play button with some transparency (optional)

$thumbnail->setImageArtifact('compose:args', "1,0,-0.5,0.5");

$play_button_composite_coords = array(
	'x' => ($thumbnail->getImageWidth() - $play_button->getImageWidth()) / 2,
	'y' => ($thumbnail->getImageHeight() - $play_button->getImageHeight()) / 2,
);
$thumbnail->compositeImage($play_button, Imagick::COMPOSITE_DEFAULT, $play_button_composite_coords['x'], $play_button_composite_coords['y']);

// Save it
if ($options['save'] === true) {
	$thumbnail->setImageFilename($img);
	$thumbnail->writeImage();
}

// Display the image from this initial call... subsequential calls will serve the image file directly if &save=true was set in the URL. 
header("Content-Type: image/jpeg");
echo $thumbnail->getImageBlob();
