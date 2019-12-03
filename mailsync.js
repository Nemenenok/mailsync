/**
 * Процесс добавляет внешний идентификатор для заявки YouTrack,
 * а также изменяет опции внешней системы через запрос API
 */

// Адрес внешней АПИ
let EXTERNAL_API__URL = "";
// Ключ авторизации
let apiKey = "";

// Подключение сущностей YouTrack
let entities = require('@jetbrains/youtrack-scripting-api/entities');
let http = require('@jetbrains/youtrack-scripting-api/http');

exports.rule = entities.Issue.onChange({
    title: 'System change issue on create',
    guard: function(ctx) {
        // Действие при создании заявки
        return ctx.issue.becomesReported;
    },
    action: function(ctx) {
        // Текущая заявка
        let issue = ctx.issue;

        // Парсинг названия заявки (Для получения External_ID)
        let search = (issue.summary).match(/#(\d+)]/);

        // Если получен результат парсинга
        if (search && 1 in search) {

            // Внешний идентификатор
            let external_id = search[1];

            // Если получен внешний идентификатор и он является числом
            if (external_id !== undefined && /^-?[0-9]+$/.test(external_id)) {

                // Сохранение внешнего идентификатора задачи
                issue.External_ID = external_id;

                // Идентификатор задачи во внешней системе
                let api_issue_id = issue.External_ID;

                // Полный адрес запроса
                let url = EXTERNAL_API__URL + "issues/" + api_issue_id + ".json";

                // Сборка сообщения (JSON)
                let data = {};
                data.issue = {};
                // Настройка опций внешней системы
                data.issue.status_id = 1;
                data.issue.custom_fields = [{"value": issue.id,"id": 1}];
                // Сбор сообщения
                let message = JSON.stringify(data);

                // Построение HTTP запроса к внешнему API
                let connection = new http.Connection(url);

                // Добавление заголовка тип контента
                connection.addHeader({
                    name: 'Content-Type',
                    value: 'application/json'
                });
                // Добавление заголовка авторизации
                connection.addHeader({
                    name: 'X-API-Key',
                    value: apiKey
                });

                // Отправка запроса
                let response = connection.putSync('', null, message);

                // Не успешный запрос
                if (response && (!response.isSuccess && response.code != 200)) {
                    // Вывод ошибки в консоль
                    console.error('Ошибка обновления заявки. Заявка YouTrack: ' + issue.id +
                        '. Заявка внешней системы: ' + api_issue_id +
                        '. Код ответа: ' + response.code);
                }
                // Запрос отправлен успешно
                else {
                    // Вывод в консоль
                    console.log('Успешное обновление заявки. Заявка YouTrack: ' + issue.id +
                        '. Заявка внешней системы: ' + api_issue_id +
                        '. Код ответа: ' + response.code +
                        '. Отправленное сообщение: ' + message);
                }
            }
        }
    }
});