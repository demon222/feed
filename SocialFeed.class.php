<?php

/**
 *
 * Gets feeds from facebook and twitter
 * @author ianbarker
 *
 */
class SocialFeed {

	private static $version = '1.0';

	protected static $upateFrequency = 25;
	protected static $logging = false;

	// db stuff
	private $db = null;

	// twitter stuff
	protected static $tweetTable = 'tweet';
	protected static $twitterFromUsers = array();
	protected static $twitterToUsers = array();
	protected static $twitterHashTags = array();
	protected static $twitterKeywords = array();
	protected static $tweetLanguage = 'en';
	protected static $hideReTweets = true;
	protected $tweetTableOk = false;

	// facebook stuff
	protected static $facebookTable = 'facebook_item';
	protected static $facebookFeedId;
	protected $facebookTableOk = false;

	public static function setLogging($logging) {
		if (!is_bool($logging)) throw new Exception('SocialFeed::setLogging requires a boolean parameter');
		self::$logging = $logging;
	}

	public static function addFromTwitterUser($user) {
		self::$twitterFromUsers[] = $user;
	}

	public static function addToTwitterUser($user) {
		self::$twitterToUsers[] = $user;
	}

	public static function addTwitterHashTag($tag) {
		self::$twitterHashTags[] = str_replace('#', '', $tag);
	}

	public static function addTwitterKeyword($keyword) {
		self::$twitterKeywords[] = $keyword;
	}

	/**
	 * Sets the tweet language
	 * @param string $language 2 character iso code e.g. 'en'
	 */
	public static function setTweetLanguage($language) {
		self::$tweetLanguage = $language;
	}

	public static function setHideReTweets($hide) {
		self::$hideReTweets = $hide;
	}

	public static function setFacebookId($id) {
		if (!is_string($id)) throw new Exception('Facebook id needs to be a string, PHP doesn\'t like such big numbers very much');
		self::$facebookFeedId = $id;
	}

	/**
	 * Sets the update frequency for the feeds
	 * @param int $seconds
	 */
	public static function setUpdateFrequency($seconds) {
		$seconds = (int) $seconds;
		if ($seconds > 0) self::$upateFrequency = $seconds;
	}

	public static function setFacebookTable($table) {
		self::$facebookTable = $table;
	}

	public static function setTweetTable($table) {
		self::$tweetTable = $table;
	}

