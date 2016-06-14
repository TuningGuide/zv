<?php
/**
 *
 * User: velten
 * Date: 23/01/15 14:20
 */


class CurlGetter {
	protected $previous = null;
	protected $lastCookie = null;
	protected $base = null;

	function CurlGetter($base) {
		$this->base = $base;
		$this->previous = $base;
	}

	public function setPrevious($previous) {
		$this->previous = $this->buildUrl($previous);
	}

	protected function buildUrl($url) {
		// string starts not with http
		return (strpos($url, "http://") === 0) ? $url : $this->base.$url ;
	}

	/**
	 * @param $url
	 * @param null $postData
	 */
	public function getPage( $url, $postData = null ){
		$data = $this->getPageWithCookie($url, $this->previous, $this->lastCookie, $postData);
		if(isset($data['cookies'])) {
			$this->lastCookie = $data['cookies'];
		}
		return $data['content'];
	}

	/**
	 * @param string $url
	 * @param string $previous
	 * @param string $cookiesIn
	 * @param string[] $postData
	 * @return mixed
	 */
	public function getPageWithCookie( $url, $previous = '', $cookiesIn = null, $postData = null ){
		$url = $this->buildUrl($url);

		$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER         => true,     //return headers in addition to content
			CURLOPT_FOLLOWLOCATION => false,     // follow redirects
			CURLOPT_ENCODING       => "",       // handle all encodings
			CURLOPT_AUTOREFERER    => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			CURLINFO_HEADER_OUT    => true,
			CURLOPT_SSL_VERIFYPEER => false,     // Disabled SSL Cert checks
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_USERAGENT		=> "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36",
			CURLOPT_REFERER 		=> $previous
		);

		if($postData) {
			$options[CURLOPT_POST] = true;
			$options[CURLOPT_POSTFIELDS] = http_build_query($postData);
		}

		if(!empty($cookiesIn)) {
			$options[CURLOPT_COOKIE] = $cookiesIn;
		}

		$ch      = curl_init( $url );
		curl_setopt_array( $ch, $options );
		$rough_content = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errmsg  = curl_error( $ch );
		$header  = curl_getinfo( $ch );
		curl_close( $ch );

		$header_content = substr($rough_content, 0, $header['header_size']);
		$body_content = trim(str_replace($header_content, '', $rough_content));
		$pattern = "#Set-Cookie:\\s+(?<cookie>[^=]+=[^;]+)#m";
		preg_match_all($pattern, $header_content, $matches);
		$cookiesOut = implode("; ", $matches['cookie']);

		$header['errno']   = $err;
		$header['errmsg']  = $errmsg;
		$header['headers']  = $header_content;
		$header['content'] = $body_content;
		$header['cookies'] = $cookiesOut;
		return $header;
	}
}


