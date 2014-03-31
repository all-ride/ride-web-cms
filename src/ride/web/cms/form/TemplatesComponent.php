<?php

namespace ride\web\cms\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\template\TemplateFacade;

/**
 * Form component to edit templates
 */
class TemplatesComponent extends AbstractComponent {

    /**
     * Creates the data for this component
     * @param \ride\library\template\TemplateFacade $templateFacade
     * @param array $templates
     * @param string $theme
     * @param string $id
     * @return array Data for the component
     */
    public function createData(TemplateFacade $templateFacade, array $templates, $theme = null, $id = null) {
        $data = array(
            'file' => array(),
            'content' => array(),
            'path' => array(),
        );

        foreach ($templates as $name => $template) {
            $template = $templateFacade->createTemplate($template, null, $theme);
            if ($id) {
                $template->setResourceId($id);
            }

            $data['file'][$name] = $templateFacade->getFile($template);
            $data['content'][$name] = file_get_contents($data['file'][$name]);

            // add the id to the template path
            $path = explode('/view/', $data['file'][$name], 2);
            $pathTokens = explode('/', $path[1]);
            $file = array_pop($pathTokens);
            $fileTokens = explode('.', $file);
            $fileExtension = array_pop($fileTokens);
            $fileId = array_pop($fileTokens);

            if ($id && $fileId != $id) {
                $fileId .= '.' . $id;
            }

            $data['path'][$name] = 'view/' . implode('/', $pathTokens) . '/' . ($fileTokens ? implode('.', $fileTokens) . '.' : '') . $fileId . '.' . $fileExtension;
        }

        return $data;
    }

    /**
     * Prepares the form by adding row definitions
     * @param \ride\library\form\FormBuilder $builder
     * @param array $options
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];

        $builder->addRow('path', 'string', array(
            'label' => $translator->translate('label.template'),
            'multiselect' => true,
            'disabled' => true,
        ));
        $builder->addRow('content', 'text', array(
            'label' => $translator->translate('label.content'),
            'multiselect' => true,
            'attributes' => array(
                'rows' => 10,
            ),
            'filters' => array(
                'trim' => array(),
            ),
            'validators' => array(
                'required' => array(),
            )
        ));
    }

}