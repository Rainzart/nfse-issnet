<?php


namespace HaDDeR\NfseIssnet;


use DOMElement;
use Prophecy\Exception\Doubler\MethodNotFoundException;
use stdClass;

class Response
{
    /**
     * @param $method
     * @param $documentElement
     * @return stdClass
     */
    public static function resolve($method, $documentElement)
    {
        $method = 'get' . $method;
        if (method_exists(self::class, $method)) {
            return call_user_func([self::class, $method], $documentElement);
        } else {
            throw new MethodNotFoundException('Método ' . $method . ' não encontrado. Classe: ' . self::class, static::class, $method);
        }
    }

    private static function getConsultarLoteRpsResposta(DOMElement $element)
    {
        if ($element->getElementsByTagName('ListaNfse')->length > 0) {
            $namespace = 'http://www.issnetonline.com.br/webserviceabrasf/vsd/tipos_complexos.xsd';
            $nod = $element->getElementsByTagNameNS($namespace, 'InfNfse')->item(0);
            $std = new stdClass();
            foreach ($nod->childNodes as $value) {
                $std->{$value->localName} = ($value->childNodes->length > 1) ? self::setStdClass($value->childNodes) : $value->nodeValue;
            }
            return $std;
        } elseif ($element->getElementsByTagName('MensagemRetorno')->length > 0) {
            $response = new stdClass();
            $response->numero = $element->getElementsByTagName('Numero')->item(0)->nodeValue;
            $response->serie = $element->getElementsByTagName('Serie')->item(0)->nodeValue;
            $response->tipo = $element->getElementsByTagName('Tipo')->item(0)->nodeValue;
            $response->codigo = $element->getElementsByTagName('Codigo')->item(0)->nodeValue;
            $response->mensagem = $element->getElementsByTagName('Mensagem')->item(0)->nodeValue;
        } else {
            $response = new stdClass();
        }
        return $response;
    }

    private static function getEnviarLoteRpsResposta(DOMElement $element)
    {
        $response = self::setStdClass($element->childNodes);
        return $response;
    }

    private static function setStdClass($values)
    {
        $std = new stdClass();
        foreach ($values as $key => $value) {
            if ($value->childNodes->length > 1) {
                $std->{$value->localName} = self::setStdClass($value->childNodes);
            } else {
                $std->{$value->localName} = $value->nodeValue;
            }
        }
        return $std;
    }
}
