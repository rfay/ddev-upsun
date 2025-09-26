# Search API OpenSearch

This modules provides a [Search API](https://www.drupal.org/project/search_api)
Backend for [OpenSearch](https://opensearch.org/).

This module uses the official [OpenSearch PHP Client](https://github.com/opensearch-project/opensearch-php).

### Features

- Search API integration for indexing, field mapping, views etc.
- Facets
- More Like This
- Connector plugin type for external connector extensions

### Hosted OpenSearch Services

- [Amazon OpenSearch Service](Amazon OpenSearch Service): Operate OpenSearch with the leading contributor of the community-driven, open source software.

### Logging configuration

The [OpenSearch PHP Client](https://github.com/opensearch-project/opensearch-php)
client library can be noisy. To disable logging, you can configure the logger
service to use a `Psr\Log\NullLogger` instance.

```yaml
services:
  logger.channel.search_api_opensearch_client:
    class: Psr\Log\NullLogger
```

For finer-grained control, you can use a custom logger or use the
[Monolog module](https://www.drupal.org/project/monolog) to configure logging.

### Credits

This module heavily based on the work of the [Elasticsearch Connector](https://www.drupal.org/project/elasticsearch_connector) module.
