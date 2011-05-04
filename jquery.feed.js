// define the dirty globals
var refreshTimer = null;
var items = [];
var timer = null;
var updateTimer = null;
var limit = 3;
var since = 0;

$(function() {

	var addItem = function(data, flip) {

		var icon = null;
		var item = null;
		var controls = null;
		var replyUrl = false;

		switch (data.type) {
			case 'tweet':
				icon = '/images/icon_sm-twitter.png';
				replyUrl = 'http://twitter.com/intent/tweet?in_reply_to=' + data.id;
				break;
			case 'facebook':
				icon = '/images/icon_sm-facebook.png';
				replyUrl = false;
				break;
		}

		item = $('<div />', {
			'class' : 'social-media-feed-item'
		}).css({
			'background-image' : "url('" + icon + "')",
			'display' : 'none'
		});

		if (data.type == 'tweet') {

			item.append($('<span />', {
				'class' : 'feed-item-from',
				html : '<strong><a href="http://twitter.com/' + data.from + '">' + data.from + '<a/></strong>&nbsp;'
			}));

		} else {

			item.append($('<span />', {
				'class' : 'feed-item-from',
				html : '<strong>' + data.from + '</strong>&nbsp;'
			}));

		}

		item.append($('<span />', {
			'class' : 'feed-item-content',
			html : data.html + '&nbsp;'
		}));

		controls = $('<div />', {
			'class' : 'social-media-feed-item-controls'
		});

		controls.append($('<abbr />', {
			'class' : 'feed-item-sent timeago',
			'title' : data.sent_format1,
			html : data.sent_format2
		}));

		if (replyUrl) {

			controls.append($('<span />', {
				'html' : '&nbsp;&middot;&nbsp;'
			}));

			controls.append($('<a />', {
				'class' : 'feed-item-reply',
				'href' : replyUrl,
				'html' : 'reply',
				'rel' : 'external',
				'target' : '_blank'
			}));

		}

		item.append(controls);
		
		// add divider
		item.append($('<hr />'));

		items.push(item);

		if (data.sent > since)
			since = data.sent;

		if (timer == null) {
			timer = setInterval(function() {
				addItemToPage(flip);
			}, 500);
		}

	};

	var addItemToPage = function(flip) {

		if (items.length > 0) {

			// stop updating
			clearInterval(updateTimer);

			if (flip) {
				var item = items.pop();
				$('#social-media-feed h3').after(item);
			} else {
				var item = items.shift();
				$('#social-media-feed').append(item);
			}

			if ($('div.social-media-feed-item').length > limit) {
				$('div.social-media-feed-item:last').remove();
			}

			// initiate timeago script
			$("abbr.timeago").timeago();

			item.fadeIn('fast');

		} else {

			clearInterval(timer);
			timer = null;

			// set up an interval for updating
			updateTimer = setInterval(function() {
				getUpdate();
			}, 10000);

		}
		
		// add last class
		$('div.social-media-feed-item').removeClass('last');
		$('div.social-media-feed-item').last().addClass('last');
		
	};

	var getUpdate = function() {
		$.ajax({
			url : '/helpers/updateSocialFeed.php',
			data : {
				'limit' : 0,
				'since' : since
			},
			success : function(data) {
				for (i in data) {
					addItem(data[i], true);
				}
			},
			dataType : 'json'
		});
	};

	// get the initial data
	$.ajax({
		url : '/helpers/updateSocialFeed.php',
		data : {
			'limit' : limit,
			'since' : false
		},
		success : function(data) {
			$('#social-media-feed div').remove();
			for (i in data) {
				addItem(data[i], false);
			}
		},
		dataType : 'json'
	});

});