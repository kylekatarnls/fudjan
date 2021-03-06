<?php

/** System locale settings
 * @package system
 */
namespace System
{
	/** System locale settings
	 * @package system
	 */
	class Locales
	{
		const DIR = '/etc/locales';
		const DIR_CACHE = '/var/cache/locales';

		const ENCODING = 'UTF-8';
		const LANG_DEFAULT = 'en';
		const TZ_DEFAULT = 'Europe/Prague';

		const TRANS_NONE = 0;
		const TRANS_STD  = 1;
		const TRANS_INF  = 2;


		/** Loaded files */
		private $files = array();

		private $locale;
		private $lang;
		private $date_trans;
		private $response;

		/** Loaded messages */
		private $messages = array();

		/** Static messages */
		private static $messages_static = array(
			"std"        => 'D, d M Y G:i:s e',
			"sql"        => 'Y-m-d H:i:s',
			"sql-date"   => 'Y-m-d',
			"sql-time"   => 'H:i:s',
			"html5"      => 'Y-m-d\\TH:i:s',
			"html5-full" => 'Y-m-d\\TH:i:sP',
		);

		protected static $dates = array(
			'days' => array(
				'normal' => array(
					"1" => "monday",
					"2" => "tuesday",
					"3" => "wednesday",
					"4" => "thursday",
					"5" => "friday",
					"6" => "saturday",
					"7" => "sunday"
				),

				'short' => array(
					"1" => "mon",
					"2" => "tue",
					"3" => "wed",
					"4" => "thu",
					"5" => "fri",
					"6" => "sat",
					"7" => "sun"
				),
			),

			'months' => array(
				'normal' => array(
					"1" => "january",
					"2" => "february",
					"3" => "march",
					"4" => "april",
					"5" => "may",
					"6" => "june",
					"7" => "july",
					"8" => "august",
					"9" => "september",
					"10" => "october",
					"11" => "november",
					"12" => "december"
				),

				'short' => array(
					"1" => "jan",
					"2" => "feb",
					"3" => "mar",
					"4" => "apr",
					"5" => "may",
					"6" => "jun",
					"7" => "jul",
					"8" => "aug",
					"9" => "sep",
					"10" => "oct",
					"11" => "nov",
					"12" => "dec"
				)
			)
		);

		private static $attrs_common = array('author', 'created_at', 'updated_at');


		/** Class init. Inits mb extension and sets default timezone for dates
		 * @return void
		 */
		public static function init()
		{
			mb_language('uni');
			mb_internal_encoding(self::ENCODING);
			date_default_timezone_set(self::get_default_timezone());
		}


		/** Get default language, if settings fail, skip
		 * @return string
		 */
		public static function get_default_lang()
		{
			try {
				return \System\Settings::get('locales', 'default_lang');
			} catch (\System\Error\Config $e) {
				return self::LANG_DEFAULT;
			}
		}


		/** Get default language, if settings fail, skip
		 * @return string
		 */
		public static function get_default_timezone()
		{
			try {
				return cfg('locales', 'timezone');
			} catch (\System\Error\Config $e) {
				return self::TZ_DEFAULT;
			}
		}


		public static function create(\System\Http\Response $response, $locale)
		{
			$obj = new self();
			$obj->response = $response;

			return $obj->set_locale($locale);
		}


		/** Set a locale to object
		 * @param string $locale
		 * @return $this
		 */
		public function set_locale($locale = null)
		{
			$this->locale = is_null($locale) ? self::get_default_lang():$locale;
			return $this;
		}


		public function make_syswide()
		{
			setlocale(LC_ALL, $this->locale.'.'.self::ENCODING);
			return $this;
		}


		/** Get language description
		 * @return string
		 */
		public function get_lang()
		{
			return $this->locale;
		}


		public static function get_available()
		{
			try {
				$list = \System\Settings::get('locales', 'allowed');
			} catch (\System\Error\Config $e) {
				$list = array('en');
			}

			return $list;
		}


		/** Get list of all loaded locale files
		 * @return array
		 */
		public function get_loaded_files()
		{
			return $this->files;
		}


		/** Get locale module data
		 * @param string      $str        Callstring
		 * @param null|string $force_lang Use this language
		 * @return mixed
		 */
		public function get_path($str)
		{
			$src = &$this->messages[$this->get_lang()];

			if (isset($src[$str])) {
				return $src[$str];
			}

			if (isset(self::$messages_static[$str])) {
				return self::$messages_static[$str];
			}

			return null;
		}


		/** Get all loaded messages for language
		 * @param string $lang
		 * @return array
		 */
		public function get_messages($locale = null)
		{
			return is_null($locale) ? $this->messages:$this->messages[$locale];
		}


		/** Translate string
		 * @param string       $str
		 * @param array|string $args Arguments to paste into the string. If not array, all arguments after str are written inside.
		 * @return string
		 */
		public function trans($str, $args = null, $lang = null)
		{
			$lang = is_null($lang) ? $this->get_lang():$lang;

			if (!isset($this->messages[$lang])) {
				$this->messages[$lang] = array();
			}

			$msg = isset($this->messages[$lang][$str]) ? $this->messages[$lang][$str]:$str;

			if (is_array($args) || (!is_null($args) && func_num_args() > 1)) {
				if (!is_array($args)) {
					$args = func_get_args();
					array_shift($args);
				}

				return stprintf($msg, $args);
			} else return $msg;
		}


