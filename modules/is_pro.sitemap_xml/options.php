<?

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Text\HtmlFilter;

global $USER;


if (!$USER->IsAdmin()) {
	return;
}

if (file_exists(__DIR__ . "/install/module.cfg.php")) {
	include(__DIR__ . "/install/module.cfg.php");
};

if (!Loader::includeModule($arModuleCfg['MODULE_ID'])) {
	return;
};
if (!Loader::includeModule("iblock")) {
	return;
};
Loc::loadMessages(__FILE__);

// получить массив сайтов [lid => name, ...]
$res = \Bitrix\Main\SiteTable::getList();
$siteIds = [];
$arSite = [];

while ($site = $res->fetch()) {
	$siteIds[$site["LID"]] = $site["NAME"];
	$arSite[$site["LID"]] = $site;
}

$currentUrl = $APPLICATION->GetCurPage() . '?mid=' . urlencode($mid) . '&amp;lang=' . LANGUAGE_ID;
$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$doc_root = \Bitrix\Main\Application::getDocumentRoot();
$url_module = str_replace($doc_root, '', __DIR__);



$ok_message = '';
$eeror_message = '';

function checkOption(string $option_name, $option)
{
	/* Тут проверяем значение настроек, если есть ошибка, то возвращаем ее текст, иначе вернем true */
	return true;
}


$saveOption = false;

if (check_bitrix_sessid()) {
	$save = $request->getpost('save');
	if ($save == 'save') {
		$saveOption = true;
	}
}

function getOptionsPerSite($arModuleCfg, $arSite) {
	$sId = $arSite['ID'];
	/* Получим все Инфоблоки */
	$resBlocks = CIBlock::GetList(
		[
			'ID',
			'NAME'
		],
		[
			'SITE_ID' => $sId,
			'ACTIVE'=>'Y',
		],
		true
	);

	$options_list = $arModuleCfg['options_list'];

	while($ar_res = $resBlocks->Fetch()) {
		$arrObjects = ['SECTION', 'ELEMENT'];
		$options_list['IBLOCK_'.$ar_res['ID']] = [
			'type' => 'checkbox',
			'NAME' => Loc::getMessage('ISPRO_SITEMAP_XML_IBLOCK',['%NAME%' => $ar_res['NAME']]),
			'default' => 'N',
			'is_iblock' => $ar_res['ID'],
			'is_iblock_name'=> 'Y'
		];

		foreach ($arrObjects as $object) {
			$options_list['IBLOCK_'.$ar_res['ID'].'_'.$object] = [
					'type' => 'checkbox',
					'NAME' => Loc::getMessage('ISPRO_SITEMAP_XML_IBLOCK_'.$object),
					'default' => 'N',
					'is_iblock' => $ar_res['ID']
			];
			$options_list['IBLOCK_'.$ar_res['ID'].'_'.$object.'_PRIORITY'] = [
					'type' => 'text',
					'NAME' => Loc::getMessage('ISPRO_SITEMAP_XML_IBLOCK_'.$object.'_PRIORITY'),
					'default' => '1',
					'is_iblock' => $ar_res['ID']
			];
			$options_list['IBLOCK_'.$ar_res['ID'].'_'.$object.'_LASTMOD'] = [
					'type' => 'select',
					'NAME' => Loc::getMessage('ISPRO_SITEMAP_XML_IBLOCK_'.$object.'_LASTMOD'),
					'values' => [
						'lastmod',
						'Y-m-d',
						'Y-m-01'
					],
					'default' => 'lastmod',
					'is_iblock' => $ar_res['ID']
			];
			$options_list['IBLOCK_'.$ar_res['ID'].'_'.$object.'_CHANGEFREQ'] = [
					'type' => 'select',
					'NAME' => Loc::getMessage('ISPRO_SITEMAP_XML_IBLOCK_'.$object.'_CHANGEFREQ'),
					'values' => [
						'always',
						'hourly',
						'daily',
						'weekly',
						'monthly',
						'yearly',
						'never'
					],
					'default' => 'monthly',
					'is_iblock' => $ar_res['ID']
			];
		}
	}
	/*
	$io = CBXVirtualIo::GetInstance();
	$dir = $io->GetDirectory($io->RelativeToAbsolutePath( $arSite['DIR']) );
	$arChildren = $dir->GetChildren();
	foreach ($arChildren as $child)
	{
		$NAME = $child->GetName();
		$options_list['DIRECTORY_'.] = [
			'type' => 'checkbox',
			'NAME' => Loc::getMessage('ISPRO_SITEMAP_XML_DIRECTORY',['%NAME%' => $ar_res['NAME']]),
			'default' => 'N',
			'is_iblock' => $ar_res['ID'],
			'is_iblock_name'=> 'Y'
		];
	   print $child->GetName();
	}
	*/
	foreach ($options_list as $option_name => $arOption) {
		if (!isset($options_list[$option_name]['default'])) {
			$options_list[$option_name]['default'] = '';
		}
	}
	return $options_list;
}


