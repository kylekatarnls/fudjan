<?

namespace System\Database
{
	class Result
	{
		private $res;
		private $free = true;
		private $first  = false;

		public function __construct($resource)
		{
			$this->res = is_object($resource) && get_class($resource) == 'mysqli_result' ? $resource:null;
		}


		public function fetch()
		{
			$result = array();

			if ($this->res !== null) {
				$result = $this->res->fetch_assoc();
			}

			$this->free && $this->res->free();
			return $result;
		}


		public function fetch_assoc($key = null, $value = null)
		{
			$result = array();

			if ($this->res !== null) {
				while ($data = $this->res->fetch_assoc()) {
					$d = is_null($value) ? $data:$data[$value];

					if (is_null($key)) {
						$result[] = $d;
					} else {
						$result[$data[$key]] = $d;
					}

					if ($this->first) break;
				}
			}

			$this->first && $result = $result[0];
			$this->free && $this->res->free();
			return $result;
		}


		public function fetch_model($model, $key = null)
		{
			if (!is_string($model)) throw new ArgumentException('Model name must be a string', $model);
			$result = array();

			if ($this->res !== null) {
				while ($data = $this->res->fetch_assoc()) {
					if (is_null($key)) {
						$result[] = new $model($data);
					} else {
						$result[$data[$key]] = new $model($data);
					}	
					if ($this->first) break;
				}
			}

			$this->first && $result = $result[0];
			$this->free && $this->res->free();
			return $result;
		}


		public function &nofree()
		{
			$this->free = false;
			return $this;
		}


		public function &first()
		{
			$this->first = true;
			return $this;
		}
	}
}
