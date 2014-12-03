<?php
return array(
	'users' => array(
		array(
			'login'=>'demo',
			'password'=>'demo',
			'virtual_root' => ROOT.'tmp'.DS.'demo1'.DS,
			'salt'=> 'ndcgh;l2;lga',
		),
		array(
			'login'=>'demo2',
			'password'=>'demo',
			'virtual_root' => ROOT.'tmp'.DS.'demo2'.DS,
			'salt'=> 'ndcgh;l2;lga',
		),
	),
	'virtual_root' => ROOT.'tmp'.DS,
	'time_format' => 'H:i:s d/m/Y',
	'white_extensions' => array(),
	'black_extensions' => array('php', 'exe', 'js'),
	'default_chmod' => 0777,
	'cookie_time' => 10000,
);