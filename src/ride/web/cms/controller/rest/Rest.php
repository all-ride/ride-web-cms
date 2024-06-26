<?php

namespace ride\web\cms\controller\rest;

use ride\library\reflection\ReflectionHelper;

class Rest {
    protected $reflectionHelper;
    protected $sortField;
    protected $sortDirection;

    public function __construct(ReflectionHelper $reflectionHelper) {
        $this->reflectionHelper = $reflectionHelper;
    }

    public function sortData(array $data, $sortDefinition) {
        if (!$sortDefinition) {
            return $data;
        }

        $this->initSort($sortDefinition);

        usort($data, array($this, 'performSort'));

        return $data;
    }

    public function initSort($sortDefinition) {
        $sortFields = explode(',', $sortDefinition);

        $this->sortField = reset($sortFields);
        $this->sortDirection = true;

        if ($this->sortField[0] === '+') {
            $this->sortField = substr($this->sortField, 1);
        } elseif ($sortField[0] === '-') {
            $this->sortField = substr($this->sortField, 1);
            $this->sortDirection = false;
        }
    }

    public function performSort($a, $b) {
        $aValue = $this->reflectionHelper->getProperty($a, $this->sortField);
        $bValue = $this->reflectionHelper->getProperty($b, $this->sortField);

        if ($aValue == $bValue) {
            return 0;
        }

        $sortIndex =  $aValue < $bValue ? -1 : 1;
        if (!$this->sortDirection) {
            $sortIndex *= -1;
        }

        return $sortIndex;

    }

    public function limitData(array $data, $limit = null, $offset = null) {
        if (!$limit) {
            $limit = 999;
        }

        if (!$offset) {
            $offset = 0;
        }

        return array_slice($data, $offset, $limit, true);
    }

    public function selectFields(array $data, $fieldDefinition) {
        if (!$fieldDefinition) {
            return $data;
        }

        $result = array();

        $fields = explode(',', $fieldDefinition);
        foreach ($fields as $fieldIndex => $fieldName) {
            $fields[$fieldIndex] = trim($fieldName);
        }

        foreach ($data as $dataIndex => $dataValue) {
            $resultData = array();

            foreach ($fields as $fieldName) {
                $resultData[$fieldName] = $this->reflectionHelper->getProperty($dataValue, $fieldName);
            }

            $result[$dataIndex] = $resultData;
        }

        return $result;
    }

}
