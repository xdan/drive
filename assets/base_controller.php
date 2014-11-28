<?php
class BaseController {
	protected $config = array(
		'login'=>'demo',
		'password'=>'demo',
		'salt' => 'ndcgh;l2;lga',
		'virtual_root' => ROOT,
		'time_format' => 'H:i:s d/m/Y',
		'default_chmod' => 0777
	);
	protected $phpFileUploadErrors = array(
	    0 => 'There is no error, the file uploaded with success',
	    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
	    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
	    3 => 'The uploaded file was only partially uploaded',
	    4 => 'No file was uploaded',
	    6 => 'Missing a temporary folder',
	    7 => 'Failed to write file to disk.',
	    8 => 'A PHP extension stopped the file upload.',
	);
	protected  $error = 0;
	protected  $data = array();

	function __construct() {
		$this->config = array_merge($this->config, include ROOT.'assets/config.php');
		$this->config['virtual_root'] = realpath($this->config['virtual_root']).DS;
	}
	function cleanDirectory($dir, $remove = false) {
	    if ($objs = glob($dir."/*")) {
	        foreach($objs as $obj) {
	            is_dir($obj) ? $this->cleanDirectory($obj, true) : unlink($obj);
	        }
	    }
	    if ($remove) {
	        rmdir($dir);
	    }
	}
	function mbtrim($str) {
		return preg_replace(array('#^[\s\n\r\t]+#u','#[\s\n\r\t]+$#u'), '', $str);
	}
	function formatBytes($size, $precision = 2){
	    $base = log($size) / log(1024);
	    $suffixes = array('', 'k', 'M', 'G', 'T');

	    return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
	}
	protected function makeHash () {
		return md5($req['login'].$req['salt'].$req['password']);
	}
	function encode () {
		exit(json_encode(array(
			'error' => $this->error,
			'data'	=> $this->data
		)));
	}
	protected function inRoot($path) {
		return strpos($path,$this->config['virtual_root']) !== false;
	}
	protected function validatePath ($path = false) {
		$path = $path ? $path : $this->config['virtual_root'];
		$path = realpath($path);
		if (!$this->inRoot($path)) {
			return $this->config['virtual_root'];
		}
		if (!$path) {
			$this->error = 3;
			$this->data['msg'] = 'invalid path';
			return false;
		}
		return $path;
	}
	function checkAuth () {
		if (isset($_COOKIE[COOKIE_NAME]) and $_COOKIE[COOKIE_NAME] === $this->makeHash()) {
			return true;
		}
		$this->error = 2;
		$this->data['msg'] = 'Please auth';
		return false;
	}
}
