<?php
/**
 * @brief Wxpay component
 *
 * @author tlanyan<tlanyan@hotmail.com>
 * @link http://tlanyan.me
 */
/* vim: set ts=4; set sw=4; set ss=4; set expandtab; */

namespace tlanyan;

use Yii;
use yii\base\Object;
use yii\httpclient\Client;

class Wxpay extends Object
{
    public $appid;
    public $appkey;

    /**
     * @var merchant id
     */
    public $mchid;

    /**
     * @var Wxpay callback url
     */
    public $notifyUrl;

    /**
     * @var sign generate algorithm
     */
    public $signType = 'HMAC-SHA256';

    /**
     * @const the gate way to get prepay id
     */
    const ORDER_URL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';

    /**
     * get the pay parameter for the client
     * @var int $orderId
     * @var float $amount
     * @var string $body brief of trade
     * @var string $ip the client ip to pay
     * @var string $detail detail of this trade
     * @var string $tradeType
     * @return string prepay id
     */
    public function getPayParameter(int $orderId, float $amount, string $body, string $ip, string $detail = '', string $tradeType = 'APP')
    {
        $postData = [
            'appid' => $this->appid,
            'body' => $this->body,
            'mach_id' => $this->mchid,
            'nonce_str' => Yii::$app->security->generateRandomString(32),
            'notify_url' => $this->notifyUrl,
            'out_trade_no' => $orderId,
            'spbill_create_ip' => $ip,
            'sign_type' => $this->signType,
            'total_fee' => $amount,
            'trade_type' => $tradeType,
        ];

        $postData['sign'] = $this->getSign($postData);

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('post')
            ->setFormat(Client::FORMAT_XML)
            ->setUrl(self::ORDER_URL)
            ->setData($postData)
            ->send();
        if ($response->isOk) {
            $data = $response->data;
            if ($data['return_code'] === 'SUCCESS') {
                return $data['prepay_id'];
            } else {
                Yii::error($data);
                return null;
            }
        }
    }

    /**
     * @var array $data the array to generate sign
     * @return string
     */
    private function getSign($data)
    {
        $data = array_filter($data);
        ksort($data);
        $stringA = http_build_query($data);
        $stringSignTemp = $stringA . '&key=' . $this->appkey;

        if ($this->signType === 'HMAC-SHA256') {
            return hash_hmac('sha256', $stringSignTemp, $this->appkey);
        } else {
            return strtoupper(md5($stringSignTemp));
        }
    }

    /**
     * check the integrity of the callback post data
     * @var array $data the post data array
     * @return boolean
     */
    public function checkSign($data)
    {
        $sign = $data['sign'];
        unset($data['sign']);

        if ($this->getSign($data) === $sign) {
            return true;
        }

        return false;
    }

    public function init()
    {
        parent::init();

        $this->signType = strtoupper($this->signType);
        if ($this->signType !== 'HMAC-SHA256') {
            $this->signType = 'MD5';
        }
    }
}