<?php

namespace HaDDeR\NfseIssnet;

use Carbon\Carbon;
use DOMElement;
use HaDDeR\NfseIssnet\Common\Tools;
use HaDDeR\NfseIssnet\Models\Rps;
use InvalidArgumentException;
use NFePHP\Common\Certificate;
use NFePHP\Common\DOMImproved;
use NFePHP\Common\Signer;
use NFePHP\Common\Validator;
use stdClass;

class Make
{
    /**
     * @var DOMImproved
     */
    private $dom;
    private $config;

    public $pathSchemas;
    private $certificado;
    /**
     * @var int
     */
    private $algorithm;

    public function __construct($config, Certificate $certificado)
    {
        $this->config = $this->setConfig($config);
        $this->certificado = $certificado;

        $this->pathSchemas = __DIR__ . '/../schemes';

        //TODO ver necessidade de não ser uma constante
        $this->algorithm = OPENSSL_ALGO_SHA1;
        $this->config->autoSign = isset($config->autoSign) ? $config->autoSign : true;
    }

    /**
     * Gera o XML, assina e envia.
     *
     * @param $rpss
     * @return string
     */
    public function make($rpss)
    {
        $body = $this->makeXML($rpss);

        if ($this->config->save_xml) {
            $this->saveXML($body);
        }

        $this->validar($body, 'servico_enviar_lote_rps_envio');
        $retorno = $this->enviar($body, 'RecepcionarLoteRps');
        return $retorno;
    }

    /**
     * Gera apenas o XML sem assinar
     *
     * @param $rpss
     * @return string
     */
    public function makeXML($rpss)
    {
        $this->dom = new DOMImproved('1.0', 'utf-8');
        if ($this->config->dom->formatOutput) {
            $this->dom->formatOutput = true;
        }
        $lote = $this->header($this->config->num_lote, $this->config->remetenteCpfCnpj, $this->config->inscricaoMunicipal, count($rpss));
        $listaRps = $this->dom->createElement('tc:ListaRps');
        if (is_object($rpss)) {
            $lote->appendChild(self::render($listaRps, $rpss));
        } elseif (is_array($rpss)) {
            foreach ($rpss as $rps) {
                $lote->appendChild(self::render($listaRps, $rps));
            }
        }
        $content = $this->dom->saveXML();
        if ($this->config->dom->autoSign) {
            $content = $this->sign($content, 'LoteRps');
            $content = $this->clear($content);
        }
        return '<?xml version="1.0" encoding="utf-8"?>' . $content;
    }

    public function consultaRps($protocolo)
    {
        $this->dom = new DOMImproved('1.0', 'utf-8');
        if ($this->config->dom->formatOutput) {
            $this->dom->formatOutput = true;
        }
        $consultaSituacao = $this->dom->createElement('ConsultarLoteRpsEnvio');
        $consultaSituacao->setAttribute('xmlns', 'http://www.issnetonline.com.br/webserviceabrasf/vsd/servico_consultar_lote_rps_envio.xsd');
        $consultaSituacao->setAttribute('xmlns:tc', 'http://www.issnetonline.com.br/webserviceabrasf/vsd/tipos_complexos.xsd');
        $prestador_dom = $this->dom->createElement('Prestador');
        $tc_cpfcnpj = $this->dom->createElement('tc:CpfCnpj');
        $this->dom->addChild(
            $tc_cpfcnpj,
            'tc:Cnpj',
            $this->config->remetenteCpfCnpj,
            true,
            'Prestador CNPJ',
            false
        );

        $this->dom->addChild(
            $prestador_dom,
            'tc:InscricaoMunicipal',
            $this->config->inscricaoMunicipal,
            true,
            'Inscrição Municipal',
            false
        );
        $prestador_dom->appendChild($tc_cpfcnpj);
        $consultaSituacao->appendChild($prestador_dom);

        $this->dom->addChild(
            $consultaSituacao,
            'Protocolo',
            $protocolo,
            true,
            'Protocolo',
            false
        );

        $this->dom->appendChild($consultaSituacao);
        $content = $this->dom->saveXML();
        $this->validar($content, 'servico_consultar_lote_rps_envio');
        $retorno = $this->enviar($content, 'ConsultarLoteRps');
        return $retorno;
    }

