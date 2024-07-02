# auth_php

PHP authentication API using JWT and SQL Server.

The API was designed to provide access control for existing customers with data in a separate table/database, thus relying in an internal code to link each account to a valid customer.

# endpoints

## /auth/

Authenticates user with email and password and creates a JWT if the credentials are valid.

### post

- parameters:

  - headers:
    ```
    Authorization Basic base64(email:password)
    ```

- responses

  ```
  {
    "statusCode": 200,
    "token": "JWT",
    "userId": "UUID",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```

  ```
  {
    "statusCode": 401,
    "message": "Credentials must be provided.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```

  ```
  {
    "statusCode": 403,
    "message": "Invalid email or password.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```

## /users/

Creates, updates, deletes, or retrieves user credentials.

### get

- parameters:
  - headers:
    ```
    Authorization Bearer token
    ```
  - url:
    ```
    /users?userId=UUID
    /users/UUID/
    ```
- responses:
  ```
  {
    "statusCode": 200,
    "userId": "UUID",
    "customerId": "string",
    "email": "string",
    "role": "string",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
    "statusCode": 403,
    "message": "Access denied.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
    "statusCode": 404,
    "message": "User not found.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```

### put

- create:

  - parameters:

    - headers:
      ```
      Authorization Bearer token
      ```
    - url:
      ```
      /users
      /users/
      ```
    - body:
      ```
      {
        "email": "string",
        "password": "string",
        "customerId": "string",
        "tempId": "UUID"
      }
      ```
    - notes:
      - Either 'customerId' or 'tempId' must be provided.
      - If 'customerId' is provided, Bearer token is required to ensure only admins can create accounts.
      - If 'tempId' is provided, Bearer token is not required due to user having an access code to create its own account.

  - responses:
    ```
    {
      "statusCode": 201,
      "message": "Account created successfully.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```
    ```
    {
      "statusCode": 201,
      "message": "User already exists.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```
    ```
    {
      "statusCode": 400,
      "message": "Email is invalid.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```
    ```
    {
      "statusCode": 400,
      "message": "Passwords must have at least 8 characters.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```
    ```
    {
      "statusCode": 401,
      "message": "Credentials must be provided.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```
    ```
    {
      "statusCode": 403,
      "message": "'tempId' expired or doesn't exist.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```
    ```
    {
      "statusCode": 403,
      "message": "Access denied.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

- update:

  - parameters:

    - headers:
      ```
      Authorization Bearer token
      ```
    - url:
      ```
      /users?userId=UUID
      /users/UUID/
      ```
    - body:

      ```
      {
        "password": "string",
        "newPassword": "string",
        "newEmail": string
      }
      ```

    - notes:
      - 'password' must match user's current password in database.
      - If user is not admin, userId in url must match UUID in Bearer token.
      - Either 'newPassword' or 'newEmail' must be provided.

  - responses:

    ```
    {
      "statusCode": 200,
      "message": "Password updated successfully.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

    ```
    {
        "statusCode": 200,
        "message": "Email updated successfully.",
        "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

    ```
    {
      "statusCode": 400,
      "message": "Either 'newEmail' or 'newPassword' must be provided.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

    ```
    {
      "statusCode": 400,
      "message": "Email is invalid.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

    ```
    {
      "statusCode": 400,
      "message": "Passwords must have at least 8 characters.",
      "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

    ```
    {
    "statusCode": 401,
    "message": "Credentials must be provided.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

    ```
    {
    "statusCode": 403,
    "message": "Access denied.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
    }
    ```

### delete

- parameters:
  - headers:
    ```
    Authorization Bearer token
    ```
  - url:
    ```
    /users?userId=UUID
    /users/UUID/
    ```
- responses:
  ```
  204 No Content
  ```
  ```
  {
    "statusCode": 401,
    "message": "Credentials must be provided.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
    "statusCode": 403,
    "message": "Access denied.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
    "statusCode": 404,
    "message": "User not found.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```

## /temp/

Used to store temporary information such as account creation access codes.

### get

- parameters:
  - url:
    ```
    /temp?tempId=UUID
    /temp/UUID/
    ```
- responses:
  ```
  {
  "statusCode": 200,
  "value": "string",
  "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
  "statusCode": 404,
  "message": "Not found.",
  "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```

### put

- parameters:
  - headers:
    ```
    Authorization Bearer token
    ```
  - body:
    ```
    {
      "value": "string"
    }
    ```
- responses:
  ```
  {
    "statusCode": 201,
    "tempId": "UUID",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
    "statusCode": 401,
    "message": "Credentials must be provided.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
  ```
  {
    "statusCode": 403,
    "message": "Access denied.",
    "timestamp": "yyyy-mm-ddThh:mm:ss+00:00"
  }
  ```
