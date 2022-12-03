/**
 * Module's JavaScript.
 */

var KN_STATUS_CLOSED = 3;
var KN_SORT_CLOSED_NEW = 'closed_new';
var KN_FILTERS_SEPARATOR = '|';

var kn_redirect_on_card_create = 0;

function knInit(text_delete)
{
	$(document).ready(function(){
		$('#kn-boards-list').change(function(e) {
			window.location.href = $(this).val();
		});

		$(window).on('resize', function(){
			knResizeBoard();
		}).resize();

		knApplyListeners();

		knShowParams();

		// On chaning params
		$('.kn-param li a').click(function(e) {
			var li = $(this).parent();

			var param = li.parents('.kn-param').attr('data-param');
			if (param != 'filters') {
				li.parent().children('li').removeClass('active');
				li.addClass('active');
			}

			knShow();

			e.preventDefault();
		});

		$('#kn-btn-refresh').click(function(e) {
			knShow();
			e.preventDefault();
		});

		$('#kn-delete-board').click(function(e) {
			showModalConfirm('<span class="text-danger"><i class="glyphicon glyphicon-exclamation-sign"></i> '+text_delete+'</span>', 'kn-confirm-delete-board', {
					on_show: function(modal) {
						modal.children().find('.kn-confirm-delete-board:first').click(function(e) {
							var button = $(this);
					    	button.attr('data-loading-text', Lang.get("messages.delete")+'…');
					    	button.button('loading');
					    
							fsAjax({
									action: 'delete_board',
									board_id: getGlobalAttr('board_id')
								}, 
								laroute.route('kanban.ajax'), 
								function(response) {
									button.button('reset');
									if (isAjaxSuccess(response)) {
										window.location.href = '';
										modal.modal('hide');
									} else {
										showAjaxError(response);
									}
								}, true
							);
						});
					}
			}, Lang.get("messages.delete"));
			e.preventDefault();
		});

		$('#kn-copy-link').click(function(e) {
			var url = window.location.origin+window.location.pathname+'?';
			var params = knGetParams();
			var kn = [];
			for (var param_name in params) {
				var value = params[param_name];
				if (param_name == 'filters') {
					for (var filter_name in value) {
						var filter_values = value[filter_name].join(KN_FILTERS_SEPARATOR);
						if (!filter_values.length) {
							continue;
						}
						kn.push('kn%5B'+param_name+'%5D%5B'+filter_name+'%5D='+encodeURIComponent(filter_values));
					}
				} else {
					kn.push('kn%5B'+param_name+'%5D='+encodeURIComponent(value));
				}
			}
			url += kn.join('&');
			copyToClipboard(url);
			e.preventDefault();
		});

		$('#kn-reset-filter').click(function(e) {
			window.location.href = $(this).attr('href');
			e.preventDefault();
		});
	});
}

function knApplyListeners()
{
	$('.kn-more').off('click').click(function(e) {
		knLoadCards($(this), false);
	});

	$('.kn-show-closed').off('click').click(function(e) {
		var button = $(this);

		if (button.next().length) {
			// Collapse
			if (button.next().is(':visible')) {
				button.nextAll().hide();
			} else {
				button.nextAll().show();
			}
		} else {
			// Load
			knLoadCards($(this), true);
		}
	});

	initModals('div');

	$('.kn-card-footer a').off('click').click(function(e) {
		e.stopPropagation();
	});

	// Sortable panels
	var cols = sortable('.kn-sortable', {
	    //handle: '.handle',
	    connectWith: 'kn-sortable',
	    items: '.kn-card',
	    forcePlaceholderSize: true 
	});
	for (var i in cols) {
		cols[i].addEventListener('sortupdate', function(e) {
		    var col = $(e.target);
		    var card = $(e.detail.item);

			fsAjax({
					action: 'move_card',
					group_by: knGetParams()['group_by'],
					column_id: col.attr('data-column-id'),
					swimlane_id: col.attr('data-swimlane-id'),
					card_id: card.attr('data-card-id'),
					conversation_id: card.attr('data-conversation-id'),
					prev_card_id: card.next().attr('data-card-id'),
					closed: card.prevAll('.kn-show-closed:first').length
				}, 
				laroute.route('kanban.ajax'), 
				function(response) {
					showAjaxResult(response);
				}, true
			);
		});
	}
}

