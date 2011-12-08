<?php
/**
 *
 * Gets feeds from facebook, twitter and blogger
 * @author _ianbarker
 *
 */

require_once 'MiniDb.class.php';

class SocialFeed {

	private static $version = '1.1';

	protected static $upateFrequency = 25;
	protected static $logging = false;

	// twitter stuff
	protected static $tweetTable = 'feed_twitter';
	protected static $twitterFromUsers = array();
	protected static $twitterToUsers = array();
	protected static $twitterHashTags = array();
	protected static $twitterKeywords = array();
	protected static $tweetLanguage = 'en';
	protected static $hideReTweets = true;

	// facebook stuff
	protected static $facebookTable = 'feed_facebook';
	protected static $facebookFeedId = null;

	// blogger stuff
	protected static $bloggerTable = 'feed_blogger';
	protected static $bloggerId = null;

	// db stuff
	private $db;

	public function __construct($host, $user, $pass, $database) {

		$this->db = new MiniDb($host, $user, $pass, $database);

	}

	/**
	 *
	 * Enables or disables logging
	 * @param bool $logging
	 * @throws InvalidParameterException
	 */

	public function setLogging($logging) {

		if (!is_bool($logging))
			throw new InvalidParameterException('SocialFeed::setLogging requires a boolean parameter');
		self::$logging = $logging;
	}

	/**
	 *
	 * Adds a from parameter to the search query for twitter
	 * @param string $user
	 */

	public function addFromTwitterUser($user) {

		self::$twitterFromUsers[] = $user;
	}

	/**
	 *
	 * Adds a to parameter to the search query for twitter
	 * @param string $user
	 */

	public function addToTwitterUser($user) {

		self::$twitterToUsers[] = $user;
	}

	/**
	 *
	 * Adds a hash tag search to the twitter search
	 * @param string $tag
	 */

	public function addTwitterHashTag($tag) {

		self::$twitterHashTags[] = (substr($tag, 0, 1) == '#') ? substr($tag, 1) : $tag;
	}

	/**
	 *
	 * Adds a keyword to the twitter search
	 * @param string $keyword
	 */

	public function addTwitterKeyword($keyword) {

		self::$twitterKeywords[] = $keyword;
	}

	/**
	 * Sets the tweet language
	 * @param string $language 2 character iso code e.g. 'en'
	 */

	public function setTweetLanguage($language) {

		self::$tweetLanguage = $language;
	}

	/**
	 *
	 * Hides tweets with RT in them
	 * @param string $hide
	 */

	public function setHideReTweets($hide) {

		self::$hideReTweets = $hide;
	}

	/**
	 *
	 * Sets the facebook id of the feed that posts will be retreived from
	 * Must be a string as PHP doesn't like 64bit numbers
	 * The facebook username can also be used
	 * @param string $id
	 * @throws GeneralException
	 */

	public function setFacebookId($id) {

		if (!is_string($id))
			throw new GeneralException('Facebook id needs to be a string, PHP doesn\'t like such big numbers very much');
		self::$facebookFeedId = $id;

		// include the facebook wrapper class, this will include the SDK
		require_once 'FacebookWrapper.class.php';

		// set the default toucan api stuff
		FacebookWrapper::setAppId('137714306268319');
		FacebookWrapper::setSecret('7bb701c9040b5462b28c77f972038e63');

	}

	/**
	 * 
	 * Sets the blogger id of the blogger feed to use
	 * This can be found by going to the blog and viewing the source, then 
	 * looking for blogid={bloggerid}
	 * This must be a string as with the facebook id due to 64bit number
	 * @param string $id
	 * @throws GeneralException
	 */
	public function setBloggerId($id) {

		if (!is_string($id))
			throw new GeneralException('Facebook id needs to be a string, PHP doesn\'t like such big numbers very much');
		self::$bloggerId = $id;
	}

	/**
	 * Sets the update frequency for the feeds
	 * @param int $seconds
	 */

