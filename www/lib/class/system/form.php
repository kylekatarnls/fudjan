<?

namespace System
{
	class Form extends \System\Model\Attr
	{
		const SEPARATOR_ID = '_';
		const SEPARATOR_INPUT_METHOD = 'input_';
		const TEMPLATE_DEFAULT = 'system/form';
		const LABEL_SUBMIT_DEFAULT = 'send';

		protected static $attrs = array(
			"id"       => array('varchar'),
			"method"   => array('varchar'),
			"action"   => array('varchar'),
			"enctype"  => array('varchar'),
			"heading"  => array('varchar'),
			"desc"     => array('varchar'),
			"anchor"   => array('varchar'),
			"bool"     => array('no_prefix'),
			"class"    => array('array'),
		);

		private static $methods_allowed = array('get', 'post', 'put', 'delete');

		protected $data_default  = array();
		protected $data_commited = array();
		protected $data_hidden   = array();

		private $objects = array();
		private $ignored = array();
		private $renderer, $response, $request;
		private $rendering = array(
			"group"     => false,
			"tab_group" => false,
			"tab"       => false,
		);

		private $prefix = '';

		protected $checkboxes = array();
		protected $counts = array(
			'inputs'    => 1,
			"tab_group" => 0,
			'tab'       => 0,
		);
		protected $errors = array();

		private static $inputs_button = array("button", "submit");


		public static function from_response(\System\Http\Response $response, array $attrs = array())
		{
			$attrs['request'] = $response->request();

			if (empty($attrs['action'])) {
				$attrs['action'] = $response->request()->path;
			}

			$form = new self($attrs);
			$form->response = $response;
			$form->renderer = $response->renderer();
			return $form;
		}


		public static function from_module(\System\Module $module, array $attrs = array())
		{
			return self::from_response($module->response(), $attrs);
		}


		public static function from_renderer(\System\Template\Renderer $ren, array $attrs = array())
		{
			return self::from_response($ren->response(), $attrs);
		}


		public static function from_request(\System\Http\Request $request, array $attrs = array())
		{
			$attrs['request'] = $request;

			if (empty($attrs['action'])) {
				$attrs['action'] = $request->path;
			}

			return new self($attrs);
		}

		/** Constructor addon
		 * @return void
		 */
		protected function construct()
		{
			!$this->method  && $this->method = 'post';
			!$this->id      && $this->id = self::get_generic_id();
			!$this->anchor  && $this->anchor = \System\Url::gen_seoname($this->id, true);
			!$this->enctype && $this->enctype = 'multipart/form-data';

			if (is_array($this->default)) {
				$this->data_default = $this->default;
			}

			if ($this->opts['request']) {
				$this->request = $this->opts['request'];
				unset($this->opts['request']);
			}

			$this->method = strtolower($this->method);
			$this->take_data_from_request();

			$this->hidden('submited', true);
			$this->data_default['submited'] = false;
		}


		/** Alias to create simple input type
		 * @param string $name Name of called method
		 * @param array  $args Arguments to the function
		 */
		public function __call($name, $args)
		{
			if (strpos($name, self::SEPARATOR_INPUT_METHOD) === 0) {
				$type = substr($name, strlen(self::SEPARATOR_INPUT_METHOD));

				if (!isset($args[0])) {
					throw new \System\Error\Argument(sprintf('You must enter input name as first argument for System\\Form::%s method', $name));
				}

				return $this->input(array(
					"type"     => $type,
					"name"     => $args[0],
					"label"    => def($args[1], ''),
					"required" => def($args[2], false),
					"info"     => def($args[3], ''),
				));

			} else throw new \System\Error\Wtf(sprintf('There is no form method "%s".', $name));
		}


		/** Lookup commited data in input class
		 * @return void
		 */
		protected function take_data_from_request()
		{
			$this->data_commited = $this->request()->input_by_prefix($this->get_prefix(), $this->method);

			if (isset($this->data_commited['data_hidden'])) {
				$this->data_hidden = \System\Json::decode(htmlspecialchars_decode($this->data_commited['data_hidden']));

				$tmp = array();

				if (is_array($this->data_hidden)) {
					foreach ($this->data_hidden as $key=>$val) {
						$tmp[$key] = $val;
					}
				}

				if (is_array($this->data_commited)) {
					foreach ($this->data_commited as $key=>$val) {
						$tmp[$key] = $val;
					}
				}

				$this->data_commited = $tmp;
				unset($this->data_commited['data_hidden']);
			}

			$this->submited = isset($this->data_commited['submited']) ? !!$this->data_commited['submited']:false;
		}


