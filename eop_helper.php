<?php

/**
 * @file
 * EOP  -  Embedded Option Parser
 *
 * Used to embed options in an arbitrary text, and to extract the UI for it.
 */

define('EOP_PREFIX', 'eop_');
$eop_name = null;
$eop_parameters = null;
$eop_options = null;

/**
 * Parses en arbitrary text containing possible option definitions, and
 * substitutes the defenitions with the provided parameters.
 *
 * @param[in] string $name
 *   Names the specific text, so that EOP can be used more than once per page.
 * @param[in] string $text
 *   Text containing possible option definitions.
 * @param[in] string $parameters
 *   Array with key value pairs, might be $_REQUEST ($prefixed should be set to
 *   true), or earlier returned $values ($prefixed should be set to false)
 * @param[in] string $prefixed
 *   boolean specifying if the $pararmeters keys are prefixed or not.
 * @param[out] array $options
 *   Options, names and values, and html to render the UI.
 * @param[out] array $values
 *   Key value pairs, store somewhere and to later feed again to EOP.
 * @param[in] array $attributes
 *   attributes of the generated HTML form tags
 *
 * @return Processed text.
 *
 * \code
 * $text = 'In een [kleur:type=list:values=groen,rood,blauw:default=groen]
 *   [kleur] [kleur] [kleur]
 *   [groente:type=list:options=knollen,bieten:default=knollen] [groente]land
 * Daar zaten [aantal:list=0|geen,2|twee,3|drie,veel:default=twee]
 *   [dier:radio=konijntjes,haasjes:default=haasjes], heel
 *   [stemming:radio=parmant,zielig:default=parmant]
 * En de een die [actie 1:type=list:options=blies,sloeg:default=blies] zijn
 *   [instrument 1:type=list:options=fluit,trommel:default=fluit]e-
 *   [instrument 1]e-[instrument 1],
 * En de ander [actie 2:type=list:options=blies,sloeg:default=sloeg] zijn
 *   [instrument 2:type=list:options=fluit,trommel:default=trommel].';
 *
 * $params=array(
 *   'kleur' => '1',
 *   'groente' => '1',
 *   'aantal' => '2',
 *   'dier' => '2',
 *   'stemming' => '1',
 *   'actie_1' => '1',
 *   'instrument_1' => '1',
 *   'actie_2' => '2',
 *   'instrument_2' => '2'
 * );
 *
 * $song = eop('song', $text, $params, false, $options, $values);
 *
 * \endcode
 *
 */
function eop($name, $text, $parameters, $prefixed, &$options, &$values,
	$attributes=null, $mode='extern', $vattributes=null
) {
	global $eop_name, $eop_parameters, $eop_options, $eop_attributes,
		$eop_vattributes, $eop_mode;

	$eop_name = eop_alias($name);
	$eop_parameters = $prefixed ? $parameters[EOP_PREFIX . $eop_name] : $parameters;
	$values = $eop_parameters;
	$eop_options = null;
	$eop_mode = $mode;
	$eop_attributes = null;

	if ($attributes)
		foreach ($attributes as $name => $value)
			$eop_attributes .= ' ' . $name . '="' . $value . '"';
	$eop_vattributes = null;

	if ($vattributes)
		foreach ($vattributes as $name => $value)
			$eop_vattributes .= ' ' . $name . '="' . $value . '"';

	$result = $text;
	$result = str_replace(
		array('\[', '\]'),
		array(':::start:::', ':::end:::'),
		$result);

	/*preload*/
	preg_replace('/\[([^\]]+)\]/sie', 'eop_process(\'\\1\', true)', $result);
	$result = preg_replace('/\[([^\]]+)\]/sie', 'eop_process(\'\\1\')', $result);
	$result = str_replace(
		array(':::start:::', ':::end:::'),
		array('[', ']'),
		$result);

	if ($eop_mode != 'inline' && $eop_mode != 'intern') $options = $eop_options;
	$eop_options = null;
	return $result;
}

/**
 * Clean up the name to make it fit for alias usage. Replaces tons of stuff with
 * underscores.
 *
 * @param mixed args You can feed it one or more arguments, which will all be
 * cleaned up.
 * @return mixed string or array
 **/
