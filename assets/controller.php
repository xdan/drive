<?php
define('DS', DIRECTORY_SEPARATOR);
define('ROOT', realpath(dirname(__FILE__).DS.'..').DS);
define('COOKIE_NAME', 'xdck');
session_start();
class Controller {
	private $config = array(
		'login'=>'demo',
		'password'=>'demo',
		'salt' => 'ndcgh;l2;lga',
		'virtual_root' => ROOT,
		'time_format' => 'H:i:s d/m/Y',
		'default_chmod' => 0777
	);
	private $phpFileUploadErrors = array(
	    0 => 'There is no error, the file uploaded with success',
	    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
	    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
	    3 => 'The uploaded file was only partially uploaded',
	    4 => 'No file was uploaded',
	    6 => 'Missing a temporary folder',
	    7 => 'Failed to write file to disk.',
	    8 => 'A PHP extension stopped the file upload.',
	);
	public $error = 0;
	public $data = array();

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
	function makeHash () {
		return md5($req['login'].$req['salt'].$req['password']);
	}
	function encode () {
		exit(json_encode(array(
			'error' => $this->error,
			'data'	=> $this->data
		)));
	}
	function inRoot($path) {
		return strpos($path,$this->config['virtual_root']) !== false;
	}
	function validatePath ($path = false) {
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

	function actionLogin ($req) {
		if ($this->config['login'] == $req['login'] and $this->config['password'] == $req['password']) {
			setcookie(COOKIE_NAME, $_COOKIE[COOKIE_NAME] = $this->makeHash(), time() +60*60*3);
			$this->error = 0;
			return true;
		}
		setcookie('xdck', false);
		$this->error = 1;
		$this->data['msg'] = 'Not true';
		return false;
	}


	function actionCreateFolder ($req) {
		$path = $this->validatePath($this->config['virtual_root'].DS.@$req['path']);
		if (!$path) {
			return false;
		}
		$name = $this->mbtrim($req['name']);
		if (empty($name)) {
			$this->error = 6;
			$this->data['msg'] = 'Expected file name';
			return false;
		}
		if (file_exists($path.DS.$name)) {
			$this->error = 7;
			$this->data['msg'] = 'File or folder with this name already exists';
			return false;
		}
		mkdir($path.DS.$name, $this->config['default_chmod']);
	}

	function actionUpload ($req) {
		$path = $this->validatePath($this->config['virtual_root'].DS.@$req['path']);
		if (!$path) {
			return false;
		}
		if (isset($_FILES['files']) and is_array($_FILES['files']) and isset($_FILES['files']['name']) and is_array($_FILES['files']['name']) and count($_FILES['files']['name'])) {
			$this->data['msg'] = array();
			foreach ($_FILES['files']['name'] as $i=>$file) {
				if ($_FILES['files']['error'][$i]) {
					$this->data['msg'][] = isset($this->phpFileUploadErrors[$_FILES['files']['error'][$i]]) ? $this->phpFileUploadErrors[$_FILES['files']['error'][$i]] : 'Error';
					continue;
				}
				$tmp_name = $_FILES['files']['tmp_name'][$i];
				if (move_uploaded_file($tmp_name, $file = $path.DS.$_FILES['files']['name'][$i])) {
					$info = pathinfo($file);
					if (isset($this->config['white_extensions']) and count($this->config['white_extensions'])) {
						if (!in_array($info['extension'], $this->config['white_extensions'])) {
							unlink($file);
							$this->data['msg'][] = 'File type not in white list';
						}
					}
					if (isset($this->config['black_extensions']) and count($this->config['black_extensions'])) {
						if (in_array($info['extension'], $this->config['black_extensions'])) {
							unlink($file);
							$this->data['msg'][] = 'File type in black list';
						}
					}
				}
			}
			return true;
		};
		$this->error = 5;
		$this->data['msg'] = 'Files did not upload';
	}
	function actionDownload ($req) {
		$path = $this->validatePath($this->config['virtual_root'].DS.@$req['path']);
		if (!$path) {
			return false;
		}
		$file = $this->mbtrim($req['file']);
		if (!empty($file) and file_exists($path.DS.$file) and is_file($path.DS.$file)) {
			$file = $path.DS.$file;
			if (ob_get_level()) {
				ob_end_clean();
			}
			// заставляем браузер показать окно сохранения файла
			header('Content-Description: File Transfer');
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: attachment; filename=' . basename($file));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($file));
			// читаем файл и отправляем его пользователю
			if ($fd = fopen($file, 'rb')) {
				while (!feof($fd)) {
					print fread($fd, 1024);
				}
				fclose($fd);
			}
	  	} else {
	  		header('Error: 404');
			echo 'File not found';
	  	}
	  	exit();
	}
	function actionDelete ($req) {
		$path = $this->validatePath($this->config['virtual_root'].DS.@$req['path']);
		if (!$path) {
			return false;
		}
		$file = preg_replace(array('#^[\s\n\r\t]+#u','#[\s\n\r\t]+$#u'), '', $req['file']);
		if (!empty($file) and file_exists($path.DS.$file)) {
			if (is_file($path.DS.$file)) {
				unlink($path.DS.$file);
			} else if (is_dir($path.DS.$file)){
				$this->cleanDirectory($path.DS.$file, true);
			}
			return true;
		}

		$this->error = 4;
		$this->data['msg'] = 'File not exists';
	}
	function actionGetFilesList ($req) {
		$path = $this->validatePath($this->config['virtual_root'].DS.@$req['path']);
		if (!$path) {
			return false;
		}
		$list = glob($path.DS.'*');
		$this->data['path'] = str_replace($this->config['virtual_root'],'',$path);
		$this->data['files'] = array();
		if ($path!==$this->config['virtual_root'] and $this->inRoot($path)) {
			$this->data['files'][] = array(
				'name' => '..',
				'type' => 'folder',
				'size'=> 0,
				'time'  =>	0,
			);
		}
		foreach($list as $file) {
			$info = pathinfo($file);
			$this->data['files'][] = array(
				'name' => $info['basename'],
				'type' =>
					is_dir($file) ? 'folder' :
					 (file_exists(ROOT.'assets'.DS.'images'.DS.'types'.DS.strtolower($info['extension']).'.png') ? strtolower($info['extension']) : '_blank'),
				'size'=> $this->formatBytes(filesize($file)),
				'time'  =>	date($this->config['time_format'], filemtime($file)),
			);
		}
		return true;
	}


	function actionUploadImage(){
		require(ROOT.'assets/UploadHandler.php');
		$upload_handler = new UploadHandler(null,false);
		$data = $upload_handler->post(false);
		$images = array();
		foreach($data['images'] as $i=>$image)
			$images[] = array('image'=>$image->name,'error'=>$image->error);
		$this->data = $images;
	}
}

$action = 'action'.strtolower($_REQUEST['action']);

$controller = new Controller();

if ($controller->checkAuth() or $action==='actionlogin') {
	if (method_exists($controller, $action)) {
		$controller->$action($_REQUEST);
	}
}


$controller->encode();