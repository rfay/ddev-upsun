<?php


if (getenv('PLATFORM_PROJECT') != "") {
    $databases['default']['default']['database'] = getenv('DB_PATH');
    $databases['default']['default']['username'] = getenv('DB_USERNAME');
    $databases['default']['default']['password'] = getenv('DB_PASSWORD');
    $databases['default']['default']['host'] = getenv('DB_HOST');
    $databases['default']['default']['driver'] = getenv('DB_SCHEME');
    $databases['default']['default']['port'] = getenv('DB_PORT');
    $settings['hash_salt'] = 'bf95508a645408b33848673dba1368d4f976bdb0ee4cd4d5b97dc9ddf9b88211';
    if (empty($settings['config_sync_directory'])) {
      $settings['config_sync_directory'] = 'sites/default/files/sync';
    }
    $settings['trusted_host_patterns'] = [
      'platformsh\.site$',
    ];
}
