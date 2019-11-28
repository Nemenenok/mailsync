<?php

use Ddeboer\Imap\Server;
use Ddeboer\Imap\Search\Date\Since;
use GuzzleHttp\Client;
use Cog\YouTrack\Rest\HttpClient\GuzzleHttpClient;
use Cog\YouTrack\Rest\Authorizer\TokenAuthorizer;
use Cog\YouTrack\Rest\Client\YouTrackClient;

class mailSync {

    // Почтовый клиент
    private $mailbox;
    // Клиент YouTrack
    private $youtrack;

    // Настройки
    private $config = [];

    /**
     * Конструктор. Инициализация сервисов
     *
     * @param $config (настройки из файла конфигурации)
     * @param $params (внешние переменные)
     */
    public function __construct(array $config, array $params) {

        // Сохранение настроек
        $this->config = $config;

        // Если передан параметр запуска скрипта
        if (isset($params[1]) && !empty($params[1])) {
            // Значения параметра
            list($param_name, $param_value) = explode('=', $params[1]);
            // Если передано значение папки для получения писем
            if ($param_name == '--folder') {
                // Присваиваем значение папки почтового сервера
                $this->config['email_config']['folder'] = $param_value;
            }
        }

        // Инициализация HTTP-клиента
        $psrHttpClient = new Client(['base_uri' => $this->config['youtrack_config']['apiBaseUri'],]);
        // Инициализация YouTrack API HTTP-клиента
        $httpClient = new GuzzleHttpClient($psrHttpClient);
        // Авторизация в YouTrack по токену
        $authorizer = new TokenAuthorizer($this->config['youtrack_config']['apiToken']);
        // Инициализация YouTrack API-клиента
        $this->youtrack = new YouTrackClient($httpClient, $authorizer);

        // Подключение к почтовому серверу
        $server = new Server($this->config['email_config']['host'], 993, 'imap/ssl/novalidate-cert');
        // Авторизация
        $connection = $server->authenticate($this->config['email_config']['email'], $this->config['email_config']['password']);
        // Получение писем из папки назначения
        $this->mailbox = $connection->getMailbox($this->config['email_config']['folder']);
    }

    /**
     * Получение проектов YouTrack
     *
     * @return array
     * @throws Exception
     */
    public function getProjects(): array {
        try {
            // Запрос к API
            $response = $this->youtrack->get('/admin/project');
            // Конвертация ответа в массив
            return $response->toArray();
        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Ошибка получения списка проектов: ".$exception->getMessage()."\n");
            die;
        }
    }

    /**
     * Получение списка задач YouTrack
     *
     * @return array
     * @throws Exception
     */
    public function getIssues(): array {
        try {
            // Запрос к API
            $response = $this->youtrack->get('/issue/byproject/HD?max=100');
            // Конвертация ответа в массив
            return $response->toArray();
        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Ошибка получения списка задач: ".$exception->getMessage()."\n");
            die;
        }
    }

    /**
     * Получение задачи YouTrack по ID
     *
     * @param $id
     * @return array
     * @throws Exception
     */
    public function getIssue(int $id): array {
        try {
            // Запрос к API
            $response = $this->youtrack->get('/issue/' . $id);
            // Конвертация ответа в массив
            return $response->toArray();
        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Ошибка получения задачи: ".$exception->getMessage()."\n");
            die;
        }
    }

    /**
     * Удаление задачи YouTrack
     *
     * @param $id
     * @throws Exception
     */
    public function deleteIssue(int $id) {
        try {
            // Запрос к API
            $this->youtrack->delete('/issue/' . $id);
        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Ошибка удаления задачи: ".$exception->getMessage()."\n");
            die;
        }
    }

    /**
     * Создание новой задачи YouTrack (проект по умолчанию Help Desk)
     * Возвращает идентификатор новой задачи
     *
     * @param $summary
     * @param $description
     * @param $project
     * @return string
     * @throws Exception
     */
    public function createIssue(string $summary, string $description, string $project = 'HD'): string {
        try {
            // Запрос к API на создание задачи
            $response = $this->youtrack->post('/issue', [
                'project' => $project,
                'summary' => $summary,
                'description' => $description
            ]);

            // Конвертация ответа в массив
            $newIssue = $response->toArray();
            // Возвращаем идентификатор новой задачи
            return $newIssue['id'];

        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Ошибка при создании задачи: ".$exception->getMessage()."\n");
        }
    }