function knLoadCards(button, closed)
{
	button.button('loading');
	var col_container = button.parents('.kn-col:first');

	var params = knGetParams();
	var offset = col_container.children('.kn-card').length;

	// More clicked in Show Closed section
	var more_in_closed = false;
	if (button.prevAll('.kn-show-closed:first').length) {
		closed = true;
		more_in_closed = true;
	}

	if (closed) {
		params.sort = KN_SORT_CLOSED_NEW;
		params.filters.status = [KN_STATUS_CLOSED];
		if (more_in_closed) {
			offset = button.prevAll('.kn-show-closed:first').nextAll('.kn-card').length;
		} else {
			offset = button.nextAll('.kn-card').length;
		}
	}

	fsAjax({
			action: 'more',
			column_id: col_container.attr('data-column-id'),
			swimlane_id: col_container.attr('data-swimlane-id'),
			offset: offset,
			kn: params
		}, 
		laroute.route('kanban.ajax'), 
		function(response) {
			button.button('reset');
			if (isAjaxSuccess(response) && response.html) {
				if (button.hasClass('kn-show-closed')) {
					$(response.html).insertAfter(button);
				} else {
					$(response.html).insertBefore(button);
					button.remove();
				}
				knApplyListeners();
			} else {
				showAjaxResult(response);
			}
		}, true
	);
}

function knGetParams()
{
	var params = {};
	$('.kn-param').each(function(i, el) {
		var j_el = $(el);
		var param = j_el.attr('data-param');
		if (param == 'filters') {

		} else {
			params[param] = j_el.children().find('li.active:first').attr('data-param-value');
		}
	});

	params.mailbox_id = getGlobalAttr('mailbox_id');
	params.board_id = getGlobalAttr('board_id');

	params.filters = {};
	$('.kn-param[data-param="filters"] li').each(function(i, el) {
		var li = $(el);
		var param_name = li.attr('data-param-name');
		var param_value = li.attr('data-param-value');
		params.filters[param_name] = [];
		if (param_value) {
			params.filters[param_name] = param_value.split(KN_FILTERS_SEPARATOR);
		}

		if (params.filters[param_name].length == 1
			&& typeof(params.filters[param_name][0]) != "undefined"
			&& params.filters[param_name][0] == ''
		) {
			params.filters[param_name] = [];
		}
	});

	return params;
}

function knShowParams()
{
	$('.kn-param-counter').each(function(i, el) {
		var j_el = $(el);
		var count = j_el.parents('.kn-param:first').children('ul:first').children('li.active').length;
		if (!count) {
			j_el.text('');
		} else {
			j_el.text(' ('+count+')');
		}
	});
}

function knResizeBoard()
{
	var padding = Math.ceil($('nav.navbar:first').outerHeight() + $('#kn-heading').outerHeight());
	$('#kn-board').css('height', 'calc(100vh - '+padding+'px)');
}

function knShow()
{
	$('#kn-btn-refresh i:first').addClass('glyphicon-spin');
	fsAjax({
			action: 'show',
			kn: knGetParams()
		}, 
		laroute.route('kanban.ajax'), 
		function(response) {
			
			if (isAjaxSuccess(response) && response.html) {
				$('#kn-board').html(response.html);
				knApplyListeners();
				initModals();
				initTooltips();
			} else {
				showAjaxResult(response);
			}

			loaderHide();
			$('#kn-btn-refresh i:first').removeClass('glyphicon-spin');
		}, false, function(response) {
			showAjaxResult(response);
			loaderHide();
			$('#kn-btn-refresh i:first').removeClass('glyphicon-spin');
		}
	);
}

