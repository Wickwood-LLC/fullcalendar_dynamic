<?php

namespace Drupal\fullcalendar_dynamic\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\views\Controller\ViewAjaxController;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Defines a controller to load a Fullcalendar events via AJAX.
 * It extends ViewAjaxController for its intializations and overrides ajaxView() method.
 */
class CalendarEventSourceController extends ViewAjaxController {

  /**
   * {@inheritdoc}
   *
   * Most of the code here are from ViewAjaxController::ajaxView()
   */
  public function ajaxView(Request $request) {
    $name = $request->request->get('view_name');
    $display_id = $request->request->get('view_display_id');
    if (isset($name) && isset($display_id)) {
      $args = $request->request->get('view_args', '');
      $args = $args !== '' ? explode('/', Html::decodeEntities($args)) : [];

      // Arguments can be empty, make sure they are passed on as NULL so that
      // argument validation is not triggered.
      $args = array_map(function ($arg) {
        return ($arg == '' ? NULL : $arg);
      }, $args);

      $path = $request->request->get('view_path');
      $dom_id = $request->request->get('view_dom_id');
      $dom_id = isset($dom_id) ? preg_replace('/[^a-zA-Z0-9_-]+/', '-', $dom_id) : NULL;
      $pager_element = $request->request->get('pager_element');
      $pager_element = isset($pager_element) ? intval($pager_element) : NULL;

      $start_datetime = $request->request->get('start');
      $end_datetime = $request->request->get('end');
      // TODO: The Fullcalendar passes this value. Need to find if this should be utilized.
      // $time_zone = $request->request->get('timeZone');

      $response = new JsonResponse();

      // Remove all of this stuff from the query of the request so it doesn't
      // end up in pagers and tablesort URLs.
      // @todo Remove this parsing once these are removed from the request in
      //   https://www.drupal.org/node/2504709.
      foreach ([
          'view_name',
          'view_display_id',
          'view_args',
          'view_path',
          'view_dom_id',
          'pager_element',
          'view_base_path',
          AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER,
          FormBuilderInterface::AJAX_FORM_REQUEST,
          MainContentViewSubscriber::WRAPPER_FORMAT,
          'start', // start, end and timeZone. These three parameters are automatically added by the Fullcalendar
          'end',
          'timeZone',
        ] as $key) {
        $request->query->remove($key);
        $request->request->remove($key);
      }

      // Load the view.
      if (!$entity = $this->storage->load($name)) {
        throw new NotFoundHttpException();
      }
      $view = $this->executableFactory->get($entity);
      if ($view && $view->access($display_id) && $view->setDisplay($display_id)) {
        // $response->setView($view);
        // Fix the current path for paging.
        if (!empty($path)) {
          $this->currentPath->setPath('/' . ltrim($path, '/'), $request);
        }

        // Add all POST data, because AJAX is always a post and many things,
        // such as tablesorts, exposed filters and paging assume GET.
        $request_all = $request->request->all();
        unset($request_all['ajax_page_state']);
        $query_all = $request->query->all();
        $request->query->replace($request_all + $query_all);

        $options = $view->displayHandlers->get($display_id)->getPlugin('style')->options;
        $filters = $view->displayHandlers->get($display_id)->getOption('filters') ?? [];

        if (!empty($options['date_filter'])) {
          $date_filter_field = $view->displayHandlers->get($display_id)->getOption('fields')[$options['date_filter']];

          $filters[$date_filter_field['id']] = [
            'id' => $date_filter_field['id'],
            'table' => $date_filter_field['table'],
            'field' => $date_filter_field['field'] . '_value', // Is appending '_value' right a right way? Otherwise find the right way to determine the value column in the field table.
            'plugin_id' => 'datetime',
            'operator' => 'between',
            'value' => [
              'min' => $start_datetime,
              'max' => $end_datetime,
            ],
            'group' => 1, // required?
          ];
          $view->displayHandlers->get($display_id)->overrideOption('filters', $filters);
        }

        // Reuse the same DOM id so it matches that in drupalSettings.
        $view->dom_id = $dom_id;

        $context = new RenderContext();
        $preview = $this->renderer->executeInRenderContext($context, function () use ($view, $display_id, $args) {
          return $view->preview($display_id, $args);
        });

        if (!$context->isEmpty()) {
          $bubbleable_metadata = $context->pop();
          BubbleableMetadata::createFromRenderArray($preview)
            ->merge($bubbleable_metadata)
            ->applyTo($preview);
        }

        // Fullcalendar View preprocess service.
        $preprocess_service = \Drupal::service('fullcalendar_dynamic.view_preprocess');
        $events_data = $preprocess_service->prepareEntries($view);
  

        $response->setData($events_data);

        return $response;
      }
      else {
        throw new AccessDeniedHttpException();
      }
    }
    else {
      throw new NotFoundHttpException();
    }
  }

}
