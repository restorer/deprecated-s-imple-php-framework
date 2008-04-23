<?php

/*
 * [S]imple framework
 * 2007-2008 Zame Software Development (http://zame-dev.org)
 * All rights reserved
 *
 * Page class
 */

##
# .begin
# = class SControl
##
class SControl
{
	##
	# [$page] This control belongs to $page
	##
	var $page = null;

	##
	# [$vars] Template variables
	##
	var $vars = array();

	##
	# [$template_name] Full path to template
	##
	var $template_name = '';

	##
	# = void set_template(string $path_to_control)
	# Usage: **$this->set_template(__FILE__)**
	##
	function set_template($path_to_control)
	{
		$this->template_name = dirname($path_to_control) . '/' . basename($path_to_control, '.php') . '.tpl';
	}

	##
	# = mixed get_var(string $name, mixed $def='')
	##
	function get_var($name, $def='')
	{
		return (array_key_exists($name, $this->vars) ? $this->vars[$name] : $def);
	}

	##
	# = string attrs_str(array $attrs)
	# Encode array of attributes to html string
	##
	function attrs_str($attrs)
	{
		$res = '';
		foreach ($attrs as $k=>$v) $res .= ' '.$k.'="'.htmlspecialchars($v).'"';
		return $res;
	}

	##
	# = mixed take_attr(array &$attrs, string $name, mixed $def='')
	# Get attribute from **$attrs** array (attribute will removed from array)
	##
	function take_attr(&$attrs, $name, $def='')
	{
		if (array_key_exists($name, $attrs))
		{
			$res = $attrs[$name];
			unset($attrs[$name]);
			return $res;
		}

		return $def;
	}

	##
	# = string render_template()
	##
	function render_template()
	{
		if (!strlen($this->template_name)) error('SControl.render_template : please set $template_name variable');

		$tpl =& new STemplate();
		$tpl->vars =& $this->vars;
		$tpl->controls =& $this->page->controls;

		return $tpl->process($this->template_name);
	}

	##
	# = abstract string render(array $attrs)
	##
	function render($attrs)
	{
		dwrite('SControl.render : Please override render function');
		return '';
	}
}
##
# .end
##

?>