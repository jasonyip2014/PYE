<?php


$installer = $this;
$installer->startSetup();
$installer->addAttribute('order', 'paper_receipt', array('type'=>'int'));
$installer->addAttribute('quote', 'paper_receipt', array('type'=>'int'));


$installer->endSetup();

