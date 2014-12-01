<?php
define('DS', DIRECTORY_SEPARATOR);
define('DEBUG', true);
define('ROOT', realpath(dirname(__FILE__).DS.'..').DS);
define('COOKIE_NAME', 'xdck');

abstract class XDSoftUser{
	abstract function login($login, $password);
	abstract function logout();
	abstract function checkAuth();
	abstract function getRoot();
	protected function makeHash ($req) {
		return md5($req['login'].$req['salt'].$req['password']);
	}
}

class BaseUser extends XDSoftUser{
	private $config = null;
	function __construct($config) {
		$this->config = $config;
	}
	function login($login, $password) {
		if ($this->config['login'] == $login and $this->config['password'] == $password) {
			setcookie(COOKIE_NAME, $_COOKIE[COOKIE_NAME] = $this->makeHash($this->config), time() +60*60*3);
			return true;
		}
		setcookie('xdck', false);
		return false;
	}
	function logout() {
		setcookie('xdck', false);
		$_COOKIE[COOKIE_NAME] = '';
	}
	function checkAuth() {
		if (isset($_COOKIE[COOKIE_NAME]) and $_COOKIE[COOKIE_NAME] === $this->makeHash($this->config)) {
			return true;
		}
		return false;
	}
	function getRoot() {
		return realpath($this->config['virtual_root']).DS;
	}
}

class BaseController {
	public $config = array(
		'login'=>'demo',
		'password'=>'demo',
		'salt' => 'ndcgh;l2;lga',
		'virtual_root' => ROOT,
		'time_format' => 'H:i:s d/m/Y',
		'default_chmod' => 0777
	);
	public $lang = array();
	protected $user = null;
	protected $phpFileUploadErrors = array(
	    0 => '{There is no error, the file uploaded with success}',
	    1 => '{The uploaded file exceeds the upload_max_filesize directive in php.ini}',
	    2 => '{The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form}',
	    3 => '{The uploaded file was only partially uploaded}',
	    4 => '{No file was uploaded}',
	    6 => '{Missing a temporary folder}',
	    7 => '{Failed to write file to disk.}',
	    8 => '{A PHP extension stopped the file upload.}',
	);
	protected  $error = 0;
	protected  $data = array();

	function __construct() {
		$this->config = array_merge($this->config, include ROOT.'assets/config.php');
		$lang = str_replace(array("lang = {\n",'};'),'',file_get_contents(ROOT.'assets/js/i18n/ru.js'));
		preg_match_all('#"([^"]+)"[\s]?:[\s]?"([^"]+)"#u', $lang, $list);
		foreach($list[1] as $i=>$key){
			$this->lang[$key] = $list[2][$i];
		};
		$this->user = new BaseUser($this->config);
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
	function encode () {
		if($this->data and isset($this->data['msg'])) {
			$msgs = is_array($this->data['msg']) ? $this->data['msg'] : array($this->data['msg']);
			if (!is_array($this->data['msg'])) {
				$this->data['msg'] = array($this->data['msg']);
			}
			foreach($this->data['msg'] as $i => $value) {
				foreach($this->lang as $lg=>$lvalue){
					$this->data['msg'][$i] = str_replace('{'.$lg.'}', $lvalue, $this->data['msg'][$i]);
				}
			}
		}
		exit(json_encode(array(
			'error' => $this->error,
			'data'	=> $this->data
		)));
	}
	protected function inRoot($path) {
		return strpos($path,$this->user->getRoot()) !== false;
	}
	protected function validatePath ($path = false) {
		$path = $path ? $path : $this->user->getRoot();
		$path = realpath($path);
		if (!$this->inRoot($path)) {
			return $this->user->getRoot();
		}
		if (!$path) {
			$this->error = 3;
			$this->data['msg'] = '{invalid path}';
			return false;
		}
		return $path;
	}
	function checkAuth () {
		if ($this->user->checkAuth()) {
			return true;
		}
		$this->error = 2;
		$this->data['msg'] = '{Please auth}';
		return false;
	}
	function getsize($dir, $deep = 0) {
		if (is_dir($dir)) {
			$totalsize=0;
			if ($dirstream = @opendir($dir)) {
				while (false !== ($filename = readdir($dirstream))) {
					if ($filename!="." && $filename!="..") {
						if (is_file($dir."/".$filename))
							$totalsize+=filesize($dir."/".$filename);

						if (is_dir($dir."/".$filename) and $deep<10)
							$totalsize+=$this->getsize($dir."/".$filename,$deep+1);
					}
				}
			}
			closedir($dirstream);
		} else {
			$totalsize+=filesize($dir);
		}
		return $totalsize;
	}
}
