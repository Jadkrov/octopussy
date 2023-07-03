<?php
use Bitrix\Main\Result;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\IO\IoException;
use Bitrix\Main\IO\FileOpenException;

//use Bitrix;
class CurrencyVal extends CBitrixComponent
{

	/**
	 * Path file to server
	 * @var string
	 */
	protected $xml_path = '/bitrix/cache/currency_rates.xml';

	protected $xml_cbr = 'http://www.cbr.ru/scripts/XML_daily.asp';

	protected $xml_xsd = 'https://www.cbr.ru/StaticHtml/File/92172/ValCurs.xsd';

	protected $xml = null;

	protected $rule_add_percent = null;

	public function __construct($component = null)
	{
		parent::__construct($component);
		$this->result = new Result();

		if (version_compare(phpversion(), '8.0.0', '<')) {
			die(ShowError(Loc::getMessage('ERROR_PHP_VERSION')));
		}

		if (!defined('LIBXML_VERSION')) {
			die(ShowError(Loc::getMessage('ERROR_LIBXML_NOT_INSTALLED')));
			;
		}
	}

	public function onPrepareComponentParams($arParams): array
	{
		$arIncreaseRules = [];
		$arRulesGroups = [];
		$arUserGroups = [];
		if (isset($arParams['GROUPS']) && !empty($arParams['GROUPS'])) {
			foreach ($arParams as $param => $val) {
				if (str_starts_with($param, 'INCREASE')) {
					$groupId = str_replace('INCREASE_', '', $param);
					if (in_array($groupId, $arParams['GROUPS'])) {
						$arIncreaseRules[(int) $groupId] = (int) $val;
						$arRulesGroups[] = $groupId;
					}
				}
			}
		}

		if (!empty($arRulesGroups)) {
			global $USER;
			$arUserGroups = CUser::GetUserGroup($USER->GetID());
		}

		$res = null;
		$res = array_intersect($arUserGroups, $arRulesGroups);
		//All users; check unauthorize | or get rang group 
		if ($res) {
			if (1 == count($res) && 2 == current($res)) {
				$this->rule_add_percent = (int) $arIncreaseRules[current($res)];
			} else {
				$this->rule_add_percent = (int) $arIncreaseRules[current($res)];
			}
		}
		return $arParams;
	}

	public function executeComponent()
	{
		$this->setXmlFile(Application::getDocumentRoot() . $this->xml_path);
		$this->xml = XMLReader::open(Application::getDocumentRoot() . $this->xml_path);

		try {
			$this->checkFile($this->xml);

			$result = [];
			$doc = new DOMDocument();
			while ($this->xml->read()) {
				if ($this->xml->name == 'Valute' && $this->xml->nodeType == XMLReader::ELEMENT) {

					$id = $this->xml->getAttribute('ID');
					if (!in_array($id, $this->arParams['CURRENCY'])) {
						continue;
					}

					$node = new SimpleXMLElement($this->xml->readOuterXML());
					$node = simplexml_import_dom($doc->importNode($this->xml->expand(), true));

					$result[(string) $node->CharCode] = [
						'NOMINAL' => (int) $node->Nominal, 
						'VALUE' => $this->formatDisplay($this->getCurrencyVal($node->Value)), 
						'TIP' => (string) $node->Name,
						'SYMBOL' => $this->getSymbol((string) $node->CharCode)
					];
				}
			}

			$this->arResult = $result;
			$this->includeComponentTemplate();
		}
		catch (Exception $e) {
			echo ShowError($e->getMessage());
		}
	}

	/**
	 * Check file and upload
	 * @param string $path
	 */
	protected function setXmlFile(string $path): void
	{
		if (!file_exists($path)) {
			$this->updateFile();
		} else {
			$fileModTime = DateTime::createFromTimestamp(filemtime($path));
			$objDateTime = new DateTime();

			if ($fileModTime->add("1 day")->format('Y-m-d') < $objDateTime->format('Y-m-d')) {
				//FIXME: Add holiday days
				if (1 < $fileModTime->add("-1 day")->format('N') && 5 > $fileModTime->add("-1 day")->format('N')) {
					$this->updateFile();
				}
			}
		}
	}

	/**
	 * Upload file currency val
	 */
	protected function updateFile(): void
	{
		file_put_contents(Application::getDocumentRoot() . $this->xml_path, file_get_contents($this->xml_cbr));
	}

	/**
	 * Validate xmlData
	 * @param XMLReader $xml
	 * @throws FileOpenException
	 * @throws IoException
	 */
	protected function checkFile(XMLReader $xml): void
	{
		if (!$xml) throw new FileOpenException($xml);

		if (20620 <= LIBXML_VERSION) {
			if (!$xml->setSchema($this->xml_xsd)) throw new IoException(Loc::getMessage('ERROR_XML_NOT_XSD'));
		}
		if (!$xml->isValid()) throw new IoException(Loc::getMessage('ERROR_XML_NOT_VALID'));
	}

	/**
	 * Sum price percent
	 * @param float $price
	 * @param int $percent
	 * @return string
	 */
	protected function addPercent(float $price, int $percent): string
	{
		return $price + ($price * $percent / 100);
	}

	/**
	 * Format string currency value from float. Check and apply rule  
	 * @param string $val
	 * @return float
	 */
	protected function getCurrencyVal(string $val): float
	{
		$result = floatval(str_replace(',', '.', (string) $val));
		if ($this->rule_add_percent) {
			$result = $this->addPercent($result, $this->rule_add_percent);
		}
		return $result;
	}

	/**
	 * Format price for user
	 * @param float $val
	 * @return string
	 */
	protected function formatDisplay(float $val): string
	{
		return number_format($val, 4, ',', '');
	}

	/**
	 * Get symbol for char currency
	 * @param string $currency
	 * @return string
	 */
	protected function getSymbol(string $currency): string
	{
		$fmt = new NumberFormatter(LANGUAGE_ID . "@currency=$currency", NumberFormatter::CURRENCY);
		$symbol = $fmt->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
		return $symbol;
	}
}