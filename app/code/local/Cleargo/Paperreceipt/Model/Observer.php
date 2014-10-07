<?php
/**
 * Created by PhpStorm.
 * User: Jason Yip
 * Date: 9/22/14
 * Time: 1:05 PM
 */

class Cleargo_Paperreceipt_Model_Observer {
    public function saveTempCustomData(Varien_Event_Observer $observer) {
        $fieldVal = Mage::app()->getRequest()->getPost();
        Mage::getSingleton('core/session')->setPaperReceipt($fieldVal['paper_receipt']);
        //mage::log($fieldVal['paper_receipt'],null,test.date('Y-m-d').'.log');
        //die($fieldVal['paper_receipt']);
    }

    public function saveCustomData(Varien_Event_Observer $observer) {
        $event = $observer->getEvent();
        $order = $event->getOrder();
        $paperReceipt  =  Mage::getSingleton('core/session')->getPaperReceipt();
        $order->setPaperReceipt($paperReceipt);
        //mage::log($paperReceipt,null,test.date('Y-m-d').'.log');
        //die($paperReceipt);
    }
}