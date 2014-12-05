(function($){
	function getParam (name, location) {
		location = location || window.location.search;
		var res = location.match(new RegExp('[#&?]' + name + '=([^&]*)', 'i'));
		return (res && res[1] ? decodeURIComponent(res[1]) : false);
	};

	var ARROWLEFT = 37,
		ARROWRIGHT = 39,
		ARROWUP = 38,
		ARROWDOWN = 40,
		TAB = 9,
		CTRLKEY = 17,
		SHIFTKEY = 16,
		DEL = 46,
		ENTER = 13,
		ESC = 27,
		BACKSPACE = 8,
		AKEY = 65,
		CKEY = 67,
		VKEY = 86,
		ZKEY = 90,
		YKEY = 89;

	var Drive = function () {
		var explorer = null,
			menu = null,
			login = null,
			path = null,
			files_box = null,
			progress_bar = null,
			popaper = null,
			status_line = null,
			i = 0,
			status = 'Size:{size} files:{files_count} foldes:{folders_count}',

			popaptpl = '<div class="error{error}">{msg}<a>&times;</a></div>',

			menu_tpl = '<div class="xdsoft_context_menu xdsoft_shadow">'+
					'<a  href="javascript:void(0)" class="delete">'+lang['delete']+'</a>'+
					'<a  href="javascript:void(0)" class="download">'+lang.download+'</a>'+
					'<a  href="javascript:void(0)" class="createFolder">'+lang.createFolder+'</a>'+
					'<a  href="javascript:void(0)" class="upload">'+
						'<form class="xdsoft_upload_form" method="POST" enctype="multipart/form-data">'+
							'<input type="file" name="files[]" multiple=""/>'+
						'</form>'+lang.upload+
					'</a>'+
					'<a  href="javascript:void(0)" class="getLink">'+lang.getLink+'</a>'+
				'</div>',

			login_tpl = '<div class="xdsoft_login">'+
					'<div class="xdsoft_login_form xdsoft_shadow">'+
						'<div><input autofocus type="text" class="login" placeholder="'+lang.Enter_login+'"/></div>'+
						'<div><input type="password" class="password" placeholder="'+lang.Enter_password+'"/></div>'+
						'<div><input type="button" class="go btn btn-warning" value="'+lang['Sign In']+'"/></div>'+
					'</div>'+
				'</div>',

			template = '<div data-type="{type}" data-name="{name_decode}" class="xdsoft_file {type}">'+
				'<div class="xdsoft_preview"><img src="assets/images/types/{type}.png"></div>'+
				'<div class="xdsoft_filename">{name}</div>'+
				'<div class="xdsoft_filesize">{size}</div>'+
				'<div class="xdsoft_timechange">{time}</div>'+
				'<div class="xdsoft_hint xdsoft_shadow">'+
					'<div class="xdsoft_hint_line">Size: {size}</div>'+
					'<div class="xdsoft_hint_line">Last change:<br>{time}</div>'+
				'</div>'+
			'</div>',

			worker = '<div class="xdsoft_file xdsoft_upload">'+
					'<form class="xdsoft_upload_form" method="POST" enctype="multipart/form-data">'+
						'<input multiple="" type="file" name="files[]" class="work_uploader"/>'+
					'</form>'+
					'<a href="javascript:void(0)">+</a>'+
				'</div>'+
				'<div class="xdsoft_clearex"></div>',

			popap = function (msgs, error) {
				if (!$.isArray(msgs)) {
					msgs = [msgs];
				}
				var div = [];
				for(i=0;i<msgs.length;i+=1) {
					div.push($(popaptpl
						.replace('{msg}', msgs[i])
						.replace('{error}', error)
					));
				}
				setTimeout(function() {
					$(div).each(function(){
						this.fadeOut(1000, function() {
							$(this).remove();
						})
					})
				}, 3000);
				popaper
					.append(div)
			},

			sortFiles = function (files) {
				var sorter = [];
				for (i=0; i<files.length; i+=1) {
					if(files[i].type == 'folder') {
						sorter.push(files[i])
					}
				}
				for (i=0; i<files.length; i+=1) {
					if(files[i].type != 'folder') {
						sorter.push(files[i])
					}
				}
				return sorter;
			},

			checkAuth = function (resp) {
				if (resp.error === 2 || resp.error === 1) {
					login
						.show();
					if (resp.error === 1) {
						login
							.addClass('error');
					}
					files_box
						.empty();
					return false;
				}
				login
					.hide();
				return true;
			},
			ekran = function (str) {
				return str!==undefined ? str.toString().replace(/[&<>"']/g, function (match) {return '&#' + match.charCodeAt(0) + ';';}) : '';
			},

			buildData = function (data) {
				if (window.FormData !== undefined) {
					if (data instanceof FormData) {
						return data;
					}
					if ($.type(data) == 'string') {
						return data;
					}
					var _data = new FormData(),
						key;
					for(key in data) {
						if (data.hasOwnProperty(key)) {
							_data.append(key,data[key])
						}
					}
					return _data;
				} else {
					return data;
				}
			},
			send = function (action, data, callback, callback1) {
				$.ajax({
					xhr: function(){
						var xhr = new window.XMLHttpRequest();
						if (window.FormData !== undefined) {
							xhr.upload.addEventListener("progress", function(evt){
								if (evt.lengthComputable) {
									var percentComplete = evt.loaded / evt.total;
									percentComplete=parseInt(percentComplete*100);
									progress_bar
										.show()
										.css('width',percentComplete+'%');
									if (percentComplete === 100) {
										progress_bar
											.hide()
									}
								}
							}, false);
						} else {
							progress_bar
								.hide()
						}
						return xhr;
					},
					type: 'POST',
					enctype: (window.FormData !== undefined && $.type(data) !== 'string') ? 'multipart/form-data' : 'application/x-www-form-urlencoded',
					data: buildData( data ),
					url: './assets/controller.php?action='+action,
					cache: false,
					contentType: (window.FormData !== undefined && $.type(data) !== 'string')? false : 'application/x-www-form-urlencoded; charset=UTF-8',
					processData: window.FormData === undefined || $.type(data) === 'string',
					dataType:'json',
					error: function(resp){
						console.log(resp);
					},
					success: function(resp){
						if (resp.data&&resp.data.msg) {
							popap(resp.data.msg, resp.error);
						}
						if (checkAuth(resp)) {

							if (resp.data && resp.data.role && resp.data.role=='admin') {
								explorer.find('.xdsoft_users').show();
							} else {
								explorer.find('.xdsoft_users').hide();
							}

							callback && callback(resp);
						} else {
							callback1 && callback1(resp);
						}
					}
				});
			},
			sendFiles = function (files, form) {
				if (window.FormData !== undefined) {
					var len = files.length;
					if(!len) {
						return false;
					}
					var form = new FormData();

					form.append("path", path);

					for (i = 0; i < len; i++) {
						form.append("files["+i+"]", files[i]);
					}

					send('upload', form, function(resp) {
						methods.updateFilesList(path);
					})
				} else {
					var iframeId = 'unique' + (new Date().getTime());
					var iframe = $('<iframe src="javascript:false;" name="'+iframeId+'" />');
					iframe.hide();
					form.attr('target',iframeId);
					form.attr('action','./assets/controller.php?action=upload');
					iframe.appendTo('body');
					iframe.load(function(e){
						methods.updateFilesList(path);
					});
					form.submit();
				}
			},
			render = function (_files) {
				var out = '',
					files = sortFiles(_files);
				for(i = 0; i < files.length ;i += 1) {
					out+=template
						.replace(/{time}/g, files[i].time || '')
						.replace(/{size}/g, files[i].size || '')
						.replace(/{name}/g, files[i].name || '')
						.replace(/{type}/g, files[i].type || '')
						.replace(/{name_decode}/g, encodeURIComponent(files[i].name))
				}
				if (files_box.hasClass('table_layout')){
					files_box.html(out);
				} else {
					files_box.html(out+worker);
				}
			},
			updateStatus = function (resp) {
				status_line.text(
					status
						.replace('{size}', resp.data.size)
						.replace('{files_count}', resp.data.files_count)
						.replace('{folders_count}', resp.data.folders_count)
				);
			},
			methods = {
				addUser: function (id, login, path, title) {
					var dialog = $('<form class="xdsoft_user_form">'+
						'<table class="xdsoft_like_form">'+
							 '<tr>'+
								'<td>Логин</td>'+
								'<td><input name="login" type="text" value="'+ekran(login)+'"></td>'+
							 '</tr>'+
							 '<tr>'+
								'<td>Путь</td>'+
								'<td><input name="virtual_root" type="text" value="'+ekran(path)+'"></td>'+
							 '</tr>'+
							 '<tr>'+
								'<td>Пароль</td>'+
								'<td>'+
									'<input name="password" type="text" value="">'+
									'<input name="id" type="hidden" value="'+ekran(id)+'">'+
								'</td>'+
							 '</tr>'+
						'</table>'+
					'</form>')
						.dialog({
							title: title,
							onAfterHide: methods.showUsers,
							buttons: {
								'Сохранить': function() {
									var data = this.find('.xdsoft_user_form').serialize()
									send('saveUser',data, function (resp) {
										if (resp.error === 0) {
											dialog.dialog('hide');
										}
									})
									return false;
								},
								'Отмена': true,
							}
						});
				},
				deleteUser: function (id) {
					send('deleteUser', {id: id}, function (resp) {
						if (resp.error === 0) {
							methods.showUsers();
						}
					});
				},
				editUser: function (id) {
					send('getUser', {id: id}, function (resp) {
						if (resp.error === 0 && resp.data && resp.data.user) {
							methods.addUser(
								resp.data.user['id'],
								resp.data.user['login'],
								resp.data.user['virtual_root'],
								'Редактирование пользователя'
							);
						}
					});
				},
				showUsersDialog : null,
				showUsers: function () {
					send('showUsers', {}, function (resp) {
						if (methods.showUsersDialog) {
							methods.showUsersDialog.dialog('hide')
						}
						if (resp.error === 0 && resp.data && resp.data.users && resp.data.users.length) {
							var out='<thead><tr>'+
										'<th>Логин</th>'+
										'<th>Путь доступа</th>'+
										'<th>Роль</th>'+
										'<th>Управление</th>'+
									'</tr></thead>', r;
							for(var r in resp.data.users) {
								if (resp.data.users.hasOwnProperty(r)) {
									out+= '<tr data-id="'+resp.data.users[r].id+'">'+
										'<td>'+resp.data.users[r].login+'</td>'+
										'<td>'+resp.data.users[r].virtual_root+'</td>'+
										'<td>'+(resp.data.users[r].role ? resp.data.users[r].role : 'user')+'</td>'+
										'<td class="xdsoft_crud">'+
											'<a class="xdsoft_edit"><img src="assets/images/edit.svg"></a>'+
											'<a class="xdsoft_delete"><img src="assets/images/delete.svg"></a>'+
										'</td>'+
									'</tr>';
								}
							}
							methods.showUsersDialog = $('<table class="xdsoft_like_table"><tbody>'+out+'</tbody></table>').dialog({
								title: 'Пользователи <a title="Добавить пользователя" class="xdsoft_add">'+
									'<img style="vertical-align:middle; width:16px;" src="assets/images/add.svg">'+
								'</a>',
								onBeforeShow: function (options) {
									this.find('.xdsoft_add').on('click', function() {
										methods.addUser();
									});
									this.find('.xdsoft_crud a').on('click', function() {
										switch(this.className.replace('xdsoft_', '')) {
											case 'edit':
												methods.editUser($(this).closest('tr').data('id'))
												methods.showUsersDialog.dialog('hide');
											break;
											case 'delete':
												jConfirm(lang['Are you shure?'], function() {
													methods.deleteUser($(this).closest('tr').data('id'))
												})
											break;
										}

									})
								},
								buttons: {
									'Ok':true,
									'Cancle':true
								}
							});

						}
					})
				},
				'delete': function (file) {
					var name = decodeURIComponent(file.data('name'));
					if (name!='..') {
						jConfirm(lang['Are you shure?'], function() {
							send('delete', {path: path, file: name}, function(resp) {
								if (resp.error === 0) {
									file.remove();
									updateStatus(resp);
								}
							})
						});
					}
				},
				download: function (file) {
					location = './assets/controller.php?action=download&path='+encodeURIComponent(path)+'&file='+file.data('name');
				},
				updateFilesList: function (_path) {
					send('getFilesList', {path:_path!==undefined? _path : ''}, function(resp) {
						if (resp.error!==3) {
							if (path!==resp.data.path) {
								path = resp.data.path;
								History.pushState({path:path}, path, "?path="+encodeURIComponent(path));
							}
							render(resp.data.files);
							updateStatus(resp);
						}
					})
				},
				getLink: function (file) {
					var name = decodeURIComponent(file.data('name'));
					if (name!='..') {
						send('getLink', {path: path, file: name}, function(resp) {
							if (resp.error === 0) {
								jPrompt(lang.getLinkMessage, resp.data.link);
							}
						})
					}

				},
				createFolder: function () {
					//var dirname = prompt(lang['Enter folder name']);
					jPrompt(lang['Enter folder name'], '' , function (event, dirname) {
						if (dirname) {
							if (files_box.find('.xdsoft_file.active').data('type') == 'folder') {
								path = path+'/'+decodeURIComponent(files_box.find('.xdsoft_file.active').data('name'));
							}
							send('createFolder', {path:path, name:dirname}, function (resp) {
								methods.updateFilesList(path)
								updateStatus(resp);
							});
						}
					});
				},
				login: function (lg, ps) {
					send(
						'login',
						{
							login: lg,
							password: ps,
						},
						function (resp) {
							if (!resp.error) {
								methods.updateFilesList(path)
							} else {
								login
									.addClass('error');
							}
						}
					);
				}
			};

		this.init = function(box) {
			explorer = $(box);

			menu = $(menu_tpl);

			explorer.append(menu);

			login = $(login_tpl);

			explorer.append(login);
			files_box = explorer.find('.xdsoft_files').eq(0);
			progress_bar = explorer.find('.xdsoft_progressbar>div').eq(0);
			popaper = explorer.find('.xdsoft_popap_box').eq(0);
			status_line = explorer.find('.xdsoft_status_line').eq(0);

			menu
				.on('mousedown','a', function(event) {
					if (methods[$(this).attr('class')]) {
						menu.hide();
						methods[$(this).attr('class')](files_box.find('.xdsoft_file.active'));
					}
				});

			popaper
				.on('mousedown','a', function(event) {
					$(this).parent().fadeOut(500);
				});

			explorer
				.find('a.xdsoft_exit')
					.on('click', function() {
						methods.login('','');
						login.find('input[type=text],input[type=password]').val('');
					})
			explorer
				.find('a.xdsoft_users')
					.on('click', function() {
						methods.showUsers();
					})
			explorer
				.on('keydown', function(event) {
					var key = event.which,
						active = files_box.find('.xdsoft_file.active').eq(0),
						stop = false;

					if (!active.length) {
						active = files_box.find('.xdsoft_file').eq(0);
						active.trigger('mousedown');
					}
					switch (key) {
					 	case ARROWUP:
					 	case ARROWLEFT:
							active.prev().trigger('mousedown');
							stop = true;
					 	break;
					 	case ARROWDOWN:
					 	case ARROWRIGHT:
					 	case TAB:
							active.next().trigger('mousedown');
							stop = true;
					 	break;
					 	case ENTER:
							active.trigger('dblclick');
							stop = true;
					 	break;
					 	case BACKSPACE:
							files_box.find('.xdsoft_file').eq(0).trigger('dblclick');
							stop = true;
					 	break;
						case DEL:
							methods['delete'](active);
							stop = true;
					 	break;
					}

					event.stopPropagation();
					if (stop) {
						event.preventDefault();
						return false;
					}
				})
				.on('contextmenu','.xdsoft_file:not(.xdsoft_upload)', function(event) {
					menu.find('.download,.getLink')[$(this).data('type')!='folder' ? 'show' : 'hide']();
					menu.find('.delete').show();

					menu
						.css({
							left: event.clientX,
							top: event.clientY
						})
						.show();

					event.preventDefault();
					event.stopPropagation();
					return false;
				})
				.on('contextmenu', function(event) {

					menu.find('.download,.getLink').hide();
					menu.find('.delete').hide();

					menu
						.css({
							left: event.clientX,
							top: event.clientY
						})
						.show();

					event.preventDefault();
					event.stopPropagation();
					return false;
				})
				.on('mousedown', function(event) {
					explorer
						.addClass('active');
					event.preventDefault();
					event.stopPropagation();
					return false;
				})
				.on('mouseup outthis', function(event) {
					menu
						.hide()
					event.preventDefault();
					event.stopPropagation();
					return false;
				})
				.on('change', 'input[type="file"]', function () {
					sendFiles(this.files, $(this).closest('form'));
				})
				.on("dragover", function(event) {
					$(this).addClass('draghover')
					event.preventDefault();
				})
				.on("dragleave dragend", function(event) {
					$(this).removeClass('draghover')
					event.preventDefault();
				})
				.on("drop", function(event) {
					$(this).removeClass('draghover')
					event.preventDefault();
					sendFiles(event.originalEvent.dataTransfer.files);
				});

			files_box
					.on('mousedown', '.xdsoft_file', function () {
						if ($(this).hasClass('xdsoft_upload')) {
							return;
						}
						files_box
							.find('.xdsoft_file')
								.removeClass('active');
						$(this)
							.addClass('active');
					})
					.on('dblclick', '.xdsoft_file', function () {
						if ($(this).data('type') == 'folder') {
							methods.updateFilesList(path+'/'+decodeURIComponent($(this).data('name')));
							return false;
						}
						if ($(this).hasClass('xdsoft_upload')) {
							return;
						}
						methods.download($(this))
					});
			login
				.find('.go')
					.on('click', function () {
						methods.login(login.find('.login').eq(0).val(),login.find('.password').eq(0).val())
					});
			login
				.on('mousedown contextmenu keydown', function(event) {
					if (event.type === 'keydown' && event.which === 13) {
						login.find('.go').trigger('click');
					}
					event.stopPropagation();
				});

			explorer
				.find('.xdsoft_manage_block a')
					.on('click', function() {
						explorer
							.find('.xdsoft_manage_block a')
								.removeClass('active')
						$(this).addClass('active');
						files_box[$(this).hasClass('table_layout') ? 'addClass' : 'removeClass']('table_layout');
						return false;
					});

			if (getParam('path')) {
				path = getParam('path');
			}
			methods.updateFilesList(path);
			History.Adapter.bind(window,'statechange',function(){
		        var state = History.getState();
				if (path!=state.data.path) {
		        	methods.updateFilesList(state.data.path)
				}
		    });
		}
	};


	$('.xdsoft_drive').each(function() {
		if ($(this).hasClass('inioted')) {
			return;
		} else {
			var drive = new Drive;
			drive.init(this);
		}
	})
	$(window)
		.on('mouseup', function () {
			$('.xdsoft_drive')
				.removeClass('active')
				.trigger('outthis')
		})
		.on('keydown', function (event) {
			$('.xdsoft_drive.active')
				.trigger(event)
		});
}(jQuery.noConflict()))
