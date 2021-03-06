<?php

namespace System
{
	class Url
	{
		/** Replace bad characters and generate seoname from string
		 * @param string $str
		 * @return string
		 */
		public static function gen_seoname($str)
		{
			$str = strtolower(strip_tags(iconv('UTF-8', 'US-ASCII//TRANSLIT', $str)));
			$str = preg_replace('/\s/', '-', $str);
			$str = preg_replace('/[^a-zA-Z0-9_-]/', '', $str);
			$str = preg_replace('/\-+/', '-', $str);
			return $str;
		}


		/** Generate ID from seoname (replace dashes to underscores)
		 * @param string $str
		 */
		public static function gen_id($str)
		{
			return str_replace('-', '_', self::gen_seoname($str));
		}


		/** Returns model ID from URL
		 * @param string $str
		 * @return int ID
		 */
		public static function get_seoid($str)
		{
			$str = explode('-', $str);
			return (int) end($str);
		}

	}
}
