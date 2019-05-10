<?php
namespace JiaLeo\Payment\Abcpay;

use JiaLeo\Payment\Abcpay\Utils\Json;
use JiaLeo\Payment\Abcpay\Utils\IChannelType;
use JiaLeo\Payment\Abcpay\Utils\IPaymentType;
use JiaLeo\Payment\Abcpay\Utils\INotifyType;
use JiaLeo\Payment\Abcpay\Utils\DataVerifier;
use JiaLeo\Payment\Abcpay\Utils\ILength;
use JiaLeo\Payment\Abcpay\Utils\IPayTypeID;
use JiaLeo\Payment\Abcpay\Utils\IInstallmentmark;
use JiaLeo\Payment\Abcpay\Utils\ICommodityType;
use JiaLeo\Payment\Abcpay\Utils\IFunctionID;

class Refund extends BaseAbcpay {
    public $request = array (
        "TrxType" => IFunctionID :: TRX_TYPE_REFUND,
        "OrderDate" => "",
        "OrderTime" => "",
        "MerRefundAccountNo" => "",
        "MerRefundAccountName" => "",
        "OrderNo" => "",
        "NewOrderNo" => "",
        "CurrencyCode" => "156",
        "TrxAmount" => "",
        "MerchantRemarks" => ""
    );

    protected function getRequestMessage() {
        Json :: arrayRecursive($this->request, "urlencode", false);
        $tMessage = json_encode($this->request);
        $tMessage = urldecode($tMessage);
        return $tMessage;
    }

    /// 支付请求信息是否合法
    protected function checkRequest() {
        if (!DataVerifier :: isValidString($this->request["OrderNo"], ILength :: ORDERID_LEN))
            throw new TrxException(TrxException :: TRX_EXC_CODE_1100, TrxException :: TRX_EXC_MSG_1100, "原交易编号不合法！");
        if (!DataVerifier :: isValidString($this->request["NewOrderNo"], ILength :: ORDERID_LEN))
            throw new TrxException(TrxException :: TRX_EXC_CODE_1100, TrxException :: TRX_EXC_MSG_1100, "交易编号不合法！");
        if (!DataVerifier :: isValidDate($this->request["OrderDate"]))
            throw new TrxException(TrxException :: TRX_EXC_CODE_1100, TrxException :: TRX_EXC_MSG_1100, "订单日期不合法！");
        if (!DataVerifier :: isValidTime($this->request["OrderTime"]))
            throw new TrxException(TrxException :: TRX_EXC_CODE_1100, TrxException :: TRX_EXC_MSG_1100, "订单时间不合法！");
        if ($this->request["CurrencyCode"] !== "156")
            throw new TrxException(TrxException :: TRX_EXC_CODE_1100, TrxException :: TRX_EXC_MSG_1100, "设定交易币种不合法！");
        if (!DataVerifier :: isValidAmount($this->request["TrxAmount"], 2))
            throw new TrxException(TrxException :: TRX_EXC_CODE_1101, TrxException :: TRX_EXC_MSG_1101, "交易金额不合法！");
    }
}
?>