function eop_alias(/*var_args*/) {
	$args = func_get_args();

	$result = strtolower(str_replace(
		array(
			' ', "\t", "\n", '"', "'", ':', '/', '\\', '.', ',', '-', '+', '=',
			'~', '!', '@', '#', '$', '%', '^' . '&', '*', '(', ')'
		),
		'_',
		implode('_', $args)
	));
	while (strstr($result, '__')) $result = str_replace('__', '_', $result);
	return trim($result, '_');
}

/**
 * Horse around with the provided string a little, UCWording it if wanted.
 *
 * @param string $text input
 * @param bool $ucwords Wheter or not to run UCwords over stuff.
 * @return string Output.
 **/
function eop_pretty($text, $ucwords = true) {
	$text = trim($text);
	$text = str_replace(array(
		'_', 'axelref', 'mediaetc', 'paypal', 'codeigniter'
	) , array(
		' ', 'AxelRef', 'MediaEtc', 'PayPal', 'CodeIgniter'
	) , strtolower($text));
	$text = $ucwords ? ucwords($text) : ucfirst($text);
	$result = str_replace(array(
		'Id', 'Faq', 'Url', 'Pvc', 'Rvs', 'Cd', 'Dvd', 'Rom', 'Ep', 'Lp', 'Xmlrpc',
		'Xml', 'Rpc', 'Youtube', 'Rss'
	) , array(
		'id', 'FAQ', 'URL', 'PVC', 'RVS', 'CD', 'DVD', 'ROM', 'EP', 'LP', 'XML-RPC',
		'XML', 'RPC', 'YouTube', 'RSS'
	) , $text);

	if (substr($result, -3) === ' At') $result = substr($result, 0, -3) . ' at';

	if (substr($result, -3) === ' By') $result = substr($result, 0, -3) . ' by';
	$result = str_replace(' Of ', ' of ', $result);
	return $result;
}

/**
 * The heart and soul of EOP. This puppy interprets your actual options,
 * coughing up beautiful arrays of data.
 *
 * @param string $option Your actual option
 * @param bool $preloop Rendering conditionals takes an extra step
 * @return array Beautiful data
 **/
