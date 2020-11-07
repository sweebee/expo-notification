<?php namespace Wiebenieuwenhuis;

/**
*  @author Wiebe Nieuwenhuis
*/
Class ExpoNotification {

	private $url = 'https://exp.host/--/api/v2/push/send';
	private $test = false;
	private $silent = false;

	private $tokens = [];

	private $title;
	private $body;
	private $badge;
	private $data;
	private $sound = 'default';
	private $channel;

	/**
	 * The receivers (Expo tokens)
	 *
	 * @param array|string $tokens
	 *
	 * @return $this
	 */
	public function to($tokens)
	{
		if(is_string($tokens)){
			$tokens = [$tokens];
		}
		
		foreach($tokens as $token){
			if(substr($token, 0, strlen('ExponentPushToken[')) === 'ExponentPushToken['){
				$this->tokens[] = $token;
			}
		}
		
		return $this;
	}

	/**
	 * The notification title
	 *
	 * @param string $title
	 *
	 * @return $this
	 */
	public function title(string $title)
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * The body (message) text in the notification
	 *
	 * @param string $body
	 *
	 * @return $this
	 */
	public function body(string $body)
	{
		$this->body = $body;
		return $this;
	}

	/**
	 * Set the badge count for the app icon (iOS only)
	 *
	 * @param int $count
	 *
	 * @return $this
	 */
	public function badge(int $count)
	{
		$this->badge = $count;
		return $this;
	}

	/**
	 * Send extra data to the app (not visible in the notification)
	 *
	 * @param array $data
	 *
	 * @return $this
	 */
	public function data(array $data)
	{
		$this->data = $data;
		return $this;
	}

	/**
	 * @param $sound
	 *
	 * @return $this
	 */
	public function sound(string $sound)
	{
		$this->sound = $sound;
		return $this;
	}
	
	/**
	 * @param $channel
	 *
	 * @return $this
	 */
	public function channel(string $channel)
	{
		$this->channel = $channel;
		return $this;
	}
	
	/**
	 * If sending with test, users will get a silent notification,
	 * all data such as title, body, data will be stripped.
	 *
	 * @param bool $test
	 *
	 * @return $this
	 */
	public function test(bool $test = null)
	{
		if($test === null) $test = true;
		$this->test = $test;
		if($this->test){
			// If test mode, add an invalid pushtoken
			$this->tokens[] = 'ExponentPushToken[invalid-test-token]';
		}
		return $this;
	}

	/**
	 * A user won't notice a notification if its silent.
	 * Usefull for sending data to the app.
	 *
	 * @param bool $silent
	 *
	 * @return $this
	 */
	public function silent(bool $silent = null)
	{
		if($silent === null) $silent = true;
		$this->silent = $silent;
		return $this;
	}

	/**
	 * Send the notifications
	 *
	 * @return array
	 */
	public function send(bool $test = null)
	{

		if($test !== null){
			$this->test($test);
		}

		// Validate the data
		if($errors = $this->validate()){
			return $errors;
		}

		$data = [];

		// Setup the post data
		foreach($this->tokens as $token){
			$data[] = $this->buildMessage($token);
		}

		// Post the request to Expo
		$response = $this->post($this->url, $data);

		// Return a nice response message
		return $this->buildResponse($response);
	}

	/**
	 * Validate the data
	 *
	 * @return array|false
	 */
	private function validate()
	{
		$errors = [];

		if(count($this->tokens) > 100){
			$errors[] = 'Tokens exceed 100 limit';
		}

		if(isset($this->data[0])){
			$errors[] = 'Data indexes must be string';
		}

		if($this->data && count($this->data)){
			if(strlen(json_encode($this->data)) > 4000){
				$errors[] = 'Data exceeds 4kB limit';
			}
		}

		if(count($errors)){
			return [
				'status' => 'error',
				'message' => $errors
			];
		}

		return false;
	}

	/**
	 * Post the data to Expo
	 *
	 * @param $url
	 * @param array $data
	 *
	 * @return string
	 */
	private function post(string $url, array $data)
	{
		// Encode the data to JSON
		$json = json_encode($data);

		// Setup the CURL POST request
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $json,
			CURLOPT_HTTPHEADER => [
				'Cache-Control: no-cache',
				'Content-Type: application/json',
			],
		]);

		// Execute and return the result
		return curl_exec($curl);
	}

	/**
	 * Build the array for a notification receiver
	 *
	 * @param string $token
	 *
	 * @return array
	 */
	private function buildMessage(string $token)
	{
		$m = [
			'to' => $token,
			'sound' => null
		];

		// If test mode, create a silent notification without content
		if($this->test){
			return $m;
		}

		if($this->badge !== null) {
			$m['badge'] = $this->badge;
		}

		if($this->data){
			$m['data'] = $this->data;
		}

		// If silent, create a notification without title, body and sound
		if($this->silent){
			return $m;
		}

		if($this->title){
			$m['title'] = $this->title;
		}
		if($this->body){
			$m['body'] = $this->body;
		}
		$m['sound'] = $this->sound;
		
		if($this->channel){
			$m['channelId'] = $this->channel;
		}

		return $m;
	}

	/**
	 * Build an nice response to handle the status
	 *
	 * @param string $response
	 *
	 * @return array
	 */
	private function buildResponse(string $result)
	{
		$response = json_decode($result, true);

		if(!$response){
			return [
				'status' => 'error',
				'test' => $this->test,
				'errors' => $result ? [$result] : ['Server did not respond, unknown error'],
				'results' => []
			];
		}

		// If data does not exists, something went wrong with the request
		if(!isset($response['data'])){
			return [
				'status' => 'error',
				'test' => $this->test,
				'errors' => $response['errors'] ?? [$result],
				'results' => []
			];
		}

		// The response went well, check the notifications
		$results = [
			'status' => 'ok',
			'test' => $this->test,
			'results' => []
		];

		// Loop through the response data
		foreach($response['data'] as $index => $status){
			$results['results'][] = [
				'id' => $status['id'] ?? null,
				'token' => $this->tokens[$index],
				'status' => $status['status'] ?? '',
				'message' => $status['message'] ?? 'Message successfully sent'
			];
		}

		return $results;
	}
}
