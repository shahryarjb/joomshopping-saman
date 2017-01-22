<?php
/**
 * @package     Joomla - > Site and Administrator payment info
 * @subpackage  com_Jshopping
 * @subpackage 	trangell_Saman
 * @copyright   trangell team => https://trangell.com
 * @copyright   Copyright (C) 20016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die();

if (!class_exists ('checkHack')) {
    require_once dirname(__FILE__). '/trangell_inputcheck.php';
}


class pm_trangellzarinpal extends PaymentRoot{
    
    function showPaymentForm($params, $pmconfigs){	
        include(dirname(__FILE__)."/paymentform.php");
    }

	//function call in admin
	function showAdminFormParams($params){
		$array_params = array('transaction_end_status', 'transaction_pending_status', 'transaction_failed_status');
		foreach ($array_params as $key){
			if (!isset($params[$key])) $params[$key] = '';
		} 
		$orders = JSFactory::getModel('orders', 'JshoppingModel'); //admin model
		include(dirname(__FILE__)."/adminparamsform.php");
	}

	function showEndForm($pmconfigs, $order){
		$app	= JFactory::getApplication();
        $uri = JURI::getInstance(); 
        $pm_method = $this->getPmMethod();       
        $liveurlhost = $uri->toString(array("scheme",'host', 'port'));
        $return = $liveurlhost.SEFLink("index.php?option=com_jshopping&controller=checkout&task=step7&act=return&js_paymentclass=".$pm_method->payment_class).'&orderId='. $order->order_id;		
    	$notify_url2 = $liveurlhost.SEFLink("index.php?option=com_jshopping&controller=checkout&task=step2&act=notify&js_paymentclass=".$pm_method->payment_class."&no_lang=1");	
		//======================================================
	
		if (!isset($MerchantId)) {	
			$app->redirect($notify_url2, '<h2>لطفا تنظیمات درگاه سامان را بررسی کنید</h2>', $msgType='Error'); 
		}
		
		$dateTime = JFactory::getDate();
			
		$merchantId = $pmconfigs['samanmerchantId'];
		$reservationNumber = time();
		$totalAmount =  $this->fixOrderTotal($order);
		$callBackUrl  = $return;
		$sendUrl = "https\://sep.shaparak.ir/Payment.aspx";
		
		echo '
			<script>
				var form = document.createElement("form");
				form.setAttribute("method", "POST");
				form.setAttribute("action", "'.$sendUrl.'");
				form.setAttribute("target", "_self");

				var hiddenField1 = document.createElement("input");
				hiddenField1.setAttribute("name", "Amount");
				hiddenField1.setAttribute("value", "'.$totalAmount.'");
				form.appendChild(hiddenField1);
				
				var hiddenField2 = document.createElement("input");
				hiddenField2.setAttribute("name", "MID");
				hiddenField2.setAttribute("value", "'.$merchantId.'");
				form.appendChild(hiddenField2);
				
				var hiddenField3 = document.createElement("input");
				hiddenField3.setAttribute("name", "ResNum");
				hiddenField3.setAttribute("value", "'.$reservationNumber.'");
				form.appendChild(hiddenField3);
				
				var hiddenField4 = document.createElement("input");
				hiddenField4.setAttribute("name", "RedirectURL");
				hiddenField4.setAttribute("value", "'.$callBackUrl.'");
				form.appendChild(hiddenField4);
				

				document.body.appendChild(form);
				form.submit();
				document.body.removeChild(form);
			</script>'
		;

	}
    
		function checkTransaction($pmconfigs, $order, $act){
			$app	= JFactory::getApplication();
			$jinput = $app->input;
			$uri = JURI::getInstance(); 
			$pm_method = $this->getPmMethod();       
			$liveurlhost = $uri->toString(array("scheme",'host', 'port'));
			$cancel_return = $liveurlhost.SEFLink("index.php?option=com_jshopping&controller=checkout&task=step7&act=cancel&js_paymentclass=".$pm_method->payment_class).'&orderId='. $order->order_id;	
			// $Mobile = $order->phone;
            //==================================================================
		
			$resNum = $jinput->post->get('ResNum', '0', 'INT');
			$trackingCode = $jinput->post->get('TRACENO', '0', 'INT');
			$stateCode = $jinput->post->get('stateCode', '1', 'INT');
			
			$refNum = $jinput->post->get('RefNum', 'empty', 'STRING');
			if (checkHack::strip($refNum) != $refNum )
				$refNum = "illegal";
			$state = $jinput->post->get('State', 'empty', 'STRING');
			if (checkHack::strip($state) != $state )
				$state = "illegal";
			$cardNumber = $jinput->post->get('SecurePan', 'empty', 'STRING'); 
			if (checkHack::strip($cardNumber) != $cardNumber )
				$cardNumber = "illegal";
				
			$price = $this->fixOrderTotal($order);	
			$merchantId = $pmconfigs['samanmerchantId'];

			if (
				checkHack::checkNum($resNum) &&
				checkHack::checkNum($trackingCode) &&
				checkHack::checkNum($stateCode) 
			){
				if (isset($state) && ($state == 'OK' || $stateCode == 0)) {
					try {
						$out    = new SoapClient('https://sep.shaparak.ir/payments/referencepayment.asmx?WSDL');
						$resultCode    = $out->VerifyTransaction($refNum, $merchantId);
					
						if ($resultCode == $price) {
							$this->onPaymentSuccess($id, $trackingCode); 
							$message = "کد پیگیری".$trackingCode."<br>" ."شماره سفارش ".$order->order_id;
							$app->enqueueMessage($message, 'message');
						    saveToLog("payment.log", "Status Complete. Order ID ".$order->order_id.". message: ".$msg . " statud_code: " . $trackingCode);
							return array(1, "");
						}
						else {
							$msg= $this->getGateMsg($state); 
							saveToLog("payment.log", "Status failed. Order ID ".$order->order_id.". message: ".$msg );
							$app->redirect($cancel_return, '<h2>'.$msg.'</h2>', $msgType='Error'); 
						}
					}
					catch(\SoapFault $e)  {
						$msg= $this->getGateMsg('error'); 
						saveToLog("payment.log", "Status failed. Order ID ".$order->order_id.". message: ".$msg );
						$app->redirect($cancel_return, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
					}
				}
				else {
					$msg= $this->getGateMsg($state);
					saveToLog("payment.log", "Status Cancelled. Order ID ".$order->order_id.". message: ".$msg );
					$app->redirect($cancel_return, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
				}
			}
			else {
				$msg= $this->getGateMsg('hck2'); 
				saveToLog("payment.log", "Status failed. Order ID ".$order->order_id.". message: ".$msg );
				$app->redirect($cancel_return, '<h2>'.$msg.'</h2>' , $msgType='Error'); 
			}
	}


    function getUrlParams($pmconfigs){
		$app	= JFactory::getApplication();
		$jinput = $app->input;
		$oId = $jinput->get->get('orderId', '0', 'INT');
        $params = array(); 
        $params['order_id'] = $oId;
        $params['hash'] = "";
        $params['checkHash'] = 0;
        $params['checkReturnParams'] = 0;
		return $params;
    }
    
	function fixOrderTotal($order){
        $total = $order->order_total;
        if ($order->currency_code_iso=='HUF'){
            $total = round($total);
        }else{
            $total = number_format($total, 2, '.', '');
        }
    return $total;
    }

    public function getGateMsg ($msgId) {
		switch($msgId){
			case '-1': $out=  'خطای داخل شبکه مالی'; break;
			case '-2': $out=  'سپردها برابر نیستند'; break;
			case '-3': $out=  'ورودی های حاوی کاراکترهای غیر مجاز می باشد'; break;
			case '-4': $out=  'کلمه عبور یا کد فروشنده اشتباه است'; break;
			case '-5': $out=  'Database excetion'; break;
			case '-6': $out=  'سند قبلا برگشت کامل یافته است'; break;
			case '-7': $out=  'رسید دیجیتالی تهی است'; break;
			case '-8': $out=  'طول ورودی های بیش از حد مجاز است'; break;
			case '-9': $out=  'وجود کاراکترهای غیر مجاز در مبلغ برگشتی'; break;
			case '-10': $out=  'رسید دیجیتالی حاوی کاراکترهای غیر مجاز است'; break;
			case '-11': $out=  'طول ورودی های کمتر از حد مجاز است'; break;
			case '-12': $out=  'مبلغ برگشت منفی است'; break;
			case '-13': $out=  'مبلغ برگشتی برای برگشت جزیی بیش از مبلغ برگشت نخورده رسید دیجیتالی است'; break;
			case '-14': $out=  'چنین تراکنشی تعریف نشده است'; break;
			case '-15': $out=  'مبلغ برگشتی به صورت اعشاری داده شده است'; break;
			case '-16': $out=  'خطای داخلی سیستم'; break;
			case '-17': $out=  'برگشت زدن جزیی تراکنشی که با کارت بانکی غیر از بانک سامان انجام پذیرفته است'; break;
			case '-18': $out=  'IP Adderess‌ فروشنده نامعتبر'; break;
			case 'Canceled By User': $out=  'تراکنش توسط خریدار کنسل شده است'; break;
			case 'Invalid Amount': $out=  'مبلغ سند برگشتی از مبلغ تراکنش اصلی بیشتر است'; break;
			case 'Invalid Transaction': $out=  'درخواست برگشت یک تراکنش رسیده است . در حالی که تراکنش اصلی پیدا نمی شود.'; break;
			case 'Invalid Card Number': $out=  'شماره کارت اشتباه است'; break;
			case 'No Such Issuer': $out=  'چنین صادر کننده کارتی وجود ندارد'; break;
			case 'Expired Card Pick Up': $out=  'از تاریخ انقضا کارت گذشته است و کارت دیگر معتبر نیست'; break;
			case 'Allowable PIN Tries Exceeded Pick Up': $out=  'رمز (PIN) کارت ۳ بار اشتباه وارد شده است در نتیجه کارت غیر فعال خواهد شد.'; break;
			case 'Incorrect PIN': $out=  'خریدار رمز کارت (PIN) را اشتباه وارده کرده است'; break;
			case 'Exceeds Withdrawal Amount Limit': $out=  'مبلغ بیش از سقف برداشت می باشد'; break;
			case 'Transaction Cannot Be Completed': $out=  'تراکنش تایید شده است ولی امکان سند خوردن وجود ندارد'; break;
			case 'Response Received Too Late': $out=  'تراکنش در شبکه بانکی  timeout خورده است'; break;
			case 'Suspected Fraud Pick Up': $out=  'خریدار فیلد CVV2 یا تاریخ انقضا را اشتباه وارد کرده و یا اصلا وارد نکرده است.'; break;
			case 'No Sufficient Funds': $out=  'موجودی به اندازه کافی در حساب وجود ندارد'; break;
			case 'Issuer Down Slm': $out=  'سیستم کارت بانک صادر کننده در وضعیت عملیاتی نیست'; break;
			case 'TME Error': $out=  'کلیه خطاهای دیگر بانکی که باعث ایجاد چنین خطایی می گردد'; break;
			case '1': $out=  'تراکنش با موفقیت انجام شده است'; break;
			case 'error': $out ='خطا غیر منتظره رخ داده است';break;
			case 'hck2': $out = 'لطفا از کاراکترهای مجاز استفاده کنید';break;
            default: $out ='خطا غیر منتظره رخ داده است';break;
		}
		return $out;
	}
}
