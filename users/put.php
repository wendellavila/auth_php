<?php
require_once "../request_method.php";

class PutRequest extends RequestMethod {

    public function resolve() {
        $body = json_decode(file_get_contents('php://input'), true);

        $password = $body["password"] ?? null;
        $queryUserId = $_GET['userId'];
        $email = $body["email"] ?? null;
        $newEmail = $body["newEmail"] ?? null;
        $newPassword = $body["newPassword"] ?? null;
        $customerId = $body["customerId"] ?? null;
        $tempId = $body["tempId"] ?? null;

        if ($queryUserId === null) {
            $token = $this->getToken(false);
            $isAdmin = $token['admin'] === true;
            $this->handleCreate($password, $email, $customerId, $tempId, $isAdmin);
        } else {
            $token = $this->getToken();
            $tokenUserId = $token['uid'];
            $isAdmin = $token['admin'] === true;
            if (!$isAdmin && $tokenUserId !== $queryUserId)
                $this->response(403, ["message" => "Access denied."]);

            $this->handleUpdate($password, $queryUserId, $newPassword, $newEmail);
        }
    }

    private function handleUpdate(string $password, string $queryUserId, ?string $newPassword, ?string $newEmail) {
        if ($password === null)
            $this->response(400, ["message" => "'password' is required."]);

        $this->confirmPassword($queryUserId, $password);
        if ($newEmail === null && $newPassword === null) {
            $this->response(400, [
                "message" => "Either 'newEmail' or 'newPassword' must be provided."
            ]);
        } else if ($newEmail !== null && $newPassword !== null) {
            $this->response(400, [
                "message" => "'newEmail' and 'newPassword' must not be provided at the same time."
            ]);
        } else if ($newEmail !== null) {
            $this->updateEmail($queryUserId, $newEmail);
        } else {
            $this->updatePassword($queryUserId, $password, $newPassword);
        }
    }

    private function handleCreate(string $password, ?string $email, ?string $customerId, ?string $tempId, bool $isAdmin) {

        if ($customerId !== null && !$isAdmin)
            $this->response(403, ["message" => "Access denied."]);

        if ($email === null)
            $this->response(400, ["message" => "'email' is required."]);

        if ($password === null)
            $this->response(400, ["message" => "'password' is required."]);

        if ($customerId === null) {
            if ($tempId === null)
                $this->response(400, ["message" => "Either 'customerId' or 'tempId' must be provided."]);

            $customerId = $this->getCustomerIdFromTemp($tempId);
            if ($customerId === null)
                $this->response(403, ["message" => "'tempId' expired or doesn't exist."]);
        }
        $this->createUser($customerId, $email, $password, $tempId);
    }

    private function getCustomerIdFromTemp(?string $tempId) {
        $customerIdSelect = "
        SELECT VALUE AS customerId FROM LOGIN_TEMP
        WHERE TEMP_UUID = TRY_CAST(:tempId AS UNIQUEIDENTIFIER) AND EXP_DATE >= :date
        ";

        $date_utc = new DateTime("now", new DateTimeZone("UTC"));
        $smallDateTime = $date_utc->format("Ymd H:i");

        try {

            $query = $this->connection->prepare($customerIdSelect);
            $query->execute(["tempId" => $tempId, "date" => $smallDateTime]);
            $tempRecords = $query->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($tempRecords)) {
                return $tempRecords[0]['customerId'];
            }
        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
        return null;
    }

    private function createUser(string $customerId, string $email, string $password, ?string $tempId) {
        $this->validateEmail($email);
        $this->validatePassword($password);
        $password = password_hash($password, PASSWORD_BCRYPT);

        $userSelect = "SELECT EMAIL FROM LOGIN WHERE EMAIL = :email";
        $userInsert = "INSERT INTO LOGIN VALUES (DEFAULT, :email, :password, :customerId)";
        $userInsertTempIdDelete = "
        BEGIN TRANSACTION
        BEGIN TRY
            INSERT INTO LOGIN VALUES (DEFAULT, :email, :password, :customerId)
            DELETE FROM LOGIN_TEMP WHERE TEMP_UUID = :tempId
            COMMIT TRANSACTION
        END TRY
        BEGIN CATCH
            ROLLBACK TRANSACTION
        END CATCH
        ";

        try {

            $query = $this->connection->prepare($userSelect);
            $query->execute(["email" => $email]);
            $users = $query->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($users)) {
                $this->response(201, ["message" => "User already exists."]);
            }

            if ($tempId === null) {
                $query = $this->connection->prepare($userInsert);
                $query->execute([
                    "email" => $email,
                    "password" => $password,
                    "customerId" => $customerId
                ]);
            } else {
                $query = $this->connection->prepare($userInsertTempIdDelete);
                $query->execute([
                    "email" => $email,
                    "password" => $password,
                    "customerId" => $customerId,
                    "tempId" => $tempId
                ]);
            }


        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
        $this->response(201, ["message" => "Account created successfully."]);
    }