	public function setUpdateFrequency($seconds) {

		$seconds = (int) $seconds;
		if ($seconds < 10)
			$seconds = 10;
		self::$upateFrequency = $seconds;
	}

	/**
	 *
	 * Sets the name of the table that is used to store facebook posts
	 * @param string $table
	 */

	public function setFacebookTable($table) {

		self::$facebookTable = $table;
		self::checkFacebookTable();
	}

	/**
	 *
	 * Sets the name of the table that is used to store tweets
	 * @param string $table
	 */

	public function setTweetTable($table) {

		self::$tweetTable = $table;
		self::checkTweetTable();
	}

	/**
	 * 
	 * Checks if the twitter feed table exists and creates if not
	 */
	public function checkTweetTable() {

		if ($this->db->getDebugLevel() > 0) {

			$query = " CREATE TABLE IF NOT EXISTS
						  `" . self::$tweetTable . "` (
						  `id` bigint(64) unsigned NOT NULL DEFAULT '0',
						  `text` text CHARACTER SET latin1 NOT NULL,
						  `sent` datetime NOT NULL,
						  `received` datetime NOT NULL,
						  `search_hash` varchar(32) CHARACTER SET latin1 NOT NULL,
						  `from` varchar(60) CHARACTER SET latin1 NOT NULL,
						  PRIMARY KEY (`id`)
						) ENGINE=MyISAM DEFAULT CHARSET=utf8;
					 ";
			$this->db->query($query);

		}
	}

	/**
	 *
	 * Checks if the facebook feed table exists and creates if not
	 */
	public function checkFacebookTable() {

		if ($this->db->getDebugLevel() > 0) {

			$query = " CREATE TABLE IF NOT EXISTS
						  `" . self::$facebookTable . "` (
						  `id` bigint(64) NOT NULL,
						  `from` varchar(120) NOT NULL,
						  `type` varchar(20) NOT NULL,
						  `content` text NOT NULL,
						  `link` varchar(256) NOT NULL,
						  `sent` datetime NOT NULL,
						  `received` datetime NOT NULL,
						  PRIMARY KEY (`id`)
						) ENGINE=MyISAM DEFAULT CHARSET=utf8; 
					 ";
			$this->db->query($query);

		}

	}

	/**
	 *
	 * Checks if the blogger feed table exists and creates if not
	 */
	public function checkBloggerTable() {

		if ($this->db->getDebugLevel() > 0) {

			$query = " CREATE TABLE IF NOT EXISTS
						  `" . self::$bloggerTable . "` (
						  `id` bigint(64) NOT NULL,
						  `title` varchar(120) NOT NULL,
						  `content` text NOT NULL,
						  `link` varchar(256) NOT NULL,
						  `sent` datetime NOT NULL,
						  `received` datetime NOT NULL,
						  PRIMARY KEY (`id`)
						) ENGINE=MyISAM DEFAULT CHARSET=utf8; 
					 ";
			$this->db->query($query);

		}

	}

	/**
	 * 
	 * Updates all feeds
	 * Each update method should check the last update date in it's table
	 * so there is no need for that to be done here
	 */
	protected function updateFeeds() {

		// twitter first
		if (!empty(self::$twitterFromUsers)) {
			self::checkTweetTable();
			// search for tweets from specific users
			foreach (self::$twitterFromUsers as $user) {
				$parameters = array(
					'from' => $user
				);
				$this->updateTweets($parameters);
			}
		}

		if (!empty(self::$twitterToUsers)) {
			self::checkTweetTable();
			// search for tweets to specific users
			foreach (self::$twitterToUsers as $user) {
				$parameters = array(
					'to' => $user
				);
				$this->updateTweets($parameters);
			}
		}

		if (!empty(self::$twitterHashTags)) {
			self::checkTweetTable();
			foreach (self::$twitterHashTags as $tag) {
				// search for tweets with a specific hash tags
				$parameters = array(
					'tag' => $tag
				);
				$this->updateTweets($parameters);
			}
		}

		if (!empty(self::$twitterKeywords)) {
			self::checkTweetTable();
			foreach (self::$twitterKeywords as $keyword) {
				// search for tweets with a specific hash tags
				$parameters = array(
					'q' => $keyword
				);
				$this->updateTweets($parameters);
			}
		}

		// now facebook
		if (!empty(self::$facebookFeedId)) {
			self::checkFacebookTable();
			$this->updateFacebookFeed();
		}

		// now blogger 
		if (!empty(self::$bloggerId)) {
			self::checkBloggerTable();
			$this->updateBloggerFeed();
		}
	}

