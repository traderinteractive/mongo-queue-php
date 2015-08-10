# Contribution Guidelines
We welcome you to report [issues](https://github.com/dominionenterprises/mongo-queue-php/issues) or submit
[pull requests](https://github.com/dominionenterprises/mongo-queue-php/pulls).  While the below guidelines are necessary to get code merged, you can
submit pull requests that do not adhere to them and we will try to take care of them in our spare time.  We are a smallish group of developers,
though, so if you can make sure the build is passing 100%, that would be very useful.

We recommend including details of your particular usecase(s) with any issues or pull requests.  We love to hear how our libraries are being used
and we can get things merged in quicker when we understand its expected usage.

## Building
Our [build](build.php) runs the code through the [PSR2 coding standard](http://www.php-fig.org/psr/psr-2/) and through our
test suite.  Failures in either will keep us from merging the pull request.  The test suite MUST have 100% code coverage in the report.

## Travis CI
Our [Travis build](https://travis-ci.org/dominionenterprises/mongo-queue-php) executes the build above against pull requests.
