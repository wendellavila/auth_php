<?php
require_once "../request_method.php";

class GetRequest extends RequestMethod {

    public function resolve() {
        $token = $this->getToken();

        $isAdmin = $token['admin'] === true;
        $queryUserId = $_GET['userId'] ?? null;
        $tokenUserId = $token['uid'];
        $listAll = $_GET['listAll'] ?? null;

        $isQueryDifferentThanToken = $queryUserId !== null && $queryUserId !== $tokenUserId;

        if (!$isAdmin && $isQueryDifferentThanToken)
            $this->response(403, ["message" => "Access denied."]);

        $customers = $isAdmin && isset($listAll) ? $this->getAllUsers() : $this->getUser($queryUserId ?? $tokenUserId);
        $this->response(200, $customers);
    }

    private function getUser(string $userId): array {
        $customerSelect = "
        SELECT
            UUID AS userId,
            RTRIM(LOGIN.CUSTOMER_ID) AS customerId,
            RTRIM(LOGIN.EMAIL) AS email
        FROM
            LOGIN
        WHERE
            LOGIN.UUID = TRY_CAST(:userId AS uniqueidentifier)
        ";
        $users = [];
        try {
            $query = $this->connection->prepare($customerSelect);
            $query->execute(["userId" => $userId]);
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }

        if ($userId !== null) {
            if (!empty($users))
                $users[0] = $this->setExtraInfo($users[0]);
            else
                $this->response(404, ["message" => "User not found."]);
        }

        return $users[0];
    }

    private function getAllUsers() {
        $allCustomersSelect = "
        SELECT
            UUID userId,
            RTRIM(LOGIN.CUSTOMER_ID) AS customerId,
            RTRIM(LOGIN.EMAIL) AS email
        FROM
            LOGIN
        ";
        try {

            $query = $this->connection->prepare($allCustomersSelect);
            $query->execute();
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }

        for ($i = 0; $i < count($users); $i++) {
            $users[$i] = $this->setExtraInfo($users[$i]);
        }
        return ["users" => $users];
    }

    private function setExtraInfo(array $userInfo): array {
        $role = $userInfo['customerId'] === '000001' ? 'admin' : 'customer';
        $userInfo['role'] = $role;
        return $userInfo;
    }
}