foreach ($siteIds as $sId => $sName) {

	$setDefault = false;
	$options_list = getOptionsPerSite($arModuleCfg, $arSite[$sId]);


	$isConfigurated =
	\Bitrix\Main\Config\Option::get($arModuleCfg['MODULE_ID'], 'IS_CONFIGURATED', 'N', $sId);
	if ($isConfigurated != 'Y') {
		\Bitrix\Main\Config\Option::set($arModuleCfg['MODULE_ID'], 'IS_CONFIGURATED', 'Y', $sId);
		$setDefault = true;
	}

	if (check_bitrix_sessid()) {
		if ($save == 'reset_' . $sId) {
			$setDefault = true;
		}
	}

	foreach ($options_list as $option_name => $arOption) {
		$option_name_def = $option_name;
		$option_name_loc = $arOption['NAME'] || $option_name;
		$option_name = $option_name . '_' . $sId;
		$optionIsValid = false;
		if ($saveOption) {

			if ($arOption['type'] == 'file') {
				$files = [];
				$files = $request->getFile($option_name);
				if (!empty($files) && (isset($files['tmp_name'])) && (!empty($files['tmp_name']))) {

					$arr_file = [
						"name" => $files['name'],
						"size" => $files['size'],
						"tmp_name" => $files['tmp_name'],
						"type" => $files['type'],
						"old_file" => \Bitrix\Main\Config\Option::get($arModuleCfg['MODULE_ID'], $option_name_def, $arOption['default'], $sId),
						"del" => "Y",
						"MODULE_ID" => $arModuleCfg['MODULE_ID']
					];

					$fid = CFile::SaveFile($arr_file, $arModuleCfg['MODULE_ID'], true ,false, '1');
					if ($fid > 0) {
						$option[$option_name] = $fid;
						$optionIsValid = checkOption($option_name_def, $option[$option_name]);
					} else {
						$optionIsValid = 'File not loaded';
					}
					if ($optionIsValid !== true) {
						$eeror_message .= 'ERROR: ' . Loc::getMessage('ISPRO_SITEMAP_XML_' . $option_name_loc) . ' ' . $optionIsValid . PHP_EOL;
					}
				}
				if ($request->getpost($option_name.'_del') == 'Y') {
					$fid = \Bitrix\Main\Config\Option::get($arModuleCfg['MODULE_ID'], $option_name_def, $arOption['default'], $sId);
					if ($fid > 0) {
						CFile::Delete($fid);
					}
				};
			} else {
				$option[$option_name] = $request->getpost('option_' . $option_name);
				$optionIsValid = checkOption($option_name_def, $option[$option_name]);
				if ($optionIsValid !== true) {
					$eeror_message .= 'ERROR: ' . Loc::getMessage('ISPRO_SITEMAP_XML_' . $option_name_loc) . ' ' . $optionIsValid . PHP_EOL;
				}
				if (is_array($option[$option_name])) {
					$option[$option_name] = json_encode($option[$option_name]);
				};
			}
		} elseif ($setDefault) {
			if ($arOption['type'] == 'file') {
				$fid = \Bitrix\Main\Config\Option::get($arModuleCfg['MODULE_ID'], $option_name_def, $arOption['default'], $sId);;
				if ($fid > 0) {
					CFile::Delete($fid);
				}
				$option[$option_name] = 0;
			} else {
				$option[$option_name] = $arOption['default'];
			}
			$optionIsValid = true;
		};
		if (($saveOption || $setDefault) && ($optionIsValid === true)) {
			\Bitrix\Main\Config\Option::set($arModuleCfg['MODULE_ID'], $option_name_def, $option[$option_name], $sId);
			$ok_message .= 'SAVED: ' . Loc::getMessage('ISPRO_SITEMAP_XML_' . $option_name_loc) . PHP_EOL;
		};

		$option[$option_name] = \Bitrix\Main\Config\Option::get($arModuleCfg['MODULE_ID'], $option_name_def,   $arOption['default'], $sId);
		if ($option_type == 'json') {
			$option[$option_name . '_VALUE'] = @json_decode($option[$option_name], true);
		};
	};
};
if (($eeror_message == '') && ($ok_message != '')) {
	$ok_message = 'Saved';
}


