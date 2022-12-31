<?php

namespace Drupal\price_in_time\OrderProcessor;

# Drupal Commerce.
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;

# Drupal Core.
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Applies a 5% discount per high quanity item because it is Thursday.
 */
class FreeShippingOrderProcessor implements OrderProcessorInterface {

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
   * Constructs an Order Processor object.
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
  public function process(OrderInterface $order) {

    $shipment_referenced_entities = $order->shipments->referencedEntities();

    if (!empty($shipment = reset($shipment_referenced_entities)) &&
        $shipment->getAmount()->getNumber() > 0 &&
        $this->checkForFreeShippingTime($order->getItems())) {

      $order->addAdjustment(new Adjustment([
        'type' => 'price_in_time__free_shipping',
        'label' => t('Free shipping bonus'),
        'amount' => $shipment->getAmount()->multiply('-1'),
      ]));

      $order->collectAdjustments();
    }
  }

  /**
   * 
   * @param array $order_items
   * @return bool
   * 
   * This will return true if the free shipping is set for the specific
   * slot of time, otherwise false (will not apply the bonus on checkout).
   * 
   */
  private function checkForFreeShippingTime($order_items) {

    foreach ($order_items as $key => $item) {
      $purchased_referenced_entities = $item->purchased_entity->referencedEntities();
      $item_entity = reset($purchased_referenced_entities);
      $item_bundle = $item_entity->bundle();
      $item_settings = $this->config_factory->get($item_bundle);
      $current_time = strtotime(date('H:i:s'));

      foreach ($item_settings['times'] as $key => $slot) {
        if ($current_time >= strtotime($slot['start']) && $current_time <= strtotime($slot['end'])) {
          if ($slot['free_shipping']) return true;
        }
      }
    }

    return false;
  }
}
