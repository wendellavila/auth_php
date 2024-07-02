<?php
require_once "../request_method.php";

class DeleteRequest extends RequestMethod {
    public function resolve() {
        $token = $this->getToken();
        $isAdmin = $token['admin'] === true;

        if (!$isAdmin)
            $this->response(403, ["message" => "Access denied."]);
        $userId = $_GET['userId'];

        if ($userId === null)
            $this->response(400, ["message" => "'userId' must be provided."]);

        $this->deleteUser($userId);
    }

    private function deleteUser(string $userId) {
        $userSelect = "
        SELECT ID as userId
        FROM LOGIN
        WHERE LOGIN.UUID = TRY_CAST(:userId AS uniqueidentifier)
        ";
        $userDelete = "
        DELETE FROM LOGIN
        WHERE UUID = TRY_CAST(:userId AS uniqueidentifier)
        ";

        try {

            $query = $this->connection->prepare($userSelect);
            $query->execute(["userId" => $userId]);
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

            if (empty($users)) {
                $this->response(404, ["message" => "User not found."]);
            }
            $query = $this->connection->prepare($userDelete);
            $query->execute(["userId" => $userId]);

            $this->response(204);
        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }

        http_response_code(501);
        exit();
    }
}