if ($ok_message != '') {
	$message = new \CAdminMessage(array(
		'MESSAGE' => $ok_message,
		'TYPE' => 'OK'
	));
	echo $message->Show();
}
if ($eeror_message != '') {
	$message = new \CAdminMessage(array(
		'MESSAGE' => $eeror_message,
		'TYPE' => 'ERROR'
	));
	echo $message->Show();
}

$tabList = [];
$tabList[] = [
	'DIV' => 'description',
	'TAB' => Loc::getMessage('ISPRO_SITEMAP_XML_TAB_SET_DESC'),
	'ICON' => 'ib_settings',
	'TITLE' => Loc::getMessage('ISPRO_SITEMAP_XML_TAB_TITLE_DESC')
];

foreach ($siteIds as $sId => $sName) {
	$tabList[] = [
		'DIV' => 'setting' . $sId,
		'TAB' => Loc::getMessage('ISPRO_SITEMAP_XML_TAB_SET_OPTION') . ' (' . $sName . ')',
		'ICON' => 'ib_settings',
		'TITLE' => Loc::getMessage('ISPRO_SITEMAP_XML_TAB_TITLE_OPTION') . ' (' . $sName . ')'
	];
}

$tabControl = new CAdminTabControl(str_replace('.', '_', $arModuleCfg['MODULE_ID']) . '_options', $tabList);
?>
<style>
	#SITEMAP_XML_form textarea {
		width: 100%;
		min-height: 150px;
	}
	#SITEMAP_XML_form .hidden:not(*[data-iblock_name="Y"]) {
		display: none;
	}
	#SITEMAP_XML_form *[data-iblock_name="Y"] td {
		border-top: 1px solid #aaa;

	}

