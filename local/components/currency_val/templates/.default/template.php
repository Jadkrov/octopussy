<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
?>
<div>
<?php 
foreach ($arResult as $arCurrency) {?>

	<span title="<?=$arCurrency['TIP']?> <?=$arCurrency['NOMINAL']?>:1"><?=$arCurrency['SYMBOL']?> - <?=$arCurrency['VALUE']?></span></br>

<?php 
}
?>
</div>
