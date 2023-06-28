# Memcache Status

** This is work in progress module. It probably does not support all the possible
memcache setups. In case of problem, please report in the queue so we can improve
the module to be compatible with more situations. **

** The main difference with other modules or tools, it uses `lru_crawler metadump`
command which does not lock the cache and can return all items, not just 1MB worth.

## Summary:

This module provides 3 UIs:
* Server(s) information: exposes the server(s) usage, eviction, hit and miss, etc.
* Slab(s) information: exposes the slabs repartition and usage.
* Items list: a Views to list down and filter all the items from the cache.

## Drush integration:

As of now, the module only exposes 1 drush command:
* `memcachestatus:refresh-dump` will truncate the items from the database and
retrieve the items from the cache.
