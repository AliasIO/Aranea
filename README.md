# Aranea

A general purpose web crawler.

```
Usage: php index.php -u <url>

Arguments:
  -h
  --help
      Print this help message.

  --ignore-nofollow
      Ignore robots.txt and rel="nofollow" on links

  -H
  --span-hosts
      Enable spanning across hosts when doing recursive retrieving.

  -l <depth>
  --level <depth>
      Specify maximum recursion depth level.

  -o <directory>
  --output-directory <directory>
      Log retrieved data to files in a directory.

  -q
  --quiet
      Turn off regular output.

  -r
  --recursive
      Turn on recursive retrieving. The default maximum depth is 5.

  -T <seconds>
  --timeout <seconds>
      Set the network timeout to <seconds> seconds.

  --connect-timeout <seconds>
      Set the connect timeout to <seconds> seconds.

  -u <url>
  --url <url>
      Retrieve a URL.

  -v
  --verbose
      Turn on verbose output.

  -w <seconds>
  --wait <seconds>
      Wait the specified number of seconds between the retrievals.
```

Example output:

```
$ php index.php -u https://mozilla.org -r
200 63.245.215.20 https://www.mozilla.org/en-US/
200 63.245.215.20 https://www.mozilla.org/en-US/mission/
200 63.245.215.20 https://www.mozilla.org/en-US/about/
200 63.245.215.20 https://www.mozilla.org/en-US/products/
200 63.245.215.20 https://www.mozilla.org/en-US/contribute/
...
```

Using Docker

```
$ docker run --rm -it aliasio/aranea -u https://mozilla.org -r
```
