# Сценарный API модуля gptchat

Один вызов из сценария MajorDoMo:

```php
include_once(DIR_MODULES . 'gptchat/scenario.php');
$r = gptchat_scenario('gptchat.chat', array('message' => 'Кратко объясни погоду'));
if ($r['OK']) $answer = $r['DATA']['answer'];
```

Возвращается всегда `array('OK'=>bool, 'DATA'=>..., 'ERROR'=>string)`.

## Действия

| Действие | Параметры | Результат | Требует PR |
|---|---|---|---|
| `gptchat.activate` | `id`, опционально `VALUE`/`image` | запуск существующего чата | — |
| `gptchat.chat` | `message`, `model`, `instructions` | `answer`, `model` | #4 (для новых моделей) |
| `gptchat.audio` | `file`, `provider`, `task`, `language` | `text` | #10 |
| `gptchat.image.analyze` | `file`, `prompt`, `provider` | `text` | #11 |
| `gptchat.image.generate` | `prompt`, `width`, `height`, `provider` | `url` | #11 |
| `gptchat.prompt.improve` | `id` | `prompt` (сохранён в БД) | #9 |

## Почему отдельный файл

`scenario.php` — новый файл, он не меняет ни один существующий метод. Поэтому PR можно принять в любом порядке, а конфликты параллельных PR #7/#9/#10/#11 не усугубляются: каждая проверка использует `method_exists()` и понятно сообщает, если нужный PR ещё не смержен.

## Пример бизнес-процесса

```php
include_once(DIR_MODULES . 'gptchat/scenario.php');

// 1. Распознать голосовое сообщение с датчика
$a = gptchat_scenario('gptchat.audio', array(
    'file' => 'doorbell_2026.mp3',
    'provider' => 'elevenlabs',
    'task' => 'translate',
    'language' => 'uk'
));

// 2. Передать текст в чат и получить решение
if ($a['OK']) {
    $c = gptchat_scenario('gptchat.chat', array(
        'message' => 'Кто у двери? ' . $a['DATA']['text'],
        'model' => 'gpt-4o-mini'
    ));
    if ($c['OK']) sg('DoorDecision', $c['DATA']['answer']);
}
```
