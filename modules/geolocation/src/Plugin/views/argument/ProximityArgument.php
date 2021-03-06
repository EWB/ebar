<?php

namespace Drupal\geolocation\Plugin\views\argument;

use Drupal\geolocation\GeolocationCore;
use Drupal\views\Plugin\views\argument\Formula;
use Drupal\views\Plugin\views\query\Sql;
use Drupal\Core\Form\FormStateInterface;

/**
 * Argument handler for geolocation proximity.
 *
 * Argument format should be in the following format:
 * "37.7749295,-122.41941550000001<=5miles" (defaults to km).
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("geolocation_argument_proximity")
 */
class ProximityArgument extends Formula {

  protected $operator = '<';
  protected $proximity = '';

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['description']['#markup'] .= $this->t('<br/> Proximity format should be in the following format: <strong>"37.7749295,-122.41941550000001<=5miles"</strong> (defaults to km).');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormula() {
    // Parse argument for reference location.
    $values  = $this->getParsedReferenceLocation();
    // Make sure we have enough information to start with.
    if ($values && $values['lat'] && $values['lng'] && $values['distance']) {
      // Get the earth radius in from the units.
      $earth_radius = $values['units'] === 'mile' ? GeolocationCore::EARTH_RADIUS_MILE : GeolocationCore::EARTH_RADIUS_KM;
      // Build a formula for the where clause.
      $formula = \Drupal::service('geolocation.core')->getProximityQueryFragment($this->tableAlias, $this->realField, $values['lat'], $values['lng'], $earth_radius);
      // Set the operator and value for the query.
      $this->proximity = $values['distance'];
      $this->operator = $values['operator'];

      return !empty($formula) ? str_replace('***table***', $this->tableAlias, $formula) : FALSE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE) {
    $this->ensureMyTable();
    // Now that our table is secure, get our formula.
    $placeholder = $this->placeholder();
    $formula = $this->getFormula() . ' ' . $this->operator . ' ' . $placeholder;
    $placeholders = array(
      $placeholder => $this->proximity,
    );

    // The addWhere function is only available for SQL queries.
    if ($this->query instanceof Sql) {
      $this->query->addWhere(0, $formula, $placeholders, 'formula');
    }
  }

  /**
   * Processes the passed argument into an array of relevant geolocation data.
   *
   * @return array|bool $values
   *   The calculated values.
   */
  public function getParsedReferenceLocation() {
    // Cache the vales so this only gets processed once.
    static $values;

    if (!isset($values)) {
      // Process argument values into an array.
      preg_match('/^([0-9\-.]+),+([0-9\-.]+)([<>=]+)([0-9.]+)(.*$)/', $this->getValue(), $values);
      // Validate and return the passed argument.
      $values = is_array($values) ? [
        'lat' => (isset($values[1]) && ($lat = abs((int) $values[1])) && $lat >= 0 && $lat) <= 90 ? floatval($values[1]) : FALSE,
        'lng' => (isset($values[2]) && ($lng = abs((int) $values[2])) && $lng >= 0 && $lng) <= 180 ? floatval($values[2]) : FALSE,
        'operator' => (isset($values[3]) && in_array($values[3], [
          '<>',
          '=',
          '>=',
          '<=',
          '>',
          '<',
        ])) ? $values[3] : '<=',
        'distance' => (isset($values[4])) ? floatval($values[4]) : FALSE,
        'units' => (isset($values[5]) && strpos(strtolower($values[5]), 'mile') !== FALSE) ? 'mile' : 'km',
      ] : FALSE;
    }
    return $values;
  }

}
