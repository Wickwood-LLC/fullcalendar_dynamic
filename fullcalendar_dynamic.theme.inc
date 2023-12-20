<?php

/**
 * @file
 * Theme for Full Calendar views.
 */

 /**
  * Implements hook_theme()
  */
function fullcalendar_view_enhanced_theme() {
  return [
    'fullcalendar_view_enhanced_tooltip' => [
      'variables' => [
        'title' => NULL,
        'body' => NULL,
      ],
    ],
  ];
}

/**
 * Implement fullcalendar view theme preprocess function.
 *
 * @param array $variables
 *   Template variables.
 */
function template_preprocess_views_view_fullcalendar_enanced(array &$variables) {
  // Fullcalendar View preprocess service.
  $preprocess_service = \Drupal::service('fullcalendar_view_enhanced.view_preprocess');

  // View processor plugins.
  $processor_plugins = \Drupal::service('plugin.manager.fullcalendar_view_enhanced_processor');
  // The list of available plugins:
  $plugin_definitions = $processor_plugins->getDefinitions();

  // Getting supported field types from plugin definitions
  $field_types = [];
  foreach ($plugin_definitions as $definition) {
    if (isset($definition['field_types'])) {
      $field_types = $field_types + $definition['field_types'];
    }
  }
  $variables['fullcalendar_fieldtypes'] = $field_types;

  $preprocess_service->process($variables);
  
  // Allow plugins to alter the view variables.
  foreach ($plugin_definitions as $definition) {
    $instance = $processor_plugins->createInstance($definition['id']);
    if (method_exists($instance, 'process')) {
      // Run the process function of the plugin instance.
      $instance->process($variables);
    }
  }
  
}