function eop_process($option, $preloop=false) {
	global $eop_name, $eop_parameters, $eop_options, $eop_attributes,
		$eop_vattributes, $eop_mode;

	$CI =& get_instance();
	$result = null;
	$default = null;
	$sep = ':';
	$param = array(
		'name' => null,
		'alias' => null,
		'type' => null,
		'value' => null,
		'prefix' => null,
		'args' => array(
			'type' => null,
			'values' => null,
			'default' => null,
			'options' => null
		),
		'html' => null
	);

	// Figure out what's used as separator
	if (preg_match('/^[\w \t\n]*(\W)/', $option, $matches)) $sep = $matches[1];

	// Split up by separator
	if ( ! $parts = explode($sep, $option)) {
		log_message('error', "(eop_process) Didn't get any parts.\noption=$option");
		return null;
	}

	// Shift off the first part; this will be our name.
	$param['name'] = eop_pretty(trim(array_shift($parts)));
	$param['alias'] = eop_alias($param['name']);

	foreach ($parts as $part) {
		// Split up the args if they contain = somewhere
		if (strpos($part, '=') !== FALSE) {
			list($key, $value) = explode('=', $part, 2);
			$param['args'][trim($key)] = trim($value);
		}
	}

	// We want to have a default value, if only in name
	if ( ! isset($param['args']['default']))
		$param['args']['default'] = null;

	// Same with glue, for checkboxes
	if ($param['args']['type'] == 'check' && ! isset($param['args']['glue']))
		$param['args']['glue'] = null;

	// If we have a set value for this one, use it. Otherwise revert to default
	// if it's there.
	$param['value'] = isset($eop_parameters[$param['alias']])
		? $eop_parameters[$param['alias']]
		: $param['args']['default'];

	// -------------------------------------------------------------------------
	// We now start picking apart the argument types.
	// -------------------------------------------------------------------------

	/**
	 *                                                                       IF
	 **/
	if (
		($param['args']['type'] == 'if' && $param['args']['condition'])
		|| isset($param['args']['if'])
	) {
		$param['type'] = 'if';
		$param['html'] = '<span' . $eop_vattributes . '>'
			. $param['args']['false'] . ' | ' . $param['args']['true']
			. '</span>';

		if ( ! $preloop) {
			$expr = $param['args']['if'];

			if ($eop_options)
				foreach ($eop_options as $p) {
					$quoted = ($p['type'] == 'string' || ! is_numeric($p['value'])) ? "'" : '';
					$expr = str_replace('$'.$p['alias'], $quoted.$p['value'].$quoted, $expr);
				}

			// IFs contain expressions, which will have to be eval()d.
			if ($bad = eop_bad_ops($expr)) {
				$param['value'] = '<span style="color:red">' . implode(', ', $bad)
					. '</span>';
			} else {
				if (eval('return ' . $expr . ';')) {
					$param['value'] = $param['args']['true'];
				} else {
					$param['value'] = $param['args']['false'];
				}
			}
			$param['html'] = '<span' . $eop_vattributes . '>' . $param['value']
				. '</span>';
		}

		// -------------------------------------------------------------------------

		/**
		 *                                                                      EXPR
		 **/
	} elseif ($param['args']['type'] == 'expr' ||
		isset($param['args']['expr'])
	) {
		$param['type'] = 'expr';
		$param['html'] = '<span' . $eop_vattributes . '>'
			. $param['args']['expr'] . '</span>';

		if ( ! $preloop) {
			$expr = $param['args']['expr'];

			// EXPR is all about expressions and eval()ing them...
			if ($eop_options)
				foreach ($eop_options as $p) {
					$expr = str_replace(
						'$' . $p['alias'],
						(($p['type'] == 'string' || !is_numeric($p['value'])) ? "'" : '')
						. $p['value']
						. (($p['type'] == 'string' || !is_numeric($p['value'])) ? "'" : ''),
							$expr
						);
				}

			if ($bad = eop_bad_ops($expr)) {
				$param['value'] = '<span style="color:red">' . implode(', ', $bad)
					. '</span>';
			} else {
				$param['value'] = eval('return ' . $expr . ';');
			}
			$param['html'] = '<span' . $eop_vattributes . '>' . $param['value']
				. '</span>';
		}

		// -------------------------------------------------------------------------

		/**
		 *                                                                      LIST
		 **/
	} elseif (
		(
			$param['args']['type'] == 'list' &&
			($param['args']['values'] || $param['args']['options'])
		) || isset($param['args']['list'])
	) {

		$param['type'] = 'list';
		$param['html'] = '<select name="' . EOP_PREFIX . $eop_name . '['
			. $param['alias'] . ']"' . $eop_attributes . '>';

		// The list values can be stored in either values=, options= or list=
		if ( ! ($values = $param['args']['values']))
			if ( ! ($values = $param['args']['options']))
				$values = $param['args']['list'];

		// Split up the options by comma / pipe
		if ($values = _eop_values_split($values)) {

			// if the default is non-numeric, find the index that's supposed to go
			// with it and set that.
			if ($param['args']['default'] && ! is_numeric($param['args']['default']))
				$param['args']['default'] = array_search($param['args']['default'], $values);

			// Loop through the options and HTMLize them
			foreach ($values as $value => $label) {
				// Decide upon selectedness of this value
				$selected = '';
				if ((
					isset($eop_parameters[$param['alias']]) &&
					$eop_parameters[$param['alias']] == eop_alias($value)
				) || (
					!isset($eop_parameters[$param['alias']]) &&
					isset($param['args']['default']) &&
					$param['args']['default'] == eop_alias($value)
				)) $selected = ' selected="selected"';

				// Cook up some HTML
				$param['html'] .= '<option value="' . eop_alias($value) . '"'
					. $selected . '>' . trim($label) . '</option>';
			}

			// Set the value to either the selected or the default one.
			if (isset($eop_parameters[$param['alias']]) && $param['value'])
				$param['value'] = $values[$param['value']];
			elseif (isset($values[$param['args']['default']]))
				$param['value'] = $values[$param['args']['default']];

		}
		$param['html'] .= '</select>';

		// -------------------------------------------------------------------------

		/**
		 *                                                                    RADIOS
		 **/
	} elseif (
		($param['args']['type'] == 'radio' && (
			$param['args']['values'] || $param['args']['options']
		)) || isset($param['args']['radio'])
	) {
		$param['type'] = 'radio';
		$param['html'] = '';

		// The actual options can be set in either values, options or radio
		if ( ! ($values = $param['args']['values']))
			if ( ! ($values = $param['args']['options']))
				$values = $param['args']['radio'];

		// Explode them values
		if ($values = _eop_values_split($values)) {
			if ($param['args']['default'] && ! is_numeric($param['args']['default']))
				$param['args']['default'] = array_search($param['args']['default'], $values);

			foreach ($values as $value => $label) {
				if ($param['html']) $param['html'] .= '&nbsp;';

				$checked = '';
				if ((
					isset($eop_parameters[$param['alias']]) &&
					eop_alias($value == $eop_parameters[$param['alias']])
				) || (
					! isset($eop_parameters[$param['alias']]) &&
					isset($param['args']['default']) &&
					$param['args']['default'] == eop_alias($value)
				)) $checked = ' checked="checked"';

				$param['html'] .= '<label><input type="radio" name="' . EOP_PREFIX
					. $eop_name . '[' . $param['alias'] . ']" value="'. eop_alias($value)
					. '"' . $checked . $eop_attributes . ' />&nbsp;' . $label
					. '</label>';
			}

			$param['value'] = $values[isset($eop_parameters[$param['alias']])
				? $param['value']
				: $param['args']['default']];
		}

		// -------------------------------------------------------------------------

		/**
		 *                                                                    CHECKS
		 **/
	} elseif (
		(
			$param['args']['type'] == 'check' &&
			(isset($param['args']['values']) || isset($param['args']['options']))
		) || isset($param['args']['check'])
	) {
		$param['type'] = 'check';
		$param['html'] = '<input type="hidden" name="' . EOP_PREFIX . $eop_name
			. '[' . $param['alias'] . ']" />';

		// Make sure we have values
		if ( ! ($values = $param['args']['values']))
			if ( ! ($values = $param['args']['options']))
				$values = $param['args']['check'];

		if ($values = _eop_values_split($values)) {

			// Checks have slightly more involved defaults, as there can be more
			// than one. This clearly shows that Highlander had nothing to do with
			// checkboxes.
			if ($param['args']['default']) {
				$param['args']['default'] = explode(',', $param['args']['default']);
			}

			foreach ($values as $value => $label) {
				if ($param['html']) $param['html'] .= '&nbsp;';

				$checked = '';
				if ((
					isset($eop_parameters[$param['alias']]) &&
					is_array($eop_parameters[$param['alias']]) &&
					in_array(eop_alias($value), $eop_parameters[$param['alias']])
				) || (
					! isset($eop_parameters[$param['alias']]) &&
					isset($param['args']['default']) &&
					is_array($param['args']['default']) &&
					in_array(eop_alias($value) , $param['args']['default'])
				) || (
					! isset($eop_parameters[$param['alias']]) &&
					isset($param['args']['default']) &&
					in_array(eop_alias($value) , $param['args']['default'])
				)) $checked = ' checked="checked"';

				$param['html'] .= '<label><input type="checkbox" name="'
					. EOP_PREFIX . $eop_name . '[' . $param['alias'] . '][]"
					value="' . eop_alias($value) . '"' . $checked . ''
					. $eop_attributes . ' />&nbsp;' . $label . '</label>';
			}

			$param['value'] = isset($eop_parameters[$param['alias']])
				? $param['value']
				: $param['args']['default'];

			// Cook up the final HTML
			if (is_array($param['value'])) {
				foreach ($param['value'] as $key => &$value) {
					// The textual rendering might use glue, if it's an array.
					$value = $values[isset($eop_parameters[$param['alias']])
						? $value
						: $param['args']['default'][$key]];
					$value = '<span ' . $eop_vattributes . '>' . $value . '</span>';
				}
				if (isset($param['args']['glue']) && $glue = $param['args']['glue'])
					$glue = ($glue == ',' ? '' : ' ') . $glue . ' ';
				else $glue = ', ';

				$param['value'] = implode($glue, $param['value']);
			}

			if ($param['args']['default'])
				$param['args']['default'] = implode(',', $param['args']['default']);
		}

		// -----------------------------------------------------------------------

		/**
		 *                                                                 NUMBERS
		 **/
	} elseif ($param['args']['type'] == 'number') {
		$param['type'] = 'number';
		$param['html'] = '<input type="text" name="' . EOP_PREFIX . $eop_name
			. '[' . $param['alias'] . ']" value="' . (
				isset($eop_parameters[$param['alias']])
				? $eop_parameters[$param['alias']]
				: $param['args']['default']
			) . '" size="4"' . $eop_attributes . ' />';

		// -----------------------------------------------------------------------

		/**
		 *                                                                  MONIES
		 **/
	} elseif ($param['args']['type'] == 'money') {
		$param['type'] = 'money';
		if ( ! isset($param['args']['currency'])) $param['args']['currency'] = null;

		switch ($param['args']['currency']) {
			case '£':
			case 'gbp':
			case 'pound':
				$param['prefix'] = '£ ';
				break;

			case '¥':
			case 'yen':
				$param['prefix'] = '¥ ';
				break;

			case '$':
			case 'usd':
			case 'dollar':
				$param['prefix'] = '$ ';
				break;

			case '€':
			case 'eur':
			case 'euro':
			default:
				$param['prefix'] = '&euro; ';
				break;
		}
		$param['html'] = $param['prefix'] . '<input type="text" name="' .
			EOP_PREFIX . $eop_name . '[' . $param['alias'] . ']" value="' . (
				isset($eop_parameters[$param['alias']])
				? $eop_parameters[$param['alias']]
				: $param['args']['default']
			) . '" size="4"' . $eop_attributes . ' />';

		// -----------------------------------------------------------------------

		/**
		 *                                                         EVERYTHING ELSE
		 **/
	} else {
		$param['type'] = 'string';
		$param['html'] = '<input type="text" name="' . EOP_PREFIX . $eop_name
			. '[' . $param['alias'] . ']" value="' . (
				isset($eop_parameters[$param['alias']])
				? $eop_parameters[$param['alias']]
				: $param['args']['default']
			) . '"' . $eop_attributes . ' />';
	}

	if ($param['name']) {
		if (isset($eop_options[$param['alias']]) && (
			strlen(json_encode($param)) < strlen(json_encode($eop_options[$param['alias']]))
		)) {
			$param = $eop_options[$param['alias']];
			$shadow = true;
		} else {
			$eop_options[$param['alias']] = $param;
			$shadow = false;
		}
	}

	if ($eop_mode == 'develop') {
		$result = $param['html'] . '&nbsp;&nbsp;&nbsp;<span style="color:silver">[' . $option . ']</span>';
	} elseif (($eop_mode == 'inline' || $eop_mode == 'intern') && !$shadow) {
		$result = $param['html'];
	} else {

		if (isset($param['value'])) $result = '<span' . $eop_vattributes . '>'
			. $param['prefix'] . $param['value'] . '</span>';
	}


	return $result;
}

