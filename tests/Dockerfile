FROM nubs/phpunit

USER root

RUN pacman --sync --refresh --noconfirm --noprogressbar --quiet && pacman --sync --noconfirm --noprogressbar --quiet php-mongo

# Get around a bug with docker registry where the owner is root for the home
# user.  See https://github.com/docker/docker/issues/5892.
RUN chown -R build /home/build

USER build

ADD provisioning/set-env.sh /home/build/set-env.sh

ENTRYPOINT ["/home/build/set-env.sh"]
CMD ["./vendor/bin/phpunit"]
CMD ["./vendor/bin/phpcs"]
