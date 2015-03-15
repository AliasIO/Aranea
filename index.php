<?php

namespace Aranea;

require 'vendor/autoload.php';

$help = <<<EOF
Usage: php index.php -u <url>

Arguments:
  -h
  --help
      Print this help message.

  -H
  --span-hosts
      Enable spanning across hosts when doing recursive retrieving.

  -l <depth>
  --level <depth>
      Enable spanning across hosts when doing recursive retrieving.

  -r
  --recursive
      Turn on recursive retrieving. The default maximum depth is 5.

  -T <seconds>
  --timeout <seconds>
      Set the network timeout to <seconds> seconds.

  --connect-timeout <seconds>
      Set the network timeout to <seconds> seconds.

  -u <url>
  --url <url>
      Retrieve a URL.

  -w <seconds>
  --wait <seconds>
      Wait the specified number of seconds between the retrievals.
EOF;

try {
	$opts = getopt('hHl:rT:u:w:', array(
		'connect-timeout:',
		'help',
		'level:',
		'ignore-nofollow',
		'max-redirect:',
		'recursive',
		'span-hosts',
		'timeout:',
		'url:',
		'wait:',
		));

	$url = isset($opts['url']) ? $opts['url'] : ( isset($opts['u']) ? $opts['u'] : '' );

	if ( isset($opts['help']) || isset($opts['h']) || !$url ) {
		throw new Exception($help);
	}

	Fetcher::$ignoreNoFollow = isset($opts['ignore-nofollow']);
	Fetcher::$spanHosts      = isset($opts['span-hosts']) || isset($opts['H']);
	Fetcher::$recursive      = isset($opts['recursive']) || isset($opts['r']);

	if ( isset($opts['connect-timeout']) ) {
		Fetcher::$connectTimeout = $opts['connect-timeout'];
	}

	if ( isset($opts['max-redirect']) ) {
		Fetcher::$maxRedirect = $opts['max-redirect'];
	}

	if ( isset($opts['level']) || isset($opts['l']) ) {
		Fetcher::$maxDepth = isset($opts['level']) ? $opts['level'] : $opts['l'];
	}

	if ( isset($opts['timeout']) || isset($opts['T']) ) {
		Fetcher::$timeout = isset($opts['timeout']) ? $opts['timeout'] : $opts['T'];
	}

	if ( isset($opts['wait']) || isset($opts['w']) ) {
		Fetcher::$wait = isset($opts['wait']) ? $opts['wait'] : $opts['w'];
	}

	$url = Fetcher::parseUrl($url);

	if ( empty($url['scheme']) ) {
		throw new Exception('Invalid URL specified');
	}

	$robotstxt = file_get_contents($url['scheme'] . $url['host'] . $url['port'] . '/robots.txt');

	Fetcher::fetch($url, $urls = [], $depth = 0, $robotstxt, function(&$response) {
		echo $response->http_code . ' ' . Fetcher::unparseUrl($response->url) . "\n";
	});
} catch ( Exception $e ) {
	echo $e->getMessage() . "\n";

	exit(1);
}

exit(0);
