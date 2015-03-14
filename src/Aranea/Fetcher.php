<?php

namespace Aranea;

class Fetcher
{
	static $followLocation = true;

	static $maxRedirs = 3;

	static $timeout = 3;

	public static function fetchRecursive($url = '', $callback) {
		echo $url . "\n";

		self::fetch($url, function(&$response) use ($url, $callback) {
			if ( is_callable($callback) ) {
				call_user_func_array($callback, array(&$response));
			}

			foreach ( $response->links as $link ) {
				self::fetchRecursive($link, $callback);
			}
		});
	}

	public static function fetch($url = '', $callback) {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Aranea)',
			CURLOPT_FOLLOWLOCATION => self::$followLocation,
			CURLOPT_HEADER         => true,
			CURLOPT_MAXREDIRS      => self::$maxRedirs,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => self::$timeout,
			CURLOPT_SSL_VERIFYPEER => false,
		));

		$result = curl_exec($ch);

		if ( $error = curl_error($ch) ) {
			throw new Exception($error);
		}

		$response = new \StdClass();

		$response->info = curl_getinfo($ch);

		curl_close($ch);

		$response->body = substr($result, $response->info['header_size']);

		$headers = substr($result, 0, $response->info['header_size']);

		$response->headers = [];

		foreach ( explode("\r\n", $headers) as $i => $header ) {
			if ( strpos($header, ':') !== false ) {
				list ($key, $value) = explode(': ', $header);

				$response->headers[$key] = $value;
			}
		}

		$response->links = self::listLinks($response->info['url'], $response->body);

		if ( is_callable($callback) ) {
			call_user_func_array($callback, array(&$response));
		}
	}

	public static function listLinks($url, $html = '') {
		$links = [];

		$dom = new \DOMDocument;

		@$dom->loadHTML($html);

		foreach ( $dom->getElementsByTagName('a') as $anchor ) {
			if ( $link = $anchor->getAttribute('href') ) {
				if ( !preg_match('/^[a-z]+:/i', $link) ) {
					$links[] = self::rel2abs($url, $link);
				}
			}
		}

		return $links;
	}

	public static function rel2abs($url = '', $href = '') {
		$url  = self::parseUrl($url);
		$href = self::parseUrl($href);

		if ( !$url['scheme'] ) {
			throw new Exception('URL has no scheme');
		}

		if ( !$url['host'] ) {
			throw new Exception('URL has no hostname');
		}

		$path = $href['path'];

		if ( $href['host'] ) {
			if ( $href['scheme'] ) {
				return $href['scheme'] . '://' . $href['host'] . $href['port'] . $path . $href['query'] . $href['fragment'];
			}

			return $url['scheme'] . '://' . ltrim($href['host'], '//') . $href['port'] . $path . $href['query'] . $href['fragment'];
		}

		if ( strpos($path, '/') !== 0 ) {
			$path = preg_replace('/^(.*\/)[^\/]+$/', '\1', rtrim($url['path'], '/')) . '/' . $path;
		}

		$dirs = explode('/', $path);

		$parents = [];

		foreach( $dirs as $dir) {
			switch( $dir) {
				case '':
				case '.':
					break;
				case '..':
					array_pop($parents);

					break;
				default:
					$parents[] = $dir;

					break;
			}
		}

		$path = '/' . ltrim(implode('/', $parents) . '/', '/');

		return $url['scheme'] . '://' . $url['host'] . $url['port'] . $path . $href['query'] . $href['fragment'];
	}

	private static function parseUrl($url) {
		if ( !preg_match('/^[a-z]+:\/\//', $url) ) {
			$slash = strpos($url, '/') == 0 ? '/' : '';

			$url = 'fake://fake/' . ltrim($url, '/');

			$url = parse_url($url);

			$url['scheme'] = '';
			$url['host']   = '';
			$url['path']   = $slash . ltrim($url['path'], '/');
		} else {
			$url = parse_url($url);
		}

		$url = array_merge(array('scheme' => '', 'host' => '', 'port' => '', 'path' => '', 'query' => '', 'fragment' => ''), $url);

		$url['port']     = $url['port']     ? ':' . $url['port']     : '';
		$url['query']    = $url['query']    ? '?' . $url['query']    : '';
		$url['fragment'] = $url['fragment'] ? '#' . $url['fragment'] : '';

		return $url;
	}
}
