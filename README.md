# Библиотека для проверки кода

[![Build Status](https://travis-ci.org/codedokode/source-analyzer.svg?branch=master)](https://travis-ci.org/codedokode/source-analyzer)

Анализирует исходный код (например, в задаче про список студентов) для поиска там ошибок.

## Установка

- установите зависимости: `php composer.phar install`
- скачайте мануал PHP в формате .html.gz отсюда: http://php.net/download-docs.php и поместите в assets/php_manual_en.html.gz (нужно для создания списка встроенных в PHP функций и типов их аргументов, без этого часть ошибок не будет находиться)
- создайте индекс PHP-функций командой `php cli/type-hint-indexer.php`
- если вы хотите определять изменения в сторонних библиотеках, скачайте версии библиотек jquery (только js-файлы) и разместите их по путям вроде assets/libraries/jquery/jquery-1.11.2.js
- если вы хотите определять изменения в сторонних библиотеках, скачайте zip-архивы библиотеки bootstrap и разместите их по путям вроде assets/libraries/bootstrap-3.3.4-dist.zip
- создайте индекс внешних библиотек командой `php cli/library-indexer.php`

## Тестирование

Для запуска юнит-тестов используйте phpunit 5.7 (он указан в composer.json):

    php phpunit-5.7.phar


## Анализ кода задачи про список студентов

Чтобы проверить код решения задачи про студентов, используйте команду

```sh
php cli/student-list-check.php /path/to/project
```

## Модификатор HTML

Модификатор HTML помогает проверить стабильность HTML верстки путем уменьшения или увеличения объема текстов и количества пунктов меню.

Использование: 

    script number menu-selector menu-selector  menu-selector ...

где number задает коэффициент, во сколько раз надо увеличить объем текста (например, "2" - в 2 раза), а menu-selector - это CSS-путь к меню.

Пример: 

    $ echo "<div>Hello</div><li>1</li><li>2</li>" | php cli/minimizer.php 2 'li' 
    <!DOCTYPE html>
    <html><div>Hello If I …</div><li>1 M…</li><li>2 Y…</li><li>2 Y…</li><li>2 Y…</li>
    </html>

    $ echo "<div>Hello</div><li>1</li><li>2</li>" | php cli/minimizer.php 0.5 'li' 
    <!DOCTYPE html>
    <html><div>Hel…</div><li>2</li>
    </html>

    $ php minimizer.php 3 '#imagecontainer > a' '.header nav > a' '.social > a' '.portfolio > .button-container' < index.html > index-3.html
