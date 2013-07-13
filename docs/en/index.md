Quickstart
==========

This extension is here to provide cache storage service, using [Redis](http://redis.io)


Installation
-----------

This extension requires you to install [Redis database](http://redis.io) at least at version 2.6
 and [PHP Redis extension](https://github.com/nicolasff/phpredis/), ideally the latest dev version.

The best way to install Kdyby/Redis is using  [Composer](http://getcomposer.org/):

```sh
$ composer require kdyby/redis:@dev
```

With dev Nette, you can enable the extension using your neon config.

```yml
extensions:
	redis: Kdyby\Redis\DI\RedisExtension
```

If you're using stable Nette, you have to register it in `app/bootstrap.php`

```php
Kdyby\Redis\DI\RedisExtension::register($configurator);

return $configurator->createContainer();
```


Minimal configuration
---------------------

By default, the extension doesn't do much, but trying to reach the database server.
There are three main configuration options, that when enabled, each one replaces or configures the original service provided by Nette.

```yml
redis:
	journal: on
	storage: on
	session: on
```


Journal
-------

This is the greatest bottleneck of Nette's caching layer. Not that the default implementation would be wrong, it's just too simple for bigger applications.

Enabling Redis journal will register `Kdyby\Redis\RedisLuaJournal` as implementation for `Nette\Caching\Storages\IJournal`
that is automatically autowired to all default cache storage services.

Journal provides simple storing of cache entries metadata, namely tags and priorities, and can invalidate cache entries based on this metadata.

This journal is implemented using Lua scripts, that are executed by the redis database itself with given arguments.
This drastically improves performance, as all the heavy lifting is done purely in database with no additional network latency.


Storage
-------

Cache storage are for saving data itself, their expiration values and optionally other invalidation dependencies.
Nette uses Storage to save all it's metadata, from robot loader indexes to templates cache macros.

All extensions for Nette that are caching data, should prefer using `Nette\Caching\IStorage` as cache storage provider,
therefore enabling this storage will have them all benefit from Redis.

Enabling Redis storage will register `Kdyby\Redis\RedisStorage` as implementation for `Nette\Caching\IStorage`.


Session
-------

Session contents have to be stored somewhere. By default, they're stored in filesystem,
and when session is stored in filesystem, it has to be regularly cleaned by garbage collector.
When you reach hundreds of thousands of sessions, this can regularly block your application, for literary seconds, when session is started.

When you store session in Redis, this problem disappears, because it's optimised inmemory database, not a filesystem.  Also, Redis handles keys expiration automatically.

Enabling Redis session will change the "save path" option on `Nette\Http\Session`, utilizing the internal php mechanism.
The PHP Extension which is this Nette extension based on, is handling this mechanism in it's own.


Session best practices
--------------

Redis enables you to have multiple databases, just like mysql. This is pretty neat,
because  we can configure this extension, to use one database for session and one for cache and journal.
Then you can easily flush whole database with cache on deploy and you won't have to worry about possibly deleting our session.

The default database is `0`, so when you configure the session to be stored in database `1`, you should be in good shape.

```yml
redis:
	session: {database: 1}
```

Then on deploy simply call from console `redis-cli -n 0 flushdb` and it will delete all the cache.
