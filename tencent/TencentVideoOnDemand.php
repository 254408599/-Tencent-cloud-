<?php

namespace tencent;

class TencentVideoOnDemand
{
    protected $_secretId;
	protected $_secretKey;
	protected $_serverUri;    
	protected $_serverHost;    
	protected $_serverPort;    
	protected $_requestMethod;    
	protected $_defaultRegion;  
	protected $_version;  

	public function __construct() {
		$this->_secretId = "SecretId";
		$this->_secretKey = "SecretKey";
		$this->_serverPort = '80';
		$this->_serverHost = 'vod.api.qcloud.com';
		$this->_serverUri = '/v2/index.php';
		$this->_requestMethod = 'POST';
	}  

    public function SetSecretId($secretId) {
		$this->_secretId = $secretId;
	}
	public function SetSecretKey($secretKey) {
		$this->_secretKey = $secretKey;
	}
	public function SetRegion($region) {
		$this->_defaultRegion = $region;
	}
	public function SetServerPort($serverPort) {
		$this->_serverPort = $serverPort;
	}
	public function SetVersion($version) {
		$this->_version = $version;
	}
	public function SetServerUri($serverUri) {
		$this->_serverUri = $serverUri;
	}
	public function SetServerHost($serverHost) {
		$this->_serverHost = $serverHost;
	}
	public function SetRequestMethod($requestMethod) {
		$this->_requestMethod = $requestMethod;
	}
    /**
     * makeSignPlainText
     * 生成拼接签名源文字符串
     * @param array 	$requestParams  请求参数
     * @param string 	$requestMethod 请求方法
     * @param string 	$requestHost   接口域名
     * @param string 	$requestPath   url路径
     * @return
     */
	public static function makeSignPlainText($requestParams, $requestMethod, $requestHost, $requestPath) {
		$url = $requestHost . $requestPath;
		$paramStr = "";
		ksort($requestParams);
		$i = 0;
		foreach ($requestParams as $key => $value) {
			if ($key == 'Signature')
				continue;
			// 排除上传文件的参数
			if ($requestMethod == 'POST' && substr($value, 0, 1) == '@')
				continue;
			// 把 参数中的 _ 替换成 .
			if (strpos($key, '_'))
				$key = str_replace('_', '.', $key);
			if ($i == 0)
				$paramStr .= '?';
			else
				$paramStr .= '&';
			$paramStr .= $key . '=' . $value;
			++$i;
		}
		$plainText = $requestMethod . $url . $paramStr;
		return $plainText;
	}
	
	/**
	 * makeRequest
	 * 生成请求结构
	 * @param string	$name 		协议命令字
	 * @param array 	$arguments 	API参数数组
	 * @param array 	&$request 	待返回的请求结构
	 * @param string 	$https 	http是否使用HTTPS
	 * @return 无返回
	 */
	public function makeRequest($name, $arguments, &$request, $https=true) {
		$action = ucfirst($name);
		$params = $arguments;
		$params['Action'] = $action;
		$params['SecretId'] = $this->_secretId;
		$params['Nonce'] = mt_rand(0, 1000000);
		$params['Region'] = 'gz';
		$params['Timestamp'] = time();
		ksort($params);
		$plainText = self::makeSignPlainText($params, $this->_requestMethod, $this->_serverHost, $this->_serverUri);
		$params['Signature'] = base64_encode(hash_hmac('sha1', $plainText, $this->_secretKey, true));

		$request['uri'] = $this->_serverUri;
		$request['host'] = $this->_serverHost;
		$request['query'] = http_build_query($params);
		$request['query'] = str_replace('+','%20',$request['query']);
		$url = $request['host'] . $request['uri'];
		if($this->_serverPort != '' && $this->_serverPort != 80)
			$url = $request['host'] . ":" . $this->_serverPort . $request['uri'];
		$url = $url.'?'.$request['query'];
		if($https)
			$url = 'https://'.$url;
		else
			$url = 'http://'.$url;
		$request['url'] = $url;//
		// $request['contentLen'] = !empty($arguments['contentLen']) ? $arguments['contentLen'] : 0;//云点播文件上传SDK
	}
	
	/**
 	* sendPostRequest
 	* @param array  $request    http请求参数
 	* @param string $data       发送的数据
 	* @return
 	*/
	public static function sendPostRequest($request, $data) {  
		$url = $request['url'];
		//云点播文件上传SDK
		// $header = array(
		// 	"POST {$request['uri']}?{$request['query']} HTTP/1/1",
		// 	"HOST:{$request['host']}",
		// 	"Content-Length:".$request['contentLen'],
		// 	"Content-type:application/octet-stream",
		// 	"Accept:*/*",
		// 	"User-Agent:Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/537.36",
				
		// );
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
		// curl_setopt($ch, CURLOPT_HTTPHEADER, $header);//云点播文件上传SDK
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));//避免data数据过长问题
			
		if (false !== strpos($url, "https")) {
			// 证书
			// curl_setopt($ch,CURLOPT_CAINFO,"ca.crt");
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		}
		
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		$response = curl_exec($ch);
		curl_close($ch);
		$result = json_decode($response, true);
		if (!$result) {
			echo "[sendPostRequest] 请求发送失败，请检查URL:\n";
			echo $url;
			return $response;
		}
		return $result;
	}

	public function DescribeVodInfo($fileId = [])
	{
        foreach ($fileId as $key => $value) {
        	$arguments['fileIds.'.($key+1)] = $value;
        }
		$this->makeRequest('DescribeVodInfo', $arguments, $request);
		return self::sendPostRequest($request, $request['query']);
	}

	public function DescribeVodPlayUrls($fileId)
	{
        $arguments = ['fileId' => $fileId];
		$this->makeRequest('DescribeVodPlayUrls', $arguments, $request);
		return self::sendPostRequest($request, $request['query']);
	}

	// 调用报服务器内部错误
	public function GetVideoInfo($fileId)
	{
        $arguments = ['fileId' => $fileId];
		$this->makeRequest('GetVideoInfo', $arguments, $request);
		print_r($request);
		return self::sendPostRequest($request, $request['query']);
	}
}
