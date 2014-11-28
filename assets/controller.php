<?php
define('DS', DIRECTORY_SEPARATOR);
define('DEBUG', false);
define('ROOT', realpath(dirname(__FILE__).DS.'..').DS);
define('COOKIE_NAME', 'xdck');
include_once ROOT.'assets'.DS.'base_controller.php';
if (!DEBUG) {
	error_reporting(0);
}
session_start();
class Controller extends BaseController{
	function __construct() {
		parent::__construct();
	}
	function actionLogin ($req) {
		if ($this->config['login'] == $req['login'] and $this->config['password'] == $req['password']) {
			setcookie(COOKIE_NAME, $_COOKIE[COOKIE_NAME] = $this->makeHash($this->config), time() +60*60*3);
			$this->data['msg'] = '{Autentification is succesfull!}';
			$this->error = 0;
			return true;
		}
		setcookie('xdck', false);
		$this->error = 1;
		$this->data['msg'] = '{Password or username is not correct!}';
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
			$this->data['msg'] = '{Expected file name!}';
			return false;
		}
		if (file_exists($path.DS.$name)) {
			$this->error = 7;
			$this->data['msg'] = '{File or folder with this name already exists}';
			return false;
		}
		mkdir($path.DS.$name, $this->config['default_chmod']);
		$this->data['msg'] = '{Folder} '.$name.' {was create}!';
		$this->actionGetFilesList($path);
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
							$this->data['msg'][] = '{File type not in white list}';
							continue;
						}
					}
					if (isset($this->config['black_extensions']) and count($this->config['black_extensions'])) {
						if (in_array($info['extension'], $this->config['black_extensions'])) {
							unlink($file);
							$this->data['msg'][] = '{File type in black list}';
							continue;
						}
					}
					$this->data['msg'][] = '{File} '.$_FILES['files']['name'][$i].' {was upload}';
				}
			}
			return true;
		};
		$this->error = 5;
		$this->data['msg'] = '{Files were not downloaded}';
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
			echo '{File not found}';
	  	}
	  	exit();
	}
	function actionDelete ($req) {
		$path = $this->validatePath($this->config['virtual_root'].DS.@$req['path']);
		if (!$path) {
			return false;
		}
		$file = preg_replace(array('#^[\s\n\r\t]+#u','#[\s\n\r\t]+$#u'), '', $req['file']);

		if (!empty($file) and $file!='..' and  file_exists($path.DS.$file)) {
			if (is_file($path.DS.$file)) {
				$this->data['msg'] = '{File} '.$file.' {was deleted}!';
				unlink($path.DS.$file);
			} else if (is_dir($path.DS.$file) and $this->inRoot($path.DS.$file) and realpath($path.DS.$file)!=$this->config['virtual_root']){
				$this->data['msg'] = '{Folder} '.$file.' {was Deleted}!';
				$this->cleanDirectory($path.DS.$file, true);
			}
			$this->actionGetFilesList($path);
			return true;
		}

		$this->error = 4;
		$this->data['msg'] = '{File not exists}';
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
		$cntfiles = 0;
		$cntfolders = 0;
		foreach($list as $file) {
			$info = pathinfo($file);
			if (is_file($file)) {
				$cntfiles++;
			} else {
				$cntfolders++;
			}
			$this->data['files'][] = array(
				'name' => $info['basename'],
				'type' =>
					is_dir($file) ? 'folder' :
					 (file_exists(ROOT.'assets'.DS.'images'.DS.'types'.DS.strtolower($info['extension']).'.png') ? strtolower($info['extension']) : '_blank'),
				'size'=> $this->formatBytes($this->getsize($file)),
				'time'  =>	date($this->config['time_format'], filemtime($file)),
			);
		}

		$this->data['size'] = $this->formatBytes($this->getsize($path));
		$this->data['folders_count'] = $cntfolders;
		$this->data['files_count'] = $cntfiles;
		return true;
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