    /**
     * Gera o conteúdo da tag tc:ListaRps
     *
     * @param $listaRps
     * @param Rps $rps
     * @return mixed
     */
    private function render(DOMElement $listaRps, Rps $rps)
    {
        $tc_rpc = $this->dom->createElement('tc:Rps');
        $tc_InfRps = $this->dom->createElement('tc:InfRps');
        $tc_IdentificacaoRps = $this->dom->createElement('tc:IdentificacaoRps');
        $this->dom->addChild(
            $tc_IdentificacaoRps,
            'tc:Numero',
            $rps->infNumero,
            true,
            "Numero do RPS",
            true
        );
        $this->dom->addChild(
            $tc_IdentificacaoRps,
            'tc:Serie',
            $rps->infSerie,
            true,
            "Serie do RPS",
            true
        );
        $this->dom->addChild(
            $tc_IdentificacaoRps,
            'tc:Tipo',
            $rps->infTipo,
            true,
            "Tipo do RPS",
            true
        );
        $this->dom->appChild($tc_InfRps, $tc_IdentificacaoRps, 'Adicionando tag IdentificacaoRPS');
//        $rps->infDataEmissao->setTimezone($this->timezone);
        $this->dom->addChild(
            $tc_InfRps,
            'tc:DataEmissao',
            $rps->infDataEmissao->format('Y-m-d\TH:i:s'),
            true,
            'Data de Emissão do RPS',
            false
        );
        $this->dom->addChild(
            $tc_InfRps,
            'tc:NaturezaOperacao',
            $rps->infNaturezaOperacao,
            true,
            'Natureza da operação',
            false
        );
        $this->dom->addChild(
            $tc_InfRps,
            'tc:OptanteSimplesNacional',
            $rps->infOptanteSimplesNacional,
            true,
            'OptanteSimplesNacional',
            false
        );
        $this->dom->addChild(
            $tc_InfRps,
            'tc:IncentivadorCultural',
            $rps->infIncentivadorCultural,
            true,
            'IncentivadorCultural',
            false
        );
        $this->dom->addChild(
            $tc_InfRps,
            'tc:Status',
            $rps->infStatus,
            true,
            'Status',
            false
        );
        if (!empty($rps->infRpsSubstituido['numero'])) {
            $rpssubs = $this->dom->createElement('tc:RpsSubstituido');
            $this->dom->addChild(
                $rpssubs,
                'tc:Numero',
                $rps->infRpsSubstituido['numero'],
                true,
                'Numero',
                false
            );
            $this->dom->addChild(
                $rpssubs,
                'tc:Serie',
                $rps->infRpsSubstituido['serie'],
                true,
                'Serie',
                false
            );
            $this->dom->addChild(
                $rpssubs,
                'tc:Tipo',
                $rps->infRpsSubstituido['tipo'],
                true,
                'tipo',
                false
            );
            $this->dom->appChild($tc_InfRps, $rpssubs, 'Adicionando tag RpsSubstituido em infRps');
        }
        $this->dom->addChild(
            $tc_InfRps,
            'tc:RegimeEspecialTributacao',
            $rps->infRegimeEspecialTributacao,
            true,
            'RegimeEspecialTributacao',
            false
        );
        $tc_servico = $this->dom->createElement('tc:Servico');
        $tc_valores = $this->dom->createElement('tc:Valores');
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorServicos',
            $rps->infValorServicos,
            true,
            'ValorServicos',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorDeducoes',
            $rps->infValorDeducoes,
            true,
            'ValorDeducoes',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorPis',
            $rps->infValorPis,
            false,
            'ValorPis',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorCofins',
            $rps->infValorCofins,
            false,
            'ValorCofins',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorInss',
            $rps->infValorInss,
            false,
            'ValorInss',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorIr',
            $rps->infValorIr,
            false,
            'ValorIr',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorCsll',
            $rps->infValorCsll,
            false,
            'ValorCsll',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:IssRetido',
            $rps->infIssRetido,
            true,
            'IssRetido',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorIss',
            $rps->infValorIss,
            false,
            'ValorIss',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorIssRetido',
            $rps->infValorIssRetido,
            false,
            'ValorIssRetido',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:OutrasRetencoes',
            $rps->infOutrasRetencoes,
            false,
            'OutrasRetencoes',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:BaseCalculo',
            $rps->infBaseCalculo,
            false,
            'BaseCalculo',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:Aliquota',
            number_format($rps->infAliquota, 2, '.', ''),
            false,
            'Aliquota',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:ValorLiquidoNfse',
            $rps->infValorLiquidoNfse,
            false,
            'ValorLiquidoNfse',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:DescontoIncondicionado',
            $rps->infDescontoIncondicionado,
            false,
            'DescontoIncondicionado',
            false
        );
        $this->dom->addChild(
            $tc_valores,
            'tc:DescontoCondicionado',
            $rps->infDescontoCondicionado,
            false,
            'DescontoCondicionado',
            false
        );
        $this->dom->appChild($tc_servico, $tc_valores, 'Adicionando tag Valores em Servico');
        $this->dom->addChild(
            $tc_servico,
            'tc:ItemListaServico',
            $rps->infItemListaServico,
            true,
            'ItemListaServico',
            false
        );
        $this->dom->addChild(
            $tc_servico,
            'tc:CodigoCnae',
            $rps->infCodigoCnae,
            true,
            'CodigoCnae',
            false
        );
        $this->dom->addChild(
            $tc_servico,
            'tc:CodigoTributacaoMunicipio',
            $rps->infCodigoTributacaoMunicipio,
            true,
            'CodigoTributacaoMunicipio',
            false
        );
        $this->dom->addChild(
            $tc_servico,
            'tc:Discriminacao',
            $rps->infDiscriminacao,
            true,
            'Discriminacao',
            false
        );
        $this->dom->addChild(
            $tc_servico,
            'tc:MunicipioPrestacaoServico',
            $rps->infMunicipioPrestacaoServico,
            true,
            'MunicipioPrestacaoServico',
            false
        );
        $this->dom->appChild($tc_InfRps, $tc_servico, 'Adicionando tag Servico');
        $tc_prestador = $this->dom->createElement('tc:Prestador');
        $tc_cpfCnpj = $this->dom->createElement('tc:CpfCnpj');
        if ($rps->infPrestador['tipo'] == 2) {
            $this->dom->addChild(
                $tc_cpfCnpj,
                'tc:Cnpj',
                $rps->infPrestador['cnpjcpf'],
                true,
                'Prestador CNPJ',
                false
            );
        } else {
            $this->dom->addChild(
                $tc_cpfCnpj,
                'tc:Cpf',
                $rps->infPrestador['cnpjcpf'],
                true,
                'Prestador CPF',
                false
            );
        }
        $this->dom->appChild($tc_prestador, $tc_cpfCnpj, 'Adicionando tag CpfCnpj em Prestador');
        $this->dom->addChild(
            $tc_prestador,
            'tc:InscricaoMunicipal',
            $rps->infPrestador['im'],
            true,
            'InscricaoMunicipal',
            false
        );
        $this->dom->appChild($tc_InfRps, $tc_prestador, 'Adicionando tag Prestador em infRPS');
        $tomador = $this->dom->createElement('tc:Tomador');
        $identificacaoTomador = $this->dom->createElement('tc:IdentificacaoTomador');
        $tc_cpfCnpjTomador = $this->dom->createElement('tc:CpfCnpj');
        if ($rps->infTomador['tipo'] == 2) {
            $this->dom->addChild(
                $tc_cpfCnpjTomador,
                'tc:Cnpj',
                $rps->infTomador['cnpjcpf'],
                true,
                'Tomador CNPJ',
                false
            );
        } else {
            $this->dom->addChild(
                $tc_cpfCnpjTomador,
                'tc:Cpf',
                $rps->infTomador['cnpjcpf'],
                true,
                'Tomador CPF',
                false
            );
        }
        $this->dom->appChild($identificacaoTomador, $tc_cpfCnpjTomador, 'Adicionando tag CpfCnpj em IdentificacaTomador');
        $this->dom->addChild(
            $identificacaoTomador,
            'tc:InscricaoMunicipal',
            $rps->infTomador['im'],
            false,
            'InscricaoMunicipal',
            false
        );
        $this->dom->appChild($tomador, $identificacaoTomador, 'Adicionando tag IdentificacaoTomador em Tomador');
        $this->dom->addChild(
            $tomador,
            'tc:RazaoSocial',
            $rps->infTomador['razao'],
            true,
            'RazaoSocial',
            false
        );
        $endereco = $this->dom->createElement('tc:Endereco');
        $this->dom->addChild(
            $endereco,
            'tc:Endereco',
            $rps->infTomadorEndereco['end'],
            true,
            'Endereco',
            false
        );
        $this->dom->addChild(
            $endereco,
            'tc:Numero',
            $rps->infTomadorEndereco['numero'],
            true,
            'Numero',
            false
        );
        $this->dom->addChild(
            $endereco,
            'tc:Complemento',
            $rps->infTomadorEndereco['complemento'],
            true,
            'Complemento',
            false
        );
        $this->dom->addChild(
            $endereco,
            'tc:Bairro',
            $rps->infTomadorEndereco['bairro'],
            true,
            'Bairro',
            false
        );
        $this->dom->addChild(
            $endereco,
            'tc:Cidade',
            $rps->infTomadorEndereco['cmun'],
            true,
            'Cidade',
            false
        );
        $this->dom->addChild(
            $endereco,
            'tc:Estado',
            $rps->infTomadorEndereco['uf'],
            true,
            'Estado',
            false
        );
        $this->dom->addChild(
            $endereco,
            'tc:Cep',
            $rps->infTomadorEndereco['cep'],
            true,
            'Cep',
            false
        );
        $this->dom->appChild($tomador, $endereco, 'Adicionando tag Endereco em Tomador');
        if ($rps->infTomador['tel'] != '' || $rps->infTomador['email'] != '') {
            $contato = $this->dom->createElement('tc:Contato');
            $this->dom->addChild(
                $contato,
                'tc:Telefone',
                $rps->infTomador['tel'],
                false,
                'Telefone Tomador',
                false
            );
            $this->dom->addChild(
                $contato,
                'tc:Email',
                $rps->infTomador['email'],
                false,
                'Email Tomador',
                false
            );
            $this->dom->appChild($tomador, $contato, 'Adicionando tag Contato em Tomador');
        }
        $this->dom->appChild($tc_InfRps, $tomador, 'Adicionando tag Tomador em infRPS');
        if (!empty($rps->infIntermediario['razao'])) {
            $intermediario = $this->dom->createElement('tc:IntermediarioServico');
            $this->dom->addChild(
                $intermediario,
                'tc:RazaoSocial',
                $rps->infIntermediario['razao'],
                true,
                'Razao Intermediario',
                false
            );
            $tc_cpfCnpj = $this->dom->createElement('tc:CpfCnpj');
            if ($rps->infIntermediario['tipo'] == 2) {
                $this->dom->addChild(
                    $tc_cpfCnpj,
                    'tc:Cnpj',
                    $rps->infIntermediario['cnpjcpf'],
                    true,
                    'CNPJ Intermediario',
                    false
                );
            } elseif ($rps->infIntermediario['tipo'] == 1) {
                $this->dom->addChild(
                    $tc_cpfCnpj,
                    'tc:Cpf',
                    $rps->infIntermediario['cnpjcpf'],
                    true,
                    'CPF Intermediario',
                    false
                );
            }
            $this->dom->appChild($intermediario, $tc_cpfCnpj, 'Adicionando tag CpfCnpj em Intermediario');
            $this->dom->addChild(
                $intermediario,
                'tc:InscricaoMunicipal',
                $rps->infIntermediario['im'],
                false,
                'IM Intermediario',
                false
            );
            $this->dom->appChild($tc_InfRps, $intermediario, 'Adicionando tag Intermediario em infRPS');
        }
        if (!empty($rps->infConstrucaoCivil['obra'])) {
            $construcao = $this->dom->createElement('tc:ContrucaoCivil');
            $this->dom->addChild(
                $construcao,
                'tc:CodigoObra',
                $rps->infConstrucaoCivil['obra'],
                true,
                'Codigo da Obra',
                false
            );
            $this->dom->addChild(
                $construcao,
                'tc:Art',
                $rps->infConstrucaoCivil['art'],
                true,
                'Art da Obra',
                false
            );
            $this->dom->appChild($tc_InfRps, $construcao, 'Adicionando tag Construcao em infRPS');
        }
        $tc_rpc->appendChild($tc_InfRps);
        $listaRps->appendChild($tc_rpc);
        return $listaRps;
    }

    private function header($numLote, $tc_cpfCnpj, $inscricao_municipal, $count_rps)
    {
        $root = $this->dom->createElement('EnviarLoteRpsEnvio');
        $this->dom->appendChild($root);
        $loteRps = $this->dom->createElement('LoteRps');
        $root->setAttribute('xmlns', 'http://www.issnetonline.com.br/webserviceabrasf/vsd/servico_enviar_lote_rps_envio.xsd');
        $root->setAttribute('xmlns:tc', 'http://www.issnetonline.com.br/webserviceabrasf/vsd/tipos_complexos.xsd');
        $root->appendChild($loteRps);
        $this->dom->addChild(
            $loteRps,
            'tc:NumeroLote',
            $numLote,
            true,
            "Numero do RPS",
            true
        );
        $tc_cpfCnpj_xml = $this->dom->createElement('tc:CpfCnpj');
        $this->dom->addChild(
            $tc_cpfCnpj_xml,
            'tc:Cnpj',
            $tc_cpfCnpj,
            true,
            "Numero do RPS",
            true
        );
        $loteRps->appendChild($tc_cpfCnpj_xml);
        $this->dom->addChild(
            $loteRps,
            'tc:InscricaoMunicipal',
            $inscricao_municipal,
            true,
            "Inscrição municipal",
            true
        );
        $this->dom->addChild(
            $loteRps,
            'tc:QuantidadeRps',
            $count_rps,
            true,
            "Quantidade de RPS",
            true
        );

        return $loteRps;
    }

    private function clear($body)
    {
        $body = str_replace('<?xml version="1.0"?>', '', $body);
        $body = str_replace('<?xml version="1.0" encoding="utf-8"?>', '', $body);
        $body = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $body);
        return $body;
    }

    /**
     * Executa a validação da mensagem XML com base no XSD
     *
     * @param string $body corpo do XML a ser validado
     * @param string $method Denominação do método
     * @return boolean
     */
    public function validar($body, $method = '')
    {
        $schema = $this->pathSchemas . DIRECTORY_SEPARATOR . $method . ".xsd";
        if (!is_file($schema)) {
            throw new InvalidArgumentException("XSD file not found. [$schema]");
        }
        return Validator::isValid(
            $body,
            $schema
        );
    }

    public function enviar($xml, $operation)
    {
        $tools = new Tools($this->config, $this->certificado);
        return $tools->send($xml, $operation);
    }

    private function sign(string $content, $tagname)
    {
        return Signer::sign(
            $this->certificado,
            $content,
            $tagname,
            '',
            $this->algorithm,
            [false, false, null, null]
        );
    }

    /**
     * Seta configurações padrões
     *
     * @param $config
     * @return mixed
     */
    private function setConfig($config)
    {
        $config = is_object($config) ? $config : json_decode($config);
        $config->nfsePath = isset($config->nfsePath) ? $config->nfsePath : '/nfse';
        $config->save_xml = isset($config->save_xml) ? $config->save_xml : false;
        $config->dom = (isset($config->dom) and is_object($config->dom) and is_a($config->dom, stdClass::class)) ? $config->dom : new stdClass();

        $config->dom->autoSign = isset($config->dom->autoSign) ? $config->dom->autoSign : true;
        $config->dom->formatOutput = isset($config->dom->formatOutput) ? $config->dom->formatOutput : false;
        return $config;
    }

    public function saveXML($body, $dir = 'xml', $filename = null)
    {
        $directory = $this->config->nfsePath . $dir . DIRECTORY_SEPARATOR;
        $filename = $filename ? $filename : Carbon::now()->format('Y_m_d_H_i_s') . '_rps';
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        $path = $directory . $filename . '.xml';
        file_put_contents($path, $body);
    }
}
