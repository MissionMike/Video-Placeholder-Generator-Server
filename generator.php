<?php

/**
 * Prevent direct access to this file; if the file isn't served
 * as a result of a 404 error (as intended), let 'em know.
 */
if ($_SERVER['REDIRECT_STATUS'] !== '404') {
	die("Direct access forbidden");
}

/**
 * In a proper configuration, a request for "abcdefgh.jpg" would return a 404 error if the 
 * image does not exist. That 404 error will route the server to this .php file.
 * 
 * ImageMagick's PHP extension "imagick" is required to proceed any further.
 */
if (!extension_loaded('imagick')) {
	die('imagick PHP extension is not installed/loaded.');
}

/**
 * Define a global to help prevent access to any included files
 */
define('VPG_LOADED', true);

/**
 * analytics.php could contain any kind of server-side tracking or other 3rd-party code.
 * I like to incorporate sentry.io to help diagnose errors, but analytics.php can contain whatever
 * you like... or, it could simply not exist.
 * 
 * If you want to prevent direct access to your analytics.php file, add a line:
 * defined('VPG_LOADED') or die();
 */
if (file_exists('analytics.php')) {
	require_once('analytics.php');
}

/**
 * If the 404 error was looking for "abcdefgh.jpg", then the following lines are meant
 * to parse the URL and extract the filename only, without extension; ex: "abcdefgh"
 */
try {
	$uri_arr = explode('/', $_SERVER['REQUEST_URI']); 	// Get array from URL parts
	$img = end($uri_arr);			// Get file from end of URL
	$img = explode('?', $img);		// Prepare to remove any query string
	$img = $img[0];					// Keep only the filename, remove query string
	$img_arr = explode('.', $img);	// Get filename only, exclude extension
} catch (Exception $e) {
	die('Unexpected error parsing the image file: ' . $e->getMessage());
}

try {
	$video_id = $img_arr[0];
} catch (Exception $e) {
	die('Invalid or no video ID: ' . $e->getMessage());
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
	'play_button_url' => './assets/youtube_play.png',
	'play_button_opacity' => 1,
	'play_button_width' => 80,
	'width' => 640,
	'save' => true,
);

/**
 * The default $_GET functionality doesn't appear to work in this unique setup 
 * where PHP is handling an image file 404 error. Use parse_str to set it up.
 */
parse_str($_SERVER['REDIRECT_QUERY_STRING'], $_GET);

if (isset($_GET['play_button_opacity'])) {
	$options['play_button_opacity'] = (float) ($_GET['play_button_opacity'] / 100);
}

if ($options['play_button_opacity'] > 1) {
	$options['play_button_opacity'] = 1;
}

if (isset($_GET['play_button_width'])) {
	$options['play_button_width'] = (int) $_GET['play_button_width'];
}

if ($options['play_button_width'] < 0) {
	$options['play_button_width'] = 80;
}

if (isset($_GET['play_button_url'])) {
	$options['play_button_url'] = (string) $_GET['play_button_url'];
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
	$options['play_button_url'],
);

$play_button_original_width = $play_button->getImageWidth();
$play_button_original_height = $play_button->getImageHeight();

$play_button_size_adjustment = $options['play_button_width'] / $play_button_original_width;
$play_button_height = $play_button_original_height * $play_button_size_adjustment;

$play_button->resizeImage($options['play_button_width'], $play_button_height, null, 1);

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
