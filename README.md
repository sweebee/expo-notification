Expo Notifcation
=========================

Send notifications with the Expo API

Example
--------
```php
$notification = new ExpoNotification();

$result = $notification
	->to([
		'ExponentPushToken[GoVJ_qPG41_nHi-4EQS5UU]',
		'ExponentPushToken[P7yjE4MojbyaqpOiwwANgB]'
	])
	->title('test')
	->body('message')
	->data([
		'row' => 'item'
	])
	->badge(1)
	->channel('default')
	->sound('default')
	//->silent()
	//->test()
	->send();

print_r($result);
```
