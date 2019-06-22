# Crawler

[![Build Status](https://travis-ci.org/PureLandFlying/Crawler.svg?branch=master)](https://travis-ci.org/PureLandFlying/Crawler)

##Dependencies
- Redis
- php >= 7.1

##Installation
```bash
git clone https://github.com/PureLandFlying/Crawler.git
cd Crawler
composer install
php bin/console app:crawler --ProductWorkerCount=10 --ListWorkerCount=2 --ListPageCount=1 --ProductCount=100
```

##Environment variables
- <code>.env</code> - Environment variables can be set in this file

##Run tests, and generate the code coverage reporting
```bash
vendor\bin\simple-phpunit.bat --coverage-html coverage
```