	/**
	 *
	 * returns the most recent tweet
	 * if hash is used then it will have to have a specific hash
	 * this is used to make sure we're only updating from twitter when the current data is old
	 * @param string $hash
	 */

	private function getLatestStoredTweet($hash = '') {

		$sqlHash = (!empty($hash)) ? " AND search_hash = '" . $this->db->escape($hash) . "' " : '';

		$query = " SELECT `id`,
						  `received`
						FROM `" . self::$tweetTable . "`
						WHERE 1
							{$sqlHash}
						ORDER BY `id` DESC
						LIMIT 1
					 ";
		$latest = $this->db->queryAssoc($query);
		return $latest[0];

	}

	/**
	 * 
	 * Censors bad words
	 * @param string $text
	 * @todo create a method to add words to this for specific sites, maybe
	 */
	protected function sanatiseText($text) {

		$matches = array(
			'/\bfuck\b/i', '/\bcunt\b/i', '/shit\b/i'
		);
		$replace = array(
			'f*!#', 'c*!#', 's*!#'
		);

		return preg_replace($matches, $replace, $text);
	}

	/**
	 * Checks to see if we need to get tweets from twitter
	 * @param array $parameters
	 */

	protected function updateTweets($parameters = array(), $no_since_id = false) {

		$hash = md5(http_build_query($parameters, null, '&'));

		$latestTweet = (!$no_since_id) ? $this->getLatestStoredTweet($hash) : array();

		if (strtotime($latestTweet['received']) < strtotime('-' . self::$upateFrequency . ' seconds')) {

			// call statically so that we can use this statically also
			$tweets = $this->getNewTweets($parameters, $latestTweet['id']);

			if (!empty($tweets->results)) {

				// log it
				self::log('Found ' . count($tweets->results) . ' new tweets');

				// store the tweets that we got
				foreach ($tweets->results as $tweet) {
					$query = " REPLACE INTO `" . self::$tweetTable . "`
								SET `id` = " . $this->db->escape($tweet->id_str) . ",
									`from` = '" . $this->db->escape($tweet->from_user) . "',
									`text` = '" . $this->db->escape($tweet->text) . "',
									`sent` = '" . date('Y-m-d H:i:s', strtotime($tweet->created_at)) . "',
									`received` = NOW(),
									`search_hash` = '" . $hash . "'
							 ";
					$this->db->query($query);
				}

			} else {

				// check for error
				if ($tweets->error) {
					switch ($tweets->error) {
						case 'since date or since_id is too old':
						// retry without since_id
							self::log('Since id was too old - retrying!');
							$this->updateTweets($parameters, true);
					}
				}

				self::log('Found no new tweets');

				if ($latestTweet['id'] > 0) {
					// update the received date to mark that we just checked
					$query = " UPDATE `" . self::$tweetTable . "`
								SET `received` = NOW()
								WHERE 1
								 AND `id` = " . $latestTweet['id'] . "
								 AND search_hash = '" . $hash . "'
								LIMIT 1
							 ";
					$this->db->query($query);

					self::log('Updated recieved date of ' . $latestTweet['id'] . ' to now');

				}

			}
		}

	}

	/**
	 *
	 * Runs a twitter search with the supplied parameters
	 * @param array $parameters
	 * @param int $sinceId
	 */

