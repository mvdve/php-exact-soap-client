<?php

namespace ExactSoapClient;

use SoapFault;
use SoapHeader;
use SoapParam;
use SoapVar;

class ExactSoapClient extends \SoapClient {

    const ACTION_CREATE = "Create";
    const ACTION_RETRIEVE = "Retrieve";
    const ACTION_UPDATE = "Update";
    const ACTION_SAVE = "Save";
    const ACTION_DELETE = "Delete";

    private ExactSoapConfig $config;
    private string $destination;

    /**
     * Class Constructor.
     *
     * @param ExactSoapConfig $config
     * @param array $options
     *
     * @throws SoapFault
     */
    public function __construct(ExactSoapConfig $config, array $options = []) {
        $defaultOptions = [
            'soap_version' => SOAP_1_1,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'exceptions' => 1,
            'trace' => 1,
        ];

        parent::__construct($config->getWsdlUrl(), array_merge($defaultOptions, $options));
        // Override the host from the WSDL file. It returns the dns location instead of an IP address.
        $this->__setLocation($config->host);
        $this->__setSoapHeaders([
            new SoapHeader('urn:exact.services.entitymodel.backoffice:v1', 'ServerName', $config->databaseHost),
            new SoapHeader('urn:exact.services.entitymodel.backoffice:v1', 'DatabaseName', $config->databaseName),
        ]);

        $this->destination = $config->getServiceUrl();
        $this->config = $config;
    }

    /**
     * Override the doRequest method to enable NTLM support.
     *
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int $version
     * @param int $one_way
     *
     * @return bool|string|null
     *
     * @throws SoapFault
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0) {
        $handle = curl_init($this->destination);
        curl_setopt($handle, CURLOPT_VERBOSE, FALSE);
        curl_setopt($handle, CURLOPT_HEADER, FALSE);
        curl_setopt($handle, CURLOPT_HTTPHEADER, array('Content-type: text/xml', 'SOAPAction: ' . $action));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($handle, CURLOPT_POSTFIELDS, $request);
        curl_setopt($handle, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($handle, CURLOPT_USERPWD, $this->config->uid . ':' . $this->config->password);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($error) {
            throw new SoapFault("Server", "CURL returned HTTP code: $code / error: $error / response body: $response");
        } elseif ($code != 200) {
            throw new SoapFault("Server", "CURL returned HTTP code: $code / response body: $response");
        } elseif (empty($response)) {
            throw new SoapFault("Server", 'CURL returned empty response.');
        }

        return $response;
    }

    /**
     * Builds a SOAP variable, containing one other variable called 'EntityData', from an array of properties.
     * This variable is used in several SOAP calls.
     *
     * @param string $action
     *   Outer variable name, which should be equal to the action name e.g.
     *   "Retrieve", "Create".
     * @param string $entity
     * @param array $data
     *
     * @return SoapVar
     */
    public function buildEntityData(string $action, string $entity, array $data): SoapVar {
        $ns_services = "http://www.exactsoftware.com/services/entities/";
        $ns_schema = "http://www.exactsoftware.com/schemas/entities/";

        $properties = [];
        foreach ($data as $name => $value) {
            $property_data = [];
            $property_data[] = new SoapVar($name, XSD_STRING, null, null, 'Name', $ns_schema);

            // We must specify a type for Value, otherwise the Exact SOAP parser returns the following error:
            // Element Value from namespace http://www.exactsoftware.com/schemas/entities/ cannot have child contents
            // to be deserialized as an object. Please use XmlNode[] to deserialize this pattern of XML.
            switch ($value) {
                case is_int($value):
                    $property_data[] = new SoapVar($value, XSD_STRING, 'int', 'http://www.w3.org/2001/XMLSchema', 'Value', $ns_schema);
                    break;
                case is_float($value):
                    $property_data[] = new SoapVar($value, XSD_STRING, 'float', 'http://www.w3.org/2001/XMLSchema', 'Value', $ns_schema);
                    break;
                default:
                    $property_data[] = new SoapVar($value, XSD_STRING, 'string', 'http://www.w3.org/2001/XMLSchema', 'Value', $ns_schema);
            };

            $properties[] = new SoapVar($property_data, SOAP_ENC_OBJECT, null, null, 'PropertyData', $ns_schema);
        }

        $elements = [];
        $elements[] = new SoapVar($entity, XSD_STRING, null, null, 'EntityName', $ns_schema);
        $elements[] = new SoapVar($properties, SOAP_ENC_OBJECT, null, null, 'Properties', $ns_schema);
        $entity =  new SoapVar($elements, SOAP_ENC_OBJECT, 'ns1:EntityData', null, 'data', $ns_services);

        return new SoapVar([$entity], SOAP_ENC_OBJECT, null, null, $action, $ns_services);
    }

    /**
     * Executes SOAP call. And get the wanted property from the result object.
     *
     * @param string $action
     *   See list of action so class constants.
     * @param string $entity
     * @param array $data
     * @param string $property_name
     *
     * @return string
     *
     * @throws ExactSoapException
     */
    public function callSoapGetProperty(string $action, string $entity, array $data, string $property_name) : string {
        try {
            $params = $this->buildEntityData($action, $entity, $data);
            $result = $this->{$action}(new SoapParam($params, "Create"));

            if ($result === null) {
                $error = $this->__getLastResponse();
                throw new ExactSoapException('The Exact entity services returned null, result: ' . htmlspecialchars(print_r($error, true)));
            }
        }
        catch (SoapFault $ex) {
            $error = $this->__getLastResponse();
            throw new ExactSoapException('The Exact entity services returned an error: ' . $ex . PHP_EOL . ' Message: ' . htmlspecialchars(print_r($error, true)));
        }

        if (empty($result->CreateResult->Properties->PropertyData) || !is_array($result->CreateResult->Properties->PropertyData)) {
            throw new ExactSoapException('No PropertyData found in the soap result, result: ' . htmlspecialchars(print_r($result, true)));
        }

        // Each property has 3 keys: Name / NoRights / Value. NoRights is always empty.
        foreach ($result->CreateResult->Properties->PropertyData as $property) {
            if (isset($property->Name) && $property->Name === $property_name) {
                if (!empty($property->Value)) {
                    return $property->Value;
                }
            }
        }

        throw new ExactSoapException("The Exact entity services result did not contain the property $property_name, result: " . htmlspecialchars(print_r($result, true)));
    }

}