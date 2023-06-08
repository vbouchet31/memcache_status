<?php

namespace Drupal\memcache_status\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Memcache Status settings form.
 */
class MemcacheStatusSettingsForm extends ConfigFormBase {

  /**
   * Memcache Status Settings Form constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Configuration Factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'memcache_status_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('memcache_status.settings');

    $server_statistic = array_fill_keys($config->get('server_statistics'), TRUE);

    $form['server_statistics'] = [
      '#type' => 'details',
      '#title' => $this->t('Server statistics'),
      '#open' => FALSE,
    ];
    $form['server_statistics']['info'] = [
      '#markup' => $this->t('Select which server statistics to display:'),
    ];
    // @TODO: All the server_* entries have been generated via ChatGPT from
    // https://raw.githubusercontent.com/memcached/memcached/master/doc/protocol.txt
    // It needs to be reviewed.
    // @TODO: We should probably improve the form so it is possible to order
    // the stats.
    // @TODO: We probably need to move keys, title, description in a helper service
    // which will also contain the type of data so we can apply transform
    // function on display (timestamp, byte, ...).
    $form['server_statistics']['server_pid'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('PID'),
      '#description' => $this->t('Process id of this server process.'),
      '#default_value' => $server_statistic['pid'] ?? FALSE,
    ];
    $form['server_statistics']['server_pid'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('PID'),
      '#description' => $this->t('Process id of this server process.'),
      '#default_value' => $server_statistic['pid'] ?? FALSE,
    ];

    $form['server_statistics']['server_uptime'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Uptime'),
      '#description' => $this->t('Number of secs since the server started.'),
      '#default_value' => $server_statistic['uptime'] ?? FALSE,
    ];

    $form['server_statistics']['server_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Current UNIX Time'),
      '#description' => $this->t('Current UNIX time according to the server.'),
      '#default_value' => $server_statistic['time'] ?? FALSE,
    ];

    $form['server_statistics']['server_version'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Server Version'),
      '#description' => $this->t('Version string of this server.'),
      '#default_value' => $server_statistic['version'] ?? FALSE,
    ];

    $form['server_statistics']['server_pointer_size'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pointer Size'),
      '#description' => $this->t('Default size of pointers on the host OS (generally 32 or 64).'),
      '#default_value' => $server_statistic['pointer_size'] ?? FALSE,
    ];

    $form['server_statistics']['server_rusage_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('User Time'),
      '#description' => $this->t('Accumulated user time for this process (seconds:microseconds).'),
      '#default_value' => $server_statistic['rusage_user'] ?? FALSE,
    ];

    $form['server_statistics']['server_rusage_system'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('System Time'),
      '#description' => $this->t('Accumulated system time for this process (seconds:microseconds).'),
      '#default_value' => $server_statistic['rusage_system'] ?? FALSE,
    ];

    $form['server_statistics']['server_curr_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Current Items'),
      '#description' => $this->t('Current number of items stored.'),
      '#default_value' => $server_statistic['curr_items'] ?? FALSE,
    ];

    $form['server_statistics']['server_total_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Total Items'),
      '#description' => $this->t('Total number of items stored since the server started.'),
      '#default_value' => $server_statistic['total_items'] ?? FALSE,
    ];

    $form['server_statistics']['server_bytes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bytes Used'),
      '#description' => $this->t('Current number of bytes used to store items.'),
      '#default_value' => $server_statistic['bytes'] ?? FALSE,
    ];

    $form['server_statistics']['server_max_connections'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Max Connections'),
      '#description' => $this->t('Max number of simultaneous connections.'),
      '#default_value' => $server_statistic['max_connections'] ?? FALSE,
    ];

    $form['server_statistics']['server_curr_connections'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Current Connections'),
      '#description' => $this->t('Number of open connections.'),
      '#default_value' => $server_statistic['curr_connections'] ?? FALSE,
    ];

    $form['server_statistics']['server_total_connections'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Total Connections'),
      '#description' => $this->t('Total number of connections opened since the server started running.'),
      '#default_value' => $server_statistic['total_connections'] ?? FALSE,
    ];

    $form['server_statistics']['server_rejected_connections'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Rejected Connections'),
      '#description' => $this->t('Conns rejected in maxconns_fast mode.'),
      '#default_value' => $server_statistic['rejected_connections'] ?? FALSE,
    ];

    $form['server_statistics']['server_connection_structures'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Connection Structures'),
      '#description' => $this->t('Number of connection structures allocated by the server.'),
      '#default_value' => $server_statistic['connection_structures'] ?? FALSE,
    ];

    $form['server_statistics']['server_response_obj_oom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Response Objects Out of Memory'),
      '#description' => $this->t('Connections closed by lack of memory.'),
      '#default_value' => $server_statistic['response_obj_oom'] ?? FALSE,
    ];

    $form['server_statistics']['server_response_obj_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Total Response Objects'),
      '#description' => $this->t('Total response objects in use.'),
      '#default_value' => $server_statistic['response_obj_count'] ?? FALSE,
    ];

    $form['server_statistics']['server_response_obj_bytes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Response Object Bytes'),
      '#description' => $this->t('Total bytes used for response objects (subset of bytes from read_buf_bytes).'),
      '#default_value' => $server_statistic['response_obj_bytes'] ?? FALSE,
    ];

    $form['server_statistics']['server_read_buf_count'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read/Response Buffer Count'),
      '#description' => $this->t('Total read/response buffers allocated.'),
      '#default_value' => $server_statistic['read_buf_count'] ?? FALSE,
    ];

    $form['server_statistics']['server_read_buf_bytes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read/Response Buffer Bytes'),
      '#description' => $this->t('Total read/response buffer bytes allocated.'),
      '#default_value' => $server_statistic['read_buf_bytes'] ?? FALSE,
    ];

    $form['server_statistics']['server_read_buf_bytes_free'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read/Response Buffer Bytes Free'),
      '#description' => $this->t('Total read/response buffer bytes cached.'),
      '#default_value' => $config->get('read_buf_bytes_free') ?? FALSE,
    ];

    $form['server_statistics']['server_read_buf_oom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Read/Response Buffer Out of Memory'),
      '#description' => $this->t('Connections closed by lack of memory.'),
      '#default_value' => $config->get('read_buf_oom') ?? FALSE,
    ];

    $form['server_statistics']['server_reserved_fds'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reserved File Descriptors'),
      '#description' => $this->t('Number of misc file descriptors used internally.'),
      '#default_value' => $config->get('reserved_fds') ?? FALSE,
    ];

    $form['server_statistics']['server_proxy_conn_requests'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Proxy Connection Requests'),
      '#description' => $this->t('Number of requests received by the proxy.'),
      '#default_value' => $config->get('proxy_conn_requests') ?? FALSE,
    ];

    $form['server_statistics']['server_proxy_conn_errors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Proxy Connection Errors'),
      '#description' => $this->t('Number of internal errors from the proxy.'),
      '#default_value' => $config->get('proxy_conn_errors') ?? FALSE,
    ];

    $form['server_statistics']['server_proxy_conn_oom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Proxy Connection Out of Memory Errors'),
      '#description' => $this->t('Number of out of memory errors while serving proxy requests.'),
      '#default_value' => $config->get('proxy_conn_oom') ?? FALSE,
    ];

    $form['server_statistics']['server_proxy_req_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Proxy Requests Active'),
      '#description' => $this->t('Number of in-flight proxy requests.'),
      '#default_value' => $config->get('proxy_req_active') ?? FALSE,
    ];

    $form['server_statistics']['server_proxy_req_await'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Proxy Requests Await'),
      '#description' => $this->t('Number of in-flight proxy asynchronous requests.'),
      '#default_value' => $config->get('proxy_req_await') ?? FALSE,
    ];

    $form['server_statistics']['server_cmd_get'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cumulative Retrieval Requests'),
      '#description' => $this->t('Cumulative number of retrieval requests.'),
      '#default_value' => $config->get('cmd_get') ?? FALSE,
    ];

    $form['server_statistics']['server_cmd_set'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cumulative Storage Requests'),
      '#description' => $this->t('Cumulative number of storage requests.'),
      '#default_value' => $config->get('cmd_set') ?? FALSE,
    ];

    $form['server_statistics']['server_cmd_flush'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cumulative Flush Requests'),
      '#description' => $this->t('Cumulative number of flush requests.'),
      '#default_value' => $config->get('cmd_flush') ?? FALSE,
    ];

    $form['server_statistics']['server_cmd_touch'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Cumulative Touch Requests'),
      '#description' => $this->t('Cumulative number of touch requests.'),
      '#default_value' => $config->get('cmd_touch') ?? FALSE,
    ];

    $form['server_statistics']['server_get_hits'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get Hits'),
      '#description' => $this->t('Number of keys that have been requested and found present.'),
      '#default_value' => $config->get('get_hits') ?? FALSE,
    ];

    $form['server_statistics']['server_get_misses'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get Misses'),
      '#description' => $this->t('Number of items that have been requested and not found.'),
      '#default_value' => $config->get('get_misses') ?? FALSE,
    ];

    $form['server_statistics']['server_get_expired'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get Expired'),
      '#description' => $this->t('Number of items that have been requested but had already expired.'),
      '#default_value' => $config->get('get_expired') ?? FALSE,
    ];

    $form['server_statistics']['server_get_flushed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Get Flushed'),
      '#description' => $this->t('Number of items that have been requested but have been flushed via flush_all.'),
      '#default_value' => $config->get('get_flushed') ?? FALSE,
    ];

    $form['server_statistics']['server_delete_misses'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Misses'),
      '#description' => $this->t('Number of deletions requested but not found.'),
      '#default_value' => $config->get('delete_misses') ?? FALSE,
    ];

    $form['server_statistics']['server_delete_hits'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete Hits'),
      '#description' => $this->t('Number of deletions requested and found.'),
      '#default_value' => $config->get('delete_hits') ?? FALSE,
    ];

    $form['server_statistics']['server_incr_misses'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Increment Misses'),
      '#description' => $this->t('Number of increment operations requested but not found.'),
      '#default_value' => $config->get('incr_misses') ?? FALSE,
    ];

    $form['server_statistics']['server_incr_hits'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Increment Hits'),
      '#description' => $this->t('Number of increment operations requested and found.'),
      '#default_value' => $server_statistic['incr_hits'] ?? FALSE,
    ];

    $form['server_statistics']['server_decr_misses'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decrement Misses'),
      '#description' => $this->t('Number of decrement operations requested but not found.'),
      '#default_value' => $server_statistic['decr_misses'] ?? FALSE,
    ];

    $form['server_statistics']['server_decr_hits'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Decrement Hits'),
      '#description' => $this->t('Number of decrement operations requested and found.'),
      '#default_value' => $server_statistic['decr_hits'] ?? FALSE,
    ];

    $form['server_statistics']['server_cas_misses'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('CAS Misses'),
      '#description' => $this->t('Number of CAS operations requested but not found.'),
      '#default_value' => $server_statistic['cas_misses'] ?? FALSE,
    ];

    $form['server_statistics']['server_cas_hits'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('CAS Hits'),
      '#description' => $this->t('Number of CAS operations requested and found.'),
      '#default_value' => $server_statistic['cas_hits'] ?? FALSE,
    ];

    $form['server_statistics']['server_cas_badval'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('CAS Bad Value'),
      '#description' => $this->t('Number of CAS operations requested but found an incorrect value.'),
      '#default_value' => $server_statistic['cas_badval'] ?? FALSE,
    ];

    $form['server_statistics']['server_auth_cmds'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Authentication Commands Processed'),
      '#description' => $this->t('Number of authentication commands handled.'),
      '#default_value' => $server_statistic['auth_cmds'] ?? FALSE,
    ];

    $form['server_statistics']['server_auth_errors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Authentication Errors'),
      '#description' => $this->t('Number of failed authentication attempts.'),
      '#default_value' => $server_statistic['auth_errors'] ?? FALSE,
    ];

    $form['server_statistics']['server_idle_kicks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Idle Kicks'),
      '#description' => $this->t('Number of connections closed due to reaching their idle timeout.'),
      '#default_value' => $server_statistic['idle_kicks'] ?? FALSE,
    ];

    $form['server_statistics']['server_evictions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Evictions'),
      '#description' => $this->t('Number of valid items removed from cache to free memory for new items.'),
      '#default_value' => $server_statistic['evictions'] ?? FALSE,
    ];

    $form['server_statistics']['server_reclaimed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reclaimed'),
      '#description' => $this->t('Number of times an entry was stored using memory from an expired entry.'),
      '#default_value' => $server_statistic['reclaimed'] ?? FALSE,
    ];

    $form['server_statistics']['server_bytes_read'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bytes Read'),
      '#description' => $this->t('Total number of bytes read by this server from the network.'),
      '#default_value' => $server_statistic['bytes_read'] ?? FALSE,
    ];

    $form['server_statistics']['server_bytes_written'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Bytes Written'),
      '#description' => $this->t('Total number of bytes sent by this server to the network.'),
      '#default_value' => $server_statistic['bytes_written'] ?? FALSE,
    ];

    $form['server_statistics']['server_limit_maxbytes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit Maxbytes'),
      '#description' => $this->t('Number of bytes this server is allowed to use for storage.'),
      '#default_value' => $server_statistic['limit_maxbytes'] ?? FALSE,
    ];

    $form['server_statistics']['server_accepting_conns'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Accepting Connections'),
      '#description' => $this->t('Whether or not the server is accepting connections.'),
      '#default_value' => $server_statistic['accepting_conns'] ?? FALSE,
    ];

    $form['server_statistics']['server_listen_disabled_num'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Listen Disabled Num'),
      '#description' => $this->t('Number of times the server has stopped accepting new connections (maxconns).'),
      '#default_value' => $server_statistic['listen_disabled_num'] ?? FALSE,
    ];

    $form['server_statistics']['server_time_in_listen_disabled_us'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Time in Listen Disabled (Microseconds)'),
      '#description' => $this->t('Number of microseconds in maxconns.'),
      '#default_value' => $server_statistic['time_in_listen_disabled_us'] ?? FALSE,
    ];

    $form['server_statistics']['server_threads'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Threads'),
      '#description' => $this->t('Number of worker threads requested. (see doc/threads.txt)'),
      '#default_value' => $server_statistic['threads'] ?? FALSE,
    ];
    $form['server_statistics']['server_conn_yields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Connection Yields'),
      '#description' => $this->t('Number of times any connection yielded to another due to hitting the -R limit.'),
      '#default_value' => $server_statistic['conn_yields'] ?? FALSE,
    ];
    $form['server_statistics']['server_hash_power_level'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hash Power Level'),
      '#description' => $this->t('Current size multiplier for hash table.'),
      '#default_value' => $server_statistic['hash_power_level'] ?? FALSE,
    ];
    $form['server_statistics']['server_hash_bytes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hash Bytes'),
      '#description' => $this->t('Bytes currently used by hash tables.'),
      '#default_value' => $server_statistic['hash_bytes'] ?? FALSE,
    ];
    $form['server_statistics']['server_hash_is_expanding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hash Table is Expanding'),
      '#description' => $this->t('Indicates if the hash table is being grown to a new size.'),
      '#default_value' => $server_statistic['hash_is_expanding'] ?? FALSE,
    ];
    $form['server_statistics']['server_expired_unfetched'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expired Unfetched'),
      '#description' => $this->t('Items pulled from LRU that were never touched by get/incr/append/etc before expiring.'),
      '#default_value' => $server_statistic['expired_unfetched'] ?? FALSE,
    ];
    $form['server_statistics']['server_evicted_unfetched'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Evicted Unfetched'),
      '#description' => $this->t('Items evicted from LRU that were never touched by get/incr/append/etc.'),
      '#default_value' => $server_statistic['evicted_unfetched'] ?? FALSE,
    ];
    $form['server_statistics']['server_evicted_active'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Evicted Active'),
      '#description' => $this->t('Items evicted from LRU that had been recently hit but did not jump to the top of LRU.'),
      '#default_value' => $server_statistic['evicted_active'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_reassign_running'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Reassign Running'),
      '#description' => $this->t('If a slab page is being moved.'),
      '#default_value' => $server_statistic['slab_reassign_running'] ?? FALSE,
    ];

    $form['server_statistics']['server_crawler_reclaimed'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Crawler Reclaimed'),
      '#description' => $this->t('Total items freed by LRU Crawler.'),
      '#default_value' => $server_statistic['crawler_reclaimed'] ?? FALSE,
    ];
    $form['server_statistics']['server_crawler_items_checked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Crawler Items Checked'),
      '#description' => $this->t('Total items examined by LRU Crawler.'),
      '#default_value' => $server_statistic['crawler_items_checked'] ?? FALSE,
    ];
    $form['server_statistics']['server_lrutail_reflocked'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('LRU Tail Reflocked'),
      '#description' => $this->t('Times LRU tail was found with active ref. Items can be evicted to avoid OOM errors.'),
      '#default_value' => $server_statistic['lrutail_reflocked'] ?? FALSE,
    ];
    $form['server_statistics']['server_moves_to_cold'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Moves to Cold'),
      '#description' => $this->t('Items moved from HOT/WARM to COLD LRU.'),
      '#default_value' => $server_statistic['moves_to_cold'] ?? FALSE,
    ];
    $form['server_statistics']['server_moves_to_warm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Moves to Warm'),
      '#description' => $this->t('Items moved from COLD to WARM LRU.'),
      '#default_value' => $server_statistic['moves_to_warm'] ?? FALSE,
    ];
    $form['server_statistics']['server_moves_within_lru'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Moves Within LRU'),
      '#description' => $this->t('Items reshuffled within HOT or WARM LRU.'),
      '#default_value' => $server_statistic['moves_within_lru'] ?? FALSE,
    ];
    $form['server_statistics']['server_direct_reclaims'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Direct Reclaims'),
      '#description' => $this->t('Times worker threads had to directly reclaim or evict items.'),
      '#default_value' => $server_statistic['direct_reclaims'] ?? FALSE,
    ];
    $form['server_statistics']['server_lru_crawler_starts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('LRU Crawler Starts'),
      '#description' => $this->t('Times an LRU crawler was started.'),
      '#default_value' => $server_statistic['lru_crawler_starts'] ?? FALSE,
    ];

    $form['server_statistics']['server_lru_maintainer_juggles'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('LRU Maintainer Juggles'),
      '#description' => $this->t('Number of times the LRU bg thread woke up.'),
      '#default_value' => $server_statistic['lru_maintainer_juggles'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_global_page_pool'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Global Page Pool'),
      '#description' => $this->t('Slab pages returned to global pool for reassignment to other slab classes.'),
      '#default_value' => $server_statistic['slab_global_page_pool'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_reassign_rescues'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Reassign Rescues'),
      '#description' => $this->t('Items rescued from eviction in page move.'),
      '#default_value' => $server_statistic['slab_reassign_rescues'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_reassign_evictions_nomem'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Reassign Evictions (No Memory)'),
      '#description' => $this->t('Items evicted during page move for lack of memory.'),
      '#default_value' => $server_statistic['slab_reassign_evictions_nomem'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_reassign_chunk_rescues'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Reassign Chunk Rescues'),
      '#description' => $this->t('Partial page moves due to insufficient space.'),
      '#default_value' => $server_statistic['slab_reassign_chunk_rescues'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_reassign_busy_items'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Reassign Busy Items'),
      '#description' => $this->t('Items busy during page move.'),
      '#default_value' => $server_statistic['slab_reassign_busy_items'] ?? FALSE,
    ];
    $form['server_statistics']['server_slab_reassign_busy_deletes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Slab Reassign Busy Deletes'),
      '#description' => $this->t('Items deleted during page move.'),
      '#default_value' => $server_statistic['slab_reassign_busy_deletes'] ?? FALSE,
    ];
    $form['server_statistics']['server_log_worker_dropped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log Worker Dropped'),
      '#description' => $this->t('Logs a worker never wrote due to full buffer.'),
      '#default_value' => $server_statistic['log_worker_dropped'] ?? FALSE,
    ];
    $form['server_statistics']['server_log_worker_written'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log Worker Written'),
      '#description' => $this->t('Logs written by a worker, to be picked up.'),
      '#default_value' => $server_statistic['log_worker_written'] ?? FALSE,
    ];
    $form['server_statistics']['server_log_watcher_skipped'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log Watcher Skipped'),
      '#description' => $this->t('Logs not sent to slow watchers.'),
      '#default_value' => $server_statistic['log_watcher_skipped'] ?? FALSE,
    ];
    $form['server_statistics']['server_log_watcher_sent'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log Watcher Sent'),
      '#description' => $this->t('Logs written to watchers.'),
      '#default_value' => $server_statistic['log_watcher_sent'] ?? FALSE,
    ];
    $form['server_statistics']['server_log_watchers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log Watchers'),
      '#description' => $this->t('Number of currently active watchers.'),
      '#default_value' => $server_statistic['log_watchers'] ?? FALSE,
    ];
    $form['server_statistics']['server_unexpected_napi_ids'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Unexpected NAPI IDs'),
      '#description' => $this->t('Number of times an unexpected NAPI ID is received.'),
      '#default_value' => $server_statistic['unexpected_napi_ids'] ?? FALSE,
    ];
    $form['server_statistics']['server_round_robin_fallback'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Round Robin Fallback'),
      '#description' => $this->t('Number of times NAPI ID 0 is received resulting in fallback to round-robin thread selection.'),
      '#default_value' => $server_statistic['round_robin_fallback'] ?? FALSE,
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['memcache_status.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //dsm($form_state);
    //dsm($form_state->getValues());
    /*$this->config('memcache_admin.settings')
      ->set('show_memcache_statistics', $form_state->getValue('show_memcache_statistics'))
      ->save();*/
    $values = $form_state->getValues();
    $server_values = array_filter($values, function($value, $key) { return $value && str_starts_with($key, 'server_');}, ARRAY_FILTER_USE_BOTH);

    $v = [];
    foreach ($server_values as $key => $value) {
      $v[str_replace('server_', '', $key)] = $value;
    }

    $this->config('memcache_status.settings')
      ->set('server_statistics', array_keys($v))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
