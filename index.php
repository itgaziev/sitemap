<?
//Отключаем статистику Bitrix
define("NO_KEEP_STATISTIC", true);
//Подключаем движок
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require(__DIR__ . '/classes.php');
$path = pathinfo($_SERVER['REQUEST_URI']);

// echo '<pre>'; print_r($uri); echo '</pre>';
$sitemap = new SitemapCostum($path);