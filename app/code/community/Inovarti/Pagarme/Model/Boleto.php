<?php
/**
 *
 * @category   Inovarti
 * @package    Inovarti_Pagarme
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Pagarme_Model_Boleto extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'pagarme_boleto';

    protected $_formBlockType = 'pagarme/form_boleto';
    protected $_infoBlockType = 'pagarme/info_boleto';

	protected $_isGateway                   = true;
	protected $_canUseForMultishipping 		= false;
	protected $_isInitializeNeeded      	= true;

	public function initialize($paymentAction, $stateObject)
    {
    	$payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $this->_place($payment, $order->getBaseTotalDue());
        return $this;
    }

    public function _place(Mage_Sales_Model_Order_Payment $payment, $amount)
    {
        $order = $payment->getOrder();
        $customer = Mage::helper('pagarme')->getCustomerInfoFromOrder($payment->getOrder());
        $data = new Varien_Object();
		$data->setPaymentMethod(Inovarti_Pagarme_Model_Api::PAYMENT_METHOD_BOLETO)
			->setAmount(Mage::helper('pagarme')->formatAmount($amount))
            ->setBoletoExpirationDate($this->_generateExpirationDate())
			->setCustomer($customer)
			->setPostbackUrl(Mage::getUrl('pagarme/transaction_boleto/postback'));

		$pagarme = Mage::getModel('pagarme/api');

		$transaction = $pagarme->charge($data);
		if ($transaction->getErrors()) {
			$messages = array();
			foreach ($transaction->getErrors() as $error) {
				$messages[] = $error->getMessage() . '.';
			}
			Mage::throwException(implode("\n", $messages));
		}

		// pagar.me info
		$payment->setPagarmeTransactionId($transaction->getId())
			->setPagarmeBoletoUrl($transaction->getBoletoUrl()) // PS: Pagar.me in test mode always returns NULL
			->setPagarmeBoletoBarcode($transaction->getBoletoBarcode())
			->setPagarmeBoletoExpirationDate($transaction->getBoletoExpirationDate());

		return $this;
    }

    protected function _generateExpirationDate()
    {
        $days = $this->getConfigData('days_to_expire');
        $result = Mage::getModel('core/date')->date('Y-m-d H:i:s', strtotime("+ $days days"));
        return $result;
    }
}
