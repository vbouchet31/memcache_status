<?php

namespace Drupal\memcache_status;

use Drupal\Core\Database\Connection;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\State;
use Drupal\memcache\Driver\MemcacheDriverFactory;

class MemcacheStatusHelper {

  public function __construct(Connection $database, MemcacheDriverFactory $memcacheDriverFactory, State $state) {
    $this->database = $database;
    $this->memcacheDriverFactory = $memcacheDriverFactory;
    $this->state = $state;
  }

  /**
   * Reverse map the memcache_bins variable.
   */
  public function getBinMapping($bin = 'cache') {
    $memcache = $this->memcacheDriverFactory->get(NULL, TRUE);
    $memcache_bins = $memcache->getBins();

    $bins = array_flip($memcache_bins);
    if (isset($bins[$bin])) {
      return $bins[$bin];
    }
    else {
      return $this->defaultBin($bin);
    }
  }

  /**
   * Returns the bin name.
   */
  public function defaultBin($bin) {
    if ($bin == 'default') {
      return 'cache';
    }

    return $bin;
  }

  /**
   * Crawl the memcache servers and store the data in the database.
   *
   * @param array $server_names
   * @param array $slabs_ids
   *
   * @return int|null
   * @throws \Exception
   */
  public function refreshDumpData(array $server_names, array $slabs_ids) {
    $bin = 'default';
    $bin = $this->getBinMapping($bin);
    /** @var $memcache \Drupal\memcache\DrupalMemcacheInterface */
    $memcache = $this->memcacheDriverFactory->get($bin);

    // Build the list of all the servers which Drupal is connected to.
    $all_servers = [];
    foreach ($memcache->getMemcache()->getServerList() as $server_data) {
      $all_servers[$server_data['host'] . ':' . $server_data['port']] = $server_data['host'] . ':' . $server_data['port'];
    }

    // Build the list of servers to query based on the $server_names and known
    // servers.
    $servers = [];
    foreach ($server_names as $server_name) {
      if ($server_name === 'all') {
        $servers = $all_servers;
        break;
      }
      elseif (in_array($server_name, $all_servers)) {
        $servers[] = $all_servers[$server_name];
      }
    }

    // Format the list of slabs.
    $slabs = implode(',', $slabs_ids);
    if (in_array('all', $slabs_ids)) {
      $slabs = 'all';
    }

    // Execute the lru_crawler metadump command on each individual server.
    $items = [];
    foreach ($servers as $s) {
      [$host, $port] = explode(':', $s);
      $tmp = $this->sendMemcacheCommand('lru_crawler metadump ' . $slabs, $host, $port, 'parseDumpResult');

      $items = array_merge($items, $tmp);
    }

    // Exit early if we don't get any dump data to store.
    if (empty($items)) {
      return 0;
    }

    // Truncate the table to store the new data.
    $this->database->truncate('memcache_status_dump_data')->execute();

    // Insert all the items via a unique query.
    // @TODO: Check on a large memcache instance if it causes performance issue.
    $query = $this->database->insert('memcache_status_dump_data')
      ->fields(['server', 'bin', 'slab', 'key_prefix', 'cid', 'expire', 'last_access', 'cas', 'fetched', 'size']);
    foreach ($items as $item) {
      // Skip the items which are not related to this site.
      // @TODO: Add a configuration so it is possible to not filter. We may
      // need to add the key_prefix in the database to allow filtering.
      if (!empty(Settings::get('memcache')['key_prefix']) && $item['key_prefix'] !== Settings::get('memcache')['key_prefix']) {
        continue;
      }
      $query->values($item);
    }
    $query->execute();

    // Store the refresh time, so it can be displayed and avoid unnecessary updates.
    $this->state->set('memcache_status.last_dump_data_refresh_time', time());

    return count($items);
  }

  /**
   * Helper function to send a command to a memcache server.
   *
   * @param $command
   *   The command to execute.
   * @param $host
   *   The name or IP of the host to send the command to.
   * @param $port
   *   The port of the host to connect to.
   * @param $callback
   *   The callback function to invoke with the result of the command.
   *
   * @return mixed
   */
  public function sendMemcacheCommand($command, $host = 'memcache', $port = 11211, $callback = 'parseCommandResult') {
    $s = @fsockopen($host, $port);

    fwrite($s, $command . "\r\n");

    $buf = '';
    while (!feof($s)) {
      $buf .= fgets($s, 256);
      if (str_contains($buf, "END\r\n")) {
        break;
      }
    }
    fclose($s);

    return $this->{$callback}($buf, ['command' => $command, 'host' => $host, 'port' => $port]);
  }

  /**
   * Break down the item key elements (prefix, bin, cid, ...).
   *
   * @param $key
   *  The key to decode.
   *
   * @return array
   *   The key elements.
   */
  public function decodeItemKey($key) {
    $elements = [
      'key' => $key,
    ];

    // @TODO: It does not handle the case where the original key was longer
    // than 255 characters and has been hashed. See DriveBase::key().
    // CID is urlencoded by Drupal but also by Memcache hence we need to
    // decode twice. Not sure we can get the original key has it is hashed.

    // Handle the case where there is a key_prefix.
    $elements['key_prefix'] = '';
    if (!empty(Settings::get('memcache')['key_prefix'])) {
      [$elements['key_prefix'], $elements['bin'], $elements['cid']] = explode(':', urldecode(urldecode($key)), 3);
    }
    else {
      [$elements['bin'], $elements['cid']] = explode(':', urldecode(urldecode($key)), 2);
    }

    // The cid starts with a "-". Remove it.
    $elements['cid'] = substr($elements['cid'], 1);

    return $elements;
  }

  /**
   * A helper method to parse the result of a "lru_crawler metadump" command.
   *
   * @param $str
   *   The command result as a string.
   * @param $data
   *   An arbitrary array of data.
   *
   * @return array
   *   An array of item data.
   */
  public function parseDumpResult($str, $data) {
    $pattern = '/key=([^ ]+)\s+exp=(-?\d+)\s+la=(-?\d+)\s+cas=(-?\d+)\s+fetch=(\w+)\s+cls=(-?\d+)\s+size=(\d+)/';

    $items = [];
    foreach (explode("\n", $str) as $line) {
      $line = trim($line);

      if (empty($line) || $line === 'END') {
        continue;
      }

      $matches = [];
      preg_match($pattern, $line, $matches);

      // We assume all the lines follow the exact same pattern. If the string
      // does not match the pattern, we move to the next one.
      if (count($matches) !== 8) {
        continue;
      }

      $key_elements = $this->decodeItemKey($matches[1]);

      $items[$key_elements['cid']] = [
        'server' => $data['host'] . ':' . $data['port'],
        'bin' => $key_elements['bin'],
        'slab' => (int) $matches[6],
        'key_prefix' => $key_elements['key_prefix'],
        'cid' => $key_elements['cid'],
        'expire' => (int) $matches[2],
        'last_access' => (int) $matches[3],
        'cas' => (int) $matches[4],
        'fetched' => $matches[5] === 'yes' ? 1 : 0,
        'size' => (int) $matches[7],
      ];
    }

    return $items;
  }
}
