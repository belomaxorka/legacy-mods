<?php

/**
 * Auth via uLogin.ru
 * @package phpBB
 * @subpackage uLogin MOD
 * @author uLogin team@ulogin.ru http://ulogin.ru/
 * @license GPL3
 */

require_once(INC_DIR .'class.JSON.php'); // http://pear.php.net/pepr/pepr-proposal-show.php?id=198

class uLogin
{
	private $user = NULL; // uLogin user data

	private $max_level = 5; // max nesting level (method: __fetch_random_name)

	public function __construct()
	{
		$this->get_user();
	}

	/**
	 * Get current user email or generate random
	 *
	 * @access 	private
	 * @param 	bool 		$random		if true will generate random email
	 * @return 	string				return email
	 */
	public function email($random = false)
	{
		if (!empty($this->user['email']))
		{
			if ($user = DB()->fetch_row("SELECT * FROM ". BB_USERS ." WHERE user_email = '". DB()->escape($this->user['email']) ."'"))
			{
				//return $this->email(true);
			}

			return $this->user['email'];
		}

		return '';
	}

	/**
	 * Get current user name or generate random
	 *
	 * @access 	private
	 * @param 	string 		$name		if set will append random string
	 * @param	int		$level		the higher the value the more random string will be in result
	 * @return 	string				return user name
	 */
	public function name($name = '', $level = 0)
	{
		if ($level == $this->max_level)
		{
			return '';
		}

		if ($name)
		{
			$name = $name . $this->random(1);
		}
		else if (!empty($this->user['first_name']) && !empty($this->user['last_name']))
		{
			$name = $this->user['first_name'] . ' ' . $this->user['last_name'];
		}
		elseif (!empty($this->user['email']) && preg_match('/^(.+)\@/i', $this->user['email'], $nickname))
		{			$name = $nickname[1];;		}
		else if (!empty($this->user['first_name']))
		{
			$name = $this->user['first_name'];
		}
		else if (!empty($this->user['last_name']))
		{
			$name = $this->user['last_name'];
		}
		else
		{
			return;
		}

		if ($user = DB()->fetch_row("SELECT * FROM ". BB_USERS ." WHERE username = '". DB()->escape($name) ."'"))
		{
			return $this->name($name, ($level + 1));
		}

		return $name;
	}

	/**
	 * Get current user location (city/country)
	 *
	 * @access 	private
	 * @return 	string				return user location
	 */
	public function from()
	{
		if (!empty($this->user['country']) && !empty($this->user['city']))
		{
			return ucfirst(strtolower($this->user['country'])) . ', ' . ucfirst(strtolower($this->user['city']));
		}
		else if (!empty($this->user['country']))
		{
			return ucfirst(strtolower($this->user['country']));
		}
		else if (!empty($this->user['city']))
		{
			return ucfirst(strtolower($this->user['city']));
		}

		return '';
	}

    /**
     * Read response with available wrapper
     *
     * @access private
     * @return string
     */
	private function get_response($url = "")
	{
		$s = array("error" => "file_get_contents or curl required");

		if (in_array('curl', get_loaded_extensions()))
		{
			$request = curl_init($url);
			curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($request, CURLOPT_BINARYTRANSFER, 1);
			$result = curl_exec($request);
			$s = $result ? $result : $s;
		}
		elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen'))
		{
			$result = file_get_contents($url);
			$s = $result ? $result : $s;
		}

		return $s;
	}

    public function password($len=6, $char_list='a-z,0-9')
    {
		$chars = array();

		$chars['a-z'] = 'qwertyuiopasdfghjklzxcvbnm';
		$chars['A-Z'] = strtoupper($chars['a-z']);
		$chars['0-9'] = '0123456789';
		$chars['~'] = '~!@#$%^&*()_+=-:";\'/\\?><,.|{}[]';

		$charset = $password = '';

		if (!empty($char_list))
		{
			$char_types = explode(',', $char_list);

			foreach ($char_types as $type)
			{
				if (array_key_exists($type, $chars))
				{
					$charset .= $chars[$type];
				}
				else
				{
					$charset .= $type;
				}
			}
		}

		for ($i=0; $i<$len; $i++)
		{
			$password .= $charset[ rand(0, strlen($charset)-1) ];
		}

		return $password;
	}

    /**
	 * Get user from ulogin.ru by token
	 *
	 * @access 	private
	 * @return 	mixed				if token expired or some errors occurred will return NULL else will return user data
	 */
	private function get_user()
	{
		if ($this->user)
		{
			return $this->user;
		}

		if ($_POST['token'])
		{
			$info = $this->get_response('http://ulogin.ru/token.php?token='. $_POST['token']);

            $data = array();

			if (function_exists('json_decode'))
			{
                $this->user = json_decode($info, true);
			}
			else
			{
				require_once (INC_DIR .'class.JSON.php');

				$json = new Services_JSON();
				$this->user = $json->decode($info, true);
			}

            return $this->user;
		}

		return null;
	}

	/**
	 * Generate random string
	 *
	 * @access 	private
	 * @param	int		$length		length of generating string
	 * @return 	string				return generated string
	 */
	public function random($length = 10)
	{
		$random = '';

		for ($i = 0; $i < $length; $i++)
		{
			$random += chr(rand(48, 57));
		}

		return $random;
	}

	/**
	 * Auth user
	 *
	 * @access 	public
	 * @return 	bool				if user authorized return true, else return false
	 */
	public function auth()
	{
		if(empty($this->user['email'])) return false;

		if (!$row = DB()->fetch_row("SELECT * FROM bb_ulogin WHERE identity = '". DB()->escape($this->user['identity']) ."'"))
		{
			return false;
		}

		if (!$user = DB()->fetch_row("SELECT * FROM ". BB_USERS ." WHERE user_id = ". $row['userid']))
		{
			DB()->query("DELETE FROM bb_ulogin WHERE userid = ". $row['userid']);
			return false;
		}

		return $user;
	}

	public function identity()
	{
		return !empty($this->user['identity']) ? $this->user['identity'] : '';
	}
}

?>
