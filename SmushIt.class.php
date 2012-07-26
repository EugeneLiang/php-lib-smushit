<?php

class SmushIt
{
	const KEEP_ERRORS
		= 0x01;

	const THROW_EXCEPTION
		= 0x02;

	const LOCAL_ORIGIN
		= 0x04;

	const REMOTE_ORIGIN
		= 0x08;

	const SERVICE_API_URL
		= "http://www.smushit.com/ysmush.it/ws.php";

	const SERVICE_API_LIMIT
		= 1048576; // 1MB limitation

	public $error;

	public $source;

	public $destination;

	public $sourceSize;

	public $destinationSize;

	public $savings;

	private $flags = null;

	private $items = array();

	public function __construct($sources, $flags = null)
	{
		$this->flags = $flags;
		$sources = $this->clean($sources);

		if (is_string($sources)) {
			if ($this->check($sources)) {
				$this->smush();
			}
		} else {
			foreach($sources as $source) {
				$smush = new SmushIt($source, $flags);
				$smushResult = $smush->get();
				if (!empty($smushResult)) {
					$this->items[] = $smushResult;
				}
			}
		}
	}

	public function get()
	{
		return $this->items;
	}

	private function clean($sources)
	{
		if (is_array($sources)) {
			$clean = array();
			array_walk_recursive($sources, function($line) use (&$clean) {
				$clean[] = $line;
			});
			$sources = array_filter(array_map(function($line) {
				if (!empty($line) AND is_string($line)) {
					return $line;
				}
			}, array_unique($clean)));
		} else if (!is_string($sources)) {
			$sources = null;
		}

		if (empty($sources) AND $this->hasFlag(self::THROW_EXCEPTION)) {
			throw new InvalidArgumentException('Sources can\'t be empty');
		}

		return $sources;
	}

	private function check($path)
	{
		if ($this->setSource($path) === false) {
			$this->error = "$path is not a valid path";
		} else if ($this->hasFlag(self::LOCAL_ORIGIN)) {
			if (!is_readable($path)) {
				$this->error = "$path is not readable";
			} else if(filesize($path) > self::SERVICE_API_LIMIT) {
				$this->error = "$path exceeds 1MB size limit";
			}
		}

		if (!empty($this->error)) {
			if ($this->hasFlag(self::THROW_EXCEPTION)) {
				throw new Exception($this->error);
			}
			return false;
		}

		return true;
	}

	private function hasFlag($flag)
	{
		return (bool)($this->flags & $flag);
	}

	private function setSource($source)
	{
		$this->source = $source;
		if (filter_var($this->source, FILTER_VALIDATE_URL) !== false) {
			$this->flags |= self::REMOTE_ORIGIN;
		} else if (file_exists($this->source) AND !is_dir($this->source)) {
			$this->flags |= self::LOCAL_ORIGIN;
		} else {
			return false;
		}
	}

	private function smush()
	{
		$handle = curl_init();
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		if ($this->hasFlag(self::LOCAL_ORIGIN)) {
			curl_setopt($handle, CURLOPT_URL, self::SERVICE_API_URL);
			curl_setopt($handle, CURLOPT_POST, true);
			curl_setopt($handle, CURLOPT_POSTFIELDS, array('files' => '@' . $this->source));
		} else {
			curl_setopt($handle, CURLOPT_URL, self::SERVICE_API_URL . '?img=' . $this->source);
		}
		$json = curl_exec($handle);
		if ($json === false) {
			if (self::hasFlag(self::THROW_EXCEPTION)) {
				throw new Exception('Curl error: ' . curl_error());
			}
			return;
		}
		$this->set($json);
	}

	private function set($json)
	{
		$response = json_decode($json);
		if (empty($response)) {
			if (self::hasFlag(self::THROW_EXCEPTION)) {
				throw new Exception('Empty JSON response');
			}
			return;
		}
		$this->error = empty($response->error) ? $this->error : $response->error;
		$this->destination = empty($response->dest) ? null : $response->dest;
		$this->sourceSize = empty($response->src_size) ? null : intval($response->src_size);
		$this->destinationSize = empty($response->dest_size) ? null : intval($response->dest_size);
		$this->savings = empty($response->percent) ? null : floatval($response->percent);

		if (!empty($this->error) AND $this->hasFlag(self::THROW_EXCEPTION)) {
			throw new Exception($this->error);
		} else if (empty($this->error) OR $this->hasFlag(self::KEEP_ERRORS)) {
			$this->items[] = $this;
		}
	}
}
