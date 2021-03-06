<?php

/** Email handling
 * @package system
 * @subpackage offcom
 */
namespace Helper\Offcom
{
	/** E-mail handling class
	 * @package system
	 * @subpackage offcom
	 * @uses System\Model\Attr
	 */
	class Mail extends \System\Model\Attr
	{
		const STATUS_SENT    = 1;
		const STATUS_READY   = 2;
		const STATUS_SENDING = 3;
		const STATUS_FAILED  = 4;


		/** Attributes */
		protected static $attrs = array(
			"subject"  => array("type" => 'varchar', "required" => true),
			"message"  => array("type" => 'text', "required" => true),
			"rcpt"     => array("type" => 'array', "required" => true),
			"headers"  => array("type" => 'array'),
			"from"     => array("type" => 'string', "is_null" => false),
			"reply_to" => array("type" => 'string', "is_null" => false),
			"status"   => array("type" => 'int', "is_unsigned" => true),
		);


		/** Headers that must be sent */
		protected static $default_headers = array(
			"Content-Type" => 'text/plain; charset=utf-8',
		);


		/** Create email object
		 * @param string $subject Subject encoded in UTF-8
		 * @param string $message Text message encoded in UTF-8
		 * @param array  $rcpt    Recipients in nice format
		 * @param string $from    E-mail address of sender in nice format
		 * @return new self
		 */
		public static function create($subject, $message, array $rcpt = null, $from = null)
		{
			if ($rcpt) {
				foreach ($rcpt as &$r) {
					$r = trim($r);
				}
			}

			return new self(array(
				"subject" => $subject,
				"message" => $message,
				"rcpt"    => $rcpt,
				"from"    => $from,
				"status"  => self::STATUS_READY,
			));
		}


		/** Create and immediately send a message
		 * @param string $subject Subject encoded in UTF-8
		 * @param string $message Text message encoded in UTF-8
		 * @param array  $rcpt    Recipients in nice format
		 * @param string $from    E-mail address of sender in nice format
		 * @return bool
		 */
		public static function post($subject, $message, array $rcpt, $from = null)
		{
			$msg = self::create($subject, $message, $rcpt, $from);
			return $msg->send();
		}


		/** Get set of default e-mail headers
		 * @return array
		 */
		public static function get_default_headers()
		{
			if (!isset(self::$default_headers['X-Mailer'])) {
				self::$default_headers["X-Mailer"] = \System\Status::introduce();
			}

			return self::$default_headers;
		}


		/** Get sender or default sender if not set
		 * @return string
		 */
		public function get_sender()
		{
			if (is_null($this->from)) {
				try {
					return \System\Settings::get('offcom', 'default', 'sender');
				} catch (\System\Error\Config $e) {
					return null;
				}
			}

			return $this->from;
		}


		/** Validate e-mail before sending
		 * @return bool
		 */
		private function validate()
		{
			foreach ($this->rcpt as $member) {
				if (!self::is_addr_valid($member)) {
					throw new \System\Error\Format(sprintf('Recipient "%s" is not formatted according to RFC 2822.', $member));
				}
			}

			if ($this->get_sender() !== null) {
				if (!self::is_addr_valid($this->get_sender())) {
					throw new \System\Error\Format(sprintf('Sender "%s" is not formatted according to RFC 2822.', $this->get_sender()));
				}
			}

			return true;
		}


		/** Get subject encoded with base64 and UTF-8
		 * @return string
		 */
		private function get_encoded_subject()
		{
			return '=?UTF-8?B?'.base64_encode($this->subject).'?=';
		}


		/** Send email message object
		 * @return int
		 */
		public function send()
		{
			try {
				$disable = \System\Settings::get('dev', 'disable', 'offcom');
			} catch (\System\Error\Config $e) {
				$disable = false;
			}

			try {
				$fallback_to = \System\Settings::get('offcom', 'mail_to');
			}  catch (\System\Error\Config $e) {
				$fallback_to = null;
			}

			$this->validate();

			$body = array();
			$headers_str = array();

			if ($fallback_to) {
				$rcpt = $fallback_to;
			} else {
				$rcpt = implode(', ', $this->rcpt);
			}

			$headers = $this->get_default_headers();
			$headers['From'] = $this->get_sender();
			$headers['Subject'] = $this->get_encoded_subject();

			if ($this->reply_to) {
				if (self::is_addr_valid($this->reply_to)) {
					$headers['Reply-To'] = $this->reply_to;
				} else throw new \System\Error\Format(sprintf('Reply-To "%s" is not formatted according to RFC 2822.', $this->reply_to));
			}

			foreach ($headers as $header=>$value) {
				$headers_str[] = ucfirsts($header, '-', '-').": ".$value;
			}

			$body[] = implode("\n", $headers_str)."\n";
			$body[] = strip_tags($this->message);
			$body = implode("\n", $body);
			$this->status = self::STATUS_SENDING;

			if (!$disable) {
				if (mail($rcpt, $this->get_encoded_subject(), '', $body)) {
					$this->status = self::STATUS_SENT;
				} else $this->status = self::STATUS_FAILED;
			}

			return $this->status;
		}


		/** Validate email address against RFC 2822
		 * @param string $email
		 * @param bool   $strict
		 * @return bool
		 */
		public static function is_addr_valid($email, $strict = false)
		{
			$ok = true;

			if (strpos($email, ',') !== false) {
				$email = explode(',', $email);
			} else {
				$email = array($email);
			}

			foreach ($email as $addr) {
				$regex = $strict ?
					'/^([.0-9a-z_+-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i':
					'/^([*+!.&#$|\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,})$/i';

				$ok = $ok && !!preg_match($regex, trim($addr), $matches);

				if (!$ok) {
					break;
				}
			}

			return $ok;
		}

	}
}
