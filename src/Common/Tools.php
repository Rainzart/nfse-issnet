<?php


namespace HaDDeR\NfseIssnet\Common;


use HaDDeR\NfseIssnet\Common\Soap\SoapCurl;
use NFePHP\Common\Certificate;
use NFePHP\Common\DOMImproved;

class Tools
{

    protected $wsobj;
    protected $urls = [
        '4313409' => [
            'municipio' => 'Novo Hamburgo',
            'uf' => 'RS',
            'homologacao' => 'http://www.issnetonline.com.br/webserviceabrasf/homologacao/servicos.asmx',
            'producao' => 'http://www.issnetonline.com.br/webserviceabrasf/novohamburgo/servicos.asmx',
            'version' => '1.00',
            'soapns' => 'http://www.issnetonline.com.br/webservice/nfd',
            'xmlns' => 'http://www.issnetonline.com.br/webservice/nfd',
        ],
        '3543402' => [
            'municipio' => 'Ribeirão Preto',
            'uf' => 'SP',
            'homologacao' => 'http://www.issnetonline.com.br/webserviceabrasf/homologacao/servicos.asmx',
            'producao' => 'https://www.issnetonline.com.br/webserviceabrasf/ribeiraopreto/servicos.asmx',
            'version' => '1.00',
            'soapns' => 'http://www.issnetonline.com.br/webservice/nfd',
            'xmlns' => 'http://www.issnetonline.com.br/webservice/nfd',
        ]
    ];
    protected $config;
    protected $environment;
    /**
     * @var string
     */
    protected $lastRequest;
    /**
     * @var SoapCurl
     */
    protected $soap;
    /**
     * @var Certificate
     */
    private $certificate;

    public function __construct($config, Certificate $certificate)
    {
        $this->config = is_object($config) ? $config : json_decode($config);
        $this->wsobj = json_decode(json_encode($this->urls[$this->config->cmun]));
        $this->certificate = $certificate;

        $this->environment = 'homologacao';
        if ($this->config->tpamb == 1) {
            $this->environment = 'producao';
        }
    }

    public function send($message, $operation)
    {
        $action = "{$this->wsobj->soapns}/$operation";
        $url = $this->wsobj->homologacao;
        if ($this->environment === 'producao') {
            $url = $this->wsobj->producao;
        }
        $request = $this->createSoapRequest($message, $operation);
        $this->lastRequest = $request;

        if (empty($this->soap)) {
            $this->soap = new SoapCurl($this->certificate);
        }
        $parameters = [
            "Content-Type: text/xml;charset=UTF-8",
            "Accept: text/xml",
            "SOAPAction: $action",
            "Content-length: " . strlen($request),
        ];

        $response = (string)$this->soap->send(
            $operation,
            $url,
            $action,
            $request,
            $parameters
        );
        return $this->extractContentFromResponse($response, $operation);
    }

    /**
     * Build SOAP request
     *
     * @param string $message
     * @param string $operation
     * @return string XML SOAP request
     */
    protected function createSoapRequest($message, $operation)
    {
        $env = null;
        $env .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
        $env .= '<soap:Body>';
        $env .= '<' . $operation . ' xmlns="' . $this->wsobj->xmlns . '">';
        $env .= '<xml><![CDATA[' . $message . ']]></xml>';
        $env .= '</' . $operation . '>';
        $env .= '</soap:Body>';
        $env .= '</soap:Envelope>';
        $dom = new DOMImproved('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($env);

        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Extract xml response from CDATA outputXML tag
     *
     * @param string $response Return from webservice
     * @return string XML extracted from response
     */
    protected function extractContentFromResponse($response, $operation)
    {
        $dom = new DOMImproved('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;
        $dom->loadXML($response);
        if (!empty($dom->getElementsByTagName('Body')->item(0))) {
            $node = $dom->getElementsByTagName('Body')->item(0);
            return $node->textContent;
        }
        return $response;
    }
}
