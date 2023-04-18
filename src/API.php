<?php

namespace Kravock\Netbank;

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\InputFormField;

class API
{
    private $username = '';
    private $password = '';
    private $client;
		private $guzzleClient;
    private $timezone = 'Australia/Sydney';

    const BASE_URL = 'https://www.my.commbank.com.au/netbank/Logon/Logon.aspx';
		const ACC_URL = 'https://www.commbank.com.au/retail/netbank/accounts/api/accounts';
		const ACC2_URL = 'https://www.commbank.com.au/retail/netbank/api/home/v1/accounts';

    /**
     * Create a new API Instance
     */
    public function __construct()
    {
			$this->client = new Client();
			$this->guzzleClient = new GuzzleClient(array(
				'allow_redirects' => true,
				'timeout' => 60,
				'cookies' => true,
				'headers' => 
					[
					'User-Agent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.1 Safari/537.36"
					],
			));

			$this->client->setClient($this->guzzleClient);
    }

    public function login($username, $password)
    {	
			// Get the logon page
			$crawler = $this->client->request('GET', sprintf("%s", self::BASE_URL));
			// Grab the Log on form
			$form = $crawler->selectButton('Log on')->form();
			// Grab input fields within the Log on form
			$fields = $crawler->filter('input');
			// We need to set fields to enabled otherwise we can't login
			foreach ($fields as $field) {
					$field->removeAttribute('disabled');
			}
			// Update necessary fields			
			$form['txtMyClientNumber$field'] = $username;
			$form['txtMyPassword$field'] = $password;
			$form['chkRemember$field'] = 'on';
			$form['JS'] = 'E';
			// Submit the form with the updated fields
			$crawler = $this->client->submit($form);
			// Check for the occassional click to continue redirect - simply submit to get past it.
			if (stripos($crawler->text(), 'Click to continue') !== false) {
				// Get the first form on the page, and submit it!
				$click_form = $crawler->filterXPath('//form')->form();
				$crawler = $this->client->submit($click_form);
			}			
			
			// Commbank introduced an API, found here: https://github.com/twang2218/node-cba-netbank/issues/17#issuecomment-643753910
			// After login, get the account list, navigate to the account list api
			if ($this->api_version == 1) {
				$crawler = $this->client->request('GET', sprintf("%s", self::ACC_URL));
			} else {
				$crawler = $this->client->request('GET', sprintf("%s", self::ACC2_URL));
			}
			
			// Decode to an associative array, and get the 'accounts' element
			$accounts = json_decode($this->client->getResponse()->getContent(), true)['accounts'];
			$accountList = [];
			foreach ($accounts as $account) {
				// Get the content, with different keys dependent on the cba api version.
				$name = ($this->api_version==1)?$account['accountName']:$account['displayName'];
				$bsb = substr($account['number'], 0, 6);
				$accountNumber = substr($account['number'], 6);
				$balance = ($this->api_version==1)?$account['accountBalance'][0]['amount']:$account['balance'][0]['amount'];
				$available = $account['availableFunds'][0]['amount'] ?? 0;				
				$link = ($this->api_version==1)?$account['link']:$account['link']['url'];				
				$productCode = ($this->api_version==1)?$account['productCode']:'';
				
				if ($productCode != 'DDA' && $this->api_version == 1) {
					// First 6 digits should be BSB, however, it could be a card or other account.
					// Savings Account == DDA. If not a savings account, skip.
					// Might transform to an optional array for sorting product types.
					continue;
				}
				// Accounts are identified with the BSB and Account Name joined (Identifier)
				$accountList[$bsb.$accountNumber] = [
						'nickname' => $name,
						'url' => $link,
						'bsb' => $bsb,
						'accountNum' => $accountNumber,
						'balance' => $balance,
						'available' => $available
				];
			}
			
			if (!$accountList) {
					throw new \Exception('Unable to retrieve account list.');
			}
			return $accountList;
    }

