<?php

namespace Drupal\search_api_opensearch_location\Plugin\search_api\data_type;

use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\search_api\DataType\DataTypePluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a geo_point data type.
 *
 * @SearchApiDataType(
 *   id = "location",
 *   label = @Translation("Geopoint"),
 *   description = @Translation("A geopoint field type contains a geographic point specified by latitude and longitude.")
 * )
 */
class GeoPointDataType extends DataTypePluginBase {

  /**
   * Constructor for GeoPointDataType.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected GeoPHPInterface $geoPHP,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geofield.geophp')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    $geom = $this->geoPHP->load($value);

    if ($geom) {
      $lon = $geom->getX();
      $lat = $geom->getY();
      return "$lat,$lon";
    }
    else {
      return $value;
    }
  }

}
