(function($){
	function getParam (name, location) {
		location = location || window.location.search;
		var res = location.match(new RegExp('[#&?]' + name + '=([^&]*)', 'i'));
		return (res && res[1] ? decodeURIComponent(res[1]) : false);
	};
	var Drive = function () {
		var explorer = null,
			menu = null,
			login = null,
			path = null,
			files_box = null,
			progress_bar = null,
			i = 0,
			template = '<div data-type="{type}" data-name="{name_decode}" class="xdsoft_file">'+
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
					'<input multiple="" type="file" class="work_uploader">'+
					'<a href="javascript:void(0)">+</a>'+
				'</div>'+
				'<div class="xdsoft_clearex"></div>',

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
						.show()
					return false;
				}
				login
					.hide()
				return true;
			},

			buildData = function (data) {
				if (data instanceof FormData) {
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
			},
			send = function (action, data, callback) {
				$.ajax({
					xhr: function(){
						var xhr = new window.XMLHttpRequest();
						xhr.upload.addEventListener("progress", function(evt){
							if ( evt.lengthComputable ) {
								var percentComplete = evt.loaded / evt.total;
								percentComplete=parseInt(percentComplete*100);
									progress_bar
										.show()
									progress_bar
										.css('width',percentComplete+'%')

								if(percentComplete === 100) {
									progress_bar
										.hide()
								}
							}
						}, false);

						return xhr;
					},
					type: 'POST',
					enctype: 'multipart/form-data',
					data: buildData( data ),
					url: './assets/controller.php?action='+action,
					cache: false,
					contentType: false,
					processData: false,
					dataType:'json',
					success: function(resp){
						if (checkAuth(resp)) {
							callback && callback(resp);
						}
					}
				});
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
			methods = {
				'delete': function (file) {
					if (confirm('Are you shure?')) {
						send('delete', {path: path, file: decodeURIComponent(file.data('name'))}, function(resp) {
							if (resp.error === 0) {
								file.remove();
							}
						})
					}
				},
				download: function (file) {
					location = './assets/controller.php?action=download&path='+encodeURIComponent(path)+'&file='+file.data('name');
				},
				uploadFile: function () {alert('upload')},
				updateFilesList: function (_path) {
					send('getFilesList', {path:_path || ''}, function(resp) {
						if (resp.error!==3) {
							if (path!==resp.data.path) {
								path = resp.data.path;
								History.pushState({path:path}, "State 1", "?path="+encodeURIComponent(path));
							}
							render(resp.data.files);
						}
					})
				},
				createFolder: function () {
					var dirname = prompt('Введите название папки');
					if (dirname) {
						if (files_box.find('.xdsoft_file.active').data('type') == 'folder') {
							path = path+'/'+decodeURIComponent(files_box.find('.xdsoft_file.active').data('name'));
						}
						send('createFolder', {path:path, name:dirname}, function () {
							methods.updateFilesList(path)
						});
					}
				}
			};

		this.init = function(box) {
			explorer = $(box);

			menu = explorer.find('.xdsoft_context_menu').eq(0);
			login = explorer.find('.xdsoft_login').eq(0);
			files_box = explorer.find('.xdsoft_files').eq(0);
			progress_bar = explorer.find('.xdsoft_progressbar>div').eq(0);

			menu
				.on('mousedown','a', function(event) {
					if (methods[$(this).attr('class')]) {
						menu.hide();
						methods[$(this).attr('class')](files_box.find('.xdsoft_file.active'));
					}
				});

			explorer
				.on('contextmenu','.xdsoft_file', function(event) {

					menu.find('.download')[$(this).data('type')!='folder' ? 'show' : 'hide']();
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

					menu.find('.download').hide();
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
					event.preventDefault();
					event.stopPropagation();
					return false;
				})
				.on('mouseup', function(event) {
					menu
						.hide()
					event.preventDefault();
					event.stopPropagation();
					return false;
				});

			explorer
				.find('.xdsoft_files')
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
						send(
							'login',
							{
								login: login.find('.login').eq(0).val(),
								password: login.find('.password').eq(0).val(),
							},
							function () {
								methods.updateFilesList(path)
							}
						);
					});
			explorer
				.on('change', 'input[type="file"]', function () {
					var	files = this.files,
						len = files.length;

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
				})
			explorer
				.find('.xdsoft_manage_block a')
					.on('click', function() {
						explorer
							.find('.xdsoft_manage_block a')
								.removeClass('active')
						$(this).addClass('active');
						files_box[$(this).hasClass('table_layout') ? 'addClass' : 'removeClass']('table_layout');
						return false;
					})
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
			$('.xdsoft_drive').trigger('mouseup')
		})
}(jQuery.noConflict()))