		/** Get value of input by name
		 * @param array $attrs Input attributes
		 * @return mixed
		 */
		public function get_input_value($attrs)
		{
			return $this->get_input_value_by_name($attrs['name']);
		}


		public function set_input_value($name, $value)
		{
			$this->data_default[$name] = $value;
		}


		public function get_input_value_by_name($name, $default = false)
		{
			$value = null;

			if (($default || !$this->submited) && isset($this->data_default[$name])) {
				$value = $this->data_default[$name];
			}

			if (!$default && $this->submited && isset($this->data_commited[$name])) {
				$value = $this->data_commited[$name];
			}

			return $value;
		}


		/** Get generic ID for this form
		 * @return string
		 */
		protected function get_generic_id()
		{
			return implode(self::SEPARATOR_ID, array('form', substr(md5($this->action), 0, 8)));
		}


		/** Add object to forms' set of objects
		 * @param System\Form\Element $element
		 * @return void
		 */
		protected function &add_object(\System\Form\Element $element)
		{
			$obj = &$this->objects[];
			$obj = $element;
			return $obj;
		}


		/** Start rendering form element container
		 * @param string $type Type of element container (see System\Form\Container)
		 * @param string $name
		 * @param string $label
		 */
		public function group_start($type, $name = '', $label = '')
		{
			$el = new \System\Form\Container(array(
				"name"  => $name ? $name:'',
				"label" => $label,
				"form"  => &$this,
				"type"  => $type,
			));

			if ($this->rendering['tab'] instanceof \System\Form\Container) {
				$this->rendering['group'] = $this->rendering['tab']->add_element($el);
			} else {
				$this->objects[$el->name] = $el;
				$this->rendering['group'] = $this->objects[$el->name];
			}

			return $this->rendering['group'];
		}


		/** Stop rendering form element container
		 * @return void
		 */
		public function group_end()
		{
			$this->rendering['group'] = false;
		}


		/** Check if form container is on, start it otherwise
		 * @param string $type
		 * @return void
		 */
		public function check_rendering_group($type)
		{
			if ($this->rendering['group'] === false || $this->rendering['group']->type != $type) {
				$this->group_start($type, count($this->objects));
			}

			return $this->rendering['group'];
		}


		/** Get generic object name
		 * @return string
		 */
		public function gen_obj_name($type)
		{
			return implode(self::SEPARATOR_ID, array($this->id, $this->inputs_count));
		}


		/** Is the form ready for processing
		 * @return bool
		 */
		public function passed()
		{
			return $this->submited;
		}


		/** Is the form ready for processing
		 * @return bool
		 */
		public function submited()
		{
			return $this->submited;
		}


		/** Get count of element type
		 * @param string $type
		 */
		public function get_count($type)
		{
			if (!isset($this->counts[$type])) {
				$this->counts[$type] = 0;
			}

			return $this->counts[$type];
		}


		/** Add hidden data
		 * @param string $name
		 * @param mixed  $value
		 */
		public function hidden($name, $value)
		{
			$this->data_hidden[$name] = $value;
		}


		/** Check if tab group has started, start it if not
		 * @return $this
		 */
		public function tab_group_check()
		{
			if (!($this->rendering[\System\Form\Container::TYPE_TAB_GROUP] instanceof \System\Form\Container)) {
				$this->tab_group_start();
			}

			return $this;
		}


		/** Start groupping tabs into a group
		 * @return $this
		 */
		public function tab_group_start()
		{
			$el = $this->add_object(new \System\Form\Container(array(
				"type" => \System\Form\Container::TYPE_TAB_GROUP,
				"form" => $this,
			)));

			$this->rendering[$el->type] = $el;
			$this->counts[$el->type]++;
			return $this;
		}


