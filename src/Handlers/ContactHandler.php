<?php

namespace App\Handlers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\NoteType\CommonNote;
use App\Database\DatabaseConnector;
use Exception;
use PDO;

class ContactHandler
{
    private AmoCRMApiClient $apiClient;
    private PDO $pdo;

    /**
     * @param AmoCRMApiClient $apiClient
     * @param DatabaseConnector $dbConnector
     */
    public function __construct(AmoCRMApiClient $apiClient, DatabaseConnector $dbConnector)
    {
        $this->apiClient = $apiClient;
        $this->pdo = $dbConnector->getConnection();
    }

    /**
     * Создаём контакт
     *
     * @param array $contact
     * @throws Exception
     */
    public function handleNewContact(array $contact): void
    {
        try {
            $contactName = $contact['name'];
            $date = date('d.m.Y H:i:s', $contact['date_create']);
            $date = str_replace(' ', ', ', $date);
            $userData = $this->getResponsibleUser($contact['responsible_user_id']);
            $noteText = "Создан контакт " . $contactName . "\nДата/время создания: $date\nОтветственный: `{$userData['userName']} ({$userData['userEmail']})`";

            $this->addNoteToContact($contact['id'], $noteText);

            // Записываем данные контакта в БД
            $stmt = $this->pdo->prepare("INSERT INTO contacts (id, name, responsible_user_id, last_modified, created_user_id, modified_user_id, account_id, created_at, updated_at) VALUES (:id, :name, :responsible_user_id, :last_modified, :created_user_id, :modified_user_id, :account_id, :created_at, :updated_at)");
            $stmt->execute([
                ':id' => $contact['id'],
                ':name' => $contact['name'],
                ':responsible_user_id' => $contact['responsible_user_id'],
                ':last_modified' => $contact['last_modified'],
                ':created_user_id' => $contact['created_user_id'],
                ':modified_user_id' => $contact['modified_user_id'],
                ':account_id' => $contact['account_id'],
                ':created_at' => $contact['created_at'],
                ':updated_at' => $contact['updated_at']
            ]);
        } catch (Exception $e) {
            echo "Ошибка добавления контакта: " . $e->getMessage() . "\n";
        }

        foreach ($contact['custom_fields'] as $field) {
            try {
                // Insert custom field if it does not exist
                $stmt = $this->pdo->prepare("INSERT INTO custom_fields (name, field_id) VALUES (:name, :field_id) ON DUPLICATE KEY UPDATE field_id = field_id");
                $stmt->execute([
                    ':name' => $field['name'],
                    ':field_id' => (int)$field['id']
                ]);

                // Insert custom field values
                foreach ($field['values'] as $value) {
                    $stmt = $this->pdo->prepare("INSERT INTO custom_field_values (subject_id, custom_field_id, value) VALUES (:subject_id, :custom_field_id, :value)");
                    $stmt->execute([
                        ':subject_id' => $field['id'],
                        ':custom_field_id' => $field['id'],
                        ':value' => $value['value']
                    ]);
                }
            } catch (Exception $e) {
                echo "Ошибка добавления полей: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Обновляем контакт
     *
     * @param array $contact
     * @throws Exception
     */
    public function handleContactUpdate(array $contact): void
    {
        // Получаем из БД данные о контакте для сравнения и обновления, затем смотрим что изменилось
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, responsible_user_id FROM contacts WHERE id = :id");
            $stmt->execute([
                ':id' => $contact['id']
            ]);
            $existingData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "Ошибка получения данных о контакте: " . $e->getMessage() . "\n";
        }

        if ($existingData) {
            $changes = [];

            if ($contact['name'] !== $existingData['name']) {
                $changes['name'] = "Название контакта изменено с '{$existingData['name']}' на '{$contact['name']}'";
            }

            // Ищем название статуса по ID
            if ((int)$contact['status_id'] !== $existingData['status_id']) {
                $statusId = (int)$contact['status_id'];
                try {
                    $pipelinesService = $this->apiClient->pipelines();
                    $pipelines = $pipelinesService->get();
                    $statusName = null;

                    foreach ($pipelines as $pipeline) {
                        foreach ($pipeline->getStatuses() as $status) {
                            if ($status->getId() == $statusId) {
                                $statusName = $status->getName();
                                break 2;
                            }
                        }
                    }
                } catch (AmoCRMApiException $e) {
                    echo "Ошибка получения данных о контактах: " . $e->getMessage();
                }

                $changes['status_id'] = "Статус контакта изменён на $statusName";
            }

            if ($contact['price'] !== $existingData['price']) {
                $changes['price'] = "Бюджет изменен с '{$existingData['price']}' на '{$contact['price']}'";
            }

            if ((int)$contact['responsible_user_id'] !== $existingData['responsible_user_id']) {
                $existingUserData = $this->getResponsibleUser($existingData['responsible_user_id']);
                $newUserData = $this->getResponsibleUser($existingData['responsible_user_id']);
                $changes['responsible_user_id'] = "Отвественный измнен с '{$existingUserData['userName']}' на '{$newUserData['userName']}'";
            }

            if (!empty($changes)) {
                $message = '';
                foreach ($changes as $field => $change) {
                    $message .= "$change\n";
                }

                $date = date('d.m.Y H:i:s', $contact['last_modified']);
                $date = str_replace(' ', ', ', $date);
                $message .= "Дата/время изменения: $date";
                $this->addNoteToContact($contact['id'], $message);
            }
        }

        // Обновляем запись в БД
        try {
            $stmt = $this->pdo->prepare("UPDATE contacts SET name = :name, status_id = :status_id, old_status_id = :old_status_id, price = :price, responsible_user_id = :responsible_user_id,
        last_modified = :last_modified, modified_user_id = :modified_user_id, created_user_id = :created_user_id,
        date_create = :date_create, pipeline_id = :pipeline_id, account_id = :account_id, created_at = :created_at,
        updated_at = :updated_at WHERE id = :id");
            $stmt->execute([
                ':id' => $contact['id'],
                ':name' => $contact['name'],
                ':status_id' => $contact['status_id'],
                ':old_status_id' => $contact['old_status_id'],
                ':price' => $contact['price'],
                ':responsible_user_id' => $contact['responsible_user_id'],
                ':last_modified' => $contact['last_modified'],
                ':modified_user_id' => $contact['modified_user_id'],
                ':created_user_id' => $contact['created_user_id'],
                ':date_create' => $contact['date_create'],
                ':pipeline_id' => $contact['pipeline_id'],
                ':account_id' => $contact['account_id'],
                ':created_at' => $contact['created_at'],
                ':updated_at' => $contact['updated_at']
            ]);
        } catch (Exception $e) {
            echo "Ошибка обновления контакта: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Добавляем примечание к контакту.
     *
     * @param int $contactId
     * @param string $noteText
     * @throws AmoCRMApiException
     */
    public function addNoteToContact(int $contactId, string $noteText): void
    {
        $note = new CommonNote();
        $note->setEntityId($contactId)->setText($noteText);

        $notesService = $this->apiClient->notes(EntityTypesInterface::CONTACTS);

        try {
            $notesService->addOne($note);
        } catch (AmoCRMApiException $e) {
            echo "Ошибка добавления примечания: " . $e->getMessage();
        }
    }

    /**
     * Получаем данные ответственного
     *
     * @param int $id
     * @return array
     */
    function getResponsibleUser (int $id): array
    {
        // Получаем дату и имя/фамилию ответственного для добавления в примечание и добавляем его
        try {
            $userService = $this->apiClient->users();
            $user = $userService->getOne($id);
        } catch (AmoCRMApiException $e) {
            echo "Ошибка получения пользователя: " . $e->getMessage();
        }
        $data['userName'] = $user->getName();
        $data['userEmail'] = $user->getEmail();
        return $data;
    }
}
