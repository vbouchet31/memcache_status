<?php

namespace Drupal\memcache_status\Commands;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\State\State;
use Drupal\memcache_status\MemcacheStatusHelper;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;

class MemcacheStatusCommands extends DrushCommands {

  public function __construct(MemcacheStatusHelper $memcache_status_helper, State $state, DateFormatter $date_formatter) {
    parent::__construct();
    $this->memcacheStatusHelper = $memcache_status_helper;
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Refresh the dump data in the database.
   *
   * @param array $options
   *   (optional) An array of options.
   *
   * @command memcachestatus:refresh-dump
   *
   * @option servers The list of servers to dump separated by comma (default to
   *   "all", format host:port).
   * @option slabs The slabs ID to export separated by comma (default to
   *   "all").
   *
   * @aliases msr
   *
   * @throws \Drush\Exceptions\UserAbortException
   */
  public function refreshDumpData(array $options = ['servers' => 'all', 'slabs' => 'all']) {
    $last_refresh_time = $this->state->get('memcache_status.last_dump_data_refresh_time');

    if (!$this->io()->confirm(dt('Are you sure you want to refresh the memcache dumped data? Last refresh time: @last_refresh_time', [
      '@last_refresh_time' => $this->dateFormatter->format($last_refresh_time),
    ]))) {
      throw new UserAbortException();
    }

    // @TODO: Add a validation of the servers.
    $servers = explode(',', $this->input()->getOption('servers'));
    // @TODO: Add a validation for the slabs.
    $slabs = explode(',', $this->input()->getOption('slabs'));

    $count = $this->memcacheStatusHelper->refreshDumpData(
      $servers,
      $slabs
    );

    if ($count) {
      Cache::invalidateTags(['memcache_list:items']);
    }

    $this->output()->writeln(dt('@count items have been dumped into the database.', ['@count' => $count]));
  }
}
