<?php

namespace App\Handlers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Exceptions\AmoCRMApiException;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\NoteType\CommonNote;
use App\Database\DatabaseConnector;
use Exception;
use PDO;

class LeadHandler
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
     * Создаём сделку
     *
     * @param array $lead
     * @throws Exception
     */
    public function handleNewLead(array $lead): void
    {
        try {
            $leadName = $lead['name'];
            $date = date('d.m.Y H:i:s', $lead['date_create']);
            $date = str_replace(' ', ', ', $date);
            $userData = $this->getResponsibleUser($lead['responsible_user_id']);
            $noteText = "Создана сделка «" . $leadName . "»\nДата/время создания: $date\nОтветственный: `{$userData['userName']} ({$userData['userEmail']})`";

            $this->addNoteToLead($lead['id'], $noteText);

            // Записываем данные сделки в БД
            $stmt = $this->pdo->prepare("INSERT INTO leads (id, name, status_id, price, responsible_user_id, last_modified, modified_user_id, created_user_id, date_create, pipeline_id, account_id, created_at, updated_at) VALUES (:id, :name, :status_id, :price, :responsible_user_id, :last_modified, :modified_user_id, :created_user_id, :date_create, :pipeline_id, :account_id, :created_at, :updated_at)");
            $stmt->execute([
                ':id' => $lead['id'],
                ':name' => $lead['name'],
                ':status_id' => $lead['status_id'],
                ':price' => $lead['price'],
                ':responsible_user_id' => $lead['responsible_user_id'],
                ':last_modified' => $lead['last_modified'],
                ':modified_user_id' => $lead['modified_user_id'],
                ':created_user_id' => $lead['created_user_id'],
                ':date_create' => $lead['date_create'],
                ':pipeline_id' => $lead['pipeline_id'],
                ':account_id' => $lead['account_id'],
                ':created_at' => $lead['created_at'],
                ':updated_at' => $lead['updated_at']
            ]);
        } catch (Exception $e) {
            echo "Ошибка добавления сделки: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Обновляем сделку
     *
     * @param array $lead
     * @throws Exception
     */
    public function handleLeadUpdate(array $lead): void
    {
        // Получаем из БД данные о сделке для сравнения и обновления, затем смотрим что изменилось
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, status_id, price, responsible_user_id FROM leads WHERE id = :id");
            $stmt->execute([
                ':id' => $lead['id']
            ]);
            $existingData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            echo "Ошибка получения данных о сделке: " . $e->getMessage() . "\n";
        }

        if ($existingData) {
            $changes = [];

            if ($lead['name'] !== $existingData['name']) {
                $changes['name'] = "Название сделки изменено с '{$existingData['name']}' на '{$lead['name']}'";
            }

            // Ищем название статуса по ID
            if ((int)$lead['status_id'] !== $existingData['status_id']) {
                $statusId = (int)$lead['status_id'];
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
                    echo "Ошибка получения данных о сделках: " . $e->getMessage();
                }

                $changes['status_id'] = "Статус сделки изменён на $statusName";
            }

            if ($lead['price'] !== $existingData['price']) {
                $changes['price'] = "Бюджет изменен с '{$existingData['price']}' на '{$lead['price']}'";
            }

            if ((int)$lead['responsible_user_id'] !== $existingData['responsible_user_id']) {
                $existingUserData = $this->getResponsibleUser($existingData['responsible_user_id']);
                $newUserData = $this->getResponsibleUser($existingData['responsible_user_id']);
                $changes['responsible_user_id'] = "Отвественный измнен с '{$existingUserData['userName']}' на '{$newUserData['userName']}'";
            }

            if (!empty($changes)) {
                $message = '';
                foreach ($changes as $field => $change) {
                    $message .= "$change\n";
                }

                $date = date('d.m.Y H:i:s', $lead['last_modified']);
                $date = str_replace(' ', ', ', $date);
                $message .= "Дата/время изменения: $date";
                $this->addNoteToLead($lead['id'], $message);
            }
        }

        // Обновляем запись в БД
        try {
            $stmt = $this->pdo->prepare("UPDATE leads SET name = :name, status_id = :status_id, old_status_id = :old_status_id, price = :price, responsible_user_id = :responsible_user_id,
        last_modified = :last_modified, modified_user_id = :modified_user_id, created_user_id = :created_user_id,
        date_create = :date_create, pipeline_id = :pipeline_id, account_id = :account_id, created_at = :created_at,
        updated_at = :updated_at WHERE id = :id");
            $stmt->execute([
                ':id' => $lead['id'],
                ':name' => $lead['name'],
                ':status_id' => $lead['status_id'],
                ':old_status_id' => $lead['old_status_id'],
                ':price' => $lead['price'],
                ':responsible_user_id' => $lead['responsible_user_id'],
                ':last_modified' => $lead['last_modified'],
                ':modified_user_id' => $lead['modified_user_id'],
                ':created_user_id' => $lead['created_user_id'],
                ':date_create' => $lead['date_create'],
                ':pipeline_id' => $lead['pipeline_id'],
                ':account_id' => $lead['account_id'],
                ':created_at' => $lead['created_at'],
                ':updated_at' => $lead['updated_at']
            ]);
        } catch (Exception $e) {
            echo "Ошибка обновления сделки: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Добавляем примечание к сделке
     *
     * @param int $leadId
     * @param string $noteText
     * @throws AmoCRMApiException
     */
    public function addNoteToLead(int $leadId, string $noteText): void
    {
        $note = new CommonNote();
        $note->setEntityId($leadId)->setText($noteText);

        $notesService = $this->apiClient->notes(EntityTypesInterface::LEADS);

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