    /**
     * Добавление файлов к задаче YouTrack
     *
     * @param $issue_id
     * @param $file_name
     * @param $file
     * @return bool
     * @throws Exception
     */
    public function attachFileToIssue(int $issue_id, string $file_name, $file): bool {
        try {

            // Добавление заголовка для загрузки файлов
            $this->youtrack->withHeader('Content-Type', 'multipart/form-data');

            // Запрос к API на прикрепление файлов к задаче
            $response = $this->youtrack->post(
                "/issue/{$issue_id}/attachment", [],
                ['multipart' => [['name' => $file_name, 'contents' => $file]]]
            );

            // Код ответа
            $statusCode = $response->statusCode();

            // Возвращает статус
            return $statusCode == 201 ? true : false;

        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Заявка $issue_id: ошибка при добавлении файла: ".$exception->getMessage()."\n");
        }
    }

    /**
     * Добавление комментария к задаче YouTrack
     * Возвращает статус добавления комментария
     *
     * @param $issue_id
     * @param $comment
     * @return bool
     * @throws Exception
     */
    public function createIssueComment(int $issue_id, string $comment): bool {
        try {
            // Запрос к API для добавления комментария
            $response = $this->youtrack->post("/issue/{$issue_id}/execute", [
                'command' => 'comment',
                'comment' => $comment,
            ]);

            // Возвращает статус
            return $response->isSuccess();

        } catch (Exception $exception) {
            // Вывод ошибки в консоль
            print_r("Заявка $issue_id: ошибка при добавлении комментария: ".$exception->getMessage()."\n");
        }
    }

    /**
     * Обработка писем
     * Создает новые задачи YouTrack и добавляет комментарии к существующим на основе заголовка письма
     */
    public function processing() {

        // LOG
        print_r(date('H:i:s')." Старт обработки писем\n");

        // Получаем текущую дату
        $today = new DateTimeImmutable();

        // Условие поиска писем - за сегодня
        $since = new Since($today);

        // Получение писем
        $messages = $this->mailbox->getMessages($since);

        // Счетчики
        $issue_counter = 0;
        $comment_counter = 0;
        $file_counter = 0;
        $error_counter = 0;

        // Перебор писем
        foreach ($messages as $message) {
            // Если письмо помечено как прочитанное -> пропускаем обработку
            if ($message->isSeen()) continue;

            // Залоговок письма
            $subject = $message->getSubject();

            // Тело письма
            $text = $message->getBodyText();

            // Приложенные файлы
            $attachments = $message->getAttachments();

            // Если заголовок письма не содержит идентификатор задачи YouTrack
            if ( strpos($subject, 'HD-') === false ) {

                // Создание новой задачи
                $issue_id = $this->createIssue($subject, $text);

                // LOG
                print_r(date('H:i:s')." Создана новая задача YouTrack: $issue_id\n");
                // Инкремент счетчика задач
                $issue_counter++;
                // Пометить письмо как прочитанное
                $message->markAsSeen();

            } else {

                // Парсинг заголовка письма
                preg_match("/(HD-\d+) /", $subject, $matches);

                // Если получен идентификатор задачи
                if (isset($matches[0]) && !empty($matches[0])) {

                    // Идентификатор задачи
                    $issue_id = trim($matches[0]);

                    // Добавление комментария к задаче
                    $new_comment = $this->createIssueComment($issue_id, $text);

                    // Если комментарий добавлен
                    if ($new_comment) {
                        // LOG
                        print_r(date('H:i:s')." Добавлен комментарий к задаче YouTrack: $issue_id\n");
                        // Инкремент счетчика комментариев
                        $comment_counter++;
                        // Пометить письмо как прочитанное
                        $message->markAsSeen();
                    }
                } else {
                    // Лог ошибки
                    print_r(date('H:i:s') . " Неверный заголовок письма (Не получен ID задачи): $subject\n");
                    // Инкремент счетчика ошибок
                    $error_counter++;
                }
            }

            // Если получен идентификатор задачи и приложены файлы
            if (!empty($issue_id) && count($attachments)>0) {

                // Перебор файлов
                foreach ($attachments as $attachment) {

                    // Имя файла
                    $file_name = $attachment->getFilename();

                    // Путь к временному файлу
                    $file_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $file_name;

                    // Запись файла
                    file_put_contents($file_path, $attachment->getDecodedContent());

                    // Получение файла
                    $file = fopen($file_path, 'r');

                    // Прикрепление файла к задаче YouTrack
                    $new_file = $this->attachFileToIssue($issue_id, $file_name, $file);

                    // Если файл загружен
                    if ($new_file) {
                        // LOG
                        print_r(date('H:i:s')." Прикреплен файл '". $file_name ."' к задаче YouTrack: $issue_id\n");
                        // Инкремент счетчика файлов
                        $file_counter++;
                    }
                }
            }
        }

        // LOG
        print_r(date('H:i:s') .
            " Всего писем: ". count($messages) .
            ". Новых: ".($issue_counter + $comment_counter) .
            ". Создано заявок: ". $issue_counter .
            ". Добавлено комментариев: ". $comment_counter .
            ". Добавлено файлов: ". $file_counter .
            ". Ошибок: ". $error_counter .
            ". \n");
    }
}