		/** Stop groupping tabs into a group
		 * @return $this
		 */
		public function tab_group_end()
		{
			$this->rendering[\System\Form\Container::TYPE_TAB] = false;
			$this->rendering[\System\Form\Container::TYPE_TAB_GROUP] = false;
			return $this;
		}


		/** Start groupping input containers into tab
		 * @param string $label Tab label
		 * @param string $name  Tab name, usefull for JS calls
		 * @return $this
		 */
		public function tab($label, $name = null)
		{
			$this->group_end();
			$this->tab_end();
			$this->tab_group_check();

			$el = new \System\Form\Container(array(
				"type"  => \System\Form\Container::TYPE_TAB,
				"name"  => $name,
				"label" => $label,
				"form"  => $this,
			));

			$this->counts[$el->type] ++;
			if (($this->rendering[\System\Form\Container::TYPE_TAB_GROUP] instanceof \System\Form\Container) && $this->rendering[\System\Form\Container::TYPE_TAB_GROUP]->type == \System\Form\Container::TYPE_TAB_GROUP) {
				$this->rendering[$el->type] = $this->rendering[\System\Form\Container::TYPE_TAB_GROUP]->add_element($el);
			} else throw new \System\Error\Form('You must put tab into tab group.');

			return $this;
		}


		/** Stop grouping inputs into current tab
		 * @return $this
		 */
		public function tab_end()
		{
			$this->rendering['tab'] = false;
			return $this;
		}


		/** Add input
		 * @param array $attrs
		 * @param bool  $detached Return input detached from the form
		 * @return System\Form\Input
		 */
		public function input(array $attrs, $detached = false)
		{
			if (in_array($attrs['type'], self::$inputs_button)) {
				$this->check_rendering_group('buttons');
			} else {
				$this->check_rendering_group('inputs');
			}

			$el = null;
			$attrs['form'] = &$this;

			if (isset($attrs['value'])) {
				$this->use_value($attrs['name'], $attrs['value']);
			}

			$attrs['value'] = $this->get_input_value_by_name($attrs['name']);

			if ($attrs['type'] == 'checkbox') {
				$this->checkboxes[] = $attrs['name'];

				// Preset value to checkbox since checkboxes are not sending any value if not checked
				if (!isset($this->data_commited[$attrs['name']])) {
					$this->data_commited[$attrs['name']] = null;
				}
			}

			if (in_array($attrs['type'], array('checkbox', 'radio'))) {
				if ($this->submited) {
					$attrs['checked'] = !!$this->data_commited[$attrs['name']];
				} else {
					$attrs['checked'] = isset($this->data_default[$attrs['name']]) && $this->data_default[$attrs['name']];
				}
			}

			if ($attrs['type'] === 'rte')      $el = new \System\Form\Widget\Rte($attrs);
			if ($attrs['type'] === 'action')   $el = new \System\Form\Widget\Action($attrs);
			if ($attrs['type'] === 'gps')      $el = new \System\Form\Widget\Gps($attrs);
			if ($attrs['type'] === 'image')    $el = new \System\Form\Widget\Image($attrs);
			if ($attrs['type'] === 'search')   $el = new \System\Form\Widget\Search($attrs);
			if ($attrs['type'] === 'location') $el = new \System\Form\Widget\Location($attrs);
			if ($attrs['type'] === 'datetime') $el = new \System\Form\Widget\DateTime($attrs);

			// Recursion prevention
			if (!isset($attrs['parent']) || !($attrs['parent'] instanceof \System\Form\Widget\Date || $attrs['parent'] instanceof \System\Form\Widget\Time)) {
				if ($attrs['type'] === 'date') $el = new \System\Form\Widget\Date($attrs);
				if ($attrs['type'] === 'time') $el = new \System\Form\Widget\Time($attrs);
			}

			if (is_null($el)) {
				$el = new \System\Form\Input($attrs);
			}

			return $detached ? $el:$this->attach($el);
		}


		public function get_rendering_container()
		{
			return $this->rendering['group'];
		}


		public function attach(\System\Form\Element $el)
		{
			$this->get_rendering_container()->add_element($el);
		}


