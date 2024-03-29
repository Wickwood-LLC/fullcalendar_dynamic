<?php

namespace Drupal\fullcalendar_dynamic\Plugin\views\display;

use Drupal\views\Plugin\views\display\Page;

/**
 * The plugin that handles a full page.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "fullcalendar_dynamic",
 *   title = @Translation("FullCalendar Page"),
 *   help = @Translation("Display the view as a fullcalendar view, with a URL and menu links."),
 *   uses_menu_links = TRUE,
 *   uses_route = TRUE,
 *   contextual_links_locations = {"page"},
 *   theme = "views_view",
 *   admin = @Translation("FullCalendar Page")
 * )
 */
class FullCalendarPage extends Page {
  /**
   * {@inheritdoc}
   */
  protected $usesPager = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['pager']['contains']['type']['default'] = 'none';

    // Set the default style plugin to 'fullcalendar_dynamic'.
    $options['style']['contains']['type']['default'] = 'fullcalendar_dynamic';
    $options['defaults']['default']['style'] = FALSE;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);
    unset($categories['pager']);
    unset($options['pager']);
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return 'fullcalendar';
  }
}
