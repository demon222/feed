(function($) {

    var methods = {

	init : function(options) {

	    // console.debug('init');

	    var settings = {
		limit : 3,
		updatePath : '/helpers/updateSocialFeed.php',
		twitterIcon : '/images/icon_sm-twitter.png',
		facebookIcon : '/images/icon_sm-facebook.png',
		bloggerIcon : '/images/icon_sm-blogger.png',
		updateInterval : 30
	    };

	    if (options) {
		$.extend(settings, options);
	    }

	    return this.each(function() {

		var data = {
		    settings : settings,
		    refreshTimer : null,
		    items : [],
		    timer : null,
		    updateTimer : null,
		    since : 0,
		    firstLoad : true
		};

		// store data with the element
		$(this).data('socialFeed', data);

		$(this).socialFeed('getUpdate', data.settings.limit);

	    });
	},

	getUpdate : function(limit) {

	    // console.debug('getUpdate');

	    return this.each(function() {

		var $this = $(this);
		var data = $this.data('socialFeed');

		$.ajax({
		    url : data.settings.updatePath,
		    data : {
			limit : limit,
			since : data.since
		    },
		    success : function(result) {
			if (data.firstLoad) {
			    data.firstLoad = false;
			    $this.find('div').eq(0).remove();
			    for (i in result) {
				$this.socialFeed('addItem', result[i], false);
			    }
			} else {
			    for (i in result) {
				$this.socialFeed('addItem', result[i], true);
			    }
			}
		    },
		    dataType : 'json'
		});

	    });
	},

	addItem : function(itemData, flip) {

	    // console.debug('addItem');

	    return this.each(function() {

		var icon = null;
		var item = null;
		var controls = null;
		var replyUrl = false;
		var $this = $(this);
		var data = $(this).data('socialFeed');

		switch (itemData.type) {
		case 'tweet':
		    icon = data.settings.twitterIcon;
		    replyUrl = 'http://twitter.com/intent/tweet?in_reply_to=' + itemData.id;
		    break;
		case 'facebook':
		    icon = data.settings.facebookIcon;
		    replyUrl = false;
		    break;
		case 'blogger':
		    icon = data.settings.bloggerIcon;
		    replyUrl = false;
		    break;
		}

		item = $('<div />', {
		    'class' : 'social-media-feed-item'
		}).css({
		    'background-image' : "url('" + icon + "')",
		    'display' : 'none'
		});

		if (itemData.type == 'tweet') {

		    item.append($('<span />', {
			'class' : 'feed-item-from',
			html : '<a href="http://twitter.com/' + itemData.from + '">' + itemData.from + '<a/>&nbsp;'
		    }));

		} else {

		    item.append($('<span />', {
			'class' : 'feed-item-from',
			html : '<strong>' + itemData.from + '</strong>&nbsp;'
		    }));

		}

		item.append($('<span />', {
		    'class' : 'feed-item-content',
		    html : itemData.html + '&nbsp;'
		}));

		controls = $('<div />', {
		    'class' : 'social-media-feed-item-controls'
		});

		controls.append($('<abbr />', {
		    'class' : 'feed-item-sent timeago',
		    title : itemData.sent_format1,
		    html : itemData.sent_format2
		}));

		if (replyUrl) {

		    controls.append($('<span />', {
			'html' : '&nbsp;&middot;&nbsp;'
		    }));

		    controls.append($('<a />', {
			'class' : 'feed-item-reply',
			href : replyUrl,
			html : 'reply',
			rel : 'external',
			target : '_blank'
		    }));

		}

		item.append(controls);

		// add divider
		item.append($('<hr />'));

		data.items.push(item);

		if (itemData.sent > data.since)
		    data.since = itemData.sent;

		if (data.timer == null) {
		    data.timer = setInterval(function() {
			$this.socialFeed('addItemToPage', flip);
		    }, 500);
		}

	    });

	},

	addItemToPage : function(flip) {

	    // console.debug('addItemToPage');

	    return this.each(function() {

		var $this = $(this);
		var data = $this.data('socialFeed');

		if (data.items.length > 0) {

		    // stop updating
		    clearInterval(data.updateTimer);

		    var item = null;
		    if (flip) {
			item = data.items.pop();
			if ($this.find('h3')) {
			    $this.find('h3').after(item);
			} else {
			    $this.prepend(item);
			}
		    } else {
			item = data.items.shift();
			$this.append(item);
		    }

		    if ($this.find('div.social-media-feed-item').length > data.limit) {
			$this.find('div.social-media-feed-item:last').remove();
		    }

		    if (jQuery.timeago) {
			// initiate timeago script
			$("abbr.timeago").timeago();
		    }

		    item.fadeIn('fast');

		} else {

		    clearInterval(data.timer);
		    data.timer = null;

		    // set up an interval for updating
		    data.updateTimer = setInterval(function() {
			$this.socialFeed('getUpdate');
		    }, (data.settings.updateInterval * 1000));

		}

		// add last class
		$this.find('div.social-media-feed-item').removeClass('last');
		$this.find('div.social-media-feed-item').last().addClass('last');

	    });
	}

    };

    $.fn.socialFeed = function(method) {

	if (methods[method]) {
	    return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
	} else if (typeof method === 'object' || !method) {
	    return methods.init.apply(this, arguments);
	} else {
	    $.error('Method ' + method + ' does not exist on jQuery.socialFeed');
	}

    };

})(jQuery);
