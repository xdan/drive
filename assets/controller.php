<?php
include_once 'base.php';

if (!DEBUG) {
	error_reporting(0);
}

class Controller extends BaseController{
	function __construct() {
		parent::__construct();
	}

	function actionLogin ($req) {
		if ($this->user->login($req['login'], $req['password'])) {
			$this->data['msg'] = '{Autentification is succesfull!}';
			$this->error = 0;
			return true;
		}
		$this->error = 1;
		$this->data['msg'] = '{Password or username is not correct!}';
		return false;
	}

	function actionDeleteUser ($req) {
		if ($this->user->getRole() == 'admin') {
			if (isset($req['id'])) {
				$user = $this->user->get($req['id']);
			}
			if ($this->user->id==$user->id) {
				$this->error = 18;
				$this->data['msg'] = '{You can not delete youself}';
				return false;
			}
			if ($user->id === null) {
				$this->error = 11;
				$this->data['msg'] = '{User not exist}';
				return false;
			}

			$user->delete();
			return true;
		}
		$this->error = 10;
	$this->data['msg'] = '{Do not access}';
	}
	function actionSaveUser ($req) {
		if ($this->user->getRole() == 'admin') {
			if (isset($req['id'])) {
				$user = $this->user->get($req['id']);
			} else {
				$user = $this->user->get();
			}

			$user->login = $req['login'];
			$user->virtual_root = $req['virtual_root'];
			if ($req['password'])
				$user->password = $req['password'];

			$user->save();
			return true;
		}
		$this->error = 10;
		$this->data['msg'] = '{Do not access}';
	}
	function actionGetUser ($req) {
		if ($this->user->getRole() == 'admin') {
			$path = $this->config['virtual_root'];
			$uid  = intval($req['id']);

			if (!isset($this->user->users[$uid])) {
				$this->error = 11;
				$this->data['msg'] = '{User not exist}';
				return false;
			}

			$this->data['user'] = $this->user->users[$uid];
			$this->data['user']['id'] = $uid;
			$this->data['user']['virtual_root'] = str_replace($path, '', $this->data['user']['virtual_root']);
			unset($this->data['user']['password']);
			return true;
		}
		$this->error = 10;
		$this->data['msg'] = '{Do not access}';
	}
	function actionShowUsers ($req) {
		if ($this->user->getRole() == 'admin') {
			$path = $this->config['virtual_root'];
			$this->data['users'] = array_map(function($ids,$user) use ($path ){
				unset($users['password']);
				$user['id'] = $ids;
				$user['virtual_root'] = str_replace($path , '',$user['virtual_root']);
				return $user;
			},array_keys($this->user->users),$this->user->users);
			return true;
		}
		$this->error = 10;
		$this->data['msg'] = '{Do not access}';
	}

	function actionCreateFolder ($req) {
		$path = $this->validatePath($this->getRoot().@$req['path']);
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
		$path = $this->validatePath($this->getRoot().@$req['path']);
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
				if (move_uploaded_file($tmp_name, $file = $path.$_FILES['files']['name'][$i])) {
					$info = pathinfo($file);
					if (isset($this->config['white_extensions']) and count($this->config['white_extensions'])) {
						if (!in_array($info['extension'], $this->config['white_extensions'])) {
							unlink($file);
							$this->error = 5;
							$this->data['msg'][] = '{File type not in white list}';
							continue;
						}
					}
					if (isset($this->config['black_extensions']) and count($this->config['black_extensions'])) {
						if (in_array($info['extension'], $this->config['black_extensions'])) {
							unlink($file);
							$this->error = 5;
							$this->data['msg'][] = '{File type in black list}';
							continue;
						}
					}
					$this->data['msg'][] = '{File} '.$_FILES['files']['name'][$i].' {was upload}';
				} else {
					$this->error = 5;
					if (!is_writable($path)) {
						$this->data['msg'] = '{destination directory is not writeble}';
					} else
						$this->data['msg'] = '{Files were not downloaded}';
					return false;
				}
			}
			return true;
		};
		$this->error = 5;
		$this->data['msg'] = '{Files were not downloaded}';
		return false;
	}
	function actionDownload ($req) {
		$path = $this->validatePath($this->getRoot().@$req['path']);
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
			echo $this->i18n('{File not exists}');
	  	}
	  	exit();
	}
	function actionDelete ($req) {
		$path = $this->validatePath($this->getRoot().@$req['path']);
		if (!$path) {
			return false;
		}
		$file = $this->mbtrim($req['file']);

		if (!empty($file) and $file!='..' and  file_exists($path.DS.$file)) {
			if (is_file($path.DS.$file)) {
				$this->data['msg'] = '{File} '.$file.' {was deleted}!';
				unlink($path.DS.$file);
			} else if (is_dir($path.DS.$file) and $this->inRoot($path.DS.$file) and realpath($path.DS.$file)!=$this->getRoot()){
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
		$path = $this->validatePath($this->getRoot().@$req['path']);
		if (!$path) {
			$this->error = 12;
			return false;
		}
		$list = glob($path.DS.'*');

		$this->data['path'] = str_replace($this->getRoot(),'',$path);
		$this->data['files'] = array();
		if ($path!==$this->getRoot() and $this->inRoot($path)) {
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
	function checkLink($req) {
		$user = $this->user->get($req['id']);
		if (!$user) {
			return false;
		}

		$path = $this->validatePath($this->getRoot($user).@$req['path']);

		if (!$path) {
			return false;
		}

		$file = $this->mbtrim($req['file']);
		if (empty($file) or $file=='..' or  !file_exists($path.DS.$file) or !is_file($path.DS.$file)) {
			return false;
		}

		$val = $user->makeHash(array('password'=>$req['path'].$user->getId(), 'login'=>$file)) == $req['key'];
		if (!$val) {
			if (ob_get_level()) {
				ob_end_clean();
			}
			header('Error: 404');
			echo $this->i18n('{File not found}');
			exit();
		}
		return true;
	}
	function generateLink($path, $file) {
		return 'http://'.$_SERVER['HTTP_HOST'].'/assets/controller.php?action=download'.
					'&path='.rawurlencode($path).
					'&file='.rawurlencode($file).
					'&id='.rawurlencode($this->user->getId()).
					'&key='.$this->user->makeHash(array('password'=>$path.$this->user->getId(), 'login'=>$file));
	}
	function actionGetLink ($req) {
		$path = $this->validatePath($this->getRoot().@$req['path']);
		if (!$path) {
			return false;
		}
		$file = $this->mbtrim($req['file']);

		if (!empty($file) and $file!='..' and  file_exists($path.DS.$file)) {
			if (is_file($path.DS.$file)) {
				$this->data['link'] = 'http://'.$_SERVER['HTTP_HOST'].'/assets/controller.php?action=download'.
					'&path='.rawurlencode(str_replace(realpath($this->config['virtual_root']), '', $path)).
					'&file='.rawurlencode($file).
					'&id='.rawurlencode($this->user->getId()).
					'&key='.$this->user->makeHash(array('password'=>$req['path'].$this->user->getId(), 'login'=>$file));
			}
			return true;
		}

		$this->error = 4;
		$this->data['msg'] = '{File not exists}';
	}
}
$action = 'action'.strtolower($_REQUEST['action']);

$controller = new Controller();

if ($controller->checkAuth() or $action==='actionlogin' or ($action==='actiondownload' and $controller->checkLink($_REQUEST))) {
	if (method_exists($controller, $action)) {
		$controller->$action($_REQUEST);
	}
}


$controller->encode();