/**
 * Parse potentially eval'able code for illegal function calls
 *
 * @param string $str input
 * @return array $parseErrors
 **/
function eop_bad_ops($str) {

	// allowed functions:
	$allowedCalls = array('explode', 'implode', 'date', 'time', 'round', 'trunc',
		'rand', 'ceil', 'floor', 'srand', 'strtolower', 'strtoupper', 'substr',
		'stristr', 'strpos', 'print', 'print_r');

	// check if there are any illegal calls
	$parseErrors = array();
	$tokens = token_get_all('<?php ' . $str);
	$vcall = '';

	foreach ($tokens as $token) {

		if (is_array($token)) {
			$id = $token[0];

			switch ($id) {
				case (T_VARIABLE):
					$vcall .= 'v';
					break;
				case (T_CONSTANT_ENCAPSED_STRING):
					$vcall .= 'e';
					break;
				case (T_STRING):
					$vcall .= 's';
				case (T_REQUIRE_ONCE):
				case (T_REQUIRE):
				case (T_NEW):
				case (T_RETURN):
				case (T_BREAK):
				case (T_CATCH):
				case (T_CLONE):
				case (T_EXIT):
				case (T_PRINT):
				case (T_GLOBAL):
				case (T_ECHO):
				case (T_INCLUDE_ONCE):
				case (T_INCLUDE):
				case (T_EVAL):
				case (T_FUNCTION):
				case (T_GOTO):
				case (T_USE):
				case (T_DIR):
					if (array_search($token[1], $allowedCalls) === false)
						$parseErrors[] = 'Illegal call: ' . $token[1] . '()';
				}
		} else {
			$vcall .= $token;
		}
	}

	// check for dynamic functions
	if (stristr($vcall, 'v(') != '') $parseErrors[] = array(
		'Illegal dynamic function call'
	);
	return $parseErrors;
}

