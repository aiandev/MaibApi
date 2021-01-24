<?php

namespace AianDev\MaibApi;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\GuzzleClient;
use GuzzleHttp\Exception\BadResponseException;

class MaibClient extends GuzzleClient
{

	#region Constants
	const MOD_ID             = 'moldovaagroindbank';
	const MOD_TITLE          = 'Moldova Agroindbank';
	const MOD_PREFIX         = 'maib_';

	const TRANSACTION_TYPE_CHARGE = 'charge';
	const TRANSACTION_TYPE_AUTHORIZATION = 'authorization';

	const LOGO_TYPE_BANK       = 'bank';
	const LOGO_TYPE_SYSTEMS    = 'systems';

	const MOD_TRANSACTION_TYPE = self::MOD_PREFIX . 'transaction_type';
	const MOD_TRANSACTION_ID   = self::MOD_PREFIX . 'transaction_id';
	const MOD_CLOSEDAY_ACTION  = self::MOD_PREFIX . 'close_day';

	const SUPPORTED_CURRENCIES = [
		'EUR' => 978,
		'USD' => 840,
		'MDL' => 498
	];

	const MAIB_TRANS_ID        		= 'trans_id';
	const MAIB_TRANSACTION_ID  		= 'TRANSACTION_ID';

	const MAIB_RESULT               = 'RESULT';
	const MAIB_RESULT_OK            = 'OK'; //successfully completed transaction
	const MAIB_RESULT_FAILED        = 'FAILED'; //transaction has failed
	const MAIB_RESULT_CREATED       = 'CREATED'; //transaction just registered in the system
	const MAIB_RESULT_PENDING       = 'PENDING'; //transaction is not accomplished yet
	const MAIB_RESULT_DECLINED      = 'DECLINED'; //transaction declined by ECOMM, because ECI is in blocked ECI list (ECOMM server side configuration)
	const MAIB_RESULT_REVERSED      = 'REVERSED'; //transaction is reversed
	const MAIB_RESULT_AUTOREVERSED  = 'AUTOREVERSED'; //transaction is reversed by autoreversal
	const MAIB_RESULT_TIMEOUT       = 'TIMEOUT'; //transaction was timed out

	const MAIB_RESULT_CODE          = 'RESULT_CODE';
	const MAIB_RESULT_3DSECURE      = '3DSECURE';
	const MAIB_RESULT_RRN           = 'RRN';
	const MAIB_RESULT_APPROVAL_CODE = 'APPROVAL_CODE';
	const MAIB_RESULT_CARD_NUMBER   = 'CARD_NUMBER';
	#endregion
	
	/**
	 * @param ClientInterface      $client
	 * @param DescriptionInterface $description
	 * @param array                $config
	 */
	public function __construct(ClientInterface $client = null, DescriptionInterface $description = null, array $config = [])
	{
		$client = $client instanceof ClientInterface ? $client : new Client();
		$description = $description instanceof DescriptionInterface ? $description : new MaibDescription();
		parent::__construct($client, $description, null, null, null, $config);
	}

    /**
     * @param string $name
     * @param array $arguments
     *
     * @return array
     * @throws BadResponseException
     * @throws \Exception
     */
    public function __call($name, array $arguments)
    {
        try {
            $response = parent::__call($name, $arguments);

            $array1 = explode(PHP_EOL, trim((string)$response->offsetGet('additionalProperties')));
            $result = array();
            foreach($array1 as $value) {
              $array2 = explode(':', $value);
              $result[$array2[0]] = isset($array2[1])? trim( $array2[1] ) : '';
            }
            return $result;
        }
        catch (\Exception $ex) {
            throw $ex;
        }
    }

	/**
	 * Registering transactions
	 * @param  float $amount
	 * @param  string $currency
	 * @param  string $clientIpAddr
	 * @param  string $description
	 * @param  string $language
	 * start SMS transaction. This is simplest form that charges amount to customer instantly.
	 * @return array  TRANSACTION_ID
	 * TRANSACTION_ID - transaction identifier (28 characters in base64 encoding)
	 * error          - in case of an error
	 */
	public function registerSmsTransaction(int $amount, string $currency, string $clientIpAddr, string $description = '', string $language = 'ro')
	{
		$args = [
			'command'  => 'v',
			'amount' => (string)($amount * 100),
			'msg_type' => 'SMS',
			'currency' => (string)self::SUPPORTED_CURRENCIES[$currency],
			'client_ip_addr' => $clientIpAddr,
			'description' => $description,
			'language' => $language
		];

		return parent::registerSmsTransaction($args);
	}

