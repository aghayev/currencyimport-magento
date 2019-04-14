<?php

/**
 * Class Aghayevi_Currencyimport_Model_Currency_Import_Euroxref
 */
class Aghayevi_Currencyimport_Model_Currency_Import_Euroxref extends Mage_Directory_Model_Currency_Import_Abstract
{

    /**
     * ECB Currency
     */
    const ECB_CURRENCY = 'EUR';

    /**
     * ECB Url
     * @var string
     */
    protected $_url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

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

                // TODO :: match if date is today, if not throw an error
                $today = new Zend_Date();

                $responseBody = $this->_httpClient
                    ->setHeaders(['Content-Type: text/xml'])
                    ->setUri($this->_url)
                    ->request(Varien_Http_Client::GET)
                    ->getBody();

                $this->_currentRates = new Varien_Simplexml_Element($responseBody);

                $namespaces = $this->_currentRates->getDocNamespaces();

                $this->_currentRates->registerXPathNamespace("ecb", $namespaces['']);
            }

            if ($currencyTo == self::ECB_CURRENCY) {
                $rateTo = 1;
            } else {
                $cubeToRate = $this->_currentRates->xpath("//ecb:Cube[@currency='" . $currencyTo . "']/@rate");
                $rateTo = (string)$cubeToRate[0]->xmlentities();
            }

            if ($currencyFrom == self::ECB_CURRENCY) {
                $rateFrom = 1;
            } else {
                $cubeFromRate = $this->_currentRates->xpath("//ecb:Cube[@currency='" . $currencyFrom . "']/@rate");
                $rateFrom = (string)$cubeFromRate[0]->xmlentities();
            }

            $result = round($rateTo / $rateFrom, 4);

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
