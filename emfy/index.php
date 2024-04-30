<?php

use AmoCRM\Client\LongLivedAccessToken;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\NoteType\CommonNote;
use AmoCRM\Exceptions\AmoCRMApiException;

include_once __DIR__ . '/../vendor/autoload.php';

$host = 'localhost';
$dbname = 'emfy';
$username = 'emfy';
$password = 'emfy';

$accessToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6IjkzN2VjZGI4MmQ5NDVhM2RjNzU2ODM4ODgzMTIzZGUzZTM4OTI3YjMyZTUyY2Q2ODdkMGU3MmJjNzc0YTZiNTMwMzIxMGVlZTBiZjYxNTM4In0.eyJhdWQiOiI2M2EzMTUyMy0xYjhhLTRkYmQtOGEzNC1kYTM3NzEwN2NlNjAiLCJqdGkiOiI5MzdlY2RiODJkOTQ1YTNkYzc1NjgzODg4MzEyM2RlM2UzODkyN2IzMmU1MmNkNjg3ZDBlNzJiYzc3NGE2YjUzMDMyMTBlZWUwYmY2MTUzOCIsImlhdCI6MTcxNDQ2NTI5MywibmJmIjoxNzE0NDY1MjkzLCJleHAiOjE4NjAxOTIwMDAsInN1YiI6IjEwOTk4Njk4IiwiZ3JhbnRfdHlwZSI6IiIsImFjY291bnRfaWQiOjMxNzI3ODE0LCJiYXNlX2RvbWFpbiI6ImFtb2NybS5ydSIsInZlcnNpb24iOjIsInNjb3BlcyI6WyJjcm0iLCJmaWxlcyIsImZpbGVzX2RlbGV0ZSIsIm5vdGlmaWNhdGlvbnMiLCJwdXNoX25vdGlmaWNhdGlvbnMiXSwiaGFzaF91dWlkIjoiNzVmN2EzZTAtNTAzYi00ODhjLTgwYTQtMGNlODEwMTU4ZTg1In0.P5-XemdQ0Vb5iw_euKMZeVfiUZ51WfRcT39pn7JTVUBg-x4nRzQVRglZlF_VN0ShLDiFo43FvGjOA5e_eC_GioPlccve710MPlN2e9IorNX0Cr-VjSC9r1jR0gUGp0mKyVqb23tt4XiyIjlpbOOaOKbxVQDv1GbE1hWuVAYbRFoX2tMVyHXPX0kdUFPljBJiO2SFUoh8L_Asg6T-sfnFkPq1wYyd2s4Jp0TkAH7_mDIFIe97NlzUkC7HULrULu-cxmfPvPSIJEnk8TlAhGQaGi7fxV6ZjSriR0m4eA468fGxzC6cb9sO4pJW5ELgo3brq8nYAd84iIWlV105R-GRYA";

$apiClient = new \AmoCRM\Client\AmoCRMApiClient();

try {
    $longLivedAccessToken = new LongLivedAccessToken($accessToken);
} catch (\AmoCRM\Exceptions\InvalidArgumentException $e) {
    printError($e);
    die;
}

$apiClient->setAccessToken($longLivedAccessToken)
    ->setAccountBaseDomain('antipovmike.amocrm.ru');

/**
 * НОВАЯ СДЕЛКА
 */
