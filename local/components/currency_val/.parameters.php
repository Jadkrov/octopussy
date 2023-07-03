<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Localization\Loc;

if (version_compare(phpversion(), '8.0.0', '<')) {
	die(ShowError(Loc::getMessage('ERROR_PHP_VERSION')));
}

if (!defined('LIBXML_VERSION')) {
	die(ShowError(Loc::getMessage('ERROR_LIBXML_NOT_INSTALLED')));
	;
}

$list = 'https://www.cbr.ru/scripts/XML_val.asp';
if (!$xml = XMLReader::open($list)) {
	die(ShowError(Loc::getMessage('ERROR_XML_NOT_LOAD')));
}

if (20620 <= LIBXML_VERSION) {
	if (!$xml->setSchema('https://www.cbr.ru/StaticHtml/File/92172/Valuta.xsd')) {
		die(ShowError(Loc::getMessage('ERROR_XML_NOT_XSD')));
	}
}

if (!$xml->isValid()) {
	die(ShowError(Loc::getMessage('ERROR_XML_NOT_VALID')));
}
/**
 * List id currency and name
 * @var array $arCur
 */
$arCur = [];
$doc = new DOMDocument();
while ($xml->read()) {
	if ($xml->name == 'Item' && $xml->nodeType == XMLReader::ELEMENT) {
		$id = $xml->getAttribute('ID');
		$node = new SimpleXMLElement($xml->readOuterXML());
		$node = simplexml_import_dom($doc->importNode($xml->expand(), true));
		if ('ru' == LANGUAGE_ID) {
			$arCur[$id] = (string) $node->Name;
		} else {
			$arCur[$id] = (string) $node->EngName;
		}
	}
}

/**
 * List id group and name
 * @var array $arGroups
 */
$arGroups = [];
$resultGroup = \Bitrix\Main\GroupTable::getList([
	'select'  => ['NAME','ID'],
	'cache'   => ['ttl' => 86400]
]);
while ($arGroup = $resultGroup->fetch()) {
	$arGroups[$arGroup['ID']] = $arGroup['NAME'];
}

/**
 * Increase exchange rates for group
 * @var array $arAdditionalParameters
 */
$arAdditionalParameters = [];
// If set group for increase, create field to indicate a percentage
if (isset($arCurrentValues['GROUPS']) && !empty($arCurrentValues['GROUPS'])) {
	foreach ($arCurrentValues['GROUPS'] as $idGroup) {
		$arAdditionalParameters['INCREASE_' . $idGroup] = [
			'PARENT'	=> 'ADDITIONAL_SETTINGS', 
			'TYPE'		=> 'STRING', 
			'NAME' 		=> Loc::getMessage('PR_INCREASE') . ' ' . $arGroups[$idGroup]
		];
	}
}

$arBaseParameters = [
	'CURRENCY' => [
		'PARENT' 	=> 'BASE',
		'TYPE'		=> 'LIST',
		'MULTIPLE'	=> 'Y',
		'NAME' 		=> Loc::getMessage('PR_CURRENCY'),
		'VALUES' 	=> $arCur,
	],
	'GROUPS' => [
		'PARENT' 	=> 'BASE',
		'TYPE'		=> 'LIST',
		'MULTIPLE'	=> 'Y',
		'REFRESH'	=> 'Y',
		'NAME' 		=> Loc::getMessage('PR_GROUPS'),
		'VALUES' 	=> $arGroups,
	]
];

$arComponentParameters['PARAMETERS'] = array_merge($arBaseParameters,$arAdditionalParameters);
