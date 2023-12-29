<?php
$xpdo_meta_map['mspModulbankBill']= array (
  'package' => 'mspmodulbank',
  'version' => '1.0',
  'table' => 'ms2_mspmodulbank_bill',
  'extends' => 'xPDOObject',
  'fields' =>
  array (
    'order_id' => 0,
    'bill_id' => '',
    'transaction' => '',
  ),
  'fieldMeta' =>
  array (
    'order_id' =>
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'phptype' => 'int',
      'null' => false,
      'default' => 0,
    ),
    'bill_id' =>
    array (
      'dbtype' => 'varchar',
      'precision' => '36',
      'phptype' => 'string',
      'null' => false,
    ),
    'transaction' =>
    array (
      'dbtype' => 'varchar',
      'precision' => '50',
      'phptype' => 'string',
      'null' => true,
      'default' => null,
    ),
  ),
  'indexes' =>
  array (
    'order_id' =>
    array (
      'alias' => 'order_id',
      'primary' => false,
      'unique' => false,
      'type' => 'BTREE',
      'columns' =>
      array (
        'order_id' =>
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
    'bill_id' =>
    array (
      'alias' => 'bill_id',
      'primary' => true,
      'unique' => true,
      'type' => 'BTREE',
      'columns' =>
      array (
        'bill_id' =>
        array (
          'length' => '36',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
);