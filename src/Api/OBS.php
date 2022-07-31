<?php


namespace Fize\Third\PingAn\Api;

use Fize\Codec\XML;
use Fize\Http\ClientSimple;
use Fize\Third\PingAn\Api;
use RuntimeException;


/**
 * OBS
 */
class OBS extends Api
{

    /**
     * 域名
     */
    public const DOMAIN = 'https://obs-cn-shanghai.fincloud.pinganyun.com';

    /**
     * @var string 存储桶名
     */
    protected $bucket = '';

    /**
     * @var bool 是否内网传输
     */
    protected $internalUpload = false;

    /**
     * 设置存储桶
     * @param string $bucket 存储桶名称
     */
    public function setBucket(string $bucket)
    {
        $this->bucket = $bucket;
    }

    /**
     * 设置是否内网传输
     * @param bool $bool 布尔值
     */
    public function setInternalUpload(bool $bool)
    {
        $this->internalUpload = $bool;
    }

    /**
     * 上传文件
     * @param string      $file   要上传的文件
     * @param string|null $key    保存文件名，默认原文件名
     * @param string|null $bucket 存储桶，不设置则为默认存储桶
     * @return string 返回存储桶文件URL
     */
    public function putObject(string $file, string $key = null, string $bucket = null): string
    {
        if (is_null($key)) {
            $key = basename($file);
        }
        $key = urlencode($key);

        $bucket = $bucket ?: $this->bucket;

        $url_out = 'https://' . $bucket . '.obs-cn-shanghai.fincloud.pinganyun.com/' . $key;  // 外网
        $url = $url_out;
        if ($this->internalUpload) {
            $url = 'https://obs-cn-shanghai-papub.fincloud.pinganyun.com/' . $bucket . '/' . $key;  // 内网
        }

        $request_resource = "/" . $bucket . '/' . $key;
        $http_verb = 'PUT';
        $headers = $this->getHeaders($file, $request_resource, $http_verb);
        $opts = [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => fopen($file, 'rb')
        ];

        $data = file_get_contents($file);
        $response = ClientSimple::put($url, $data, $headers, $opts, ['time_out' => 3000]);
        if (!$response->hasHeader('etag')) {
            throw new RuntimeException("上传失败！");
        }

        return $url_out;
    }

    /**
     * 获取签名后URL
     * @param string      $key                 资源标识
     * @param int|null    $expires             失效时间戳，不设置为最长15分钟
     * @param string|null $content_disposition 期望云存储服务端返回的 content-disposition 头信息
     * @param array       $params              其他自定义参数
     * @param string|null $bucket              存储桶
     * @return string
     */
    public function getSignedUrl(string $key, int $expires = null, string $content_disposition = null, array $params = [], string $bucket = null): string
    {
        $key = urlencode($key);
        if (is_null($expires)) {
            $expires = time() + 15 * 60;
        }
        $bucket = $bucket ?: $this->bucket;

        $request_resource = "/" . $bucket . '/' . $key;
        if ($content_disposition) {
            $request_resource .= '?response-content-disposition=' . $content_disposition;
        }
        $sign = $this->getSign($request_resource, 'GET', $expires, '', '', $params);

        $url = '';
        //$url .= 'https://' . $bucket . '.obs-cn-shanghai.pinganyun.com/' . $key;
        $url .= self::DOMAIN . '/' . $bucket . '/' . $key;
        $url .= '?AWSAccessKeyId=' . urlencode($this->accessKey);
        $url .= '&Expires=' . $expires;
        $url .= '&Signature=' . urlencode($sign);
        if ($content_disposition) {
            $url .= '&response-content-disposition=' . $content_disposition;
        }

        foreach ($params as $key => $val) {
            $url .= "&$key=" . urlencode($val);
        }
        return $url;
    }

    public function batchPutObject()
    {

    }

    /**
     * 分片上传文件
     * @param string      $file      要上传的文件
     * @param int         $part_size 分页大小
     * @param string|null $key       资源标识
     * @param string|null $bucket    指定存储桶
     * @return bool
     */
    public function putObjectMultipart(string $file, int $part_size, string $key = null, string $bucket = null): bool
    {
        if (is_null($key)) {
            $key = basename($file);
        }
        $key = urlencode($key);
        $bucket = $bucket ?: $this->bucket;
        $init = $this->putObjectMultipartInit($file, $key, $bucket);

        $etags = [];
        $i = 0;
        $fp = fopen($file, "rb");
        while (!feof($fp)) {
            $i++;
            $data = fread($fp, $part_size);
            $etag = $this->putObjectMultipartUpload($init['UploadId'], $data, $i, $key, $bucket);
            $etags[] = $etag;
        }
        fclose($fp);

        $fin_etag = $this->putObjectMultipartComplete($init['UploadId'], $etags, $key, $bucket);
        return $fin_etag;
    }

