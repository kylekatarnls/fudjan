<?

try {
	$this->req('ident');
} catch(\System\Error\Argument $e) {
	$this->req('id');
}

def($template, 'system/text/show');
def($show_heading, true);

$conds = array();

if (isset($ident)) {
	$conds['ident'] = $ident;
} else {
	$conds['id'] = $id;
}

if ($text = get_first('\System\Text', $conds)->fetch()) {

	$this->partial($template, array(
		"text" => $text,
		"show_heading" => $show_heading,
	));

} else {
	if (cfg('dev', 'debug', 'backend')) {
		throw new \System\Error\Config('Text was not found', isset($ident) ? $ident:$id);
	} else {
		throw new \System\Error\NotFound();
	}
}
