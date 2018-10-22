<?php
namespace JiaLeo\Payment\Abcpay;

use JiaLeo\Payment\Common\PaymentException;
use JiaLeo\Payment\Abcpay\Utils\MerchantConfig;
use JiaLeo\Payment\Abcpay\Utils\TrxRequest;
use JiaLeo\Payment\Abcpay\Utils\LogWriter;

class BaseAbcpay extends TrxRequest
{
    /**
     * 配置
     * @var array
     */
    public $config = array();

    /**
     * 基本配置
     * @var array
     */
    public $base_config = array(
        'TrustPayConnectMethod' => 'https',
        'TrustPayServerName' => 'pay.abchina.com',
        'TrustPayServerPort' => '443',
        'TrustPayNewLine' => '2',
        'TrustPayTrxURL' => '/ebus/trustpay/ReceiveMerchantTrxReqServlet',
        'TrustPayIETrxURL' => 'https://pay.abchina.com/ebus/ReceiveMerchantIERequestServlet',
        'MerchantKeyStoreType' => '0',
        'SignServerIP' => '如果使用签名服务器，请在此设定签名服务器的IP',
        'SignServerPort' => '如果使用签名服务器，请在此设定签名服务器的端口号',
        'SignServerPassword' => '如果使用签名服务器，请在此设定签名服务器的密码',
    );

    public function __construct($config)
    {
        if (empty($config['MerchantID'])) {
            throw new PaymentException('缺少配置MerchantID');
        }

        if (empty($config['TrustPayCertFile'])) {
            throw new PaymentException('缺少配置TrustPayCertFile');
        }

        if (empty($config['MerchantCertFile'])) {
            throw new PaymentException('缺少配置MerchantCertFile');
        }

        if (empty($config['MerchantCertPassword'])) {
            throw new PaymentException('缺少配置MerchantCertPassword');
        }

        $this->config = array_merge($this->base_config,$config);
    }

    /**
     * 支付请求
     * @return Json|Utils\Json
     * @throws Utils\TrxException
     * @throws Utils\TrxException：报文内容不合法
     */
    public function postRequest()
    {
        try
        {
            $aMerchantNo = 1;//单商户配置

            $this->iLogWriter = new LogWriter();
            $this->iLogWriter->logNewLine("TrustPayClient V3.0.0 交易开始==========================");
            $MerchantConfig = new MerchantConfig($this->config);

            $MerchantConfig::getLogWriterObject($this->iLogWriter);

            //0、检查传入参数是否合法
            if(($aMerchantNo <= 0) || ($aMerchantNo > $MerchantConfig::getMerchantNum()))
            {
                throw new TrxException(TrxException::TRX_EXC_CODE_1008, TrxException::TRX_EXC_MSG_1008,
                    '配置文件中商户数为'.$MerchantConfig::getMerchantNum().", 但是请求指定的商户配置编号为$aMerchantNo ！");
            }

            //1、检查交易请求是否合法
            $this->iLogWriter->logNewLine('检查交易请求是否合法：');
            $this->checkRequest();
            $this->iLogWriter->log('正确');

            //2、取得交易报文
            $tRequestMessage = $this->getRequestMessage();

            //3、组成完整交易报文
            $this->iLogWriter->log("完整交易报文：");
            $tRequestMessage = $this->composeRequestMessage($aMerchantNo,$tRequestMessage);
            $this->iLogWriter->log($tRequestMessage);

            //4、对交易报文进行签名
            $tRequestMessage = $MerchantConfig::signMessage($aMerchantNo, $tRequestMessage);

            //5、发送交易报文至网上支付平台
            $tResponseMessage = $this->sendMessage($tRequestMessage);

            //6、验证网上支付平台响应报文的签名
            $this->iLogWriter->logNewLine('验证网上支付平台响应报文的签名：');
            $MerchantConfig::verifySign($tResponseMessage);
            $this->iLogWriter->log('正确');

            //7、生成交易响应对象
            $this->iLogWriter->logNewLine('生成交易响应对象：');
            $this->iLogWriter->logNewLine('交易结果：['.$tResponseMessage->getReturnCode().']');
            $this->iLogWriter->logNewLine('错误信息：['.$tResponseMessage->getErrorMessage().']');

        }
        catch (TrxException $e)
        {
            $tResponseMessage = new Json();
            $tResponseMessage->initWithCodeMsg($e->getCode(), $e->getMessage()." - ".$e->getDetailMessage());
            if($this->iLogWriter != null)
            {
                $this->iLogWriter->logNewLine('错误代码：[' + $tResponseMessage->getReturnCode().']    错误信息：['.
                    $tResponseMessage->getErrorMessage().']');
            }
        }
        catch (Exception $e)
        {
            $tResponseMessage = new Json();
            $tResponseMessage->initWithCodeMsg(TrxException::TRX_EXC_CODE_1999, TrxException::TRX_EXC_MSG_1999.
                ' - '.$e->getMessage());
            if ($this->iLogWriter != null)
            {
                $this->iLogWriter->logNewLine('错误代码：['.$tResponseMessage->getReturnCode().']    错误信息：['.
                    $tResponseMessage->getErrorMessage().']');
            }
        }

        if ($this->iLogWriter != null)
        {
            $this->iLogWriter->logNewLine("交易结束==================================================\n\n\n\n");
            $this->iLogWriter->closeWriter(MerchantConfig::getTrxLogFile());
        }

        return $tResponseMessage;
    }

    /**
     * 设置自定义字段
     * @param array $data
     * @return string
     */
    public function setPassbackParams(array $data)
    {
        $str_arr = array();
        foreach ($data as $key => $v) {
            $str_arr[] = $key . '--' . $v;
        }

        $str = implode('---', $str_arr);
        return $str;
    }

    /**
     * 获取自定义字段
     * @param $str
     * @return array
     */
    public function getPassbackParams($str)
    {
        $str_arr = explode('---', $str);
        $data = array();
        foreach ($str_arr as $v) {
            $temp = explode('--', $v);
            $data[$temp[0]] = $temp[1];
        }
        return $data;
    }


}