<?php

namespace Helper\Cli\Module
{
	class Test extends \Helper\Cli\Module
	{
		protected static $info = array(
			'name' => 'test',
			'head' => array(
				'Test your application',
			),
		);


		protected static $attrs = array(
			"help"    => array("type" => 'bool', "value" => false, "short" => 'h', "desc"  => 'Show this help'),
			"verbose" => array("type" => 'bool', "value" => false, "short" => 'v', "desc" => 'Be verbose'),
		);


		protected static $commands = array(
			"all"  => array('Run all tests'),
			"list" => array('Run all tests'),
		);


		public static function cmd_all()
		{
			\System\Init::basic();

			$all = self::get_all();
			$path = \System\Composer::resolve('/etc/init.d/test.php');

			foreach ($all as $key=>$val) {
				$cmd = implode(';', array(
					"cd '".BASE_DIR."'",
					"phpunit --bootstrap '".$path."' --colors --test-suffix .php '".$val."'"
				));

				\Helper\Cli::out($val);
				$out = passthru($cmd);
				\Helper\Cli::out();
			}

		}


		public static function cmd_list()
		{
			\Helper\Cli::out_flist(array(
				"list"      => self::get_all(),
				"show_keys" => false,
			));
		}


		private static function get_all()
		{
			return \System\Composer::list_dirs('/lib/test');
		}

	}
}
