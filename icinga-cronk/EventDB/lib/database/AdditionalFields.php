<?php

class AdditionalFields extends Doctrine_Template
{
    public function setTableDefinition()
    {
        $cronksAndCategories = include(
            AgaviConfigCache::checkConfig(AgaviConfig::get('core.config_dir') . '/cronks.xml')
        );
        $columns = array();
        foreach ($cronksAndCategories[0] as $name => $config) {
            if ($config['module'] === 'EventDB'
                && (bool) $config['hide'] !== true
                && isset($config['ae:parameter'])
                && isset($config['ae:parameter']['additionalFields'])
            ) {
                foreach ($config['ae:parameter']['additionalFields'] as $column) {
                    if (isset($column['dataIndex'])) {
                        $columns[] = $column['dataIndex'];
                    }
                }
            }
        }
        foreach (array_unique($columns) as $column) {
            $this->hasColumn($column, 'string');
        }
    }
}
