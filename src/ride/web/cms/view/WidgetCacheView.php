<?php

namespace ride\web\cms\view;

use ride\library\mvc\view\AbstractHtmlView;

/**
 * Cacheable view for a widget
 */
class WidgetCacheView extends AbstractHtmlView {

    /**
     * Body for the view
     * @var string
     */
    protected $body;

    /**
     * Constructs a new cacheable view
     * @param string $body
     */
    public function __construct($body) {
        $this->body = $body;
    }

    /**
     * Renders the output for this view
     * @param boolean $willReturnValue True to return the rendered view, false
     * to send it straight to the client
     * @return null|string Null when provided $willReturnValue is set to true, the
     * rendered output otherwise
     */
    public function render($willReturnValue = true) {
        if ($willReturnValue) {
            return $this->body;
        }

        echo $this->body;
    }

}
