<?php

use Aranea\Fetcher as Fetcher;

require 'vendor/autoload.php';

$dbh = new \PDO('sqlite::memory:');

$dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents('db/schema.sql');

$dbh->exec($sql);

try {
	$url = 'https://alias.io';

	Fetcher::fetchRecursive($url, function(&$response) use($dbh, $url) {
		foreach ( $response->links as $i => $link ) {
			$link = preg_replace('/#.*$/', '', $link);

			$parsedUrl  = parse_url($url);
			$parsedLink = parse_url($link);

			if ( $parsedLink['scheme'] != 'http' && $parsedLink['scheme'] != 'https' ) {
				unset($response->links[$i]);

				continue;
			}

			if ( $parsedUrl['host'] != $parsedLink['host'] ) {
				unset($response->links[$i]);

				continue;
			}

			$sth = $dbh->prepare('INSERT INTO urls ( url ) VALUES ( :url );');

			$sth->bindParam('url', $link, \PDO::PARAM_STR);

			try {
				$sth->execute();
			}
			catch ( \PDOException $e ) {
				unset($response->links[$i]);
			}
		}
	});
} catch(Aranea\Exception $e) {
	echo $e->getMessage() . "\n";

	exit(1);
}

exit(0);