if (isset($_POST['leads']['add'][0]))
{
    $leadName = $_POST['leads']['add'][0]['name'];

    try {
        // Fetch the user by ID
        $userService = $apiClient->users();
        $user = $userService->getOne($_POST['leads']['add'][0]['responsible_user_id']);
        $userName = $user->getName();
        $userEmail = $user->getEmail();
    } catch (AmoCRMApiException $e) {
        echo "Error fetching user: " . $e->getMessage();
    }

    $date = date('d.m.Y H:i:s', $_POST['leads']['add'][0]['date_create']);

    $note = new CommonNote();
    $note->setEntityId($_POST['leads']['add'][0]['id'])
        ->setText("Создана сделка «" . $leadName . "», дата/время создания: $date, ответственный: $userName ($userEmail)");

    // Service for working with notes
    $notesService = $apiClient->notes(EntityTypesInterface::LEADS);

    try {
        // Add the note to the lead
        $note = $notesService->addOne($note);
        echo "Note added successfully with ID: " . $note->getId();
    } catch (AmoCRMApiException $e) {
        // Handle exception
        echo "Error adding note: " . $e->getMessage();
    }

    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host; dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepare the SQL statement
    $sql = "INSERT INTO leads (id, name, status_id, price, responsible_user_id, last_modified, modified_user_id, created_user_id, date_create, pipeline_id, account_id, created_at, updated_at) VALUES (:id, :name, :status_id, :price, :responsible_user_id, :last_modified, :modified_user_id, :created_user_id, :date_create, :pipeline_id, :account_id, :created_at, :updated_at)";

    $stmt = $pdo->prepare($sql);

    // Check if 'leads' and 'update' keys exist in the POST array
    if (isset($_POST['leads']['add'])) {
        foreach ($_POST['leads']['add'] as $update) {
            // Bind values from the array to the placeholders in the SQL statement
            $stmt->bindParam(':id', $update['id'], PDO::PARAM_INT);
            $stmt->bindParam(':name', $update['name'], PDO::PARAM_STR);
            $stmt->bindParam(':status_id', $update['status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':price', $update['price']);
            $stmt->bindParam(':responsible_user_id', $update['responsible_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':last_modified', $update['last_modified'], PDO::PARAM_INT);
            $stmt->bindParam(':modified_user_id', $update['modified_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':created_user_id', $update['created_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':date_create', $update['date_create'], PDO::PARAM_INT);
            $stmt->bindParam(':pipeline_id', $update['pipeline_id'], PDO::PARAM_INT);
            $stmt->bindParam(':account_id', $update['account_id'], PDO::PARAM_INT);
            $stmt->bindParam(':created_at', $update['created_at'], PDO::PARAM_INT);
            $stmt->bindParam(':updated_at', $update['updated_at'], PDO::PARAM_INT);

            // Execute the statement
            try {
                $stmt->execute();
            } catch (PDOException $e) {
                echo $e->getMessage();
                die;
            }
        }

        echo "Data has been successfully inserted!";
    }

    /**
     * ОБНОВЛЕНИЕ СДЕЛКИ
     */
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=$host; dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Prepare the SQL statement for updating
    $sql = "UPDATE leads SET name = :name, status_id = :status_id, old_status_id = :old_status_id, price = :price, responsible_user_id = :responsible_user_id, 
    last_modified = :last_modified, modified_user_id = :modified_user_id, created_user_id = :created_user_id, 
    date_create = :date_create, pipeline_id = :pipeline_id, account_id = :account_id, created_at = :created_at, 
    updated_at = :updated_at WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    // Check if the necessary keys exist in the POST array
    if (isset($_POST['leads']['update'])) {
        foreach ($_POST['leads']['update'] as $update) {
            // Bind values from the array to the placeholders in the SQL statement
            $stmt->bindParam(':id', $update['id'], PDO::PARAM_INT);
            $stmt->bindParam(':name', $update['name'], PDO::PARAM_STR);
            $stmt->bindParam(':status_id', $update['status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':old_status_id', $update['old_status_id'], PDO::PARAM_INT);
            $stmt->bindParam(':price', $update['price']);
            $stmt->bindParam(':responsible_user_id', $update['responsible_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':last_modified', $update['last_modified'], PDO::PARAM_INT);
            $stmt->bindParam(':modified_user_id', $update['modified_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':created_user_id', $update['created_user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':date_create', $update['date_create'], PDO::PARAM_INT);
            $stmt->bindParam(':pipeline_id', $update['pipeline_id'], PDO::PARAM_INT);
            $stmt->bindParam(':account_id', $update['account_id'], PDO::PARAM_INT);
            $stmt->bindParam(':created_at', $update['created_at'], PDO::PARAM_INT);
            $stmt->bindParam(':updated_at', $update['updated_at'], PDO::PARAM_INT);

            // Execute the statement
            try {
                $stmt->execute();
            } catch (PDOException $e) {
                echo $e->getMessage();
                die;
            }
        }
        echo "Data has been successfully inserted!";
    }
}
