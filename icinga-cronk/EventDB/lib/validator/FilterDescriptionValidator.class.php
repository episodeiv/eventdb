<?php

class FilterDescriptionValidator extends AgaviStringValidator {

    protected function validate() {
        $jsonString = $this->getData($this->getArgument());
        $filter = json_decode($jsonString, true);
        if (!$filter) {
            $this->throwError("Invalid JSON");
            return false;
        }
        $model = AgaviContext::getInstance()->getModel("EventDBFilterParser", "EventDB");

        $filterDesc = $model->getFilterDefinition($filter);
        foreach ($filterDesc as $name => $value) {
            $this->export($value, $name);
        }

        return true;
    }

}
