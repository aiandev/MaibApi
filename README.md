# MaibAPI
Maib online payments php SDK
<https://docs.google.com/document/d/1ZEkmYhlfHQs9VRHIENbHcVwLMvCLCH5vb-hYHvaVP4s/edit>
## Installing

```bash
composer require aiandev/maib-api
```

## Convert  pfx to pem
 - password: Za86DuC$
## Conversion to separate PEM files
```bash
#To convert a PFX file to a PEM file that contains both the certificate and private key, the following command needs to be used:
openssl pkcs12 -in filename.pfx -out cert.pem -nodes

# We can extract the private key form a PFX to a PEM file with this command:
openssl pkcs12 -in filename.pfx -nocerts -out key.pem

#Exporting the certificate only:
openssl pkcs12 -in filename.pfx -clcerts -nokeys -out cert.pem

```

URL ECOMM test server:

Merchant: https://ecomm.maib.md:4499/ecomm2/MerchantHandler
Client:   https://ecomm.maib.md:7443/ecomm2/ClientHandler

URL ECOMM production server:

Merchant: https://ecomm.maib.md:4455/ecomm2/MerchantHandler
Client:   https://ecomm.maib.md/ecomm2/ClientHandler


## Usage

```php
namespace MyProject;
require_once(__DIR__ . '/vendor/autoload.php');

use AianDev\MaibApi\MaibClient;
use AianDev\MaibApi\MaibDescription;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

//set options
$options = [
	'base_uri' => 'https://ecomm.maib.md:4455',
	'debug'  => false,
	'verify' => true,
	'cert'    => [__DIR__.'/cert/pcert.pem', 'Pem_pass'],
	'ssl_key' => __DIR__.'/cert/key.pem',
	'config'  => [
		'curl'  =>  [
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_SSL_VERIFYPEER => true,
		]
	]
];

// create a log for client class, if you want (monolog/monolog required)
if ($log_is_required) {
	$log = new Logger('maib_guzzle_request');
	$log->pushHandler(new StreamHandler(__DIR__.'/logs/maib_guzzle_request.log', Logger::DEBUG));
	$stack = HandlerStack::create();
	$stack->push(
		Middleware::log($log, new MessageFormatter(MessageFormatter::DEBUG))
	);
	$options['handler'] = $stack;
}

// init Client
$guzzleClient = new Client($options);
$client = new MaibClient($guzzleClient);

// examples

//register sms transaction
$smsTransaction = $client->registerSmsTransaction('1', 'MDL', '127.0.0.1', '', 'ro');
check if response with transcation_id
var_dump($smsTransaction);

//register dms authorization
var_dump($client->registerDmsAuthorization('1', 'MDL', '127.0.0.1', '', 'ro'));

//execute dms transaction
var_dump($client->makeDMSTrans('1', '1', 'MDL', '127.0.0.1', '', 'ro'));

//get transaction result
var_dump($client->getTransactionResult('1', '127.0.0.1'));

//revert transaction
var_dump($client->revertTransaction('1', '1'));

//transaction refund
var_dump($client->refundTransaction('1', '1'));

//close business day
var_dump($client->closeDay());

```
The flow of transaction
1. Create Transaction id after client confirm his cart 
2. Send the Transaction id to frontend and fill the form of confirmation (if you use Ajax you should hide it and trigger submit immeditaly)
3. listen to callback 
4. check the status of Transaction 

Neccesary steps 
1. Create job run every 24 hour to close the day # requirment from MIAB
2. Check the Trans_id after 20 min / 40 min if you didn't receive call back 
```html
<form action="https://ecomm.maib.md:7443/ecomm2/ClientHandler" method="post">
<input type="text" name="command" value="T" /><br>
<input type="text" name="amount" value="100" /><br>
<input type="text" name="client_ip_addr" value="127.0.0.1" /><br>
<input type="text" name="currency" value="498" /><br>
<input type="text" name="trans_id" value="$smsTransaction['TRANSCATION_ID']" /><br>
<input type="submit"  value="Submit" name "Submit" /><br>
</form>
```