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
	abstract function getRole ();
	abstract function save();
}

class User extends XDSoftUser{
	public $users = null;
	public $data = null;
	public $auth = false;
	public $cookie_time = 10000;
	function __get($name) {
		return isset($this->data[$name]) ? $this->data[$name] : null;
	}
	function __set($name, $value) {
		$this->data[$name] = $value;
	}
	private function generateSalt ($length = 8){
		$password = "";
		$possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";
		$maxlength = strlen($possible);
		if ($length > $maxlength) {
			$length = $maxlength;
		}
		$i = 0;
		while ($i < $length) {
			$char = substr($possible, mt_rand(0, $maxlength-1), 1);
			if (!strstr($password, $char)) {
				$password .= $char;
				$i++;
			}
		}
		return $password;
	}
	function __construct() {
		$this->users = include 'config.users.php';
	}
	function delete() {
		if ($this->id!==null) {
			unset($this->users[$this->id]);
		}
		file_put_contents('config.users.php', '<?php return '.var_export($this->users, true).';');
	}
	function save() {
		$user = array();

		if ($this->id!==null && $this->users[$this->id]) {
			$user = &$this->users[$this->id];
		} else {
			$this->users[] = $user;
			$user = &$this->users[count($this->users)-1];
		}

		$user['login'] = $this->login;
		$user['virtual_root'] = $this->virtual_root;
		if ($this->password!==null)
			$user['password'] = $this->password;
		$user['salt'] = $this->generateSalt();

		file_put_contents('config.users.php', '<?php return '.var_export($this->users, true).';');
	}
	function login($login, $password) {
		foreach($this->users as $id=>$user) {
			if ($user['login'] == $login and $user['password'] == $password) {
				$this->data = $user;
				$this->id = $id;
				$this->salt = $user['salt'];
				setcookie(COOKIE_NAME, $_COOKIE[COOKIE_NAME] = $this->makeHash($user), time() + $this->cookie_time);
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
		if (isset($_COOKIE[COOKIE_NAME])) {
			foreach($this->users as $id=>$user) {
				$this->data = $user;
				$this->id = $id;
				$this->salt = $user['salt'];
				if (isset($_COOKIE[COOKIE_NAME]) and $_COOKIE[COOKIE_NAME] === $this->makeHash($user)) {
					$this->auth = true;
					return true;
				}
			}
		}
		return false;
	}
	function getRoot() {
		if ($this->id!==null) {
			return $this->users[$this->id]['virtual_root'].DS;
		} else {
			return '';
		}
	}
	function makeHash ($req) {
		return md5($req['login'].$this->salt.$req['password']);
	}
	function getLogin() {
		return $this->data['login'];
	}
	function getRole() {
		return $this->data['role'] ? $this->data['role'] : 'user';
	}
	function getId() {
		return intval($this->id);
	}
	function get($userid = null) {
		$classname = __CLASS__;
		$user = new $classname();

		if (isset($this->users[$userid])) {
			$user->data = $this->users[$userid];
			$user->id = intval($userid);
		}

		return $user;
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
		$this->user = new User();
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
		if (!DEBUG){
			ob_clean();
		}
		//header('Content-Type: application/json');
		exit(json_encode(array(
			'error' => $this->error,
			'data'	=> $this->data
		)));
	}
	protected function getRoot($user = false) {
		return realpath($this->config['virtual_root'].($user ? $user->getRoot() : $this->user->getRoot())).DS;
	}
	protected function inRoot($path) {
	//	echo $path,$this->getRoot();
		return strpos($path,$this->getRoot()) !== false;
	}
	protected function validatePath ($path = false) {
		$path = $path ? $path : $this->getRoot();
		$path = realpath($path).DS;
		if (!$this->inRoot($path)) {
			return $this->getRoot();
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
			$this->data['role'] = $this->user->getRole();
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
