FROM phusion/baseimage

MAINTAINER Elbert Alias <elbert@alias.io>

ENV PROJECT_FOLDER /usr/local/aranea/

ENV DEBIAN_FRONTEND noninteractive

RUN mkdir -p $PROJECT_FOLDER

ADD . $PROJECT_FOLDER

WORKDIR $PROJECT_FOLDER

# Apt
RUN \
	apt-get update && apt-get install -y \
	php-cli \
	php-dom \
	php-curl \
	&& apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Composer
RUN \
	curl -sS https://getcomposer.org/installer | php && \
	php composer.phar install

ENTRYPOINT ["php", "index.php"]
