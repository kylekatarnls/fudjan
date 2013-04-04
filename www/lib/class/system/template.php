<?

namespace System
{
	class Template
	{
		const TEMPLATES_DIR = '/lib/template';
		const PARTIALS_DIR = '/lib/template/partial';
		const DIR_ICONS = '/share/icons';
		const DEFAULT_SLOT = 'zzTop';
		const DEFAULT_ICON_THEME = 'default';

		const CASE_UPPER = MB_CASE_UPPER;
		const CASE_LOWER = MB_CASE_LOWER;

		private static $default_time_format = 'D, d M Y G:i:s e';
		private static $heading_level = 1;
		private static $heading_section_level = 1;

		private static $styles = array(
			array("name" => 'default', "type" => 'text/css'),
		);

		private static $units = array(
			"information" => array("B","kiB", "MiB", "GiB", "TiB", "PiB"),
		);


		public static function icon($icon, $size='32', array $attrs = array())
		{
			@list($width, $height) = explode('x', $size, 2);
			!$height && $height = $width;
			$icon = ($icon instanceof Image) ? $icon->thumb(intval($width), intval($height), !empty($attrs['crop'])):self::DIR_ICONS.'/'.$size.'/'.$icon;

			return '<span class="icon isize-'.$size.'" '.\Tag::html_attrs('span', $attrs).'style="background-image:url('.$icon.'); width:'.$width.'px; height:'.$height.'px"></span>';
		}


		public static function get_filename($name, $format = null, $lang = null)
		{
			$format == 'xhtml' && $format = 'html';
			return $name.($lang ? '.'.$lang.'.':'').($format ? '.'.$format:'').'.php';
		}


		public static function partial($name, array $locals = array())
		{
			$temp = self::get_name($name);
			foreach ((array) $locals as $k=>$v) {
				$k = str_replace('-', '_', $k);
				$$k=$v;
			}

			if (file_exists($temp)) {
				include($temp);
			} else throw new \System\Error\File(sprintf('Partial "%s" not found.', $name));
		}


		public static function insert($name, $locals = array(), $slot = self::DEFAULT_SLOT)
		{
			Output::add_template(array("name" => $name, "locals" => $locals), $slot);
		}


		public static function get_name($name)
		{
			$base = ROOT.self::PARTIALS_DIR.'/';
			$f = '';

			file_exists($f = $base.self::get_filename($name, Output::get_format(), \System\Locales::get_lang())) ||
			file_exists($f = $base.self::get_filename($name, Output::get_format())) ||
			file_exists($f = $base.self::get_filename($name)) ||
			$f = '';

			return $f;
		}


		public static function meta_out()
		{
			Output::content_for("meta", array("name" => 'generator', "content" => Output::introduce()));
			Output::content_for("meta", array("http-equiv" => 'content-type', "content" => Output::get_format(true).'; charset=utf-8'));
			Output::content_for("meta", array("charset" => 'utf-8'));

			$meta = Output::get_content_from("meta");
			foreach ($meta as $name=>$value) {
				if ($value) {
					Output::content_for("head", '<meta'.\Tag::html_attrs('meta', $value).'>');
				}
			}
		}


		public static function scripts_out()
		{
			$cont = Output::get_content_from("scripts");

			if (!is_null($cont)) {
				Output::content_for("head", '<script type="text/javascript" src="/share/scripts/'.$cont.'"></script>');
			}
		}


		public static function styles_out()
		{
			$cont = Output::get_content_from("styles");

			if (!is_null($cont)) {
				Output::content_for("head", '<link type="text/css" rel="stylesheet" href="/share/styles/'.$cont.'" />');
			}
		}


		public static function title_out()
		{
			Output::content_for("head", '<title>'.Output::get_title().'</title>');
		}


		public static function head_out()
		{
			self::meta_out();
			self::title_out();
			self::scripts_out();
			self::styles_out();
		}


		public static function link_for($label, $url, $object = array())
		{
			if (!is_array($object)) {
				$object = array("no-tag" => !!$object);
			}

			!isset($object['no-tag']) && $object['no-tag'] = false;
			!isset($object['strict']) && $object['strict'] = false;
			!isset($object['no-activation']) && $object['no-activation'] = false;
			!isset($object['class']) && $object['class'] = '';

			$path = Page::get_path();
			clear_this_url($url); clear_this_url($path);
			$object['class'] = explode(' ', $object['class']);

			$is_root     = $url == '/' && $path == '/';
			$is_selected = $url && !$object['no-activation'] && (($object['strict'] && ($url == $path || $url == $path.'/')) || (!$object['strict'] && strpos($path, $url) === 0));

			if ($is_root || ($url != '/' && $is_selected)) {
				$object['class'][] = 'link-selected';
			}

			$object['class'] = implode(' ', $object['class']);

			return (($object['no-tag'] && $path == $url) ?
					'<span class="link'.($object['class'] ? ' '.$object['class']:NULL).'">':
					'<a href="'.$url.'"'.\Tag::html_attrs('a', $object).'>'
				).$label.
				(($object['no-tag'] && $path == $url) ? '</span>':'</a>');
		}


		public static function icon_for($icon, $size=32, $url, $label = NULL, $object = array())
		{
			def($object['label'], '');
			def($object['label_left'], false);

			$object['title'] = $label;
			return self::link_for(
				($object['label_left'] && $object['label'] ? self::label_text($object['label']):'').
				self::icon($icon, $size).
				(!$object['label_left'] && $object['label'] ? self::label_text($object['label']):''), $url, $object);
		}


