<?php

/**
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 */

$installer = $this;
$installer->startSetup();

/*
 * Add order infos
 *  export_finish_lengow - boolean
 *  
 */
$order_entity_id = $installer->getEntityTypeId('order');

$list_attribute[] = array(
    'name' => 'export_finish_lengow',
    'label' => 'Export finish lengow',
    'type' => 'int',
    'input' => 'select',
    'source' => 'eav/entity_attribute_source_boolean',
    'default' => 1,
    'grid' => false,
);

$list_attribute[] = array(
    'name' => 'delivery_address_id_lengow',
    'label' => 'Delivery address id lengow',
    'type' => 'int',
    'input' => 'text',
    'source' => '',
    'default' => 0,
    'grid' => false,
);

foreach ($list_attribute as $attr) {
    $order_attribute = $installer->getAttribute($order_entity_id, $attr['name']);
    if (!$order_attribute) {
        $installer->addAttribute('order', $attr['name'], array(
            'name' => $attr['name'],
            'label' => $attr['label'],
            'type' => $attr['type'],
            'visible' => true,
            'required' => false,
            'unique' => false,
            'filterable' => 1,
            'sort_order' => 700,
            'default' => $attr['default'],
            'input' => $attr['input'],
            'source' => $attr['source'],
            'grid'   => $attr['grid'],
        ));
    }
    $usedInForms = array(
        'adminhtml_order',
    );
}

$installer->run(
    "CREATE TABLE IF NOT EXISTS `{$this->getTable('lengow_order_line')}` (
    `id` int(11) NOT NULL auto_increment,
    `id_order` int(11) NOT NULL,
    `id_order_line` text NOT NULL,
    PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
);

$installer->endSetup();