		/** Prepare data for translating datetimes
		 * @return $this
		 */
		private function load_date_translations()
		{
			if (is_null($this->date_trans)) {
				$this->date_trans = array(
					"find" => array_merge(
						$this::$dates['days']['normal'],
						$this::$dates['days']['short'],
						$this::$dates['months']['normal'],
						$this::$dates['months']['short']
					),

					"replace" => array_merge(
						(array) $this->trans('days'),
						(array) $this->trans('days-short'),
						(array) $this->trans('months'),
						(array) $this->trans('months-short')
					),

					"replace_hard" => array_merge(
						(array) $this->trans('days'),
						(array) $this->trans('days-short'),
						(array) $this->trans('months-date'),
						(array) $this->trans('months-short')
					),
				);
			}

			return $this;
		}


		/** Translate date by locale standards
		 * @param string $date
		 * @param bool $hard
		 * @return string
		 */
		public function translate_date($date, $hard = false)
		{
			$this->load_date_translations();

			$replace_key = 'replace';

			if ($hard) {
				$replace_key = 'replace_hard';
			}

			return str_replace($this->date_trans['find'], $this->date_trans[$replace_key], strtolower($date));
		}


		/** Load all messages by language
		 * @param string $lang
		 * @return void
		 */
		public function load_messages($locale = null)
		{
			if (is_null($locale)) {
				$locale = $this->locale;
			}

			if (!isset($this->messages[$locale])) {
				$list = $this::get_available();

				if (in_array($locale, $list)) {
					$file = BASE_DIR.self::DIR_CACHE.'/'.$locale.'.json';

					if (file_exists($file)) {
						$this->messages[$locale] = \System\Json::read($file);
					} else {
						$this->messages[$locale] = \System\Settings::read(self::DIR.'/'.$locale, false, $this->files);
					}
				} else {
					throw new \System\Error\Locales(sprintf('Unknown language %s', $locale));
				}
			}

			return $this;
		}


		/** Calculate binary length of UTF-8 string
		 * @param string $str
		 * @return int
		 */
		public static function strlen_binary($str)
		{
			$strlen_var = strlen($str);
			$d = 0;

			for ($c = 0; $c < $strlen_var; ++$c) {
				$ord_var_c = ord($str{$d});

				switch (true) {
					case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
						$d++;
						break;

					case (($ord_var_c & 0xE0) == 0xC0):
						$d+=2;
						break;

					case (($ord_var_c & 0xF0) == 0xE0):
						$d+=3;
						break;

					case (($ord_var_c & 0xF8) == 0xF0):
						$d+=4;
						break;

					case (($ord_var_c & 0xFC) == 0xF8):
						$d+=5;
						break;

					case (($ord_var_c & 0xFE) == 0xFC):
						$d+=6;
						break;

					default:
						$d++;
				}
			}

			return $d;
		}


		/** Format and translate datetime format
		 * @param DateTime|int|null $date      Date to format. Takes current time if null.
		 * @param string            $format    Format name or format directly
		 * @param int               $translate 0 for no translation, 1 for standart translation, 2 for special translation
		 * @return string
		 */
		public function format_date($date, $format = 'std', $translate = self::TRANS_STD)
		{
			if (\System\Template::is_date($date)) {
				if (is_null($date)) {
					$date = new \DateTime();
				} elseif (is_numeric($date)) {
					$helper = new \DateTime();
					$helper->setTimestamp($date);
					$date = $helper;
				}

				$local_format = $this->trans('date-format-'.$format);
				$d = $date->format(is_null($local_format) ? $format:$local_format);

				if ($translate == self::TRANS_NONE) {
					return $d;
				} else {
					return $this->translate_date($d, $translate == self::TRANS_INF);
				}

			} else throw new \System\Error\Argument(sprintf("Method format_date accepts only date type arguments. Instance of DateTime or utime number. '%s' was given.", gettype($date)));
		}


		/** Get class translation from class format
		 * @param string $class_name Class name in class format
		 * @param bool   $plural     Return plural
		 * @return string
		 */
		public function trans_class_name($class_name, $plural = false)
		{
			return $this->trans('model-'.\System\Loader::get_link_from_class($class_name).($plural ? '-plural':''));
		}



		/** Get translated attribute name
		 * @param string $model
		 * @param string $attr
		 * @return string
		 */
		public function trans_model_attr_name($model, $attr)
		{
			return $this->trans($this->get_common_attr_trans_name($model, $attr));
		}


		/** Get translated attribute description
		 * @param string $model
		 * @param string $attr
		 * @return string
		 */
		public function trans_model_attr_desc($model, $attr)
		{
			return $this->trans('attr-'.\System\Loader::get_link_from_class($model).'-'.$attr.'-desc');
		}


		public function has_msg($msg)
		{
			return isset($this->messages[$this->get_lang()][$msg]);
		}


		/** Get string constant for common attributes
		 * @param string $model
		 * @param string $attr
		 * @return string
		 */
		public function get_common_attr_trans_name($model, $attr)
		{
			$key   = 'attr-'.$attr;
			$model = \System\Loader::get_model_from_class($model);
			$full  = 'model-'.$model.'-'.$key;

			return $this->has_msg($full) ? $full:$key;
		}
	}
}