// Create/update board
function knInitBoardModal()
{
	$(document).ready(function(){

		$('.kn-board-name:visible:first').focus();

		$('.kn-board-form:visible:first').on('submit', function(e) {
			
			var board_id = $(this).attr('data-board-id');

			var data = $(this).serialize();

			if (board_id) {
				data += '&board_id='+board_id;
				data += '&action=update_board';
			} else {
				data += '&action=new_board';
			}
			
			var button = $(this).children().find('button:first');
	    	button.button('loading');

			fsAjax(data, 
				laroute.route('kanban.ajax'), 
				function(response) {
					if (isAjaxSuccess(response)) {
						if (board_id) {
							// Update
							//button.button('reset');
							window.location.href = '';
							//showFloatingAlert('success', response.msg_success);
							//knShow();
						} else {
							// Create
							window.location.href = response.board_url;
						}
					} else {
						showAjaxError(response);
						button.button('reset');
					}
					loaderHide();
				}
			);

			e.preventDefault();

			return false;
		});

		// Add column
		$('.kn-add-column:visible:first').click(function(e) {
			var columns_container = $('.kn-board-columns:visible:first');
			var column_id = parseInt(columns_container.attr('data-max-id')) + 1;
			var html = $('.kn-board-column-pattern:first').html();
			html = replaceAll(html, '-1', column_id);
			html = replaceAll(html, 'required-x', 'required');

			columns_container.append(html);

			columns_container.attr('data-max-id', column_id);

			knColumnsListeners();

			e.preventDefault();
		});

		knColumnsListeners();

		// Add swimlane
		$('.kn-add-swimlane:visible:first').click(function(e) {
			var swimlanes_container = $('.kn-board-swimlanes:visible:first');
			var swimlane_id = parseInt(swimlanes_container.attr('data-max-id')) + 1;
			var html = $('.kn-board-swimlane-pattern:first').html();
			html = replaceAll(html, '-1', swimlane_id);
			html = replaceAll(html, 'required-x', 'required');

			swimlanes_container.append(html);

			swimlanes_container.attr('data-max-id', swimlane_id);

			knSwimlanesListeners();

			e.preventDefault();
		});

		knSwimlanesListeners();
	});
}