</style>
<form method="POST" action="<?= $currentUrl; ?>" enctype="multipart/form-data" id="SITEMAP_XML_form">
	<?= bitrix_sessid_post(); ?>
	<?
	$tabControl->Begin();
	?>

	<?
	$tabControl->BeginNextTab();
	?>
	<tr>
		<td colspan="2">
			<?= BeginNote(); ?>
			<?= Loc::getMessage('ISPRO_SITEMAP_XML_DESCRIPTION'); ?>
			<?= EndNote(); ?>
		</td>
	</tr>


	<? foreach ($siteIds as $sId => $sName) : ?>
		<?
		$tabControl->BeginNextTab();
		?>
		<?
		$options_list = getOptionsPerSite($arModuleCfg, $arSite[$sId]);
		?>
		<? foreach ($options_list as $option_name => $arOption) : ?>
			<? $option_name_def = $option_name; ?>
			<? $option_name_loc = $arOption['NAME']; ?>
			<? $option_name = $option_name . '_' . $sId; ?>

				<tr
					<?
						if (isset($arOption['is_iblock'])) {
							echo 'data-iblock="'.$arOption['is_iblock'].'"';
							if (isset($arOption['is_iblock_name'])) {
								echo 'data-iblock_name="Y"';
							} else {
								echo 'class="hidden"';
							}
						}
					?>
					>
					<td width="30%" valign="top">
						<?= $option_name_loc != '' ? $option_name_loc : Loc::getMessage('ISPRO_SITEMAP_XML_' . $option_name_def); ?>
					</td>

					<td width="70%">
						<? if ($arOption['type'] == 'textarea') : ?>
							<textarea name="option_<?= $option_name ?>"><?= HtmlFilter::encode($option[$option_name]) ?></textarea>
						<? elseif ($arOption['type'] == 'checkbox') : ?>
							<input type="hidden" name="option_<?= $option_name ?>" value="N" />
							<input type="checkbox" name="option_<?= $option_name ?>" value="Y" <?= ($option[$option_name] == "Y") ? 'checked="checked"' : '' ?> />
						<? elseif ($arOption['type'] == 'select') : ?>
							<select name="option_<?= $option_name ?>">
								<? foreach ($arOption['values'] as $value) : ?>
									<option value="<?= $value ?>" <?= ($option[$option_name] == $value) ? 'selected' : '' ?>>
										<?= $value; ?>
									</option>
								<? endforeach ?>
							</select>
						<? elseif ($arOption['type'] == 'file') : ?>
							<?
							echo CFile::InputFile(
								$option_name, 									//FieldName
								20,												//field_size
								$option[$option_name], 							//ImageID
								'/upload/', 									//ImageStorePath
								0,												//file_max_size
								$arOption['ext'],								//FileType
								"",												//field_file
								0,												//description_size
								"class=typeinput",								//field_text
								"",												//field_checkbox
								true,											//ShowNotes
								true											//ShowFilePath
							)
							?>
						<? else : ?>
							<input type="<?= $arOption['type'] ?>" name="option_<?= $option_name ?>" value="<?= HtmlFilter::encode($option[$option_name]) ?>" />
						<? endif ?>
					</td>
				</tr>

		<? endforeach ?>
		<tr>
			<td>
				<?= Loc::getMessage('ISPRO_SITEMAP_XML_RESET'); ?>
			</td>
			<td>
				<button type="submit" class="adm-btn" name="save" value="reset_<?= $sId ?>"><?= Loc::getMessage('ISPRO_SITEMAP_XML_RESET'); ?> (<?= $sName ?>)</button>
			</td>
		</tr>

	<? endforeach ?>

	<? $tabControl->Buttons(); ?>

	<button type="submit" class="adm-btn adm-btn-save" name="save" value="save"><? echo Loc::getMessage('ISPRO_SITEMAP_XML_SAVE'); ?></button>


	<? $tabControl->End(); ?>
</form>
<script>
document.addEventListener('DOMContentLoaded', () => {
	const iblock_blocks = document.querySelectorAll('*[data-iblock_name="Y"]');
	if (iblock_blocks.length > 0) {
		iblock_blocks.forEach((ib_block) => {
 			const el_checkbox = ib_block.querySelector('input[type="checkbox"]');
			const ib_data_iblock = ib_block.dataset.iblock;
			console.log('ib_data_iblock');
			console.log(ib_data_iblock);
			const elements = document.querySelectorAll('*[data-iblock="'+ib_data_iblock+'"]');
			hideShowToogle(el_checkbox, elements)
			el_checkbox.addEventListener('change', () => {
				hideShowToogle(el_checkbox, elements);
			})
		})
	}
})

function hideShowToogle(el_checkbox, elements) {
			if (elements.length > 0) {
				if (el_checkbox.checked) {
					elements.forEach((element)=> {
						element.classList.remove('hidden');
					});
				} else {
					elements.forEach((element)=> {
						element.classList.add('hidden');
					});
				}
			}
}
</script>
