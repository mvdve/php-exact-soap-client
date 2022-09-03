<?php

namespace ExactSoapClient;

class ExactSoapConfig {

    public string $host;
    public string $uid;
    public string $password;
    public string $databaseHost;
    public string $databaseName;
    private int $port;
    private bool $https;
    private string $service;
    private string $wsdl;

    public function __construct(string $host, string $uid, string $password, string $databaseHost, string $databaseName, int $port = 8010, bool $https = true) {
        $this->host = $host;
        $this->uid = $uid;
        $this->password = $password;
        $this->databaseHost = $databaseHost;
        $this->databaseName = $databaseName;
        $this->port = $port;
        $this->https = $https;

        $this->service = "/services/Exact.Entity.EG";
        $this->wsdl = "singleWsdl";
    }

    public function setService(string $service): void {
        $this->service = $service;
    }

    public function setWsdl(string $wsdl): void {
        $this->wsdl = $wsdl;
    }

    public function getServiceUrl(): string {
        return $this->https ? 'https://' : 'http://' . $this->host . ':' . $this->port . $this->service;
    }

    public function getWsdlUrl(): string {
        return $this->getServiceUrl() . "?" . $this->wsdl;
    }

}