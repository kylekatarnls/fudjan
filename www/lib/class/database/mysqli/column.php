<?

namespace Database\Mysqli
{
	class Column
	{
		private $table;
		private $name;
		private $renamed = false;
		private $drop    = false;
		private $attr_names = array(
			'type',
			'length',
			'is_null',
			'is_unique',
			'is_unsigned',
			'is_autoincrement',
			'key',
			'default',
			'extra',
			'comment',
		);

		private $attrs = array();
		private $default = array();


		public function __construct(\Database\Mysqli\Table $table, $name, array $cfg = array())
		{
			$this->table = $table;
			$this->name  = $name;
			$this->use_loaded_cfg($cfg);
			$this->default = $this->attrs;
		}


		public function use_loaded_cfg($cfg)
		{
			if (any($cfg)) {
				if (strpos($cfg['Type'], '(')) {
					$t = explode(' ', $cfg['Type']);
					$t = explode('(', $t[0]);
					$this->attrs['type'] = $t[0];
					$this->attrs['length'] = intval($t[1]);
				} else $this->attrs['type'] = $cfg['Type'];

				$this->attrs['is_unsigned'] = strpos($cfg['Type'], 'unsigned') !== false;
				$this->attrs['is_unique']   = (
					strpos($cfg['Key'], 'UNI') !== false ||
					strpos($cfg['Key'], 'PRI') !== false
				);
				$this->attrs['is_null']     = strtolower($cfg['Null']) === 'Yes';
				$this->attrs['key']         = $cfg['Key'];
				$this->attrs['default']     = $cfg['Default'];
				$this->attrs['is_autoincrement'] = strpos($cfg['Extra'], "auto_increment") !== false;
				$this->attrs['extra']       = $cfg['Extra'];
				$this->attrs['name']        = $this->name;
			}
		}


		public function get_cfg()
		{
			$cfg = $this->attrs;
			$cfg['name'] = $this->name;
			return $cfg;
		}


		public function exists()
		{
			if ($this->table()->exists()) {
				$result = $this->table()->db()->query("
					SHOW COLUMNS FROM ".$this->table->name()."
					WHERE Field = '".($this->renamed ? $this->default['name']:$this->name)."'
				")->fetch();

				if (empty($this->default)) {
					$this->use_loaded_cfg($result);
					$this->default['name'] = $result['Field'];
				}

				$res = is_array($result) ? $result['Field']:$result;
				return $res == $this->name;
			}

			return false;
		}


		public function table()
		{
			return $this->table;
		}


		public function set_cfg(array $cfg)
		{
			foreach ($cfg as $key=>$c)
			{
				if (in_array($key, $this->attr_names)) {
					$this->attrs[$key] = $c;
				}
			}
		}


		public function rename($name)
		{
			$this->renamed = true;
			$this->default['name'] = $this->name;
			$this->name = $name;
			return $this;
		}


		public function drop()
		{
			$this->drop = true;
		}


		public function save()
		{
			$query =
				"ALTER TABLE `".$this->table()->db()->name()."`.`".$this->table->name()."` ".
				$this->get_save_query().';';

			if ($this->renamed) {
				v($query);
			}

			$this->table()->db()->query($query);
			return $this;
		}


		public function get_save_query()
		{
			if ($this->drop) {
				if ($this->exists()) {
				return "DROP `".$this->name."`";
				} else throw new \DatabaseException(sprintf('Column %s cannot be dropped from table %s. It does not exists.', $this->name, $this->table()->name()));
			} else {
				$exists = $this->exists();

				if ($exists || $this->renamed) {
					$front = 'CHANGE `'.$this->default['name'].'` `'.$this->name.'`';
				} else {
					$front = 'ADD `'.$this->name.'`';
				}

				$sq = implode(' ', array(
					$front,
					$this->attrs['type'].(any($this->attrs['length']) ? '('.$this->attrs['length'].')':''),
					any($this->attrs['is_unsigned']) ? 'unsigned':'',
					any($this->attrs['is_null']) ? 'NULL':'NOT NULL',
					any($this->attrs['is_autoincrement']) ? 'AUTO_INCREMENT':'',
					any($this->attrs['is_unique']) ? 'UNIQUE':'',
					any($this->attrs['comment']) ? " COMMENT '".$this->attrs['comment']."'":'',
				));

				return trim($sq);
			}
		}
	}
}
