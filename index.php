<?php

namespace Aranea;

require 'vendor/autoload.php';

$help = <<<EOF
Usage: php index.php -u <url>
EOF;

try {
	$opts = getopt('u:hHri', array(
		'url:',
		'help',
		'span-hosts',
		'recursive',
		'ignore-nofollow'
		));

	$url = isset($opts['url']) ? $opts['url'] : ( isset($opts['u']) ? $opts['u'] : '' );

	Fetcher::$spanHosts      = isset($opts['span-hosts'])      || isset($opts['H']);
	Fetcher::$recursive      = isset($opts['recursive'])       || isset($opts['r']);
	Fetcher::$ignoreNoFollow = isset($opts['ignore-nofollow']) || isset($opts['i']);

	if ( isset($opts['help']) || isset($opts['h']) || !$url ) {
		throw new Exception($help);
	}

	$url = Fetcher::parseUrl($url);

	if ( empty($url['scheme']) ) {
		throw new Exception('Invalid URL specified');
	}

	// Databse connection
	$dbh = new \PDO('sqlite::memory:');

	$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

	$sql = file_get_contents('db/schema.sql');

	$dbh->exec($sql);

	$robotstxt = file_get_contents($url['scheme'] . $url['host'] . $url['port'] . '/robots.txt');

	Fetcher::fetch($url, $robotstxt, function(&$response) use($dbh) {
		echo $response->http_code . ' ' . Fetcher::unparseUrl($response->url) . "\n";

		foreach ( $response->links as $i => $link ) {
			if ( isset($link) ) {
				$linkString = Fetcher::unparseUrl($link);

				$sth = $dbh->prepare('INSERT INTO urls ( url ) VALUES ( :url );');

				$sth->bindParam('url', $linkString, \PDO::PARAM_STR);

				try {
					$sth->execute();
				} catch ( \PDOException $e ) {
					// URL has already been processed
					unset($response->links[$i]);
				}
			}
		}
	});
} catch ( Exception $e ) {
	echo $e->getMessage() . "\n";

	exit(1);
}

exit(0);
