<?php

/**
 * Class Aghayevi_Currencyimport_Model_Currency_Import_Yahoofinance
 */
class Aghayevi_Currencyimport_Model_Currency_Import_Yahoofinance extends Mage_Directory_Model_Currency_Import_Abstract
{

    /**
     * Yahoo Finance Api Endpoint Url
     * @var string
     */
    protected $_url = 'http://adsynth-ofx-quotewidget-prod.herokuapp.com/api/1';

    /**
     * @var array
     */
    protected $_messages = array();

    /**
     * HTTP client
     * @var Varien_Http_Client
     */
    protected $_httpClient;

    /**
     * VisionDirect_Currencyimport_Model_Currency_Import_Euroxref constructor.
     */
    public function __construct()
    {
        $this->_httpClient = new Varien_Http_Client();
    }

    /**
     * Convert method
     *
     * @param string $currencyFrom
     * @param string $currencyTo
     * @param int $retry
     * @return float|null
     */
    protected function _convert($currencyFrom, $currencyTo, $retry = 0)
    {

        try {
            $request['method'] = 'spotRateHistory';
            $request['data'] = ['base' => $currencyFrom, 'term' => $currencyTo, 'period' => 'week'];

            $responseBody = $this->_httpClient
                ->setHeaders(['Content-Type' => 'application/json', 'referer' => 'https://widget-yahoo.ofx.com/'])
                ->setUri($this->_url)
                ->setRawData(Mage::helper('core')->jsonEncode($request))
                ->request(Varien_Http_Client::POST)
                ->getBody();

            $response = Mage::helper('core')->jsonDecode($responseBody,true);

            $result = round($response["data"]["CurrentInterbankRate"],4);

            if (!$result) {
                $this->_messages[] = Mage::helper('directory')->__('Cannot retrieve rate from %s', $this->_url);
                return null;
            }

            return (float) $result * 1.0;
        } catch (Exception $e) {

            if ($retry == 0) {
                $this->_convert($currencyFrom, $currencyTo, 1);
            } else {
                $this->_messages[] = Mage::helper('directory')->__('Cannot retrieve rate from %s.', $this->_url);
            }
        }
    }
}
