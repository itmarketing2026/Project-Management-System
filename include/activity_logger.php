<?php

function logActivity(
    mysqli $db,
    string $action,
    ?string $module = null,
    ?int $record_id = null,
    ?string $description = null,
    $old_values = null,
    $new_values = null,
    ?int $user_id_override = null
) {

    /* ===============================
       USER INFORMATION
    ================================ */

    $user_id = $user_id_override ?? ($_SESSION['user_id'] ?? null);

    $user_name = $_SESSION['full_name'] ?? null;


    /* ===============================
       IP ADDRESS
    ================================ */

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;


    /* ===============================
       BROWSER / DEVICE
    ================================ */

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;


    /* ===============================
       JSON DATA
    ================================ */

    $old_json = $old_values !== null
        ? json_encode(
            $old_values,
            JSON_UNESCAPED_UNICODE
        )
        : null;


    $new_json = $new_values !== null
        ? json_encode(
            $new_values,
            JSON_UNESCAPED_UNICODE
        )
        : null;


    /* ===============================
       INSERT ACTIVITY LOG
    ================================ */

    $stmt = $db->prepare("
        INSERT INTO activity_logs
        (
            user_id,
            user_name,
            action,
            module,
            record_id,
            description,
            old_values,
            new_values,
            ip_address,
            user_agent
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");


    if (!$stmt) {
        return false;
    }


    /* ===============================
       BIND PARAMETERS
    ================================ */

    $stmt->bind_param(
        "isssisssss",
        $user_id,
        $user_name,
        $action,
        $module,
        $record_id,
        $description,
        $old_json,
        $new_json,
        $ip_address,
        $user_agent
    );


    /* ===============================
       EXECUTE
    ================================ */

    $success = $stmt->execute();


    $stmt->close();


    return $success;
}