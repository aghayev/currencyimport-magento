<?php

/**
 * Class Aghayevi_Currencyimport_Model_Currency_Import_Bankofcanada
 */
class Aghayevi_Currencyimport_Model_Currency_Import_Bankofcanada extends Mage_Directory_Model_Currency_Import_Abstract
{

    /**
     * Bank of Canada Base Currency
     */
    const BANK_BASE_CURRENCY = 'CAD';

    /**
     * Bank of Canada Url
     * @var string
     */
    protected $_url = 'https://www.bankofcanada.ca/valet/observations/group/FX_RATES_DAILY/xml?start_date=%s';

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
     * Current Rates
     * @var Varien_Simplexml_Element
     */
    protected $_currentRates;

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

            if (empty($this->_currentRates)) {

                $searchDate = new Zend_Date(Mage::getModel('core/date')->timestamp());
                $searchDate->subDay('7');

                $this->_url = sprintf($this->_url, $searchDate->toString('YYYY-MM-dd'));

                $responseBody = $this->_httpClient
                    ->setHeaders(['Content-Type: text/xml'])
                    ->setUri($this->_url)
                    ->request(Varien_Http_Client::GET)
                    ->getBody();

                $this->_currentRates = new Varien_Simplexml_Element($responseBody);
            }

            if ($currencyTo == self::BANK_BASE_CURRENCY) {
                $rateTo = 1;
            } else {
                $bankToRate = $this->_currentRates->xpath("//data/observations/o[@d][last()]/v[@s='FX".$currencyTo. self::BANK_BASE_CURRENCY ."']");
                $rateTo = (string) $bankToRate[0]->xmlentities();
            }

            if ($currencyFrom == self::BANK_BASE_CURRENCY) {
                $rateFrom = 1;
            } else {
                $bankFromRate = $this->_currentRates->xpath("//data/observations/o[@d][last()]/v[@s='FX".$currencyFrom. self::BANK_BASE_CURRENCY . "']");
                $rateFrom = (string) $bankFromRate[0]->xmlentities();
            }

            $result = round($rateFrom / $rateTo, 4);

            if (!$result) {
                $this->_messages[] = Mage::helper('directory')->__('Cannot retrieve rate from %s', $this->_url);
                return null;
            }

            return (float)$result * 1.0;
        } catch (Exception $e) {

            if ($retry == 0) {
                $this->_convert($currencyFrom, $currencyTo, 1);
            } else {
                $this->_messages[] = Mage::helper('directory')->__('Cannot retrieve rate from %s.', $this->_url);
            }
        }
    }
}