// Create/update card
function knInitCardModal()
{
	$(document).ready(function(){

		$('.kn-card-form-name:visible:first').focus();

		$('#link_to_conversation').change(function(e) {
			if (!$(this).is(':checked')) {
				// Standalone
				$('.kn-card-linked').addClass('hidden');
				$('.kn-card-standalone').removeClass('hidden');

				$('.kn-card-linked :input').removeAttr("required");
			} else {
				// Linked
				$('.kn-card-linked').removeClass('hidden');
				$('.kn-card-standalone').addClass('hidden');

				$('.kn-card-linked :input').attr("required", "required");
			}
		});

		$('#link_to_conversation:visible').change();

		var editor_buttons = fs_conv_editor_buttons;
		$.extend(editor_buttons, {
		    //attachment: EditorAttachmentButton,
		    removeformat: EditorRemoveFormatButton,
		    lists: EditorListsButton
		});

		var editor_toolbar = fs_conv_editor_toolbar;
		// Remove everything after 'codeview'.
		var remove_button = false;
		for (var i in editor_toolbar[0][1]) {
			if (remove_button) {
				editor_toolbar[0][1].splice(i, 1);
				continue;
			}
			if (editor_toolbar[0][1][i] == 'codeview') {
				remove_button = true;
			}
		}
		var options = {
			minHeight: 120,
			dialogsInBody: true,
			dialogsFade: true,
			disableResizeEditor: true,
			followingToolbar: false,
			toolbar: editor_toolbar,
			//toolbar: fsApplyFilter('conversation.editor_toolbar', fs_conv_editor_toolbar),
			buttons: editor_buttons,
			callbacks: {
		 		onImageUpload: function(files) {
		 			if (!files) {
		 				return;
		 			}
		            for (var i = 0; i < files.length; i++) {
						editorSendFile(files[i], undefined, false, '#kn-card-body');
		            }
		        }
		    }
		};
		$('.kn-card-body:visible:first').summernote(options);

		$('.kn-card-submit:visible:first').click(function(e) {
			kn_redirect_on_card_create = 0;
		});
		$('.kn-card-submit-redirect:visible:first').click(function(e) {
			kn_redirect_on_card_create = 1;
		});

		$('.kn-card-form:visible:first').on('submit', function(e) {

			var card_id = $(this).attr('data-card-id');

			var data = $(this).serialize();

			if (card_id) {
				data += '&card_id='+card_id;
				data += '&action=update_card';
			} else {
				data += '&action=new_card';
			}
			if (kn_redirect_on_card_create) {
				var button = $(this).children().find('.kn-card-submit-redirect:first');
			} else {
				var button = $(this).children().find('.kn-card-submit:first');
			}
	    	button.button('loading');

			fsAjax(data, 
				laroute.route('kanban.ajax'), 
				function(response) {
					if (isAjaxSuccess(response)) {
						if (kn_redirect_on_card_create && response.board_url) {
							window.location.href = response.board_url;
						} else {
							if (response.msg_success) {
								showFloatingAlert("success", response.msg_success);
							}
							knShow();
						}
						$('.modal:visible:first').modal('hide');
					} else {
						showAjaxError(response);
						button.button('reset');
					}
				}, true
			);

			e.preventDefault();

			return false;
		});

		$('#kn-delete-card').click(function(e) {
			var button = $(this);
			showModalConfirm(kn_text_delete_card, 'kn-confirm-delete-card', {
				on_show: function(modal) {
					modal.children().find('.kn-confirm-delete-card:first').click(function(e) {
						button.button('loading');
						modal.modal('hide');
						var card_id = button.parents('.kn-card-form:first').attr('data-card-id');
						fsAjax(
							{
								action: 'delete_card',
								card_id: card_id
							},
							laroute.route('kanban.ajax'),
							function(response) {
								if (isAjaxSuccess(response)) {
									if (response.msg_success) {
										showFloatingAlert("success", response.msg_success);
									}
									$('.modal:visible').modal('hide');
									knShow();
								} else {
									showAjaxError(response);
									button.button('reset');
								}
							}, true
						);
					});
				}
			}, Lang.get("messages.delete"));

			e.preventDefault();
		});

		window.addEventListener("message", function(event) {
			if (typeof(event.data) != "undefined") {
				var number = event.data.split('kn.pick_conversation:');
				if (typeof(number[1])) {
					$('#kn-card-conv').val(number[1]);
					$('.modal:visible:first').animate({scrollTop: 0}, 600, 'swing');
				}
			}
		});

		$('#kn-board-input').change(function(e) {
			var board_id = $(this).val();
			$('.kn-board-inputs').addClass('hidden').attr('disabled', 'disabled');
			$('#kn-board-columns-select-'+board_id).removeClass('hidden').removeAttr('disabled');
			$('#kn-board-swimlane-select-'+board_id).removeClass('hidden').removeAttr('disabled');
		});

		$('.modal button.close').click(function(e) {
			knShow();
		});

		initTooltips();
	});
}

function knColumnsListeners()
{
	sortable('.kn-board-columns', {
	    handle: '.handle',
	    //items: '.kn-card',
	    forcePlaceholderSize: true 
	});

	$('.kn-delete-column').off('click').click(function(e) {
		if ($('.kn-delete-column:visible').length < 2) {
			return;
		}

		var button = $(this);
    	button.button('loading');
    	var container = button.parents('.panel:first');
		fsAjax({
				action: 'delete_column',
				board_id: $('.kn-board-form:visible:first').attr('data-board-id'),
				column_id: container.attr('data-column-id')
			}, 
			laroute.route('kanban.ajax'), 
			function(response) {
				button.button('reset');
				if (isAjaxSuccess(response)) {
					if (response.confirmation_text) {
						showModalConfirm('<span class="text-danger"><i class="glyphicon glyphicon-exclamation-sign"></i> '+response.confirmation_text+'</span>', 'kn-confirm-delete-column', {
							on_show: function(modal) {
								modal.children().find('.kn-confirm-delete-column:first').click(function(e) {
									button.parents('.panel:first').remove();
									modal.modal('hide');
								});
							}
						}, Lang.get("messages.delete"));
					} else {
						container.remove();
					}
				} else {
					showAjaxError(response);
				}
			}, true
		);

		e.preventDefault();
	});
}