/**
 * Splits up options. Options are comma-delimited, and each option is optionally
 * (hurr hurr) split in a value|label pair. Usually this is done with a pipe.
 *
 * @author Max Roeleveld
 * @date do 29 sep 13:51:46 2011
 * @param string $values The comma-separated values to clean up
 * @param string $delimited The in-option split character.
 **/
function _eop_values_split($values, $delimiter='|') {
	// If there's not a comma in there, we can stop bothering right away.
	if (strpos($values, ',') === FALSE) return null;

	$idx = 0; // internal index counter
	$return = array(); // export array
	$values = explode(',', $values);

	foreach ($values as $value) {
		$idx++;

		// Remove loose spaces and pipes
		trim(trim($value, '|'));

		// See if there's a pipe in the middle, split on it, divide
		// value/label pairs. If there's no pipe, use the index for value.
		if (strpos($value, '|') !== FALSE) {
			// Value contains pipes, let's handle it as such
			list($value, $label) = explode('|', $value, 2);
			if ( ! $label) $label = $value; // fallback
		} else {
			$label = $value;
			$value = $idx;
		}
		$return[$value] = $label;
	}

	return $return;
} // end function

/**
 * Strips commas from within the option blocks, but leaves other commas alone.
 *
 * @author Max Roeleveld
 * @date vr 15 jul 16:06:03 2011
 *
 * @param string $text The text to be decommafied
 * @return string The decommafied text
 */
