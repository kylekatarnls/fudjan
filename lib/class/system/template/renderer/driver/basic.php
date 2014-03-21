<?

namespace System\Template\Renderer\Driver
{
	class Basic extends \System\Template\Renderer\Driver
	{
		public function render_template($path, array $locals = array())
		{
			$name = str_replace('/', '-', $path);
			extract($locals);

			ob_start();
			require $path;
			$cont = ob_get_contents();
			ob_end_clean();
			return '<div class="template '.$name.'">'. $cont.'</div>';
		}


		public function get_suffix()
		{
			return 'php';
		}
	}
}