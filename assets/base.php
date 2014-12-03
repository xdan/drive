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
	abstract function makeHash ($req);
	abstract function getLogin ();
	abstract function getId ();
}

class User extends XDSoftUser{
	private $config = null;
	private $salt = null;
	private $id = null;
	private $data = null;
	public $auth = false;

	function __construct($config) {
		$this->config = $config;
	}
	function login($login, $password) {
		foreach($this->config['users'] as $id=>$user) {
			if ($user['login'] == $login and $user['password'] == $password) {
				$this->id = $id;
				$this->salt = $user['salt'];
				$this->data = $user;
				setcookie(COOKIE_NAME, $_COOKIE[COOKIE_NAME] = $this->makeHash($user), time() + $this->config['cookie_time']);
				$this->auth = true;
				return true;
			}
		}

		setcookie('xdck', false);
		return false;
	}
	function logout() {
		setcookie('xdck', false);
		$_COOKIE[COOKIE_NAME] = '';
		$this->auth = false;
	}
	function checkAuth() {
		foreach($this->config['users'] as $id=>$user) {
			$this->id = $id;
			$this->salt = $user['salt'];
			if (isset($_COOKIE[COOKIE_NAME]) and $_COOKIE[COOKIE_NAME] === $this->makeHash($user)) {
				$this->auth = true;
				return true;
			}
		}
		return false;
	}
	function getRoot() {
		if ($this->auth) {
			return realpath($this->config['users'][$this->id]['virtual_root']).DS;
		} else {
			return realpath($this->config['virtual_root']).DS;
		}
	}
	function makeHash ($req) {
		return md5($req['login'].$this->salt.$req['password']);
	}
	function getLogin() {
		return $this->data['login'];
	}
	function getId() {
		return $this->id;
	}
	function get($userid) {
		$classname = __CLASS__;
		return new $classname($this->config);
	}
}

class BaseController {
	public $config = array();
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
		$this->user = new User($this->config);
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

	function i18n($str) {
		foreach($this->lang as $lg=>$lvalue){
			$str = str_replace('{'.$lg.'}', $lvalue, $str);
		}
		return $str;
	}

	function encode () {
		if($this->data and isset($this->data['msg'])) {
			$msgs = is_array($this->data['msg']) ? $this->data['msg'] : array($this->data['msg']);
			if (!is_array($this->data['msg'])) {
				$this->data['msg'] = array($this->data['msg']);
			}
			foreach($this->data['msg'] as $i => $value) {
				$this->data['msg'][$i] = $this->i18n($value);
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
