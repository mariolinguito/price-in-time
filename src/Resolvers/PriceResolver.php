<?php

namespace Drupal\price_in_time\Resolvers;

# Drupal Commerce.
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Drupal\commerce_price\Price;

# Drupal Entity.
use Drupal\user\Entity\User;

# Drupal Core.
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;

class PriceResolver implements PriceResolverInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config_factory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a Price Resolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Connection $connection) {
    $this->config_factory = $config_factory->get('price_in_time.settings');
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    $query = $this->connection
      ->select('commerce_product_variation_slots_data', 'commerce_product_slots')
      ->fields('commerce_product_slots', [
        'product_variation_sku',
        'number',
        'currency_code',
        'slot_uuid',
      ])
      ->condition('commerce_product_slots.product_variation_sku', $entity->getSku())
      ->execute()
      ->fetchAllAssoc('slot_uuid');

    if ($this->config_factory->get($entity->bundle() . '.enabled') && !empty($query)) {
      return $this->getCurrentTimeSlotPrice(
        $query,
        $entity->bundle()
      );
    } return $entity->getPrice();
  }

  /**
   * 
   * @param array $results
   * @param string $bundle
   * @return Price
   * 
   * Return the price of the specific slot of time (based on the
   * current time). This will return the price everytime because
   * we check the existence of the record before.
   * 
   */
  private function getCurrentTimeSlotPrice($results, $bundle) {
    $bundle_settings = $this->config_factory->get($bundle);
    $current_time = strtotime(date('H:i:s'));

    foreach ($bundle_settings['times'] as $key => $slot) {
      if ($current_time >= strtotime($slot['start']) && $current_time <= strtotime($slot['end'])) {
        if (isset($results[$slot['uuid']])) {
          return new Price(
            $results[$slot['uuid']]->number,
            $results[$slot['uuid']]->currency_code
          );
        }
      }
    }
  }
}