	/**
	 * Registering DMS authorization
	 * 	 * @param  float $amount
	 * 	 * @param  string $currency
	 * 	 * @param  string $clientIpAddr
	 * 	 * @param  string $description
	 * 	 * @param  string $language
	 * DMS is different from SMS, dms_start_authorization blocks amount, and than we use dms_make_transaction to charge customer.
	 * @return array  TRANSACTION_ID
	 * TRANSACTION_ID - transaction identifier (28 characters in base64 encoding)
	 * error          - in case of an error
	 */
	public function registerDmsAuthorization(int $amount, string $currency, $clientIpAddr, $description = '', $language = 'ro')
	{
		$args = [
			'command'  => 'a',
			'amount' => (string)($amount * 100),
			'currency' => (string)$currency,
			'msg_type' => 'DMS',
			'client_ip_addr' => $clientIpAddr,
			'description' => $description,
			'language' => $language
		];

		return parent::registerDmsAuthorization($args);
	}

	/**
	 * Executing a DMS transaction
	 * @param  string $authId
	 * @param  float $amount
	 * @param  string $currency
	 * @param  string $clientIpAddr
	 * @param  string $description
	 * @param  string $language
	 * @return array  RESULT, RESULT_CODE, BRN, APPROVAL_CODE, CARD_NUMBER, error
	 * RESULT         - transaction results: OK - successful transaction, FAILED - failed transaction
	 * RESULT_CODE    - transaction result code returned from Card Suite Processing RTPS (3 digits)
	 * BRN            - retrieval reference number returned from Card Suite Processing RTPS (12 characters)
	 * APPROVAL_CODE  - approval code returned from Card Suite Processing RTPS (max 6 characters)
	 * CARD_NUMBER    - masked card number
	 * error          - in case of an error
	 */
	public function makeDMSTrans($authId, $amount, $currency, $clientIpAddr, $description = '', $language = 'ru'){

		$args = [
			'command'  => 't',
			'trans_id' => $authId,
			'amount' => (string)($amount * 100),
			'currency' => (string)self::SUPPORTED_CURRENCIES[$currency],
			'client_ip_addr' => $clientIpAddr,
			'msg_type' => 'DMS',
			'description' => $description,
			'language' => $language
		];

		return parent::makeDMSTrans($args);
	}

	/**
	 * Transaction result
	 * @param  string $transId
	 * @param  string $clientIpAddr
	 * @return array  RESULT, RESULT_PS, RESULT_CODE, 3DSECURE, RRN, APPROVAL_CODE, CARD_NUMBER, AAV, RECC_PMNT_ID, RECC_PMNT_EXPIRY, MRCH_TRANSACTION_ID
	 * RESULT        	   - OK              - successfully completed transaction,
	 * 				 	     FAILED          - transaction has failed,
	 * 				 	     CREATED         - transaction just registered in the system,
	 * 				 	     PENDING         - transaction is not accomplished yet,
	 * 				 	     DECLINED        - transaction declined by ECOMM,
	 * 				 	     REVERSED        - transaction is reversed,
	 * 				 	     AUTOREVERSED    - transaction is reversed by autoreversal,
	 * 				 	     TIMEOUT         - transaction was timed out
	 * RESULT_PS     	   - transaction result, Payment Server interpretation (shown only if configured to return ECOMM2 specific details
	 * 				 	     FINISHED        - successfully completed payment,
	 * 				 	     CANCELLED       - cancelled payment,
	 * 				 	     RETURNED        - returned payment,
	 * 				 	     ACTIVE          - registered and not yet completed payment.
	 * RESULT_CODE   	   - transaction result code returned from Card Suite Processing RTPS (3 digits)
	 * 3DSECURE      	   - AUTHENTICATED   - successful 3D Secure authorization
	 * 				 	     DECLINED        - failed 3D Secure authorization
	 * 				 	     NOTPARTICIPATED - cardholder is not a member of 3D Secure scheme
	 * 				 	     NO_RANGE        - card is not in 3D secure card range defined by issuer
	 * 				 	     ATTEMPTED       - cardholder 3D secure authorization using attempts ACS server
	 * 				 	     UNAVAILABLE     - cardholder 3D secure authorization is unavailable
	 * 				 	     ERROR           - error message received from ACS server
	 * 				 	     SYSERROR        - 3D secure authorization ended with system error
	 * 				 	     UNKNOWNSCHEME   - 3D secure authorization was attempted by wrong card scheme (Dinners club, American Express)
	 * RRN           	   - retrieval reference number returned from Card Suite Processing RTPS
	 * APPROVAL_CODE 	   - approval code returned from Card Suite Processing RTPS (max 6 characters)
	 * CARD_NUMBER   	   - Masked card number
	 * AAV           	   - FAILED the results of the verification of hash value in AAV merchant name (only if failed)
	 * RECC_PMNT_ID            - Reoccurring payment (if available) identification in Payment Server.
	 * RECC_PMNT_EXPIRY        - Reoccurring payment (if available) expiry date in Payment Server in form of YYMM
	 * MRCH_TRANSACTION_ID     - Merchant Transaction Identifier (if available) for Payment - shown if it was sent as additional parameter  on Payment registration.
	 * The RESULT_CODE and 3DSECURE fields are informative only and can be not shown. The fields RRN and APPROVAL_CODE appear for successful transactions only, for informative purposes, and they facilitate tracking the transactions in Card Suite Processing RTPS system.
	 * error                   - In case of an error
	 * warning                 - In case of warning (reserved for future use).
	 */
	public function getTransactionResult(string $transId, $clientIpAddr)
	{
		$args = [
			'command'  => 'c',
			'trans_id' => $transId,
			'client_ip_addr' => $clientIpAddr,
		];

		return parent::getTransactionResult($args);
	}


