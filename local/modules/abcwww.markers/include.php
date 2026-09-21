<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('abcwww.markers', [
    'Abcwww\Markers\Options' => 'lib/Config/options.php',
    'Abcwww\Markers\FieldManager' => 'lib/Application/fieldmanager.php',
    'Abcwww\Markers\Repository' => 'lib/Application/repository.php',
    'Abcwww\Markers\AdminInjector' => 'lib/Presentation/admininjector.php',
    'Abcwww\Markers\PublicInjector' => 'lib/Presentation/publicinjector.php',
    'Abcwww\Markers\ColorUserType' => 'lib/Infrastructure/colorusertype.php',
    'Abcwww\Markers\CacheManager' => 'lib/Infrastructure/cachemanager.php',
    'Abcwww\Markers\Sanitizer' => 'lib/Infrastructure/sanitizer.php',
]);

// Регистрируем UF-тип при подключении модуля (дополнение к RegisterModuleDependences).
if (class_exists('\Abcwww\Markers\FieldManager')) {
    \Abcwww\Markers\FieldManager::registerColorUserTypeRuntime();
}
