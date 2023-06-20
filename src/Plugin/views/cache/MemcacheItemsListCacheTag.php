<?php

namespace Drupal\memcache_status\Plugin\views\cache;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Cache\Cache;
use Drupal\views\Annotation\ViewsCache;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * @ingroup views_cache_plugins
 *
 * @ViewsCache(
 *   id = "memcache_items_list_tag",
 *   title = @Translation("Custom tag based: memcache_list:items"),
 *   help = @Translation("Add a custom tag memcache_list:items."),
 *   base = {"memcache_status_dump_data"},
 * )
 */
class MemcacheItemsListCacheTag extends Tag {

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();

    return Cache::mergeTags(['memcache_list:items'], $tags);
  }
}
