<?php
/**-------------------------------------------------------
 *
 *   UpLink
 *   Copyright © 2024-2024 Тыльнов Сергей
 *
 *--------------------------------------------------------
 *
 *   Contact e-mail: sergey.tylnov@gmail.com
 *
 *   GNU General Public License, version 3:
 *   https://www.gnu.org/licenses/gpl-3.0.html
 *--------------------------------------------------------
 */
declare(strict_types=1);

namespace UpLink;

// Импортируем класс RuntimeException для обработки исключений
use RuntimeException;

/**
 * Класс AutoLoad, реализует механизм автозагрузки классов в PHP
 */
class AutoLoad
{
    /**
     * @var string $vendorDir директория с файлами классов
     */
    private string $vendorDir;
    /**
     * @var array $classMap массив с соответствием между именами классов и путями к файлам
     */
    private array $classMap = [];

    /**
     * @param string|null $vendorDir директорию с файлами классов
     * @param string|null $rootDir корневая директория
     */
    public function __construct(?string $vendorDir = null, ?string $rootDir = null)
    {
        // Используем оператор объединения с null-соответствием для задания значений по умолчанию для параметров
        $vendorDir ??= dirname(__DIR__);
        $rootDir ??= __DIR__;
        // Удаляем лишние символы разделителя директорий и добавляем их в конец путей
        $this->vendorDir = rtrim($vendorDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $rootDir = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        // Используем функцию version_compare для проверки версии PHP
        if (PHP_VERSION_ID <= 80200) {
            // Формируем сообщение об ошибке, если версия PHP ниже 8.2.0
            $issues = 'Для ваших зависимостей требуется версия PHP ">= 8.2.0". У вас работает ' . PHP_VERSION . '.';
            // Отправляем заголовок с кодом ошибки, если заголовки еще не отправлены
            if (!headers_sent()) {
                header('HTTP/1.1 500 Internal Server Error');
            }
            // Выводим сообщение об ошибке, если отображение ошибок не включено
            if (!ini_get('display_errors')) {
                // В зависимости от интерфейса PHP, выводим сообщение в стандартный поток ошибок или в браузер
                if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
                    fwrite(STDERR, 'Обнаружена проблема на вашей платформе:' . PHP_EOL . PHP_EOL . $issues . PHP_EOL . PHP_EOL);
                } else if (!headers_sent()) {
                    echo 'Обнаружена проблема на вашей платформе:' . PHP_EOL . PHP_EOL . str_replace('У вас работает ' . PHP_VERSION . '.', '', $issues) . PHP_EOL . PHP_EOL;
                }
            }
            // Генерируем ошибку пользователя с сообщением об ошибке
            trigger_error('Обнаружена проблема на вашей платформе: ' . $issues, E_USER_ERROR);
        }
        // Проверяем, существует ли файл с картой классов, и подключаем его, если он существует и возвращает массив
        if (file_exists($path = $rootDir . 'Autoload' . DIRECTORY_SEPARATOR . 'classMap.php') && is_array($map = require $path)) {
            // Присваиваем свойству classMap значение массива из файла
            $this->classMap = $map;
        }
        // Проверяем, существует ли файл с автозагрузкой от композера, и подключаем его, если он существует
        if (file_exists($file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php')) {
            require $file;
        }
        // Регистрируем метод loadClass в качестве функции автозагрузки с помощью функции spl_autoload_register
        $this->register([$this, 'loadClass',], true);
        // Проверяем, существует ли файл с дополнительными файлами для подключения, и подключаем их, если он существует и возвращает массив
        if (file_exists($path = $rootDir . 'Autoload' . DIRECTORY_SEPARATOR . 'files.php') && is_array($includeFiles = require $path)) {
            // Подключаем каждый файл из массива с помощью функции require_once
            foreach ($includeFiles as $file) {
                require_once $file;
            }
        }
    }

    /**
     * @param array $callable массив с функцией автозагрузки
     * @param bool $prepend значение, указывающее, нужно ли добавлять функцию в начало очереди автозагрузки
     * @return void
     */
    public function register(array $callable, bool $prepend = false): void
    {
        // Пытаемся зарегистрировать функцию автозагрузки с помощью функции spl_autoload_register
        if (spl_autoload_register($callable, true, $prepend) === false) {
            // Если регистрация не удалась, получаем имя функции с помощью функции is_callable
            is_callable($callable, false, $callableName);
            // Генерируем исключение с сообщением об ошибке
            throw new RuntimeException('Произошла ошибка при регистрации ' . $callableName . ' функции в качестве реализации метода __autoload()');

        }
    }

    /**
     * @param string $class имя класса для загрузки
     * @return bool
     */
    public function loadClass(string $class): bool
    {
        // Пытаемся найти файл с классом с помощью метода findFile
        if ($file = $this->findFile($class)) {
            // Если файл найден, подключаем его с помощью функции require_once
            require_once $file;
            // Возвращаем true, указывая, что класс загружен
            return true;
        }
        // Возвращаем false, указывая, что класс не найден
        return false;
    }

    /**
     * @param string $class имя класса для загрузки
     * @return string|false
     */
    public function findFile(string $class): string|false
    {
        // Проверяем, есть ли имя класса в массиве classMap в ключе file
        if (isset($this->classMap['file'][$class])) {
            // Если есть, возвращаем соответствующий путь к файлу
            return $this->classMap['file'][$class];
        }
        // Заменяем символы обратного слеша на символы разделителя директорий в имени класса
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        // Строим путь к файлу с классом в директории vendorDir
        if (file_exists($file = $this->vendorDir . $class . '.php')) {
            // Если файл существует, возвращаем его путь
            return $file;
        }
        // Проверяем, есть ли массив директорий в массиве classMap в ключе dir
        if (isset($this->classMap['dir'])) {
            // Если есть, перебираем каждую директорию в цикле
            foreach ($this->classMap['dir'] as $dir) {
                // Строим путь к файлу с классом в текущей директории
                if (file_exists($dir . $class . '.php')) {
                    // Если файл существует, возвращаем его путь
                    return $dir . $class . '.php';
                }
            }
        }
        // Возвращаем false, указывая, что файл с классом не найден
        return false;
    }
}