	protected function getNewTweets($parameters = array(), $sinceId = 0) {

		// build the url

		$url = 'http://search.twitter.com/search.json';

		$default = array(
				'result_type' => 'recent',
				'lang' => self::$tweetLanguage,
				'rpp' => 100,
				'since_id' => $sinceId
		);
		$parameters = array_merge($default, $parameters);
		$url .= '?' . http_build_query($parameters, null, '&');

		self::log($url);

		// get the url
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, 'Toucan Twitter Search v' . self::$version);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);

		// return the decoded result
		return json_decode($result);

	}

	/**
	 * 
	 * Gets new posts from facebook, if the last check was > than the update frequency
	 * 
	 */
	protected function updateFacebookFeed() {

		$latestItem = $this->getLatestStoredfacebookItem();

		if (strtotime($latestItem['received']) < strtotime('-' . self::$upateFrequency . ' seconds')) {

			$feed = $this->getFacebookFeed(strtotime($latestItem['sent']));

			if (!empty($feed->data)) {

				foreach ($feed->data as $item) {

					$link = '';
					$content = '';
					// get the correct content
					switch ($item->type) {
						case 'photo':
							$content = $item->picture;
							$link = $item->link;
							break;
						case 'status':
							$content = $item->message;
							break;
						case 'link':
							$content = $item->message;
							if (strlen($content) < strlen($item->description))
								$content = $item->description;
							if (strlen($content) == 0)
								$content = $item->name;
							$link = $item->link;
							break;

					}

					$parts = explode("_", $item->id);
					$id = $parts[1];

					$query = " INSERT IGNORE INTO `" . self::$facebookTable . "`
								SET `id` = " . $this->db->escape($id) . ",
									`from` = '" . $this->db->escape($item->from->name) . "',
									`type` = '" . $this->db->escape($item->type) . "',
									`content` = '" . $this->db->escape($content) . "',
									`link` = '" . $this->db->escape($link) . "',
									`sent` = '" . $this->db->escape($item->created_time) . "',
									`received` = '" . $this->db->escape(date('Y-m-d H:i:s', strtotime('-' . self::$upateFrequency . ' seconds'))) . "'
							 ";
					$this->db->query($query);

				}

			} else {

				self::log('Found no new Facebook stuff');

				if ($latestItem['id'] > 0) {

					$query = " UPDATE `" . self::$facebookTable . "`
								SET received = NOW()
								WHERE 1
								 AND id = " . $latestItem['id'] . "
								LIMIT 1
							 ";
					$this->db->query($query);

					self::log('Updated recieved date of Facebook item ' . $latestItem['id'] . ' to now');

				}

			}

		}

	}

	/**
	 * 
	 * creates a url for getting data from facebook, runs the CURL request and
	 * returns the result
	 * @param string/date $since
	 * @return mixed json decoded result
	 */
	private function getFacebookFeed($since = false) {

		// build graph feed url

		$url = 'https://graph.facebook.com/' . self::$facebookFeedId . '/feed';
		$url .= '?access_token=' . FacebookWrapper::getToken();
		if ($since)
			$url .= '&since=' . $since;

		self::log($url);

		// get it
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, 'Toucan Facebook Feed Reader v' . self::$version);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);

		// return the decoded result
		return json_decode($result);

	}

	/**
	 * 
	 * Returns the latest post from the facebook table
	 * @return Array the record from the databse
	 */
	private function getLatestStoredfacebookItem() {

		$query = " SELECT *
					FROM `" . self::$facebookTable . "`
					WHERE 1
					ORDER BY `received` DESC
					LIMIT 1
				 ";
		$latest = $this->db->queryAssoc($query);
		return $latest[0];

	}

	/**
	 * 
	 * Gets new posts from blogger, if the last check was > than the update frequency
	 * 
	 */
	protected function updateBloggerFeed() {

		$latestPost = $this->getLatestBlogPost();

		if (strtotime($latestPost['received']) < strtotime('-' . self::$upateFrequency . ' seconds')) {

			$result = $this->getBloggerFeed($latestPost['sent']);

			$posts = $result->feed->entry;
			if (count($posts) > 0) {
				foreach ($posts as $k => $v) {
					$post = array(
							'title' => $v->title->{'$t'},
							'content' => $this->sanatiseBloggerContent($v->content->{'$t'}),
							'link' => $v->link[4]->href,
							'sent' => $v->published->{'$t'}
					);

					// get post id part from the id
					preg_match('/post-([0-9]+)$/', $v->id->{'$t'}, $matches);
					$post['id'] = $matches[1];

					// add to db
					$query = " INSERT IGNORE INTO `" . self::$bloggerTable . "`
								SET `id` = " . $this->db->escape($post['id']) . ",
									`title` = '" . $this->db->escape($post['title']) . "',
									`content` = '" . $this->db->escape($post['content']) . "',
									`link` = '" . $this->db->escape($post['link']) . "',
									`sent` = '" . $this->db->escape($post['sent']) . "',
									`received` = '" . $this->db->escape(date('Y-m-d H:i:s', strtotime('-' . self::$upateFrequency . ' seconds'))) . "'
							 ";
					$this->db->query($query);

				}

			} else {

				self::log('Found no new Blogger stuff');

				if ($latestPost['id'] > 0) {

					$query = " UPDATE `" . self::$bloggerTable . "`
								SET received = NOW()
								WHERE 1
								 AND id = " . $latestPost['id'] . "
								LIMIT 1
							 ";
					$this->db->query($query);

					self::log('Updated recieved date of Blogger item ' . $latestPost['id'] . ' to now');

				}

			}

		}

	}

	/**
	 * 
	 * Removes style tags from blogger content, these are sometimes there when
	 * the content was pasted from word... blogger should really do this for us
	 * @param string $text
	 */
	private function sanatiseBloggerContent($text) {

		$text = preg_replace('/<style>.*?<\/style>/', '', $text);
		return strip_tags($text);

	}

	/**
	 * 
	 * Gets the latest blog post from the database
	 * @return array blog post
	 */
	private function getLatestBlogPost() {

		$query = " SELECT *
					FROM `" . self::$bloggerTable . "`
					WHERE 1
					ORDER BY `received` DESC
					LIMIT 1
				 ";
		$latest = $this->db->queryAssoc($query);
		return $latest[0];
	}

	/**
	 * 
	 * Builds the url to retrieve new blogger posts and makes the CURL request
	 * @param string/date $since 
	 * @return mixed json decoded curl result
	 */
	protected function getBloggerFeed($since = false) {

		// build url

		$url = 'http://www.blogger.com/feeds/' . self::$bloggerId . '/posts/default/?';
		$params = array(
			'alt' => 'json', 'orderby' => 'updated'
		);
		if ($since) {
			// convert to correct format
			$since = date('Y-m-d\TH:i:s', strtotime($since));
			$params['updated-min'] = $since;
		}

		$url .= http_build_query($params, null, '&');

		self::log($url);

		// get it
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, 'Toucan Blogger Feed Reader v' . self::$version);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);

		// return the decoded result
		return json_decode($result);

	}

	/**
	 *
	 * Gets the latest data, will update from twitter and facebook as neccessary
	 * @param int $limit
	 * @param int $since
	 */

	public function getData($limit = 10, $since = 0) {

		$output = array();

		// make sure the feeds are up to date
		$this->updateFeeds();

		$since = ((int) $since > 0) ? (int) $since : false;
		$limit = ((int) $limit > 0) ? (int) $limit : false;

		// get the tweets
		$tweets = $this->getTweets($limit, $since);

		// get the wall feed
		$items = $this->getWallItems($limit, $since);

		// get the blogger feed
		$posts = $this->getBlogPosts($limit, $since);

		if ($limit > 0) {
			// make sure there's one of each, this only applies on the initial display
			if (!empty($tweets))
				$output[] = $tweets[0];
			if (!empty($items))
				$output[] = $items[0];
			if (!empty($posts))
				$output[] = $posts[0];
			$feed = array_merge($tweets, $items, $posts);
			$i = 0;
			while ($limit > count($output)) {
				if (!in_array($feed[$i], $output)) {
					$output[] = $feed[$i];
				}
				$i++;
			}
		} else {
			// merge the feeds
			$output = array_merge($tweets, $items, $posts);
		}

		// sort by sent date
		usort($output, 'SocialFeed::cmpdate');

		return $output;
	}

	/**
	 *
	 * Gets the latest tweets from the database
	 * @param int $limit
	 */

	private function getTweets($limit = 5, $since = 0) {

		$this->checkTweetTable();

		$sqlSince = ($since > 0) ? " AND `sent` > '" . date('Y-m-d H:i:s', $since) . "' " : '';
		$sqlHide = (self::$hideReTweets) ? " AND `text` NOT LIKE 'RT%' " : '';
		$limit = ($limit > 0) ? ' LIMIT ' . $limit : '';

		$tweets = array();
		$query = " SELECT `id`,
						  `from`,
						  `text`,
						  `sent`	
					 FROM `" . self::$tweetTable . "`
					 WHERE 1
					 	{$sqlSince}
					 	{$sqlHide}
					 ORDER BY `sent` DESC
					 {$limit}
				 ";
		try {
			$tweets = $this->db->queryAssoc($query);
		} catch (Exception $e) {
			return array();
		}
		foreach ($tweets as &$tweet) {
			$tweet['type'] = 'tweet';
			$tweet['html'] = $this->linkItUp($this->sanatiseText($tweet['text']));
			$tweet['sent'] = strtotime($tweet['sent']);
			$tweet['sent_format1'] = date('c', $tweet['sent']);
			$tweet['sent_format2'] = date('F j, Y', $tweet['sent']);
			unset($tweet['text']);
		}
		return $tweets;
	}

	/**
	 *
	 * Gets the facebook wall posts from the database
	 * @param int $limit
	 */

	private function getWallItems($limit = 10, $since = 0) {

		$sqlSince = ($since > 0) ? " AND sent > '" . date('Y-m-d H:i:s', $since) . "' " : '';
		$limit = ($limit > 0) ? ' LIMIT ' . $limit : '';

		$query = " SELECT *
					 FROM `" . self::$facebookTable . "`
					 WHERE 1
					 	{$sqlSince}
					 ORDER BY `sent` DESC
					 {$limit}
				 ";
		try {
			$rawItems = $this->db->queryAssoc($query);
		} catch (Exception $e) {
			return array();
		}
		$items = array();
		foreach ($rawItems as $item) {

			switch ($item['type']) {
				case 'link':
					$html = $this->shrinkUrls($this->shortenText($item['content'], 0, 140)) . '<br><a href="' . $item['link'] . '" rel="external">read more</a>';
					break;
				case 'photo':
					$html = '<br><a href="' . $item['link'] . '" rel="external"><img src="' . $item['content'] . '"></a>';
					break;
				default:
					$html = $item['content'];
			}

			$items[] = array(
					'type' => 'facebook',
					'from' => $item['from'],
					'html' => $html,
					'sent' => strtotime($item['sent']),
					'sent_format1' => date('c', strtotime($item['sent'])),
					'sent_format2' => date('F j, Y', strtotime($item['sent']))
			);
		}

		return $items;
	}

	/**
	 * 
	 * Gets blog posts from the database
	 * @param integer $limit
	 * @param string/date $since
	 * @return array associative array
	 */
	private function getBlogPosts($limit = 10, $since = 0) {

		$sqlSince = ($since > 0) ? " AND sent > '" . date('Y-m-d H:i:s', $since) . "' " : '';
		$limit = ($limit > 0) ? ' LIMIT ' . $limit : '';

		$query = " SELECT *
					 FROM `" . self::$bloggerTable . "`
					 WHERE 1
					  {$sqlSince}
					 ORDER BY `sent` DESC
					 {$limit}
				 ";
		try {
			$rawItems = $this->db->queryAssoc($query);
		} catch (Exception $e) {
			return array();
		}
		$items = array();
		foreach ($rawItems as $item) {

			$items[] = array(
					'type' => 'blogger',
					'from' => $item['title'],
					'html' => $this->shortenText($this->shrinkUrls($item['content']), 140) . '<br><a href="' . $item['link'] . '" rel="external">read more</a>',
					'sent' => strtotime($item['sent']),
					'sent_format1' => date('c', strtotime($item['sent'])),
					'sent_format2' => date('F j, Y', strtotime($item['sent']))
			);
		}

		return $items;

	}

	/**
	 * Converts urls a string into html links
	 * @param string $string
	 */

	private function linkItUp($string) {

		// add standard links
		$regex = '/(?i)\\b((?:https?:\/\/|www\\d{0,3}[.]|[a-z0-9.\\-]+[.][a-z]{2,4}\/)(?:[^\\s()<>]+|\\(([^\\s()<>]+|(\\([^\\s()<>]+\\)))*\\))+(?:\\(([^\\s()<>]+|(\\([^\\s()<>]+\\)))*\\)|[^\\s`!()\\[\\]{};:\'".,<>?гхрсту]))/i';
		$string = preg_replace($regex, '<a href="$1" target="_blank" rel="external">$1</a>', $string);

		// add @ links
		$string = preg_replace('/@([a-z0-9_-]+)/i', '<a href="http://twitter.com/$1" target="_blank" rel="external">@$1</a>', $string);

		// add # links
		$string = preg_replace('/#([a-z0-9_-]+)/i', '<a href="http://twitter.com/search?q=%23$1" target="_blank" rel="external">#$1</a>', $string);

		return $string;

	}

	/**
	 * Returns a string like '2 hours ago'
	 * @deprecated it's better to use the javascript version as it will update in realtime
	 * @param int $time unix timestamp
	 */

	private function timesince($ptime) {

		$etime = time() - $ptime;

		if ($etime < 1) {
			return '0 seconds';
		}

		$a = array(
				12 * 30 * 24 * 60 * 60 => 'year',
				30 * 24 * 60 * 60 => 'month',
				24 * 60 * 60 => 'day',
				60 * 60 => 'hour',
				60 => 'minute',
				1 => 'second'
		);

		foreach ($a as $secs => $str) {
			$d = $etime / $secs;
			if ($d >= 1) {
				$r = round($d);
				return $r . ' ' . $str . ($r > 1 ? 's' : '');
			}
		}
	}

	/**
	 * 
	 * Writes a message to the log file
	 * @param string $message
	 */
	private static function log($message) {

		if (self::$logging) {
			$fh = fopen($_SERVER['DOCUMENT_ROOT'] . '/log/socialfeed_log', 'a');
			fwrite($fh, "[ " . date('d.m.Y H:i:s') . " ]-[ {$message} ]\n");
			fclose($fh);
		}
	}

	/*
	 * Helper functions
	 * 
	 * shrinkUrls - goes through a string and converts urls to links (they must start with http)
	 * shrinkUrl - used by shrinkUrls to add the <a> and shrink the url to just the host
	 * cmpdate - used by array callback sort for sorting items by date
	 * shortenText - used to shorten a string, to nearest word
	 */

	private function shrinkUrls($string) {
		// @todo make this work with https and wwww.
		return preg_replace_callback('"\b(http://\S+)"', array(
			'SocialFeed', 'shrinkUrl'
		), $string);
	}

	private function shrinkUrl($url) {

		$parts = parse_url($url[0]);
		return '<a href="' . $url[0] . '" rel="external">' . $parts['host'] . '</a>';
	}

	private static function cmpdate($a, $b) {

		if ($a['sent'] == $b['sent'])
			return 0;

		return ($a['sent'] > $b['sent']) ? -1 : 1;

	}

	private function shortenText($s, $length) {
		// @todo make this work on word boundary so that it removes a trailing , . or whatever 
		if (mb_strlen($s) > $length) {
			$s = substr($s, 0, (140 - 3));
			$s = preg_replace('/ [^ ]*$/', '...', $s);
		}

		return $s;
	}
}

