<?php

namespace Drupal\price_in_time\Form;

# Drupal Core.
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

# Symfony.
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Price in Time settings for this site.
 */
class ProductTypeTimeSlotsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entity_type_manager;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a Form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $connection) {
    $this->entity_type_manager = $entity_type_manager;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'price_in_time_product_type_time_slots';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['price_in_time.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $all_product_types_list = $this->getProductTypes();

    foreach ($all_product_types_list as $key => $value) {
      $times_items = [];

      if ($form_state->get('times_num_items') == null) {
        $form_state->set(
          'times_num_items', 3 # fixed slots.
        );
      }

      #--------------------------------------
      # define drupal 'fieldset' element
      #--------------------------------------
      $form[$key] = [
        '#type' => 'fieldset',
        '#title' => $value,
      ];
      #--------------------------------------
      # define drupal 'checkbox' element
      #--------------------------------------
      $form[$key]['product_type_enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable for this product type.'),
        '#default_value' => $this->config('price_in_time.settings')->get($key.'.enabled'),
      ];
      #--------------------------------------
      # define drupal 'se-times' element
      #--------------------------------------
      if(!$form_state->has('times_num_items')) {
        $form_state->set('times_num_items', 0);
      } $times_num_items = $form_state->get('times_num_items');

      for($index = 0; $index < $times_num_items; $index++) {
        $times_items = array_values($times_items);

        #--------------------------------------
        # define drupal 'fieldset' element
        #--------------------------------------
        $form[$key]['times'][$index] = [
          '#type' => 'fieldset',
          '#title' => $this->t('['.$index.'] Slot times: '),
          '#prefix' => '<div class="price_in_time__slots_element">',
          '#suffix' => '</div>',
        ];
        $form[$key]['times'][$index]['start_time'] = [
          '#type' => 'datetime',
          '#title' => t('Start time:'),
          '#date_date_element' => 'none',
          '#date_time_element' => 'time',
          '#date_time_format' => 'H:i',
          '#default_value' => new DrupalDateTime(
            $this->config('price_in_time.settings')->get($key.'.times.'.$index.'.start')
          ),
          '#prefix' => '<div class="price_in_time__slots_element__start">',
          '#suffix' => '</div>',
        ];
        $form[$key]['times'][$index]['end_time'] = [
          '#type' => 'datetime',
          '#title' => t('End time:'),
          '#date_date_element' => 'none',
          '#date_time_element' => 'time',
          '#date_time_format' => 'H:i',
          '#default_value' => new DrupalDateTime(
            $this->config('price_in_time.settings')->get($key.'.times.'.$index.'.end')
          ),
          '#prefix' => '<div class="price_in_time__slots_element__end">',
          '#suffix' => '</div>',
        ];
        #--------------------------------------
        # define drupal 'checkbox' element
        #--------------------------------------
        $form[$key]['times'][$index]['free_shipping'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable free shipping for this slot.'),
          '#default_value' => $this->config('price_in_time.settings')->get($key.'.times.'.$index.'.free_shipping'),
          '#prefix' => '<div class="price_in_time__slots_element__free_shipping">',
          '#suffix' => '</div>',
        ];
      }
    }

    #--------------------------------------
    # define drupal 'fieldset' element
    #--------------------------------------
    $form['price_in_time__slots'] = [
      '#type' => 'fieldset',
      '#title' => t('Information:'),
      '#description' => t('To be sure that the configuration will be applyed, you should rebuild the cache.'),
      '#prefix' => '<div id="price-in-time--slots">',
      '#suffix' => '</div>',
      '#weight' => 900, # we want this at the bottom.
    ];

    $form['#tree'] = TRUE;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $all_product_types_list = $this->getProductTypes();
    $all_form_state_values = $form_state->getValues();

