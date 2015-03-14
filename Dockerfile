FROM phusion/baseimage

MAINTAINER Elbert Alias <elbert@alias.io>

ENV PROJECT_FOLDER /usr/local/aranea/

ENV DEBIAN_FRONTEND noninteractive

RUN mkdir -p $PROJECT_FOLDER

ADD src/* $PROJECT_FOLDER

WORKDIR $PROJECT_FOLDER

# Apt
RUN \
	apt-get update && apt-get install -y \
	curl \
	php5-cli \
	php5-curl \
	php5-sqlite \
	&& apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Composer
RUN \
	curl -sS https://getcomposer.org/installer | php && \
	php composer.phar install

CMD ["/sbin/my_init"]