		/** Add label
		 * @param string $text
		 * @param input  $for
		 */
		public function label($text, \System\Form\Input &$for = null)
		{
			$this->check_rendering_group('inputs');
			$attrs['form'] = &$this;
			return $this->rendering['group']->add_element(new Form\Label(array("content" => $text, "input" => $for)));
		}


		public function text($label, $text)
		{
			$this->check_rendering_group('inputs');

			return $this->rendering['group']->add_element(new Form\Text(array(
				"form" => $this,
				"name" => crc32($label),
				"label" => $label,
				"content" => $text)
			));
		}


		/** Add common submit button
		 * @param string $label
		 */
		public function submit($label = self::LABEL_SUBMIT_DEFAULT)
		{
			return $this->input(array(
				"name"    => 'button_submited',
				"value"   => true,
				"type"    => 'submit',
				"label"   => $label,
			));
		}


		/** Render form or add form to processing
		 * @param System\Module $obj    Module to render the form in
		 * @param array         $locals Extra local data
		 * @return mixed
		 */
		public function out(\System\Module $obj = NULL, array $locals = array())
		{
			$this->group_end();
			$this->tab_group_end();

			return $obj instanceof \System\Module ?
				$obj->partial(self::get_default_template(), (array) $locals + array("f" => $this)):
				$this->response->renderer()->partial(self::get_default_template(), array("f" => $this));
		}


		public static function get_default_template()
		{
			return self::TEMPLATE_DEFAULT;
		}


		public function get_hidden_data()
		{
			return $this->data_hidden;
		}


		public function get_prefix()
		{
			!$this->prefix && !$this->no_prefix && $this->setup_prefix();
			return $this->prefix;
		}


		/** Set default form prefix
		 * @return string
		 */
		protected function setup_prefix()
		{
			$this->prefix = $this->id.'_';
		}


		public function get_objects()
		{
			return $this->objects;
		}


		public function report_error($input_name, $msg)
		{
			if (!isset($this->errors[$input_name])) {
				$this->errors[$input_name] = array();
			}

			$this->errors[$input_name][] = $msg;
		}


		public function get_attr_data()
		{
			return parent::get_data();
		}


		public function get_data()
		{
			if ($this->submited) {
				$data = $this->data_commited;
			} else {
				$data = $this->data_default;
			}

			foreach ($this->ignored as $name) {
				if (isset($data[$name])) {
					unset($data[$name]);
				}
			}

			return $data;
		}


		public function get_errors($name = '')
		{
			if ($name) {
				if (isset($this->errors[$name])) {
					$error_list = &$this->errors[$name];
				} else $error_list = array();
			} else {
				$error_list = &$this->errors;
			}

			return $error_list;
		}


		/** Get field type from model attr type
		 * @param string $attr_type
		 * @return string
		 */
		public static function get_field_type($attr_type)
		{
			if (in_array($attr_type, array('date', 'datetime', 'time', 'image', 'location'))) {
				$type = $attr_type;
			} elseif ($attr_type === 'point') {
				$type = 'gps';
			} elseif ($attr_type === 'bool') {
				$type = 'checkbox';
			} elseif ($attr_type === 'text') {
				$type = 'rte';
			} else {
				$type = 'text';
			}

			return $type;
		}


		public function renderer(\System\Template\Renderer $renderer = null)
		{
			if (!is_null($renderer)) {
				$this->renderer = $renderer;
			}

			return $this->renderer;
		}


		public function response(\System\Http\Response $response = null)
		{
			if (!is_null($response)) {
				$this->response = $response;
			}

			return $this->response;
		}


		public function request(\System\Http\Request $request = null)
		{
			if (!is_null($request)) {
				$this->request = $request;
			}

			return $this->request;
		}


		public function ignore_input($name)
		{
			if (!in_array($name, $this->ignored)) {
				$this->ignored[] = $name;
			}

			return $this;
		}


		public function ignore_inputs(array $names)
		{
			foreach ($names as $name) {
				$this->ignore_input($name);
			}

			return $this;
		}


		public function use_value($name, $val)
		{
			if ($this->submited()) {
				$this->data_commited[$name] = $val;
			} else {
				$this->data_default[$name] = $val;
			}
		}
	}
}
