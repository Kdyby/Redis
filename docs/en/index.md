# Quickstart

This extension is here to provide cache storage service, using [Redis](http://redis.io)


## Installation

* Compile & Install [latest stable Redis database](http://redis.io/download)
* Compile & Install [latest phpredis](https://github.com/nicolasff/phpredis/)
* Install Kdyby/Redis to your project is using  [Composer](http://getcomposer.org/):

You can install the extension using this command

```sh
$ composer require kdyby/redis
```

and enable the extension using your neon config.

```yml
extensions:
	redis: Kdyby\Redis\DI\RedisExtension
```


## Minimal configuration

By default, the extension doesn't do much, but trying to reach the database server.
There are four main configuration options, that when enabled, each one replaces or configures the original service provided by Nette.
CacheKey option for specifying cache namespace.
 
```yml
redis:
	journal: on
	storage: on
	session: on
	cacheKey: 'staging'
```


## Journal

This is the greatest bottleneck of Nette's caching layer. Not that the default implementation would be wrong, it's just too simple for bigger applications.

Enabling Redis journal will register `Kdyby\Redis\RedisLuaJournal` as implementation for `Nette\Caching\Storages\IJournal`
that is automatically autowired to all default cache storage services.

Journal provides simple storing of cache entries metadata, namely tags and priorities, and can invalidate cache entries based on this metadata.

This journal is implemented using Lua scripts, that are executed by the redis database itself with given arguments.
This drastically improves performance, as all the heavy lifting is done purely in database with no additional network latency.


## Storage

Cache storage are for saving data itself, their expiration values and optionally other invalidation dependencies.
Nette uses Storage to save all it's metadata, from robot loader indexes to templates cache macros.

All extensions for Nette that are caching data, should prefer using `Nette\Caching\IStorage` as cache storage provider,
therefore enabling this storage will have them all benefit from Redis.

Enabling Redis storage will register `Kdyby\Redis\RedisStorage` as implementation for `Nette\Caching\IStorage`.


### Locks in storage

This storage has locking mechanism, that is implemented using [a best practise for Redis](http://redis.io/commands/setnx), however, it's not even remotely perfect.

The problem is that Redis doesn't have native locks, and they have to be emulated.
You may (or may not) run into problems when you experience extreme traffic,
some of the threads die and the lock was not released and it's released after the timeout.
While the timeout is running, other users have to wait and the system may just collapse.

This normally shouldn't be a problem, but when is, you can disable the locking.
The downside is, that you will generate the cache repeatedly until it's saved for the first time,
but there never will be any locks waiting for timeout.

You can disable the locks using the configuration option on storage

```yml
redis:
	storage: {locks: off}
```


## Session

Session contents have to be stored somewhere. By default, they're stored in filesystem,
and when session is stored in filesystem, it has to be regularly cleaned by garbage collector.
When you reach hundreds of thousands of sessions, this can regularly block your application, for literary seconds, when session is started.

When you store session in Redis, this problem disappears, because it's optimised in-memory database, not a filesystem.  Also, Redis handles keys expiration automatically.


### Session best practices

Redis enables you to have multiple databases, just like mysql. This is pretty neat,
because  we can configure this extension, to use one database for session and one for cache and journal.
Then you can easily flush whole database with cache on deploy and you won't have to worry about possibly deleting our session.

The default database is `0`, so when you configure the session to be stored in database `1`, you should be in good shape.

```yml
redis:
	session: {database: 1}
```

Then on deploy simply call from console `redis-cli -n 0 flushdb` and it will delete all the cache.


### Session drivers

There are two ways to store sessions using this extension. Native and "emulated".

The native way will change the "save path" option on `Nette\Http\Session`, utilizing the internal php mechanism.
The PHP Extension which is this Nette extension based on, will be handling this mechanism in it's own.

The "emulated" way will use own session storage, which works exactly the same and can be switched on runtime without the need to rebuild the sessions.

The difference is that the "emulated" storage has locking mechanism and the native might be slightly faster.
You may or may not wanna use locks for session. But they are best practise and you should prefer them.

You can disable the native driver by this option (and the emulated will take control)

```yml
redis:
	session: {native: off}
```
