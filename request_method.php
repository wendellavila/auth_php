<?php
require_once "../vendor/autoload.php";

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class RequestMethod {
    protected $connection;

    public function __construct() {
        $this->connection = Conexao::getConnection();
    }

    public function resolve() {
        http_response_code(405);
    }

    protected static function response(int $code, ?array $payload = null): void {
        http_response_code($code);
        if (isset($payload)) {
            header('Content-Type: application/json; charset=utf-8');
            $date_utc = new DateTime("now", new DateTimeZone("UTC"));
            $timestamp = $date_utc->format(DateTime::ATOM);
            $payload = ['statusCode' => $code] + $payload + ['timestamp' => $timestamp];
            echo json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
        exit();
    }

    protected static function getToken(bool $required = true): ?array {
        $jwt = static::getBearerToken();
        $date_utc = new DateTime("now", new DateTimeZone("UTC"));

        if ($jwt === null) {
            if ($required === false) {
                return null;
            }
            static::response(401, ["message" => "Credentials must be provided."]);
        }

        $publicKeyFile = '../public.key';
        $publicKey = file_get_contents($publicKeyFile);

        try {
            $decodedJwt = (array) JWT::decode($jwt, new Key($publicKey, 'RS256'));
        } catch (Exception $e) {
            static::response(403, ["message" => "Invalid credentials."]);
        }
        $isExpired = ((int) $decodedJwt['exp'] - $date_utc->getTimestamp()) <= 0;
        if ($isExpired) {
            static::response(403, ["message" => "Credentials expired."]);
        }
        return $decodedJwt;
    }

    private static function getAuthorizationHeader(): ?string {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }
    private static function getBearerToken(): ?string {
        $headers = static::getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}