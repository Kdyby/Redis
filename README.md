Kdyby/Redis [![Build Status](https://secure.travis-ci.org/Kdyby/Redis.png?branch=master)](http://travis-ci.org/Kdyby/Redis)
===========================


Requirements
------------

Kdyby/Redis requires PHP 5.3.2 or higher.

- [Nette Framework 2.0.x](https://github.com/nette/nette)
- [Redis](http://redis.io)
- [php redis extension](https://github.com/nicolasff/phpredis/)


Installation
------------

* Compile & Install [latest stable Redis](http://redis.io/download)
* Compile & Install [latest stable phpredis](https://github.com/nicolasff/phpredis/)
* Install Kdyby/Redis to your project is using  [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/redis:~2.0
```

If you like to live on the edge, you can install dev version of kdyby/redis, that is compatible with dev version of Nette Framework

```sh
$ composer require kdyby/redis:@dev
```

* Register Compiler extension in config.neon

```yml
extensions:
  redis: Kdyby\Redis\DI\RedisExtension
```

* Configure the extension - enable Redis handlers

```yml
redis:
  journal: on
  session: on
  storage: on
  debugger: off
```




-----

Homepage [http://www.kdyby.org](http://www.kdyby.org) and repository [http://github.com/kdyby/Redis](http://github.com/kdyby/Redis).
