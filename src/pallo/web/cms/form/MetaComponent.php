<?php

namespace pallo\web\cms\form;

use pallo\library\form\component\AbstractComponent;
use pallo\library\form\FormBuilder;

/**
 * Form component to edit meta tags
 */
class MetaComponent extends AbstractComponent {

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        if (strpos($data, '=') !== false) {
            list($property, $content) = explode('=', $data, 2);

            $data = array(
            	'property' => $property,
            	'content' => $content,
            );
        } else {
            $data = null;
        }

        return $data;
    }

    /**
     * Parse the form values to data of the component
     * @param array $data
     * @return mixed $data
     */
    public function parseGetData(array $data) {
        return $data['property'] . '=' . $data['content'];
    }

    /**
     * Prepares the form by adding row definitions
     * @param pallo\library\form\FormBuilder $builder
     * @param array $options
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];

        $builder->addRow('property', 'string', array(
            'label' => $translator->translate('label.property'),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'regex' => array(
                    'regex' => '/^([a-zA-Z0-9\-])*$/',
                ),
            )
        ));
        $builder->addRow('content', 'text', array(
            'label' => $translator->translate('label.content'),
            'attributes' => array(
                'rows' => 2,
            ),
            'filters' => array(
                'trim' => array(),
            ),
        ));
    }

}