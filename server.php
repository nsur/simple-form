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
	/*
	 * Ajax request handler
	 */
	static public function init() {
		// Init values of some class variables
		self::$response = self::$responseDefs;
		$apiKeyArr = explode('-', self::$apiKey);
		// Check is API key valid or not
		if(empty($apiKeyArr[1])) {
			// Set response state - error
			self::setErrorData('message', 'API key is invalid');
			// Return ajax response
			self::response();
		}
		// Update API endpoint url depending on API key
		self::$apiEndpoint = str_replace('<dc>', $apiKeyArr[1], self::$apiEndpoint);
		// Fill in form data from $_POST array
		self::$formData = !empty($_POST['form_data']) ? $_POST['form_data'] : array();
		if(empty(self::$formData)) {
			self::setErrorData('message', 'Form data is empty');
			self::response();
		}
		// Parse and validate form data
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
		if(!self::$response['error']) {
			// If no validation errors -try to make requests
			$message = !empty($params['message']) ? $params['message'] : '';
			$mailBody = 'First Name: '. $params['first_name']. '
Last Name: '. $params['last_name']. '
E-mail: '. $params['email']. '
Message: '. $message;
			// Send e-mail and handle errors
			$mailSendRes = mail(self::$to, self::$subject, $mailBody);
			if(!$mailSendRes) {
				self::setErrorData('message', error_get_last());
			}
			// Check is current user already exists
			self::checkEmailInMailChimpList($params);
			if(!self::$response['error'] && self::$response['data']['email_address'] === $params['email']) {
				// If yes - set appropriate message and response state
				self::setErrorData('message', 'This user has already subscribed');
			} else if(self::$response['data']['status'] == 404) {
				// If not - reset response state and try to add new subscriber
				self::resetErrorData();
				self::addEmailToMailChimpList($params);
			}
		}
		// Return ajax response
		self::response();
	}
	/*
	 * Check was current email already added to subscription list or not
	 * $params: (array) array with form current data
	 * return (bool) request status
	 */
	static private function checkEmailInMailChimpList($params) {
		$method = 'GET';
		$url = self::$apiEndpoint. '/lists/'. self::$listId. '/members/'. md5($params['email']);
		return self::makeRequest($method, $url);
	}
	/*
	 * Add new email to subscription list
	 * $params: (array) array with form current data
	 * return (bool) request status
	 */
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
	/*
	 * Make MailChimp API request
	 * $method: (string) method of HTTP request
	 * $url: (string) url to MailChimp API endpoint
	 * $body: (array) array of request data to send
	 * return (bool) request status
	 */
	static private function makeRequest($method, $url, $body = array()) {
		if(!function_exists('curl_init') || !function_exists('curl_setopt')) {
			// Check are curl functions exist or not
			self::setErrorData('message', 'Service need a cURL support');
			return false;
		}
		// Set request status
		$result = true;
		// Init curl
		$ch = curl_init();
		// Set curl options
		curl_setopt($ch, CURLOPT_URL, $url);				// request url
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(			// http headers
			'Accept: application/vnd.api+json',
			'Content-Type: application/vnd.api+json',
			'Authorization: apikey ' . self::$apiKey
		));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);		// return an request answer as a result of curl_exec() if it is success
		curl_setopt($ch, CURLOPT_VERBOSE, true);			// put additional data to STDERR stream
		curl_setopt($ch, CURLOPT_HEADER, true);				// include headers to output
		curl_setopt($ch, CURLOPT_TIMEOUT, self::$timeout);	// timeout for curl_exec() execution
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);	// set HTTP version to use
		curl_setopt($ch, CURLOPT_ENCODING, '');							// set any encoding types for request headers
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);					// track of handle's request string
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);				// apply using of custom request methods
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));		// body of HTTP request
		// Send curl request
		$responseContent = curl_exec($ch);

		if($responseContent === false) {
			// Handle curl error
			$result = false;
			$errorMsg = 'cURL error: '. curl_error($ch);
			self::setErrorData('message', $errorMsg);
		} else {
			// Get request headers and body
			$responseHeaders = curl_getinfo($ch);
			$responseBody = substr($responseContent, $responseHeaders['header_size']);
			// Decode body data
			self::$response['data'] = json_decode($responseBody, true);
			if($responseHeaders['http_code'] != 200) {
				// Handle request error
				$result = false;
				self::setErrorData('message', 'Request error: '. self::$response['data']['title']. '. '. self::$response['data']['detail']);
			}
		}
		// Close curl session
		curl_close($ch);
		// Return result of request sending
		return $result;
	}
	/*
	 * Set error state to request response
	 * $type: (string) type of error
	 * $value: (string) value of error message or name of field which contains error
	 */
	static private function setErrorData($type, $value) {
		self::$response['success'] = false;
		self::$response['error'] = true;
		if($type === 'message') {
			self::$response['errors'][$type] = $value;
		} else {
			array_push(self::$response['errors'][$type], $value);
		}
	}
	/*
	 * Clear request response
	 */
	static private function resetErrorData() {
		self::$response = self::$responseDefs;
	}
	/*
	 * Return ajax response
	 */
	static private function response() {
		exit(json_encode(self::$response));
	}

}
// Call Ajax request handler
MailSender::init();