<?php
require_once "../request_method.php";

class GetRequest extends RequestMethod {

    public function resolve() {
        $tempId = $_GET['tempId'] ?? null;

        $value = $this->getValue($tempId);
        $this->response(200, ["value" => $value]);
    }

    private function getValue(string $tempId): ?string {
        $valueSelect = "
        SELECT VALUE AS value
        FROM LOGIN_TEMP
        WHERE TEMP_UUID = TRY_CAST(:tempId AS uniqueidentifier)
        AND EXP_DATE >= :date
        ";
        try {
            $query = $this->connection->prepare($valueSelect);

            $date_utc = new DateTime("now", new DateTimeZone("UTC"));
            $smallDateTime = $date_utc->format("Ymd H:i");

            $query->execute(["tempId" => $tempId, "date" => $smallDateTime]);
            $results = $query->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                return $results[0]['value'];
            } else {
                $this->response(404, ["message" => "Not found."]);
            }
        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
        return null;
    }
}