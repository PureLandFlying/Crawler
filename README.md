# Crawler

[![Build Status](https://travis-ci.org/PureLandFlying/Crawler.svg?branch=master)](https://travis-ci.org/PureLandFlying/Crawler)

## Dependencies
- Redis
- php >= 7.1

## Installation
```bash
git clone https://github.com/PureLandFlying/Crawler.git
cd Crawler
composer install
php bin/console app:crawler --ProductWorkerCount=10 --ListWorkerCount=2 --ListPageCount=1 --ProductCount=100
```

## Environment variables
- <code>.env</code> - Environment variables can be set in this file

## Run tests, and generate the code coverage reporting
```bash
vendor/bin/simple-phpunit --coverage-html coverage
```
the reporting is generated in coverage folder

## Docker (Recommended)
~~~bash
git clone https://github.com/PureLandFlying/Crawler.git
cd Crawler
echo "REDIS_URL=redis://redis" >> .env.test 
docker-compose -f docker\docker-compose.yml build
docker-compose -f docker\docker-compose.yml up
~~~
It will generate two items in Crawler folder:
~~~
- products.json (file)
- coverage (folder)
~~~
coverage is the test coverage reporting