    private function confirmPassword(string $userId, string $password) {
        $passwordSelect = "
        SELECT PASSWORD AS password
        FROM LOGIN
        WHERE LOGIN.UUID = TRY_CAST(:userId AS uniqueidentifier)";

        try {

            $query = $this->connection->prepare($passwordSelect);
            $query->execute(["userId" => $userId]);
            $result = $query->fetch(PDO::FETCH_ASSOC);


            if (!isset($result['password'])) {
                $this->response(404, ["message" => "User not found."]);
            }
            if (!password_verify($password, $result['password'])) {
                $this->response(403, ["message" => "Access denied."]);
            }
            return;
        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
    }

    private function updateEmail(string $userId, string $newEmail) {
        $this->validateEmail($newEmail);

        $emailSelect = "
        SELECT LOGIN.UUID AS userId, LOGIN.EMAIL AS email
        FROM LOGIN
        WHERE LOGIN.EMAIL = :email";
        $emailUpdate = "
        UPDATE LOGIN
        SET EMAIL = :email
        WHERE UUID = TRY_CAST(:userId AS uniqueidentifier)
        ";

        try {

            $query = $this->connection->prepare($emailSelect);
            $query->execute(["email" => $newEmail]);
            $userInfo = $query->fetch(PDO::FETCH_ASSOC);
            if (isset($userInfo['email'])) {
                $emailUid = $userInfo['userId'];
                if ($emailUid === $userId) {
                    $this->response(200, ["message" => "New email is the same as previous email."]);
                } else {
                    $this->response(200, ["message" => "Email is already in use by a different account."]);
                }
            }
            $query = $this->connection->prepare($emailUpdate);
            $query->execute(["email" => $newEmail, "userId" => $userId]);

        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
        $this->response(200, ["message" => "Email updated successfully."]);
    }

    private function validateEmail(string $newEmail): void {
        // regex matches if email is VALID
        $regex = "/^\S+@\S+$/";
        // If invalid
        if (!preg_match($regex, $newEmail)) {
            $this->response(400, ["message" => "Email is invalid."]);
        }
    }

    private function updatePassword(string $userId, string $password, string $newPassword) {
        $this->validatePassword($newPassword);

        if ($password === $newPassword)
            $this->response(200, ["message" => "New password is the same as previous password."]);

        $newPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $passwordUpdate = "
        UPDATE LOGIN
        SET PASSWORD = :password
        WHERE UUID = TRY_CAST(:userId AS uniqueidentifier)
        ";

        try {

            $query = $this->connection->prepare($passwordUpdate);
            $query->execute(["password" => $newPassword, "userId" => $userId]);

        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
        $this->response(200, ["message" => "Password updated successfully."]);
    }

    private function validatePassword(string $newPassword): void {
        $isInvalid = strlen($newPassword) < 8;

        if ($isInvalid) {
            $this->response(400, ["message" =>
                "Passwords must have at least 8 characters."
            ]);
        }
    }

    private function validateCustomerId(string $customerId): void {
        $customerIdSelect = "
        SELECT
            CUSTOMER_ID
        FROM
            CUSTOMERS
        WHERE
            CUSTOMER_ID = :customerId
        ";
        try {

            $query = $this->connection->prepare($customerIdSelect);
            $query->execute(["customerId" => $customerId]);
            $customers = $query->fetchAll();

            if (empty($customers)) {
                $this->response(400, ["message" => "'customerId' is invalid."]);
            }
        } catch (Exception $e) {
            $this->response(500, ["message" => "Internal server error."]);
        }
    }
}