<?php 
class MailSender {
	static private $listId = '3b0a85eabe';
	static private $apiKey = '969f419219b047c2ecdde1de1dc8479b-us4';
	static private $apiEndpoint = 'https://<dc>.api.mailchimp.com/3.0';
	static private $timeout = 10;
	static private $to = 'hello@hello.com';
	static private $subject = 'Form Data';

	static private $formData = null;
	static private $valideteFields = array('first_name', 'last_name', 'email');
	static private $responseDefs = array(
		'success' => true,
		'data' => array(),
		'error' => false,
		'errors' => array(
			'empty' => array(),
			'short' => array(),
			'email' => array(),
			'message' => array(),
		),
	);
	static private $response = array();

	static public function init() {
		self::$response = self::$responseDefs;
		$apiKeyArr = explode('-', self::$apiKey);
		if(empty($apiKeyArr[1])) {
			self::setErrorData('message', 'API key is invalid');
			self::response();
		}
		self::$apiEndpoint = str_replace('<dc>', $apiKeyArr[1], self::$apiEndpoint);
		self::$formData = $_POST['form_data'];
		if(empty(self::$formData)) {
			self::setErrorData('message', 'Form data is empty');
			self::response();
		}
		parse_str(self::$formData, $params);
		foreach($params as $k => $v) {
			if(in_array($k, self::$valideteFields)) {
				if(!$v) {
					self::setErrorData('empty', $k);
				} else if($k !== 'email' && strlen($v) < 3) {
					self::setErrorData('short', $k);
				} else if($k === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
					self::setErrorData('email', $k);
				}
			}
		}
		if(self::$response['error']) {
			self::$response['success'] = false;
		} else {
			$message = !empty($params['message']) ? $params['message'] : '';
			$mailBody = 'First Name: '. $params['first_name']. '
Last Name: '. $params['last_name']. '
E-mail: '. $params['email']. '
Message: '. $message;
			$mailSendRes = mail(self::$to, self::$subject, $mailBody);
			if(!$mailSendRes) {
				self::setErrorData('message', error_get_last());
			}
			self::checkEmailInMailChimpList($params);
			if(!self::$response['error'] && self::$response['data']['email_address'] === $params['email']) {
				self::setErrorData('message', 'This user has already subscribed');
			} else if(self::$response['data']['status'] == 404) {
				self::resetErrorData();
				self::addEmailToMailChimpList($params);
			}
		}
		self::response();
	}
	static private function checkEmailInMailChimpList($params) {
		$method = 'GET';
		$url = self::$apiEndpoint. '/lists/'. self::$listId. '/members/'. md5($params['email']);
		return self::makeRequest($method, $url);
	}
	static private function addEmailToMailChimpList($params) {
		$method = 'POST';
		$url = self::$apiEndpoint. '/lists/'. self::$listId. '/members/';
		return self::makeRequest($method, $url, array(
			'email_address' => $params['email'],
			'status' => 'subscribed',
			'merge_fields' => array(
				'FNAME' => $params['first_name'],
				'LNAME' => $params['last_name'],
			)
		));
	}
	static private function makeRequest($method, $url, $body = array()) {
		if(!function_exists('curl_init') || !function_exists('curl_setopt')) {
			self::setErrorData('message', 'Service need a cURL support');
			return false;
		}
		$result = true;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/vnd.api+json',
			'Content-Type: application/vnd.api+json',
			'Authorization: apikey ' . self::$apiKey
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

		$responseContent = curl_exec($ch);

		if($responseContent === false) {
			$result = false;
			$errorMsg = 'cURL error: '. curl_error($ch);
			self::setErrorData('message', $errorMsg);
		} else {
			$responseHeaders = curl_getinfo($ch);
			$responseBody = substr($responseContent, $responseHeaders['header_size']);
			self::$response['data'] = json_decode($responseBody, true);
			if($responseHeaders['http_code'] != 200) {
				$result = false;
				self::setErrorData('message', 'Request error: '. self::$response['data']['title']. '. '. self::$response['data']['detail']);
			}
		}
		curl_close($ch);
		return $result;
	}
	static private function setErrorData($type, $value) {
		self::$response['success'] = false;
		self::$response['error'] = true;
		if($type === 'message') {
			self::$response['errors'][$type] = $value;
		} else {
			array_push(self::$response['errors'][$type], $value);
		}
	}
	static private function resetErrorData() {
		self::$response = self::$responseDefs;
	}
	static private function response() {
		exit(json_encode(self::$response));
	}

}
MailSender::init();