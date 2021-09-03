<?php

namespace Onizamov\ErpExchange;

class Logger
{
    /** @var $instances - экземпляр одиночки */
    private static $instances = [];

    protected function __construct()
    {
        $sql = LogTable::getEntity()->compileDbTableStructureDump()[0];
        $sql = str_replace("CREATE TABLE", "CREATE TABLE IF NOT EXISTS", $sql);
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->query($sql);
    }

    /**
     * Запрет Клонирование и десериализация.
     */
    protected function __clone()
    {
    }

    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Метод, используемый для получения экземпляра Одиночки.
     */
    public static function getInstance()
    {
        $subclass = static::class;
        if (!isset(self::$instances[$subclass])) {
            self::$instances[$subclass] = new static();
        }
        return self::$instances[$subclass];
    }

    /**
     * Пишем запись в журнале в открытый файловый ресурс.
     */
    private function writeLog(string $url, string $requestType, string $request): void
    {
        $date = new \Bitrix\Main\Type\DateTime();
        LogTable::add(
            [
                'UF_URL'          => $url,
                'UF_REQUEST_TYPE' => $requestType,
                'UF_REQUEST'      => $request,
                'UF_DATE_CREATE'  => $date,
            ]
        );
    }

    /**
     * Просто удобный ярлык для уменьшения объёма кода, необходимого для
     * регистрации сообщений из клиентского кода.
     */
    public static function log(string $url, string $requestType, string $request): void
    {
        $logger = static::getInstance();
        $logger->writeLog($url, $requestType, $request);
    }
}