    foreach ($all_product_types_list as $key => $value) {
      if (isset($all_form_state_values[$key]) &&
          isset($all_form_state_values[$key]['times']) &&
          $all_form_state_values[$key]['product_type_enabled']) {

        if ($this->isTimesOverlapped($all_form_state_values[$key]['times'], 'start_time', 'end_time', $corrupted)) {
          $form_state->setErrorByName(
            'simple][times]['.$corrupted,
            $this->t('The value of times are overlapped.')
          );

          $form_state->setRebuild();
        }
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $price_in_time_settings = $this->config('price_in_time.settings');
    $all_product_types_list = $this->getProductTypes();
    $all_form_state_values = $form_state->getValues();

    foreach ($all_product_types_list as $key => $value) {
      if (isset($all_form_state_values[$key]) && $all_form_state_values[$key]['product_type_enabled']) {

        $price_in_time_settings->set(
          $key.'.enabled',
          $all_form_state_values[$key]['product_type_enabled']
        );

        for($index = 0; $index < $form_state->get('times_num_items'); $index++) {

          $price_in_time_settings->set(
            $key.'.times.'.$index.'.start',
            $all_form_state_values[$key]['times'][$index]['start_time']->format('H:i:s')
          );

          $price_in_time_settings->set(
            $key.'.times.'.$index.'.end',
            $all_form_state_values[$key]['times'][$index]['end_time']->format('H:i:s')
          );

          $price_in_time_settings->set(
            $key.'.times.'.$index.'.free_shipping',
            $all_form_state_values[$key]['times'][$index]['free_shipping']
          );

          if (empty($price_in_time_settings->get($key.'.times.'.$index.'.uuid'))) {
            $price_in_time_settings->set(
              $key.'.times.'.$index.'.uuid',

              # This generate random string of 20 chars.
              # Will be used for the UUID of the slot.
              bin2hex(random_bytes(10))
            );
          }
        }
      }

      # If the element was enabled in the past, but not now,
      # so it should be deleted from config and database.
      else {
        if ($price_in_time_settings->get($key.'.enabled')) {

          # The bundle was disabled right now, so we need
          # to delete the configuration elements from the
          # database.
          $price_in_time_settings->delete($key);

          # We need to clear the database table from the
          # elements with this specific bundle.
          $this->connection
            ->delete('commerce_product_variation_slots_data')
            ->condition('product_variation', $key, '=')
            ->execute();
        }
      }
    }

    $price_in_time_settings->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * 
   * Check the two time periods overlap.
   *
   * Example:
   * $periods = [
   *    ['start_time' => "09:00", 'end_time' => '10:30'],
   *    ['start_time' => "14:30", "end_time" => "16:30"],
   *    ['start_time' => "11:30", "end_time" => "13:00"],
   *    ['start_time' => "10:30", "end_time" => "11:30"],
   * ];
   *
   * @param $periods
   * @param string $start_time_key
   * @param string $end_time_key
   * @param int $corrupted
   * @return bool
   *
   * Check the code on this page:
   *  - https://dev.to/xichlo/determine-whether-two-date-ranges-overlap-in-php-1b5a
   * 
   * Of course I understood the code that I copy&paste, and I added
   * the parameter $corrupted because I need the position of the error.
   *
   */
  private function isTimesOverlapped($periods, $start_time_key, $end_time_key, &$corrupted = null) {
    usort($periods, function ($a, $b) use ($start_time_key, $end_time_key) {
      return strtotime($a[$start_time_key]) <=> strtotime($b[$end_time_key]);
    });

    foreach ($periods as $key => $period) {
      if ($key != 0) {
        if (strtotime($period[$start_time_key]) < strtotime($periods[$key - 1][$end_time_key])) {
          $corrupted = $key - 1;
          return true;
        }
      }
    }

    return false;
  }

  /**
   * 
   * @return array
   * 
   * Return all the variation types defined in the CMS,
   * keyed and labeled.
   * 
   */
  private function getProductTypes() {

    $product_types_loaded = $this->entity_type_manager
      ->getStorage('commerce_product_variation_type')
      ->loadByProperties([
        # Empty properties...
      ]);

    $product_types_list = [
      /** All the product/variation types. */
    ];

    foreach ($product_types_loaded as $key => $value) {
      $product_types_list[$key] = $value->label();
    }

    return $product_types_list;
  }
}
