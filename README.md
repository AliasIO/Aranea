# Aranea

A general purpose web crawler.

```
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
```

Example output:

```
$ php index.php -u http://mozilla.org -r
200 https://www.mozilla.org/en-US/
200 https://www.mozilla.org/en-US/mission/
200 https://www.mozilla.org/en-US/about/
200 https://www.mozilla.org/en-US/products/
200 https://www.mozilla.org/en-US/contribute/
...
```
