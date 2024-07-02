<?php
require_once "../vendor/autoload.php";
require_once "../request_method.php";

use Firebase\JWT\JWT;

class PostRequest extends RequestMethod {

    public function resolve() {
        $email = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        if (empty($email) || empty($password))
            $this->response(401, ["message" => "Credentials must be provided."]);

        $userInfo = $this->getUserInfo($email);

        $passwordHash = $userInfo['password'];
        $userId = $userInfo['userId'];
        $customerId = $userInfo['customerId'];

        $isAdmin = $customerId === '000001';

        if ($userId === null || !password_verify($password, $passwordHash)) {
            $this->response(403, ["message" => "Invalid email or password."]);
        }

        $payload = $this->createToken($userId, $isAdmin);
        $this->response(200, $payload);
    }
    private function getUserInfo(string $email): array {
        $credentialsSelect = "
        SELECT
            UUID userId,
            CUSTOMER_ID customerId,
            PASSWORD password
        FROM
            LOGIN
        WHERE
            EMAIL = :email
        ";

        try {

            $query = $this->connection->prepare($credentialsSelect);
            $query->execute(["email" => $email]);

            $result = $query->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
        if ($result['customerId']) {
            return $result;
        }
        return [];
    }

    private function createToken(string $userId, bool $isAdmin): array {
        $domain = 'https://example.com';

        $date_utc = new DateTime("now", new DateTimeZone("UTC"));
        $expiry_date_utc = clone $date_utc;
        $expiry_date_utc->modify('+1 month');

        $payload = [
            'iss' => $domain, // issuer
            'aud' => $domain, // audience
            'iat' => $date_utc->getTimestamp(), // issued at
            'nbf' => $date_utc->getTimestamp(), // not before
            'exp' => $expiry_date_utc->getTimestamp(), // expires in
            'uid' => $userId,
            'admin' => $isAdmin,
        ];

        $privateKeyFile = '../private.key';
        $passphrase = '';
        $privateKey = openssl_pkey_get_private(
            file_get_contents($privateKeyFile),
            $passphrase
        );

        $jwt = JWT::encode($payload, $privateKey, 'RS256');
        return ["token" => $jwt, "userId" => $userId];
    }
}