<?php
/**
 * @author Petra Barus <petra@urbanindo.com>
 * @author Diveev Alexey <alexz29@yandex.ru>
 */

namespace UrbanIndo\Yii2\JsonFileTarget;

use Yii;
use yii\base\Application;
use yii\base\InvalidArgumentException;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\helpers\VarDumper;
use yii\log\FileTarget;
use yii\helpers\ArrayHelper;
use yii\log\Logger;
use yii\web\Request;
use yii\web\Session;
use yii\web\User;

/**
 * JsonLogFileTarget stores the log file as single line JSON.
 * @author Petra Barus <petra@urbanindo.com>
 */
class JsonFileTarget extends FileTarget
{
    public $maskVars=[];
    /**
     * Should include context in log.
     * @var bool
     */
    public $includeContext = true;

    /**
     * Set this to true when the message in json format instead of object/array when logging.
     * e.g.
     * \Yii::info(\yii\helpers\Json::encode(['class' => $class,
     * 'attributes' => $attributes]),$this->category);
     *
     * @var bool
     */
    public $decodeMessage = true;

    /**
     * @param mixed $log
     * @return string The formatted message
     */
    public function formatMessage($log): string
    {
        list($message, $level, $category, $timestamp) = $log;
        $traces = self::formatTracesIfExists($log);

        $text = $this->parseMessage($message);
        $basicInfo = [
            'timestamp' => self::formatTime($timestamp),
            'level' => Logger::getLevelName($level),
            'category' => $category,
            'traces' => $traces,
            'message' => $text,
        ];
        $appInfo = self::getAppInfo($log);
        $formatted = array_merge($basicInfo, $appInfo);

        if ($this->includeContext) {
            $formatted = array_merge($formatted, [
                'context' => ArrayHelper::getValue($log, 'context')
            ]);
        }

        return Json::encode($this->mask($formatted));
    }

    /**
     * @param mixed $message
     * @return array|mixed|string
     */
    protected function parseMessage($message)
    {
        if (is_array($message)) {
            return $message;
        }

        if ($message instanceof \Exception) {
            $message = (string)$message->getMessage();
        }

        if (!is_string($message)) {
            return VarDumper::export($message);
        }

        if (!$this->decodeMessage) {
            return $message;
        }

        try {
            return Json::decode($message, true);
        } catch (InvalidArgumentException $e) {
            return $message;
        }
    }

    /**
     * @param $timestamp
     * @return string
     */
    protected static function formatTime($timestamp): string
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @param $log
     * @return array
     */
    protected static function formatTracesIfExists($log): array
    {
        $traces = ArrayHelper::getValue($log, 4, []);
        $formattedTraces = array_map(function ($trace) {
            return "in {$trace['file']}:{$trace['line']}";
        }, $traces);

        $message = ArrayHelper::getValue($log, 0);
        if ($message instanceof \Exception) {
            $tracesFromException = explode("\n", $message->getTraceAsString());
            $formattedTraces = array_merge($formattedTraces, $tracesFromException);
        }
        return $formattedTraces;
    }

    /**
     * @param $message
     * @return array
     */
    protected function getAppInfo($message): array
    {
        if ($this->prefix !== null) {
            return call_user_func($this->prefix, $message);
        }

        $app = Yii::$app;
        if ($app === null) {
            return [];
        }

        $ip = self::getUserIP($app);
        $sessionId = self::getSessionId($app);
        $userId = self::getUserId($app);

        return [
            'ip' => $ip,
            'userId' => $userId,
            'sessionId' => $sessionId,
        ];
    }

    /**
     * @param Application $app
     * @return string
     */
    private static function getUserIP(Application $app): string
    {
        $request = $app->getRequest();
        if ($request instanceof Request) {
            return $request->getUserIP();
        }
        return '-';
    }

    /**
     * @param Application $app
     * @return string
     */
    private static function getSessionId(Application $app): string
    {
        try {
            /** @var Session $session */
            $session = $app->get('session', false);
        } catch (InvalidConfigException $ex) {
            return '-';
        }
        if ($session === null) {
            return '-';
        }

        if (!$session->getIsActive()) {
            return '-';
        }
        return $session->getId();
    }

    /**
     * @param Application $app
     * @return string
     */
    private static function getUserId(Application $app): string
    {
        try {
            /** @var User $user */
            $user = $app->get('user', false);
        } catch (InvalidConfigException $ex) {
            return '-';
        }

        if ($user === null || !$user instanceof User) {
            return '-';
        }
        try {
            $identity = $user->getIdentity(false);
        } catch (\Throwable $ex) {
            return '-';
        }
        if ($identity === null) {
            return '-';
        }
        return $identity->getId();
    }

    /**
     * @return array
     */
    protected function getContextMessage(): array
    {
        return ArrayHelper::filter($GLOBALS, $this->logVars);
    }

    /**
     * Function put mask on item in array
     *
     * @param $message
     * @return mixed
     */
    protected function mask($message)
    {
        foreach ($this->maskVars as $var) {
            if (ArrayHelper::getValue($message, $var) !== null) {
                ArrayHelper::setValue($message, $var, '***');
            }
        }

        return $message;
    }

    /**
     * @param array $messages
     * @param bool $final
     * @throws InvalidConfigException
     * @throws \yii\log\LogRuntimeException
     */
    public function collect($messages, $final)
    {
        $this->messages = array_merge(
            $this->messages,
            $this->filterMessages($messages, $this->getLevels(), $this->categories, $this->except)
        );

        $count = count($this->messages);

        if ($count > 0 && ($final || $this->exportInterval > 0 && $count >= $this->exportInterval)) {
            if ($this->includeContext) {
                if (!empty(($context = $this->getContextMessage()))) {
                    foreach ($this->messages as &$message) {
                        $message['context'] = $context;
                    }
                }
            }


            // set exportInterval to 0 to avoid triggering export again while exporting
            $oldExportInterval = $this->exportInterval;
            $this->exportInterval = 0;
            $this->export();
            $this->exportInterval = $oldExportInterval;
            $this->messages = [];
        }
    }
}
