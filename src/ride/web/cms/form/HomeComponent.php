<?php

namespace ride\web\cms\form;

use ride\library\cms\node\HomePage;
use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\validation\constraint\OrConstraint;

use ride\web\base\form\DateTimeComponent;

/**
 * Form component to edit the home page
 */
class HomeComponent extends AbstractComponent {
    protected $nodeList;

    /**
     * Sets the list of the available nodes
     * @param array $nodeList Array with the node id as key and a friendly name
     * as value
     * @return null
     */
    public function setNodeList(array $nodeList) {
        $this->nodeList = $nodeList;
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
        return 'ride\\library\\cms\\node\\HomePage';
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        if (!$data) {
            return array();
        }

        return array(
            'node' => $data->getNodeId(),
            'dateStart' => $data->getDateStart(),
            'dateStop' => $data->getDateStop(),
        );
    }

    /**
     * Parse the form values to data of the component
     * @param array $data
     * @return mixed $data
    */
    public function parseGetData(array $data) {
        return new HomePage($data['node'], $data['dateStart'], $data['dateStop']);
    }

    /**
     * Prepares the form by adding row definitions
     * @param \ride\library\form\FormBuilder $builder
     * @param array $options
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];

        $builder->addRow('node', 'option', array(
            'label' => $translator->translate('label.home.node'),
            'description' => $translator->translate('label.home.node.description'),
            'options' => $this->nodeList,
            'validators' => array(
                'required' => array(),
            ),
            'widget' => 'select',
        ));
        $builder->addRow('dateStart', 'component', array(
            'label' => $translator->translate('label.date.start'),
            'description' => $translator->translate('label.home.date.start.description'),
            'component' => new DateTimeComponent(),
        ));
        $builder->addRow('dateStop', 'component', array(
            'label' => $translator->translate('label.date.stop'),
            'description' => $translator->translate('label.home.date.stop.description'),
            'component' => new DateTimeComponent(),
        ));

        $constraint = new OrConstraint();
        $constraint->setError('error.validation.date.home');
        $constraint->addProperty('dateStart');
        $constraint->addProperty('dateStop');

        $builder->addValidationConstraint($constraint);
    }

}
