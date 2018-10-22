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
use JiaLeo\Payment\Abcpay\Utils\IIsBreakAccountType;
use JiaLeo\Payment\Abcpay\Utils\IFunctionID;
use JiaLeo\Payment\Common\PaymentException;

class WebPay extends BaseAbcpay{
    /**
     * 订单信息
     * @var array
     */
    public $order = array (
        "PayTypeID" => "ImmediatePay", //交易类型  直接支付
        "OrderNo" => "", //交易编号
        "ExpiredDate" => "30", //订单保存时间 单位:天
        "OrderAmount" => "", //交易金额 保留小数点后两位数字
        "Fee" => "", //手续费金额 保留小数点后两位数字
        "CurrencyCode" => "156",//156币种
        "ReceiverAddress" => "", //售后地址
        "InstallmentMark" => "", //分期相关
        "InstallmentCode" => "", //分期相关
        "InstallmentNum" => "", //分期相关
        "BuyIP" => "",//购买者ip
        "OrderDesc" => "",//订单说明
        "OrderURL" => "",//订单地址
        "OrderDate" => "",//订单日期 (YYYY/MM/DD）
        "OrderTime" => "",//订单时间 (HH:MM:SS）
        "orderTimeoutDate" => "",//订单支付有效期,精确到秒
        "CommodityType" => "0202"//商品种类 0202-消费类-传统类
    );

    /**
     * 订单商品
     * @var array
     */
    public $orderitems = array ();

    /**
     * 请求信息
     * @var array
     */
    public $request = array (
        "TrxType" => IFunctionID :: TRX_TYPE_PAY_REQ,
        "PaymentType" => "A",//支付账户类型 1：农行卡支付 2：国际卡支付 3：农行贷记卡支付 5:基于第三方的跨行支付 A:支付方式合并 6：银联跨行支付
        "PaymentLinkType" => "1",//交易渠道 internet网络接入 2：手机网络接入 3:数字电视网络接入 4:智能客户端
        "UnionPayLinkType" => "",//银联跨行移动支付接入方式 ,非必须
        "ReceiveAccount" => "",//收款方账号
        "ReceiveAccName" => "",//收款方户名
        "NotifyType" => "1",//通知方式 0：URL页面通知 1：服务器通知
        "ResultNotifyURL" => "",//异步通知URL地址
        "MerchantRemarks" => "",//附言
        "IsBreakAccount" => "0",//交易是否分账
        "SplitAccTemplate" => ""//分账模版编号
    );

    /**
     * 格式化请求信息
     * @return bool|string
     */
    protected function getRequestMessage() {
        Json :: arrayRecursive($this->order, "urlencode", false);
        Json :: arrayRecursive($this->request, "urlencode", false);

        $js = '"Order":' . (json_encode(($this->order)));
        $js = substr($js, 0, -1);
        $js = $js . ',"OrderItems":[';
        $count = count($this->orderitems, COUNT_NORMAL);
        for ($i = 0; $i < $count; $i++) {
            Json :: arrayRecursive($this->orderitems[$i], "urlencode", false);
            $js = $js . json_encode($this->orderitems[$i]);
            if ($i < $count -1) {
                $js = $js . ',';
            }
        }
        $js = $js . ']}}';
        $tMessage = json_encode($this->request);
        $tMessage = substr($tMessage, 0, -1);
        $tMessage = $tMessage . ',' . $js;
        $tMessage = urldecode($tMessage);
        return $tMessage;
    }

    /**
     * 支付请求信息是否合法
     * @throws PaymentException
     */
    protected function checkRequest() {
        $tError = $this->isValid();
        if ($tError != null)
            throw new PaymentException('调起支付失败!错误信息:'.$tError);
            //throw new TrxException(TrxException :: TRX_EXC_CODE_1101, TrxException :: TRX_EXC_MSG_1101 . "订单信息不合法！[" . $tError . "]");
    }

