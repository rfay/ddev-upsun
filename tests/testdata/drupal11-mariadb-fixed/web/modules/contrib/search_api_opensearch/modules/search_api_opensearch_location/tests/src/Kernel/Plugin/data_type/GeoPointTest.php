<?php

declare(strict_types=1);

namespace Drupal\Tests\search_api_opensearch_location\Kernel\Plugin\data_type;

use Drupal\KernelTests\KernelTestBase;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\search_api_opensearch_location\Plugin\search_api\data_type\GeoPointDataType;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the geo point data type.
 *
 * @coversDefaultClass \Drupal\search_api_opensearch_location\Plugin\search_api\data_type\GeoPointDataType
 * @group search_api_opensearch
 */
class GeoPointTest extends KernelTestBase {

  use ProphecyTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_opensearch_location',
    'geofield',
  ];

  /**
   * Test behavior when valid geometry object is returned.
   *
   * @covers ::getValue
   */
  public function testValidGeom(): void {
    $pointValue = 'POINT (150.9994152 -33.8151479)';
    /** @var \Drupal\geofield\GeoPHP\GeoPHPInterface $geoPhp */
    $geoPhp = \Drupal::service('geofield.geophp');
    $geoPoint = new GeoPointDataType([], 'location', [], $geoPhp);
    $transformedValue = $geoPoint->getValue($pointValue);
    $this->assertEquals('-33.8151479,150.9994152', $transformedValue);
  }

  /**
   * Test behavior when invalid geometry object is returned.
   *
   * @covers ::getValue
   */
  public function testNullGeom(): void {
    $pointValue = 'POINT (150.9994152 -33.8151479)';
    $geoPhp = $this->prophesize(GeoPHPInterface::class);
    $geoPoint = new GeoPointDataType([], 'location', [], $geoPhp->reveal());
    $transformedValue = $geoPoint->getValue($pointValue);
    $this->assertEquals($pointValue, $transformedValue);
  }

}
