# Contribution Guidelines

We welcome you to report [issues](/../../issues) or submit [pull requests](/../../pulls).  While the below guidelines
are necessary to get code merged, you can submit pull requests that do not adhere to them and we will try to take care
of them in our spare time.  We are a smallish group of developers, though, so if you can make sure the build is passing
100%, that would be very useful.

We recommend including details of your particular usecase(s) with any issues or pull requests.  We love to hear how our
libraries are being used and we can get things merged in quicker when we understand its expected usage.

## Running the build
To run the build do the following:
```sh
composer install
./vendor/bin/phpunit
./vendor/bin/phpcs
```

This build enforces 100% [PHPUnit](http://www.phpunit.de) code coverage and 0 errors for the [coding standard](http://www.php-fig.org/psr/psr-2/).

Failures in either will keep us from merging the pull request.