    /**
     * 分片上传文件初始化
     * @param string $file   文件路径
     * @param string $key    资源标识
     * @param string $bucket 指定存储桶
     * @return array
     */
    protected function putObjectMultipartInit(string $file, string $key, string $bucket): array
    {
        $url = self::DOMAIN . '/' . $bucket . '/' . $key . '?uploads';

        $request_resource = "/" . $bucket . '/' . $key . '?uploads';
        $http_verb = 'POST';
        $headers = $this->getHeaders($file, $request_resource, $http_verb);
        $opts = [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0
        ];

        $response = ClientSimple::post($url, '', $headers, $opts, ['time_out' => 3000]);
        $array = XML::decode($response->getBody());
        return $array;
    }

    /**
     * 分片上传文件上传中
     * @param string $upload_id   上传标识
     * @param string $data        文件截取数据流
     * @param int    $part_number 第几个切片，下标由1开始
     * @param string $key         资源标识
     * @param string $bucket      指定存储桶
     * @return string 返回etag
     */
    protected function putObjectMultipartUpload(string $upload_id, string $data, int $part_number, string $key, string $bucket): string
    {
        $url = self::DOMAIN . '/' . $bucket . '/' . $key . '?partNumber=' . $part_number . '&uploadId=' . urlencode($upload_id);
        $request_resource = "/" . $bucket . '/' . $key . '?partNumber=' . $part_number . '&uploadId=' . $upload_id;
        $http_verb = 'PUT';
        $headers = $this->getHeaders(null, $request_resource, $http_verb);

        $temp = tmpfile();
        fwrite($temp, $data);
        rewind($temp);
        $opts = [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $temp
        ];
        $response = ClientSimple::put($url, $data, $headers, $opts, ['time_out' => 3000]);
        fclose($temp);

        if (!$response->hasHeader('etag')) {
            throw new RuntimeException("上传失败！");
        }
        return $response->getHeaderLine('etag');
    }

    /**
     * 分片上传文件完成
     * @param string $upload_id 上传标识
     * @param array  $etags     上传分片标识按顺序组成的数组
     * @param string $key       资源标识
     * @param string $bucket    指定存储桶
     * @return bool
     */
    protected function putObjectMultipartComplete(string $upload_id, array $etags, string $key, string $bucket): bool
    {
        $url = self::DOMAIN . '/' . $bucket . '/' . $key . '?uploadId=' . $upload_id;
        $request_resource = "/" . $bucket . '/' . $key . '?uploadId=' . $upload_id;
        $http_verb = 'POST';
        $headers = $this->getHeaders(null, $request_resource, $http_verb);
        $opts = [
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_SSL_VERIFYHOST => 0
        ];

        $data = '';
        foreach ($etags as $index => $etag) {
            $part_number = $index + 1;
            $data .= "<Part><PartNumber>$part_number</PartNumber><ETag>$etag</ETag></Part>";
        }
        $data = "<CompleteMultipartUpload>$data</CompleteMultipartUpload>";

        $response = ClientSimple::post($url, $data, $headers, $opts, ['time_out' => 3000]);
        $array = XML::decode($response->getBody());
        return isset($array['ETag']);
    }

    /**
     * @param string|null $file                      文件，为null时表示【application/octet-stream】
     * @param string      $request_resource          请求资源标识
     * @param string      $http_verb                 HTTP请求类型
     * @param array       $user_defined_meta_headers 用户定义META头
     * @return array
     */
    protected function getHeaders(?string $file, string $request_resource, string $http_verb, array $user_defined_meta_headers = []): array
    {
        $content_md5 = '';
        if (is_null($file)) {
            $content_type = 'application/octet-stream';
        } else {
            $content_type = mime_content_type($file);
        }
        $date = gmdate('D, d M Y H:i:s T');
        $authorization = $this->getAuthorization($request_resource, $http_verb, $content_md5, $content_type, $date, $user_defined_meta_headers);
        $headers = [
            'Authorization' => $authorization,
            'Content-Type'  => $content_type,
            'Date'          => $date
        ];
        return $headers;
    }
}
