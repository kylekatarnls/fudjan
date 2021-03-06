<?php

/** Database data model handling */
namespace System\Model
{
	abstract class Perm extends Filter
	{
		const VIEW_SCHEMA = 'schema';
		const CREATE      = 'create';
		const BROWSE      = 'browse';
		const UPDATE      = 'update';
		const DROP        = 'drop';
		const VIEW        = 'view';


		/** Get default config for this action
		 * @param string $method One of permission method constants
		 * @return bool
		 */
		public static function get_default_for($method)
		{
			try {
				$res = cfg('api', 'allow', $method);
			} catch (\System\Error\Config $e) {
				throw new \System\Error\Argument('Unknown permission method type.', $method);
			}

			return !!$res;
		}

    public function get_groups()
    {
      return $this->groups->fetch();
    }


		/** Ask if user has right to do this
		 * @param string      $method One of created, browsed
		 * @param System\User $user   User to get perms for
		 * @return bool
		 */
		public static function can_user($method, \System\User $user)
		{
			if ($user->is_root()) {
				return true;
			}

			$cname = get_called_class();
			$conds = array();

			if (isset($cname::$access) && isset($cname::$access[$method]) && !is_null($cname::$access[$method])) {
				return !!$cname::$access[$method];
			}

			if ($user->is_guest()) {
				$conds['public'] = true;
			} else {
				$groups = $user->get_groups();

				if (any($groups)) {
					$conds[] = 'id_group IN ('.implode(',', collect_ids($groups)).')';
				}
			}

			$perm = \System\User\Perm::get_first()
				->add_filter(array(
					'attr' => 'trigger',
					'type' => 'in',
					'in'   => array(
						'model-'.$method,
						'*'
					),
				))
				->add_filter(array(
					'attr' => 'name',
					'type' => 'in',
					'in'   => array(
						\System\Loader::get_model_from_class($cname).\System\Loader::SEP_MODEL.$method,
						'*'
					)
				))
				->where($conds)
				->fetch();

			return $perm ? $perm->allow:static::get_default_for($method);
		}


		/** Ask if user has right to do this
		 * @param string      $method One of viewed, updated, dropped
		 * @param System\User $user   User to get perms for
		 * @return bool
		 */
		public function can_be($method, \System\User $user)
		{
			return static::can_user($method, $user);
		}


		public function to_object_with_perms(\System\User $user)
		{
			return array_merge($this->to_object_with_id_and_perms($user), $this->get_rels_to_object_with_perms($user));
		}


		public function to_object_with_id_and_perms(\System\User $user)
		{
			$data  = parent::to_object_with_id();
			$attrs = $this::get_attr_list();

			foreach ($attrs as $attr_name) {
				if (!array_key_exists($attr_name, $data) || !$this::is_rel($attr_name)) {
					continue;
				}

				$def = $this::get_attr($attr_name);
				$rel_cname = $def['model'];
				$is_subclass = is_subclass_of($rel_cname, '\System\Model\Perm');
				$is_allowed  = $is_subclass && $rel_cname::can_user(static::BROWSE, $user);

				if (!$is_allowed) {
					unset($data[$attr_name]);

					if ($def['type'] == static::REL_BELONGS_TO) {
						unset($data[$this::get_belongs_to_id($attr_name)]);
					}
				}
			}

			return $data;
		}


		public function get_rels_to_object_with_perms(\System\User $user)
		{
			$data  = array();
			$attrs = $this::get_attr_list();

			foreach ($attrs as $attr_name) {
				if ($this::is_rel($attr_name)) {
					$def = $this::get_attr($attr_name);
					$rel_cname = $def['model'];
					$is_subclass = is_subclass_of($rel_cname, '\System\Model\Perm');
					$is_allowed  = $is_subclass && $rel_cname::can_user(static::BROWSE, $user);

					if ($is_allowed) {
						if ($def['type'] == static::REL_BELONGS_TO) {
							$bid = $this::get_belongs_to_id($attr_name);

							if ($this->$bid) {
								$data[$attr_name] = $this->$bid;
							}
						}
					}
				}
			}

			return $data;
		}


		public static function get_visible_schema(\System\User $user)
		{
			if (static::can_user(static::VIEW_SCHEMA, $user)) {
				$cname  = get_called_class();
				$schema = static::get_schema();
				$res    = array();
				$rel_attrs = array(
					'collection',
					'model'
				);

				foreach ($schema['attrs'] as $key=>$attr) {
					if (in_array($attr['type'], $rel_attrs)) {
						$rel_cname = \System\Loader::get_class_from_model($attr['model']);

						if (class_exists($rel_cname) && is_subclass_of($rel_cname, '\System\Model\Perm') && $rel_cname::can_user(static::VIEW_SCHEMA, $user)) {
							$res[] = $attr;
						}
					} else {
						$res[] = $attr;
					}
				}

				$schema['attrs'] = $res;
				return $schema;
			} else throw new \System\Error\AccessDenied();
		}
	}
}
