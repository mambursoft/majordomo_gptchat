<?php
/**
 * Сценарный API модуля gptchat.
 *
 * Подключается из сценария MajorDoMo:
 *   include_once(DIR_MODULES . 'gptchat/scenario.php');
 *   gptchat_scenario('gptchat.chat', array('message' => '...'));
 *
 * Все действия возвращают массив array('OK'=>bool, 'DATA'=>..., 'ERROR'=>string).
 */

if (!function_exists('gptchat_scenario')) {
    function gptchat_scenario($action, $params = array())
    {
        $result = array('OK' => false, 'DATA' => null, 'ERROR' => '');
        if (!is_array($params)) $params = array();

        $module = null;
        if (function_exists('getModule')) {
            $loaded = getModule('gptchat');
            if (is_object($loaded)) $module = $loaded;
        }
        if (!$module && isset($GLOBALS['gptchat_module']) && is_object($GLOBALS['gptchat_module'])) {
            $module = $GLOBALS['gptchat_module'];
        }
        if (!$module && class_exists('gptchat')) {
            $module = new gptchat();
        }
        if (!$module) {
            $result['ERROR'] = 'Не удалось загрузить модуль gptchat';
            return $result;
        }

        switch ($action) {
            case 'gptchat.activate':
                $id = isset($params['id']) ? (int)$params['id'] : 0;
                if (!$id) { $result['ERROR'] = 'Не задан id чата'; return $result; }
                $image = isset($params['image']) ? (string)$params['image'] : '';
                $module->activateChat($id, $params, $image);
                $result['OK'] = true;
                $result['DATA'] = array('id' => $id);
                return $result;

            case 'gptchat.chat':
                $message = isset($params['message']) ? (string)$params['message'] : '';
                if ($message === '') { $result['ERROR'] = 'Пустое сообщение'; return $result; }
                $model = isset($params['model']) && $params['model'] !== '' ? (string)$params['model'] : 'gpt-4o-mini';
                $instructions = isset($params['instructions']) ? (string)$params['instructions'] : 'Ты краткий помощник. Отвечай по делу.';
                if (method_exists($module, 'callProvider')) {
                    $answer = $module->callProvider($model, $instructions, $message, 0);
                } elseif (method_exists($module, 'openaigpt')) {
                    $answer = $module->openaigpt($model, $instructions, $message, 0);
                } else {
                    $result['ERROR'] = 'Метод запроса недоступен';
                    return $result;
                }
                $result['OK'] = true;
                $result['DATA'] = array('answer' => $answer, 'model' => $model);
                return $result;

            case 'gptchat.audio':
                if (!method_exists($module, 'audioProcess')) {
                    $result['ERROR'] = 'Поддержка аудио недоступна (PR #10)';
                    return $result;
                }
                $audio = $module->audioProcess(
                    isset($params['provider']) ? (string)$params['provider'] : 'openai',
                    isset($params['file']) ? (string)$params['file'] : '',
                    isset($params['task']) ? (string)$params['task'] : 'transcript',
                    isset($params['language']) ? (string)$params['language'] : 'uk'
                );
                $result['OK'] = $audio['error'] === '';
                $result['DATA'] = array('text' => $audio['text']);
                $result['ERROR'] = $audio['error'];
                return $result;

            case 'gptchat.image.analyze':
                if (!method_exists($module, 'imageAnalyze')) {
                    $result['ERROR'] = 'Поддержка изображений недоступна (PR #11)';
                    return $result;
                }
                $image = $module->imageAnalyze(
                    isset($params['provider']) ? (string)$params['provider'] : 'openai',
                    isset($params['file']) ? (string)$params['file'] : '',
                    isset($params['prompt']) ? (string)$params['prompt'] : 'Опиши изображение'
                );
                $result['OK'] = $image['error'] === '';
                $result['DATA'] = array('text' => $image['text']);
                $result['ERROR'] = $image['error'];
                return $result;

            case 'gptchat.image.generate':
                if (!method_exists($module, 'imageGenerate')) {
                    $result['ERROR'] = 'Поддержка изображений недоступна (PR #11)';
                    return $result;
                }
                $image = $module->imageGenerate(
                    isset($params['provider']) ? (string)$params['provider'] : 'openai',
                    isset($params['prompt']) ? (string)$params['prompt'] : '',
                    isset($params['width']) ? (int)$params['width'] : 1024,
                    isset($params['height']) ? (int)$params['height'] : 1024
                );
                $result['OK'] = $image['error'] === '';
                $result['DATA'] = array('url' => $image['url']);
                $result['ERROR'] = $image['error'];
                return $result;

            case 'gptchat.prompt.improve':
                $id = isset($params['id']) ? (int)$params['id'] : 0;
                if (!$id) { $result['ERROR'] = 'Не задан id чата'; return $result; }
                if (!method_exists($module, 'improvePromptOnce')) {
                    $result['ERROR'] = 'Улучшение недоступно (PR #9)';
                    return $result;
                }
                $rec = SQLSelectOne("SELECT * FROM gptchats WHERE ID=" . $id);
                if (!$rec['ID']) { $result['ERROR'] = 'Чат не найден'; return $result; }
                $improved = $module->improvePromptOnce($rec, $rec['INSTRUCTIONS'], $rec['PROMPT']);
                if ($improved !== $rec['PROMPT']) {
                    $rec['PROMPT'] = $improved;
                    SQLUpdate('gptchats', $rec);
                }
                $result['OK'] = true;
                $result['DATA'] = array('prompt' => $improved, 'id' => $id);
                return $result;
        }

        $result['ERROR'] = 'Неизвестное действие: ' . $action;
        return $result;
    }
}
