<?

/** CLI core
 * @package core
 */
namespace
{
	/** Container class for commands and options
	 * @package core
	 */
	abstract class YacmsCLIOptions
	{
		/** Options container
		 * @param array $opts
		 */
		protected static $opts;

		/** Command list container
		 * @param array $commands
		 */
		protected static $commands = array();

		/** Commands that need The Force
		 * @param array
		 */
		protected static $forced_commands = array();

		/** Console width
		 * @param int
		 */
		protected static $con_width = 40;

		/** Predefined environment
		 * @param string
		 */
		protected static $env = 'dev';

		/** Script information
		 * @param array
		 */
		protected static $info = array(
			"head" => null,
			"name" => null,
			"foot" => null,
		);


		/** Public getter
		 * @param string $opt  Option to get
		 * @param bool   $stfu Shut the fuck up
		 * @return mixed
		 */
		public static function get($opt, $stfu = false)
		{
			if (isset(self::$opts[$opt])) {
				return self::$opts[$opt]['value'];
			} else !$stfu && give_up("Undefined option: '".$opt."'");

			return false;
		}


		/** Environment getter-wrapper
		 * @return string
		 */
		public static function get_env()
		{
			$opt = CLIOptions::get('env', true) ? CLIOptions::get('env'):CLIOptions::$env;
			return $opt;
		}


		/** Setter pro options
		 * @param string $opt Option to save
		 * @param mixed  $val Value to save
		 * @return mixed
		 */
		public static function set($opt, $val)
		{
			if (isset(self::$opts[$opt])) {
				return self::$opts[$opt]['value'] = $val;
			} else give_up("Undefined option: '".$opt."'");
		}


		/** Get list of available commands
		 * @return array Set of commands
		 */
		public static function get_commands_list()
		{
			return self::$commands;
		}


		/** Does command exist?
		 * @param string $cmd Name of command
		 * @return bool
		 */
		public static function command_exists($cmd)
		{
			return array_key_exists($cmd, self::$commands);
		}


		/** Does command need --force options to be invoked?
		 * @param string $cmd Name of command
		 * @return bool
		 */
		public static function command_needs_the_force($cmd)
		{
			return in_array($cmd, self::$forced_commands);
		}


		/** Get console width (for decoration) in chars
		 * @return int Console width
		 */
		public static function get_con_width()
		{
			return self::$con_width;
		}


		/** Parse CLI arguments into options
		 * @return void
		 */
		public static function parse_options()
		{

			$argv = $_SERVER['argv'];
			reset($argv);

			while ($arg = next($argv)) {
				$argn = key($argv);
				if (isset($argv[$argn])) {
					foreach (self::$opts as $long => $info) {
						if (($arg == '--'.$long && $t = 'l') || (isset($info['short']) && $arg == '-'.$info['short'] && $t = 's')) {

							if (isset($info['type'])) {
								switch ($info['type']) {
									case 'string':{
										if ($t == 's') {

											$value = &$argv[$argn++];
											empty($value) ?
												give_up("Option ".$arg." requires value"):
												CLIOptions::set($long, $value);

											unset($argv[$argn]);
											$argv = array_values($argv);

										} else {

											if (strpos($arg, '=') > 0) {
												list($key, $value) = explode('=', $arg, 2);
												empty($value) ?
													give_up("Option ".$arg." requires value"):
													CLIOptions::set('env', $value);

												unset($argv[$argn], $argv[$argn+1]);
												$argv = array_values($argv);

												unset($argv[$argn]);
											} else give_up("Option ".$arg." requires value");

										}
										break;
									}
								}
							} else {

								CLIOptions::set($long, true);
								unset($argv[$argn]);
								$argv = array_values($argv);

							}
						}
					}
				}
			}

			if (self::get('help')) {
				return self::usage();
				exit;
			}

			foreach ($argv as $arg) {
				if (strpos($arg, '-') === 0) {
					give_up("Unrecognized option: '".$arg."'");
				}
			}

			if (empty(self::$commands) || (isset($argv[1]) && CLIOptions::command_exists($argv[1]))) {
				if (isset($argv[1])) {
					CLIOptions::command_needs_the_force($argv[1]) && !CLIOptions::get('force') ?
						give_up("If you know what you are doing, please use --force option.", 0):
						CLIOptions::set('command', $argv[1]);

				} elseif(empty(self::$commands)) {
					CLIOptions::set('command', 'run');
				}

				unset($argv[0], $argv[1]);
				CLIOptions::set('params', array_values($argv));
			} else give_up("Please specify a valid command. Use --help option to get more info.");
		}


		/** Display usage
		 * @return void
		 */
		public static function usage()
		{
			$cmd_list = array();
			$opt_list = array();

			foreach (self::$commands as $cmd => $info) {
				if (is_array($info)) {
					foreach ($info as $type => $desc) {
						$cmd_list[$type == 'single' ? $cmd:$cmd." '".$type."'"] = $desc;
					}
				} else $cmd_list[$cmd] = $info;
			}

			foreach (self::$opts as $opt => $info) {
				if (isset($info['desc'])) {
					$name = (isset($info['short']) ? '-'.$info['short'].' ':'   ').'--'.$opt;
					$opt_list[$name] = $info['desc'];
				}
			}

			echo
				self::$info['head'].NL.

				"Usage:".NL.
					"  ./".self::$info['name']." ".(empty(self::$commands) ? "environment":"command")." ".NL.
					"  ./".self::$info['name']." ".(empty(self::$commands) ? "environment":"command")." [params]".NL.
				NL;

			if (!empty(self::$commands)) {
				echo
					"Commands:".NL;
					out_flist($cmd_list, false, 2);

				echo NL;
			}

			echo
				"Options:".NL;
				out_flist($opt_list, false, 2);

			echo NL.

				self::$info['foot'];
			exit;
		}
	}


	/** Class container for cli commands
	 * @package core
	 */
	abstract class YacmsCLICommands
	{
		/** Wrapper for calling static methods
		 * @param string $name Name of method
		 * @param array  $args Argument list
		 * @return mixed
		 */
		public static function __callStatic($name, $args)
		{
			$callback = array('CLICommands', $name);
			is_callable($callback) ?
				call_user_func($callback):
				give_up("Invalid command. Try --help for more info");
		}
	}
}