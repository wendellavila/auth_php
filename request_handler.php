<?php

require_once "request_method.php";
class OptionsRequest extends RequestMethod {
    public function resolve() {
        $this->response(200);
    }
}
class RequestHandler {
    private $get;
    private $post;
    private $put;
    private $delete;
    private $options;
    private $head;
    private $patch;
    private $trace;
    private $connect;

    private $methodNames = [];

    public function __construct(
        ?RequestMethod $get = null,
        ?RequestMethod $post = null,
        ?RequestMethod $put = null,
        ?RequestMethod $delete = null,
        ?RequestMethod $options = null,
        ?RequestMethod $head = null,
        ?RequestMethod $patch = null,
        ?RequestMethod $trace = null,
        ?RequestMethod $connect = null
    ) {
        $methods = [
            'GET' => $get,
            'POST' => $post,
            'PUT' => $put,
            'DELETE' => $delete,
            'HEAD' => $head,
            'PATCH' => $patch,
            'TRACE' => $trace,
            'CONNECT' => $connect
        ];
        foreach ($methods as $name => $method) {
            if ($method !== null)
                array_push($this->methodNames, $name);
        }
        // OPTIONS is always included due to CORS
        array_push($this->methodNames, 'OPTIONS');

        $defaultMethod = new RequestMethod();
        $this->get = $get ?? $defaultMethod;
        $this->post = $post ?? $defaultMethod;
        $this->put = $put ?? $defaultMethod;
        $this->delete = $delete ?? $defaultMethod;
        $this->head = $head ?? $defaultMethod;
        $this->patch = $patch ?? $defaultMethod;
        $this->trace = $trace ?? $defaultMethod;
        $this->connect = $connect ?? $defaultMethod;

        $this->options = $options ?? new OptionsRequest();
    }

    public function resolve() {
        $this->handleCors();
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        switch ($method) {
            case 'GET':
                $this->get->resolve();
                break;
            case 'POST':
                $this->post->resolve();
                break;
            case 'PUT':
                $this->put->resolve();
                break;
            case 'DELETE':
                $this->delete->resolve();
                break;
            case 'OPTIONS':
                $this->options->resolve();
                break;
            case 'HEAD':
                $this->head->resolve();
                break;
            case 'TRACE':
                $this->trace->resolve();
                break;
            case 'PATCH':
                $this->patch->resolve();
                break;
            case 'CONNECT':
                $this->connect->resolve();
                break;
            default:
                http_response_code(400);
                break;
        }
    }

    private function handleCors(): void {
        if (!empty($this->methodNames)) {
            header('Access-Control-Allow-Methods: ' . implode(', ', $this->methodNames));
        }
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Max-Age: 600');

        $allowedDomains = [
            'https://example.com',
            'https://subdomain.example.com',
        ];

        $domain = $this->getUrl();
        if (in_array($domain, $allowedDomains))
            header("Access-Control-Allow-Origin: $domain");
        else
            header("Access-Control-Allow-Origin: " . $allowedDomains[0]);
    }

    private static function getUrl(): string {
        return static::getProtocol() . $_SERVER['HTTP_HOST'];
    }

    private static function getProtocol(): string {
        if (!empty($_SERVER['HTTPS']))
            return $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
        else
            return $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://';
    }
}