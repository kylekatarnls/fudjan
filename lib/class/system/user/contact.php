<?php

/** User contacts
 * @package system
 * @subpackage users
 */
namespace System\User
{
	/** User contact model
	 */
	class Contact extends \System\Model\Perm
	{
		const ALLOW_RELATION_DELETE = true;

		const STD_EMAIL   = 1;
		const STD_MOBILE  = 2;
		const STD_WEBSITE = 3;
		const STD_PHONE   = 4;

		const IM_XMPP_JABBER = 5;
		const IM_XMPP_GTALK  = 6;
		const IM_AIM   = 7;
		const IM_ICQ   = 8;
		const IM_MSN   = 9;
		const IM_YAHOO = 10;

		const SOCIAL_FACEBOOK    = 11;
		const SOCIAL_TWITTER     = 12;
		const SOCIAL_GOOGLE_PLUS = 13;

		protected static $attrs = array(
			"user" => array("type" => 'belongs_to', "model" => 'System\User'),
			"type"    => array(
				"type" => 'int',
				"is_unsigned" => true,
				"options" => array(
					self::STD_EMAIL          => 'contact_type_email',
					self::STD_MOBILE         => 'contact_type_mobile',
					self::STD_WEBSITE        => 'contact_type_website',
					self::STD_PHONE          => 'contact_type_phone',
					self::IM_XMPP_JABBER     => 'contact_type_xmpp_jabber',
					self::IM_XMPP_GTALK      => 'contact_type_xmpp_gtalk',
					self::IM_AIM             => 'contact_type_aim',
					self::IM_ICQ             => 'contact_type_icq',
					self::IM_MSN             => 'contact_type_msn',
					self::IM_YAHOO           => 'contact_type_yahoo',
					self::SOCIAL_FACEBOOK    => 'contact_type_facebook',
					self::SOCIAL_TWITTER     => 'contact_type_twitter',
					self::SOCIAL_GOOGLE_PLUS => 'contact_type_google_plus',
				)),
			"ident"  => array("type" => 'varchar'),
			"name"   => array("type" => 'varchar'),
			"spam"   => array("type" => 'bool'),
			"public" => array("type" => 'bool'),
		);

		private static $internal_types = array(
			'email'  => self::STD_EMAIL,
			'mobile' => self::STD_MOBILE,
		);


		protected static $access = array(
			'schema' => true,
		);


		public static function get_type_options()
		{
			return self::get_attr_options('type');
		}


		public function get_type_name($trans = false)
		{
			$opts = self::get_type_options();
			return str_replace('contact_type_', '', $opts[$this->type]);
		}


		public function to_html(\System\Template\Renderer $ren)
		{
			return $this->name ?
				sprintf('%s (%s): %s', $ren->locales()->trans($this->get_type_name()), $this->name, $this->ident):
				sprintf('%s: %s', $ren->locales()->trans($this->get_type_name()), $this->ident);
		}
	}
}