function eop_strip_commas($text = null) {
	return preg_replace_callback('/\[[^]]+\]/', '_eop_strip_commas_cb', $text);
}

function _eop_strip_commas_cb($match) {
	$match = str_replace(', ', ',', $match[0]);
	$match = str_replace(' ,', ',', $match);
	return $match;
}

/**
 * Help! Returns a Markdown()d version of the help, to enlighten the users.
 **/
function eop_help() {
	$CI = & get_instance();
	$CI->load->helper('markdown');
	$help = '
LIST
====

`values` is een kommagescheiden lijst. Elke value is ofwel de daadwerkelijke
waarde, of combinatie van waarde|weergave-waarde.

* [kleur:type=list:values=groen,rood,blauw:default=groen]
* [smaak:type=list:values=drop|Dropjes,salmiak|Salmiak,menthol|Menthol:default=menthol]

RADIO
-----
* [dier:radio=konijntjes,haasjes:default=haasjes]

CHECK
-----
* [aspect:check=snel,handig,voordelig:default=snel,voordelig:glue=én]

TEXT
----
* [naam:default=Joris]

NUMBER
------
* [fietsen:type=number:default=2]

MONEY
-----
* [price_hotel:type=money:default=15:currency=euro]

IF
--
* [if:if=$fietsen != 1:true=fietsen:false=fiets]

EXPR
----------
* [expr:expr=$fietsen-1]
';
return (Markdown(eop('help', $help, array() , true, $o, $v, array(
	'disabled' => 'disabled'
) , 'develop')));
}

