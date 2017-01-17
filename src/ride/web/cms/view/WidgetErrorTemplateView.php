<?php

namespace ride\web\cms\view;

use ride\library\template\GenericThemedTemplate;

use ride\web\mvc\view\TemplateView;

use \Exception;

/**
 * Frontend view for a widget error
 */
class WidgetErrorTemplateView extends TemplateView {

    /**
     * Constructs a new template view
     * @param string $theme Name of the theme
     * @param string $region Name of the region the widget resides in
     * @param string $section Name of the section inside the region
     * @param string $block Name of the block inside the section
     * @param string $widgetId Id of the widget instance
     * @param string $widgetName Machine name of the widget
     * @param Exception $exception Occured exception
     * @return null
     */
    public function __construct($theme, $region, $section, $block, $widgetId, $widgetName, Exception $exception) {
        $template = new GenericThemedTemplate();
        $template->setResource('cms/widget/error');
        $template->setTheme($theme);

        $exceptionArray = array();
        $e = $exception;
        while ($e) {
            $exceptionArray[] = array(
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            );

            $e = $e->getPrevious();
        }
        $exceptionArray = array_reverse($exceptionArray);

        $template->set('region', $region);
        $template->set('section', $section);
        $template->set('block', $block);
        $template->set('widgetId', $widgetId);
        $template->set('widgetName', $widgetName);
        $template->set('exception', $exception);
        $template->set('exceptionArray', $exceptionArray);

        parent::__construct($template);
    }

}
