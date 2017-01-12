<?php

namespace ride\web\cms\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\validation\constraint\OrConstraint;

use ride\web\base\form\DateTimeComponent;

/**
 * Form component to edit the home page
 */
class HomeComponent extends AbstractComponent {

    public function setNodeList(array $nodeList) {
        $this->nodeList = $nodeList;
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
