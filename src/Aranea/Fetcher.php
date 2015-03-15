<?php

namespace Aranea;

class Fetcher
{
	static $connectTimeout = 1;

	static $ignoreNoFollow = false;

	static $recursive = false;

	static $followLocation = true;

	static $maxDepth = 5;

	static $maxRedirect = 3;

	static $name = 'Aranea';

	static $spanHosts = false;

	static $timeout = 3;

	static $userAgent = 'Mozilla/5.0 (compatible; Aranea; +https://github.com/AliasIO/Aranea)';

	static $wait = 0;

	public static function fetch(array $url = [], array &$urls = [], &$depth = 0, $robotstxt, $callback) {
		if ( empty($urls) ) {
			$urls[] = $url;
		}

		$response = self::fetchUrl($url);

		$response->links = self::extractLinks($response->url, $response->body);

		foreach ( $response->links as $i => &$link ) {
			$link = self::absoluteUrl($url, $link);

			if ( $link['scheme'] != 'http://' && $link['scheme'] != 'https://' ) {
				unset($response->links[$i]);
			}

			if ( !self::$spanHosts && $link['host'] != $url['host'] ) {
				unset($response->links[$i]);
			}

			if ( !self::$ignoreNoFollow && !self::urlAllowed($link, $robotstxt) ) {
				unset($response->links[$i]);
			}

			if ( in_array(self::unparseUrl($link), $urls) ) {
				unset($response->links[$i]);
			}
		}

		if ( is_callable($callback) ) {
			call_user_func_array($callback, array(&$response));
		}

		if ( self::$recursive ) {
			foreach ( $response->links as $link ) {
				if ( self::$wait ) {
					usleep(self::$wait * 1000000);
				}

				$urls[] = self::unparseUrl($link);

				$depth ++;

				try {
					if ( $depth <= self::$maxDepth ) {
						self::fetch($link, $urls, $depth, $robotstxt, $callback);
					}
				} catch ( Exception $e ) {
					//
				}

				$depth --;
			}
		}
	}

	private static function fetchUrl(array $url = []) {
		$ch = curl_init();

		curl_setopt_array($ch, array(
			CURLOPT_AUTOREFERER    => true,
			CURLOPT_COOKIESESSION  => true,
			CURLOPT_CONNECTTIMEOUT => self::$connectTimeout,
			CURLOPT_FOLLOWLOCATION => self::$followLocation,
			CURLOPT_HEADER         => true,
			CURLOPT_MAXREDIRS      => self::$maxRedirect,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_TIMEOUT        => self::$timeout,
			CURLOPT_URL            => self::unparseUrl($url),
			CURLOPT_USERAGENT      => self::$userAgent,
			));

		$result = curl_exec($ch);

		if ( $error = curl_error($ch) ) {
			throw new Exception($error);
		}

		$response = (object) curl_getinfo($ch);

		curl_close($ch);

		$response->body = substr($result, $response->header_size);

		$headers = substr($result, 0, $response->header_size);

		$response->headers = [];

		foreach ( explode("\r\n", $headers) as $i => $header ) {
			if ( strpos($header, ':') !== false ) {
				list ($key, $value) = explode(': ', $header);

				$response->headers[$key] = $value;
			}
		}

		$response->url = self::parseUrl($response->url);

		return $response;
	}

	private static function extractLinks($url, $html = '') {
		$links = [];

		$dom = new \DOMDocument;

		@$dom->loadHTML($html);

		foreach ( $dom->getElementsByTagName('a') as $anchor ) {
			if ( $link = $anchor->getAttribute('href') ) {
				if ( !preg_match('/^[a-z]+:[^\/]/i', $link) ) { // E.g. javascript:, mailto:
					if ( $anchor->getAttribute('rel') != 'nofollow' || self::$ignoreNoFollow ) {
						$links[] = self::parseUrl($link);
					}
				}
			}
		}

		$links = array_unique($links, SORT_REGULAR);

		return $links;
	}

	public static function parseUrl($url = '') {
		if ( !preg_match('/^[a-z]+:\/\//', $url) ) {
			$slash = substr($url, 0, 1) == '/' ? '/' : '';

			$url = 'fake://fake/' . ltrim($url, '/');

			$url = parse_url($url) ?: [];

			$url['scheme'] = '';
			$url['host']   = '';
			$url['path']   = $slash . ltrim($url['path'], '/');
		} else {
			$url = parse_url($url) ?: [];
		}

		$url = array_merge(array('scheme' => '', 'host' => '', 'port' => '', 'path' => '', 'query' => '', 'fragment' => ''), $url);

		if ( !$url['path'] ) {
			$url['path'] = '/';
		}

		$url['scheme']   = $url['scheme']   ? $url['scheme'] . '://' : '';
		$url['port']     = $url['port']     ? ':' . $url['port']     : '';
		$url['query']    = $url['query']    ? '?' . $url['query']    : '';
		$url['fragment'] = $url['fragment'] ? '#' . $url['fragment'] : '';

		return $url;
	}

	public static function unparseUrl(array $url = []) {
		return $url['scheme'] . $url['host'] . $url['port'] . $url['path'] . $url['query'] . $url['fragment'];
	}

	public static function absoluteUrl(array $url = [], array $link = []) {
		if ( !$url['scheme'] ) {
			throw new Exception('URL has no scheme');
		}

		if ( !$url['host'] ) {
			throw new Exception('URL has no hostname');
		}

		$path = $link['path'];

		if ( $link['host'] ) {
			// URL is already absolute
			if ( $link['scheme'] ) {
				return $link;
			}

			// Protocol agnostic URL
			return self::parseUrl($url['scheme'] . ltrim($link['host'], '//') . $link['port'] . $link['path'] . $link['query'] . $link['fragment']);
		}

		// Remove the last path component
		if ( substr($path, 0, 1) != '/' ) {
			$path = rtrim(preg_replace('/^(.*\/)[^\/]+$/', '\1', $url['path']), '/') . '/' . $path;
		}

		// Resolve directory paths
		$parents = [];

		foreach(explode('/', $path) as $dir) {
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

		$path = '/' . ltrim(implode('/', $parents) . ( substr($path, -1) == '/' ? '/' : '' ), '/');

		return self::parseUrl($url['scheme'] . $url['host'] . $url['port'] . $path . $link['query'] . $link['fragment']);
	}

	public static function urlAllowed(array $url = [], $robotstxt = '') {
		$applies = false;

		foreach ( explode("\n", $robotstxt) as $line ) {
			if ( preg_match('/^\s*User-agent:(.*)/i', $line, $match) ) {
				$agent = trim($match[1]);

				$applies = $agent == '*' || $agent == self::$name;
			}

			if ( $applies ) {
				if ( preg_match('/^\s*Disallow:(.*)/i', $line, $match) ) {
					$rule = trim($match[1]);

					if ( preg_match('/^' . preg_quote($rule, '/') . '/', $url['path']) ) {
						return false;
					}
				}
			}
		}

    return true;
	}
}