	/**
	 * Transaction reversal
	 * @param  string $transId
	 * @param  string $amount          reversal amount in fractional units (up to 12 characters). For DMS authorizations only full amount can be reversed, i.e., the reversal and authorization amounts have to match. In other cases partial reversal is also available.
	 * @return array  RESULT, RESULT_CODE
	 * RESULT         - OK              - successful reversal transaction
	 *                  REVERSED        - transaction has already been reversed
	 * 		    FAILED          - failed to reverse transaction (transaction status remains as it was)
	 * RESULT_CODE    - reversal result code returned from Card Suite Processing RTPS (3 digits)
	 * error          - In case of an error
	 * warning        - In case of warning (reserved for future use).
	 */
	public function revertTransaction($transId, $amount)
	{
		$args = array(
			'command'  => 'r',
			'trans_id' => $transId,
			'amount' => (string)($amount * 100),
		);

		return parent::revertTransaction($args);
	}

	/**
	 * Transaction refund
	 * @param  string $transId
	 * @param  string $amount          full original transaction amount is always refunded.
	 * @return array  RESULT, RESULT_CODE
	 * RESULT         - OK              - successful refund transaction
	 * 		    FAILED          - failed refund transaction
	 * RESULT_CODE    - result code returned from Card Suite Processing RTPS (3 digits)
	 * refund_transaction_id - refund transaction identifier for obtaining refund payment details
	 * error          - In case of an error
	 * warning        - In case of warning (reserved for future use).
	 *
	 * Transaction status in payment server after refund is not changed.
	 */
	public function refundTransaction($transId, $amount)
	{
		$args = array(
			'command'  => 'k',
			'trans_id' => $transId,
			'amount' => (string)($amount * 100),
		);

		return parent::refundTransaction($args);
	}

	/**
	 * needs to be run once every 24 hours.
	 * this tells bank to process all transactions of that day SMS or DMS that were success
	 * in case of DMS only confirmed and sucessful transactions will be processed
	 * @return array RESULT, RESULT_CODE, FLD_075, FLD_076, FLD_087, FLD_088
	 * RESULT        - OK     - successful end of business day
	 * 		   FAILED - failed end of business day
	 * RESULT_CODE   - end-of-business-day code returned from Card Suite Processing RTPS (3 digits)
	 * FLD_075       - the number of credit reversals (up to 10 digits), shown only if result_code begins with 5
	 * FLD_076       - the number of debit transactions (up to 10 digits), shown only if result_code begins with 5
	 * FLD_087       - total amount of credit reversals (up to 16 digits), shown only if result_code begins with 5
	 * FLD_088       - total amount of debit transactions (up to 16 digits), shown only if result_code begins with 5
	 */
	public function closeDay()
	{
		$args = [
			'command'  => 'b',
		];

		return parent::closeDay($args);
	}
}
