<?php
require_once "../request_method.php";

class PutRequest extends RequestMethod {

    public function resolve() {
        $body = json_decode(file_get_contents('php://input'), true);
        $token = $this->getToken();
        $isAdmin = $token['admin'] === true;

        if (!$isAdmin)
            $this->response(403, ["message" => "Access denied."]);

        $value = $body["value"] ?? null;
        if ($value === null)
            $this->response(403, ["message" => "'value' must be provided."]);

        $this->createTempRecord($value);
    }

    private function createTempRecord(string $value) {
        $tempRecordInsert = "
        INSERT INTO LOGIN_TEMP
        VALUES (DEFAULT, DEFAULT, :value)
        ";

        $tempRecordSelect = "
        SELECT TEMP_UUID AS tempId
        FROM LOGIN_TEMP
        WHERE ID = :id
        ";

        try {

            $query = $this->connection->prepare($tempRecordInsert);
            $query->execute(["value" => $value]);

            $id = $this->connection->lastInsertId();

            $query = $this->connection->prepare($tempRecordSelect);
            $query->execute(["id" => $id]);

            $result = $query->fetch(PDO::FETCH_ASSOC);
            $tempUid = $result['tempId'];
            $this->response(201, ['tempId' => $tempUid]);
        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
    }
}