<?php

namespace Aranea;

class Fetcher
{
	const EXCEPTION_URL_SCHEME = 1;

	const EXCEPTION_URL_HOSTNAME = 2;

	const EXCEPTION_OUTPUT_DIR_MISSING = 3;

	const EXCEPTION_OUTPUT_DIR_PERMISSION = 4;

	const EXCEPTION_OUTPUT_DIR_NONEMPTY = 5;

	const EXCEPTION_CURL = 6;

	public static $connectTimeout = 1;

	public static $debug = false;

	public static $ignoreNoFollow = false;

	public static $recursive = false;

	public static $followLocation = true;

	public static $maxDepth = 5;

	public static $maxUrls = 1000;

	public static $maxRedirect = 3;

	public static $name = 'Aranea';

	public static $quiet = false;

	public static $outputDirectory = '';

	public static $spanHosts = false;

	public static $timeout = 3;

	public static $userAgent = 'Mozilla/5.0 (compatible; Aranea; +https://github.com/AliasIO/Aranea)';

	public static $verbose = false;

	public static $wait = 0;

	private static $ipAddresses = [];

	private static $urls = [];

	private static $depth = 0;

	private static $robotstxt = [];

	public static function fetch($url = '') {
		$url = self::parseUrl($url);

		if ( !$url['scheme'] ) {
			throw new Exception('Invalid URL specified (missing scheme)', self::EXCEPTION_URL_SCHEME);
		}

		if ( !$url['host'] ) {
			throw new Exception('Invalid URL specified (missing hostname)', self::EXCEPTION_URL_HOSTNAME);
		}

		if ( self::$outputDirectory ) {
			if ( !is_dir(self::$outputDirectory) ) {
				throw new Exception('Output directory does not exist', self::EXCEPTION_OUTPUT_DIR_MISSING);
			}

			if ( !is_writable(self::$outputDirectory) ) {
				throw new Exception('Output directory is not writeable', self::EXCEPTION_OUTPUT_DIR_PERMISSION);
			}

			$handle = opendir(self::$outputDirectory);

			while ( $file = readdir($handle) ) {
				if ( $file != '.' && $file != '..' ) {
					throw new Exception('Output directory is not empty', self::EXCEPTION_OUTPUT_DIR_NONEMPTY);

					break;
				}
			}

			closedir($handle);
		}

		self::$depth           = 0;
		self::$urls            = [];
		self::$ipAddresses     = [];
		self::$outputDirectory = rtrim(self::$outputDirectory, '/');

		self::fetchRecursive($url);
	}

