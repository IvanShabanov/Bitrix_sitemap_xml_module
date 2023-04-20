<?
$arModuleCfg = [
	'MODULE_ID' => mb_strtolower('is_pro.sitemap_xml'),

	/* Настройки модуля */
	'options_list' => [

		/* Пример настройки с выбором (select) */

		'MODULE_MODE' => [ 					/* Имя настройки */
			'type' => 'select', 			/* Тип поля настройки */
			'values' => [					/* Значения настройки */
				'off',
				'test',
				'on'
			],
			'default' => 'off'				/* Значение по умолчанию */
		],

		'MAIN_URL' => [
			'type' => 'text',
			'default' => ''
		],

		'PAGES' => [
			'type' => 'json',
			'default' => json_encode(['/'])
		],

		'IBLOCKS' => [
			'type' => 'json',
			'default' => json_encode([])
		],

		'IGRORE' => [
			'type' => 'json',
			'default' => json_encode([])
		],

		'AGENT_TIME' => [
			'type' => 'textarea',
			'default' => ''
		]

	]
];