function knSwimlanesListeners()
{
	sortable('.kn-board-swimlanes', {
	    handle: '.handle',
	    //items: '.kn-card',
	    forcePlaceholderSize: true 
	});

	$('.kn-delete-swimlane').off('click').click(function(e) {
		if ($('.kn-delete-swimlane:visible').length < 2) {
			return;
		}
		var button = $(this);
    	button.button('loading');
    	var container = button.parents('.panel:first');
		fsAjax({
				action: 'delete_swimlane',
				board_id: $('.kn-board-form:visible:first').attr('data-board-id'),
				swimlane_id: container.attr('data-swimlane-id')
			}, 
			laroute.route('kanban.ajax'), 
			function(response) {
				button.button('reset');
				if (isAjaxSuccess(response)) {
					if (response.confirmation_text) {
						showModalConfirm('<span class="text-danger"><i class="glyphicon glyphicon-exclamation-sign"></i> '+response.confirmation_text+'</span>', 'kn-confirm-delete-swimlane', {
							on_show: function(modal) {
								modal.children().find('.kn-confirm-delete-swimlane:first').click(function(e) {
									button.parents('.panel:first').remove();
									modal.modal('hide');
								});
							}
						}, Lang.get("messages.delete"));
					} else {
						container.remove();
					}
				} else {
					showAjaxError(response);
				}
			}, true
		);

		e.preventDefault();
	});
}

function knInitFilterModal()
{
	$(document).ready(function(){

		$('.kn-type-date:visible').flatpickr({allowInput: true});

		$('.kn-filter-form:visible:first').on('submit', function(e) {
			
			var fields = $(this).serializeArray();
			
			var filter = $(this).children(':input[name="filter"]').val();
			var selected = [];
			var cf_data = {};
			for (var i in fields) {
				// if (fields[i].name == 'filter') {
				// 	filter = fields[i].value;
				// }
				if (filter == 'custom_field') {
					matches = fields[i].name.match(/selected\[op\]\[(\d+)\]/);
					if (matches && typeof(matches[1]) != "undefined" && matches[1]) {
						if (typeof(cf_data[matches[1]]) == "undefined") {
							cf_data[matches[1]] = {};
						}
						cf_data[matches[1]]['op'] = fields[i].value;
					}

					matches = fields[i].name.match(/selected\[value\]\[(\d+)\]/);
					if (matches && typeof(matches[1]) != "undefined" && matches[1]) {
						if (typeof(cf_data[matches[1]]) == "undefined") {
							cf_data[matches[1]] = {};
						}
						cf_data[matches[1]]['value'] = fields[i].value;
					}
				} else {
					if (fields[i].name == 'selected[]') {
						selected.push(fields[i].value);
					}
				}
			}

			if (filter == 'custom_field') {
				for (var cf_id in cf_data) {
					if (cf_data[cf_id].value != '') {
						selected.push(value = JSON.stringify({
							id: cf_id,
							value: cf_data[cf_id].value,
							op: cf_data[cf_id].op
						}));
					}
				}
			}

			// Set filters.
			var li = $('.kn-param[data-param="filters"] li[data-param-name="'+filter+'"]');

			li.attr('data-param-value', selected.join(KN_FILTERS_SEPARATOR));
			var a = li.children('a:first');

			if (selected.length) {
				li.addClass('active');
				a.children('.kn-filter-counter:first').text('('+selected.length+')');
			} else {
				li.removeClass('active');
				a.children('.kn-filter-counter:first').text('');
			}
			// Update modal link
			var href = a.attr('href');
			href = href.replace(/&selected.*/, '');
			for (var i in selected) {
				href += '&selected%5B'+i+'%5D='+encodeURIComponent(selected[i]);
			}
			a.attr('href', href);

			$('.kn-reset-filter').removeClass('hidden');

			knShow();
			knShowParams();
			$('.modal:visible:first').modal('hide');
			
			e.preventDefault();

			return false;
		});
	});
}

function knPickConv(number, btn, event)
{
	if (typeof(window.parent) != "undefined") {
		window.parent.postMessage('kn.pick_conversation:'+number);
		$('.kn-btn-pick.btn-primary').removeClass('btn-primary').addClass('btn-default');
		$(btn).addClass('btn-primary');
	}
	event.stopPropagation();
}