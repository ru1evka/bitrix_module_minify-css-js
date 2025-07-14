<?php
namespace Advantika\Optimazer;
// Подключение классов вручную
require_once __DIR__ . '/lib/minify/src/Minify.php';
require_once __DIR__ . '/lib/minify/src/CSS.php';
require_once __DIR__ . '/lib/minify/src/JS.php';
require_once __DIR__ . '/lib/converter/src/ConverterInterface.php';
require_once __DIR__ . '/lib/converter/src/Converter.php';

// Использование классов
use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\IO\Directory;
use Bitrix\Main;

const ID = 'advantika.optimazer';
const APP = __DIR__ . '/';
const LIB = APP . 'lib/';

define(
	__NAMESPACE__ . '\\CONFIG_DIR',
	$_SERVER['DOCUMENT_ROOT'] . '/local/optimazer_config'
);
const CONFIG = CONFIG_DIR . '/.' . ID . '.';

const IGNORED_TEMPLATES = [
	'bitrix24' => 1,
	'desktop_app' => 1,
	'learning' => 1,
	'login' => 1,
	'mail_user' => 1,
	'mobile_app' => 1,
	'pub' => 1,
];

require LIB . 'encoding/include.php';

function AppendValues($data, $n, $v)
{
	yield from $data;
	for ($i = 0; $i < $n; ++$i)
	{
		yield  $v;
	}
}

function Options($siteId)
{
	$fname = OptionsFilename('options', $siteId);
	return is_readable($fname) ?
		include $fname : [
			'active_minify_css' => 'N',
			'active_minify_js' => 'N',
		];
}

function OptionsUpdate($data, $siteId)
{
    $fname = OptionsFilename('options', $siteId);
	if (!is_dir(CONFIG_DIR))
	{
		Directory::createDirectory(CONFIG_DIR);
	}
	\Encoding\PhpArray\Write($fname, [
		'active_minify_css' => $data['active_minify_css'],
		'active_minify_js' => $data['active_minify_js'],
	]);
}

function OptionsFilename($group, $siteId, $ext = '.php')
{
	return CONFIG . $group . '.' . $siteId . $ext;
}

/**
 * Метод возвращает список файлов, подлежащих минификации
 */
function minify($siteId){
    // Получаем root каталога сайта
    $siteRoot = \Bitrix\Main\Application::getDocumentRoot();

    //Получаем название текушего шаблона сайта
    $rsSite = \CSite::GetByID($siteId);
    if ($arSite = $rsSite->Fetch()) {
        // Список шаблонов сайта
        $arTemplates = \CSite::GetTemplateList($siteId);
        while ($arTemplate = $arTemplates->Fetch()) {
            $templateteName = $arTemplate["TEMPLATE"];
        }
    }

    // Получить список нужных директорий
    $searchDirectories = [
        "$siteRoot/local/$templateteName",
        "$siteRoot/bitrix/templates/$templateteName",
        "$siteRoot/local/components",
    ];

    // Получаем все файлы
    $allFiles = [];
    set_time_limit(300);
    foreach ($searchDirectories as $dir) {
        if(is_dir($dir)){
            foreach (new \RecursiveIteratorIterator(
                         new \RecursiveDirectoryIterator($dir),
                         \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
                if ($file->isFile() &&
                    (stripos($file->getBasename(), '.css') !== false ||
                        stripos($file->getBasename(), '.js') !== false)) {
                    $allFiles[] = $file->getRealPath();
                }
            }
        }
    }
    processFilesInChunks($allFiles, $siteId);
}
function processFilesInChunks($files, $siteId, int $chunkSize = 30) {
    // Чанкинг массива файлов на кусочки размером chunkSize
    $chunks = array_chunk($files, $chunkSize);

    foreach ($chunks as $index => $chunk) {
        // Запускаем минификацию текущего чанка
        startMinification($chunk, $siteId);

        // Делаем короткую паузу между партиями
        sleep(2); // Пауза на 2 секунды
    }
    \CAdminMessage::ShowNote(
        "Все файлы минифицированы успешно.\n"
    );
}

/**
 * Производит минификацию выбранного набора файлов
 *
 * @param array $files Массив файлов для минификации
 */
function startMinification(array $files, $siteId) {
    //Получаем сохраненные настройки из модуля
    $currentOptions = Options($siteId);
    foreach ($files as $i => $filePath) {
        $baseName = basename($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $outputPath = dirname($filePath) . DIRECTORY_SEPARATOR .
            pathinfo($filePath, PATHINFO_FILENAME) . '.min.' .
            pathinfo($filePath, PATHINFO_EXTENSION);
        // Проверяем, не является ли файл уже минифицированным
        // Если файл не минифицирован, продолжаем нормальную обработку
        if (str_ends_with($baseName, '.min.' . $extension)) {
            // Проверяем наличие оригинального файла
            $originalFilePath = rtrim($filePath, '.min.');
            if (file_exists($originalFilePath)) {
                // Обновляем минифицированную версию из оригинального файла
                if ($extension === 'css' && $currentOptions['active_minify_css'] == 'Y') {
                    minimizeCss($originalFilePath, $filePath);
                } elseif ($extension === 'js' && $currentOptions['active_minify_js'] == 'Y') {
                    simpleJsMinify($originalFilePath, $filePath);
                }
            }
        } else if ($extension === 'css' && $currentOptions['active_minify_css'] == 'Y') {
            minimizeCss($filePath, $outputPath);
        } elseif ($extension === 'js' && $currentOptions['active_minify_js'] == 'Y') {
            simpleJsMinify($filePath, $outputPath);
        }
    }
    AddMessage2Log("Минификация выполнена успешно.", "myminificator");
}

/**
 * Минификация CSS-файла
 *
 * @param string $inputFile Входящий файл
 * @param string $outputFile Выходящий файл
 */
function minimizeCss($inputFile, $outputFile) {
    $css = new CSS(file_get_contents($inputFile));
    file_put_contents($outputFile, $css->minify());
}

/**
 * Минификация JS-файла
 *
 * @param string $inputFile Входящий файл
 * @param string $outputFile Выходящий файл
 */
function simpleJsMinify($inputFile, $outputFile) {
    $js = new JS(file_get_contents($inputFile));
    file_put_contents($outputFile, $js->minify());
}

function init()
{
	Loc::loadMessages(__FILE__);
	AddEventHandler('main', 'OnAdminListDisplay', function (&$list) {
		if ($list->table_id != 'tbl_site') {return;}
		\CJSCore::init('sidepanel');
		$urlSettings = '/bitrix/admin/settings.php?lang=ru&mid='
			. ID . '&IFRAME=Y';
		foreach ($list->aRows as &$row) {
			$url = $urlSettings . '&advantika_optimazer_action=settings&site_id='
				. $row->arRes['LID'];
			$row->aActions[] = [
				'TEXT' => Loc::getMessage('ADVANTIKA_OPTIMAZER_MODULE_NAME')
					. Loc::getMessage('ADVANTIKA_OPTIMAZER_MENU_ITEM_SETTINGS'),
				'ACTION' => 'BX.SidePanel? BX.SidePanel.Instance.open("' . $url . '") : (location.href="' . $url . '");',
			];
			$url = $urlSettings . '&advantika_optimazer_action=optimazer&site_id='
				. $row->arRes['LID'];
		}
	});
}

init();
