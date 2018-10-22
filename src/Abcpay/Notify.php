<?php

namespace JiaLeo\Payment\Abcpay;

use JiaLeo\Payment\Common\PaymentException;
use JiaLeo\Payment\Abcpay\Utils\LogWriter;
use JiaLeo\Payment\Abcpay\Utils\MerchantConfig;
use JiaLeo\Payment\Abcpay\Utils\TrxException;
use JiaLeo\Payment\Abcpay\Utils\XMLDocument;
use JiaLeo\Payment\Abcpay\Utils\TrxResponse;

class Notify extends BaseAbcpay
{
    private $iLogWriter = null;

    public $rawData = array();

    /**
     * 处理回调
     * @return mixed
     * @throws PaymentException
     */
    public function handle()
    {

        if (empty($_POST) && empty($_GET)) {
            return false;
        }
        $data = $_POST ?  : $_GET;

        $this->rawData = $data['MSG'];

        try {
            //1、还原经过base64编码的信息
            $tMessage = base64_decode($this->rawData);

            $MerchantConfig = new MerchantConfig($this->config);

            $MerchantConfig :: verifySignXML($tMessage);

            $tResponse = new TrxResponse($tMessage);

        } catch (TrxException $e) {
            $tResponse = new TrxResponse();
            if ($this->iLogWriter != null) {
                $this->iLogWriter->logNewLine('错误代码：[' . $e->getCode() . ']    错误信息：[' .
                    $e->getMessage() . " - " . $e->getDetailMessage() . ']');
            }
            $tResponse->initWithCodeMsg($e->getCode(), $e->getMessage());
        } catch (Exception $e) {
            $tResponse = new TrxResponse($tMessage);
            if ($this->iLogWriter != null) {
                $this->iLogWriter->logNewLine('错误代码：[' . TrxException :: TRX_EXC_CODE_199 . ']    错误信息：[' .
                    $e->getMessage() . ']');
            }
            $tResponse->initWithCodeMsg(TrxException :: TRX_EXC_CODE_199, $e->getMessage());
        }

        $data = array();
        if ($tResponse->isSuccess()){
            $data['merchant_id'] = $tResponse->getMerchantID();
            $data['trx_type'] = $tResponse->getValue('TrxType');
            $data['order_no'] = $tResponse->getValue('OrderNo');
            $data['amount'] = $tResponse->getValue('Amount');
            $data['batch_no'] = $tResponse->getValue('BatchNo');
            $data['voucher_no'] = $tResponse->getValue('VoucherNo');
            $data['host_date'] = $tResponse->getValue('HostDate');
            $data['host_time'] = $tResponse->getValue('HostTime');
            $data['merchant_remarks'] = $tResponse->getValue('MerchantRemarks');
            $data['pay_type'] = $tResponse->getValue('PayType');
            $data['notify_type'] = $tResponse->getValue('NotifyType');
            $data['i_rsp_ref'] = $tResponse->getValue('iRspRef');
        }else{
            throw new PaymentException('交易失败!');
        }

        return $data;
    }

    /**
     *  回复成功
     */
    public function returnSuccess()
    {
        echo 'success';
    }

    /**
     *  回复失败
     */
    public function returnFailure()
    {
        echo 'failure';
    }
}