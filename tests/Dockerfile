FROM base/archlinux

RUN pacman --sync --refresh --noconfirm --noprogressbar --quiet
RUN pacman --sync --noconfirm --noprogressbar --quiet php xdebug php-mongo git openssh

ADD provisioning/php/php-extensions.ini /etc/php/conf.d/extensions.ini
ADD provisioning/php/xdebug.ini /etc/php/conf.d/xdebug.ini
ADD provisioning/set-env.sh /set-env.sh

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

VOLUME ["/code"]
WORKDIR /code

ENTRYPOINT ["/set-env.sh"]
CMD ["/code/build.php"]
