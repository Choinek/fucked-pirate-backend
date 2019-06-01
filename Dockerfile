FROM php:7.2-fpm

RUN apt-get update

RUN apt-get install vim -y && \
    apt-get install openssl -y && \
    apt-get install libssl-dev -y && \
    apt-get install wget -y  && \
    apt-get install curl -y

RUN cd /tmp && wget https://pecl.php.net/get/swoole-4.2.9.tgz && \
    tar zxvf swoole-4.2.9.tgz && \
    cd swoole-4.2.9  && \
    phpize  && \
    ./configure  --enable-openssl && \
    make && make install

RUN touch /usr/local/etc/php/conf.d/swoole.ini && \
    echo 'extension=swoole.so' > /usr/local/etc/php/conf.d/swoole.ini

# INSTALL COMPOSER
RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer
RUN composer self-update

RUN mkdir -p /app/data

WORKDIR /app

COPY ./app /app

ENTRYPOINT ["/app/init.sh"]

EXPOSE 80
CMD ["/usr/local/bin/php", "/app/index.php"]
