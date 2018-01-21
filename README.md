phpunit-patch-coverage
======================

A tool to generate coverage reports for a Git patch without
actually running the entire coverage report.

Purpose
-------

Running PHPUnit coverage reports with Xdebug can be extremely slow.
For example, the MediaWiki core coverage report takes around 2 hours
to run. This makes it hard for developers to get quick feedback on
their patches to see how they affected the overall coverage.

The goal of this is to be able to generate coverage reports for
Git patches without running the full coverage tests.

Implementation
--------------
We look at the files that were changed in the last commit. We identify
classes that were changed, as well as tests that were changed. We then
find all the tests that cover those classes, and run the tests with coverage
for those files.

Next we'll checkout the previous commit, and re-calculate to see which tests
should be run (@covers and modified files). We'll re-run the coverage, and
then diff the result!

There are probably plenty of edge cases where this won't work, but
I think it will do reasonably well.

Usage
-----
The current working directory must be your git repository.
With full options:
```
./vendor/bin/phpunit-patch-coverage check \
 --command "php vendor/bin/phpunit" \
 --sha1 HEAD
```

The options shown in the example above are the defaults, and do not need to
be specified again. You may find it useful to have xdebug disabled by
default, and then specify it at runtime with:
`php -d zend_extension=xdebug.so ...`.
Or if you have a PHPUnit wrapper (like MediaWiki), you can call that.

Security
--------
This is likely full of shell injection issues that need to be fixed. For now,
only run it on code that is reasonably trustworthy.

License
-------
phpunit-patch-coverage is (C) 2018 Kunal Mehta, under the terms of the GPL v3
or any later version. See COPYING for more details.