    /**
     * 支付请求信息是否合法
     * @return string
     * @throws PaymentException
     */
    private function isValid() {

        $time = time();
        $this->order['OrderDate'] = date('Y/m/d',$time);
        $this->order['OrderTime'] = date('H:i:s',$time);

        //过期时间
        $this->order['orderTimeoutDate'] = date('YmdHis',$time + 86400 * 30);

        // 检查金额不能低于0.01
        if (bccomp($this->order['OrderAmount'] / 100, '0.01', 2) === -1) {
            throw new PaymentException('支付金额不能低于 0.01 元');
        }

        $this->order['OrderAmount'] = (string)($this->order['OrderAmount'] / 100);

        if ($this->request["PaymentType"] === IPaymentType :: PAY_TYPE_UCBP && $this->request["PaymentLinkType"] === IChannelType :: PAY_LINK_TYPE_MOBILE) {
            if (!($this->request["UnionPayLinkType"] === IChannelType :: UPMPLINK_TYPE_WAP) && !($this->request["UnionPayLinkType"] === IChannelType :: UPMPLINK_TYPE_CLIENT))
                return "银联跨行移动支付接入方式不合法";
        } else {
            unset ($this->request["UnionPayLinkType"]);
        }

        if (!($this->request["NotifyType"] === INotifyType :: NOTIFY_TYPE_URL) && !($this->request["NotifyType"] === INotifyType :: NOTIFY_TYPE_SERVER))
            return "支付通知类型不合法！";

        if (!(DataVerifier :: isValidURL($this->request["ResultNotifyURL"])))
            return "支付结果回传网址不合法！";

        if (strlen($this->request["MerchantRemarks"]) > 100) {
            return "附言长度大于100";
        }
        if (($this->request["IsBreakAccount"] !== IIsBreakAccountType :: IsBreak_TYPE_YES) && ($this->request["IsBreakAccount"] !== IIsBreakAccountType :: IsBreak_TYPE_NO)) {
            return "交易是否分账设置异常，必须为：0或1";
        }

        //验证order信息
        $payTypeId = $this->order["PayTypeID"];
        if (!($payTypeId === IPayTypeID :: PAY_TYPE_DIRECTPAY) && !($payTypeId === IPayTypeID :: PAY_TYPE_PREAUTH) && !($payTypeId === IPayTypeID :: PAY_TYPE_INSTALLMENTPAY))
            return "设定交易类型错误";

        if ($payTypeId === IPayTypeID :: PAY_TYPE_INSTALLMENTPAY) {
            if (!($this->order["InstallmentMark"] === IInstallmentmark :: INSTALLMENTMARK_YES)) {
                return "分期标识为空或输入非法";
            } else {
                if (strlen($this->order["InstallmentCode"]) !== 8) {
                    return "分期代码长度应该为8位";
                }

                if (!DataVerifier :: isValid($this->order["InstallmentNum"]) || (strlen($this->order["InstallmentNum"]) > 2)) {
                    return "分期期数非有效数字或者长度超过2";
                }
            }
        } else {
            unset ($this->order["InstallmentCode"]);
            unset ($this->order["InstallmentNum"]);
        }
        if ((($payTypeId === IPayTypeID :: PAY_TYPE_DIRECTPAY) || ($payTypeId === IPayTypeID :: PAY_TYPE_PREAUTH)) && ($this->order["InstallmentMark"] === IInstallmentmark :: INSTALLMENTMARK_YES))
            return "交易类型为直接支付或预授权支付时，分期标识不允许输入为“1”";
        if (!DataVerifier :: isValidString($this->order["OrderNo"], ILength :: ORDERID_LEN))
            return "交易编号不合法";
        if (!DataVerifier :: isValidDate($this->order["OrderDate"]))
            return "订单日期不合法";
        if (!DataVerifier :: isValidTime($this->order["OrderTime"]))
            return "订单时间不合法";
        if (!ICommodityType :: InArray($this->order["CommodityType"]))
            return "商品种类不合法";
        if (!DataVerifier :: isValidAmount($this->order["OrderAmount"], 2))
            return "订单金额不合法";
        if ($this->order["CurrencyCode"] !== "156")
            return "设定交易币种错误";

        #region 验证$orderitems信息（订单明细）
        if (count($this->orderitems, COUNT_NORMAL) < 1)
            return "商品明细为空";
        foreach ($this->orderitems as $orderitem) {
            if (!DataVerifier :: isValidString($orderitem["ProductName"], ILength :: PRODUCTNAME_LEN))
                return "产品名称不合法";
        }
        return "";
    }
}
?>