	protected function checkTweetTable() {
		$query = " CREATE TABLE IF NOT EXISTS
				  `".self::$tweetTable."` (
				  `id` bigint(64) unsigned NOT NULL DEFAULT '0',
				  `text` text NOT NULL,
				  `sent` datetime NOT NULL,
				  `received` datetime NOT NULL,
				  `search_hash` varchar(32) NOT NULL,
				  `from` varchar(60) NOT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		$result = $this->db->query($query);
		$this->tweetTableOk = ($result) ? true : false;
	}

	protected function checkFacebookTable() {
		$query = " CREATE TABLE IF NOT EXISTS
				  `".self::$facebookTable."` (
				  `id` bigint(64) NOT NULL,
				  `from` varchar(120) NOT NULL,
				  `type` varchar(20) NOT NULL,
				  `content` text NOT NULL,
				  `link` varchar(256) NOT NULL,
				  `sent` datetime NOT NULL,
				  `received` datetime NOT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8; ";
		$result = $this->db->query($query);
		$this->facebookTableOk = ($result) ? true : false;
	}

	protected function updateFeeds() {

		// twitter first
		if (!empty(self::$twitterFromUsers)) {
			// search for tweets from specific users
			foreach (self::$twitterFromUsers as $user) {
				$parameters = array('from' => $user);
				$this->updateTweets($parameters);
			}
		}

		if (!empty(self::$twitterToUsers)) {
			// search for tweets to specific users
			foreach (self::$twitterToUsers as $user) {
				$parameters = array('to' => $user);
				$this->updateTweets($parameters);
			}
		}

		if (!empty(self::$twitterHashTags)) {
			foreach (self::$twitterHashTags as $tag) {
				// search for tweets with a specific hash tags
				$parameters = array('tag' => $tag);
				$this->updateTweets($parameters);
			}
		}

		if (!empty(self::$twitterKeywords)) {
			foreach (self::$twitterKeywords as $keyword) {
				// search for tweets with a specific hash tags
				$parameters = array('q' => $keyword);
				$this->updateTweets($parameters);
			}
		}

		// now facebook
		if (!empty(self::$facebookFeedId)) {
			$this->updateFacebookFeed(self::$facebookFeedId);
		}

	}

	public function __construct($host, $user, $password, $name) {
		$db = new mysqli($host, $user, $password, $name);
		if ($db->connect_error) throw new Exception('ERROR: '.$db->connect_error);
		$this->db = $db;

		// check the tables
		$this->checkFacebookTable();
		$this->checkTweetTable();

	}

	/**
	 *
	 * returns the most recent tweet
	 * if hash is used then it will have to have a specific hash
	 * this is used to make sure we're only updating from twitter when the current data is old
	 * @param string $hash
	 */
	private function getLatestStoredTweet($hash = '') {

		$sqlHash = (!empty($hash)) ? " AND search_hash = ".$db->safe($hash)." " : '';

		$query = " SELECT `id`,
						  `received`
						FROM `".self::$tweetTable."`
						WHERE 1
							{$sqlHash}
						ORDER BY `id` DESC
						LIMIT 1
					 ";
		$result = $this->db->query($query);
		return ($result) ? $result->fetch_row() : false;

	}

	/**
	 * Checks to see if we need to get tweets from twitter
	 * @param array $parameters
	 */
	private function updateTweets($parameters = array()) {

		$hash = md5(http_build_query($parameters, null, '&'));

		$latestTweet = self::getLatestStoredTweet($hash);

		if (strtotime($latestTweet['received']) < strtotime('-'.self::$upateFrequency.' seconds')) {

			// call statically so that we can use this statically also
			$tweets = self::getNewTweets($parameters, $latestTweet['id']);

			if (!empty($tweets->results)) {

				// log it
				self::log('Found '.count($tweets->results).' new tweets');

				// store the tweets that we got
				foreach ($tweets->results as $tweet) {
					$query = " REPLACE INTO `".self::$tweetTable."`
								SET `id` = ".$db->safe($tweet->id_str).",
									`from` = ".$db->safe($tweet->from_user).",
									`text` = ".$db->safe($tweet->text).",
									`sent` = '".date('c', strtotime($tweet->created_at))."',
									`received` = NOW(),
									`search_hash` = '".$hash."'
							 ";
					$this->db->query($query);
				}

			} else {

				self::log('Found no new tweets');

				if ($latestTweet['id'] > 0) {
					// update the received date to mark that we just checked
					$query = " UPDATE `".self::$tweetTable."`
								SET `received` = NOW()
								WHERE 1
								 AND `id` = ".$latestTweet['id']."
								 AND search_hash = '".$hash."'
								LIMIT 1
							 ";
					$this->db->query($query);

					self::log('Updated recieved date of '.$latestTweet['id'].' to now');

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
	private function getNewTweets($parameters = array(), $sinceId = 0) {

		// build the url
		$url = 'http://search.twitter.com/search.json';

		$default = array(
			'result_type' => 'recent',
			'lang' => self::$tweetLanguage,
			'rpp' => 100,
			'since_id' => $sinceId
		);
		$parameters = array_merge($default, $parameters);
		$url .= '?'.http_build_query($parameters, null, '&');

		self::log($url);

		// get the url
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, 'Toucan Twitter Search v'.self::$version);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);

		// return the decoded result
		return json_decode($result);

	}

	private function updateFacebookFeed() {

		$latestItem = self::getLatestStoredfacebookItem();

		if (strtotime($latestItem['received']) < strtotime('-'.self::$upateFrequency.' seconds')) {

			$feed = self::getFacebookFeed(strtotime($latestItem['received']));

			if (!empty($feed->data)) {

				foreach ($feed->data as $item) {

					$link = '';
					$content = '';
					// get the correct content
					switch ($item->type ) {
						case 'photo':
							$content = $item->picture;
							$link = $item->link;
							break;
						case 'status':
							$content = $item->message;
							break;
						case 'link':
							$content = $item->message;
							if (strlen($content) == 0) $content = $item->description;
							if (strlen($content) == 0) $content = $item->name;
							$link = $item->link;
							break;

					}

					$parts = explode("_", $item->id);
					$id = $parts[1];

					$query = " INSERT IGNORE INTO `".self::$facebookTable."`
								SET `id` = ".$db->safe($id).",
									`from` = ".$db->safe($item->from->name).",
									`type` = ".$db->safe($item->type).",
									`content` = ".$db->safe($content).",
									`link` = ".$db->safe($link).",
									`sent` = ".$db->safe($item->created_time).",
									`received` = ".$db->safe(date('Y-m-d H:i:s', strtotime('-'.self::$upateFrequency.' seconds')))."
							 ";
					$this->db->query($query);

				}

			} else {

				self::log('Found no new Facebook stuff');

				if ($latestItem['id'] > 0) {

					$query = " UPDATE `".self::$facebookTable."`
								SET received = NOW()
								WHERE 1
								 AND id = ".$latestItem['id']."
								LIMIT 1
							 ";
					$this->db->query($query);

					self::log('Updated recieved date of Facebook item '.$latestItem['id'].' to now');

				}

			}

		}

	}

	private function getFacebookFeed($since = false) {

		// build graph feed url
		$url = 'https://graph.facebook.com/'.self::$facebookFeedId.'/feed';
		if ($since) $url .= '?since='.$since;

		self::log($url);

		// get it
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, 'Toucan Facebook Feed Reader v'.self::$version);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		$result = curl_exec($ch);
		curl_close($ch);

		// return the decoded result
		return json_decode($result);

	}

	private function getLatestStoredfacebookItem() {

		$db = new Database();

		$query = " SELECT *
					FROM `".self::$facebookTable."`
					WHERE 1
					ORDER BY `received` DESC
					LIMIT 1
				 ";
		$result = $this->db->query($query);
		return $result->fetch_row();

	}

	/**
	 *
	 * Gets the latest data, will update from twitter and facebook as neccessary
	 * @param int $limit
	 * @param int $since
	 */
	public function getData($limit = 4, $since = 0) {

		// make sure the feeds are up to date
		$this->updateFeeds();

		$since = ((int) $since > 0) ? (int) $since : false;
		$limit = ((int) $limit > 0) ? (int) $limit : false;

		// get the tweets
		$tweets = $this->getTweets($limit, $since);

		// get the wall feed
		$items = $this->getWallItems($limit, $since);

		// merge the two feeds
		$feed = array_merge($tweets, $items);

		// sort by sent date
		usort($feed, 'cmpdate');

		$feed = ($limit > 0) ? array_slice($feed, 0, $limit) : $feed;

		// make sure there is at least one of each
		$tCount = 0;
		$fCount = 0;
		foreach ($feed as $item) {
			if ($item['type'] == 'facebook') $fCount++;
			if ($item['type'] == 'tweet') $tCount++;
		}

		if ($tCount == 0) {
			$feed = array_slice($feed, 0, -1);
			usort($tweets, 'cmpdate');
			array_push($feed, $tweets[0]);
		}

		if ($fCount == 0) {
			$feed = array_slice($feed, 0, -1);
			usort($items, 'cmpdate');
			array_push($feed, $items[0]);
		}

		return $feed;

	}

	/**
	 *
	 * Gets the latest tweets from the database
	 * @param int $limit
	 */
	private function getTweets($limit = 5, $since = 0) {

		$db = new Database();
		$sqlSince = ($since > 0) ? " AND sent > '".date('c', $since)."' " : '';
		$sqlHide = (self::$hideReTweets) ? " AND text NOT LIKE 'RT%' " : '';
		$limit = ($limit > 0) ? ' LIMIT '.$limit : '';

		$query = " SELECT `id`,
						  `from`,
						  `text`,
						  `sent`	
					 FROM `".self::$tweetTable."`
					 WHERE 1
					 	{$sqlSince}
					 	{$sqlHide}
					 ORDER BY `sent` DESC
					 {$limit}
				 ";
		//echo '<pre>'.$query.'</pre>';
		$tweets = $db->query($query, 'table');
		if ($tweets) {
			foreach ($tweets as & $tweet) {
				$tweet['type'] = 'tweet';
				$tweet['html'] = $this->linkItUp($tweet['text']);
				$tweet['sent'] = strtotime($tweet['sent']);
				$tweet['sent_format1'] = date('c', $tweet['sent']);
				$tweet['sent_format2'] = date('F j, Y', $tweet['sent']);
				unset($tweet['text']);
			}
		} else {
			$tweets = array();
		}
		return $tweets;
	}

	/**
	 *
	 * Gets the facebook wall posts from the database
	 * @param int $limit
	 */
	private function getWallItems($limit = 10, $since = 0) {

		$sqlSince = ($since > 0) ? " AND sent > '".date('c', $since)."' " : '';
		$limit = ($limit > 0) ? ' LIMIT '.$limit : '';

		$query = " SELECT *
					 FROM `".self::$facebookTable."`
					 WHERE 1
					 	{$sqlSince}
					 ORDER BY `sent` DESC
					 {$limit}
				 ";
		$result = $this->db->query($query);
		while ($row = $result->fetch_row()) {
			$rawItems[] = $row;
		}

		$items = array();
		foreach ($rawItems as $item) {

			switch ($item['type']) {
				case 'link':
					$html = $this->shrinkUrls($item['content']).'<br /><a href="'.$item['link'].'" target="_blank" rel="external">read more</a>';
					break;
				case 'photo':
					$html = '<br /><a href="'.$item['link'].'" target="_blank" rel="external"><img src="'.$item['content'].'" /></a>';
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
	 * Converts urls a string into html links
	 * @param string $string
	 */
	private function linkItUp($string) {

		// add standard links
		$string = preg_replace('"\b(http://\S+)"', '<a href="$1" target="_blank" rel="external">$1</a>', $string);

		// add @ links
		$string = preg_replace('/@([a-z0-9_-]+)/i', '<a href="http://twitter.com/$1" target="_blank" rel="external">@$1</a>', $string);

		// add # links
		$string = preg_replace('/#([a-z0-9_-]+)/i', '<a href="http://twitter.com/search?q=%23$1" target="_blank" rel="external">#$1</a>', $string);

		return $string;

	}

	/**
	 * Returns a string like 2 hours ago
	 * @deprecated it's better to use the javascript version as it will update in realtime
	 * @param int $time unix timestamp
	 */
	private function timesince($ptime) {

		$etime = time() - $ptime;

		if ($etime < 1) {
			return '0 seconds';
		}

		$a = array(12 * 30 * 24 * 60 * 60 => 'year',
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
				return $r.' '.$str.($r > 1 ? 's' : '');
			}
		}
	}

	private function shrinkUrls($string) {
		return preg_replace_callback('"\b(http://\S+)"', 'SocialFeed::shrinkUrl', $string);
	}

	private function shrinkUrl($url) {
		$parts = parse_url($url[0]);
		return '<a href="'.$url[0].'" target="_blank" rel="external">'.$parts['host'].'</a>';
	}

	private static function log($message) {
		if (self::$logging) {
			$fh = fopen($_SERVER['DOCUMENT_ROOT'].'/log/socialfeed_log', 'a');
			fwrite($fh, "[ ".date('d.m.Y H:i:s')." ]-[ {$message} ]\n");
			fclose($fh);
		}
	}
}

function cmpdate($a, $b) {

	if ($a['sent'] == $b['sent']) return 0;

	return ($a['sent'] > $b['sent']) ? -1 : 1;

}
