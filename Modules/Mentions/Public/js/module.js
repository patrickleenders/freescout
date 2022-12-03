var mentions_users = [];
//var mentions_loading = false;

function mentionsInitConv()
{
	fsAddFilter('editor.options', function(options, params) {
		
		if (typeof(options.hint) == "undefined") {
			options.hint = [];
		}

		options.hint.push({
		    match: /\B@([\w\p{L}_]*)$/u,
		    search: function (keyword, callback) {
		    	if (!isNote()) {
		    		return;
		    	}

		    	if (!mentions_users.length) {
		    		
					//mentions_loading = true;
					callback(['...']);

					// Only second ajax shows the list
			    	fsAjax({
							action: 'users',
							mailbox_id: getGlobalAttr('mailbox_id')
						}, 
						laroute.route('mentions.ajax'), 
						function(response) {
							if (isAjaxSuccess(response) && response.users) {
								mentions_users = response.users;
								callback(mentionsGrepUsers(keyword));
						        //mentions_loading = false;
							}
						},
						true,
						function(response) {
							//mentions_loading = false;
						}
					);
				} else {
					callback(mentionsGrepUsers(keyword));
				}
		    },
		    // In the list
		    template: function (item) {
		    	var parts = item.split('|');
		    	var name = item;
		    	if (parts.length > 1) {
		    		name = parts[0];
		    	}
			    return name;
		    },
		    // In HTML
		    content: function (item) {
		    	var parts = item.split('|');
		    	var name = item;
		    	var id = '';
		    	if (parts.length > 1) {
		    		name = parts[0];
		    		id = parts[parts.length - 1];
		    	}
		    	name = name.replaceAll(' ', '_').toLowerCase();
		        return $('<span><b data-mentioned-id="'+id+'">@' + name + ' </b>&nbsp;</span>')[0];
		    } 
		});
		return options;
	});
}

function mentionsGrepUsers(keyword)
{
	if (!keyword) {
		return mentions_users;
	}
	return $.grep(mentions_users, function (item) {
        return item.replaceAll(' ', '').toLowerCase().indexOf(keyword.replaceAll('_', '').toLowerCase()) == 0;
    })
}
