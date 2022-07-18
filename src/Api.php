<?php


namespace Fize\Third\PingAn;

use Fize\Codec\Base64;
use Fize\Security\Hash;


/**
 * 公用API
 */
class Api
{

    /**
     * @var string SecretKey
     */
    protected $secretKey;

    /**
     * @var string AccessKey
     */
    protected $accessKey;

    /**
     * 初始化
     * @param string $access_key AccessKey
     * @param string $secret_key SecretKey
     */
    public function __construct(string $access_key, string $secret_key)
    {
        $this->accessKey = $access_key;
        $this->secretKey = $secret_key;
    }

    /**
     * 生成签名
     * @param string      $the_request_resource      请求资源，注意GET参数要进行自然顺序排序
     * @param string      $http_verb                 请求动作
     * @param string      $content_md5               请求体MD5值
     * @param string      $content_type              请求体类型
     * @param string|null $date                      GMT时间，不传递默认当前时间
     * @param array       $user_defined_meta_headers 自定义头
     * @return string
     */
    public function getAuthorization(string $the_request_resource, string $http_verb, string $content_md5, string $content_type, string $date = null, array $user_defined_meta_headers = []): string
    {
        $sign = $this->getSign($the_request_resource, $http_verb, $date, $content_md5, $content_type, $user_defined_meta_headers);
        return 'AWS ' . $this->accessKey . ':' . $sign;
    }

    /**
     * 生成签名
     * @param string      $the_request_resource      请求资源，注意GET参数要进行自然顺序排序
     * @param string      $http_verb                 请求动作
     * @param string|null $date                      GMT时间，不传递默认当前时间
     * @param string      $content_md5               请求体MD5值
     * @param string      $content_type              请求体类型
     * @param array       $user_defined_meta_headers 自定义头
     * @return string
     */
    public function getSign(string $the_request_resource, string $http_verb, string $date = null, string $content_md5 = '', string $content_type = '', array $user_defined_meta_headers = []): string
    {
        $str_to_sign = '';
        $str_to_sign .= $http_verb . "\n";
        $str_to_sign .= $content_md5 . "\n";
        $str_to_sign .= $content_type . "\n";
        if (is_null($date)) {
            $date = gmdate('D, d M Y H:i:s T');
        }
        $str_to_sign .= $date . "\n";

        ksort($user_defined_meta_headers);
        foreach ($user_defined_meta_headers as $key => $val) {
            $str_to_sign .= "x-amz-meta-$key:$val" . "\n";
        }
        $str_to_sign .= $the_request_resource;

        $sha1 = Hash::hmac('sha1', $str_to_sign, $this->secretKey, true);
        return Base64::encode($sha1);
    }
}
