<?php

class SmushIt
{
	const SERVICE_API_URL
		= "http://www.smushit.com/ysmush.it/ws.php";

	const SERVICE_API_LIMIT
		= 1048576; // 1MB limitation

	private static $extensions
		= array('jpg', 'jpeg', 'png', 'gif');

	public $error;

	public $source;

	public $destination;

	public $sourceSize;

	public $destinationSize;

	public $savings;

	private $items = array();

	public function __construct($path)
	{
		if (empty($path)) {
			throw new InvalidArgumentException(
				'In SmushIt::__construct(): parameter can\'t be empty'
			);
		}

		if (is_array($path)) {
			array_map(function($location) {
				$smushit = new SmushIt($location);
				array_map(function($single) {
					$this->items[] = $single;
				}, $smushit->get());
			}, $path);
		} else if (is_string($path)) {
			$this->smush($path);
		}
	}

	public function get()
	{
		return $this->items;
	}

	private function smush($path)
	{
		$isRemote = filter_var($path, FILTER_VALIDATE_URL) !== false;
		$isLocal = (!$isRemote AND file_exists($path) AND !is_dir($path));

		if (!$isLocal AND !$isRemote) {
			$this->error = "$path is not a valid path";
			return;
		} else if ($isLocal AND !is_readable($path)) {
			$this->error = "$path is not readable";
		} else {
			$handle = curl_init();
			curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);

			if ($isRemote) {
				curl_setopt($handle, CURLOPT_URL, self::SERVICE_API_URL . '?img=' . $path);
			} else {
				curl_setopt($handle, CURLOPT_POST, true);
				curl_setopt($handle, CURLOPT_POSTFIELDS, array('files' => '@' . $path));
			}

			$this->source = $path;
			$json = curl_exec($handle);
			curl_close($handle);
			$this->set($json);
		}

		$this->items[] = $this;
	}

	private function set($json)
	{
		try {
			$response = json_decode($json);
			if (empty($response)) {
				throw new Exception('Empty JSON response');
			}
		} catch(Exception $e) {
			$this->error = 'An error occured during JSON deserialization: ' . $e->getMessage();
			return;
		}

		$this->error = empty($response->error) ? null : $response->error;
		$this->source = empty($response->src) ? $this->source : urldecode($response->src);
		$this->destination = empty($response->dest) ? null : $response->dest;
		$this->sourceSize = empty($response->src_size) ? null : intval($response->src_size);
		$this->destinationSize = empty($response->dest_size) ? null : intval($response->dest_size);
		$this->savings = empty($response->percent) ? null : floatval($response->percent);
	}
}

// $j = new SmushIt(array(
// 	'http://freedomwallpaper.com/wallpaper_hd/apple_wallpaper.jpg',
// 	'http://mintyferret.com/wp-content/uploads/2007/07/lolcat7.gif'
// 	// 'http://ysmushit.zenfs.com/results/4e0c2a9a%2Fsmush%2Fgoogle-buzz-e1265748634462.jpg'
// ));
// var_dump($j->get());
