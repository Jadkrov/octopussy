<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();
use Bitrix\Main\Localization\Loc;

$arComponentDescription = array(
    'NAME' => Loc::getMessage('NAME'),
	'DESCRIPTION' => Loc::getMessage('DESCRIPTION'),
    'ICON' => '/images/news_list.gif',
    'PATH' => array(
		'ID' => 'octopussy',
    	'NAME' => Loc::getMessage('PATH_NAME'),
    	'SORT' => 10,
    ),
);
