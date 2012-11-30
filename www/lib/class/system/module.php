<?

namespace System
{
	class Module
	{
		const BASE_DIR = '/lib/module';
		static private $instances;
		static private $array_forced_locals = array("conds", "opts");
		private $id, $path, $locals, $slot, $parents;

		static public function get_new_id()
		{
			return 'noname-'.self::$instances;
		}


		public function __construct($module, $locals = array(), $parents = array())
		{
			self::$instances ++;
			$this->path = '/'.$module;
			$this->locals = $locals;
			$this->parents = $parents;

			if (!empty($this->locals['module_id'])) {
				$this->id = $this->locals['module_id'];
			} else {
				$this->id = self::get_new_id();
			}
			$this->slot = def($locals['slot'], Template::DEFAULT_SLOT);
		}


		public function get_path()
		{
			return $this->path;
		}


		public function get_name()
		{
			return _('Modul').' '.$this->path;
		}


		public function get_id()
		{
			return $this->id;
		}


		public function make()
		{
			$path = ROOT.self::BASE_DIR.$this->path.'.php';

			if (file_exists($path)) {
				if (user()->is_root() || user()->has_right_to('*') || user()->has_right_to(substr($this->path, 1))) {
					if (is_readable($path)) {
						if (!is_array($this->locals)) $this->locals = array($this->locals);
						$locals = &$this->locals;
						if (is_array($locals)) {
							$input = Input::get('page');
							$propagated = array();

							if (any($this->parents)) {
								$propagated = DataBus::get_data($this->parents);
								$locals = array_merge($locals, $propagated);
							}

							foreach (self::$array_forced_locals as $var) {
								if (isset($locals[$var]) && !is_array($locals[$var])) throw new \CatchableException(sprintf(_('Module: `%s`'), $this->path).":\n".sprintf(_('local var `$%s` must be an array'), $var));
							}

							foreach ($locals as $key=>&$val) {
								if (is_numeric($key)) {
									$key = 'local_attr_'.$key;
									$locals[$key] = &$val;
								} else {
									$key = str_replace('-', '_', $key);
								}

								$val === '#' && $val = end($input);
								!is_object($val) && !is_array($val) && preg_match("/^\#\{[0-9]{1,3}\}$/", $val) && $val = Input::get('page', intval(substr($val, 2)));
								!is_object($val) && !is_array($val) && strpos($val, '#user{') === 0 && $val = soprintf(substr($val, 5), user());

								$$key = &$val;
							}
						}

						$req = require($path);

						if (any($propagate)) {
							DataBus::save_data($this, $propagate);
						}

						return !!$req;
					} else return message("error", _('Moduly'), _('Modul není určen ke čtení: ').$this->path, true);
				} else return message("error", _('Oprávnění'), sprintf(_('Nemáte oprávnění přistupovat k modulu %s'), $this->path), true);
			} else return message("error", _('Moduly'), _('Modul nebyl nalezen: ').$this->path, true);
		}


		public function template($name, $locals = array())
		{
			if ($name instanceof \System\Form)
			{
				$f = $name;
				$f->check_group_end();
				$f->check_tab_group_end();
				$f->check_inputs_end();
				$name = \System\Form::get_default_template();
				$locals += array("f" => $f);
			}

			$locals = array_merge($this->locals, $locals);
			$locals['module_id'] = $this->id;
			Template::insert($name, $locals, def($this->locals['slot'], Template::DEFAULT_SLOT));
		}


		public function error($e, $desc = null)
		{
			switch($e){
				case 'params':
					message('error', _('Moduly'), sprintf(_('%s dostal špatné parametry'), $this->get_name()));
					break;
				case 'perms':
					message('error', _('Oprávnění'), sprintf(_('Nemáte oprávnění k provedení akce: %s'), $desc));
					break;
				case 'ref-not-found':
					message('error', _('Moduly'), sprintf(_('%s nenalezl hledaný objekt'), $this->get_name()));
					break;
				default:
					message('error', sprintf(_('V modulu %s se vyskytla chyba'), $this->get_name()), $desc);
					break;
			}
		}


		public static function get_all($with_perms = false)
		{
			$mods = array();
			$path = ROOT.self::BASE_DIR;

			\System\Directory::find_all_files($path, $mods, '/\.php$/');
			sort($mods);

			foreach ($mods as &$mod) {
				$mod = array("path" => preg_replace('/\.php$/', '', substr($mod, strlen($path)+1)));

				if ($with_perms) {
					$mod['perms'] = get_all("\System\User\Perm", array(
						"type" => 'module',
						"trigger" => $mod['path'],
					))->fetch();
				}
			}

			v($mods);
			return $mods;
		}


		public static function exists($mod)
		{
			return file_exists(ROOT.self::BASE_DIR.'/'.$mod.'.php');
		}


		public static function eval_conds(array $conds)
		{
			$result = true;
			foreach ($conds as $cond_str) {
				strpos($cond_str, ',') === false && $cond_str .= ',';
				list($cond, $val) = explode(',', $cond_str, 2);
				switch ($cond) {
					case 'logged-in':
						$result = $result && \System\User::logged_in();
						break;
					case 'logged-out':
						$result = $result && !\System\User::logged_in();
						break;
				}
			}

			return empty($conds) || $result;
		}
	}
}