	private static function fetchRecursive(array $url = []) {
		if ( self::$outputDirectory ) {
			if ( file_exists(self::$outputDirectory . '/' . sha1(self::unparseUrl($url))) ) {
				return;
			}

			file_put_contents(self::$outputDirectory . '/' . sha1(self::unparseUrl($url)), json_encode($response));
		} else {
			if ( in_array(self::unparseUrl($url), self::$urls) ) {
				return;
			}

			self::$urls[] = self::unparseUrl($url);
		}

		$response = self::fetchUrl($url);

		$response->links = self::extractLinks($response->url, $response->body);

		$response->links = array_map(function($link) use($url) {
			return self::absoluteUrl($url, $link);
		}, $response->links);

		$response->links = array_unique($response->links, SORT_REGULAR);

		foreach ( $response->links as $i => &$link ) {
			if ( $link['scheme'] != 'http' && $link['scheme'] != 'https' ) {
				unset($response->links[$i]);

				continue;
			}

			if ( !self::$spanHosts && $link['host'] != $url['host'] ) {
				unset($response->links[$i]);

				continue;
			}

			if ( !self::$ignoreNoFollow && !self::urlAllowed($link) ) {
				unset($response->links[$i]);

				continue;
			}
		}

		if ( !self::$quiet ) {
			echo $response->http_code . ' ' . $response->ip_address . ' ' . self::unparseUrl($response->url) . ( self::$verbose ? ' ' . json_encode($response) : '' ) . "\n";
		}

		if ( self::$recursive ) {
			foreach ( $response->links as $link ) {
				if ( self::$wait ) {
					usleep(self::$wait * 1000000);
				}

				self::$depth ++;

				try {
					if ( self::$depth <= self::$maxDepth && count(self::$urls) < self::$maxUrls ) {
						self::fetchRecursive($link);
					}
				} catch ( Exception $e ) {
					if ( !self::$quiet ) {
						fwrite(STDERR, $e->getMessage() . "\n");
					}
				}

				self::$depth --;
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
			throw new Exception($error, self::EXCEPTION_CURL);
		}

		$response = (object) curl_getinfo($ch);

		curl_close($ch);

		$response->body = substr($result, $response->header_size);

		$headers = substr($result, 0, $response->header_size);

		$response->headers = [];

		foreach ( explode("\r\n", $headers) as $i => $header ) {
			if ( strstr($header, ': ') !== false ) {
				list ($key, $value) = explode(': ', $header);

				$response->headers[$key] = $value;
			}
		}

		$response->url = self::parseUrl($response->url);

		if ( !isset(self::$ipAddresses[$response->url['host']]) ) {
			self::$ipAddresses[$response->url['host']] = gethostbyname($response->url['host']);
		}

		$response->ip_address = self::$ipAddresses[$response->url['host']];

		return $response;
	}

	private static function extractLinks($url, $html = '') {
		$links = [];

		$doc = new \DOMDocument;

		@$doc->loadHTML($html);

		foreach ( $doc->getElementsByTagName('a') as $anchor ) {
			if ( $link = $anchor->getAttribute('href') ) {
				if ( !preg_match('/^[a-z]+:[^\/]/i', $link) ) { // E.g. javascript:, mailto:
					if ( $anchor->getAttribute('rel') != 'nofollow' || self::$ignoreNoFollow ) {
						$links[] = self::parseUrl($link, $url['scheme']);
					}
				}
			}
		}

		$links = array_unique($links, SORT_REGULAR);

		return $links;
	}

	public static function parseUrl($url = '', $parentScheme = null) {
		// Protocol relative URL
		if ( preg_match('/^\/\//', $url) ) {
			$url = $parentScheme . ':' . $url;
		}

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

		return $url;
	}

	public static function unparseUrl(array $url = []) {
		return $url['scheme'] . '://' . $url['host'] . ( $url['port'] ? ':' . $url['port'] : '' ) . '/' . ltrim($url['path'], '/') . ( $url['query'] ? '?' . $url['query'] : '' ) . ( $url['fragment'] ? '#' . $url['fragment'] : '' );
	}

	public static function absoluteUrl(array $url = [], array $link = []) {
		if ( $link['host'] ) {
			// URL is already absolute
			if ( $link['scheme'] ) {
				return $link;
			}

			// Protocol agnostic URL
			$link['scheme'] = $url['scheme'];
			$link['host']   = ltrim($url['host'], '/');

			return $link;
		}

		$link['scheme'] = $url['scheme'];
		$link['host']   = $url['host'];

		// Remove the last path component
		if ( substr($link['path'], 0, 1) != '/' ) {
			$link['path'] = rtrim(preg_replace('/^(.*\/)[^\/]+$/', '\1', $url['path']), '/') . '/' . $link['path'];
		}

		// Resolve directory paths
		$parents = [];

		foreach(explode('/', $link['path']) as $dir) {
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

		$link['path'] = '/' . ltrim(implode('/', $parents) . ( substr($link['path'], -1) == '/' ? '/' : '' ), '/');

		return $link;
	}

	public static function urlAllowed(array $url = []) {
		if ( self::$ignoreNoFollow ) {
			return true;
		}

		$applies = false;

		if ( !isset(self::$robotstxt[$url['host']]) ) {
			self::$robotstxt[$url['host']] = '';

			$robotstxtUrl = $url;

			$robotstxtUrl['path']     = 'robots.txt';
			$robotstxtUrl['query']    = '';
			$robotstxtUrl['fragment'] = '';

			if ( self::$debug ) {
				echo '[debug] Fetching ' . self::unparseUrl($robotstxtUrl) . "\n";
			}

			try {
				$response = self::fetchUrl($robotstxtUrl);

				self::$robotstxt[$url['host']] = $response->http_code == 200 ? $response->body : '';

				if ( self::$debug ) {
					echo '[debug] ' . $response->http_code . ' ' . self::unparseUrl($response->url) . "\n";
				}
			} catch ( Exception $e ) {
				if ( !self::$quiet ) {
					fwrite(STDERR, $e->getMessage() . "\n");
				}
			}
		}

		foreach ( explode("\n", self::$robotstxt[$url['host']]) as $line ) {
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