		public static function label_for($icon, $size=32, $label, $url, $object = array())
		{
			$object['label'] = $label;
			return \System\Template::icon_for($icon, $size, $url, $label, $object);
		}


		public static function label_right_for($icon, $size=32, $label, $url, $object = array())
		{
			$object['label'] = $label;
			$object['label_left'] = true;
			return \System\Template::icon_for($icon, $size, $url, $label, $object);
		}


		public static function label_text($label)
		{
			return '<span class="lt">'.$label.'</span>';
		}


		public static function heading($label, $save_level = true, $level = NULL)
		{
			if ($level === NULL) {
				$level = self::$heading_level+1;
			}

			if ($save_level) {
				self::set_heading_level($level);
				if ($level == 1) {
					self::$heading_section_level = 2;
				}
			}

			$tag = ($level > 6) ? 'strong':'h'.$level;
			$attrs = array(
				"id" => \System\Model\Database::gen_seoname($label)
			);
			return self::tag($tag, $label, $attrs);
		}


		public static function tag($tag, $content = '', array $attrs = array())
		{
			return '<'.$tag.' '.\Tag::html_attrs($tag, $attrs).'>'.$content.'</'.$tag.'>';
		}


		public static function section_heading($label, $level = NULL)
		{
			if ($level === NULL) {
				$level = self::$heading_section_level == 1 ? self::$heading_section_level++:self::$heading_section_level;
			}

			self::set_heading_level($level);

			return heading($label, true, $level);
		}


		public static function get_heading_level()
		{
			return self::$heading_level;
		}


		public static function set_heading_level($lvl)
		{
			return self::$heading_level = intval($lvl);
		}


		public static function set_heading_section_level($lvl)
		{
			return self::$heading_section_level = intval($lvl);
		}


		/** Format and translate datetime format
		 * @param mixed  $date
		 * @param string $format Format name
		 * @return string
		 */
		public static function format_date($date, $format = 'std')
		{
			if (is_null($date)) {
				$date = new \DateTime();
			}

			if ($date instanceof \DateTime) {
				$d = $date->format(\System\Locales::get('date:'.$format));
				return strpos($format, 'html5') === 0 ? $d:\System\Locales::translate_date($d);
			} elseif(is_numeric($date)) {
				$d = date(\System\Locales::get('date:'.$format), $date);
				return strpos($format, 'html5') === 0 ? $d:\System\Locales::translate_date($d);
			} else {
				return $date;
			}
		}


		public static function get_css_color($color)
		{
			if ($color instanceof ColorModel) {
				$c = $color->get_color();
			} elseif (is_array($color)) {
				$c = $color;
			} else {
				throw new \System\Error\Argument("Argument 0 must be instance of System\Model\Color or set of color composition");
			}

			return is_null($c[3]) ?
				'rgb('.$c[0].','.$c[1].','.$c[2].')':
				'rgba('.$c[0].','.$c[1].','.$c[2].','.str_replace(",", ".", floatval($c[3])).')';
		}


		public static function get_color_container($color)
		{
			if ($color instanceof ColorModel) {
				$c = $color->get_color();
			} elseif (is_array($color)) {
				$c = $color;
			} else {
				throw new \System\Error\Argument("Argument 0 must be instance of System\Model\Color or set of color composition");
			}

			return '<span class="color-container" style="background-color:'.self::get_css_color($c).'"></span>';
		}



		static function convert_value($type, $value)
		{
			switch($type){
				case 'information':
					$step = 1024;
					break;
				default:
					$step = 1000;
					break;
			}

			for($i=0; $value>=1024; $i++){
				$value /= 1024;
			}
			return round($value, 2)." ".self::$units[$type][$i];
		}


		/** Get configured or default icon theme
		 * @return string
		 */
		static function get_icon_theme()
		{
			$theme = cfg('icons', 'theme');
			return $theme ? $theme:self::DEFAULT_ICON_THEME;
		}


		/** Convert value to HTML parseable format
		 * @param mixed $value
		 * @return string
		 */
		public static function to_html($value)
		{
			if (is_object($value) && method_exists($value, 'to_html')) {
				return $value->to_html();
			}

			if ($value instanceof \DateTime) {
				return format_date($value, 'human');
			}

			if (gettype($value) == 'boolean') {
				$value = $value ? 'yes':'no';
				return \Tag::span(array(
					"output"  => false,
					"content" => l($value),
					"class"   => $value,
				));
			}

			if (gettype($value) == 'float') {
				return number_format($value, 5);
			}

			if (gettype($value) == 'string') {
				return htmlspecialchars_decode($value);
			}

			return $value;
		}


		/** Convert known objects to JSON
		 * @param mixed $value
		 * @param bool [true] Encode into JSON string
		 * @return array|string
		 */
		public static function to_json($value, $encode=true)
		{
			if (is_array($value)) {
				$values = array();

				foreach ($value as $key=>$item) {
					$values[$key] = self::to_json($item, false);
				}

				return $encode ? json_encode($values):$values;
			} else {
				if (is_object($value) && method_exists($value, 'to_json')) {
					return $value->to_json(false);
				}

				if ($value instanceof \DateTime) {
					return format_date($value, 'sql');
				}

				return $value;
			}
		}
	}
}