    public function getTransactions($account, $from, $to)
    {
				// Get the json response from transactions api page
				// As is, should return the 40 latest transactions, not including pending transactions.				
				// Use an updated link, as per https://github.com/jcwillox/commbank-api/blob/9ed0f4d1107c28bddb45cc07d6d3be97cbf82178/commbank/client.py#L113-L115
				$link = "https://www.commbank.com.au".str_replace('/retail/netbank/accounts/', '/retail/netbank/accounts/api/transactions', $account['url']);
				// Get the transactions
				$crawler = $this->client->request('GET', $link);
				// Decode to an associative array, and get the 'transactions' element
				$transactions = json_decode($this->client->getResponse()->getContent(), true)['transactions'];
				return $transactions;
				/* Not needed yet, but soon.
				$form = $crawler->filter('#aspnetForm');

        // Check that we we a form on the transaction page
        if (!$form->count()) {
            return [];
        }

        $form = $crawler->form();

        $field = $this->createField('input', '__EVENTTARGET', 'ctl00$BodyPlaceHolder$lbSearch');
        $form->set($field);

        $field = $this->createField('input', '__EVENTARGUMENT', '');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$ctl00', 'ctl00$BodyPlaceHolder$updatePanelSearch|ctl00$BodyPlaceHolder$lbSearch');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$searchTypeField', '1');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$radioSwitchDateRange$field$', 'ChooseDates');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$dateRangeField', 'ChooseDates');
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$fromCalTxtBox$field', $from);
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$toCalTxtBox$field', $to);
        $form->set($field);

        $field = $this->createField('input', 'ctl00$BodyPlaceHolder$radioSwitchSearchType$field$', 'AllTransactions');
        $form->set($field);

        $crawler = $this->client->submit($form);
        return $this->filterTransactions($crawler);*/
    }
		
		private function createField($type, $name, $value)
    {
        $domdocument = new \DOMDocument;
        $ff = $domdocument->createElement($type);
        $ff->setAttribute('name', $name);
        $ff->setAttribute('value', $value);
        $formfield = new InputFormField($ff);

        return $formfield;
    }
				
		// Doesn't seem necessary anymore
		/*

    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
    }

    private function processNumbersOnly($value)
    {
        return preg_replace('$[^0-9]$', '', $value);
    }

    private function processCurrency($amount)
    {
        $value = preg_replace('$[^0-9.]$', '', $amount);

        if (strstr($amount, 'DR')) {
            $value = -$value;
        }

        return $value;
    }

    

    public function filterTransactions($crawler)
    {
        $pattern = '
        /
        \{              # { character
            (?:         # non-capturing group
                [^{}]   # anything that is not a { or }
                |       # OR
                (?R)    # recurses the entire pattern
            )*          # previous group zero or more times
        \}              # } character
        /x
        ';
        $html = $crawler->html();

        preg_match_all('/({"Transactions":(?:.+)})\);/', $html, $matches);

        foreach ($matches[1] as $_temp) {
            if (strstr($_temp, 'Transactions')) {
                $transactions = json_decode($_temp);
                break;
            }
        }

        $transactionList = [];
        if (!empty($transactions->Transactions)) {
            foreach ($transactions->Transactions as $transaction) {
                $date = \DateTime::createFromFormat('YmdHisu', substr($transaction->Date->Sort[1], 0, 20), new \DateTimeZone('UTC'));
                $date->setTimeZone(new \DateTimeZone($this->timezone));
                $transactionList[] = [
                    'timestamp' => $transaction->Date->Sort[1],
                    'date' => $date->format('Y-m-d H:i:s.u'),
                    'description' => $transaction->Description->Text,
                    'amount' => $this->processCurrency($transaction->Amount->Text),
                    'balance' => $this->processCurrency($transaction->Balance->Text),
                    'trancode' => $transaction->TranCode->Text,
                    'receiptnumber' => $transaction->ReceiptNumber->Text,
                ];
            }

        }

        return $transactionList;
    }
		
    private function getAccountPage($account)
    {
				// Use an updated link, as per https://github.com/jcwillox/commbank-api/blob/9ed0f4d1107c28bddb45cc07d6d3be97cbf82178/commbank/client.py#L113-L115
				$link = "https://www.commbank.com.au".str_replace('/retail/netbank/accounts/', '/retail/netbank/accounts/api/transactions', $account['url']);
				return $this->client->request('GET', $link);
    }*/
}